<?php

declare(strict_types=1);

namespace Mailcow\Backup\Model;

/**
 * A snapshot of the repository (table `backup_snapshot`, the runner's cache of `restic snapshots --json`).
 */
class Snapshot
{
    public string $id = '';
    public string $shortId = '';
    /** unix timestamp */
    public int $time = 0;
    public string $hostname = '';
    /** @var string[] */
    public array $tags = [];
    /** restic's summary of the backup (data_added, files_new, ...) */
    public ?array $summary = null;
    /** @var ?string[] domains of its manifest, null for snapshots not made by mailcow */
    public ?array $domains = null;
    /** @var array<string, ?int> domain => its creation time (unix) when the snapshot was made, from the snapshot row */
    public array $created = [];
    /**
     * @var ?array<string, array{created: ?int, mailboxes: array<string, string>}> per domain its creation time (unix, null
     *      when not recorded) and username => name of its mailboxes; null = not known (snapshots this installation did
     *      not make itself, e.g. of another server in the same repository)
     */
    public ?array $content = null;

    /** Row of `backup_snapshot` with the time as unix timestamp */
    public static function fromRow(array $row): self
    {
        $snapshot = new self();
        $snapshot->id = (string)$row['id'];
        $snapshot->shortId = (string)$row['short_id'];
        $snapshot->time = (int)$row['time'];
        $snapshot->hostname = (string)($row['hostname'] ?? '');
        $tags = (string)($row['tags'] ?? '');
        $decoded = json_decode($tags, true);
        $tags = is_array($decoded) ? $decoded : explode(',', $tags);
        $snapshot->tags = array_values(array_filter(array_map(fn(mixed $tag): string => is_scalar($tag) ? trim((string)$tag) : '', $tags), 'strlen'));
        $summary = is_string($row['summary'] ?? null) ? json_decode($row['summary'], true) : null;
        $snapshot->summary = is_array($summary) ? $summary : null;
        // {domain: creation time}: lists of snapshots need no content
        $domains = is_string($row['domains'] ?? null) ? json_decode($row['domains'], true) : null;
        if (is_array($domains)) {
            $snapshot->domains = [];
            foreach ($domains as $domain => $created) {
                $snapshot->domains[] = (string)$domain;
                $snapshot->created[strtolower((string)$domain)] = is_numeric($created) ? (int)$created : null;
            }
        }
        if (is_string($row['content'] ?? null) && $row['content'] !== '') {
            $snapshot->content = self::decodeContent($row['content']);
        }
        return $snapshot;
    }

    /**
     * `content` (gzip JSON) written by the runner: {"domains": {"<domain>": {"created": <unix>, "mailboxes": {"<username>": "<name>"}}}}.
     * Keys lower case; null when broken.
     */
    public static function decodeContent(string $gzip): ?array
    {
        $json = @gzdecode($gzip);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) || !is_array($data['domains'] ?? null)) {
            return null;
        }
        $content = [];
        foreach ($data['domains'] as $domain => $entry) {
            $mailboxes = is_array($entry) ? ($entry['mailboxes'] ?? []) : [];
            $created = is_numeric($entry['created'] ?? null) ? (int)$entry['created'] : null;
            $names = [];
            foreach (is_array($mailboxes) ? $mailboxes : [] as $username => $name) {
                $names[strtolower((string)$username)] = is_scalar($name) ? (string)$name : '';
            }
            $content[strtolower((string)$domain)] = ['created' => $created, 'mailboxes' => $names];
        }
        return $content;
    }

    /** Domains of the snapshot: those of its content and of its manifest (an export may have failed) */
    public function domainList(): array
    {
        return array_values(array_unique(array_merge(array_map('strval', array_keys($this->content ?? [])), array_map('strtolower', $this->domains ?? []))));
    }

    /** @return ?array<string, string> username => name of the domain's mailboxes, null when the content does not list the domain */
    public function mailboxesOf(string $domain): ?array
    {
        return $this->content[strtolower($domain)]['mailboxes'] ?? null;
    }

    /** Creation time of the domain when the snapshot was made, null when not recorded */
    public function createdOf(string $domain): ?int
    {
        return $this->content[strtolower($domain)]['created'] ?? $this->created[strtolower($domain)] ?? null;
    }

    public function hasDomain(string $domain): bool
    {
        return in_array(strtolower($domain), $this->domainList(), true);
    }

    /**
     * RESTIC_BACKUP_MODE of the backup (tag mode:<mode>, none for all): all, data (without the mailcow directory) or
     * config (without mails)
     */
    public function mode(): string
    {
        foreach ($this->tags as $tag) {
            if (str_starts_with($tag, 'mode:')) {
                return substr($tag, 5);
            }
        }
        return 'all';
    }

    /** Snapshots not made by mailcow know neither domains nor mailboxes */
    public function knowsDomains(): bool
    {
        return $this->content !== null || $this->domains !== null;
    }

    /** Entry of the API for admins */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'short_id' => $this->shortId,
            'time' => $this->time,
            'hostname' => $this->hostname,
            'tags' => $this->tags,
            'summary' => $this->summary,
            'domains' => $this->domains,
            'mode' => $this->mode(),
        ];
    }
}
