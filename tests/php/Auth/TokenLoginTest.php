<?php

declare(strict_types=1);

namespace Mailcow\Tests\Auth;

use Firebase\JWT\JWT;
use Mailcow\Auth\IdpUnavailable;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../data/web/inc/functions.auth.inc.php';

// token_login() (SASL OAUTHBEARER / XOAUTH2) on an in-memory SQLite database. The issuer's keys are
// put in the Redis cache, so no IdP is asked.
class TokenLoginTest extends TestCase
{
    private const ISSUER = 'https://id.example.org/realms/example';
    private const OTHER_ISSUER = 'https://id.other.org/realms/other';
    private const PROTOCOLS = ['imap_access', 'smtp_access', 'pop3_access', 'sieve_access', 'eas_access', 'dav_access'];

    private static string $privateKey;
    private static array $jwk;
    private static string $otherPrivateKey;
    private static array $otherJwk;

    private PDO $pdo;
    private object $redis;

    public static function setUpBeforeClass(): void
    {
        [self::$privateKey, self::$jwk] = self::keyPair('k1');
        [self::$otherPrivateKey, self::$otherJwk] = self::keyPair('k1');
    }

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('regexp', fn ($pattern, $value) => preg_match('/' . $pattern . '/', (string)$value) === 1, 2);
        $this->pdo->exec("CREATE TABLE `domain` (`domain` TEXT PRIMARY KEY, `active` INTEGER NOT NULL DEFAULT 1)");
        $this->pdo->exec("CREATE TABLE `mailbox` (`username` TEXT PRIMARY KEY, `domain` TEXT, `kind` TEXT NOT NULL DEFAULT '',
            `active` INTEGER NOT NULL DEFAULT 1, `attributes` TEXT)");
        $this->pdo->exec("CREATE TABLE `domain_token_issuer` (`domain` TEXT PRIMARY KEY, `issuer` TEXT NOT NULL,
            `audiences` TEXT NOT NULL DEFAULT '[]', `claim` TEXT NOT NULL DEFAULT 'email')");
        $this->redis = new class {
            public array $hashes = [];
            public function hGet($key, $field) { return $this->hashes[$key][$field] ?? false; }
            public function hSet($key, $field, $value) { $this->hashes[$key][$field] = $value; return 1; }
        };
        $this->redis->hSet('TOKEN_ISSUER_KEYS', self::ISSUER, json_encode(['fetched' => time(), 'keys' => [self::$jwk]]));
        $this->redis->hSet('TOKEN_ISSUER_KEYS', self::OTHER_ISSUER, json_encode(['fetched' => time(), 'keys' => [self::$otherJwk]]));
        $GLOBALS['pdo'] = $this->pdo;
        $GLOBALS['redis'] = $this->redis;

