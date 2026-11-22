<?php

declare(strict_types=1);

namespace Mailcow\Tests\Auth;

use Mailcow\Auth\OAuth2Storage;
use Mailcow\Tests\Support\CountingPdo;
use Mailcow\Tests\Support\LegacyLoginFlow;
use OAuth2\GrantType\UserCredentials;
use OAuth2\Request;
use OAuth2\Response;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

require_once __DIR__ . '/../Support/legacy_auth_functions.php';
require_once __DIR__ . '/../Support/legacy_login_flow_functions.php';
require_once __DIR__ . '/../../../data/web/inc/functions.auth.inc.php';

// The whole legacy login flow (check_login() -> admin/domain admin/app password/mailbox login) with
// ALLOW_BARE_LOCALPART_LOGIN, on an in-memory SQLite database: which identity the callers get back
// (UI session, SOGo X-User, OAuth token) and what netfilter is told.
class BareLocalpartLoginFlowTest extends TestCase
{
    private const PROTOCOLS = ['imap_access', 'smtp_access', 'pop3_access', 'sieve_access', 'eas_access', 'dav_access'];
    private const IP = '192.0.2.1';

    private CountingPdo $pdo;
    private array $savedServer;
    private array|null $savedSession;
    private array $savedGlobals;
    private const GLOBALS = ['pdo', 'redis', 'iam_settings', 'iam_provider', 'ALLOW_BARE_LOCALPART_LOGIN'];
    private string $errorLog;
    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        $this->savedSession = $_SESSION ?? null;
        $this->savedGlobals = array_intersect_key($GLOBALS, array_flip(self::GLOBALS));
        $this->pdo = new CountingPdo('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('regexp', fn ($pattern, $value) => preg_match('/' . $pattern . '/', (string)$value) === 1, 2);
        $this->pdo->exec("CREATE TABLE `domain` (`domain` TEXT PRIMARY KEY, `active` INTEGER NOT NULL DEFAULT 1)");
        $this->pdo->exec("CREATE TABLE `mailbox` (`username` TEXT PRIMARY KEY, `domain` TEXT, `kind` TEXT NOT NULL DEFAULT '',
            `active` INTEGER NOT NULL DEFAULT 1, `password` TEXT, `authsource` TEXT NOT NULL DEFAULT 'mailcow', `attributes` TEXT)");
        $this->pdo->exec("CREATE TABLE `app_passwd` (`id` INTEGER PRIMARY KEY, `name` TEXT, `mailbox` TEXT, `password` TEXT,
            `active` INTEGER NOT NULL DEFAULT 1, " . implode(', ', array_map(fn ($p) => "`$p` INTEGER NOT NULL DEFAULT 0", self::PROTOCOLS)) . ")");
        $this->pdo->exec("CREATE TABLE `admin` (`username` TEXT PRIMARY KEY, `password` TEXT, `superadmin` INTEGER NOT NULL DEFAULT 0,
            `active` INTEGER NOT NULL DEFAULT 1, `attributes` TEXT)");
        $this->pdo->exec("CREATE TABLE `tfa` (`username` TEXT, `active` INTEGER NOT NULL DEFAULT 1)");

        $this->pdo->exec("INSERT INTO `domain` (`domain`, `active`) VALUES ('example.org', 1), ('example.net', 1)");
        $this->addMailbox('alice@example.org', 'secret');
        $this->addMailbox('bob@example.net', 'secret');
        $this->addMailbox('carol@example.org', 'secret');
        $this->addAppPasswd('alice@example.org', 'app-secret', ['eas_access' => 1]);

        LegacyLoginFlow::reset();
        $GLOBALS['pdo'] = $this->pdo;
        $GLOBALS['redis'] = LegacyLoginFlow::redis();
        $GLOBALS['iam_settings'] = ['authsource' => 'mailcow'];
        $GLOBALS['iam_provider'] = null;
        $GLOBALS['ALLOW_BARE_LOCALPART_LOGIN'] = 'y';
        $_SERVER['HTTP_HOST'] = 'mail.example.org';
        $_SERVER['REMOTE_ADDR'] = self::IP;
        $_SESSION = [];
        $this->errorLog = (string)tempnam(sys_get_temp_dir(), 'mailcow-login-');
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
        $_SERVER = $this->savedServer;
        if ($this->savedSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->savedSession;
        }
        LegacyLoginFlow::reset();
        ini_set('error_log', (string)$this->previousErrorLog);
        @unlink($this->errorLog);
    }

