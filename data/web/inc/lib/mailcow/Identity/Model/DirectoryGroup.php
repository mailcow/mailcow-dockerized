<?php

declare(strict_types=1);

namespace Mailcow\Identity\Model;

/**
 * A selected IdP group with the ids of its member users.
 */
class DirectoryGroup
{
    /**
     * @param ?string $mail      group address in the IdP, decides the domain of an auto-created group
     * @param string[] $memberIds ids of the member users (DirectoryUser::$id)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $mail = null,
        public array $memberIds = []
    ) {
    }
}
