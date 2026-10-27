<?php

declare(strict_types=1);

namespace Mailcow\Identity\Model;

/**
 * A complete listing of the IdP. Directories never return partial listings, they throw DirectoryException,
 * so a user missing here is really gone (or out of the filter).
 */
class Listing
{
    /**
     * @param array<string, DirectoryUser> $users by id
     * @param ?array<string, DirectoryGroup> $groups by id, null when groups were not listed
     */
    public function __construct(public readonly array $users, public readonly ?array $groups = null)
    {
    }
}
