<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Relations\HasMany;

class ForeignSecurityPrincipal extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'foreignsecurityprincipal',
    ];

    /**
     * The groups relationship.
     *
     * Retrieves groups that the current security principal is a part of.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'member');
    }
}
