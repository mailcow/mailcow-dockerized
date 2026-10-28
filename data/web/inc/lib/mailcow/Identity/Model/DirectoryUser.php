<?php

declare(strict_types=1);

namespace Mailcow\Identity\Model;

/**
 * A user as listed by the IdP. `id` is stable across renames (Keycloak user id, LDAP objectGUID/entryUUID/...).
 */
class DirectoryUser
{
    /**
     * @param ?string $email   lowercase, null if the user has none
     * @param string[] $templateValues values of the template attribute (legacy mapping)
     * @param string $dn       LDAP only, lowercase, used to resolve group members
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $email,
        public readonly string $name,
        public readonly bool $enabled,
        public readonly array $templateValues = [],
        public readonly string $dn = ''
    ) {
    }
}
