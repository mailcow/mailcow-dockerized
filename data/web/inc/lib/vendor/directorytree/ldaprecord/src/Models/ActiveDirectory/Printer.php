<?php

namespace LdapRecord\Models\ActiveDirectory;

class Printer extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = ['printqueue'];
}
