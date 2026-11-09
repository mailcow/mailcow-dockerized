<?php

declare(strict_types=1);

namespace Mailcow\Archive\Repository;

use Mailcow\Archive\Model\Mailbox;
use Mailcow\Core\Repository;

/**
 * Read-only lookups of the address tables, with the same conditions as the Postfix maps
 * (data/Dockerfiles/postfix/postfix.sh), and the mailboxes with their archive settings.
 */
class LookupRepository extends Repository
{
    /** Columns of a mailbox that receives mail; the domain must be active and no backup MX */
    private const MAILBOX = "SELECT m.`username`, m.`domain`, m.`name`, m.`active`,
        JSON_VALUE(m.`attributes`, '$.archive') AS `archive`, d.`archive` AS `domain_archive`
      FROM `mailbox` m
      JOIN `domain` d ON d.`domain` = m.`domain`
      WHERE m.`kind` = '' AND (m.`active` = '1' OR m.`active` = '2') AND d.`active` = '1' AND d.`backupmx` = '0'";

    /** recipient_canonical_maps: new destination of an address or '@domain' */
    public function recipientMap(string $key): ?string
    {
        return $this->value("SELECT `new_dest` FROM `recipient_maps` WHERE `old_dest` = :key AND `active` = '1' LIMIT 1", $key);
    }

    /** Targets of an alias row (aliases, catch-alls '@domain', groups, mailbox self rows) */
    public function aliasGoto(string $key): ?string
    {
        return $this->value("SELECT `goto` FROM `alias` WHERE `address` = :key AND (`active` = '1' OR `active` = '2') LIMIT 1", $key);
    }

    /** Resources (location, thing, group) are discarded by Postfix */
    public function isResource(string $username): bool
    {
        return $this->value("SELECT `username` FROM `mailbox` WHERE `username` = :key AND `kind` REGEXP 'location|thing|group' LIMIT 1", $username) !== null;
    }

    public function spamAliasGoto(string $key): ?string
    {
        return $this->value("SELECT `goto` FROM `spamalias` WHERE `address` = :key
          AND (`validity` >= UNIX_TIMESTAMP() OR `permanent` != 0) LIMIT 1", $key);
    }

    /** Mailbox local@target behind local@domain when domain is an alias domain */
    public function aliasDomainMailbox(string $local, string $domain): ?string
    {
        $username = $this->query("SELECT `mailbox`.`username` FROM `mailbox`, `alias_domain`
          WHERE `alias_domain`.`alias_domain` = :domain AND `mailbox`.`username` = CONCAT(:local, '@', `alias_domain`.`target_domain`)
            AND (`mailbox`.`active` = '1' OR `mailbox`.`active` = '2') AND `alias_domain`.`active` = '1' LIMIT 1",
          ['domain' => $domain, 'local' => $local])->fetchColumn();
        return is_string($username) ? $username : null;
    }

    /** BCC destination of an address or '@domain' as rspamd's bcc.php finds it; $type 'rcpt' or 'sender' */
    public function bccDestination(string $key, string $type): ?string
    {
        $dest = $this->query("SELECT `bcc_dest` FROM `bcc_maps` WHERE `type` = :type AND `local_dest` = :key AND `active` = '1' LIMIT 1",
          ['type' => $type, 'key' => $key])->fetchColumn();
        return is_string($dest) ? $dest : null;
    }

    /** A domain or alias domain of this mailcow */
    public function isMailcowDomain(string $domain): bool
    {
        return $this->query("SELECT `domain` FROM `domain` WHERE `domain` = :domain
          UNION SELECT `alias_domain` FROM `alias_domain` WHERE `alias_domain` = :alias LIMIT 1",
          ['domain' => $domain, 'alias' => $domain])->fetchColumn() !== false;
    }

    /** The mailbox when it receives mail */
    public function mailbox(string $username): ?Mailbox
    {
        $row = $this->query(self::MAILBOX . " AND m.`username` = :username", ['username' => $username])->fetch();
        return is_array($row) ? Mailbox::fromRow($row) : null;
    }

    /** @return Mailbox[] mailboxes archived by their setting or their domain (without the global switch) */
    public function archivedMailboxes(): array
    {
        $rows = $this->query(self::MAILBOX . " AND (JSON_VALUE(m.`attributes`, '$.archive') = '1'
            OR (COALESCE(JSON_VALUE(m.`attributes`, '$.archive'), '') NOT IN ('0', '1') AND d.`archive` = '1'))
          ORDER BY m.`username`")->fetchAll();
        return array_map(fn(array $row): Mailbox => Mailbox::fromRow($row), $rows);
    }

    private function value(string $sql, string $key): ?string
    {
        $value = $this->query($sql, ['key' => $key])->fetchColumn();
        return is_string($value) ? $value : null;
    }
}
