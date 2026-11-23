<?php

declare(strict_types=1);

namespace Mailcow\Tests\Auth;

use LdapRecord\Auth\Guard;
use LdapRecord\Connection;
use LdapRecord\Query\Builder;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/legacy_auth_functions.php';
require_once __DIR__ . '/../Support/legacy_idp_login_functions.php';
require_once __DIR__ . '/../../../data/web/inc/functions.auth.inc.php';

// user_login() with an LDAP IdP on an in-memory SQLite database: the login that creates the mailbox
// (login provisioning) must return "user", like the login of an existing mailbox. sogo-auth.php accepts
// only check_login() === 'user' (SOGo, ActiveSync, CalDAV/CardDAV).
class IdpFirstLoginTest extends TestCase
{
    private const GLOBALS = ['pdo', 'iam_settings', 'iam_provider', 'idp_login_mailbox_calls'];

    private PDO $pdo;
    private array $savedGlobals;
    private array|null $savedSession;
    private string $errorLog;
    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        $this->savedGlobals = array_intersect_key($GLOBALS, array_flip(self::GLOBALS));
        $this->savedSession = $_SESSION ?? null;
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('regexp', fn ($pattern, $value) => preg_match('/' . $pattern . '/', (string)$value) === 1, 2);
        $this->pdo->exec("CREATE TABLE `domain` (`domain` TEXT PRIMARY KEY, `active` INTEGER NOT NULL DEFAULT 1)");
        $this->pdo->exec("CREATE TABLE `mailbox` (`username` TEXT PRIMARY KEY, `domain` TEXT, `kind` TEXT NOT NULL DEFAULT '',
            `active` INTEGER NOT NULL DEFAULT 1, `password` TEXT, `authsource` TEXT NOT NULL DEFAULT 'mailcow', `attributes` TEXT)");
        $this->pdo->exec("CREATE TABLE `identity_link` (`username` TEXT PRIMARY KEY, `provider` TEXT, `external_id` TEXT,
            `idp_disabled` INTEGER NOT NULL DEFAULT 0, `missing_since` TEXT)");
        $this->pdo->exec("INSERT INTO `domain` (`domain`, `active`) VALUES ('example.org', 1)");

        $GLOBALS['pdo'] = $this->pdo;
        $GLOBALS['iam_settings'] = ['authsource' => 'ldap', 'username_field' => 'mail', 'filter' => '', 'attribute_field' => 'memberof',
            'id_attribute' => 'entryuuid', 'login_provisioning' => 1, 'default_template' => 'Default'];
        $GLOBALS['iam_provider'] = $this->directory();
        $GLOBALS['idp_login_mailbox_calls'] = [];
        $_SESSION = [];
        $this->errorLog = (string)tempnam(sys_get_temp_dir(), 'mailcow-idp-login-');
        $this->previousErrorLog = ini_set('error_log', $this->errorLog);
    }

    protected function tearDown(): void
    {
        foreach (self::GLOBALS as $name) {
            unset($GLOBALS[$name]);
        }
        foreach ($this->savedGlobals as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        if ($this->savedSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->savedSession;
        }
        ini_set('error_log', (string)$this->previousErrorLog);
        @unlink($this->errorLog);
    }

    public static function services(): array
    {
        return [
            // the service sogo-auth.php passes, and the UI login
            'ActiveSync' => ['EAS'],
            'CalDAV/CardDAV' => ['DAV'],
            'SOGo web' => ['NONE'],
            'mailcow UI' => ['MAILCOWUI'],
        ];
    }

    #[DataProvider('services')]
    public function testLoginThatCreatesTheMailboxReturnsUser(string $service): void
    {
        $denied = false;
        $this->assertSame('user', user_login('erin@example.org', 'secret', ['is_internal' => false, 'service' => $service], $denied));
        $this->assertFalse($denied);
        $this->assertSame('add', $GLOBALS['idp_login_mailbox_calls'][0][0]);
        $this->assertSame('ldap', $this->pdo->query("SELECT `authsource` FROM `mailbox` WHERE `username` = 'erin@example.org'")->fetchColumn());
    }

    public function testWrongPasswordCreatesNothing(): void
    {
        $denied = false;
        $this->assertFalse(user_login('erin@example.org', 'wrong', ['is_internal' => false, 'service' => 'EAS'], $denied));
        $this->assertFalse($denied);
        $this->assertSame([], $GLOBALS['idp_login_mailbox_calls']);
    }

    // the created mailbox has no access to the service: denied (not counted by netfilter), as for an existing mailbox
    public function testCreatedMailboxWithoutServiceAccessIsDenied(): void
    {
        $this->pdo->exec("CREATE TRIGGER `no_eas` AFTER INSERT ON `mailbox` BEGIN
            UPDATE `mailbox` SET `attributes` = '{\"eas_access\":\"0\"}' WHERE `username` = NEW.`username`; END");
        $denied = false;
        $this->assertFalse(user_login('erin@example.org', 'secret', ['is_internal' => false, 'service' => 'EAS'], $denied));
        $this->assertTrue($denied);
    }

    /** LDAP directory with mail=erin@example.org, password "secret" */
    private function directory(): Connection
    {
        $searched = null;
        $query = $this->createMock(Builder::class);
        $query->method('select')->willReturnSelf();
        $query->method('where')->willReturnCallback(function ($field, $op, $value) use (&$searched, $query) {
            $searched = [$field, $value];
            return $query;
        });
        $query->method('get')->willReturnCallback(function () use (&$searched): array {
            return ($searched === ['mail', 'erin@example.org'])
                ? [['dn' => 'uid=erin,dc=example,dc=org', 'mail' => ['erin@example.org'], 'entryuuid' => ['uuid-erin'], 'displayname' => ['Erin']]] : [];
        });
        $guard = $this->createMock(Guard::class);
        $guard->method('bind')->willReturnCallback(function (string $dn, string $password): void {
            if ($password !== 'secret') {
                throw new \LdapRecord\Auth\BindException('Invalid credentials');
            }
        });
        $ldap = $this->createMock(Connection::class);
        $ldap->method('query')->willReturn($query);
        $ldap->method('auth')->willReturn($guard);
        return $ldap;
    }
}
