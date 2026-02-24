<?php

namespace LdapRecord\Models\FreeIPA;

use LdapRecord\Models\Relations\HasMany;

class Group extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'groupofnames',
        'nestedgroup',
        'ipausergroup',
        'posixgroup',
    ];

    /**
     * The groups relationship.
     *
     * Retrieves groups that the current group is a part of.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(self::class, 'member');
    }

    /**
     * Retrieve the members of the group.
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'memberof')->using($this, 'member');
    }
}
