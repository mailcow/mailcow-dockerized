<?php

declare(strict_types=1);

namespace Mailcow\Backup\Model;

/**
 * A job of the backup runners (table `backup_job`): backup, check, prune and init run in the restic container; a
 * restore runs there (step `files`, then the merge through backup.php) and, for mails, IMAP ACLs and Sieve
 * scripts, in the dovecot container (step `mails`). Times are unix timestamps.
 */
class Job
{
    public const TYPES = ['backup', 'check', 'prune', 'init', 'restore'];
    /** Types an admin starts with add/backup-job */
    public const MANUAL_TYPES = ['backup', 'check', 'prune', 'init'];
    public const ACTIVE_STATES = ['queued', 'running'];

    public ?int $id = null;
    public string $type = 'backup';
    public string $state = 'queued';
    public ?string $step = null;
    /**
     * restore: snapshot, snapshot_time, scope, domain, mailboxes, components, mail_target, requested_role, create_domain (0|1),
     * create_mailboxes (missing when requested), acl and allowed_domains (domain admins)
     */
    public array $params = [];
    public ?array $result = null;
    /** Only loaded for a single job */
    public ?string $log = null;
    public ?string $error = null;
    /** restore: the domain of its mailboxes, so domain admins see their restores */
    public string $domain = '';
    public string $requestedBy = '';
    public ?int $created = null;
    public ?int $started = null;
    public ?int $finished = null;

    public function __construct(string $type = 'backup')
    {
        $this->type = $type;
    }

    /** Row of `backup_job` with the times as unix timestamps */
    public static function fromRow(array $row): self
    {
        $job = new self((string)$row['type']);
        $job->id = (int)$row['id'];
        $job->state = (string)$row['state'];
        $job->step = isset($row['step']) ? (string)$row['step'] : null;
        $params = is_string($row['params'] ?? null) ? json_decode($row['params'], true) : null;
        $job->params = is_array($params) ? $params : [];
        $result = is_string($row['result'] ?? null) ? json_decode($row['result'], true) : null;
        $job->result = is_array($result) ? $result : null;
        $job->log = isset($row['log']) ? (string)$row['log'] : null;
        $job->error = isset($row['error']) ? (string)$row['error'] : null;
        $job->domain = (string)($row['domain'] ?? '');
        $job->requestedBy = (string)($row['requested_by'] ?? '');
        foreach (['created', 'started', 'finished'] as $time) {
            $job->$time = isset($row[$time]) ? (int)$row[$time] : null;
        }
        return $job;
    }

    /** Column values for a new row */
    public function toRow(): array
    {
        return [
            'type' => $this->type,
            'state' => $this->state,
            'step' => $this->step,
            'params' => json_encode($this->params ?: new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'domain' => $this->domain,
            'requested_by' => $this->requestedBy,
        ];
    }

    /** @return string[] mailboxes of a restore */
    public function mailboxes(): array
    {
        return array_values(array_filter((array)($this->params['mailboxes'] ?? []), 'is_string'));
    }

    /** @return string[] components of a restore */
    public function components(): array
    {
        return array_values(array_filter((array)($this->params['components'] ?? []), 'is_string'));
    }

    /** @return string[] mailboxes of a restore that did not exist when it was requested */
    public function createMailboxes(): array
    {
        return array_values(array_filter((array)($this->params['create_mailboxes'] ?? []), 'is_string'));
    }

    /** The restore creates its domain (requested by an admin while the domain did not exist) */
    public function createsDomain(): bool
    {
        return (int)($this->params['create_domain'] ?? 0) === 1;
    }

    public function isActive(): bool
    {
        return in_array($this->state, self::ACTIVE_STATES, true);
    }

    /** Entry of the API; the log only with $withLog (get/backup-job/<id>) */
    public function toArray(bool $withLog = false): array
    {
        $job = [
            'id' => $this->id,
            'type' => $this->type,
            'state' => $this->state,
            'step' => $this->step,
            'params' => $this->params ?: new \stdClass(),
            'result' => $this->result,
            'error' => $this->error,
            'domain' => $this->domain,
            'requested_by' => $this->requestedBy,
            'created' => $this->created,
            'started' => $this->started,
            'finished' => $this->finished,
        ];
        if ($withLog) {
            $job['log'] = (string)$this->log;
        }
        return $job;
    }
}
