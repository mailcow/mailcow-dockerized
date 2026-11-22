<?php

declare(strict_types=1);

namespace Mailcow\Tests\Auth\IdpLogout;

use DOMDocument;
use DOMElement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The logout of an identity provider session end to end on the PHP side: the logout POST through the real
 * sessions.inc.php, then identity_provider('send-logout') as prerequisites.inc.php calls it. Each request runs in a
 * process of its own (Harness/runner.php), as functions.inc.php declares global functions and the test replaces
 * curl_*, header(), header_remove() and http_response_code() with recorders.
 */
class IdpLogoutTest extends TestCase
{
    private const USER = 'user@example.org';
    private const KEYCLOAK = [
        'authsource' => 'keycloak', 'server_url' => 'https://kc.example.org', 'realm' => 'mailcow', 'version' => '26.4.0',
        'client_id' => 'mailcow', 'redirect_url' => 'https://mail.example.org/', 'idp_logout' => true,
    ];
    private const KEYCLOAK_LOGOUT = 'https://kc.example.org/realms/mailcow/protocol/openid-connect/logout';
    private const GENERIC = [
        'authsource' => 'generic-oidc', 'authorize_url' => 'https://idp.example.org/authorize',
        'token_url' => 'https://idp.example.org/token', 'userinfo_url' => 'https://idp.example.org/userinfo',
        'client_id' => 'mailcow', 'redirect_url' => 'https://mail.example.org/', 'idp_logout' => true,
    ];
    private const ISSUER = 'https://idp.example.org';
    private const DISCOVERY = 'https://idp.example.org/.well-known/openid-configuration';
    /** Reason of the warning for a session without ID token; %s: label of the idp_logout setting */
    private const NO_ID_TOKEN = 'no ID token in the session (a session from before the update, or the provider returns none: '
        . 'add the openid scope or turn off "%s"), logged at most once an hour';
    /** The form the logout button of the mailcow UI posts */
    private const UI_LOGOUT = ['logout' => '1', 'csrf_token' => 'csrf-token-of-the-session'];

    // generic OIDC without an ID token in the session (logged in before the update): no discovery, a log entry

    /** @return array<string, array{0: ?string, 1: string}> ID token, reason in the log */
    public static function unusableTokens(): array
    {
        return [
            'no token' => [null, self::NO_ID_TOKEN],
            'empty token' => ['', self::NO_ID_TOKEN],
            'not a JWT' => ['opaque-token', 'ID token of the session has no readable issuer'],
            'no issuer claim' => [self::idToken(['sub' => 'x']), 'ID token of the session has no readable issuer'],
        ];
    }

    #[DataProvider('unusableTokens')]
    public function testGenericOidcWithoutUsableIdTokenLogsOutLocallyWithoutDiscovery(?string $token, string $reason): void
    {
        $result = $this->logout(self::GENERIC, self::session($token), self::UI_LOGOUT);

        $this->assertFalse($result['sent_to_idp']);
        $this->assertSame([], $result['fetched'], 'no discovery request');
        $this->assertSame([], array_filter($result['redis_calls'], fn(array $c): bool => str_starts_with($c[1], 'IAM_END_SESSION_ENDPOINT_')),
            'no discovery cache lookup');
        $this->assertSame(['Location: /'], $result['headers'], 'the local logout redirect of sessions.inc.php');
        $this->assertSame(302, $result['status']);
        $this->assertSame('', $result['body']);
        $this->assertSame([], $result['session'], 'the local session is gone');
        $this->assertSame([self::localOnlyLog(sprintf($reason, self::idpLogoutLabel()))], $result['logs']);
    }

    // a provider that returns no ID token would warn on every logout: at most once an hour per provider, the logout that
    // sets the marker with one atomic SET NX logs (two logouts at once cannot both log)

