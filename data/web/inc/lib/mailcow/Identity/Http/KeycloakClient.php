<?php

declare(strict_types=1);

namespace Mailcow\Identity\Http;

use Mailcow\Identity\Directory\DirectoryException;

/**
 * Admin REST API of a Keycloak realm with the client_credentials token of the mailcow client
 * (service account roles: view-users, query-groups). The HTTP call sits in send() so tests can replace it.
 */
class KeycloakClient
{
    private ?string $token = null;

    public function __construct(private string $serverUrl, private string $realm, private string $clientId,
        private string $clientSecret, private bool $ignoreSslError = false)
    {
    }

    /**
     * GET below /admin/realms/<realm>, decoded JSON. A rejected token is renewed once.
     *
     * @throws DirectoryException
     */
    public function get(string $path, array $query = []): array
    {
        $url = $this->serverUrl . '/admin/realms/' . rawurlencode($this->realm) . $path . (empty($query) ? '' : '?' . http_build_query($query));
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->token ??= $this->fetchToken();
            [$code, $body] = $this->send('GET', $url, ['Authorization: Bearer ' . $this->token, 'Accept: application/json'], null);
            if ($code === 401 && $attempt === 0) {
                $this->token = null;
                continue;
            }
            if ($code !== 200) {
                throw new DirectoryException('Keycloak returned HTTP ' . $code . ' for ' . $path);
            }
            $data = json_decode($body, true);
            if (!is_array($data)) {
                throw new DirectoryException('Keycloak returned invalid JSON for ' . $path);
            }
            return $data;
        }
        throw new DirectoryException('Keycloak rejected the access token of the mailcow client');
    }

    /** @throws DirectoryException */
    private function fetchToken(): string
    {
        [$code, $body] = $this->send('POST', $this->serverUrl . '/realms/' . rawurlencode($this->realm) . '/protocol/openid-connect/token',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['grant_type' => 'client_credentials', 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret]));
        $data = json_decode($body, true);
        if ($code !== 200 || !is_array($data) || empty($data['access_token'])) {
            throw new DirectoryException('Keycloak token request failed (HTTP ' . $code . ')');
        }
        return (string)$data['access_token'];
    }

    /**
     * @return array{0: int, 1: string} HTTP status and body
     * @throws DirectoryException on transport errors
     */
    protected function send(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, (string)$body);
        }
        if ($this->ignoreSslError) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        }
        $response = curl_exec($curl);
        $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($response === false) {
            throw new DirectoryException('Keycloak request failed: ' . $error);
        }
        return [$code, (string)$response];
    }
}
