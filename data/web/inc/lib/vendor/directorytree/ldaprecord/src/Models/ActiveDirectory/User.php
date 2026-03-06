<?php

namespace LdapRecord\Models\ActiveDirectory;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\ActiveDirectory\Concerns\HasAccountControl;
use LdapRecord\Models\ActiveDirectory\Concerns\HasPrimaryGroup;
use LdapRecord\Models\ActiveDirectory\Scopes\RejectComputerObjectClass;
use LdapRecord\Models\Concerns\CanAuthenticate;
use LdapRecord\Models\Concerns\HasPassword;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Models\Relations\HasOne;
use LdapRecord\Query\Model\Builder;

class User extends Entry implements Authenticatable
{
    use CanAuthenticate;
    use HasAccountControl;
    use HasPassword;
    use HasPrimaryGroup;

    /**
     * The password's attribute name.
     */
    protected string $passwordAttribute = 'unicodepwd';

    /**
     * The password's hash method.
     */
    protected string $passwordHashMethod = 'encode';

    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'user',
    ];

    /**
     * The attributes that should be mutated to dates.
     */
    protected array $dates = [
        'lastlogon' => 'windows-int',
        'lastlogoff' => 'windows-int',
        'pwdlastset' => 'windows-int',
        'lockouttime' => 'windows-int',
        'accountexpires' => 'windows-int',
        'badpasswordtime' => 'windows-int',
        'lastlogontimestamp' => 'windows-int',
    ];

    /**
     * {@inheritdoc}
     */
    protected static function boot(): void
    {
        parent::boot();

        // Here we will add a global scope to reject the 'computer' object
        // class. This is needed due to computer objects containing all
        // of the ActiveDirectory 'user' object classes. Without
        // this scope, they would be included in results.
        static::addGlobalScope(new RejectComputerObjectClass);
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): ?string
    {
        return $this->getConvertedGuid();
    }

    /**
     * The groups relationship.
     *
     * Retrieves groups that the user is a part of.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'member')->with($this->primaryGroup());
    }

    /**
     * The manager relationship.
     *
     * Retrieves the manager of the user.
     */
    public function manager(): HasOne
    {
        return $this->hasOne(static::class, 'manager');
    }

    /**
     * The primary group relationship of the current user.
     *
     * Retrieves the primary group the user is a part of.
     */
    public function primaryGroup(): HasOne
    {
        return $this->hasOnePrimaryGroup(Group::class, 'primarygroupid');
    }

    /**
     * Scopes the query to exchange mailbox users.
     */
    public function scopeWhereHasMailbox(Builder $query): Builder
    {
        return $query->whereHas('msExchMailboxGuid');
    }

    /**
     * Scopes the query to users having a lockout value set.
     */
    public function scopeWhereHasLockout(Builder $query): Builder
    {
        return $query->where('lockoutTime', '>=', 1);
    }

    /**
     * Determine if the user is locked out using the domains LockoutDuration group policy value.
     *
     * @see https://ldapwiki.com/wiki/Active%20Directory%20Account%20Lockout
     * @see https://docs.microsoft.com/en-us/windows/security/threat-protection/security-policy-settings/account-lockout-duration
     */
    public function isLockedOut(string|int $localTimezone, ?int $durationInMinutes = null): bool
    {
        $time = $this->getFirstAttribute('lockouttime');

        if (! $time instanceof Carbon) {
            return false;
        }

        is_int($localTimezone)
            ? $time->addMinutes($localTimezone)
            : $time->setTimezone($localTimezone)->addMinutes($durationInMinutes ?: 0);

        return ! $time->isPast();
    }
}