    public function testMissingIdTokenWarningSetsTheMarkerAtomically(): void
    {
        $result = $this->logout(self::GENERIC, self::session(null), self::UI_LOGOUT);

        $this->assertSame([['set', self::noIdTokenKey(), '1', ['nx', 'ex' => 3600]]], $result['redis_calls'], 'one SET NX EX, no read before');
        $this->assertSame([self::noIdTokenKey() => '1'], $result['redis']);
        $this->assertSame([self::noIdTokenKey() => 3600], $result['redis_ttl']);
        $this->assertCount(1, $result['logs']);
    }

    public function testMissingIdTokenWarningIsNotRepeatedWhileTheMarkerExists(): void
    {
        $result = $this->logout(self::GENERIC, self::session(null), self::UI_LOGOUT, redis: [self::noIdTokenKey() => 'set by another logout']);

        $this->assertFalse($result['sent_to_idp']);
        $this->assertSame([], $result['fetched']);
        $this->assertSame(['Location: /'], $result['headers']);
        $this->assertSame([], $result['logs'], 'SET NX failed: another logout logged');
        $this->assertSame([['set', self::noIdTokenKey(), '1', ['nx', 'ex' => 3600]]], $result['redis_calls']);
        $this->assertSame([self::noIdTokenKey() => 'set by another logout'], $result['redis'], 'the marker is not overwritten');
        $this->assertSame([], $result['redis_ttl'], 'nor its expiry extended');
    }

    public function testMissingIdTokenWarningOfAnotherProviderIsLogged(): void
    {
        $result = $this->logout(['client_id' => 'other-client'] + self::GENERIC, self::session(null), self::UI_LOGOUT,
            redis: [self::noIdTokenKey() => '1']);

        $this->assertCount(1, $result['logs']);
    }

    public function testMissingIdTokenWarningIsLoggedWhenRedisIsDown(): void
    {
        $result = $this->logout(self::GENERIC, self::session(null), self::UI_LOGOUT, redisDown: true);

        $this->assertFalse($result['sent_to_idp']);
        $this->assertSame(['Location: /'], $result['headers']);
        $this->assertSame([self::localOnlyLog(sprintf(self::NO_ID_TOKEN, self::idpLogoutLabel()))], $result['logs']);
    }

    // a discovered end_session_endpoint off the configured provider hosts is never used, fresh or from the cache

    public function testForeignEndSessionEndpointFromDiscoveryIsNotUsed(): void
    {
        $token = self::idToken(['iss' => self::ISSUER]);
        $result = $this->logout(self::GENERIC, self::session($token), self::UI_LOGOUT, documents: [
            self::DISCOVERY => [200, json_encode(['issuer' => self::ISSUER, 'end_session_endpoint' => 'https://evil.example.net/logout'])],
        ]);

        $this->assertFalse($result['sent_to_idp']);
        $this->assertSame([self::DISCOVERY], $result['fetched']);
        $this->assertSame(['Location: /'], $result['headers']);
        $this->assertStringNotContainsString('evil.example.net', $result['body']);
        $this->assertSame([['setEx', self::cacheKey(), 3600, 'https://evil.example.net/logout']],
            array_values(array_filter($result['redis_calls'], fn(array $c): bool => $c[0] === 'setEx')));
        $this->assertCount(1, $result['logs']);
        $this->assertSame(json_encode('IdP logout not possible for ' . self::USER
            . ', local logout only: end_session_endpoint is not on the host of the configured provider URLs'), $result['logs'][0]['msg']);
        $this->assertSame(json_encode(['identity_provider', 'get-end-session-endpoint', self::USER, self::ISSUER]), $result['logs'][0]['call']);
        $this->assertStringNotContainsString($token, json_encode($result['logs']), 'no token in the log');
    }

    /** @return array<string, array{0: string}> cached endpoint */
    public static function foreignEndpoints(): array
    {
        return [
            'other host' => ['https://evil.example.net/logout'],
            'other scheme' => ['http://idp.example.org/logout'],
            'other port' => ['https://idp.example.org:8443/logout'],
            'subdomain of the provider' => ['https://evil.idp.example.org/logout'],
            'no host' => ['/logout'],
        ];
    }

