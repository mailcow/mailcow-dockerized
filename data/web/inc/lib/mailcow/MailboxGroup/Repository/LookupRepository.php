<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Repository;

use Mailcow\Core\Repository;
use PDO;

/**
 * Read-only queries on tables of the legacy code (domains, mailboxes, aliases, templates) and the group
 * memberships the template logic needs.
 */
class LookupRepository extends Repository
{
    /** A primary domain (groups never belong to alias domains) */
    public function isDomain(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `domain` WHERE `domain` = :domain", ['domain' => $domain])->fetchColumn();
    }

    public function isDomainOrAliasDomain(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `domain` WHERE `domain` = :d1 UNION SELECT 1 FROM `alias_domain` WHERE `alias_domain` = :d2",
            ['d1' => $domain, 'd2' => $domain])->fetchColumn();
    }

    /** An alias or mailbox address (mailboxes have an alias row of their own) */
    public function aliasExists(string $address): bool
    {
        return (bool)$this->query("SELECT 1 FROM `alias` WHERE `address` = :address", ['address' => $address])->fetchColumn();
    }

    /** Used by an alias other than the group's own row, by a mailbox or resource, or as spam alias */
    public function addressTaken(string $address, ?int $groupId): bool
    {
        return (bool)$this->query("SELECT 1 FROM `alias` WHERE `address` = :a1 AND (`group_id` IS NULL OR `group_id` != :id)
          UNION SELECT 1 FROM `mailbox` WHERE `username` = :a2
          UNION SELECT 1 FROM `spamalias` WHERE `address` = :a3",
            ['a1' => $address, 'id' => $groupId ?? 0, 'a2' => $address, 'a3' => $address])->fetchColumn();
    }

    /** Aliases the domain may still create (same count as mailbox('get', 'domain_details')) */
    public function aliasesLeft(string $domain): int
    {
        $max = $this->query("SELECT `aliases` FROM `domain` WHERE `domain` = :domain", ['domain' => $domain])->fetchColumn();
        $used = $this->query("SELECT COUNT(`address`) FROM `alias`
          WHERE (`domain` = :d1 OR `domain` IN (SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = :d2))
            AND `address` NOT IN (SELECT `username` FROM `mailbox`)", ['d1' => $domain, 'd2' => $domain])->fetchColumn();
        return (int)$max - (int)$used;
    }

    /**
     * @param string[] $usernames
     * @return string[] the given usernames that are regular mailboxes (no resources)
     */
    public function regularMailboxes(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        [$in, $params] = self::in('u', $usernames);
        return $this->query("SELECT `username` FROM `mailbox` WHERE `kind` = '' AND `username` IN ($in)", $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Name of a mailbox template, null if there is none with this id */
    public function templateName(int $id): ?string
    {
        $name = $this->query("SELECT `template` FROM `templates` WHERE `id` = :id AND `type` = 'mailbox'", ['id' => $id])->fetchColumn();
        return ($name === false) ? null : (string)$name;
    }

    /** @return array<string, string> username => full name */
    public function mailboxNames(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        [$in, $params] = self::in('u', $usernames);
        return $this->query("SELECT `username`, `name` FROM `mailbox` WHERE `username` IN ($in)", $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Template of the active group with the highest priority (lowest id on ties) per member.
     *
     * @return array<string, ?string> username => template name, null without group template
     */
    public function effectiveTemplates(array $usernames): array
    {
        $result = array_fill_keys(array_map('strval', $usernames), null);
        if (empty($usernames)) {
            return $result;
        }
        [$in, $params] = self::in('u', $usernames);
        $rows = $this->query("SELECT m.`username`, t.`template` FROM `mailbox_group_member` m
          JOIN `mailbox_group` g ON g.`id` = m.`group_id` AND g.`active` = '1'
          JOIN `templates` t ON t.`id` = g.`template_id` AND t.`type` = 'mailbox'
          WHERE m.`username` IN ($in)
          ORDER BY m.`username`, g.`priority` DESC, g.`id`", $params)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $result[$row['username']] ??= $row['template'];
        }
        return $result;
    }

    /** @return string[] members of active groups bound to the template */
    public function membersWithTemplate(int $templateId): array
    {
        return $this->query("SELECT DISTINCT m.`username` FROM `mailbox_group_member` m
          JOIN `mailbox_group` g ON g.`id` = m.`group_id` AND g.`active` = '1'
          WHERE g.`template_id` = :id", ['id' => $templateId])->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Groups of a mailbox with its membership source */
    public function membershipsOf(string $username): array
    {
        return $this->query("SELECT g.`id`, g.`domain`, g.`name`, g.`address`, g.`sender_allowed`, g.`active`, g.`priority`,
            t.`template`, m.`source` FROM `mailbox_group_member` m
          JOIN `mailbox_group` g ON g.`id` = m.`group_id`
          LEFT JOIN `templates` t ON t.`id` = g.`template_id`
          WHERE m.`username` = :username ORDER BY g.`domain`, g.`name`", ['username' => $username])->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return string[] addresses a mailbox may send as through its active groups */
    public function sendAsOf(string $username): array
    {
        return $this->query("SELECT `send_as` FROM `sender_acl` WHERE `logged_in_as` = :u1 AND `group_id` IS NOT NULL
          UNION SELECT g.`address` FROM `mailbox_group_member` m JOIN `mailbox_group` g ON g.`id` = m.`group_id`
          WHERE m.`username` = :u2 AND g.`active` = '1' AND g.`sender_allowed` = '1' AND g.`address` IS NOT NULL
          ORDER BY 1", ['u1' => $username, 'u2' => $username])->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * IN list with one placeholder per value (native prepares allow a placeholder only once).
     *
     * @return array{0: string, 1: array}
     */
    private static function in(string $prefix, array $values): array
    {
        $names = [];
        $params = [];
        foreach (array_values($values) as $i => $value) {
            $names[] = ':' . $prefix . $i;
            $params[$prefix . $i] = (string)$value;
        }
        return [implode(', ', $names), $params];
    }
}
