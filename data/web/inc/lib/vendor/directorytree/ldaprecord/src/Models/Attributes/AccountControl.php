<?php

namespace LdapRecord\Models\Attributes;

use ReflectionClass;
use Stringable;

class AccountControl implements Stringable
{
    public const SCRIPT = 1;

    public const ACCOUNTDISABLE = 2;

    public const HOMEDIR_REQUIRED = 8;

    public const LOCKOUT = 16;

    public const PASSWD_NOTREQD = 32;

    public const PASSWD_CANT_CHANGE = 64;

    public const ENCRYPTED_TEXT_PWD_ALLOWED = 128;

    public const TEMP_DUPLICATE_ACCOUNT = 256;

    public const NORMAL_ACCOUNT = 512;

    public const INTERDOMAIN_TRUST_ACCOUNT = 2048;

    public const WORKSTATION_TRUST_ACCOUNT = 4096;

    public const SERVER_TRUST_ACCOUNT = 8192;

    public const DONT_EXPIRE_PASSWORD = 65536;

    public const MNS_LOGON_ACCOUNT = 131072;

    public const SMARTCARD_REQUIRED = 262144;

    public const TRUSTED_FOR_DELEGATION = 524288;

    public const NOT_DELEGATED = 1048576;

    public const USE_DES_KEY_ONLY = 2097152;

    public const DONT_REQ_PREAUTH = 4194304;

    public const PASSWORD_EXPIRED = 8388608;

    public const TRUSTED_TO_AUTH_FOR_DELEGATION = 16777216;

    public const PARTIAL_SECRETS_ACCOUNT = 67108864;

    /**
     * The account control flags.
     *
     * @var array<int, int>
     */
    protected array $flags = [];

    /**
     * Constructor.
     */
    public function __construct(?int $flag = null)
    {
        if (! is_null($flag)) {
            $this->applyFlags($flag);
        }
    }

    /**
     * Get the value when cast to string.
     */
    public function __toString(): string
    {
        return (string) $this->getValue();
    }

    /**
     * Get the value when cast to int.
     */
    public function __toInt(): int
    {
        return $this->getValue();
    }

    /**
     * Set a flag on the account control.
     */
    public function setFlag(int $flag): static
    {
        // Use the value as a key so if the same value
        // is used, it will always be overwritten
        $this->flags[$flag] = $flag;

        return $this;
    }

    /**
     * Unset a flag from the account control.
     */
    public function unsetFlag(int $flag): static
    {
        unset($this->flags[$flag]);

        return $this;
    }

    /**
     * Extract and apply several flags.
     */
    public function applyFlags(int $flags): void
    {
        $this->setFlags($this->extractFlags($flags));
    }

    /**
     * Determine if the account control contains the given flag(s).
     */
    public function hasFlag(int $flag): bool
    {
        // Here we will extract the given flag into an array
        // of possible flags. This will allow us to see if
        // our AccountControl object contains any of them.
        $flagsUsed = array_intersect(
            $this->extractFlags($flag),
            $this->flags
        );

        return in_array($flag, $flagsUsed);
    }

    /**
     * Determine if the account control does not contain the given flag(s).
     */
    public function doesntHaveFlag(int $flag): bool
    {
        return ! $this->hasFlag($flag);
    }

    /**
     * Generate an LDAP filter based on the current value.
     */
    public function filter(): string
    {
        return sprintf('(UserAccountControl:1.2.840.113556.1.4.803:=%s)', $this);
    }

    /**
     * The logon script will be run.
     */
    public function setRunLoginScript(): static
    {
        return $this->setFlag(static::SCRIPT);
    }

    /**
     * The user account is locked.
     */
    public function setAccountIsLocked(): static
    {
        return $this->setFlag(static::LOCKOUT);
    }

    /**
     * The user account is disabled.
     */
    public function setAccountIsDisabled(): static
    {
        return $this->setFlag(static::ACCOUNTDISABLE);
    }

    /**
     * This is an account for users whose primary account is in another domain.
     *
     * This account provides user access to this domain, but not to any domain that
     * trusts this domain. This is sometimes referred to as a local user account.
     */
    public function setAccountIsTemporary(): static
    {
        return $this->setFlag(static::TEMP_DUPLICATE_ACCOUNT);
    }

    /**
     * This is a default account type that represents a typical user.
     */
    public function setAccountIsNormal(): static
    {
        return $this->setFlag(static::NORMAL_ACCOUNT);
    }

    /**
     * This is a permit to trust an account for a system domain that trusts other domains.
     */
    public function setAccountIsForInterdomain(): static
    {
        return $this->setFlag(static::INTERDOMAIN_TRUST_ACCOUNT);
    }

    /**
     * This is a computer account for a computer that is running Microsoft
     * Windows NT 4.0 Workstation, Microsoft Windows NT 4.0 Server, Microsoft
     * Windows 2000 Professional, or Windows 2000 Server and is a member of this domain.
     */
    public function setAccountIsForWorkstation(): static
    {
        return $this->setFlag(static::WORKSTATION_TRUST_ACCOUNT);
    }

