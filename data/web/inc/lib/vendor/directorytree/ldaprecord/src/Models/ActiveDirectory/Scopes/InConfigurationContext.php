<?php

namespace LdapRecord\Models\ActiveDirectory\Scopes;

use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class InConfigurationContext implements Scope
{
    /**
     * Refines the base dn to be inside the configuration context.
     *
     * @param Builder $query
     * @param Model   $model
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     *
     * @return void
     */
    public function apply(Builder $query, Model $model)
    {
        $query->in($this->getConfigurationNamingContext($model));
    }

    /**
     * Get the LDAP server configuration naming context distinguished name.
     *
     * @param Model $model
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     *
     * @return mixed
     */
    protected function getConfigurationNamingContext(Model $model)
    {
        return Entry::getRootDse($model->getConnectionName())
            ->getFirstAttribute('configurationNamingContext');
    }
}
