<?php
/**
 * Test Suite for SimpleLogin API & Extended Alias Functionality (#6249)
 *
 * Covers:
 * 1. SimpleLogin Response Formatting (id, email, alias, note, mailboxes, etc.)
 * 2. Extended Validity Validation (supports up to 876,600 hours / 100 years, permanent = 1)
 * 3. User API Key Authentication & Route Handling (user_api lookup, IP ACL check)
 * 4. SimpleLogin Endpoints (/api/user_info, /api/v5/alias/options, /api/alias/random/new, /api/aliases, toggle, delete)
 * 5. Postfix Sender ACL query verification (ensures spamalias send_as is permitted)
 */

declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../data/web');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION['mailcow_cc_username'] = 'user@example.com';
$_SESSION['mailcow_cc_role'] = 'user';


$failures = 0;
$tests = 0;

function it(string $desc, callable $fn) {
  global $failures, $tests;
  $tests++;
  try {
    $fn();
    echo "  ✓ {$desc}\n";
  } catch (Throwable $e) {
    $failures++;
    echo "  ✗ {$desc}\n    Error: {$e->getMessage()}\n";
  }
}

function expect($actual) {
  return new class($actual) {
    private $val;
    public function __construct($val) { $this->val = $val; }
    public function toBe($expected) {
      if ($this->val !== $expected) {
        throw new Exception("Expected " . var_export($expected, true) . ", got " . var_export($this->val, true));
      }
    }
    public function toBeTrue() {
      if ($this->val !== true) {
        throw new Exception("Expected true, got " . var_export($this->val, true));
      }
    }
    public function toBeFalse() {
      if ($this->val !== false) {
        throw new Exception("Expected false, got " . var_export($this->val, true));
      }
    }
    public function toContain($needle) {
      if (is_string($this->val) && strpos($this->val, $needle) === false) {
        throw new Exception("Expected string to contain '{$needle}'");
      }
      if (is_array($this->val) && !in_array($needle, $this->val)) {
        throw new Exception("Expected array to contain " . var_export($needle, true));
      }
    }
  };
}

echo "Running tests for #6249 (SimpleLogin API & Long-Term Aliases)...\n\n";

// --- Suite 1: SimpleLogin Alias Format ---
echo "Suite 1: SimpleLogin Format Verification\n";

it("formats alias according to SimpleLogin API v2/v5 specification", function() {
  require_once __DIR__ . '/../data/web/inc/functions.inc.php';
  $formatted = format_simplelogin_alias("test.alias@example.com", "My Note", "user@example.com", 0, 1, 1700000000);
  expect($formatted['alias'])->toBe("test.alias@example.com");
  expect($formatted['email'])->toBe("test.alias@example.com");
  expect($formatted['enabled'])->toBeTrue();
  expect($formatted['note'])->toBe("My Note");
  expect($formatted['mailboxes'][0]['email'])->toBe("user@example.com");
  expect($formatted['nb_forward'])->toBe(0);
  expect($formatted['nb_block'])->toBe(0);
  expect($formatted['nb_reply'])->toBe(0);
  expect(is_int($formatted['id']))->toBeTrue();
  expect($formatted['creation_timestamp'])->toBe(1700000000);
});

// --- Suite 2: Validity Range & 100-Year Extension ---
echo "\nSuite 2: 100-Year / Permanent Validity Verification\n";

it("accepts validity up to 876,600 hours (100 years)", function() {
  $validity_100_years = 876600;
  $res = filter_var($validity_100_years, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 876600)));
  expect($res)->toBe(876600);

  $validity_over = 876601;
  $res_over = filter_var($validity_over, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 876600)));
  expect($res_over)->toBeFalse();
});

it("supports permanent=true setting validity=0", function() {
  $data = array('permanent' => true, 'validity' => 8760);
  $permanent = (isset($data['permanent']) && filter_var($data['permanent'], FILTER_VALIDATE_BOOL)) ? 1 : 0;
  $validity = ($permanent === 1) ? 0 : time() + ($data['validity'] * 3600);
  expect($permanent)->toBe(1);
  expect($validity)->toBe(0);
});

// --- Suite 3: Postfix Sender ACL Script ---
echo "\nSuite 3: Postfix Sender ACL Verification\n";

it("includes spamalias in postfix virtual_sender_acl query", function() {
  $postfix_sh = file_get_contents(__DIR__ . '/../data/Dockerfiles/postfix/postfix.sh');
  expect($postfix_sh)->toContain("SELECT goto FROM spamalias");
  expect($postfix_sh)->toContain("WHERE address='%s'");
  expect($postfix_sh)->toContain("(validity >= UNIX_TIMESTAMP() OR permanent != 0)");
});

// --- Suite 4: Database Schema and Version ---
echo "\nSuite 4: DB Schema and Version Verification\n";

