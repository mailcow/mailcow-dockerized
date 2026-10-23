<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Repository;

use Mailcow\Core\Context;
use Mailcow\Core\Repository;
use Mailcow\Imapsync\Model\Source;
use PDO;

/**
 * SQL for `imapsync_source` and its target tables `imapsync_source_domain` / `imapsync_source_user`.
 * No permission decisions here except the visibility filter, which is pure SQL (see visibility()).
 */
class SourceRepository extends Repository
{
    /** Full source incl. secrets and targets, without visibility filter */
    public function find(int $id): ?Source
    {
        return $this->fetch('1=1', ['id' => $id]);
    }

    /** Like find(), but only when the source is visible to the caller */
    public function findVisible(int $id, Context $context): ?Source
    {
        [$visible, $params] = $this->visibility($context);
        return $this->fetch($visible, $params + ['id' => $id]);
    }

    /** @return int[] ids of all sources visible to the caller, ordered by name */
    public function visibleIds(Context $context): array
    {
        [$visible, $params] = $this->visibility($context);
        return array_map('intval', $this->query("SELECT s.`id` FROM `imapsync_source` s WHERE $visible ORDER BY s.`name`, s.`id`",
            $params)->fetchAll(PDO::FETCH_COLUMN));
    }

    public function nameExists(string $name, string $createdBy, ?int $exceptId = null): bool
    {
        return (bool)$this->query("SELECT 1 FROM `imapsync_source` WHERE `name` = :name AND `created_by` = :created_by AND `id` != :id",
            ['name' => $name, 'created_by' => $createdBy, 'id' => $exceptId ?? 0])->fetchColumn();
    }

    /** True while a sync job references the source */
    public function isInUse(int $id): bool
    {
        return (bool)$this->query("SELECT 1 FROM `imapsync` WHERE `source_id` = :id LIMIT 1", ['id' => $id])->fetchColumn();
    }

    public function isSuperAdmin(string $username): bool
    {
        return (bool)$this->query("SELECT 1 FROM `admin` WHERE `username` = :username AND `superadmin` = '1'",
            ['username' => $username])->fetchColumn();
    }

    /** Inserts the source with its targets and returns the new id */
    public function insert(Source $source): int
    {
        return $this->transaction(function () use ($source): int {
            $source->id = $this->insertRow('imapsync_source',
                $this->values($source) + ['created_by' => $source->createdBy, 'scope' => $source->scope]);
            $this->writeTargets($source);
            return $source->id;
        });
    }

