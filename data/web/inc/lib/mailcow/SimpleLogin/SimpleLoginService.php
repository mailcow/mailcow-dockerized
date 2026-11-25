<?php

declare(strict_types=1);

namespace Mailcow\SimpleLogin;

use PDO;
use PDOException;

/**
 * Creates random native mailcow aliases for SimpleLogin-compatible clients.
 *
 * Authentication deliberately reuses mailcow app passwords instead of introducing a second
 * credential store: the client sends `mailbox@example.org:app-password` in the
 * `Authentication` header and the password is verified against `app_passwd`. Alias creation
 * additionally requires the mailbox's `spam_alias` ACL, so an app password cannot be used to
 * escape the permissions an administrator configured for that mailbox.
 *
 * The alias is a normal row in mailcow's `alias` table, not a temporary `spamalias` entry:
 * it is visible in SOGo, survives restarts and keeps the mailbox's send-as behaviour.
 */
class SimpleLoginService
{
    /** @var callable(string, string): bool */
    private $verifyPassword;

    /**
     * @param ?callable(string, string): bool $verifyPassword defaults to mailcow's verify_hash()
     */
    public function __construct(private PDO $pdo, ?callable $verifyPassword = null)
    {
        $this->verifyPassword = $verifyPassword ?? static fn(string $hash, string $password): bool => verify_hash($hash, $password);
    }