        $this->addDomain('example.org', self::ISSUER);
        $this->addDomain('other.org', self::OTHER_ISSUER);
        $this->addDomain('plain.org', null);
        $this->addMailbox('user@example.org');
        $this->addMailbox('user@other.org');
        $this->addMailbox('user@plain.org');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['pdo'], $GLOBALS['redis']);
    }

    public static function logins(): array
    {
        $off = array_fill_keys(self::PROTOCOLS, '0');
        return [
            // [user, claims, service, mailbox, issuer settings, result, denied]
            'imap' => ['user@example.org', [], 'imap', [], [], 'user', false],
            'smtp' => ['user@example.org', [], 'smtp', [], [], 'user', false],
            'address in another case' => ['User@Example.org', ['email' => 'USER@example.org'], 'imap', [], [], 'user', false],
            'audience in aud' => ['user@example.org', ['aud' => ['account', 'webmail']], 'imap', [], ['audiences' => ['webmail']], 'user', false],
            'audience in azp' => ['user@example.org', ['azp' => 'webmail'], 'imap', [], ['audiences' => ['webmail']], 'user', false],
            'other address claim' => ['user@example.org', ['email' => 'x@y.z', 'mail' => 'user@example.org'], 'imap', [], ['claim' => 'mail'], 'user', false],
            // denied: valid token, login not allowed
            'protocol off' => ['user@example.org', [], 'imap', ['attributes' => $off], [], false, true],
            'mailbox inactive' => ['user@example.org', [], 'imap', ['active' => 0], [], false, true],
            'mailbox login disabled' => ['user@example.org', [], 'imap', ['active' => 2], [], false, true],
            'shared mailbox' => ['user@example.org', [], 'imap', ['attributes' => ['shared' => '1']], [], false, true],
            // failed: counted by netfilter
            'token for another mailbox' => ['user@example.org', ['email' => 'someone@example.org'], 'imap', [], [], false, false],
            // example.org's IdP names a mailbox of other.org, which trusts another issuer
            'token naming a mailbox of another domain' => ['user@other.org', ['email' => 'user@other.org'], 'imap', [], [], false, false],
            'token naming a mailbox of another domain, with its iss' => ['user@other.org', ['email' => 'user@other.org', 'iss' => self::OTHER_ISSUER], 'imap', [], [], false, false],
            'domain without issuer' => ['user@plain.org', ['email' => 'user@plain.org'], 'imap', [], [], false, false],
            'other issuer in iss' => ['user@example.org', ['iss' => 'https://id.example.org/realms/evil'], 'imap', [], [], false, false],
            'expired' => ['user@example.org', ['exp' => time() - 120], 'imap', [], [], false, false],
            'not yet valid' => ['user@example.org', ['nbf' => time() + 120], 'imap', [], [], false, false],
            'no expiry' => ['user@example.org', ['exp' => null], 'imap', [], [], false, false],
            'wrong audience' => ['user@example.org', ['aud' => 'account', 'azp' => 'other'], 'imap', [], ['audiences' => ['webmail']], false, false],
            'email not verified' => ['user@example.org', ['email_verified' => false], 'imap', [], [], false, false],
            'no address claim' => ['user@example.org', ['email' => null], 'imap', [], [], false, false],
            'unknown mailbox' => ['nobody@example.org', ['email' => 'nobody@example.org'], 'imap', [], [], false, false],
            'resource mailbox' => ['user@example.org', [], 'imap', ['kind' => 'location'], [], false, false],
        ];
    }

    #[DataProvider('logins')]
    public function testLogin(string $user, array $claims, string $service, array $mailbox, array $issuer, string|false $result, bool $denied): void
    {
        if ($mailbox) {
            $this->addMailbox('user@example.org', $mailbox);
        }
        if ($issuer) {
            $this->addDomain('example.org', self::ISSUER, $issuer['audiences'] ?? [], $issuer['claim'] ?? 'email');
        }

        $isDenied = false;
        $this->assertSame($result, token_login($user, $this->token($claims), $service, $isDenied));
        $this->assertSame($denied, $isDenied);
    }

    public function testTokenSignedWithAnotherKeyFails(): void
    {
        $token = JWT::encode($this->claims([]), self::$otherPrivateKey, 'RS256', 'k1');
        $this->assertFalse(token_login('user@example.org', $token, 'imap'));
    }

    public function testUnsignedTokenFails(): void
    {
        $b64 = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');
        $token = $b64(['alg' => 'none', 'typ' => 'JWT', 'kid' => 'k1']) . '.' . $b64($this->claims([])) . '.';
        $this->assertFalse(token_login('user@example.org', $token, 'imap'));
    }

    public function testGarbageFails(): void
    {
        $this->assertFalse(token_login('user@example.org', 'not-a-jwt', 'imap'));
        $this->assertFalse(token_login('user@example.org', '', 'imap'));
        $this->assertFalse(token_login('not an address', $this->token([]), 'imap'));
    }

    // a key id that is not cached is fetched once more, but not more than once a minute
    public function testUnknownKeyIdWithFreshCacheIsNotFetched(): void
    {
        $token = JWT::encode($this->claims([]), self::$privateKey, 'RS256', 'rotated');
        $this->assertFalse(token_login('user@example.org', $token, 'imap'));
    }

    // an issuer that cannot be reached is a temporary failure, not a failed login
    public function testUnreachableIssuerWithoutCacheIsUnavailable(): void
    {
        $issuer = 'http://127.0.0.1:1/realms/down';
        $this->addDomain('example.org', $issuer);
        $this->expectException(IdpUnavailable::class);
        token_login('user@example.org', $this->token(['iss' => $issuer]), 'imap');
    }

    public function testStaleCacheIsUsedWhenTheIssuerIsUnreachable(): void
    {
        $issuer = 'http://127.0.0.1:1/realms/down';
        $this->addDomain('example.org', $issuer);
        $this->redis->hSet('TOKEN_ISSUER_KEYS', $issuer, json_encode(['fetched' => time() - 7200, 'keys' => [self::$jwk]]));
        $this->assertSame('user', token_login('user@example.org', $this->token(['iss' => $issuer]), 'imap'));
    }

    private function claims(array $claims): array
    {
        $claims = array_merge([
            'iss' => self::ISSUER,
            'exp' => time() + 300,
            'iat' => time(),
            'email' => 'user@example.org',
            'email_verified' => true,
        ], $claims);
        return array_filter($claims, fn ($v) => $v !== null);
    }

    private function token(array $claims): string
    {
        return JWT::encode($this->claims($claims), self::$privateKey, 'RS256', 'k1');
    }

    private function addDomain(string $domain, ?string $issuer, array $audiences = [], string $claim = 'email'): void
    {
        $this->pdo->prepare("INSERT OR IGNORE INTO `domain` (`domain`, `active`) VALUES (?, 1)")->execute([$domain]);
        $this->pdo->prepare("DELETE FROM `domain_token_issuer` WHERE `domain` = ?")->execute([$domain]);
        if ($issuer !== null) {
            $this->pdo->prepare("INSERT INTO `domain_token_issuer` (`domain`, `issuer`, `audiences`, `claim`) VALUES (?, ?, ?, ?)")
                ->execute([$domain, $issuer, json_encode($audiences), $claim]);
        }
    }

    private function addMailbox(string $username, array $mailbox = []): void
    {
        $attributes = array_merge(array_fill_keys(self::PROTOCOLS, '1'), ['shared' => '0'], $mailbox['attributes'] ?? []);
        $this->pdo->prepare("INSERT OR REPLACE INTO `mailbox` (`username`, `domain`, `kind`, `active`, `attributes`) VALUES (?, ?, ?, ?, ?)")
            ->execute([$username, substr($username, strpos($username, '@') + 1), $mailbox['kind'] ?? '', $mailbox['active'] ?? 1, json_encode($attributes)]);
    }

    private static function keyPair(string $kid): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $b64 = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        return [$private, ['kty' => 'RSA', 'kid' => $kid, 'use' => 'sig', 'alg' => 'RS256', 'n' => $b64($rsa['n']), 'e' => $b64($rsa['e'])]];
    }
}
