#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Regression test for issue #6913 / PR #6914.
 *
 * Ensures that user_login() returns "user" for internal IMAP/SMTP requests
 * when TFA is enabled (internal auth must bypass TFA), while UI logins with
 * the same account and TFA still return "pending".
 *
 * Run via: php tests/regression/tfa_internal_auth_test.php
 */

$repoRoot = realpath(__DIR__ . '/../../');
require_once $repoRoot . '/data/web/inc/functions.inc.php';
require_once $repoRoot . '/data/web/inc/functions.auth.inc.php';

$_SESSION = [];

// Globals required by the auth helpers.
$iam_provider = [];
$iam_settings = [
    'authsource' => 'mailcow',
    'mailpassword_flow' => 0,
];

// In-memory SQLite database keeps the test isolated.
/** @var PDO $pdo */
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (method_exists($pdo, 'sqliteCreateFunction')) {
    $pdo->sqliteCreateFunction('REGEXP', function (?string $pattern, ?string $value): int {
        if ($pattern === null || $value === null) {
            return 0;
        }
        $delimiter = '#';
        $escaped = str_replace($delimiter, '\\' . $delimiter, $pattern);
        return preg_match($delimiter . $escaped . $delimiter . 'i', $value) === 1 ? 1 : 0;
    }, 2);
}

$pdo->exec(<<<'SQL'
CREATE TABLE domain (
  domain TEXT PRIMARY KEY,
  active INTEGER NOT NULL
);
SQL);

$pdo->exec(<<<'SQL'
CREATE TABLE mailbox (
  username TEXT PRIMARY KEY,
  domain TEXT NOT NULL,
  password TEXT NOT NULL,
  active INTEGER NOT NULL,
  attributes TEXT NOT NULL,
  authsource TEXT NOT NULL,
  kind TEXT NOT NULL
);
SQL);

$pdo->exec(<<<'SQL'
CREATE TABLE tfa (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  key_id TEXT,
  authmech TEXT NOT NULL,
  secret TEXT,
  active INTEGER NOT NULL
);
SQL);

$pdo->prepare('INSERT INTO domain (domain, active) VALUES (:domain, :active)')
    ->execute([':domain' => 'example.org', ':active' => 1]);

$mailPassword = '{BLF-CRYPT}' . password_hash('Sup3rSecret!', PASSWORD_BCRYPT);
$attributes = json_encode([
    'force_pw_update' => 0,
    'imap_access' => '1',
    'smtp_access' => '1',
]);
$pdo->prepare('INSERT INTO mailbox (username, domain, password, active, attributes, authsource, kind)
               VALUES (:username, :domain, :password, 1, :attributes, :authsource, "")')
    ->execute([
        ':username' => 'user@example.org',
        ':domain' => 'example.org',
        ':password' => $mailPassword,
        ':attributes' => $attributes,
        ':authsource' => 'mailcow',
    ]);

// Helper for deterministic assertions.
function assertSameResult($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: {$message} (expected '{$expected}', got '{$actual}')\n");
        exit(1);
    }
    fwrite(STDOUT, "✅ {$message}\n");
}

function resetSession(): void
{
    global $_SESSION;
    $_SESSION = [];
}

function seedTfaEntries(PDO $pdo, bool $enabled): void
{
    $pdo->exec('DELETE FROM tfa');
    if ($enabled) {
        $stmt = $pdo->prepare('INSERT INTO tfa (username, key_id, authmech, secret, active)
                               VALUES (:username, :key_id, :authmech, :secret, 1)');
        $stmt->execute([
            ':username' => 'user@example.org',
            ':key_id' => 'totp-1',
            ':authmech' => 'totp',
            ':secret' => 'ABC123',
        ]);
    }
}

function performLogin(bool $isInternal): string|false
{
    resetSession();
    return user_login(
        'user@example.org',
        'Sup3rSecret!',
        [
            'is_internal' => $isInternal,
            'service' => 'imap',
        ]
    );
}

seedTfaEntries($pdo, true);

$result = performLogin(false);
assertSameResult('pending', $result, 'External (UI) login with TFA requires pending state');

$result = performLogin(true);
assertSameResult('user', $result, 'Internal IMAP login with TFA bypasses interactive TFA');

seedTfaEntries($pdo, false);
$result = performLogin(true);
assertSameResult('user', $result, 'Internal IMAP login without TFA continues to succeed');

fwrite(STDOUT, "All regression checks passed.\n");
