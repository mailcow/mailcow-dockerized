<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Relations\HasMany;

class Contact extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'contact',
    ];

    /**
     * The groups relationship.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'member');
    }
}
