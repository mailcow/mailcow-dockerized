<?php

declare(strict_types=1);

namespace Mailcow\Tests\Auth;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

require_once __DIR__ . '/../Support/legacy_auth_functions.php';
require_once __DIR__ . '/../../../data/web/inc/functions.auth.inc.php';

class BareLocalpartLoginTest extends TestCase
{
    private PDO $pdo;
    private array $savedServer;
    private array|null $savedSession;
    private array $savedGlobals;
    private string|false $savedEnv;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->savedSession = $_SESSION ?? null;
        $this->savedGlobals = array_intersect_key($GLOBALS, array_flip(['pdo', 'ALLOW_BARE_LOCALPART_LOGIN']));
        $this->savedEnv = getenv('ALLOW_BARE_LOCALPART_LOGIN');
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE `domain` (`domain` TEXT PRIMARY KEY, `active` INTEGER NOT NULL DEFAULT 1)");
        $this->pdo->exec("INSERT INTO `domain` (`domain`, `active`) VALUES ('example.org', 1), ('example.net', 1), ('inactive.test', 0)");
        $GLOBALS['pdo'] = $this->pdo;
        $GLOBALS['ALLOW_BARE_LOCALPART_LOGIN'] = 'y';
        $_SERVER['HTTP_HOST'] = 'mail.example.org';
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['pdo'], $GLOBALS['ALLOW_BARE_LOCALPART_LOGIN']);
        foreach ($this->savedGlobals as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        $_SERVER = $this->savedServer;
        if ($this->savedSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->savedSession;
        }
        putenv($this->savedEnv === false ? 'ALLOW_BARE_LOCALPART_LOGIN' : 'ALLOW_BARE_LOCALPART_LOGIN=' . $this->savedEnv);
    }

    public static function hosts(): array
    {
        return [
            'mail plus domain' => ['mail.example.org', 'example.org'],
            'uppercase' => ['MAIL.Example.ORG', 'example.org'],
            'with port' => ['mail.example.org:443', 'example.org'],
            'additional server name pattern' => ['mail.example.net', 'example.net'],
            'subdomain mail host' => ['mail.foo.example.org', 'foo.example.org'],
            'plain domain' => ['example.org', null],
            'www' => ['www.example.org', null],
            'autodiscover' => ['autodiscover.example.org', null],
            'autoconfig' => ['autoconfig.example.org', null],
            'ip' => ['192.0.2.10', null],
            'localhost' => ['localhost', null],
            'empty' => ['', null],
            'mail only' => ['mail', null],
            'mail dot' => ['mail.', null],
            'leading space' => [' mail.example.org', null],
        ];
    }

    #[DataProvider('hosts')]
    public function testDomainFromHost(?string $host, ?string $expected): void
    {
        $this->assertSame($expected, bare_localpart_login_domain_from_host($host));
    }

    public function testDomainFromHostUsesHttpHostWhenOmitted(): void
    {
        $_SERVER['HTTP_HOST'] = 'mail.example.net:8443';
        $this->assertSame('example.net', bare_localpart_login_domain_from_host());
    }

    public function testQualifyOffByDefault(): void
    {
        $GLOBALS['ALLOW_BARE_LOCALPART_LOGIN'] = 'n';
        $this->assertFalse(bare_localpart_login_enabled());
        $this->assertSame('user', bare_localpart_login_qualify('user'));
    }

    public function testQualifyYesFlag(): void
    {
        foreach (['y', 'Y', 'yes', 'YES'] as $flag) {
            $GLOBALS['ALLOW_BARE_LOCALPART_LOGIN'] = $flag;
            $this->assertTrue(bare_localpart_login_enabled(), $flag);
            $this->assertSame('user@example.org', bare_localpart_login_qualify('user'), $flag);
        }
    }

    public function testQualifyBareOnMatchingActiveDomain(): void
    {
        $this->assertSame('user@example.org', bare_localpart_login_qualify('user'));
        $_SERVER['HTTP_HOST'] = 'mail.example.net';
        $this->assertSame('user@example.net', bare_localpart_login_qualify('user'));
    }

    public function testQualifyFullAddressNeverChanged(): void
    {
        $this->assertSame('user@example.net', bare_localpart_login_qualify('user@example.net'));
        $this->assertSame('user@example.org', bare_localpart_login_qualify('user@example.org'));
        $this->assertSame('other@example.net', bare_localpart_login_qualify('other@example.net'));
    }

    public function testQualifyUnknownOrUnrelatedHostLeftUnchanged(): void
    {
        $_SERVER['HTTP_HOST'] = 'mail.unknown.example';
        $this->assertSame('user', bare_localpart_login_qualify('user'));

        $_SERVER['HTTP_HOST'] = 'autodiscover.example.org';
        $this->assertSame('user', bare_localpart_login_qualify('user'));

        $_SERVER['HTTP_HOST'] = '192.0.2.10';
        $this->assertSame('user', bare_localpart_login_qualify('user'));
    }

    // a disabled domain is qualified too: the login then fails as for the full address (denied, no netfilter strike)
    public function testQualifyInactiveDomain(): void
    {
        $_SERVER['HTTP_HOST'] = 'mail.inactive.test';
        $this->assertSame('user@inactive.test', bare_localpart_login_qualify('user'));
    }

    public static function flags(): array
    {
        return [
            // [$ALLOW_BARE_LOCALPART_LOGIN (null: not set), environment (null: not set), enabled]
            'nothing set' => [null, null, false],
            'environment only' => [null, 'y', true],
            'vars.inc.php without environment' => [false, null, false],
            'global n wins over environment y' => ['n', 'y', false],
            'global y wins over environment n' => ['y', 'n', true],
            'global yes' => ['yes', null, true],
            'global empty' => ['', 'y', false],
            'other value' => ['true', null, false],
            'global true (vars.local.inc.php)' => [true, 'n', true],
            'global false (vars.local.inc.php)' => [false, 'y', false],
        ];
    }

    #[DataProvider('flags')]
    public function testFlagPrecedence(string|bool|null $global, ?string $env, bool $enabled): void
    {
        unset($GLOBALS['ALLOW_BARE_LOCALPART_LOGIN']);
        if ($global !== null) {
            $GLOBALS['ALLOW_BARE_LOCALPART_LOGIN'] = $global;
        }
        putenv($env === null ? 'ALLOW_BARE_LOCALPART_LOGIN' : 'ALLOW_BARE_LOCALPART_LOGIN=' . $env);

        $this->assertSame($enabled, bare_localpart_login_enabled());
        $this->assertSame($enabled ? 'user@example.org' : 'user', bare_localpart_login_qualify('user'));
    }

    public static function flagValues(): array
    {
        return [
            // exactly y or yes, in any case, like the other mailcow.conf switches
            'y' => ['y', true],
            'Y' => ['Y', true],
            'yes' => ['yes', true],
            'YES' => ['YES', true],
            'Yes' => ['Yes', true],
            // anything else is off: no repetition, no surrounding whitespace (as for the other switches)
            'yy' => ['yy', false],
            'yesy' => ['yesy', false],
            'yyes' => ['yyes', false],
            'YyEs' => ['YyEs', false],
            'spaces' => [' y ', false],
            'trailing newline' => ["y\n", false],
            'n' => ['n', false],
            'no' => ['no', false],
            'empty' => ['', false],
            '1' => ['1', false],
        ];
    }

    #[DataProvider('flagValues')]
    public function testFlagValue(string $value, bool $enabled): void
    {
        $GLOBALS['ALLOW_BARE_LOCALPART_LOGIN'] = $value;
        $this->assertSame($enabled, bare_localpart_login_enabled());

        unset($GLOBALS['ALLOW_BARE_LOCALPART_LOGIN']);
        putenv('ALLOW_BARE_LOCALPART_LOGIN=' . $value);
        $this->assertSame($enabled, bare_localpart_login_enabled(), 'from the environment');
    }

    public function testCheckLoginUserParameterIsByReference(): void
    {
        $param = (new ReflectionFunction('check_login'))->getParameters()[0];
        $this->assertTrue($param->isPassedByReference());
        $this->assertSame('user', $param->getName());
    }

    public function testAdminAndDomainAdminLoginSignaturesAreNotByReference(): void
    {
        $this->assertFalse((new ReflectionFunction('admin_login'))->getParameters()[0]->isPassedByReference());
        $this->assertFalse((new ReflectionFunction('domainadmin_login'))->getParameters()[0]->isPassedByReference());
    }
}
