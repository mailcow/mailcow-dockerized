<?php

declare(strict_types=1);

namespace Mailcow\Tests\Support;

use Mailcow\Archive\Model\Mailbox;
use Mailcow\Archive\Repository\LookupRepository;

/**
 * Address tables in memory, with the conditions of the SQL already applied: only active rows are listed,
 * $mailboxes holds the mailboxes that receive mail as username => [archive setting, domain archive].
 */
class FakeArchiveLookup extends LookupRepository
{
    public array $recipientMaps = [];
    public array $aliases = [];
    public array $resources = [];
    public array $spamAliases = [];
    /** alias domain => target domain */
    public array $aliasDomains = [];
    public array $mailboxes = [];
    /** ['rcpt' => [key => dest], 'sender' => [key => dest]] */
    public array $bcc = ['rcpt' => [], 'sender' => []];
    /** @var string[] domains and alias domains of the mailcow */
    public array $domains = [];
    /** @var string[] every lookup as "<method>:<key>" */
    public array $queries = [];

    public function __construct()
    {
    }

    public function recipientMap(string $key): ?string
    {
        $this->queries[] = 'recipientMap:' . $key;
        return $this->recipientMaps[$key] ?? null;
    }

    public function aliasGoto(string $key): ?string
    {
        $this->queries[] = 'aliasGoto:' . $key;
        return $this->aliases[$key] ?? null;
    }

    public function isResource(string $username): bool
    {
        return in_array($username, $this->resources, true);
    }

    public function spamAliasGoto(string $key): ?string
    {
        return $this->spamAliases[$key] ?? null;
    }

    public function aliasDomainMailbox(string $local, string $domain): ?string
    {
        $target = $this->aliasDomains[$domain] ?? null;
        return ($target !== null && isset($this->mailboxes[$local . '@' . $target])) ? $local . '@' . $target : null;
    }

    public function bccDestination(string $key, string $type): ?string
    {
        return $this->bcc[$type][$key] ?? null;
    }

    public function isMailcowDomain(string $domain): bool
    {
        return in_array($domain, $this->domains, true);
    }

    public function mailbox(string $username): ?Mailbox
    {
        if (!isset($this->mailboxes[$username])) {
            return null;
        }
        [$archive, $domainArchive] = $this->mailboxes[$username];
        return Mailbox::fromRow(['username' => $username, 'domain' => substr($username, strrpos($username, '@') + 1),
            'archive' => $archive, 'domain_archive' => $domainArchive, 'name' => '', 'active' => 1]);
    }

    public function archivedMailboxes(): array
    {
        return array_values(array_filter(array_map(fn(string $username): Mailbox => $this->mailbox($username), array_keys($this->mailboxes)),
            fn(Mailbox $mailbox): bool => $mailbox->archived()));
    }
}
