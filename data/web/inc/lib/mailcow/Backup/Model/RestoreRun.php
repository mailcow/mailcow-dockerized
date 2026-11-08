<?php

declare(strict_types=1);

namespace Mailcow\Backup\Model;

use Mailcow\Core\Context;

/**
 * The merge of one mailbox of a restore job: what the backup holds, who asked for the restore and what the merge
 * did per component (added, skipped, errors), plus the caches to refresh afterwards.
 */
class RestoreRun
{
    /** @var array<string, array{added: int, skipped: int, errors: string[]}> */
    public array $results = [];
    /** Mailboxes whose SOGo identities (aliases, send-as addresses) changed */
    public array $sogoUsers = [];
    /** Mailboxes whose userdb or passdb values changed (Dovecot auth cache) */
    public array $authUsers = [];

    /**
     * @param array $mailbox  the mailbox row as it is now
     * @param array $params   params of the restore job (requested_role, acl of a domain admin, scope, ...)
     * @param array $data     the mailbox's section of the domain export in the snapshot
     * @param string[] $domains domains whose aliases and send-as addresses the restore may change, [] = all (admins)
     * @param string $requestedBy who requested the restore
     */
    /** The restore created the mailbox (its own alias gets the forwarding of the backup) */
    public bool $created = false;

    public function __construct(
        public readonly string $username,
        public readonly string $domain,
        public readonly array $mailbox,
        public readonly array $params,
        public readonly array $data,
        public readonly array $domains = [],
        public readonly string $requestedBy = ''
    ) {
    }

    /**
     * The caller the group module checks: an admin, or the domain admin who requested the restore with his ACL of then
     * and his domains now
     */
    public function requester(): Context
    {
        if ($this->byAdmin()) {
            return new Context('admin', 'backup', [], true);
        }
        return new Context('domainadmin', $this->requestedBy, array_map(fn(mixed $flag): string => (string)(int)$flag, (array)($this->params['acl'] ?? [])));
    }

    public function start(string $component): void
    {
        $this->results[$component] = ['added' => 0, 'skipped' => 0, 'errors' => []];
    }

    public function added(string $component, int $count = 1): void
    {
        $this->results[$component]['added'] += $count;
    }

    public function skipped(string $component, int $count = 1): void
    {
        $this->results[$component]['skipped'] += $count;
    }

    public function error(string $component, string $error): void
    {
        $this->results[$component]['errors'][] = $error;
    }

    /** The part of a component was rolled back: nothing of it counts */
    public function failed(string $component, string $error): void
    {
        $this->start($component);
        $this->error($component, $error);
    }

    /** The restore was requested by an admin (domain admins are limited by their ACL and the job's domain) */
    public function byAdmin(): bool
    {
        return ($this->params['requested_role'] ?? '') === 'admin';
    }

    /** Admins, or a domain admin with this ACL when he requested the restore */
    public function may(string $acl): bool
    {
        return $this->byAdmin() || (int)($this->params['acl'][$acl] ?? 0) === 1;
    }

    public function mayChangeDomain(string $domain): bool
    {
        return $this->domains === [] || in_array(strtolower($domain), $this->domains, true);
    }

    /** Rows of a list in the backup (sieve_filters, tfa, ...) */
    public function rows(string $key): array
    {
        return array_values(array_filter((array)($this->data[$key] ?? []), 'is_array'));
    }

    public function sogo(string ...$usernames): void
    {
        array_push($this->sogoUsers, ...$usernames);
        $this->sogoUsers = array_values(array_unique($this->sogoUsers));
    }

    public function auth(string ...$usernames): void
    {
        array_push($this->authUsers, ...$usernames);
        $this->authUsers = array_values(array_unique($this->authUsers));
    }
}
