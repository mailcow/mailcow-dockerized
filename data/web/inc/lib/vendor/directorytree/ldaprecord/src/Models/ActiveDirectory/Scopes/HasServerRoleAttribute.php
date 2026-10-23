<?php

namespace LdapRecord\Models\ActiveDirectory\Scopes;

use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class HasServerRoleAttribute implements Scope
{
    /**
     * Includes condition of having a serverRole attribute.
     */
    public function apply(Builder $query, Model $model): void
    {
        $query->whereHas('serverRole');
    }
}
