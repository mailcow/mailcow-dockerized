<?php

namespace LdapRecord\Models;

use LdapRecord\Query\Model\Builder;

interface Scope
{
    /**
     * Apply the scope to the given query.
     */
    public function apply(Builder $query, Model $model): void;
}
