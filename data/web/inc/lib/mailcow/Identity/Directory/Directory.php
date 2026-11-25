<?php

declare(strict_types=1);

namespace Mailcow\Identity\Directory;

use Mailcow\Identity\Model\Listing;

/**
 * Lists users (and groups) of an IdP for the sync. Implementations: KeycloakDirectory, LdapDirectory.
 */
interface Directory
{
    /**
     * The complete listing. Anything that could make it incomplete (HTTP or LDAP errors, size limits, ranged
     * attributes) throws, because the sync treats users that are not listed as removed.
     *
     * @throws DirectoryException
     */
    public function listAll(bool $withGroups): Listing;
}