    /**
     * This is a computer account for a domain controller that is a member of this domain.
     */
    public function setAccountIsForServer(): static
    {
        return $this->setFlag(static::SERVER_TRUST_ACCOUNT);
    }

    /**
     * This is an MNS logon account.
     */
    public function setAccountIsMnsLogon(): static
    {
        return $this->setFlag(static::MNS_LOGON_ACCOUNT);
    }

    /**
     * (Windows 2000/Windows Server 2003) This account does
     * not require Kerberos pre-authentication for logging on.
     */
    public function setAccountDoesNotRequirePreAuth(): static
    {
        return $this->setFlag(static::DONT_REQ_PREAUTH);
    }

    /**
     * When this flag is set, it forces the user to log on by using a smart card.
     */
    public function setAccountRequiresSmartCard(): static
    {
        return $this->setFlag(static::SMARTCARD_REQUIRED);
    }

    /**
     * (Windows Server 2008/Windows Server 2008 R2) The account is a read-only domain controller (RODC).
     *
     * This is a security-sensitive setting. Removing this setting from an RODC compromises security on that server.
     */
    public function setAccountIsReadOnly(): static
    {
        return $this->setFlag(static::PARTIAL_SECRETS_ACCOUNT);
    }

    /**
     * The home folder is required.
     */
    public function setHomeFolderIsRequired(): static
    {
        return $this->setFlag(static::HOMEDIR_REQUIRED);
    }

    /**
     * No password is required.
     */
    public function setPasswordIsNotRequired(): static
    {
        return $this->setFlag(static::PASSWD_NOTREQD);
    }

    /**
     * The user cannot change the password. This is a permission on the user's object.
     *
     * For information about how to programmatically set this permission, visit the following link:
     *
     * @see http://msdn2.microsoft.com/en-us/library/aa746398.aspx
     */
    public function setPasswordCannotBeChanged(): static
    {
        return $this->setFlag(static::PASSWD_CANT_CHANGE);
    }

    /**
     * Represents the password, which should never expire on the account.
     */
    public function setPasswordDoesNotExpire(): static
    {
        return $this->setFlag(static::DONT_EXPIRE_PASSWORD);
    }

    /**
     * (Windows 2000/Windows Server 2003) The user's password has expired.
     */
    public function setPasswordIsExpired(): static
    {
        return $this->setFlag(static::PASSWORD_EXPIRED);
    }

    /**
     * The user can send an encrypted password.
     */
    public function setAllowEncryptedTextPassword(): static
    {
        return $this->setFlag(static::ENCRYPTED_TEXT_PWD_ALLOWED);
    }

    /**
     * When this flag is set, the service account (the user or computer account)
     * under which a service runs is trusted for Kerberos delegation.
     *
     * Any such service can impersonate a client requesting the service.
     *
     * To enable a service for Kerberos delegation, you must set this
     * flag on the userAccountControl property of the service account.
     */
    public function setTrustForDelegation(): static
    {
        return $this->setFlag(static::TRUSTED_FOR_DELEGATION);
    }

    /**
     * (Windows 2000/Windows Server 2003) The account is enabled for delegation.
     *
     * This is a security-sensitive setting. Accounts that have this option enabled
     * should be tightly controlled. This setting lets a service that runs under the
     * account assume a client's identity and authenticate as that user to other remote
     * servers on the network.
     */
    public function setTrustToAuthForDelegation(): static
    {
        return $this->setFlag(static::TRUSTED_TO_AUTH_FOR_DELEGATION);
    }

    /**
     * When this flag is set, the security context of the user is not delegated to a
     * service even if the service account is set as trusted for Kerberos delegation.
     */
    public function setDoNotTrustForDelegation(): static
    {
        return $this->setFlag(static::NOT_DELEGATED);
    }

    /**
     * (Windows 2000/Windows Server 2003) Restrict this principal to
     * use only Data Encryption Standard (DES) encryption types for keys.
     */
    public function setUseDesKeyOnly(): static
    {
        return $this->setFlag(static::USE_DES_KEY_ONLY);
    }

    /**
     * Get the account control value.
     */
    public function getValue(): int
    {
        return array_sum($this->flags);
    }

    /**
     * Get the account control flag values.
     *
     * @return array<int, int>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Set the account control values.
     *
     * @param  array<int, int>  $flags
     */
    public function setFlags(array $flags): void
    {
        $this->flags = $flags;
    }

    /**
     * Get all flags that are currently applied to the value.
     */
    public function getAppliedFlags(): array
    {
        $flags = $this->getAllFlags();

        $applied = [];

        foreach ($flags as $name => $flag) {
            if ($this->hasFlag($flag)) {
                $applied[$name] = $flag;
            }
        }

        return $applied;
    }

    /**
     * Get all possible account control flags.
     */
    public function getAllFlags(): array
    {
        return (new ReflectionClass(__CLASS__))->getConstants();
    }

    /**
     * Extracts the given flag into an array of flags used.
     */
    protected function extractFlags(int $flag): array
    {
        $flags = [];

        for ($i = 0; $i <= 26; $i++) {
            if ($flag & (1 << $i)) {
                $flags[1 << $i] = 1 << $i;
            }
        }

        return $flags;
    }
}