    /**
     * Parses `mailbox:app-password` from the headers SimpleLogin clients use.
     *
     * @return array{0: string, 1: string}|null [mailbox, app password]
     */
    public static function parseApiKey(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $value, $matches) === 1) {
            $value = trim($matches[1]);
        }
        // Some clients base64-encode the whole key before putting it in the header.
        if (!str_contains($value, ':') && preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $value) === 1) {
            $decoded = base64_decode($value, true);
            if (is_string($decoded) && str_contains($decoded, ':')) {
                $value = $decoded;
            }
        }
        $separator = strpos($value, ':');
        if ($separator === false || $separator === 0) {
            return null;
        }
        $mailbox = substr($value, 0, $separator);
        $password = substr($value, $separator + 1);
        if ($password === '' || filter_var($mailbox, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        return [$mailbox, $password];
    }

    /** A fresh local part without visually ambiguous characters. */
    public static function randomLocalPart(int $length = 8): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;
        $localPart = '';
        for ($i = 0; $i < $length; $i++) {
            $localPart .= $alphabet[random_int(0, $max)];
        }
        return $localPart;
    }

    /** The optional note a client attaches to the alias, safe for mailcow's 160 character column. */
    public static function normalizeNote(mixed $note): string
    {
        if (!is_string($note)) {
            return '';
        }
        $note = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $note) ?? '';
        return mb_substr(trim($note), 0, 160);
    }

    /**
     * Creates one alias for the authenticated mailbox.
     *
     * @return array{alias: string, note: string, mailbox: string, id: int}
     * @throws SimpleLoginException
     */
    public function createAlias(string $apiKey, mixed $requestedDomain, mixed $note): array
    {
        $credentials = self::parseApiKey($apiKey);
        if ($credentials === null) {
            throw new SimpleLoginException(401, 'invalid_api_key', 'Expected an API key in the form mailbox@example.org:app-password.');
        }
        [$mailbox, $password] = $credentials;
        $account = $this->authenticate($mailbox, $password);

        $domain = $this->resolveDomain($account, $requestedDomain);
        $this->assertAliasQuota($domain);

        $note = self::normalizeNote($note);
        $address = $this->insertAlias($account['mailbox'], $domain, $note);
        $id = (int)$this->pdo->lastInsertId();

        return [
            'alias' => $address,
            'note' => $note,
            'mailbox' => $account['mailbox'],
            'id' => $id,
        ];
    }

    /**
     * @return array{mailbox: string, domain: string, attributes: array}
     * @throws SimpleLoginException
     */
    private function authenticate(string $mailbox, string $password): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT app_passwd.password, app_passwd.mailbox, mailbox.domain, mailbox.attributes
               FROM app_passwd
               JOIN mailbox ON mailbox.username = app_passwd.mailbox
               JOIN domain ON domain.domain = mailbox.domain
              WHERE app_passwd.mailbox = :mailbox
                AND app_passwd.active = 1
                AND mailbox.active = 1
                AND domain.active = 1
                AND mailbox.kind NOT IN ('location', 'thing', 'group')"
        );
        $stmt->execute([':mailbox' => $mailbox]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($this->verifyPassword)($row['password'], $password) !== true) {
                continue;
            }
            $attributes = json_decode((string)$row['attributes'], true);
            if (!is_array($attributes) || (int)($attributes['acl_spam_alias'] ?? 0) !== 1) {
                throw new SimpleLoginException(403, 'alias_creation_denied', 'This mailbox is not allowed to create aliases.');
            }
            return ['mailbox' => $row['mailbox'], 'domain' => $row['domain'], 'attributes' => $attributes];
        }

        throw new SimpleLoginException(401, 'invalid_api_key', 'The mailbox or app password is invalid.');
    }

    /**
     * @param array{mailbox: string, domain: string, attributes: array} $account
     * @throws SimpleLoginException
     */
    private function resolveDomain(array $account, mixed $requestedDomain): string
    {
        if ($requestedDomain === null || $requestedDomain === '') {
            return $account['domain'];
        }
        if (!is_string($requestedDomain)) {
            throw new SimpleLoginException(400, 'invalid_domain', 'The domain must be a string.');
        }
        $domain = strtolower(trim($requestedDomain));
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $domain = $ascii;
            }
        }
        if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new SimpleLoginException(400, 'invalid_domain', 'The requested domain is not valid.');
        }

        $stmt = $this->pdo->prepare('SELECT target_domain FROM alias_domain WHERE alias_domain = :domain AND active = 1');
        $stmt->execute([':domain' => $domain]);
        $target = $stmt->fetchColumn();
        if ($target !== false && strcasecmp((string)$target, $account['domain']) === 0) {
            return $domain;
        }
        if (strcasecmp($domain, $account['domain']) !== 0) {
            throw new SimpleLoginException(403, 'domain_not_allowed', 'This mailbox cannot create aliases in that domain.');
        }
        return $account['domain'];
    }

    /** @throws SimpleLoginException */
    private function assertAliasQuota(string $domain): void
    {
        // An alias domain shares the quota of the target domain it points at.
        $stmt = $this->pdo->prepare('SELECT target_domain FROM alias_domain WHERE alias_domain = :domain AND active = 1');
        $stmt->execute([':domain' => $domain]);
        $quotaDomain = $stmt->fetchColumn();
        if ($quotaDomain === false) {
            $quotaDomain = $domain;
        }

        $stmt = $this->pdo->prepare('SELECT aliases FROM domain WHERE domain = :domain AND active = 1');
        $stmt->execute([':domain' => $quotaDomain]);
        $maximum = $stmt->fetchColumn();
        if ($maximum === false) {
            throw new SimpleLoginException(403, 'domain_not_allowed', 'The alias domain is not available.');
        }
        if ((int)$maximum <= 0) {
            throw new SimpleLoginException(409, 'alias_quota_exceeded', 'This domain has no alias quota left.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM alias
              WHERE (domain = :domain OR domain IN (SELECT alias_domain FROM alias_domain WHERE target_domain = :target))
                AND address NOT IN (SELECT username FROM mailbox)"
        );
        $stmt->execute([':domain' => $quotaDomain, ':target' => $quotaDomain]);
        if ((int)$stmt->fetchColumn() >= (int)$maximum) {
            throw new SimpleLoginException(409, 'alias_quota_exceeded', 'This domain has no alias quota left.');
        }
    }

    /** @throws SimpleLoginException */
    private function insertAlias(string $mailbox, string $domain, string $note): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $address = self::randomLocalPart() . '@' . $domain;
            if ($this->addressExists($address)) {
                continue;
            }
            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO alias (address, goto, domain, private_comment, public_comment, sogo_visible, internal, sender_allowed, active)
                     VALUES (:address, :goto, :domain, :comment, :comment, 1, 0, 1, 1)'
                );
                $stmt->execute([
                    ':address' => $address,
                    ':goto' => $mailbox,
                    ':domain' => $domain,
                    ':comment' => $note,
                ]);
                return $address;
            } catch (PDOException $e) {
                // A concurrent request may have taken the address between the check and the insert.
                if ($this->addressExists($address)) {
                    continue;
                }
                throw $e;
            }
        }

        throw new SimpleLoginException(503, 'alias_generation_failed', 'Could not generate a unique alias, please retry.');
    }

    private function addressExists(string $address): bool
    {
        $checks = [
            'SELECT 1 FROM alias WHERE address = :address',
            'SELECT 1 FROM mailbox WHERE username = :address',
            'SELECT 1 FROM spamalias WHERE address = :address',
        ];
        foreach ($checks as $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':address' => $address]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        }

        $at = strrpos($address, '@');
        if ($at === false) {
            return false;
        }
        $localPart = substr($address, 0, $at);
        $domain = substr($address, $at + 1);
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM mailbox
               JOIN alias_domain ON alias_domain.target_domain = mailbox.domain
              WHERE alias_domain.alias_domain = :domain AND mailbox.local_part = :local_part AND alias_domain.active = 1'
        );
        $stmt->execute([':domain' => $domain, ':local_part' => $localPart]);
        return $stmt->fetchColumn() !== false;
    }
}
