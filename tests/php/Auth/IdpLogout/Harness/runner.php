<?php

// Runs one logout request of IdpLogoutTest in a process of its own, with the real data/web/inc/functions.inc.php and
// sessions.inc.php: the logout POST of sessions.inc.php, then the IdP logout step of prerequisites.inc.php. The test
// disables curl_*, header(), header_remove() and http_response_code(); here they are recorders, the discovery
// documents are scripted, Redis is an array and the UI log an in-memory SQLite table. The result (headers, status,
// output, discovery requests, Redis calls, log rows, PHP notices) goes to the result file named in the request.

namespace IdpLogoutHarness {

    final class State
    {
        /** @var list<string> the response headers as PHP would send them */
        public static array $headers = [];
        /** @var list<array> header(), header_remove() and http_response_code() calls in their order */
        public static array $headerCalls = [];
        public static int $status = 200;
        /** @var array<string, array{0: int, 1: string}> discovery URL => [HTTP code, body] */
        public static array $documents = [];
        /** @var array<int, array<int, mixed>> curl options per handle */
        public static array $handles = [];
        /** @var list<string> URLs fetched with curl_exec() */
        public static array $fetched = [];
        /** @var list<string> */
        public static array $notices = [];
    }

    /** The Redis calls identity_provider() makes, on an array */
    final class FakeRedis
    {
        /** @var list<array> */
        public array $calls = [];

        /** @param array<string, string> $data */
        public function __construct(public array $data)
        {
        }

        public function get(string $key): string|false
        {
            $this->calls[] = ['get', $key];
            return $this->data[$key] ?? false;
        }

        public function setEx(string $key, int $ttl, string $value): bool
        {
            $this->calls[] = ['setEx', $key, $ttl, $value];
            $this->data[$key] = $value;
            return true;
        }
    }
}

namespace {

    use IdpLogoutHarness\FakeRedis;
    use IdpLogoutHarness\State;

    function curl_init(?string $url = null)
    {
        $handle = count(State::$handles) + 1;
        State::$handles[$handle] = ($url !== null) ? [CURLOPT_URL => $url] : [];
        return $handle;
    }

    function curl_setopt($handle, int $option, mixed $value): bool
    {
        State::$handles[$handle][$option] = $value;
        return true;
    }

    function curl_exec($handle): string|bool
    {
        $url = (string)(State::$handles[$handle][CURLOPT_URL] ?? '');
        State::$fetched[] = $url;
        if (!isset(State::$documents[$url])) {
            return false;
        }
        return State::$documents[$url][1];
    }

    function curl_getinfo($handle, ?int $option = null): mixed
    {
        $url = (string)(State::$handles[$handle][CURLOPT_URL] ?? '');
        return State::$documents[$url][0] ?? 0;
    }

    function curl_close($handle): void
    {
    }

    function header(string $header, bool $replace = true, int $response_code = 0): void
    {
        State::$headerCalls[] = ['header', $header];
        $name = strtolower(trim(strstr($header, ':', true) ?: $header));
        if ($replace) {
            State::$headers = array_values(array_filter(State::$headers,
                fn(string $h): bool => strtolower(trim(strstr($h, ':', true) ?: $h)) !== $name));
        }
        State::$headers[] = $header;
        // like PHP: a Location header turns a 200 into a 302
        if ($name === 'location' && State::$status === 200) {
            State::$status = 302;
        }
    }

    function header_remove(?string $name = null): void
    {
        State::$headerCalls[] = ['header_remove', $name];
        State::$headers = ($name === null) ? [] : array_values(array_filter(State::$headers,
            fn(string $h): bool => strtolower(trim(strstr($h, ':', true) ?: $h)) !== strtolower($name)));
    }

    function http_response_code(int $response_code = 0): int|bool
    {
        State::$headerCalls[] = ['http_response_code', $response_code];
        $previous = State::$status;
        if ($response_code > 0) {
            State::$status = $response_code;
        }
        return $previous;
    }

    // defined in prerequisites.inc.php, used by logger()
    function get_remote_ip($use_x_real_ip = true)
    {
        return '192.0.2.10';
    }

    $request = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $web = realpath(__DIR__ . '/../../../../../data/web');

    $_SERVER['DOCUMENT_ROOT'] = $web;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
    $_SERVER['HTTP_HOST'] = 'mail.example.org';
    $_SERVER['HTTP_USER_AGENT'] = 'IdpLogoutTest';
    $_SERVER['HTTPS'] = 'on';
    foreach ($request['server'] as $key => $value) {
        if ($value === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $value;
        }
    }
    State::$documents = $request['documents'];

    require $web . '/inc/lib/vendor/autoload.php';
    require $web . '/inc/functions.inc.php';

    // notices, warnings and deprecations of the logout code are test failures
    set_error_handler(function (int $errno, string $message, string $file, int $line): bool {
        State::$notices[] = "$message in $file:$line";
        return true;
    });

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->sqliteCreateFunction('UNIX_TIMESTAMP', fn(): int => time(), 0);
    $pdo->exec('CREATE TABLE `logs` (`id` INTEGER PRIMARY KEY, `type` TEXT, `task` TEXT, `msg` TEXT, `call` TEXT,
        `user` TEXT, `role` TEXT, `remote` TEXT, `time` INTEGER)');
    $redis = new FakeRedis($request['redis']);
    $iam_settings = $request['settings'];
    $lang = json_decode(file_get_contents($web . '/lang/lang.en-gb.json'), true);

    $sessionDir = sys_get_temp_dir() . '/idp-logout-sessions-' . getmypid();
    @mkdir($sessionDir, 0700);
    session_save_path($sessionDir);
    ini_set('session.use_cookies', '0');
    ini_set('session.cache_limiter', '');

    $sent_to_idp = false;
    register_shutdown_function(function () use ($request, &$sent_to_idp, $sessionDir): void {
        global $pdo, $redis;
        $output = '';
        while (ob_get_level() > 0) {
            $output = ob_get_clean() . $output;
        }
        $error = error_get_last();
        $fatal = ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true));
        array_map('unlink', glob($sessionDir . '/*') ?: []);
        @rmdir($sessionDir);
        file_put_contents($request['result'], json_encode([
            'sent_to_idp' => $sent_to_idp,
            'status' => State::$status,
            'headers' => State::$headers,
            'header_calls' => State::$headerCalls,
            'body' => $output,
            'fetched' => State::$fetched,
            'redis_calls' => $redis->calls,
            'redis' => $redis->data,
            'logs' => $pdo->query('SELECT `type`, `msg`, `call`, `user` FROM `logs` ORDER BY `id`')->fetchAll(PDO::FETCH_ASSOC),
            'session' => $_SESSION ?? null,
            'notices' => State::$notices,
            'error' => $fatal ? $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] : null,
        ], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    });

    ob_start();
    // the session of the logged-in user, as php-fpm would load it
    session_start();
    $_SESSION = $request['session'];
    $_POST = $request['post'];
    $SESSION_NAME = 'MCSESSID';
    $SESSION_SAMESITE_POLICY = 'Lax';
    $SESSION_LIFETIME = 10800;
    require $web . '/inc/sessions.inc.php';
    // identity_provider('init') of prerequisites.inc.php builds the OAuth client; send-logout does not use it
    $iam_provider = true;
    // the IdP logout step of prerequisites.inc.php (it exits after it)
    if (!empty($iam_logout) && $iam_provider &&
        identity_provider('send-logout', $iam_logout_id_token ?? null, $iam_logout_username ?? null)) {
        $sent_to_idp = true;
    }
}