    // UI login (triggers.user.inc.php): role user, the session is created with the name check_login() hands back

    public function testUiBareNameLogsInAsFullAddress(): void
    {
        $login = 'alice';
        $this->assertSame('user', check_login($login, 'secret', ['role' => 'user', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('alice@example.org', $login);
        $this->assertSame([['alice@example.org', 'MAILCOWUI']], LegacyLoginFlow::$saslLog);
        $this->assertSame([], LegacyLoginFlow::strikes());
    }

    public function testUiBareNameOnSecondDomainHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'mail.example.net';
        $login = 'bob';
        $this->assertSame('user', check_login($login, 'secret', ['role' => 'user', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('bob@example.net', $login);
    }

    public function testUiFullAddressOfOtherDomainIsKept(): void
    {
        $login = 'bob@example.net';
        $this->assertSame('user', check_login($login, 'secret', ['role' => 'user', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('bob@example.net', $login);
    }

    public function testUiBareNameOfOtherDomainFailsOnWrongHost(): void
    {
        $login = 'bob';
        $this->assertFalse(check_login($login, 'secret', ['role' => 'user', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('bob@example.org', $login);
        $this->assertSame(['mailcow UI: Invalid password for bob@example.org by ' . self::IP], LegacyLoginFlow::strikes());
    }

    public function testFeatureOffKeepsPreviousBehaviour(): void
    {
        $GLOBALS['ALLOW_BARE_LOCALPART_LOGIN'] = 'n';
        $login = 'alice';
        $this->assertFalse(check_login($login, 'secret', ['role' => 'user', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('alice', $login);
        $this->assertSame(['mailcow UI: Invalid password for alice by ' . self::IP], LegacyLoginFlow::strikes());

        $login = 'alice@example.org';
        $this->assertSame('user', check_login($login, 'secret', ['role' => 'user', 'service' => 'MAILCOWUI', 'is_internal' => false]));
    }

    public function testWrongPasswordIsOneStrikeForTheFullAddress(): void
    {
        $login = 'alice';
        $this->assertFalse(check_login($login, 'wrong', ['role' => 'user', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame(['mailcow UI: Invalid password for alice@example.org by ' . self::IP], LegacyLoginFlow::strikes());
    }

    public function testSecondFactorIsPendingForTheFullAddress(): void
    {
        LegacyLoginFlow::$tfa['carol@example.org'] = [['id' => 1, 'authmech' => 'totp']];
        $login = 'carol';
        $this->assertSame('pending', check_login($login, 'secret', ['role' => 'user', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('carol@example.org', $_SESSION['pending_mailcow_cc_username']);
        $this->assertSame('user', $_SESSION['pending_mailcow_cc_role']);
    }

    // admin and domain admin forms pass their role: the name is never rewritten

    public function testExplicitAdminRolesKeepTheName(): void
    {
        $this->addAdmin('alice', 'admin-secret', 1);
        $this->addAdmin('dadmin', 'dadmin-secret', 0);

        $login = 'alice';
        $this->assertSame('admin', check_login($login, 'admin-secret', ['role' => 'admin', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('alice', $login);

        $login = 'dadmin';
        $this->assertSame('domainadmin', check_login($login, 'dadmin-secret', ['role' => 'domain_admin', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('dadmin', $login);

        // the mailbox password on the admin form is a failed admin login of "alice", not of the mailbox
        $_SESSION = [];
        $login = 'alice';
        $this->assertFalse(check_login($login, 'secret', ['role' => 'admin', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('alice', $login);
        $this->assertSame(['mailcow UI: Invalid password for alice by ' . self::IP], LegacyLoginFlow::strikes());
    }

    // SOGo/EAS/DAV (sogo-auth.php): no role, X-User is the name check_login() hands back

    public function testSogoBareNameGivesFullAddress(): void
    {
        $login = 'alice';
        $this->assertSame('user', $this->sogoLogin($login, 'secret', 'EAS'));
        $this->assertSame('alice@example.org', $login);
    }

    public function testSogoAppPasswordKeepsItsServiceRestrictions(): void
    {
        $login = 'alice';
        $this->assertSame('user', $this->sogoLogin($login, 'app-secret', 'EAS'));
        $this->assertSame('alice@example.org', $login);

        // the app password has no DAV access: denied, not counted by netfilter
        $_SESSION = [];
        $login = 'alice';
        $this->assertFalse($this->sogoLogin($login, 'app-secret', 'DAV'));
        $this->assertSame([], LegacyLoginFlow::strikes());
    }

    // Known limit (documented): without a role, admin and domain admin are tried first with the name as typed.
    // An admin called "alice" with the same password wins; sogo-auth.php then refuses, as it only accepts "user".
    public function testSogoAdminWithTheSameNameAndPasswordIsTriedFirst(): void
    {
        $this->addAdmin('alice', 'secret', 1);
        $login = 'alice';
        $this->assertSame('admin', $this->sogoLogin($login, 'secret', 'EAS'));
        $this->assertSame('alice', $login);
    }

    // Disabled domain: the right password is denied, not a strike, as for the full address

    public static function disabledDomainLogins(): array
    {
        return [
            // [username, password, role (null: SOGo/EAS/DAV), service]
            'ui, bare' => ['alice', 'secret', 'user', 'MAILCOWUI'],
            'ui, full address' => ['alice@example.org', 'secret', 'user', 'MAILCOWUI'],
            'eas, bare' => ['alice', 'secret', null, 'EAS'],
            'eas, bare, app password' => ['alice', 'app-secret', null, 'EAS'],
            'eas, full address' => ['alice@example.org', 'secret', null, 'EAS'],
        ];
    }

    #[DataProvider('disabledDomainLogins')]
    public function testDisabledDomainDeniesWithoutStrike(string $username, string $password, ?string $role, string $service): void
    {
        $attempt = function (string $password) use ($username, $role, $service): string|false {
            $_SESSION = [];
            LegacyLoginFlow::$published = [];
            $login = $username;
            $result = $role === null ? $this->sogoLogin($login, $password, $service)
                : check_login($login, $password, ['role' => $role, 'service' => $service, 'is_internal' => false]);
            $this->assertSame('alice@example.org', $login);
            return $result;
        };

        $this->assertSame('user', $attempt($password));

        $this->pdo->exec("UPDATE `domain` SET `active` = 0 WHERE `domain` = 'example.org'");
        $this->assertFalse($attempt($password));
        $this->assertSame([], LegacyLoginFlow::strikes(), 'right password on a disabled domain must not be a strike');
        $this->assertStringContainsString('mailcow UI: Login denied for alice@example.org by ' . self::IP, (string)file_get_contents($this->errorLog));
        $this->assertFalse($attempt('wrong'));
        $this->assertSame(['mailcow UI: Invalid password for alice@example.org by ' . self::IP], LegacyLoginFlow::strikes());

        $this->pdo->exec("UPDATE `domain` SET `active` = 1 WHERE `domain` = 'example.org'");
        $this->assertSame('user', $attempt($password));
    }

    public function testUnknownDomainHostLeavesTheNameAlone(): void
    {
        $_SERVER['HTTP_HOST'] = 'mail.unknown.example';
        $login = 'alice';
        $this->assertFalse(check_login($login, 'secret', ['role' => 'user', 'service' => 'MAILCOWUI', 'is_internal' => false]));
        $this->assertSame('alice', $login);
    }

    // Dovecot (mailcowauth.php) asks https://nginx:9082: that Host never qualifies

    // The name is resolved once, in check_login(): the verifiers take it as given, on any Host
    public function testVerifiersDoNotQualifyThemselves(): void
    {
        $denied = false;
        $this->assertFalse(user_login('alice', 'secret', ['is_internal' => true, 'service' => 'imap'], $denied));
        $this->assertFalse(apppass_login('alice', 'app-secret', ['is_internal' => true, 'service' => 'eas'], $denied));
        $this->assertSame(0, $this->pdo->domainLookups);
    }

    public static function lookupCounts(): array
    {
        return [
            // [host, username, role (null: SOGo/EAS/DAV), password]
            'ui, known domain' => ['mail.example.org', 'alice', 'user', 'secret'],
            'ui, unknown domain' => ['mail.unknown.example', 'alice', 'user', 'secret'],
            'eas, known domain' => ['mail.example.org', 'alice', null, 'app-secret'],
            'eas, unknown domain' => ['mail.unknown.example', 'alice', null, 'secret'],
            'eas, wrong password' => ['mail.example.org', 'alice', null, 'wrong'],
        ];
    }

    #[DataProvider('lookupCounts')]
    public function testOneDomainLookupPerLogin(string $host, string $username, ?string $role, string $password): void
    {
        $_SERVER['HTTP_HOST'] = $host;
        $login = $username;
        $role === null ? $this->sogoLogin($login, $password, 'EAS')
            : check_login($login, $password, ['role' => $role, 'service' => 'MAILCOWUI', 'is_internal' => false]);
        $this->assertSame(1, $this->pdo->domainLookups);
    }

    public function testDovecotInternalRequestIsUnchanged(): void
    {
        $_SERVER['HTTP_HOST'] = 'nginx:9082';
        $denied = false;
        $this->assertFalse(user_login('alice', 'secret', ['is_internal' => true, 'service' => 'imap'], $denied));
        $this->assertFalse(apppass_login('alice', 'app-secret', ['is_internal' => true, 'service' => 'eas'], $denied));
        $this->assertSame('user', user_login('alice@example.org', 'secret', ['is_internal' => true, 'service' => 'imap'], $denied));
    }

    // OAuth2 password grant (oauth/token.php): the token belongs to the mailbox whose password was checked

    public static function passwordGrants(): array
    {
        return [
            // [host, username, user_id]
            'bare' => ['mail.example.org', 'alice', 'alice@example.org'],
            'bare, second domain' => ['mail.example.net', 'bob', 'bob@example.net'],
            'full address of other domain' => ['mail.example.org', 'bob@example.net', 'bob@example.net'],
            'full address' => ['mail.example.org', 'alice@example.org', 'alice@example.org'],
        ];
    }

    #[DataProvider('passwordGrants')]
    public function testOAuthPasswordGrantUsesTheCheckedMailbox(string $host, string $username, string $userId): void
    {
        $_SERVER['HTTP_HOST'] = $host;
        $grant = new UserCredentials(new OAuth2Storage($this->pdo));
        $response = new Response();

        $this->assertTrue($grant->validateRequest($this->passwordGrant($username, 'secret'), $response), (string)$response->getParameter('error_description'));
        $this->assertSame($userId, $grant->getUserId());
    }

    public function testOAuthPasswordGrantWrongPassword(): void
    {
        $grant = new UserCredentials(new OAuth2Storage($this->pdo));
        $response = new Response();

        $this->assertNull($grant->validateRequest($this->passwordGrant('alice', 'wrong'), $response));
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('invalid_grant', $response->getParameter('error'));
    }

    public function testOAuthStorageReadsTheMailboxTable(): void
    {
        $storage = new OAuth2Storage($this->pdo);
        $this->assertSame('alice@example.org', $storage->getUserDetails('alice@example.org')['user_id']);
        // a name that was never checked is looked up as given
        $this->assertFalse($storage->getUserDetails('alice'));
    }

    // Login form for the Apple profile (index.php?mobileconfig): the browser must accept a bare name

    public static function loginForms(): array
    {
        return [
            // [is_mobileconfig, bare login applies, input type]
            'mobileconfig, feature applies' => [true, true, 'text'],
            'mobileconfig, feature off' => [true, false, 'email'],
            'normal login, feature applies' => [false, true, 'text'],
            'normal login, feature off' => [false, false, 'text'],
        ];
    }

    #[DataProvider('loginForms')]
    public function testLoginFormUsernameType(bool $mobileconfig, bool $bare, string $type): void
    {
        $html = $this->renderUserIndex(['is_mobileconfig' => $mobileconfig, 'bare_localpart_login' => $bare]);
        $this->assertMatchesRegularExpression('/<input name="login_user"[^>]* type="' . $type . '"/', $html);
    }

    public static function formHosts(): array
    {
        return [
            // [flag, host, applies]
            'on, mail host' => ['y', 'mail.example.org', true],
            'on, unknown mail host' => ['y', 'mail.unknown.example', true],
            'on, other host' => ['y', 'example.org', false],
            'off, mail host' => ['n', 'mail.example.org', false],
        ];
    }

    #[DataProvider('formHosts')]
    public function testBareLoginAppliesToTheForm(string $flag, string $host, bool $applies): void
    {
        $GLOBALS['ALLOW_BARE_LOCALPART_LOGIN'] = $flag;
        $_SERVER['HTTP_HOST'] = $host;
        $this->assertSame($applies, bare_localpart_login_applies());
    }

    public function testIndexPassesTheFlagToTheTemplate(): void
    {
        $index = file_get_contents(__DIR__ . '/../../../data/web/index.php');
        $this->assertStringContainsString("'bare_localpart_login' => bare_localpart_login_applies(),", $index);
    }

    // sogo-auth.php calls check_login() without a role. Without a role admin_login() and domainadmin_login()
    // read a missing admin row first (PHP warning in the legacy code, as in production), hence the @.
    private function sogoLogin(string &$login, string $password, string $service): string|false
    {
        return @check_login($login, $password, ['service' => $service, 'is_internal' => false]);
    }

    private function passwordGrant(string $username, string $password): Request
    {
        return new Request([], ['grant_type' => 'password', 'username' => $username, 'password' => $password], [], [], [], ['REQUEST_METHOD' => 'POST']);
    }

    private function renderUserIndex(array $data): string
    {
        $loader = new ChainLoader([
            new ArrayLoader(['base.twig' => '{% block content %}{% endblock %}']),
            new FilesystemLoader(__DIR__ . '/../../../data/web/templates'),
        ]);
        $twig = new Environment($loader, ['strict_variables' => false]);
        $twig->addFilter(new TwigFilter('rot13', 'str_rot13'));
        $twig->addFunction(new TwigFunction('query_string', fn (...$args) => ''));
        $twig->addFunction(new TwigFunction('is_uri', fn (...$args) => false));
        return $twig->render('user_index.twig', $data + ['lang' => ['login' => [], 'oauth2' => []], 'ui_texts' => [], 'custom_login' => []]);
    }

    private function addMailbox(string $username, string $password): void
    {
        $attributes = array_merge(array_fill_keys(self::PROTOCOLS, '1'), ['shared' => '0', 'force_tfa' => '0', 'force_pw_update' => '0']);
        $this->pdo->prepare("INSERT INTO `mailbox` (`username`, `domain`, `password`, `attributes`) VALUES (?, ?, ?, ?)")
            ->execute([$username, substr($username, strpos($username, '@') + 1), $this->hash($password), json_encode($attributes)]);
    }

    private function addAppPasswd(string $mailbox, string $password, array $protocols): void
    {
        $row = array_merge(array_fill_keys(self::PROTOCOLS, 0), $protocols);
        $this->pdo->prepare("INSERT INTO `app_passwd` (`name`, `mailbox`, `password`, " . implode(', ', array_map(fn ($c) => "`$c`", array_keys($row))) . ")
            VALUES ('test', ?, ?, " . implode(', ', array_fill(0, count($row), '?')) . ")")
            ->execute(array_merge([$mailbox, $this->hash($password)], array_values($row)));
    }

    private function addAdmin(string $username, string $password, int $superadmin): void
    {
        $this->pdo->prepare("INSERT INTO `admin` (`username`, `password`, `superadmin`, `attributes`) VALUES (?, ?, ?, '{}')")
            ->execute([$username, $this->hash($password), $superadmin]);
    }

    private function hash(string $password): string
    {
        return '{BLF-CRYPT}' . password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    }
}
