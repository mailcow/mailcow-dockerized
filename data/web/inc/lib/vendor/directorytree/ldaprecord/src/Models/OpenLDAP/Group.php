<?php

namespace LdapRecord\Models\OpenLDAP;

use LdapRecord\Models\Relations\HasManyIn;

class Group extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'groupofuniquenames',
    ];

    /**
     * The members relationship.
     */
    public function members(): HasManyIn
    {
        return $this->hasManyIn([static::class, User::class], 'uniquemember')->using($this, 'uniquemember');
    }
}
