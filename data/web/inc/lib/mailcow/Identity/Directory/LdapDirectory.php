<?php

declare(strict_types=1);

namespace Mailcow\Identity\Directory;

use LdapRecord\Connection;
use Mailcow\Identity\Model\DirectoryGroup;
use Mailcow\Identity\Model\DirectoryUser;
use Mailcow\Identity\Model\Listing;
use Mailcow\Identity\Model\Settings;

/**
 * LDAP / Active Directory users and the groups matching the group filter.
 *
 * Searches run on the raw connection with paged results and check the result code of every page, because
 * LdapRecord's paginator stops silently on size limits. Group members come from memberOf (default), from the
 * member attribute of the groups, or from the AD in-chain rule (nested groups).
 */
class LdapDirectory implements Directory
{
    private const PAGE = 500;
    private const ID_ATTRIBUTES = ['objectguid', 'entryuuid', 'nsuniqueid', 'ipauniqueid'];
    private const AD_IN_CHAIN = '1.2.840.113556.1.4.1941';
    /** userAccountControl: ACCOUNTDISABLE */
    private const UAC_DISABLED = 0x2;

    public function __construct(private ?Connection $connection, private Settings $settings)
    {
    }

    public function listAll(bool $withGroups): Listing
    {
        $s = $this->settings;
        $userFilter = $this->userFilter();
        $disabled = [];
        if ($s->disabledFilter !== '') {
            foreach ($this->search($s->basedn, '(&' . $userFilter . self::wrap($s->disabledFilter) . ')', ['dn']) as $entry) {
                $disabled[strtolower((string)$entry['dn'])] = true;
            }
        }
        $attributes = array_merge([$s->usernameField, 'displayname', 'cn', 'useraccountcontrol'],
            ($s->attributeField !== '') ? [$s->attributeField] : [],
            self::idAttributes($s->idAttribute),
            ($withGroups && $s->groupMemberMode === 'memberof') ? ['memberof'] : []);
        $users = [];
        $memberOf = [];
        foreach ($this->search($s->basedn, $userFilter, array_values(array_unique($attributes))) as $entry) {
            $dn = strtolower((string)$entry['dn']);
            $email = self::first($entry, $s->usernameField);
            $uac = self::first($entry, 'useraccountcontrol');
            $user = new DirectoryUser(self::entryId($entry, $s->idAttribute),
                ($email === null) ? null : strtolower($email),
                self::first($entry, 'displayname') ?? self::first($entry, 'cn') ?? '',
                !isset($disabled[$dn]) && !($uac !== null && ((int)$uac & self::UAC_DISABLED)),
                ($s->attributeField !== '') ? self::values($entry, $s->attributeField) : [],
                $dn);
            $users[$user->id] = $user;
            $memberOf[$user->id] = array_map('strtolower', self::values($entry, 'memberof'));
        }
        return new Listing($users, $withGroups ? $this->groups($users, $memberOf, $userFilter) : null);
    }

    /** Attributes entryId() reads */
    public static function idAttributes(string $configured): array
    {
        return ($configured !== '') ? [$configured] : self::ID_ATTRIBUTES;
    }

    /**
     * Stable id of an entry: the configured attribute, else objectGUID (as GUID string), entryUUID, nsUniqueId,
     * ipaUniqueID, else the DN. Also used at login, with an entry of an LdapRecord raw query.
     */
    public static function entryId(array $entry, string $configured = ''): string
    {
        foreach (self::idAttributes($configured) as $attribute) {
            $value = self::first($entry, $attribute);
            if ($value !== null && $value !== '') {
                return ($attribute === 'objectguid' && strlen($value) === 16) ? self::guid($value) : strtolower($value);
            }
        }
        return 'dn:' . strtolower((string)(self::first($entry, 'dn') ?? ''));
    }