    #[DataProvider('foreignEndpoints')]
    public function testForeignEndSessionEndpointFromTheCacheIsNotUsed(string $endpoint): void
    {
        $result = $this->logout(self::GENERIC, self::session(self::idToken(['iss' => self::ISSUER])), self::UI_LOGOUT,
            redis: [self::cacheKey() => $endpoint]);

        $this->assertFalse($result['sent_to_idp']);
        $this->assertSame([], $result['fetched'], 'cached: no discovery request');
        $this->assertSame(['Location: /'], $result['headers']);
        $this->assertSame('', $result['body']);
    }

    public function testCachedEndSessionEndpointOfTheProviderIsUsedWithoutDiscovery(): void
    {
        $token = self::idToken(['iss' => self::ISSUER]);
        $result = $this->logout(self::GENERIC, self::session($token), self::UI_LOGOUT,
            redis: [self::cacheKey() => 'https://idp.example.org/logout']);

        $this->assertTrue($result['sent_to_idp']);
        $this->assertSame([], $result['fetched']);
        $this->assertSame(['Location: https://idp.example.org/logout?' . http_build_query([
            'client_id' => 'mailcow', 'post_logout_redirect_uri' => 'https://mail.example.org/', 'id_token_hint' => $token,
        ])], $result['headers']);
        $this->assertSame([], $result['logs']);
    }

    // transport: a redirect, or for a large ID token an auto-submitting form POST that replaces the local redirect

    public function testShortLogoutIsARedirectToTheIdp(): void
    {
        $token = self::idToken(['iss' => 'https://kc.example.org/realms/mailcow']);
        $result = $this->logout(self::KEYCLOAK, self::session($token), self::UI_LOGOUT);

        $this->assertTrue($result['sent_to_idp']);
        $this->assertSame(['Location: ' . self::KEYCLOAK_LOGOUT . '?' . http_build_query([
            'client_id' => 'mailcow', 'post_logout_redirect_uri' => 'https://mail.example.org/', 'id_token_hint' => $token,
        ])], $result['headers']);
        $this->assertSame(302, $result['status']);
        $this->assertSame('', $result['body']);
    }

    public function testLargeIdTokenIsPostedAndTheLocalRedirectIsDropped(): void
    {
        $token = self::idToken(['iss' => 'https://kc.example.org/realms/mailcow', 'pad' => str_repeat('a', 3000)]);
        $result = $this->logout(self::KEYCLOAK, self::session($token), self::UI_LOGOUT);

        $this->assertTrue($result['sent_to_idp']);
        $this->assertSame(['header', 'Location: /'], $result['header_calls'][0], 'sessions.inc.php redirected first');
        $this->assertSame(['Content-Type: text/html; charset=utf-8', 'Cache-Control: no-store'], $result['headers']);
        $this->assertSame(200, $result['status']);
        $form = $this->form($result['body']);
        $this->assertSame('post', $form->getAttribute('method'));
        $this->assertSame(self::KEYCLOAK_LOGOUT, $form->getAttribute('action'));
        $this->assertSame(['client_id' => 'mailcow', 'post_logout_redirect_uri' => 'https://mail.example.org/', 'id_token_hint' => $token],
            $this->fields($form));
        $this->assertSame('document.forms[0].submit()', $form->ownerDocument->getElementsByTagName('body')->item(0)->getAttribute('onload'));
        $this->assertSame('Continue', $form->getElementsByTagName('button')->item(0)->textContent, 'button without JavaScript');
        $this->assertSame('Signing out', $form->ownerDocument->getElementsByTagName('title')->item(0)->textContent);
        $this->assertSame([], $result['logs']);
    }