    /**
     * Updates the source. $writeTargets also rewrites scope + target lists, $resetTokens drops the shared
     * token and all per-user tokens of the source. All in one transaction.
     */
    public function update(Source $source, bool $writeTargets, bool $resetTokens): void
    {
        $this->transaction(function () use ($source, $writeTargets, $resetTokens): void {
            $id = ['id' => $source->id];
            $this->updateRow('imapsync_source', (int)$source->id, $this->values($source));
            if ($writeTargets) {
                $this->query("DELETE FROM `imapsync_source_domain` WHERE `source_id` = :id", $id);
                $this->query("DELETE FROM `imapsync_source_user` WHERE `source_id` = :id", $id);
                $this->writeTargets($source);
            }
            if ($resetTokens) {
                $this->query("UPDATE `imapsync_source` SET `oauth_access_token` = NULL, `oauth_token_expires` = NULL,
                  `oauth_last_refresh_error` = NULL, `oauth_last_refresh_attempt` = NULL WHERE `id` = :id", $id);
                $this->query("DELETE FROM `imapsync_source_oauth_token` WHERE `source_id` = :id", $id);
            }
        });
    }

    /** Deletes the source; targets and per-user tokens go via FK ON DELETE CASCADE */
    public function delete(int $id): void
    {
        $this->query("DELETE FROM `imapsync_source` WHERE `id` = :id", ['id' => $id]);
    }

    /** Deletes the source and its child rows explicitly, for callers running with FOREIGN_KEY_CHECKS = 0 (mailbox rename) */
    public function deleteWithChildren(int $id): void
    {
        foreach (['imapsync_source_oauth_token', 'imapsync_source_user', 'imapsync_source_domain'] as $table) {
            $this->query("DELETE FROM `$table` WHERE `source_id` = :id", ['id' => $id]);
        }
        $this->delete($id);
    }

    /** @return Source[] sources created by an account (id and name only) */
    public function listCreatedBy(string $username): array
    {
        return array_map([Source::class, 'fromRow'], $this->query("SELECT `id`, `name` FROM `imapsync_source` WHERE `created_by` = :username",
            ['username' => $username])->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Hands a source over to the admins (created_by '') under the given name */
    public function handOver(int $id, string $name): void
    {
        $this->query("UPDATE `imapsync_source` SET `created_by` = '', `name` = :name WHERE `id` = :id", ['name' => $name, 'id' => $id]);
    }

    /** Follows an account rename: creator and user targets */
    public function renameAccount(string $old, string $new): void
    {
        $this->query("UPDATE `imapsync_source` SET `created_by` = :new WHERE `created_by` = :old", ['new' => $new, 'old' => $old]);
        $this->query("UPDATE `imapsync_source_user` SET `username` = :new WHERE `username` = :old", ['new' => $new, 'old' => $old]);
    }

    /** Removes a deleted account from all user target lists */
    public function deleteUserTarget(string $username): void
    {
        $this->query("DELETE FROM `imapsync_source_user` WHERE `username` = :username", ['username' => $username]);
    }

    private function fetch(string $where, array $params): ?Source
    {
        $row = $this->query("SELECT s.* FROM `imapsync_source` s WHERE s.`id` = :id AND $where", $params)->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $source = Source::fromRow($row);
        $id = ['id' => $source->id];
        $source->domains = $this->query("SELECT `domain` FROM `imapsync_source_domain` WHERE `source_id` = :id ORDER BY `domain`",
            $id)->fetchAll(PDO::FETCH_COLUMN);
        $source->users = $this->query("SELECT `username` FROM `imapsync_source_user` WHERE `source_id` = :id ORDER BY `username`",
            $id)->fetchAll(PDO::FETCH_COLUMN);
        return $source;
    }

    /**
     * WHERE fragment over `imapsync_source s` that limits rows to what the caller may see, and its parameters.
     *   admin       everything
     *   domainadmin own + 'all' + sources targeting his domains or their mailboxes + sources created by them
     *   user        own + 'all' + sources targeting him or his domain
     *
     * @return array{0: string, 1: array}
     */
    private function visibility(Context $context): array
    {
        $username = $context->username;
        if ($context->isAdmin()) {
            return ['1=1', []];
        }
        // Native prepares: a placeholder may appear only once, so the username is bound under one name per use
        $managed = "SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = ";
        if ($context->isDomainAdmin()) {
            return ["(s.`created_by` = :vis_self OR s.`scope` = 'all'
                OR (s.`scope` = 'domain' AND EXISTS (SELECT 1 FROM `imapsync_source_domain` t
                  WHERE t.`source_id` = s.`id` AND t.`domain` IN ($managed :vis_d1)))
                OR (s.`scope` = 'user' AND EXISTS (SELECT 1 FROM `imapsync_source_user` t JOIN `mailbox` mb ON mb.`username` = t.`username`
                  WHERE t.`source_id` = s.`id` AND mb.`domain` IN ($managed :vis_d2)))
                OR s.`created_by` IN (SELECT `username` FROM `mailbox` WHERE `domain` IN ($managed :vis_d3)))",
                ['vis_self' => $username, 'vis_d1' => $username, 'vis_d2' => $username, 'vis_d3' => $username]];
        }
        if ($context->isUser()) {
            return ["(s.`created_by` = :vis_self OR s.`scope` = 'all'
                OR (s.`scope` = 'user' AND EXISTS (SELECT 1 FROM `imapsync_source_user` t
                  WHERE t.`source_id` = s.`id` AND t.`username` = :vis_u1))
                OR (s.`scope` = 'domain' AND EXISTS (SELECT 1 FROM `imapsync_source_domain` t JOIN `mailbox` mb ON mb.`domain` = t.`domain`
                  WHERE t.`source_id` = s.`id` AND mb.`username` = :vis_u2)))",
                ['vis_self' => $username, 'vis_u1' => $username, 'vis_u2' => $username]];
        }
        return ['0=1', []];
    }

    private function writeTargets(Source $source): void
    {
        foreach ($source->domains as $domain) {
            $this->query("INSERT INTO `imapsync_source_domain` (`source_id`, `domain`) VALUES (:id, :domain)",
                ['id' => $source->id, 'domain' => $domain]);
        }
        foreach ($source->users as $username) {
            $this->query("INSERT INTO `imapsync_source_user` (`source_id`, `username`) VALUES (:id, :username)",
                ['id' => $source->id, 'username' => $username]);
        }
        $this->query("UPDATE `imapsync_source` SET `scope` = :scope WHERE `id` = :id", ['scope' => $source->scope, 'id' => $source->id]);
    }

    private function values(Source $source): array
    {
        return [
            'name' => $source->name,
            'description' => $source->description,
            'host1' => $source->host,
            'port1' => $source->port,
            'enc1' => $source->enc,
            'auth_type' => $source->authType,
            'oauth_flow' => $source->oauthFlow,
            'oauth_token_endpoint' => $source->tokenEndpoint,
            'oauth_authorize_endpoint' => $source->authorizeEndpoint,
            'oauth_userinfo_endpoint' => $source->userinfoEndpoint,
            'oauth_client_id' => $source->clientId,
            'oauth_client_secret' => $source->clientSecret,
            'oauth_scope' => $source->oauthScope,
            'oauth_extra_params' => $source->extraParams,
            'active' => $source->active,
        ];
    }
}
