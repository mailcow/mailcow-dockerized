<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy\Repository;

use Mailcow\Core\Repository;
use Mailcow\MailPolicy\Model\Policy;

/**
 * Read-only lookups of the policy check: mailboxes, alias domains, groups and the active policies of a mailbox
 * or group. Addresses are passed in lower case.
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

    /** A domain or alias domain of this mailcow */
    public function isLocalDomain(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `domain` WHERE `domain` = :d1 UNION SELECT 1 FROM `alias_domain` WHERE `alias_domain` = :d2",
            ['d1' => $domain, 'd2' => $domain])->fetchColumn();
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

    public function isGroupMember(int $groupId, string $username): bool
    {
        return (bool)$this->query("SELECT 1 FROM `mailbox_group_member` WHERE `group_id` = :id AND `username` = :username",
            ['id' => $groupId, 'username' => $username])->fetchColumn();
    }

    /** Domain of an active alias that only accepts mail from its own domain (alias and group flag `internal`) */
    public function internalAlias(string $address): ?string
    {
        $domain = $this->query("SELECT `domain` FROM `alias` WHERE `address` = :address AND `internal` = '1' AND `active` = '1'",
            ['address' => $address])->fetchColumn();
        return is_string($domain) ? strtolower($domain) : null;
    }

    /** @return Policy[] active policies of the mailbox: assigned to it or to an active group it is a member of */
    public function policiesForMailbox(string $username): array
    {
        return $this->policies("SELECT DISTINCT `p`.* FROM `mail_policy` `p`
          JOIN `mail_policy_member` `pm` ON `pm`.`policy_id` = `p`.`id`
          WHERE `p`.`active` = '1' AND (`pm`.`username` = :u1 OR `pm`.`group_id` IN (
            SELECT `gm`.`group_id` FROM `mailbox_group_member` `gm` JOIN `mailbox_group` `g` ON `g`.`id` = `gm`.`group_id`
            WHERE `gm`.`username` = :u2 AND `g`.`active` = '1'))
          ORDER BY `p`.`id`", ['u1' => $username, 'u2' => $username]);
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
