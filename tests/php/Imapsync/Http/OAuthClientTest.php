<?php

declare(strict_types=1);

namespace Mailcow\Tests\Imapsync\Http;

use Mailcow\Imapsync\Http\OAuthException;
use Mailcow\Tests\Support\FakeOAuthClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OAuthClientTest extends TestCase
{
    private FakeOAuthClient $client;

    protected function setUp(): void
    {
        $this->client = new FakeOAuthClient();
    }

    public static function malformedUrls(): array
    {
        return [
            'http' => ['http://login.example.org/token'],
            'file' => ['file:///etc/passwd'],
            'gopher' => ['gopher://login.example.org/'],
            'userinfo' => ['https://user:pass@login.example.org/token'],
            'user only' => ['https://user@login.example.org/token'],
            'no host' => ['https:///token'],
            'invalid hostname' => ['https://login_example.org/token'],
            'space' => ['https://login.example.org/a b'],
            'control character' => ["https://login.example.org/\x01"],
            'too long' => ['https://login.example.org/' . str_repeat('a', 480)],
            'empty' => [''],
        ];
    }

    #[DataProvider('malformedUrls')]
    public function testMalformedUrlsAreNeverAllowed(string $url): void
    {
        $this->assertFalse($this->client->isAllowed($url, false));
        $this->assertFalse($this->client->isAllowed($url, true));
    }

    public function testNonStrictModeDoesNotResolve(): void
    {
        $this->assertTrue($this->client->isAllowed('https://login.example.org/token', false));
        $this->assertTrue($this->client->isAllowed('https://localhost.localdomain:8443/token?x=1', false));
        $this->assertSame([], $this->client->resolved);
    }

    public static function blockedAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'this network' => ['0.0.0.0'],
            'private 10/8' => ['10.1.2.3'],
            'private 172.16/12' => ['172.31.255.255'],
            'private 192.168/16' => ['192.168.0.1'],
            'link-local' => ['169.254.169.254'],
            'CGNAT start' => ['100.64.0.0'],
            'CGNAT end' => ['100.127.255.255'],
            'benchmarking start' => ['198.18.0.1'],
            'benchmarking end' => ['198.19.255.255'],
            'multicast' => ['224.0.0.1'],
            'reserved 240/4' => ['240.0.0.1'],
            'broadcast' => ['255.255.255.255'],
            'IPv6 loopback' => ['::1'],
            'IPv6 unspecified' => ['::'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv6 ULA' => ['fd00::1'],
            'IPv6 multicast' => ['ff02::1'],
            'IPv4-mapped' => ['::ffff:127.0.0.1'],
            'NAT64' => ['64:ff9b::a00:1'],
            '6to4' => ['2002:a00:1::1'],
            'Teredo' => ['2001:0:4136:e378::1'],
            'documentation' => ['2001:db8::1'],
            'outside 2000::/3' => ['4000::1'],
        ];
    }

    #[DataProvider('blockedAddresses')]
    public function testStrictModeBlocksNonPublicAddresses(string $ip): void
    {
        $this->client->addresses['login.example.org'] = [$ip];

        $this->assertFalse($this->client->isAllowed('https://login.example.org/token', true));
    }

    public static function publicAddresses(): array
    {
        return [
            'IPv4' => ['8.8.8.8'],
            'just below CGNAT' => ['100.63.255.255'],
            'just above CGNAT' => ['100.128.0.1'],
            'just below benchmarking' => ['198.17.255.255'],
            'just above benchmarking' => ['198.20.0.1'],
            'just below multicast' => ['223.255.255.254'],
            'IPv6 global' => ['2606:4700:4700::1111'],
            'IPv6 global 2a00' => ['2a00:1450:4001::1'],
        ];
    }

    #[DataProvider('publicAddresses')]
    public function testStrictModeAllowsPublicAddresses(string $ip): void
    {
        $this->client->addresses['login.example.org'] = [$ip];

        $this->assertTrue($this->client->isAllowed('https://login.example.org/token', true));
    }

    public function testStrictModeNeedsEveryAddressPublic(): void
    {
        $this->client->addresses['login.example.org'] = ['8.8.8.8', '10.0.0.1'];
        $this->assertFalse($this->client->isAllowed('https://login.example.org/token', true));

        $this->client->addresses['login.example.org'] = [];
        $this->assertFalse($this->client->isAllowed('https://login.example.org/token', true));
    }

    public function testStrictRequestIsPinnedToTheCheckedAddresses(): void
    {
        $this->client->addresses['login.example.org'] = ['8.8.8.8', '8.8.8.8', '2606:4700:4700::1111'];
        $this->client->response = ['{"access_token":"t"}', 200];

        $this->client->request('https://Login.Example.org:8443/token', true, ['grant_type' => 'client_credentials']);

        $this->assertSame(['login.example.org:8443:8.8.8.8,[2606:4700:4700::1111]'], $this->client->sent[0][CURLOPT_RESOLVE]);
    }

    public function testRequestOptionsAllowHttpsOnlyWithoutRedirects(): void
    {
        $this->client->response = ['{"ok":true}', 200];

        $result = $this->client->request('https://login.example.org/token', false, ['a' => 'b c', 'd' => 'e'], ['Authorization: Bearer x']);

        $options = $this->client->sent[0];
        $this->assertSame(['ok' => true], $result);
        $this->assertArrayNotHasKey(CURLOPT_RESOLVE, $options);
        $this->assertSame(CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        $this->assertSame(CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame(1048576, $options[CURLOPT_MAXFILESIZE]);
        $this->assertTrue($options[CURLOPT_POST]);
        $this->assertSame('a=b+c&d=e', $options[CURLOPT_POSTFIELDS]);
        $this->assertSame(['Accept: application/json', 'Authorization: Bearer x'], $options[CURLOPT_HTTPHEADER]);
    }

    public function testGetRequestHasNoBody(): void
    {
        $this->client->request('https://login.example.org/userinfo', false);

        $this->assertArrayNotHasKey(CURLOPT_POST, $this->client->sent[0]);
    }

    public function testDisallowedEndpointIsNeverRequested(): void
    {
        $this->client->addresses['internal.example.org'] = ['10.0.0.1'];

        $this->assertSame('endpoint not allowed', $this->requestError('https://internal.example.org/token', true));
        $this->assertSame([], $this->client->sent);
    }

    public static function failedResponses(): array
    {
        return [
            'connection error' => [[false, 0], 'connection failed'],
            'provider error fields' => [['{"error":"invalid_client","error_description":"Bad secret","trace":"x"}', 401], 'HTTP 401: invalid_client - Bad secret'],
            'html body is never shown' => [['<html><body>internal details</body></html>', 500], 'HTTP 500'],
            'non-scalar error fields' => [['{"error":{"code":1}}', 400], 'HTTP 400'],
            '2xx without JSON object' => [['"just a string"', 200], 'HTTP 200: malformed response'],
            'tags and control characters' => [["{\"error\":\"<script>x</script>\\nbad\"}", 400], 'HTTP 400: x bad'],
        ];
    }

    #[DataProvider('failedResponses')]
    public function testErrorsAreSanitised(array $response, string $error): void
    {
        $this->client->response = $response;

        $this->assertSame($error, $this->requestError('https://login.example.org/token', false));
    }

    public function testErrorTextIsCutTo200Characters(): void
    {
        $this->client->response = [json_encode(['error_description' => str_repeat('x', 500)]), 400];

        $this->assertSame(200, mb_strlen($this->requestError('https://login.example.org/token', false)));
    }

    private function requestError(string $url, bool $strict): string
    {
        try {
            $this->client->request($url, $strict, []);
        } catch (OAuthException $e) {
            return $e->getMessage();
        }
        $this->fail('OAuthException expected');
    }
}
