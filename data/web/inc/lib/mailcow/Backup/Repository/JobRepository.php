<?php

declare(strict_types=1);

namespace Mailcow\Backup\Repository;

use Mailcow\Backup\Model\Job;
use Mailcow\Core\Context;
use Mailcow\Core\Repository;
use Mailcow\Core\UserError;
use PDO;

/**
 * Table `backup_job` (the runners claim, log and finish the jobs) and the lookups a new restore job needs.
 */
class JobRepository extends Repository
{
    private const COLUMNS = "`id`, `type`, `state`, `step`, `params`, `result`, `error`, `domain`, `requested_by`,
        UNIX_TIMESTAMP(`created`) AS `created`, UNIX_TIMESTAMP(`started`) AS `started`, UNIX_TIMESTAMP(`finished`) AS `finished`";
    /** Serializes the check for active jobs and the insert of a new one */
    private const LOCK = 'mailcow_backup_job';

    public function find(int $id, bool $withLog = false): ?Job
    {
        $row = $this->query("SELECT " . self::COLUMNS . ($withLog ? ", `log`" : "") . " FROM `backup_job` WHERE `id` = :id", ['id' => $id])->fetch();
        return is_array($row) ? Job::fromRow($row) : null;
    }

    /**
     * @param ?string $type only jobs of this type
     * @return Job[] newest first: all jobs for admins, the restores of their domains (since the domain was created) for
     *         domain admins
     */
    public function visible(Context $context, int $limit, ?string $type = null): array
    {
        if ($context->hasAdminRights()) {
            [$where, $params] = ['1=1', []];
        } elseif ($context->isDomainAdmin()) {
            // restores of their domains, requested since the domain was created (not those of a former domain of that name)
            [$where, $params] = ["`type` = 'restore' AND `domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :da)
              AND `created` >= (SELECT `d`.`created` FROM `domain` `d` WHERE `d`.`domain` = `backup_job`.`domain`)", ['da' => $context->username]];
        } else {
            return [];
        }
        if ($type !== null) {
            $where .= " AND `type` = :type";
            $params['type'] = $type;
        }
        return array_map(fn(array $row): Job => Job::fromRow($row),
            $this->query("SELECT " . self::COLUMNS . " FROM `backup_job` WHERE $where ORDER BY `id` DESC LIMIT " . max(1, $limit), $params)->fetchAll());
    }

    /** @return Job[] queued and running jobs */
    public function active(): array
    {
        return array_map(fn(array $row): Job => Job::fromRow($row),
            $this->query("SELECT " . self::COLUMNS . " FROM `backup_job` WHERE `state` IN ('queued', 'running') ORDER BY `id`")->fetchAll());
    }

    /** @return array<string, Job> type => its newest job */
    public function latestOfEachType(): array
    {
        $jobs = [];
        foreach ($this->query("SELECT " . self::COLUMNS . " FROM `backup_job` WHERE `id` IN (SELECT MAX(`id`) FROM `backup_job` GROUP BY `type`)")->fetchAll() as $row) {
            $jobs[(string)$row['type']] = Job::fromRow($row);
        }
        return $jobs;
    }

    /** A backup job was created by the schedule since this unix time */
    public function scheduledSince(int $since): bool
    {
        return (bool)$this->query("SELECT 1 FROM `backup_job` WHERE `type` = 'backup' AND `requested_by` = 'schedule' AND `created` >= FROM_UNIXTIME(:since) LIMIT 1",
            ['since' => $since])->fetchColumn();
    }

    /**
     * Inserts the job after $check(active jobs) passed; $check throws a UserError on a conflict. A named lock keeps two
     * requests from queueing the same work at once.
     *
     * @param callable(Job[]): void $check
     * @throws UserError
     */
    public function insertExclusive(Job $job, callable $check): int
    {
        if ((int)$this->query("SELECT GET_LOCK('" . self::LOCK . "', 10)")->fetchColumn() !== 1) {
            throw new UserError('backup_job_active', [$job->type]);
        }
        try {
            $check($this->active());
            $job->id = $this->insertRow('backup_job', $job->toRow());
            return $job->id;
        } finally {
            $this->query("SELECT RELEASE_LOCK('" . self::LOCK . "')");
        }
    }

    /**
     * Cancels a queued or running job; the restic runner stops at its next check. A restore in the dovecot step
     * (mails, mails_running) cannot be cancelled. False when the job finished or reached that step meanwhile.
     */
    public function cancel(int $id): bool
    {
        return $this->query("UPDATE `backup_job` SET `state` = 'cancelled', `finished` = NOW() WHERE `id` = :id
            AND `state` IN ('queued', 'running') AND NOT (`type` = 'restore' AND COALESCE(`step`, '') IN ('mails', 'mails_running'))",
            ['id' => $id])->rowCount() > 0;
    }

    public function isDomain(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `domain` WHERE `domain` = :domain", ['domain' => $domain])->fetchColumn();
    }

    /** Domain of a mailbox, null if there is none */
    public function mailboxDomain(string $username): ?string
    {
        $domain = $this->query("SELECT `domain` FROM `mailbox` WHERE `username` = :username", ['username' => $username])->fetchColumn();
        return is_string($domain) ? strtolower($domain) : null;
    }

    /** Unix time the domain was created, null if it does not exist */
    public function domainCreated(string $domain): ?int
    {
        $created = $this->query("SELECT UNIX_TIMESTAMP(`created`) FROM `domain` WHERE `domain` = :domain", ['domain' => $domain])->fetchColumn();
        return ($created === false || $created === null) ? null : (int)$created;
    }

    /**
     * @param string[] $domains
     * @return array{0: array<string, true>, 1: array<string, array<string, true>>} the domains that exist, and per domain
     *         the usernames of its mailboxes; one query each per 500 domains
     */
    public function domainsAndUsernames(array $domains): array
    {
        $existing = [];
        $usernames = [];
        foreach (array_chunk(array_values(array_unique($domains)), 500) as $chunk) {
            $params = [];
            foreach ($chunk as $i => $domain) {
                $params['d' . $i] = $domain;
            }
            $in = implode(', ', array_map(fn(string $name): string => ':' . $name, array_keys($params)));
            foreach ($this->query("SELECT `domain` FROM `domain` WHERE `domain` IN ($in)", $params)->fetchAll(PDO::FETCH_COLUMN) as $domain) {
                $existing[strtolower((string)$domain)] = true;
            }
            foreach ($this->query("SELECT `domain`, `username` FROM `mailbox` WHERE `domain` IN ($in)", $params)->fetchAll() as $row) {
                $usernames[strtolower((string)$row['domain'])][strtolower((string)$row['username'])] = true;
            }
        }
        return [$existing, $usernames];
    }

    /** @return array<string, string> username => name of the mailboxes of a domain */
    public function mailboxNames(string $domain): array
    {
        $names = [];
        foreach ($this->query("SELECT `username`, `name` FROM `mailbox` WHERE `domain` = :domain ORDER BY `username`", ['domain' => $domain])->fetchAll() as $row) {
            $names[strtolower((string)$row['username'])] = (string)$row['name'];
        }
        return $names;
    }

    /** @return string[] all mailboxes of a domain */
    public function mailboxesOf(string $domain): array
    {
        return array_map('strtolower', $this->query("SELECT `username` FROM `mailbox` WHERE `domain` = :domain ORDER BY `username`",
            ['domain' => $domain])->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return string[] alias domains of a domain */
    public function aliasDomainsOf(string $domain): array
    {
        return array_map('strtolower', $this->query("SELECT `alias_domain` FROM `alias_domain` WHERE `target_domain` = :domain",
            ['domain' => $domain])->fetchAll(PDO::FETCH_COLUMN));
    }
}
