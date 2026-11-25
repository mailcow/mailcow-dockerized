<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Model;

/**
 * A sync job (table `imapsync`): copies the remote account user1 of a source into the local mailbox user2.
 *
 * Loaded jobs also carry display data of their source and of the per-user token ($source / $token,
 * filled from the JOIN; no secrets). Switches default to off, because unchecked form checkboxes are not sent.
 */
class Job
{
    public ?int $id = null;
    public string $user2 = '';
    public ?string $regextrans2 = '';
    public int $authmd51 = 0;
    public string $domain2 = '';
    public string $subfolder2 = '';
    public string $user1 = '';
    /** Remote password, never returned by toArray() */
    public string $password1 = '';
    public string $exclude = '';
    public int $maxage = 0;
    public int $minsInterval = 0;
    /** Bytes/s limit, stored as text */
    public string $maxBytesPerSecond = '0';
    public int $delete2duplicates = 0;
    public int $delete1 = 0;
    public int $delete2 = 0;
    public int $automap = 0;
    public int $skipcrossduplicates = 0;
    /** JSON list of {o, v} pairs (see CustomParams), old rows may hold other text */
    public ?string $customParams = '[]';
    public ?string $customParamsLegacy = null;
    public int $timeout1 = 600;
    public int $timeout2 = 600;
    public int $subscribeall = 0;
    public int $dry = 0;
    public int $isRunning = 0;
    public ?string $returnedText = null;
    public ?string $lastRun = null;
    public ?int $success = null;
    public ?string $exitStatus = null;
    public ?string $created = null;
    public ?string $modified = null;
    public int $active = 0;
    public int $sourceId = 0;
    public int $prio = 0;
    public ?Source $source = null;
    public ?OAuthToken $token = null;

    /**
     * Builds a job from an `imapsync` row, optionally joined with `source_<column>` (columns of the source)
     * and `user_token_*` columns.
     */
    public static function fromRow(array $row): self
    {
        $job = new self();
        $job->id = isset($row['id']) ? (int)$row['id'] : null;
        foreach (['authmd51', 'maxage', 'delete2duplicates', 'delete1', 'delete2', 'automap', 'skipcrossduplicates', 'timeout1',
            'timeout2', 'subscribeall', 'dry', 'active', 'prio'] as $column) {
            $job->$column = (int)($row[$column] ?? 0);
        }
        foreach (['user2', 'domain2', 'subfolder2', 'user1', 'password1', 'exclude'] as $column) {
            $job->$column = (string)($row[$column] ?? '');
        }
        $job->minsInterval = (int)($row['mins_interval'] ?? 0);
        $job->maxBytesPerSecond = (string)($row['maxbytespersecond'] ?? '0');
        $job->isRunning = (int)($row['is_running'] ?? 0);
        $job->success = isset($row['success']) ? (int)$row['success'] : null;
        // Nullable text columns (column => property)
        foreach (['regextrans2' => 'regextrans2', 'custom_params' => 'customParams', 'custom_params_legacy' => 'customParamsLegacy',
            'returned_text' => 'returnedText', 'last_run' => 'lastRun', 'exit_status' => 'exitStatus', 'created' => 'created',
            'modified' => 'modified'] as $column => $property) {
            $job->$property = isset($row[$column]) ? (string)$row[$column] : null;
        }
        $job->sourceId = (int)($row['source_id'] ?? 0);
        if (isset($row['source_name'])) {
            // source_id is the job's foreign key, i.e. the id of the source
            $source = [];
            foreach ($row as $key => $value) {
                if (str_starts_with($key, 'source_')) {
                    $source[substr($key, 7)] = $value;
                }
            }
            $job->source = Source::fromRow($source);
        }
        if (!empty($row['user_token_exists'])) {
            $job->token = OAuthToken::fromRow(['source_id' => $job->sourceId, 'owner' => $job->user2, 'username' => $job->user1,
                'token_expires' => $row['user_token_expires'] ?? null, 'last_refresh_error' => $row['user_token_error'] ?? null]);
        }
        return $job;
    }

    /**
     * API representation (GET syncjobs, edit page, log view). Never contains password1.
     * Without $withLog the `log` field is empty (UI tables request `no_log`).
     */
    public function toArray(bool $withLog = true): array
    {
        $source = $this->source;
        return [
            'id' => $this->id,
            'user2' => $this->user2,
            'regextrans2' => $this->regextrans2,
            'authmd51' => $this->authmd51,
            'domain2' => $this->domain2,
            'subfolder2' => $this->subfolder2,
            'user1' => $this->user1,
            'exclude' => $this->exclude,
            'maxage' => $this->maxage,
            'mins_interval' => $this->minsInterval,
            'maxbytespersecond' => $this->maxBytesPerSecond,
            'delete2duplicates' => $this->delete2duplicates,
            'delete1' => $this->delete1,
            'delete2' => $this->delete2,
            'automap' => $this->automap,
            'skipcrossduplicates' => $this->skipcrossduplicates,
            'custom_params' => $this->customParams,
            'timeout1' => $this->timeout1,
            'timeout2' => $this->timeout2,
            'subscribeall' => $this->subscribeall,
            'dry' => $this->dry,
            'is_running' => $this->isRunning,
            'last_run' => $this->lastRun,
            'success' => $this->success,
            'exit_status' => $this->exitStatus,
            'created' => $this->created,
            'modified' => $this->modified,
            'active' => $this->active,
            'source_id' => $this->sourceId,
            'custom_params_legacy' => $this->customParamsLegacy,
            'prio' => $this->prio,
            'source_name' => $source?->name,
            'source_description' => $source?->description,
            'source_host' => $source?->host,
            'source_port' => $source?->port,
            'source_enc' => $source?->enc,
            'source_auth_type' => $source?->authType,
            'source_created_by' => $source?->createdBy,
            'source_scope' => $source?->scope,
            'source_oauth_flow' => $source?->oauthFlow,
            'source_active' => $source?->active,
            'source_oauth_token_expires' => $source?->tokenExpires,
            'source_oauth_last_refresh_error' => $source?->lastRefreshError,
            'user_token_expires' => $this->token?->tokenExpires,
            'user_token_error' => $this->token?->lastRefreshError,
            'user_token_exists' => $this->token === null ? 0 : 1,
            'log' => ($withLog && !empty($this->returnedText)) ? $this->returnedText : '',
        ];
    }
}
