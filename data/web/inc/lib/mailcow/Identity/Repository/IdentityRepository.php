<?php

declare(strict_types=1);

namespace Mailcow\Identity\Repository;

use Mailcow\Core\Repository;
use Mailcow\Identity\Model\Account;
use PDO;

/**
 * SQL for IdP mailboxes and their links (table `identity_link`).
 */
class IdentityRepository extends Repository
{
    /** @return array<string, Account> mailboxes with the provider as authsource, by username */
    public function accounts(string $provider): array
    {
        $rows = $this->query("SELECT m.`username`, m.`name`, m.`active`,
            JSON_UNQUOTE(JSON_EXTRACT(m.`attributes`, '$.attribute_hash')) AS `attribute_hash`,
            l.`external_id`, l.`idp_disabled`, l.`missing_since`
          FROM `mailbox` m
            LEFT JOIN `identity_link` l ON l.`username` = m.`username` AND l.`provider` = :p1
          WHERE m.`authsource` = :p2 AND m.`kind` = ''", ['p1' => $provider, 'p2' => $provider])->fetchAll(PDO::FETCH_ASSOC);
        $accounts = [];
        foreach ($rows as $row) {
            $accounts[(string)$row['username']] = new Account((string)$row['username'], (string)$row['name'], (int)$row['active'],
                isset($row['external_id']) ? (string)$row['external_id'] : null, (int)($row['idp_disabled'] ?? 0) === 1,
                isset($row['missing_since']) ? (string)$row['missing_since'] : null, (string)($row['attribute_hash'] ?? ''));
        }
        return $accounts;
    }

    /** @return array{accounts: int, linked: int, idp_disabled: int, missing: int} mailboxes of the provider */
    public function stats(string $provider): array
    {
        $row = $this->query("SELECT COUNT(*) AS `accounts`, COUNT(l.`external_id`) AS `linked`,
            COALESCE(SUM(l.`idp_disabled`), 0) AS `idp_disabled`, COUNT(l.`missing_since`) AS `missing`
          FROM `mailbox` m LEFT JOIN `identity_link` l ON l.`username` = m.`username` AND l.`provider` = :p1
          WHERE m.`authsource` = :p2 AND m.`kind` = ''", ['p1' => $provider, 'p2' => $provider])->fetch(PDO::FETCH_ASSOC);
        return array_map('intval', $row);
    }

    /** @return array<string, string> username => authsource of every mailbox (incl. resources) */
    public function mailboxes(): array
    {
        return $this->query("SELECT `username`, `authsource` FROM `mailbox`")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @return string[] mailcow domains (no alias domains) */
    public function domains(): array
    {
        return $this->query("SELECT `domain` FROM `domain`")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return array<string, string> template name => hash as stored by mailbox_from_template in attributes.attribute_hash */
    public function templateHashes(): array
    {
        $hashes = [];
        foreach ($this->query("SELECT `template`, `attributes` FROM `templates` WHERE `type` = 'mailbox'")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $hashes[(string)$row['template']] = sha1(json_encode($row['attributes']));
        }
        return $hashes;
    }

    /** @return ?array{provider: string, external_id: string, idp_disabled: int, missing_since: ?string} */
    public function findLink(string $username): ?array
    {
        $row = $this->query("SELECT `provider`, `external_id`, `idp_disabled`, `missing_since` FROM `identity_link` WHERE `username` = :username",
            ['username' => $username])->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Mailbox linked to the IdP user; links of mailboxes that no longer use the provider do not count */
    public function usernameOf(string $provider, string $externalId): ?string
    {
        $username = $this->query("SELECT l.`username` FROM `identity_link` l
            JOIN `mailbox` m ON m.`username` = l.`username` AND m.`authsource` = l.`provider`
          WHERE l.`provider` = :provider AND l.`external_id` = :id", ['provider' => $provider, 'id' => $externalId])->fetchColumn();
        return ($username === false) ? null : (string)$username;
    }

    /**
     * Links the mailbox to the IdP user; an older link of the mailbox is replaced. Callers make sure the IdP user
     * is not linked to another mailbox of the provider (usernameOf()); stale links of the id are dropped.
     */
    public function link(string $username, string $provider, string $externalId): void
    {
        $this->transaction(function () use ($username, $provider, $externalId): void {
            $this->query("DELETE FROM `identity_link` WHERE `provider` = :provider AND `external_id` = :id AND `username` != :username",
                ['provider' => $provider, 'id' => $externalId, 'username' => $username]);
            $this->query("INSERT INTO `identity_link` (`username`, `provider`, `external_id`) VALUES (:username, :provider, :id)
              ON DUPLICATE KEY UPDATE `provider` = VALUES(`provider`), `external_id` = VALUES(`external_id`), `missing_since` = NULL",
                ['username' => $username, 'provider' => $provider, 'id' => $externalId]);
        });
    }

    public function unlink(string $username): void
    {
        $this->query("DELETE FROM `identity_link` WHERE `username` = :username", ['username' => $username]);
    }

    /** Marks the IdP mailbox as not listed (now) or listed again; unlinked mailboxes get a link row without id */
    public function setMissing(string $username, string $provider, bool $missing): void
    {
        $this->query("INSERT INTO `identity_link` (`username`, `provider`, `missing_since`) VALUES (:username, :provider, "
            . ($missing ? "NOW()" : "NULL") . ") ON DUPLICATE KEY UPDATE `missing_since` = VALUES(`missing_since`)",
            ['username' => $username, 'provider' => $provider]);
    }

    /**
     * Sets mailbox.active like the mailbox edit does (incl. the mailbox's own alias row) and records whether the
     * IdP sync caused it.
     */
    public function setActive(string $username, string $provider, int $active, bool $idpDisabled): void
    {
        $this->transaction(function () use ($username, $provider, $active, $idpDisabled): void {
            $this->query("UPDATE `mailbox` SET `active` = :active WHERE `username` = :username", ['active' => $active, 'username' => $username]);
            $this->query("UPDATE `alias` SET `active` = :active WHERE `address` = :username", ['active' => $active, 'username' => $username]);
            $this->setIdpDisabled($username, $provider, $idpDisabled);
        });
    }

    public function setIdpDisabled(string $username, string $provider, bool $idpDisabled): void
    {
        $this->query("INSERT INTO `identity_link` (`username`, `provider`, `idp_disabled`) VALUES (:username, :provider, :flag)
          ON DUPLICATE KEY UPDATE `idp_disabled` = VALUES(`idp_disabled`)",
            ['username' => $username, 'provider' => $provider, 'flag' => $idpDisabled ? 1 : 0]);
    }
}
