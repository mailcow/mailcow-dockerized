<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Service;

use Exception;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Mailcow\Imapsync\Http\OAuthClient;
use Mailcow\Imapsync\Http\OAuthException;
use Mailcow\Imapsync\Model\OAuthToken;
use Mailcow\Imapsync\Model\Source;
use Mailcow\Imapsync\Repository\SourceRepository;
use Mailcow\Imapsync\Repository\TokenRepository;
use PDOException;

/**
 * OAuth2 for XOAUTH2 sources: their OAuth settings, token requests for every grant type, the browser sign-in
 * (authorization_code) and token refresh. Needs no Context, so the refresh cron can use it; callers check
 * permissions first.
 */
class OAuthService
{
    /** Tokens expiring within this many seconds are refreshed (the runner starts jobs with >= 15 min left) */
    private const REFRESH_AHEAD = 1200;
    /** A token whose last refresh failed is retried after this many seconds */
    private const RETRY_AFTER = 180;
    /** Extra params that belong to the browser sign-in; all others go to the token request */
    private const AUTHORIZE_PARAMS = ['prompt', 'access_type', 'login_hint', 'domain_hint', 'hd'];
    /** Per-user tokens that no job uses are kept this long (time to save the job after signing in) */
    private const UNUSED_TOKEN_GRACE = 86400;

    private string $hostname;

    public function __construct(private SourceRepository $sources, private TokenRepository $tokens, private OAuthClient $client,
        string $hostname)
    {
        $this->hostname = strtolower(trim($hostname));
    }

    /**
     * Redirect URI to register at the provider. Only MAILCOW_HOSTNAME is supported: the popup callback
     * needs the session cookie and opener origin of the UI, so the UI must be used on that host.
     */
    public function redirectUri(): string
    {
        return ($this->hostname === '') ? '' : 'https://' . $this->hostname . '/syncjob-oauth';
    }

    /**
     * Sets the OAuth settings of a source from request attributes: cleared for password sources, read and
     * validated for XOAUTH2 (incl. the endpoint policy C5). Missing attributes keep the value of $source,
     * an empty client secret keeps the stored one. Permission checks are the caller's job.
     *
     * @throws UserError
     */
    public function applySettings(Source $source, array $input): void
    {
        if (!$source->isXOAuth2()) {
            $source->oauthFlow = Source::FLOW_CLIENT_CREDENTIALS;
            $source->tokenEndpoint = $source->authorizeEndpoint = $source->userinfoEndpoint = null;
            $source->clientId = $source->clientSecret = $source->oauthScope = $source->extraParams = null;
            return;
        }
        $source->oauthFlow = (($input['oauth_flow'] ?? $source->oauthFlow) === Source::FLOW_AUTHORIZATION_CODE)
            ? Source::FLOW_AUTHORIZATION_CODE : Source::FLOW_CLIENT_CREDENTIALS;
        $source->tokenEndpoint = $this->oauthText($input, 'oauth_token_endpoint', $source->tokenEndpoint);
        $authorizeEndpoint = $this->oauthText($input, 'oauth_authorize_endpoint', $source->authorizeEndpoint);
        $userinfoEndpoint = $this->oauthText($input, 'oauth_userinfo_endpoint', $source->userinfoEndpoint);
        $source->clientId = $this->oauthText($input, 'oauth_client_id', $source->clientId);
        $source->oauthScope = $this->oauthText($input, 'oauth_scope', $source->oauthScope);
        $secret = $input['oauth_client_secret'] ?? '';
        if (!is_scalar($secret)) {
            throw new UserError('imapsync_source_oauth_invalid');
        }
        $source->clientSecret = ((string)$secret !== '') ? (string)$secret : (string)$source->clientSecret;
        $source->extraParams = $this->extraParams(array_key_exists('oauth_extra_params', $input) ? $input['oauth_extra_params'] : $source->extraParams);

        if ($source->tokenEndpoint === '' || $source->clientId === '' || $source->clientSecret === '' || $source->oauthScope === '') {
            throw new UserError('imapsync_source_oauth_required');
        }
        if (mb_strlen($source->clientId) > 500 || mb_strlen($source->clientSecret) > 500 || mb_strlen($source->oauthScope) > 500) {
            throw new UserError('imapsync_source_oauth_invalid');
        }
        // C5, checked again before every request. The authorize endpoint is only opened in the browser,
        // but must not point users to internal hosts either.
        $endpoints = [$source->tokenEndpoint];
        if ($source->usesAuthorizationCode()) {
            $endpoints[] = $authorizeEndpoint;
        }
        if ($userinfoEndpoint !== '') {
            $endpoints[] = $userinfoEndpoint;
        }
        foreach ($endpoints as $endpoint) {
            if (!$this->endpointAllowed($endpoint, $source->createdBy)) {
                throw new UserError('imapsync_source_oauth_endpoint_invalid');
            }
        }
        $source->authorizeEndpoint = ($authorizeEndpoint === '') ? null : $authorizeEndpoint;
        $source->userinfoEndpoint = ($userinfoEndpoint === '') ? null : $userinfoEndpoint;
    }

