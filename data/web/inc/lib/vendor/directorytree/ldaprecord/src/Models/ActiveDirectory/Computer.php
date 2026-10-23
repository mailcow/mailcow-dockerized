<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\ActiveDirectory\Concerns\HasAccountControl;
use LdapRecord\Models\ActiveDirectory\Concerns\HasPrimaryGroup;
use LdapRecord\Models\ActiveDirectory\Relations\HasOnePrimaryGroup;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Models\Relations\HasOne;

class Computer extends Entry
{
    use HasAccountControl;
    use HasPrimaryGroup;

    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'user',
        'computer',
    ];

    /**
     * The groups relationship.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'member')->with($this->primaryGroup());
    }

    /**
     * The primary group relationship.
     */
    public function primaryGroup(): HasOnePrimaryGroup
    {
        return $this->hasOnePrimaryGroup(Group::class, 'primarygroupid');
    }

    /**
     * The managed by relationship.
     */
    public function managedBy(): HasOne
    {
        return $this->hasOne([Contact::class, Group::class, User::class], 'managedby');
    }
}
