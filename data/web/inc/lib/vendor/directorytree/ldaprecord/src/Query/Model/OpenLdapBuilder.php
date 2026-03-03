<?php

namespace LdapRecord\Query\Model;

class OpenLdapBuilder extends Builder
{
    /**
     * Adds a enabled filter to the current query.
     */
    public function whereEnabled(): static
    {
        return $this->rawFilter('(!(pwdAccountLockedTime=*))');
    }

    /**
     * Adds a disabled filter to the current query.
     */
    public function whereDisabled(): static
    {
        return $this->rawFilter('(pwdAccountLockedTime=*)');
    }
}
