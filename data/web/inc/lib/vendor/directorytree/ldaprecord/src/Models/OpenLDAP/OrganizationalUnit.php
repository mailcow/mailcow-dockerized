<?php

namespace LdapRecord\Models\OpenLDAP;

class OrganizationalUnit extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'organizationalunit',
    ];

    /**
     * Get the creatable RDN attribute name.
     */
    public function getCreatableRdnAttribute(): string
    {
        return 'ou';
    }
}
