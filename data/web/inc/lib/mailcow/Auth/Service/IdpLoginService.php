<?php

declare(strict_types=1);

namespace Mailcow\Auth\Service;

use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Service\LinkService;
use Mailcow\MailboxGroup\Service\TemplateService;
use Throwable;

/**
 * Steps of an IdP login (Keycloak REST, LDAP bind, OIDC) the legacy login flows call. The link checks fail
 * closed: an error denies the login.
 */
class IdpLoginService
{
    public function __construct(private Settings $settings, private LinkService $links, private TemplateService $templates)
    {
    }

    /** Template of the first attribute mapping matching one of the IdP attribute values, null if none */
    public function mappedTemplate(mixed $values): ?string
    {
        return $this->settings->mappedTemplate((array)$values);
    }

    /** Template for the login of an existing mailbox: its effective group template, else the mapped one */
    public function templateFor(string $username, ?string $mapped): ?string
    {
        try {
            return $this->templates->templateForLogin($username, $mapped);
        } catch (Throwable $e) {
            error_log('mailcow: group template lookup failed for ' . $username . ': ' . $e->getMessage());
            return $mapped;
        }
    }

    /** The IdP user may open the mailbox, an unlinked mailbox gets linked */
    public function verifyLink(string $provider, string $username, string $externalId): bool
    {
        try {
            return $this->links->verify($provider, $username, $externalId);
        } catch (Throwable $e) {
            error_log('mailcow: IdP link check failed for ' . $username . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Before a mailbox is created on login: the IdP user must not own another mailbox */
    public function mayCreate(string $provider, string $username, string $externalId): bool
    {
        try {
            return $this->links->mayCreate($provider, $username, $externalId);
        } catch (Throwable $e) {
            error_log('mailcow: IdP link check failed for ' . $username . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Links a mailbox created on login */
    public function storeLink(string $provider, string $username, string $externalId): void
    {
        try {
            $this->links->link($provider, $username, $externalId);
        } catch (Throwable $e) {
            error_log('mailcow: IdP link could not be stored for ' . $username . ': ' . $e->getMessage());
        }
    }
}
