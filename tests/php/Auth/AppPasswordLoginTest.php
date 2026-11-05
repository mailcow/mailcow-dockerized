<?php

declare(strict_types=1);

namespace Mailcow\Tests\Auth;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/legacy_auth_functions.php';
require_once __DIR__ . '/../../../data/web/inc/functions.auth.inc.php';

// Legacy apppass_login() with its own query, on an in-memory SQLite database
class AppPasswordLoginTest extends TestCase
{
    private const PROTOCOLS = ['imap_access', 'smtp_access', 'pop3_access', 'sieve_access', 'eas_access', 'dav_access'];

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('regexp', fn ($pattern, $value) => preg_match('/' . $pattern . '/', (string)$value) === 1, 2);
        $this->pdo->exec("CREATE TABLE `domain` (`domain` TEXT PRIMARY KEY, `active` INTEGER NOT NULL DEFAULT 1)");
        $this->pdo->exec("CREATE TABLE `mailbox` (`username` TEXT PRIMARY KEY, `domain` TEXT, `kind` TEXT NOT NULL DEFAULT '',
            `active` INTEGER NOT NULL DEFAULT 1, `attributes` TEXT)");
        $this->pdo->exec("CREATE TABLE `app_passwd` (`id` INTEGER PRIMARY KEY, `name` TEXT, `mailbox` TEXT, `password` TEXT,
            `active` INTEGER NOT NULL DEFAULT 1, " . implode(', ', array_map(fn ($p) => "`$p` INTEGER NOT NULL DEFAULT 0", self::PROTOCOLS)) . ")");
        $GLOBALS['pdo'] = $this->pdo;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['pdo']);
        $_SESSION = [];
    }

    public static function logins(): array
    {
        $off = array_fill_keys(self::PROTOCOLS, '0');
        return [
            // [mailbox, domain active, app password, service, password, result, denied]
            'imap' => [[], 1, ['imap_access' => 1], 'imap', 'app-secret', 'user', false],
            'imap, mailbox imap off' => [['attributes' => $off], 1, ['imap_access' => 1, 'smtp_access' => 1], 'imap', 'app-secret', 'user', false],
            'smtp, mailbox smtp off' => [['attributes' => $off], 1, ['imap_access' => 1, 'smtp_access' => 1], 'smtp', 'app-secret', 'user', false],
            'eas, mailbox eas off' => [['attributes' => $off], 1, ['eas_access' => 1], 'EAS', 'app-secret', 'user', false],
            'dav, mailbox dav off' => [['attributes' => $off], 1, ['dav_access' => 1], 'DAV', 'app-secret', 'user', false],
            // denied: right password, login not allowed
            'protocol not in app password' => [[], 1, ['imap_access' => 1, 'smtp_access' => 1], 'pop3', 'app-secret', false, true],
            'app password inactive' => [[], 1, ['imap_access' => 1, 'active' => 0], 'imap', 'app-secret', false, true],
            'mailbox inactive' => [['active' => 0], 1, ['imap_access' => 1], 'imap', 'app-secret', false, true],
            'mailbox login disabled' => [['active' => 2], 1, ['imap_access' => 1], 'imap', 'app-secret', false, true],
            'domain inactive' => [[], 0, ['imap_access' => 1], 'imap', 'app-secret', false, true],
            'shared mailbox' => [['attributes' => ['shared' => '1']], 1, ['imap_access' => 1], 'imap', 'app-secret', false, true],
            // failed: counted by netfilter
            'wrong password' => [[], 1, ['imap_access' => 1], 'imap', 'wrong', false, false],
            'resource mailbox' => [['kind' => 'location'], 1, ['imap_access' => 1], 'imap', 'app-secret', false, false],
        ];
    }

    #[DataProvider('logins')]
    public function testLogin(array $mailbox, int $domainActive, array $appPasswd, string $service, string $password, string|false $result, bool $denied): void
    {
        $this->addMailbox($mailbox, $domainActive);
        $id = $this->addAppPasswd($appPasswd);

        $isDenied = false;
        $this->assertSame($result, apppass_login('user@example.org', $password, ['service' => $service, 'is_internal' => true], $isDenied));
        $this->assertSame($denied, $isDenied);
        if ($result !== false) {
            $this->assertEquals($id, $_SESSION['app_passwd_id']);
        }
    }

    public function testUiLoginWithoutServiceIsRefused(): void
    {
        $this->addMailbox([], 1);
        $this->addAppPasswd(array_fill_keys(self::PROTOCOLS, 1));

        $this->assertFalse(apppass_login('user@example.org', 'app-secret', ['is_internal' => false]));
    }

    public function testOtherAppPasswordOfTheMailboxIsTried(): void
    {
        $this->addMailbox(['attributes' => array_fill_keys(self::PROTOCOLS, '0')], 1);
        $this->addAppPasswd(['eas_access' => 1], 'other-secret');
        $id = $this->addAppPasswd(['imap_access' => 1]);

        $this->assertSame('user', apppass_login('user@example.org', 'app-secret', ['service' => 'imap', 'is_internal' => true]));
        $this->assertEquals($id, $_SESSION['app_passwd_id']);
    }

    private function addMailbox(array $mailbox, int $domainActive): void
    {
        $attributes = array_merge(array_fill_keys(self::PROTOCOLS, '1'), ['shared' => '0'], $mailbox['attributes'] ?? []);
        $this->pdo->prepare("INSERT INTO `domain` (`domain`, `active`) VALUES ('example.org', ?)")->execute([$domainActive]);
        $this->pdo->prepare("INSERT INTO `mailbox` (`username`, `domain`, `kind`, `active`, `attributes`) VALUES ('user@example.org', 'example.org', ?, ?, ?)")
            ->execute([$mailbox['kind'] ?? '', $mailbox['active'] ?? 1, json_encode($attributes)]);
    }

    private function addAppPasswd(array $appPasswd, string $password = 'app-secret'): int
    {
        $row = array_merge(array_fill_keys(self::PROTOCOLS, 0), ['active' => 1], $appPasswd);
        $hash = '{BLF-CRYPT}' . password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->pdo->prepare("INSERT INTO `app_passwd` (`name`, `mailbox`, `password`, " . implode(', ', array_map(fn ($c) => "`$c`", array_keys($row))) . ")
            VALUES ('test', 'user@example.org', ?, " . implode(', ', array_fill(0, count($row), '?')) . ")")
            ->execute(array_merge([$hash], array_values($row)));
        return (int)$this->pdo->lastInsertId();
    }
}
