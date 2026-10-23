<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Query\Model\Builder;

/** @mixin \LdapRecord\Models\Model */
trait HasScopes
{
    /**
     * Begin querying the direct descendants of the model.
     */
    public function descendants(): Builder
    {
        return $this->in($this->getDn())->list();
    }

    /**
     * Begin querying the direct ancestors of the model.
     */
    public function ancestors(): Builder
    {
        $parent = $this->getParentDn($this->getDn());

        return $this->in($this->getParentDn($parent))->list();
    }

    /**
     * Begin querying the direct siblings of the model.
     */
    public function siblings(): Builder
    {
        return $this->in($this->getParentDn($this->getDn()))->list();
    }
}
