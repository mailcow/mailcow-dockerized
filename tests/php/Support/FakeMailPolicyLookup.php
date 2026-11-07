<?php

declare(strict_types=1);

namespace Mailcow\Tests\Support;

use Mailcow\MailPolicy\Model\Policy;
use Mailcow\MailPolicy\Repository\LookupRepository;

/**
 * Address tables and policy assignments in memory, with the conditions of the SQL already applied
 * (only active rows are listed).
 */
class FakeMailPolicyLookup extends LookupRepository
{
    /** username => domain */
    public array $mailboxes = [];
    /** alias domain => target domain */
    public array $aliasDomains = [];
    /** @var string[] domains of the mailcow (without alias domains) */
    public array $domains = [];
    /** id => ['address' => ?string, 'members' => string[]] */
    public array $groups = [];
    /** alias address => domain of aliases with the internal flag */
    public array $internalAliases = [];
    /** username => Policy[] assigned directly */
    public array $mailboxPolicies = [];
    /** group id => Policy[] */
    public array $groupPolicies = [];

    public function __construct()
    {
    }

    public function mailbox(string $username): ?array
    {
        return isset($this->mailboxes[$username]) ? ['username' => $username, 'domain' => $this->mailboxes[$username]] : null;
    }

    public function aliasDomainTarget(string $domain): ?string
    {
        return $this->aliasDomains[$domain] ?? null;
    }

    public function isLocalDomain(string $domain): bool
    {
        return in_array($domain, $this->domains, true) || isset($this->aliasDomains[$domain]);
    }

    public function groupByAddress(string $address): ?int
    {
        foreach ($this->groups as $id => $group) {
            if (($group['address'] ?? null) === $address) {
                return $id;
            }
        }
        return null;
    }

    public function groupAddress(int $id): ?string
    {
        return $this->groups[$id]['address'] ?? null;
    }

    public function isGroupMember(int $groupId, string $username): bool
    {
        return in_array($username, $this->groups[$groupId]['members'] ?? [], true);
    }

    public function internalAlias(string $address): ?string
    {
        return $this->internalAliases[$address] ?? null;
    }

    public function policiesForMailbox(string $username): array
    {
        $policies = $this->mailboxPolicies[$username] ?? [];
        foreach ($this->groups as $id => $group) {
            if (in_array($username, $group['members'] ?? [], true)) {
                $policies = array_merge($policies, $this->groupPolicies[$id] ?? []);
            }
        }
        return $policies;
    }

    public function policiesForGroup(int $groupId): array
    {
        return $this->groupPolicies[$groupId] ?? [];
    }
}
