<?php

declare(strict_types=1);

namespace Mailcow\Identity\Model;

/**
 * A mailbox with the authsource of the configured IdP and its link (table `identity_link`).
 */
class Account
{
    /**
     * @param int $active       mailbox.active: 1 active, 2 login disabled (still receives mail), 0 inactive
     * @param bool $idpDisabled the sync set active = 2, so it may undo it
     * @param ?string $missingSince first complete run that did not list the user
     */
    public function __construct(
        public readonly string $username,
        public readonly string $name,
        public readonly int $active,
        public readonly ?string $externalId = null,
        public readonly bool $idpDisabled = false,
        public readonly ?string $missingSince = null,
        public readonly string $attributeHash = ''
    ) {
    }
}
