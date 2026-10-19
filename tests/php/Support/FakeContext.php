<?php

declare(strict_types=1);

namespace Mailcow\Tests\Support;

use Mailcow\Core\Context;

/**
 * Context with a fixed ACL world instead of the legacy hasMailboxObjectAccess()/hasDomainAccess() bridge:
 * like the legacy functions, the own mailbox is always accessible for a logged-in role.
 */
class FakeContext extends Context
{
    private array $mailboxes;
    private array $domains;

    public function __construct(string $role, ?string $username, array $acl = ['syncjobs' => '1'], bool $accessAll = false,
        array $mailboxes = [], array $domains = [])
    {
        parent::__construct($role, $username, $acl, $accessAll);
        $this->mailboxes = $mailboxes;
        $this->domains = $domains;
    }

    public static function admin(array $acl = ['syncjobs' => '1']): self
    {
        return new self('admin', 'admin', $acl, false, ['*'], ['*']);
    }

    public static function domainAdmin(array $mailboxes = [], array $domains = [], array $acl = ['syncjobs' => '1']): self
    {
        return new self('domainadmin', 'da', $acl, false, $mailboxes, $domains);
    }

    public static function user(string $username = 'user@example.org', array $acl = ['syncjobs' => '1']): self
    {
        return new self('user', $username, $acl);
    }

    public function canAccessMailbox(string $mailbox): bool
    {
        if ($this->accessAll) {
            return true;
        }
        if (!in_array($this->role, ['admin', 'domainadmin', 'user'], true) || $this->username === null) {
            return false;
        }
        return $mailbox === $this->username || in_array($mailbox, $this->mailboxes, true) || in_array('*', $this->mailboxes, true);
    }

    public function canAccessDomain(string $domain): bool
    {
        return $this->accessAll || in_array($domain, $this->domains, true) || in_array('*', $this->domains, true);
    }
}
