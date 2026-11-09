<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy\Repository;

use Mailcow\Core\Repository;
use Mailcow\MailPolicy\Model\Policy;

/**
 * Read-only lookups of the policy check: mailboxes, aliases, alias domains, groups and the active policies of a
 * mailbox or group. Addresses are passed in lower case.
 */
class LookupRepository extends Repository
{
    /** @return array{username: string, domain: string}|null a regular mailbox that is not disabled */
    public function mailbox(string $username): ?array
    {
        $row = $this->query("SELECT `username`, `domain` FROM `mailbox` WHERE `username` = :username AND `kind` = '' AND `active` != '0'",
            ['username' => $username])->fetch();
        return is_array($row) ? ['username' => strtolower((string)$row['username']), 'domain' => strtolower((string)$row['domain'])] : null;
    }

    /** Target domain of an active alias domain */
    public function aliasDomainTarget(string $domain): ?string
    {
        $target = $this->query("SELECT `target_domain` FROM `alias_domain` WHERE `alias_domain` = :domain AND `active` = '1'",
            ['domain' => $domain])->fetchColumn();
        return is_string($target) ? strtolower($target) : null;
    }

    /** A domain or alias domain Postfix delivers itself (as mysql_virtual_domains_maps.cf: active, no backup MX) */
    public function isLocalDomain(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `alias_domain` WHERE `alias_domain` = :d1 AND `active` = '1'
          UNION SELECT 1 FROM `domain` WHERE `domain` = :d2 AND `active` = '1' AND `backupmx` = '0'",
            ['d1' => $domain, 'd2' => $domain])->fetchColumn();
    }

    /**
     * Stored spelling of a mailbox, alias or temporary alias address. MySQL compares without case and accents, so
     * Postfix delivers mail for chéf@ to chef@.
     */
    public function storedAddress(string $address): ?string
    {
        $stored = $this->query("SELECT `username` FROM `mailbox` WHERE `username` = :a1 UNION SELECT `address` FROM `alias` WHERE `address` = :a2
          UNION SELECT `address` FROM `spamalias` WHERE `address` = :a3 LIMIT 1", ['a1' => $address, 'a2' => $address, 'a3' => $address])->fetchColumn();
        return is_string($stored) ? strtolower($stored) : null;
    }

    /** Stored spelling of a domain or alias domain, see storedAddress() */
    public function storedDomain(string $domain): ?string
    {
        $stored = $this->query("SELECT `domain` FROM `domain` WHERE `domain` = :d1 UNION SELECT `alias_domain` FROM `alias_domain` WHERE `alias_domain` = :d2 LIMIT 1",
            ['d1' => $domain, 'd2' => $domain])->fetchColumn();
        return is_string($stored) ? strtolower($stored) : null;
    }

    /** Active group with this distribution address */
    public function groupByAddress(string $address): ?int
    {
        $id = $this->query("SELECT `id` FROM `mailbox_group` WHERE `address` = :address AND `active` = '1'", ['address' => $address])->fetchColumn();
        return ($id === false) ? null : (int)$id;
    }

    public function groupAddress(int $id): ?string
    {
        $address = $this->query("SELECT `address` FROM `mailbox_group` WHERE `id` = :id AND `active` = '1'", ['id' => $id])->fetchColumn();
        return is_string($address) && $address !== '' ? strtolower($address) : null;
    }

    /** Member of an active group */
    public function isGroupMember(int $groupId, string $username): bool
    {
        return (bool)$this->query("SELECT 1 FROM `mailbox_group_member` `gm` JOIN `mailbox_group` `g` ON `g`.`id` = `gm`.`group_id`
          WHERE `gm`.`group_id` = :id AND `gm`.`username` = :username AND `g`.`active` = '1'",
            ['id' => $groupId, 'username' => $username])->fetchColumn();
    }

    /**
     * What Postfix' virtual alias maps find for this key, in their order: an alias (also the own alias of a mailbox
     * and the alias of a group address) or a temporary alias.
     *
     * @return array{kind: string, domain?: string, internal?: bool, owner?: string}|null
     */
    public function aliasTarget(string $address): ?array
    {
        $alias = $this->query("SELECT `domain`, `internal` FROM `alias` WHERE `address` = :address AND `active` IN ('1', '2')",
            ['address' => $address])->fetch();
        if (is_array($alias)) {
            return ['kind' => 'alias', 'domain' => strtolower((string)$alias['domain']), 'internal' => $alias['internal'] == '1'];
        }
        $owner = $this->query("SELECT `goto` FROM `spamalias` WHERE `address` = :address AND (`validity` >= UNIX_TIMESTAMP() OR `permanent` != 0)",
            ['address' => $address])->fetchColumn();
        return is_string($owner) ? ['kind' => 'temporary', 'owner' => strtolower($owner)] : null;
    }

    /** An active catch-all alias of the domain has the internal flag (applies to the whole domain) */
    public function internalCatchAll(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `alias` WHERE `address` = :address AND `internal` = '1' AND `active` IN ('1', '2')",
            ['address' => '@' . $domain])->fetchColumn();
    }

    /** An active policy with members restricts sending */
    public function hasSendRules(): bool
    {
        return (bool)$this->query("SELECT 1 FROM `mail_policy` `p` JOIN `mail_policy_member` `pm` ON `pm`.`policy_id` = `p`.`id`
          WHERE `p`.`active` = '1' AND (`p`.`send_mode` != 'all' OR `p`.`send_block` != '[]') LIMIT 1")->fetchColumn();
    }

    /**
     * @return Policy[] active policies of the mailbox: assigned to it or to an active group it is a member of; only
     *                  policies of its own domain, so a group with members of other domains cannot restrict them
     */
    public function policiesForMailbox(string $username): array
    {
        return $this->policies("SELECT DISTINCT `p`.* FROM `mail_policy` `p`
          JOIN `mail_policy_member` `pm` ON `pm`.`policy_id` = `p`.`id`
          JOIN `mailbox` `m` ON `m`.`username` = :u1 AND `m`.`domain` = `p`.`domain`
          WHERE `p`.`active` = '1' AND (`pm`.`username` = :u2 OR `pm`.`group_id` IN (
            SELECT `gm`.`group_id` FROM `mailbox_group_member` `gm` JOIN `mailbox_group` `g` ON `g`.`id` = `gm`.`group_id`
            WHERE `gm`.`username` = :u3 AND `g`.`active` = '1'))
          ORDER BY `p`.`id`", ['u1' => $username, 'u2' => $username, 'u3' => $username]);
    }

    /** @return Policy[] active policies assigned to the group */
    public function policiesForGroup(int $groupId): array
    {
        return $this->policies("SELECT DISTINCT `p`.* FROM `mail_policy` `p`
          JOIN `mail_policy_member` `pm` ON `pm`.`policy_id` = `p`.`id`
          WHERE `p`.`active` = '1' AND `pm`.`group_id` = :id
          ORDER BY `p`.`id`", ['id' => $groupId]);
    }

    /** @return Policy[] */
    private function policies(string $sql, array $params): array
    {
        return array_map(fn(array $row): Policy => Policy::fromRow($row), $this->query($sql, $params)->fetchAll());
    }
}
