<?php

declare(strict_types=1);

namespace Mailcow\Identity\Service;

use Mailcow\Identity\Repository\IdentityRepository;

/**
 * Links of mailboxes to IdP users, checked at IdP logins (REST, LDAP bind, OIDC). Mailboxes are found by email,
 * so the IdP user id has to match the link: an address taken over by another IdP user must not open the old
 * mailbox. Unlinked mailboxes are linked on their first login.
 */
class LinkService
{
    public function __construct(private IdentityRepository $repo)
    {
    }

    /** The IdP user may log in to the mailbox; links the mailbox when it has no link yet */
    public function verify(string $provider, string $username, string $externalId): bool
    {
        if ($externalId === '') {
            return false;
        }
        $link = $this->repo->findLink($username);
        if ($link !== null && $link['provider'] === $provider && $link['external_id'] !== null) {
            return hash_equals((string)$link['external_id'], $externalId);
        }
        if (!$this->mayCreate($provider, $username, $externalId)) {
            return false;
        }
        $this->repo->link($username, $provider, $externalId);
        return true;
    }

    /** The IdP user owns no other mailbox (checked before a mailbox is created on login) */
    public function mayCreate(string $provider, string $username, string $externalId): bool
    {
        $owner = ($externalId === '') ? null : $this->repo->usernameOf($provider, $externalId);
        return $externalId !== '' && ($owner === null || $owner === $username);
    }

    /** Links a mailbox created on login */
    public function link(string $provider, string $username, string $externalId): void
    {
        if ($externalId !== '') {
            $this->repo->link($username, $provider, $externalId);
        }
    }
}
