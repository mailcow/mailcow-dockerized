<?php

declare(strict_types=1);

namespace Mailcow\Core;

/**
 * Who is calling: role, username, ACL flags and the access-all exception of the session.
 *
 * Services get a Context instead of reading $_SESSION. Build it once per request in the entry point:
 * `Context::fromSession($_SESSION)` for web requests, `Context::system()` for cron/CLI code paths
 * that do no permission checks.
 */
class Context
{
    /**
     * @param array $acl      ACL flags of the session (user_acl / da_acl, always set for admins)
     * @param bool $accessAll the session may ignore object ownership (`access_all_exception`)
     */
    public function __construct(
        public readonly string $role,
        public readonly ?string $username,
        private readonly array $acl = [],
        public readonly bool $accessAll = false
    ) {
    }

    public static function fromSession(array $session): self
    {
        $username = $session['mailcow_cc_username'] ?? null;
        return new self(
            (string)($session['mailcow_cc_role'] ?? ''),
            is_scalar($username) ? (string)$username : null,
            is_array($session['acl'] ?? null) ? $session['acl'] : [],
            ($session['access_all_exception'] ?? null) == '1'
        );
    }

    /** Caller for cron and CLI code: not a logged-in account, so every permission check fails */
    public static function system(): self
    {
        return new self('system', null);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDomainAdmin(): bool
    {
        return $this->role === 'domainadmin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function hasAcl(string $name): bool
    {
        return isset($this->acl[$name]) && $this->acl[$name] == '1';
    }

    /** Bridge to the legacy hasMailboxObjectAccess() until functions.inc.php is converted */
    public function canAccessMailbox(string $mailbox): bool
    {
        return (bool)hasMailboxObjectAccess($this->username, $this->role, $mailbox);
    }

    /** Bridge to the legacy hasDomainAccess() until functions.inc.php is converted */
    public function canAccessDomain(string $domain): bool
    {
        return (bool)hasDomainAccess($this->username, $this->role, $domain);
    }
}
