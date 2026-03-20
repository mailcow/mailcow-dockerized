<?php

namespace LdapRecord\Models\DirectoryServer;

class Group extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'groupOfUniqueNames',
        'posixGroup',
    ];
}
