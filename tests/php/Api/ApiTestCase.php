<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api;

use PHPUnit\Framework\TestCase;

/**
 * Runs requests through the real data/web/json_api.php in a process of its own (Harness/runner.php). The legacy
 * functions (mailbox(), bcc(), ...) and the module classes it calls are recorders: a test checks the HTTP behaviour of
 * a route and which backend call it makes with which arguments, not the backend itself.
 *
 *   $result = $this->post('add/alias', ['address' => 'a@example.org', 'goto' => 'b@example.org']);
 *   $this->assertCalled($result, 'mailbox', ['add', 'alias', ['address' => 'a@example.org', 'goto' => 'b@example.org']]);
 *
 * Backend answers are scripted with self::answer(); a call without one returns null (module chains like
 * Module::fromGlobals()->api->x() resolve to recorders).
 */
abstract class ApiTestCase extends TestCase
{
    /** Session of a read/write API key (sessions.inc.php logs it in as the admin "API") */
    protected const API_RW = ['mailcow_cc_username' => 'API', 'mailcow_cc_role' => 'admin', 'mailcow_cc_api' => true, 'mailcow_cc_api_access' => 'rw'];
    /** Session of a read-only API key */
    protected const API_RO = ['mailcow_cc_username' => 'API', 'mailcow_cc_role' => 'admin', 'mailcow_cc_api' => true, 'mailcow_cc_api_access' => 'ro'];
    /** UI sessions */
    protected const ADMIN = ['mailcow_cc_username' => 'admin', 'mailcow_cc_role' => 'admin'];
    protected const DOMAIN_ADMIN = ['mailcow_cc_username' => 'domainadmin', 'mailcow_cc_role' => 'domainadmin'];
    protected const USER = ['mailcow_cc_username' => 'user@example.org', 'mailcow_cc_role' => 'user'];
    protected const NO_SESSION = [];

    private const JSON_API = __DIR__ . '/../../../data/web/json_api.php';
    /** PHP functions with outside effects that json_api.php calls directly: disabled, so the harness records them */
    private const DISABLED = ['curl_init', 'curl_setopt', 'curl_setopt_array', 'curl_exec', 'curl_close', 'curl_error', 'curl_errno', 'curl_getinfo'];

    /**
     * One scripted backend answer: the first call of $call whose arguments start with $args returns $return and
     * appends $messages to $_SESSION['return'] (what the legacy functions do), $once makes it answer one call only.
     */
    protected static function answer(string $call, array $args = [], mixed $return = null, array $messages = [], bool $once = false): array
    {
        return ['call' => $call, 'args' => $args, 'return' => $return, 'messages' => $messages, 'once' => $once];
    }

    /** A mailcow message as the legacy functions put it into $_SESSION['return'] */
    protected static function message(string $type, string|array $msg, array $log = []): array
    {
        return ['type' => $type, 'log' => $log, 'msg' => $msg];
    }

    /**
     * @param array{session?: array, script?: list<array>, server?: array, query?: array} $options
     */
    protected function get(string $route, array $options = []): ApiResult
    {
        return $this->request('GET', $route, $options);
    }

    /**
     * POST with a JSON body (any value; a string is sent as it is), as API clients send it
     *
     * @param array{session?: array, script?: list<array>, server?: array, query?: array, content_type?: string} $options
     */
    protected function post(string $route, mixed $json = null, array $options = []): ApiResult
    {
        if ($json !== null) {
            $options['body'] = is_string($json) ? $json : json_encode($json);
            $options['content_type'] ??= 'application/json';
        }
        return $this->request('POST', $route, $options);
    }

    /**
     * POST with form fields (attr, items as JSON strings), as the mailcow UI sends it
     *
     * @param array<string, string> $fields
     */
    protected function postForm(string $route, array $fields, array $options = []): ApiResult
    {
        $options['post'] = $fields;
        $options['content_type'] ??= 'application/x-www-form-urlencoded';
        return $this->request('POST', $route, $options);
    }

    /**
     * @param string $route the part after /api/v1/, e.g. "get/domain/all"
     * @param array{session?: array, script?: list<array>, server?: array, query?: array, post?: array, body?: string,
     *     content_type?: string, globals?: array, allow_error?: bool} $options globals: variables prerequisites.inc.php
     *     would set (e.g. iam_settings)
     */
    protected function request(string $method, string $route, array $options = []): ApiResult
    {
        $dir = sys_get_temp_dir();
        $requestFile = tempnam($dir, 'api-req-');
        $resultFile = tempnam($dir, 'api-res-');
        try {
            file_put_contents($requestFile, json_encode([
                'json_api' => realpath(self::JSON_API),
                'method' => $method,
                'query' => ltrim(preg_replace('#^/?api/v1/#', '', $route), '/'),
                'get' => $options['query'] ?? [],
                'post' => $options['post'] ?? [],
                'body' => $options['body'] ?? null,
                'content_type' => $options['content_type'] ?? null,
                'server' => $options['server'] ?? [],
                'session' => $options['session'] ?? self::API_RW,
                'script' => $options['script'] ?? [],
                'globals' => $options['globals'] ?? [],
                'result' => $resultFile,
            ], JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'disable_functions=' . implode(',', self::DISABLED),
                __DIR__ . '/Harness/runner.php', $requestFile],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $data = json_decode((string)file_get_contents($resultFile), true);
            $this->assertIsArray($data, "the harness wrote no result for $method $route:\n$stdout$stderr");
        } finally {
            @unlink($requestFile);
            @unlink($resultFile);
        }
        $result = new ApiResult((int)$data['status'], (string)$data['body'], $data['calls'], $data['session_return'], $data['error']);
        if (empty($options['allow_error'])) {
            $this->assertNull($result->error, "json_api.php failed for $method $route");
        }
        return $result;
    }

    /** At least one call of $name had exactly $args */
    protected function assertCalled(ApiResult $result, string $name, array $args): void
    {
        $this->assertContains($args, $result->callsTo($name),
            "no call $name(" . json_encode($args) . "), calls were:\n" . json_encode($result->calls, JSON_PRETTY_PRINT));
    }

    /** $name was not called at all */
    protected function assertNotCalled(ApiResult $result, string $name): void
    {
        $this->assertSame([], $result->callsTo($name), "unexpected call of $name");
    }

    /** The JSON answer json_api.php gives without calling a backend, e.g. 404 route not found */
    protected function assertApiError(ApiResult $result, int $status, string $msg): void
    {
        $this->assertSame([$status, ['type' => 'error', 'msg' => $msg]], [$result->status, $result->json()], $result->body);
    }
}
