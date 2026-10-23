<?php

namespace LdapRecord\Models\FreeIPA;

use LdapRecord\Models\Relations\HasMany;

class User extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'person',
        'inetorgperson',
        'organizationalperson',
    ];

    /**
     * Retrieve groups that the current user is a part of.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'member');
    }
}
