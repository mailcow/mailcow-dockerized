<?php

namespace LdapRecord\Models\OpenLDAP;

class Group extends Entry
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'groupofuniquenames',
    ];

    /**
     * The members relationship.
     *
     * Retrieves members that are apart of the group.
     *
     * @return \LdapRecord\Models\Relations\HasMany
     */
    public function members()
    {
        return $this->hasMany([static::class, User::class], 'memberUid');
    }
}