    public function testLargeIdTokenFormKeepsTheQueryOfAGenericEndpoint(): void
    {
        $token = self::idToken(['iss' => self::ISSUER, 'pad' => str_repeat('a', 3000)]);
        $endpoint = 'https://idp.example.org/logout?tenant=a&x="1"';
        $result = $this->logout(self::GENERIC, self::session($token), self::UI_LOGOUT, documents: [
            self::DISCOVERY => [200, json_encode(['issuer' => self::ISSUER, 'end_session_endpoint' => $endpoint])],
        ]);

        $this->assertTrue($result['sent_to_idp']);
        $this->assertSame(['Content-Type: text/html; charset=utf-8', 'Cache-Control: no-store'], $result['headers']);
        $form = $this->form($result['body']);
        $this->assertSame($endpoint, $form->getAttribute('action'));
        $this->assertSame(['client_id' => 'mailcow', 'post_logout_redirect_uri' => 'https://mail.example.org/', 'id_token_hint' => $token],
            $this->fields($form));
    }

    public function testLargeIdTokenForKeycloak18IsAShorterRedirect(): void
    {
        $token = self::idToken(['iss' => 'https://kc.example.org/realms/mailcow', 'pad' => str_repeat('a', 3000)]);
        $result = $this->logout(['version' => '18.0.2'] + self::KEYCLOAK, self::session($token), self::UI_LOGOUT);

        $this->assertTrue($result['sent_to_idp']);
        $this->assertSame(['Location: ' . self::KEYCLOAK_LOGOUT . '?client_id=mailcow'], $result['headers']);
        $this->assertSame('', $result['body']);
        $this->assertCount(1, $result['logs']);
        $this->assertStringContainsString('too large for a logout redirect', $result['logs'][0]['msg']);
    }

    // the SOGo logout button: mc_logout() of data/conf/sogo/custom-sogo.js posts logout=1 without the CSRF token

    // a structural regression check of mc_logout(); that the browser follows the redirect is checked by the local browser tests
    public function testSogoLogoutButtonBuildsATopLevelLogoutForm(): void
    {
        // without comments, without whitespace around punctuation and with one quote style: only a change of behaviour fails
        $js = (string)file_get_contents(__DIR__ . '/../../../../data/conf/sogo/custom-sogo.js');
        $js = (string)preg_replace(['#/\*.*?\*/#s', '#(^|\s)//[^\n]*#', '/\s*([=(){};.,])\s*/'], ['', '$1', '$1'], $js);
        $js = str_replace("'", '"', $js);
        $start = strpos($js, 'function mc_logout(){');
        $this->assertNotFalse($start, 'custom-sogo.js declares mc_logout()');
        for ($i = $start + strlen('function mc_logout()'), $depth = 0; $i < strlen($js); $i++) {
            $depth += ['{' => 1, '}' => -1][$js[$i]] ?? 0;
            if ($depth === 0) {
                break;
            }
        }
        $body = substr($js, $start, $i - $start + 1);

        $this->assertSame(1, preg_match('/(?:var|let|const)\s+(\w+)=document\.createElement\("form"\)/', $body, $form), 'a form');
        $this->assertSame(1, preg_match('/(?:var|let|const)\s+(\w+)=document\.createElement\("input"\)/', $body, $input), 'an input');
        [$form, $input] = [$form[1], $input[1]];
        $this->assertSame('POST', strtoupper((string)self::jsProperty($body, $form, 'method')));
        $this->assertSame('/', self::jsProperty($body, $form, 'action'));
        $this->assertSame('logout', self::jsProperty($body, $input, 'name'));
        $this->assertSame('1', self::jsProperty($body, $input, 'value'));
        $this->assertStringContainsString($form . '.appendChild(' . $input . ')', $body);
        $this->assertSame(1, preg_match('/document\.(?:body|documentElement)\.(?:appendChild|append)\(' . $form . '\)/', $body),
            'the form is attached to the document (a detached form is not submitted)');
        $this->assertSame(0, preg_match('/\.target=|setAttribute\("target"|\btarget:/i', $body), 'no target: the logout replaces the page');
        $this->assertSame(1, preg_match('/' . $form . '\.(?:requestSubmit|submit)\(\)/', $body), 'the form is submitted');
        foreach (['fetch(', 'XMLHttpRequest', '.ajax(', 'sendBeacon('] as $background) {
            $this->assertStringNotContainsString($background, $body, 'a top-level navigation, so the browser follows the IdP redirect');
        }
    }

