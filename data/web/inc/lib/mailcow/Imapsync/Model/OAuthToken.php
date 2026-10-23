<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Model;

/**
 * Per-user OAuth token of an authorization_code source (table `imapsync_source_oauth_token`).
 *
 * Bound to (source, owner, username): owner = local mailcow mailbox (job user2),
 * username = authenticated remote identity (job user1).
 */
class OAuthToken
{
    public int $sourceId = 0;
    public string $owner = '';
    public string $username = '';
    public ?string $accessToken = null;
    public ?string $refreshToken = null;
    public ?int $tokenExpires = null;
    public ?string $lastRefreshError = null;
    /** Seconds since the last refresh attempt by the DB clock; only filled by the refresh queries */
    public ?int $refreshAttemptAge = null;

    public static function fromRow(array $row): self
    {
        $token = new self();
        $token->sourceId = (int)($row['source_id'] ?? 0);
        $token->owner = (string)($row['owner'] ?? '');
        $token->username = (string)($row['username'] ?? '');
        $token->accessToken = isset($row['access_token']) ? (string)$row['access_token'] : null;
        $token->refreshToken = isset($row['refresh_token']) ? (string)$row['refresh_token'] : null;
        $token->tokenExpires = isset($row['token_expires']) ? (int)$row['token_expires'] : null;
        $token->lastRefreshError = isset($row['last_refresh_error']) ? (string)$row['last_refresh_error'] : null;
        $token->refreshAttemptAge = isset($row['refresh_attempt_age']) ? (int)$row['refresh_attempt_age'] : null;
        return $token;
    }
}