    /**
     * @param array<string, DirectoryUser> $users
     * @param array<string, string[]> $memberOf user id => lowercase group DNs
     * @return array<string, DirectoryGroup>
     */
    private function groups(array $users, array $memberOf, string $userFilter): array
    {
        $s = $this->settings;
        if ($s->groupFilter === '') {
            throw new DirectoryException('The LDAP group filter is empty');
        }
        $userIds = [];
        foreach ($users as $user) {
            $userIds[$user->dn] = $user->id;
        }
        $attributes = array_merge([$s->groupNameAttribute, 'mail'], self::ID_ATTRIBUTES,
            ($s->groupMemberMode === 'member') ? [$s->groupMemberAttribute] : []);
        $groups = [];
        $groupIds = [];
        foreach ($this->search(($s->groupBasedn !== '') ? $s->groupBasedn : $s->basedn, self::wrap($s->groupFilter),
            array_values(array_unique($attributes))) as $entry) {
            $dn = strtolower((string)$entry['dn']);
            $mail = self::first($entry, 'mail');
            $group = new DirectoryGroup(self::entryId($entry), self::first($entry, $s->groupNameAttribute) ?? $dn,
                ($mail === null) ? null : strtolower($mail));
            if ($s->groupMemberMode === 'member') {
                foreach (array_keys($entry) as $key) {
                    // AD returns large member lists in ranges; a partial list would remove members
                    if (is_string($key) && str_starts_with($key, $s->groupMemberAttribute . ';range=')) {
                        throw new DirectoryException('LDAP returned a ranged member list for ' . $dn . ', use the memberOf or AD mode');
                    }
                }
                foreach (self::values($entry, $s->groupMemberAttribute) as $memberDn) {
                    if (isset($userIds[strtolower($memberDn)])) {
                        $group->memberIds[] = $userIds[strtolower($memberDn)];
                    }
                }
            }
            $groups[$group->id] = $group;
            $groupIds[$dn] = $group->id;
        }
        if ($s->groupMemberMode === 'memberof') {
            foreach ($memberOf as $userId => $dns) {
                foreach ($dns as $dn) {
                    if (isset($groupIds[$dn])) {
                        $groups[$groupIds[$dn]]->memberIds[] = (string)$userId;
                    }
                }
            }
        } elseif ($s->groupMemberMode === 'ad_chain') {
            foreach ($groupIds as $dn => $groupId) {
                $filter = '(&' . $userFilter . '(memberOf:' . self::AD_IN_CHAIN . ':=' . self::escape($dn) . '))';
                foreach ($this->search($s->basedn, $filter, ['dn']) as $entry) {
                    $userDn = strtolower((string)$entry['dn']);
                    if (isset($userIds[$userDn])) {
                        $groups[$groupId]->memberIds[] = $userIds[$userDn];
                    }
                }
            }
        }
        foreach ($groups as $group) {
            $group->memberIds = array_values(array_unique($group->memberIds));
        }
        return $groups;
    }

    /** Users of the configured filter that have the username attribute */
    private function userFilter(): string
    {
        $filter = ($this->settings->filter !== '') ? self::wrap($this->settings->filter) : '(objectClass=*)';
        return '(&' . $filter . '(' . $this->settings->usernameField . '=*))';
    }

    /**
     * Paged search that fails instead of returning a partial result.
     *
     * @return array[] entries as returned by ldap_get_entries()
     * @throws DirectoryException
     */
    protected function search(string $base, string $filter, array $attributes): array
    {
        if ($this->connection === null) {
            throw new DirectoryException('No connection to the LDAP server');
        }
        $link = $this->connection->getLdapConnection()->getConnection();
        $entries = [];
        $cookie = '';
        $paged = true;
        while (true) {
            $controls = $paged ? [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'iscritical' => true,
                'value' => ['size' => self::PAGE, 'cookie' => $cookie]]] : [];
            $result = @ldap_search($link, $base, $filter, $attributes, 0, 0, 0, LDAP_DEREF_NEVER, $controls);
            if ($result === false) {
                throw new DirectoryException('LDAP search failed: ' . ldap_error($link));
            }
            $responseControls = [];
            if (!ldap_parse_result($link, $result, $code, $matched, $message, $referrals, $responseControls)) {
                throw new DirectoryException('LDAP result could not be read: ' . ldap_error($link));
            }
            // 12 = unavailableCriticalExtension: no paging on this server, search once without it
            if ($code === 12 && $paged && $cookie === '') {
                $paged = false;
                continue;
            }
            if ($code !== 0) {
                throw new DirectoryException('LDAP search incomplete (code ' . $code . '): ' . (($message !== '') ? $message : ldap_err2str($code)));
            }
            $page = ldap_get_entries($link, $result);
            for ($i = 0; $i < (int)($page['count'] ?? 0); $i++) {
                $entries[] = $page[$i];
            }
            $cookie = $paged ? (string)($responseControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '') : '';
            if ($cookie === '') {
                return $entries;
            }
        }
    }

    /** First value of an attribute: ldap_get_entries() format or LdapRecord attributes */
    private static function first(array $entry, string $attribute): ?string
    {
        $values = self::values($entry, $attribute);
        return $values[0] ?? null;
    }

    /** @return string[] */
    private static function values(array $entry, string $attribute): array
    {
        $raw = $entry[$attribute] ?? $entry[strtolower($attribute)] ?? null;
        if (!is_array($raw)) {
            return is_scalar($raw) ? [(string)$raw] : [];
        }
        unset($raw['count']);
        return array_values(array_map('strval', array_filter($raw, 'is_scalar')));
    }

    private static function wrap(string $filter): string
    {
        return str_starts_with($filter, '(') ? $filter : '(' . $filter . ')';
    }

    /** RFC 4515 value escaping */
    private static function escape(string $value): string
    {
        return str_replace(['\\', '*', '(', ')', "\0"], ['\\5c', '\\2a', '\\28', '\\29', '\\00'], $value);
    }

    /** Binary objectGUID as the usual GUID string */
    private static function guid(string $binary): string
    {
        $hex = bin2hex($binary);
        return substr($hex, 6, 2) . substr($hex, 4, 2) . substr($hex, 2, 2) . substr($hex, 0, 2) . '-'
            . substr($hex, 10, 2) . substr($hex, 8, 2) . '-' . substr($hex, 14, 2) . substr($hex, 12, 2) . '-'
            . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}