    /** Provider consent URL for the browser sign-in, or null when the authorize endpoint is not allowed */
    public function authorizeUrl(Source $source, string $state): ?string
    {
        $endpoint = (string)$source->authorizeEndpoint;
        if (!$this->endpointAllowed($endpoint, $source->createdBy)) {
            return null;
        }
        // select_account: never sign in silently with the account the browser already uses (often the admin's)
        $params = array_merge(['prompt' => 'select_account'], $this->extraParamsFor($source, true), ['client_id' => $source->clientId,
            'response_type' => 'code', 'redirect_uri' => $this->redirectUri(), 'response_mode' => 'query',
            'scope' => $source->oauthScope, 'state' => $state]);
        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . http_build_query($params);
    }

    /**
     * Finishes the browser sign-in: exchanges the code, finds the remote identity and stores the token
     * for the local mailbox $owner (C1). Returns the remote identity (the job's user1).
     *
     * @throws UserError token_failed (args: detail for the server log) or no_identity
     */
    public function connectUser(Source $source, string $owner, string $code): string
    {
        try {
            $token = $this->requestToken($source, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri()]);
        } catch (OAuthException $e) {
            throw new UserError('token_failed', ['token exchange failed for source ' . $source->id . ': ' . $e->getMessage()]);
        }
        $identity = $this->identity($source, $token);
        if ($identity === null) {
            throw new UserError('no_identity');
        }
        try {
            $this->tokens->saveUserToken(OAuthToken::fromRow(['source_id' => $source->id, 'owner' => $owner, 'username' => $identity,
                'access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? '',
                'token_expires' => time() + (int)$token['expires_in']]));
        } catch (PDOException $e) {
            throw new UserError('token_failed', ['storing token failed for source ' . $source->id . ': ' . $e->getMessage()]);
        }
        return $identity;
    }

    /** Fetches and stores a new shared token of a client_credentials source (or stores the error); returns the error or null */
    public function refreshSourceToken(Source $source): ?string
    {
        if (!$source->usesClientCredentials()) {
            return 'not refreshable';
        }
        try {
            $token = $this->requestToken($source, ['grant_type' => 'client_credentials']);
        } catch (OAuthException $e) {
            $this->tokens->storeSourceError((int)$source->id, $e->getMessage());
            return $e->getMessage();
        }
        $this->tokens->storeSourceToken((int)$source->id, $token['access_token'], time() + (int)$token['expires_in']);
        return null;
    }

    /** Refreshes and stores a per-user token via its refresh token (or stores the error); returns the error or null */
    public function refreshUserToken(Source $source, OAuthToken $token): ?string
    {
        if (!$source->usesAuthorizationCode() || (string)$token->refreshToken === '') {
            return 'not refreshable';
        }
        try {
            $new = $this->requestToken($source, ['grant_type' => 'refresh_token', 'refresh_token' => $token->refreshToken]);
        } catch (OAuthException $e) {
            $this->tokens->storeUserError($token, $e->getMessage());
            return $e->getMessage();
        }
        $token->accessToken = $new['access_token'];
        // Some providers omit a new refresh token on refresh: keep the existing one then
        $token->refreshToken = empty($new['refresh_token']) ? $token->refreshToken : $new['refresh_token'];
        $token->tokenExpires = time() + (int)$new['expires_in'];
        $this->tokens->storeUserRefresh($token);
        return null;
    }

    /**
     * Cron run: refreshes due source tokens, drops unused per-user tokens, refreshes due per-user tokens.
     *
     * @return array<int, array{priority: string, message: string}> log entries for CRON_LOG
     */
    public function refreshDue(): array
    {
        $log = [];
        foreach ($this->tokens->refreshableSources() as $source) {
            if ($this->isDue($source->tokenExpires, $source->lastRefreshError, $source->refreshAttemptAge)) {
                try {
                    $error = $this->refreshSourceToken($source);
                } catch (Exception $e) {
                    $error = get_class($e);
                }
                $label = "source '" . $source->name . "' (id=" . $source->id . ")";
                $log[] = $this->logEntry($error, "Refreshed token for " . $label, "Refresh failed for " . $label);
            }
        }
        $removed = $this->tokens->deleteUnusedOlderThan(self::UNUSED_TOKEN_GRACE);
        if ($removed > 0) {
            $log[] = ['priority' => 'info', 'message' => "Removed " . $removed . " unused user token(s)"];
        }
        foreach ($this->tokens->refreshableUserTokens() as $token) {
            if ($this->isDue($token->tokenExpires, $token->lastRefreshError, $token->refreshAttemptAge)) {
                $source = null;
                try {
                    $source = $this->sources->find($token->sourceId);
                    $error = ($source === null) ? 'not refreshable' : $this->refreshUserToken($source, $token);
                } catch (Exception $e) {
                    $error = get_class($e);
                }
                $label = "user token '" . $token->username . "' (mailbox " . $token->owner . ") on source '"
                    . ($source->name ?? '') . "' (id=" . $token->sourceId . ")";
                $log[] = $this->logEntry($error, "Refreshed " . $label, "Refresh failed for " . $label);
            }
        }
        return $log;
    }

    /** Due when the token expires soon (or is missing) and the last failed attempt is old enough */
    private function isDue(?int $expires, ?string $lastError, ?int $attemptAge): bool
    {
        return ($expires === null || $expires < time() + self::REFRESH_AHEAD)
            && ($lastError === null || $attemptAge === null || $attemptAge > self::RETRY_AFTER);
    }

    private function logEntry(?string $error, string $success, string $failure): array
    {
        return ($error === null) ? ['priority' => 'info', 'message' => $success]
            : ['priority' => 'warning', 'message' => $failure . ": " . $error];
    }

    /**
     * Endpoint policy (C5). Endpoints of sources not created by an admin must resolve to public addresses;
     * admin sources have no address restriction (API key sessions run as admin under the reserved name 'API').
     */
    private function endpointAllowed(string $url, string $createdBy): bool
    {
        return $this->client->isAllowed($url, $this->isStrict($createdBy));
    }

    private function isStrict(string $createdBy): bool
    {
        return !($createdBy === 'API' || $this->sources->isSuperAdmin($createdBy));
    }

    /** One OAuth text attribute from the request, or the stored value when the attribute is missing */
    private function oauthText(array $input, string $key, ?string $stored): string
    {
        $value = array_key_exists($key, $input) ? Input::text($input[$key]) : (string)$stored;
        return $value ?? throw new UserError('imapsync_source_oauth_invalid');
    }

    /** Stored extra params of the browser sign-in ($authorize) or of the token request */
    private function extraParamsFor(Source $source, bool $authorize): array
    {
        $extra = json_decode((string)$source->extraParams, true);
        return array_filter(is_array($extra) ? $extra : [],
            fn($key): bool => in_array($key, self::AUTHORIZE_PARAMS, true) === $authorize, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Extra parameters: a JSON object with scalar values, as string or as array (after 011-api.js
     * auto-parsing). Returns the JSON to store, null for none.
     */
    private function extraParams(mixed $extra): ?string
    {
        if ($extra === null || (is_string($extra) && trim($extra) === '')) {
            $extra = [];
        } elseif (is_string($extra)) {
            $extra = json_decode($extra, true);
        }
        $bad = !is_array($extra);
        foreach ((array)$extra as $key => $value) {
            $bad = $bad || !is_string($key) || $key === '' || !(is_scalar($value) || $value === null);
        }
        $json = empty($extra) ? null : json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($bad || strlen((string)$json) > 4000) {
            throw new UserError('imapsync_source_oauth_extra_invalid');
        }
        return $json;
    }

    /**
     * Token endpoint request for every grant type. Stored extra params never override the client and grant fields.
     *
     * @throws OAuthException
     */
    private function requestToken(Source $source, array $grant): array
    {
        $post = array_merge($this->extraParamsFor($source, false),
            ['client_id' => $source->clientId, 'client_secret' => $source->clientSecret, 'scope' => $source->oauthScope], $grant);
        $token = $this->client->request((string)$source->tokenEndpoint, $this->isStrict($source->createdBy), $post);
        if (empty($token['access_token']) || !is_string($token['access_token'])) {
            throw new OAuthException('malformed token response');
        }
        if (!isset($token['expires_in']) || !is_numeric($token['expires_in'])) {
            $token['expires_in'] = 3600;
        }
        if (isset($token['refresh_token']) && !is_string($token['refresh_token'])) {
            unset($token['refresh_token']);
        }
        return $token;
    }

    /** Remote identity: id_token claims (received directly from the token endpoint over TLS), else the userinfo endpoint */
    private function identity(Source $source, array $token): ?string
    {
        $parts = explode('.', is_string($token['id_token'] ?? null) ? $token['id_token'] : '');
        $email = (count($parts) === 3) ? $this->emailClaim(json_decode((string)base64_decode(strtr($parts[1], '-_', '+/')), true)) : null;
        if ($email !== null || empty($source->userinfoEndpoint)) {
            return $email;
        }
        try {
            return $this->emailClaim($this->client->request($source->userinfoEndpoint, $this->isStrict($source->createdBy), null,
                ['Authorization: Bearer ' . $token['access_token']]));
        } catch (OAuthException $e) {
            return null;
        }
    }

    private function emailClaim(mixed $claims): ?string
    {
        foreach (['email', 'preferred_username', 'upn', 'unique_name'] as $key) {
            if (is_array($claims) && !empty($claims[$key]) && is_string($claims[$key]) && filter_var($claims[$key], FILTER_VALIDATE_EMAIL)) {
                return strtolower($claims[$key]);
            }
        }
        return null;
    }
}
