<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Model;

/**
 * A reusable IMAP source (table `imapsync_source` plus its scope targets).
 *
 * Visibility is driven by `scope` and the target lists:
 *   'all'    visible to everyone (admin-created only)
 *   'domain' visible to the listed domains (their domain admins and users)
 *   'user'   visible to the listed users (and their domain admins); no targets = private
 * `createdBy` records the creator ('' = handed over to the admins).
 *
 * The object holds secrets (client secret, access token); toArray() never returns them.
 */
class Source
{
    public const AUTH_XOAUTH2 = 'XOAUTH2';
    public const FLOW_CLIENT_CREDENTIALS = 'client_credentials';
    public const FLOW_AUTHORIZATION_CODE = 'authorization_code';

    public ?int $id = null;
    public string $name = '';
    public string $description = '';
    public string $createdBy = '';
    public string $scope = 'user';
    public string $host = '';
    public int $port = 0;
    public ?string $enc = 'TLS';
    public ?string $authType = 'PLAIN';
    public string $oauthFlow = self::FLOW_CLIENT_CREDENTIALS;
    public ?string $tokenEndpoint = null;
    public ?string $authorizeEndpoint = null;
    public ?string $userinfoEndpoint = null;
    public ?string $clientId = null;
    public ?string $clientSecret = null;
    public ?string $oauthScope = null;
    /** Extra OAuth parameters (sign-in or token request, see OAuthService), JSON object text */
    public ?string $extraParams = null;
    /** Shared token of a client_credentials source */
    public ?string $accessToken = null;
    public ?int $tokenExpires = null;
    public ?string $lastRefreshError = null;
    public ?string $lastRefreshAttempt = null;
    /** Seconds since lastRefreshAttempt by the DB clock; only filled by the refresh queries */
    public ?int $refreshAttemptAge = null;
    public ?string $created = null;
    public ?string $modified = null;
    public int $active = 1;
    /** @var string[] */
    public array $domains = [];
    /** @var string[] */
    public array $users = [];

    /** Builds a source from an `imapsync_source` row (column names as in the table) */
    public static function fromRow(array $row): self
    {
        $source = new self();
        $source->id = isset($row['id']) ? (int)$row['id'] : null;
        $source->name = (string)($row['name'] ?? '');
        $source->description = (string)($row['description'] ?? '');
        $source->createdBy = (string)($row['created_by'] ?? '');
        $source->scope = (string)($row['scope'] ?? 'user');
        $source->host = (string)($row['host1'] ?? '');
        $source->port = (int)($row['port1'] ?? 0);
        $source->oauthFlow = (string)($row['oauth_flow'] ?? self::FLOW_CLIENT_CREDENTIALS);
        $source->tokenExpires = isset($row['oauth_token_expires']) ? (int)$row['oauth_token_expires'] : null;
        $source->refreshAttemptAge = isset($row['refresh_attempt_age']) ? (int)$row['refresh_attempt_age'] : null;
        // Nullable text columns (column => property)
        foreach (['enc1' => 'enc', 'auth_type' => 'authType', 'oauth_token_endpoint' => 'tokenEndpoint',
            'oauth_authorize_endpoint' => 'authorizeEndpoint', 'oauth_userinfo_endpoint' => 'userinfoEndpoint',
            'oauth_client_id' => 'clientId', 'oauth_client_secret' => 'clientSecret', 'oauth_scope' => 'oauthScope',
            'oauth_extra_params' => 'extraParams', 'oauth_access_token' => 'accessToken', 'oauth_last_refresh_error' => 'lastRefreshError',
            'oauth_last_refresh_attempt' => 'lastRefreshAttempt', 'created' => 'created', 'modified' => 'modified'] as $column => $property) {
            $source->$property = isset($row[$column]) ? (string)$row[$column] : null;
        }
        $source->active = (int)($row['active'] ?? 1);
        return $source;
    }

    public function isXOAuth2(): bool
    {
        return $this->authType === self::AUTH_XOAUTH2;
    }

    /** XOAUTH2 with one app-wide token that reaches every mailbox of the tenant */
    public function usesClientCredentials(): bool
    {
        return $this->isXOAuth2() && $this->oauthFlow === self::FLOW_CLIENT_CREDENTIALS;
    }

    /** XOAUTH2 with a per-user token from the browser sign-in */
    public function usesAuthorizationCode(): bool
    {
        return $this->isXOAuth2() && $this->oauthFlow === self::FLOW_AUTHORIZATION_CODE;
    }

    /**
     * API representation (GET syncjob_source). Secrets and tokens are replaced by has_* flags;
     * extra params are only shown to callers who may edit the source.
     */
    public function toArray(bool $canEdit, string $redirectUri): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_by' => $this->createdBy,
            'scope' => $this->scope,
            'host1' => $this->host,
            'port1' => $this->port,
            'enc1' => $this->enc,
            'auth_type' => $this->authType,
            'oauth_flow' => $this->oauthFlow,
            'oauth_token_endpoint' => $this->tokenEndpoint,
            'oauth_authorize_endpoint' => $this->authorizeEndpoint,
            'oauth_userinfo_endpoint' => $this->userinfoEndpoint,
            'oauth_client_id' => $this->clientId,
            'oauth_scope' => $this->oauthScope,
            'oauth_extra_params' => $canEdit ? $this->extraParams : null,
            'oauth_token_expires' => $this->tokenExpires,
            'oauth_last_refresh_error' => $this->lastRefreshError,
            'oauth_last_refresh_attempt' => $this->lastRefreshAttempt,
            'created' => $this->created,
            'modified' => $this->modified,
            'active' => $this->active,
            'domains' => $this->domains,
            'users' => $this->users,
            'can_edit' => $canEdit,
            'host' => $this->host,
            'port' => $this->port,
            'enc' => $this->enc,
            'has_client_secret' => !empty($this->clientSecret),
            'has_access_token' => !empty($this->accessToken),
        ];
        if ($this->usesAuthorizationCode()) {
            $data['oauth_redirect_uri'] = $redirectUri;
        }
        return $data;
    }
}
