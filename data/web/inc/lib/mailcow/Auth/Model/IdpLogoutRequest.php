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

    /** Keycloak: the endpoint and parameters of its version, an empty version counts as current */
    public static function keycloak(string $serverUrl, string $realm, string $version, string $clientId, string $redirectUri, ?string $idToken): self
    {
        $endpoint = $serverUrl . '/realms/' . $realm . '/protocol/openid-connect/logout';
        $legacy = $version !== '' && version_compare($version, '19.0.0', '<');
        if ($version !== '' && version_compare($version, '18.0.0', '<')) {
            // Keycloak 17 and older: redirect_uri, as the Keycloak provider library sends it (17 also reads
            // post_logout_redirect_uri); without id_token_hint it is checked against the redirect URIs of the realm
            $params = ['client_id' => $clientId, 'redirect_uri' => $redirectUri];
            $short = $params;
        } else {
            // Keycloak 18 accepts post_logout_redirect_uri only with id_token_hint; without both it asks the user
            // to confirm and does not redirect back
            $params = ['client_id' => $clientId, 'post_logout_redirect_uri' => $redirectUri];
            $short = ['client_id' => $clientId];
        }
        if ($idToken !== null && $idToken !== '') {
            $params['id_token_hint'] = $idToken;
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

    public function url(?array $params = null): string
    {
        return $this->endpoint . (str_contains($this->endpoint, '?') ? '&' : '?') . http_build_query($params ?? $this->params);
    }

    /**
     * @return array{method: 'GET'|'POST', action: string, params: array<string, string>, reduced: bool}
     *   reduced: the request lacks id_token_hint because the provider cannot take it as POST
     */
    public function transport(): array
    {
        if (strlen($this->url()) <= self::MAX_REDIRECT_URL) {
            return ['method' => 'GET', 'action' => $this->endpoint, 'params' => $this->params, 'reduced' => false];
        }
        if ($this->getParams === null) {
            return ['method' => 'POST', 'action' => $this->endpoint, 'params' => $this->params, 'reduced' => false];
        }
        return ['method' => 'GET', 'action' => $this->endpoint, 'params' => $this->getParams, 'reduced' => true];
    }
}
