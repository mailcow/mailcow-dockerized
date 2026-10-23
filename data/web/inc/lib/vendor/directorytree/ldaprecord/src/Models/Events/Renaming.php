<?php

namespace LdapRecord\Models\Events;

use LdapRecord\Models\Model;

class Renaming extends Event
{
    /**
     * The models RDN.
     */
    protected string $rdn;

    /**
     * The models new parent DN.
     */
    protected string $newParentDn;

    /**
     * Constructor.
     */
    public function __construct(Model $model, string $rdn, string $newParentDn)
    {
        parent::__construct($model);

        $this->rdn = $rdn;
        $this->newParentDn = $newParentDn;
    }

    /**
     * Get the models RDN.
     */
    public function getRdn(): string
    {
        return $this->rdn;
    }

    /**
     * Get the models parent DN.
     */
    public function getNewParentDn(): string
    {
        return $this->newParentDn;
    }
}
