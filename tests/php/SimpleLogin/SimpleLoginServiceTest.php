<?php

declare(strict_types=1);

namespace Mailcow\Tests\SimpleLogin;

use Mailcow\SimpleLogin\SimpleLoginException;
use Mailcow\SimpleLogin\SimpleLoginService;
use PDO;
use PHPUnit\Framework\TestCase;

class SimpleLoginServiceTest extends TestCase
{
    private const MAILBOX = 'user@example.org';
    private const APP_PASSWORD = 'correct horse battery staple';

    public function testParsesTheHeaderFormatsUsedByForwarders(): void
    {
        self::assertSame([self::MAILBOX, self::APP_PASSWORD], SimpleLoginService::parseApiKey(self::MAILBOX . ':' . self::APP_PASSWORD));
        self::assertSame([self::MAILBOX, self::APP_PASSWORD], SimpleLoginService::parseApiKey('Bearer ' . self::MAILBOX . ':' . self::APP_PASSWORD));
        self::assertSame([self::MAILBOX, 'pass:with:colons'], SimpleLoginService::parseApiKey(self::MAILBOX . ':pass:with:colons'));
        self::assertSame([self::MAILBOX, self::APP_PASSWORD], SimpleLoginService::parseApiKey(base64_encode(self::MAILBOX . ':' . self::APP_PASSWORD)));

        self::assertNull(SimpleLoginService::parseApiKey(null));
        self::assertNull(SimpleLoginService::parseApiKey(''));
        self::assertNull(SimpleLoginService::parseApiKey(self::MAILBOX));
        self::assertNull(SimpleLoginService::parseApiKey('not-an-email:secret'));
        self::assertNull(SimpleLoginService::parseApiKey(self::MAILBOX . ':'));
    }

    public function testNormalizesNotesForMailcowsCommentColumns(): void
    {
        self::assertSame('Bitwarden login', SimpleLoginService::normalizeNote('  Bitwarden login  '));
        self::assertSame('', SimpleLoginService::normalizeNote(null));
        self::assertSame('', SimpleLoginService::normalizeNote(['not a string']));
        self::assertSame('ab', SimpleLoginService::normalizeNote("a\x00b"));
        self::assertSame(160, mb_strlen(SimpleLoginService::normalizeNote(str_repeat('x', 200))));
    }

    public function testGeneratesShortUnambiguousRandomLocalParts(): void
    {
        $first = SimpleLoginService::randomLocalPart();
        $second = SimpleLoginService::randomLocalPart(12);

        self::assertMatchesRegularExpression('/^[abcdefghijkmnpqrstuvwxyz23456789]{8}$/', $first);
        self::assertMatchesRegularExpression('/^[abcdefghijkmnpqrstuvwxyz23456789]{12}$/', $second);
        self::assertNotSame($first, SimpleLoginService::randomLocalPart());
    }

    public function testCreatesANativeAliasInTheMailboxDomain(): void
    {
        $pdo = $this->database();
        $service = $this->service($pdo);

        $alias = $service->createAlias(self::MAILBOX . ':' . self::APP_PASSWORD, null, 'Bitwarden login');

        self::assertMatchesRegularExpression('/^[a-z2-9]{8}@example\.org$/', $alias['alias']);
        self::assertSame('Bitwarden login', $alias['note']);
        $row = $pdo->query('SELECT goto, domain, public_comment, private_comment, active FROM alias')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(self::MAILBOX, $row['goto']);
        self::assertSame('example.org', $row['domain']);
        self::assertSame('Bitwarden login', $row['public_comment']);
        self::assertSame('Bitwarden login', $row['private_comment']);
        self::assertSame(1, (int)$row['active']);
    }

    public function testCreatesTheAliasInAnAliasDomainThatTargetsTheMailboxDomain(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO alias_domain (alias_domain, target_domain, active) VALUES ('alias.test', 'example.org', 1)");

        $alias = $this->service($pdo)->createAlias(self::MAILBOX . ':' . self::APP_PASSWORD, 'alias.test', null);

        self::assertStringEndsWith('@alias.test', $alias['alias']);
        self::assertSame('alias.test', $pdo->query('SELECT domain FROM alias')->fetchColumn());
    }