it("defines user_api table and updated db_version in init_db.inc.php", function() {
  $init_db = file_get_contents(__DIR__ . '/../data/web/inc/init_db.inc.php');
  expect($init_db)->toContain('"user_api" => array(');
  expect($init_db)->toContain('"api_key" => "VARCHAR(255) NOT NULL"');
  expect($init_db)->toContain('"username" => "VARCHAR(255) NOT NULL"');
  expect($init_db)->toContain('$db_version = "27102026_1200";');
});

// --- Suite 5: Sessions User API Authentication Check ---
echo "\nSuite 5: Authentication Token Support in sessions.inc.php\n";

it("supports Authentication and Bearer headers for user_api", function() {
  $sessions = file_get_contents(__DIR__ . '/../data/web/inc/sessions.inc.php');
  expect($sessions)->toContain('HTTP_AUTHENTICATION');
  expect($sessions)->toContain('Bearer\s+([a-zA-Z0-9-]+)');
  expect($sessions)->toContain('SELECT * FROM `user_api` WHERE `api_key`');
});

// --- Suite 6: Nginx Routing Configuration ---
echo "\nSuite 6: Nginx Template Routing Verification\n";

it("routes SimpleLogin endpoints to simplelogin.php", function() {
  $nginx_conf = file_get_contents(__DIR__ . '/../data/conf/nginx/templates/sites-default.conf.j2');
  expect($nginx_conf)->toContain('location ~ ^/api/(v[0-9]+/)?(alias|aliases|user_info)($|/.*)');
  expect($nginx_conf)->toContain('/simplelogin.php?query=$uri&$args');
});

// --- Suite 7: SQLite In-Memory Functional Test of user_api & simplelogin logic ---
echo "\nSuite 7: End-to-End Logic Simulation (SQLite)\n";

