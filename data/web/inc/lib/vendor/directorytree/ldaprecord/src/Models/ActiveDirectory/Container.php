<?php

namespace LdapRecord\Models\ActiveDirectory;

class Container extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'container',
    ];
}
