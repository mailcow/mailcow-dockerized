<?php

declare(strict_types=1);

namespace Mailcow\Identity\Model;

/**
 * What mailcow knows before a sync run, read by SyncService and planned against by SyncPlanner.
 */
class State
{
    /**
     * @param array<string, Account> $accounts           mailboxes with the provider as authsource, by username
     * @param array<string, string> $mailboxes           username => authsource of every mailbox
     * @param string[] $domains                          mailcow domains
     * @param array[] $groups                            mailbox groups {id, domain, name, idp_ref, template, priority,
     *                                                   active, members: username => source}
     * @param array<string, ?string> $effectiveTemplates username => effective group template
     * @param array<string, string> $templateHashes      template name => attribute hash
     */
    public function __construct(
        public readonly array $accounts,
        public readonly array $mailboxes = [],
        public readonly array $domains = [],
        public readonly array $groups = [],
        public readonly array $effectiveTemplates = [],
        public readonly array $templateHashes = []
    ) {
    }
}
