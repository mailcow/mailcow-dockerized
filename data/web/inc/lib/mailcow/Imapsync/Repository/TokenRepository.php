<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Repository;

use Mailcow\Core\Repository;
use Mailcow\Imapsync\Model\OAuthToken;
use Mailcow\Imapsync\Model\Source;
use PDO;

/**
 * SQL for OAuth tokens: the shared token of a client_credentials source (columns of `imapsync_source`)
 * and the per-user tokens of authorization_code sources (`imapsync_source_oauth_token`).
 */
class TokenRepository extends Repository
{
    private const USER_TOKEN = "`source_id` = :sid AND `owner` = :owner AND `username` = :user";

    public function exists(int $sourceId, string $owner, string $username): bool
    {
        return (bool)$this->query("SELECT 1 FROM `imapsync_source_oauth_token` WHERE " . self::USER_TOKEN,
            ['sid' => $sourceId, 'owner' => $owner, 'user' => $username])->fetchColumn();
    }

    /**
     * Stores the token of a finished browser sign-in. An existing refresh token is kept when the
     * provider sent none (Microsoft only sends one with offline_access).
     */
    public function saveUserToken(OAuthToken $token): void
    {
        $this->query("INSERT INTO `imapsync_source_oauth_token`
            (`source_id`, `owner`, `username`, `access_token`, `refresh_token`, `token_expires`, `last_refresh_error`, `oauth_last_refresh_attempt`)
          VALUES (:sid, :owner, :user, :acc, :ref, :exp, NULL, NOW())
          ON DUPLICATE KEY UPDATE
            `access_token` = VALUES(`access_token`),
            `refresh_token` = IF(VALUES(`refresh_token`) = '', `refresh_token`, VALUES(`refresh_token`)),
            `token_expires` = VALUES(`token_expires`),
            `last_refresh_error` = NULL,
            `oauth_last_refresh_attempt` = NOW()",
            $this->key($token) + ['acc' => $token->accessToken, 'ref' => (string)$token->refreshToken, 'exp' => $token->tokenExpires]);
    }

    /** Stores a refreshed per-user token and clears its last error */
    public function storeUserRefresh(OAuthToken $token): void
    {
        $this->query("UPDATE `imapsync_source_oauth_token` SET `access_token` = :acc, `refresh_token` = :ref,
            `token_expires` = :exp, `last_refresh_error` = NULL, `oauth_last_refresh_attempt` = NOW() WHERE " . self::USER_TOKEN,
            $this->key($token) + ['acc' => $token->accessToken, 'ref' => $token->refreshToken, 'exp' => $token->tokenExpires]);
    }

    public function storeUserError(OAuthToken $token, string $error): void
    {
        $this->query("UPDATE `imapsync_source_oauth_token` SET `last_refresh_error` = :err, `oauth_last_refresh_attempt` = NOW()
          WHERE " . self::USER_TOKEN, $this->key($token) + ['err' => $error]);
    }

    public function storeSourceToken(int $sourceId, string $accessToken, int $expires): void
    {
        $this->query("UPDATE `imapsync_source` SET `oauth_access_token` = :tok, `oauth_token_expires` = :exp,
            `oauth_last_refresh_error` = NULL, `oauth_last_refresh_attempt` = NOW() WHERE `id` = :id",
            ['tok' => $accessToken, 'exp' => $expires, 'id' => $sourceId]);
    }

    public function storeSourceError(int $sourceId, string $error): void
    {
        $this->query("UPDATE `imapsync_source` SET `oauth_last_refresh_error` = :err, `oauth_last_refresh_attempt` = NOW()
          WHERE `id` = :id", ['err' => $error, 'id' => $sourceId]);
    }

    /** @return Source[] active client_credentials sources (incl. secrets), each with refreshAttemptAge */
    public function refreshableSources(): array
    {
        return array_map([Source::class, 'fromRow'], $this->query("SELECT *,
            TIMESTAMPDIFF(SECOND, `oauth_last_refresh_attempt`, NOW()) AS `refresh_attempt_age`
          FROM `imapsync_source`
          WHERE `active` = 1 AND `auth_type` = 'XOAUTH2' AND `oauth_flow` = 'client_credentials'
          ORDER BY `id`")->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return OAuthToken[] per-user tokens with a refresh token on active authorization_code sources, with refreshAttemptAge */
    public function refreshableUserTokens(): array
    {
        return array_map([OAuthToken::class, 'fromRow'], $this->query("SELECT t.*,
            TIMESTAMPDIFF(SECOND, t.`oauth_last_refresh_attempt`, NOW()) AS `refresh_attempt_age`
          FROM `imapsync_source_oauth_token` t
          JOIN `imapsync_source` s ON s.`id` = t.`source_id`
          WHERE s.`active` = 1 AND s.`auth_type` = 'XOAUTH2' AND s.`oauth_flow` = 'authorization_code'
            AND t.`refresh_token` <> ''
          ORDER BY t.`source_id`, t.`owner`, t.`username`")->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Drops a per-user token once no job uses (source, owner, username) anymore */
    public function deleteIfUnused(int $sourceId, string $owner, string $username): void
    {
        $this->query("DELETE FROM `imapsync_source_oauth_token` WHERE " . self::USER_TOKEN . "
            AND NOT EXISTS (SELECT 1 FROM `imapsync` WHERE `source_id` = :sid2 AND `user2` = :owner2 AND `user1` = :user2)",
            ['sid' => $sourceId, 'owner' => $owner, 'user' => $username, 'sid2' => $sourceId, 'owner2' => $owner, 'user2' => $username]);
    }

    /** Drops per-user tokens that no job uses and that are older than $seconds. Returns the number of rows. */
    public function deleteUnusedOlderThan(int $seconds): int
    {
        return $this->query("DELETE t FROM `imapsync_source_oauth_token` t
          LEFT JOIN `imapsync` i ON i.`source_id` = t.`source_id` AND i.`user2` = t.`owner` AND i.`user1` = t.`username`
          WHERE i.`id` IS NULL AND t.`created` < NOW() - INTERVAL :seconds SECOND", ['seconds' => $seconds])->rowCount();
    }

    public function deleteByOwner(string $owner): void
    {
        $this->query("DELETE FROM `imapsync_source_oauth_token` WHERE `owner` = :owner", ['owner' => $owner]);
    }

    public function renameOwner(string $old, string $new): void
    {
        $this->query("UPDATE `imapsync_source_oauth_token` SET `owner` = :new WHERE `owner` = :old", ['new' => $new, 'old' => $old]);
    }

    private function key(OAuthToken $token): array
    {
        return ['sid' => $token->sourceId, 'owner' => $token->owner, 'user' => $token->username];
    }
}