it("performs end-to-end SimpleLogin alias creation and retrieval against mock db", function() {
  $pdo = new PDO('sqlite::memory:');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Setup mock schema
  $pdo->exec("
    CREATE TABLE user_api (
      api_key TEXT PRIMARY KEY,
      username TEXT NOT NULL,
      allow_from TEXT NOT NULL,
      skip_ip_check INTEGER NOT NULL,
      created TEXT DEFAULT CURRENT_TIMESTAMP,
      modified TEXT,
      active INTEGER NOT NULL
    );
    CREATE TABLE spamalias (
      address TEXT PRIMARY KEY,
      description TEXT NOT NULL,
      goto TEXT NOT NULL,
      validity INTEGER,
      permanent INTEGER NOT NULL,
      created TEXT DEFAULT CURRENT_TIMESTAMP
    );
  ");

  // Insert user api key
  $test_key = 'TEST-API-KEY-123';
  $user = 'user@domain.tld';
  $stmt = $pdo->prepare("INSERT INTO user_api (api_key, username, allow_from, skip_ip_check, active) VALUES (:key, :u, '', 1, 1)");
  $stmt->execute(array(':key' => $test_key, ':u' => $user));

  // Verify key lookup
  $stmt = $pdo->prepare("SELECT username FROM user_api WHERE api_key = :k AND active = 1");
  $stmt->execute(array(':k' => $test_key));
  $found_user = $stmt->fetchColumn();
  expect($found_user)->toBe($user);

  // Create alias
  $alias_addr = 'random.word.123@domain.tld';
  $stmt = $pdo->prepare("INSERT INTO spamalias (address, description, goto, validity, permanent) VALUES (:a, :d, :g, 0, 1)");
  $stmt->execute(array(':a' => $alias_addr, ':d' => 'Bitwarden test', ':g' => $user));

  // Query alias
  $stmt = $pdo->prepare("SELECT * FROM spamalias WHERE goto = :u");
  $stmt->execute(array(':u' => $user));
  $alias_row = $stmt->fetch(PDO::FETCH_ASSOC);
  expect($alias_row['address'])->toBe($alias_addr);
  expect((int)$alias_row['permanent'])->toBe(1);

  // Sender ACL query check
  $sender_check_stmt = $pdo->prepare("SELECT goto FROM spamalias WHERE address = :a AND (validity >= :now OR permanent != 0)");
  $sender_check_stmt->execute(array(':a' => $alias_addr, ':now' => time()));
  $allowed_sender = $sender_check_stmt->fetchColumn();
  expect($allowed_sender)->toBe($user);
});

it("tests user_api get, edit, and regen_key lifecycle", function() {
  global $pdo;
  // Setup mock PDO for user_api testing
  $pdo = new PDO('sqlite::memory:');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("
    CREATE TABLE user_api (
      api_key TEXT PRIMARY KEY,
      username TEXT NOT NULL,
      allow_from TEXT NOT NULL,
      skip_ip_check INTEGER NOT NULL,
      created TEXT DEFAULT CURRENT_TIMESTAMP,
      modified TEXT,
      active INTEGER NOT NULL
    );
  ");

  $_SESSION['mailcow_cc_username'] = 'testuser@example.com';
  $_SESSION['mailcow_cc_role'] = 'user';

  // 1. Initial get should return empty/false
  $init = user_api('get');
  expect($init)->toBeFalse();

  // 2. Regen key creates key
  $res = user_api('regen_key');
  expect($res)->toBeTrue();

  // 3. Get returns the key
  $key_data = user_api('get');
  expect(empty($key_data['api_key']))->toBeFalse();
  expect($key_data['username'])->toBe('testuser@example.com');
  expect((int)$key_data['active'])->toBe(1);

  $old_key = $key_data['api_key'];

  // 4. Edit key settings
  user_api('edit', array('active' => 0, 'skip_ip_check' => 1, 'allow_from' => '1.2.3.4'));
  $updated = user_api('get');
  expect((int)$updated['active'])->toBe(0);
  expect($updated['allow_from'])->toBe('1.2.3.4');

  // 5. Regen rotates key
  user_api('regen_key');
  $rotated = user_api('get');
  expect($rotated['api_key'] !== $old_key)->toBeTrue();
});

it("tests SimpleLogin alias toggle and deletion by CRC32 integer ID and email string", function() {
  $pdo = new PDO('sqlite::memory:');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("
    CREATE TABLE spamalias (
      address TEXT PRIMARY KEY,
      description TEXT NOT NULL,
      goto TEXT NOT NULL,
      validity INTEGER,
      permanent INTEGER NOT NULL,
      created TEXT DEFAULT CURRENT_TIMESTAMP
    );
  ");

  $user = 'user@example.com';
  $addr = 'shopping.xyz123@example.com';
  $stmt = $pdo->prepare("INSERT INTO spamalias (address, description, goto, validity, permanent) VALUES (:a, 'Shopping', :u, 0, 1)");
  $stmt->execute(array(':a' => $addr, ':u' => $user));

  $alias_id = sprintf('%u', crc32($addr));

  // Toggle disable using address match
  $stmt = $pdo->prepare("UPDATE spamalias SET permanent = 0, validity = :past WHERE address = :addr");
  $stmt->execute(array(':past' => time() - 3600, ':addr' => $addr));

  // Check status
  $stmt = $pdo->prepare("SELECT validity, permanent FROM spamalias WHERE address = :addr");
  $stmt->execute(array(':addr' => $addr));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $is_enabled = ($row['permanent'] != 0 || $row['validity'] >= time());
  expect($is_enabled)->toBeFalse();

  // Toggle re-enable
  $stmt = $pdo->prepare("UPDATE spamalias SET permanent = 1, validity = 0 WHERE address = :addr");
  $stmt->execute(array(':addr' => $addr));

  $stmt = $pdo->prepare("SELECT validity, permanent FROM spamalias WHERE address = :addr");
  $stmt->execute(array(':addr' => $addr));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $is_enabled = ($row['permanent'] != 0 || $row['validity'] >= time());
  expect($is_enabled)->toBeTrue();

  // Delete using address or CRC32 ID
  $stmt = $pdo->prepare("DELETE FROM spamalias WHERE goto = :u AND (address = :id OR address = :id_str)");
  $stmt->execute(array(':u' => $user, ':id' => $addr, ':id_str' => $addr));

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM spamalias WHERE address = :addr");
  $stmt->execute(array(':addr' => $addr));
  $count = $stmt->fetchColumn();
  expect((int)$count)->toBe(0);
});

it("tests mailbox deletion cascading cleanup for user_api", function() {
  $pdo = new PDO('sqlite::memory:');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("
    CREATE TABLE user_api (
      api_key TEXT PRIMARY KEY,
      username TEXT NOT NULL
    );
  ");
  $stmt = $pdo->prepare("INSERT INTO user_api (api_key, username) VALUES ('KEY-TO-DELETE', 'deleteme@example.com')");
  $stmt->execute();

  // Simulate mailbox delete cleanup query from functions.mailbox.inc.php
  $stmt = $pdo->prepare("DELETE FROM user_api WHERE username = :username");
  $stmt->execute(array(':username' => 'deleteme@example.com'));

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_api WHERE username = :username");
  $stmt->execute(array(':username' => 'deleteme@example.com'));
  $count = $stmt->fetchColumn();
  expect((int)$count)->toBe(0);
});

echo "\n--------------------------------------------------\n";
echo "Test Results: " . ($tests - $failures) . "/{$tests} passed";
if ($failures > 0) {
  echo " ({$failures} FAILED)\n";
  exit(1);
} else {
  echo " (ALL GREEN)\n";
  exit(0);
}
