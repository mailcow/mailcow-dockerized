<?php

namespace LdapRecord\Models\DirectoryServer;

use LdapRecord\Models\Model;
use LdapRecord\Models\Types\DirectoryServer;

class Entry extends Model implements DirectoryServer
{
    /**
     * The attribute key that contains the models object GUID.
     */
    protected string $guidKey = 'gidNumber';
}