    /** @return array<string, array{0: array<string, ?string>}> request headers of a same-origin logout */
    public static function sameOriginRequests(): array
    {
        return [
            'Sec-Fetch-Site same-origin' => [['HTTP_SEC_FETCH_SITE' => 'same-origin']],
            'Origin of the host (no Sec-Fetch-Site)' => [['HTTP_ORIGIN' => 'https://mail.example.org']],
        ];
    }

    #[DataProvider('sameOriginRequests')]
    public function testSogoLogoutReachesTheIdp(array $server): void
    {
        $token = self::idToken(['iss' => 'https://kc.example.org/realms/mailcow']);
        $result = $this->logout(self::KEYCLOAK, self::session($token), ['logout' => '1'], $server);

        $this->assertTrue($result['sent_to_idp']);
        $this->assertCount(1, $result['headers']);
        $this->assertStringStartsWith('Location: ' . self::KEYCLOAK_LOGOUT . '?client_id=mailcow&', $result['headers'][0]);
        $this->assertStringContainsString('&id_token_hint=' . $token, $result['headers'][0]);
        $this->assertSame([], $result['session']);
    }

    /** @return array<string, array{0: array<string, ?string>}> request headers of a cross-site logout */
    public static function crossSiteRequests(): array
    {
        return [
            'Sec-Fetch-Site cross-site' => [['HTTP_SEC_FETCH_SITE' => 'cross-site']],
            'Sec-Fetch-Site same-site' => [['HTTP_SEC_FETCH_SITE' => 'same-site']],
            'foreign Origin' => [['HTTP_ORIGIN' => 'https://evil.example.net']],
            'Origin over http' => [['HTTP_ORIGIN' => 'http://mail.example.org']],
        ];
    }

    #[DataProvider('crossSiteRequests')]
    public function testCrossSiteLogoutWithoutTokenIsRefused(array $server): void
    {
        $session = self::session(self::idToken(['iss' => 'https://kc.example.org/realms/mailcow']));
        $result = $this->logout(self::KEYCLOAK, $session, ['logout' => '1'], $server);

        $this->assertFalse($result['sent_to_idp']);
        $this->assertSame([], $result['headers']);
        $this->assertSame(self::USER, $result['session']['mailcow_cc_username'] ?? null, 'still logged in');
        $this->assertSame('session_token', $result['session']['return'][0]['msg'] ?? null);
    }

    // helpers

