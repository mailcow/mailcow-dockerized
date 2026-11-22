<?php

declare(strict_types=1);

namespace Mailcow\Auth\Model;

/**
 * Logout request to the end session endpoint of an IdP (OpenID Connect RP-Initiated Logout 1.0) and how the
 * browser sends it. A URL up to MAX_REDIRECT_URL bytes is a redirect (GET). A longer one (large ID token) does not
 * fit in the FastCGI header buffer of nginx and is sent as a form POST, which the specification also allows.
 * Keycloak before 19 handles a logout POST as a refresh token logout, so there the long request is sent as GET
 * without id_token_hint instead.
 */
class IdpLogoutRequest
{
    public const MAX_REDIRECT_URL = 2048;

    /** @param ?array<string, string> $getParams parameters for a long URL when POST is not supported */
    public function __construct(
        public readonly string $endpoint,
        public readonly array $params,
        public readonly ?array $getParams = null
    ) {
    }

    /**
     * Major version of the free-text Keycloak version setting ("18", "18.0.2", "18.0.0.redhat-00001"), null when it
     * does not start with a number ("", "latest"): that counts as current
     */
    public static function keycloakMajor(string $version): ?int
    {
        return preg_match('/^\s*v?(\d+)/i', $version, $match) ? (int)$match[1] : null;
    }

    /** Keycloak: the endpoint and parameters of its version */
    public static function keycloak(string $serverUrl, string $realm, string $version, string $clientId, string $redirectUri, ?string $idToken): self
    {
        $endpoint = $serverUrl . '/realms/' . $realm . '/protocol/openid-connect/logout';
        $major = self::keycloakMajor($version);
        $legacy = $major !== null && $major < 19;
        if ($major !== null && $major < 18) {
            // Keycloak 17 and older: redirect_uri, as the Keycloak provider library sends it (17 also reads
            // post_logout_redirect_uri); without id_token_hint it is checked against the redirect URIs of the realm
            $params = ['client_id' => $clientId, 'redirect_uri' => $redirectUri];
            $short = $params;
        } else {
            // Keycloak 18 accepts post_logout_redirect_uri only with id_token_hint; without both it asks the user
            // to confirm and does not redirect back. From 19, client_id is enough for post_logout_redirect_uri.
            $params = ['client_id' => $clientId, 'post_logout_redirect_uri' => $redirectUri];
            $short = ['client_id' => $clientId];
        }
        if ($idToken !== null && $idToken !== '') {
            $params['id_token_hint'] = $idToken;
        } elseif ($major === 18) {
            // no ID token (e.g. a session from before the update)
            $params = $short;
        }
        return new self($endpoint, $params, $legacy ? $short : null);
    }

    /** Generic OIDC: the end session endpoint from discovery, which must support GET and POST */
    public static function generic(string $endpoint, string $clientId, string $redirectUri, ?string $idToken): self
    {
        $params = ['client_id' => $clientId, 'post_logout_redirect_uri' => $redirectUri];
        if ($idToken !== null && $idToken !== '') {
            $params['id_token_hint'] = $idToken;
        }
        return new self($endpoint, $params);
    }

    /** Issuer (iss) of an ID token, read without verification, '' for no or an unreadable token */
    public static function issuer(?string $idToken): string
    {
        $parts = explode('.', (string)$idToken);
        if (count($parts) !== 3) {
            return '';
        }
        $claims = json_decode((string)base64_decode(strtr($parts[1], '-_', '+/')), true);
        return (is_array($claims) && is_string($claims['iss'] ?? null)) ? $claims['iss'] : '';
    }

    /**
     * Warning for the mailcow UI log when the IdP session is not ended (no tokens in it): the session is already
     * gone, so the user is named. log is [identity_provider, <calling action>, user(, issuer)].
     *
     * @return array{type: 'warning', log: list<string>, msg: string}
     */
    public static function localOnlyLogEntry(string $origin, ?string $username, string $issuer, string $reason): array
    {
        $log = ['identity_provider', $origin, (string)$username];
        if ($issuer !== '') {
            $log[] = $issuer;
        }
        return [
            'type' => 'warning',
            'log' => $log,
            'msg' => 'IdP logout not possible for ' . ($username ?: 'unknown user') . ', local logout only: ' . $reason,
        ];
    }

    public function url(?array $params = null): string
    {
        return $this->endpoint . (str_contains($this->endpoint, '?') ? '&' : '?') . http_build_query($params ?? $this->params);
    }

    /**
     * How the browser sends the request: a redirect to url (GET), or a form POST of params to action.
     * reduced: the GET lacks id_token_hint because the provider cannot take the long request as POST.
     *
     * @return array{method: 'GET', action: string, params: array<string, string>, url: string, reduced: bool}
     *   |array{method: 'POST', action: string, params: array<string, string>, reduced: false}
     */
    public function transport(): array
    {
        $url = $this->url();
        if (strlen($url) <= self::MAX_REDIRECT_URL) {
            return ['method' => 'GET', 'action' => $this->endpoint, 'params' => $this->params, 'url' => $url, 'reduced' => false];
        }
        if ($this->getParams === null) {
            return ['method' => 'POST', 'action' => $this->endpoint, 'params' => $this->params, 'reduced' => false];
        }
        return ['method' => 'GET', 'action' => $this->endpoint, 'params' => $this->getParams, 'url' => $this->url($this->getParams), 'reduced' => true];
    }
}