    public function testRejectsAWrongAppPassword(): void
    {
        $caught = null;
        try {
            $this->service($this->database())->createAlias(self::MAILBOX . ':wrong', null, null);
        } catch (SimpleLoginException $e) {
            $caught = $e;
        }
        self::assertNotNull($caught);
        self::assertSame(401, $caught->status);
        self::assertSame('invalid_api_key', $caught->error);
    }

    public function testRejectsAMailboxWithoutTheAliasAcl(): void
    {
        $pdo = $this->database(['acl_spam_alias' => 0]);
        try {
            $this->service($pdo)->createAlias(self::MAILBOX . ':' . self::APP_PASSWORD, null, null);
            self::fail('alias creation should be denied');
        } catch (SimpleLoginException $e) {
            self::assertSame(403, $e->status);
            self::assertSame('alias_creation_denied', $e->error);
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM alias')->fetchColumn());
    }

    public function testRejectsAFullDomain(): void
    {
        $pdo = $this->database(aliases: 1);
        $pdo->exec("INSERT INTO alias (address, goto, domain, private_comment, public_comment, sogo_visible, internal, sender_allowed, active)
                    VALUES ('taken@example.org', 'other@example.org', 'example.org', '', '', 1, 0, 1, 1)");
        try {
            $this->service($pdo)->createAlias(self::MAILBOX . ':' . self::APP_PASSWORD, null, null);
            self::fail('alias creation should exceed the quota');
        } catch (SimpleLoginException $e) {
            self::assertSame(409, $e->status);
            self::assertSame('alias_quota_exceeded', $e->error);
        }
    }

    public function testRejectsADomainOutsideTheMailbox(): void
    {
        $pdo = $this->database();
        try {
            $this->service($pdo)->createAlias(self::MAILBOX . ':' . self::APP_PASSWORD, 'other.test', null);
            self::fail('foreign domain should be denied');
        } catch (SimpleLoginException $e) {
            self::assertSame(403, $e->status);
            self::assertSame('domain_not_allowed', $e->error);
        }
    }

    private function service(PDO $pdo): SimpleLoginService
    {
        return new SimpleLoginService($pdo, static fn(string $hash, string $password): bool => password_verify($password, $hash));
    }

    private function database(array $attributes = ['acl_spam_alias' => 1], int $aliases = 10): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE domain (domain TEXT PRIMARY KEY, active INTEGER NOT NULL, aliases INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE mailbox (username TEXT PRIMARY KEY, local_part TEXT NOT NULL, domain TEXT NOT NULL, active INTEGER NOT NULL, kind TEXT NOT NULL, attributes TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE app_passwd (id INTEGER PRIMARY KEY AUTOINCREMENT, mailbox TEXT NOT NULL, password TEXT NOT NULL, active INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE alias_domain (alias_domain TEXT PRIMARY KEY, target_domain TEXT NOT NULL, active INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE alias (id INTEGER PRIMARY KEY AUTOINCREMENT, address TEXT NOT NULL UNIQUE, goto TEXT NOT NULL, domain TEXT NOT NULL, private_comment TEXT, public_comment TEXT, sogo_visible INTEGER NOT NULL, internal INTEGER NOT NULL, sender_allowed INTEGER NOT NULL, active INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE spamalias (address TEXT PRIMARY KEY)');

        $pdo->prepare('INSERT INTO domain (domain, active, aliases) VALUES (?, 1, ?)')->execute(['example.org', $aliases]);
        $pdo->prepare('INSERT INTO mailbox (username, local_part, domain, active, kind, attributes) VALUES (?, ?, ?, 1, ?, ?)')
            ->execute([self::MAILBOX, 'user', 'example.org', '', json_encode($attributes)]);
        $pdo->prepare('INSERT INTO app_passwd (mailbox, password, active) VALUES (?, ?, 1)')
            ->execute([self::MAILBOX, password_hash(self::APP_PASSWORD, PASSWORD_DEFAULT)]);

        return $pdo;
    }
}