    /**
     * @param array<string, mixed> $settings iam_settings
     * @param array<string, mixed> $session the session before the request
     * @param array<string, string> $post
     * @param array<string, ?string> $server $_SERVER entries, null removes one
     * @param array<string, array{0: int, 1: string}> $documents discovery URL => [HTTP code, body]
     * @param array<string, string> $redis
     * @param bool $redisDown every Redis call throws RedisException
     */
    private function logout(array $settings, array $session, array $post, array $server = [], array $documents = [], array $redis = [],
        bool $redisDown = false): array
    {
        $requestFile = tempnam(sys_get_temp_dir(), 'idp-logout-req-');
        $resultFile = tempnam(sys_get_temp_dir(), 'idp-logout-res-');
        try {
            file_put_contents($requestFile, json_encode(['settings' => $settings, 'session' => $session, 'post' => $post,
                'server' => $server, 'documents' => (object)$documents, 'redis' => (object)$redis, 'redis_down' => $redisDown,
                'result' => $resultFile],
                JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr',
                '-d', 'disable_functions=curl_init,curl_setopt,curl_exec,curl_getinfo,curl_close,header,header_remove,http_response_code',
                __DIR__ . '/Harness/runner.php', $requestFile],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $result = json_decode((string)file_get_contents($resultFile), true);
        } finally {
            @unlink($requestFile);
            @unlink($resultFile);
        }
        $this->assertIsArray($result, "the harness wrote no result:\n$output");
        $this->assertNull($result['error'], 'the logout failed');
        $this->assertSame([], $result['notices'], 'PHP notices');
        return $result;
    }

    /** The session of a user who logged in through the identity provider */
    private static function session(?string $idToken): array
    {
        return [
            'mailcow_cc_username' => self::USER, 'mailcow_cc_role' => 'user', 'SESS_REMOTE_UA' => 'IdpLogoutTest',
            'CSRF' => ['TOKEN' => 'csrf-token-of-the-session'],
            'iam_token' => 'access-token', 'iam_refresh_token' => 'refresh-token', 'iam_id_token' => $idToken,
        ];
    }

    /** An unsigned JWT with $claims (mailcow reads the issuer only, the IdP checks the signature) */
    private static function idToken(array $claims): string
    {
        $encode = fn(string $part): string => rtrim(strtr(base64_encode($part), '+/', '-_'), '=');
        return $encode('{"alg":"RS256","typ":"JWT"}') . '.' . $encode(json_encode($claims)) . '.signature';
    }

    /** A local-only logout warning of get-end-session-endpoint as logger() writes it */
    private static function localOnlyLog(string $reason): array
    {
        return [
            'type' => 'warning',
            'msg' => json_encode('IdP logout not possible for ' . self::USER . ', local logout only: ' . $reason,
                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'call' => json_encode(['identity_provider', 'get-end-session-endpoint', self::USER]),
            'user' => 'unauthenticated',
        ];
    }

    /** The label of the idp_logout setting in the admin UI */
    private static function idpLogoutLabel(): string
    {
        $lang = json_decode((string)file_get_contents(__DIR__ . '/../../../../data/web/lang/lang.en-gb.json'), true);
        return $lang['admin']['iam_idp_logout'];
    }

    /** Redis key that remembers the missing-ID-token warning of the GENERIC provider */
    private static function noIdTokenKey(): string
    {
        return 'IAM_NO_ID_TOKEN_LOGGED_' . md5(json_encode([self::GENERIC['token_url'], self::GENERIC['client_id']]));
    }

    /** The string $object.$property = "..." (or setAttribute) sets in normalised $js */
    private static function jsProperty(string $js, string $object, string $property): ?string
    {
        $pattern = '/' . $object . '(?:\.' . $property . '="([^"]*)"|\.setAttribute\("' . $property . '","([^"]*)"\))/';
        return preg_match($pattern, $js, $match) ? ($match[1] !== '' ? $match[1] : ($match[2] ?? '')) : null;
    }

    private static function cacheKey(): string
    {
        return 'IAM_END_SESSION_ENDPOINT_' . md5(self::ISSUER);
    }

    private function form(string $html): DOMElement
    {
        $this->assertStringStartsWith('<!DOCTYPE html><html lang="en-gb">', $html);
        $document = new DOMDocument();
        $this->assertTrue($document->loadHTML($html, LIBXML_NOERROR));
        $forms = $document->getElementsByTagName('form');
        $this->assertSame(1, $forms->length);
        return $forms->item(0);
    }

    /** @return array<string, string> the hidden fields of $form, in their order */
    private function fields(DOMElement $form): array
    {
        $fields = [];
        foreach ($form->getElementsByTagName('input') as $input) {
            $this->assertSame('hidden', $input->getAttribute('type'));
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        return $fields;
    }
}
