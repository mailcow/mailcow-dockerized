<?php

namespace LdapRecord\Models\ActiveDirectory\Scopes;

use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelNotFoundException;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class InConfigurationContext implements Scope
{
    /**
     * Refines the base dn to be inside the configuration context.
     *
     * @throws ModelNotFoundException
     */
    public function apply(Builder $query, Model $model): void
    {
        $query->in($this->getConfigurationNamingContext($model));
    }

    /**
     * Get the LDAP server configuration naming context distinguished name.
     *
     * @throws ModelNotFoundException
     */
    protected function getConfigurationNamingContext(Model $model)
    {
        return Entry::getRootDse($model->getConnectionName())
            ->getFirstAttribute('configurationNamingContext');
    }
}
