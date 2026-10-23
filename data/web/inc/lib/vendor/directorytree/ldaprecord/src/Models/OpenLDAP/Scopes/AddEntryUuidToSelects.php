<?php

namespace LdapRecord\Models\OpenLDAP\Scopes;

use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class AddEntryUuidToSelects implements Scope
{
    /**
     * Add the entry UUID to the selected attributes.
     */
    public function apply(Builder $query, Model $model): void
    {
        empty($query->columns)
            ? $query->addSelect(['*', $model->getGuidKey()])
            : $query->addSelect($model->getGuidKey());
    }
}
