<?php

namespace LdapRecord\Models\ActiveDirectory\Concerns;

use LdapRecord\Models\ActiveDirectory\Relations\HasOnePrimaryGroup;

trait HasPrimaryGroup
{
    /**
     * Returns a new has one primary group relationship.
     */
    public function hasOnePrimaryGroup(string $related, string $relationKey, string $foreignKey = 'primarygroupid'): HasOnePrimaryGroup
    {
        return new HasOnePrimaryGroup($this->newQuery(), $this, $related, $relationKey, $foreignKey);
    }
}
