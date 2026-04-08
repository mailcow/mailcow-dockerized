<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\ConnectionException;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\Password;

/** @mixin \LdapRecord\Models\Model */
trait HasPassword
{
    /**
     * Set the password on the user.
     *
     * @throws ConnectionException
     */
    public function setPasswordAttribute(array|string $password): void
    {
        $this->assertSecureConnection();

        // Here we will attempt to determine the password hash method in use
        // by parsing the users hashed password (if it as available). If a
        // method is determined, we will override the default here.
        if (! ($method = $this->determinePasswordHashMethod())) {
            $method = $this->getPasswordHashMethod();
        }

        // If the password given is an array, we can assume we
        // are changing the password for the current user.
        if (is_array($password)) {
            $this->setChangedPassword(
                $this->getHashedPassword($method, $password[0], $this->getPasswordSalt($method)),
                $this->getHashedPassword($method, $password[1]),
                $this->getPasswordAttributeName()
            );
        }
        // Otherwise, we will assume the password is being
        // reset, overwriting the one currently in place.
        else {
            $this->setPassword(
                $this->getHashedPassword($method, $password),
                $this->getPasswordAttributeName()
            );
        }
    }

    /**
     * Alias for setting the password on the user.
     *
     * @throws ConnectionException
     */
    public function setUnicodepwdAttribute(array|string $password): void
    {
        $this->setPasswordAttribute($password);
    }

    /**
     * An accessor for retrieving the user's hashed password value.
     */
    public function getPasswordAttribute(): ?string
    {
        return $this->getAttribute($this->getPasswordAttributeName())[0] ?? null;
    }

    /**
     * Get the name of the attribute that contains the user's password.
     */
    public function getPasswordAttributeName(): string
    {
        if (property_exists($this, 'passwordAttribute')) {
            return $this->passwordAttribute;
        }

        if (method_exists($this, 'passwordAttribute')) {
            return $this->passwordAttribute();
        }

        return 'unicodepwd';
    }

    /**
     * Get the name of the method to use for hashing the user's password.
     */
    public function getPasswordHashMethod(): string
    {
        if (property_exists($this, 'passwordHashMethod')) {
            return $this->passwordHashMethod;
        }

        if (method_exists($this, 'passwordHashMethod')) {
            return $this->passwordHashMethod();
        }

        return 'encode';
    }

    /**
     * Set the changed password.
     */
    protected function setChangedPassword(string $oldPassword, string $newPassword, string $attribute): void
    {
        // Create batch modification for removing the old password.
        $this->addModification(
            $this->newBatchModification(
                $attribute,
                LDAP_MODIFY_BATCH_REMOVE,
                [$oldPassword]
            )
        );

        // Create batch modification for adding the new password.
        $this->addModification(
            $this->newBatchModification(
                $attribute,
                LDAP_MODIFY_BATCH_ADD,
                [$newPassword]
            )
        );
    }

    /**
     * Set the password on the model.
     */
    protected function setPassword(string $password, string $attribute): void
    {
        if (! $this->exists) {
            $this->setRawAttribute($attribute, $password);

            return;
        }

        $this->addModification(
            $this->newBatchModification(
                $attribute,
                LDAP_MODIFY_BATCH_REPLACE,
                [$password]
            )
        );
    }

    /**
     * Encode / hash the given password.
     *
     * @throws LdapRecordException
     */
    protected function getHashedPassword(string $method, string $password, ?string $salt = null): string
    {
        if (! method_exists(Password::class, $method)) {
            throw new LdapRecordException("Password hashing method [{$method}] does not exist.");
        }

        if (Password::hashMethodRequiresSalt($method)) {
            return Password::{$method}($password, $salt);
        }

        return Password::{$method}($password);
    }

    /**
     * Validates that the current LDAP connection is secure.
     *
     * @throws ConnectionException
     */
    protected function assertSecureConnection(): void
    {
        $connection = $this->getConnection();

        $config = $connection->getConfiguration();

        if ($config->get('allow_insecure_password_changes') === true) {
            return;
        }

        if ($connection->isConnected()) {
            $secure = $connection->getLdapConnection()->canChangePasswords();
        } else {
            $secure = $config->get('use_ssl') || $config->get('use_tls');
        }

        if (! $secure) {
            throw new ConnectionException(
                'You must be connected to your LDAP server with TLS or SSL to perform this operation.'
            );
        }
    }

    /**
     * Attempt to retrieve the password's salt.
     */
    public function getPasswordSalt(string $method): ?string
    {
        if (! Password::hashMethodRequiresSalt($method)) {
            return null;
        }

        return Password::getSalt($this->password);
    }

    /**
     * Determine the password hash method to use from the users current password.
     *
     * @return string|void
     */
    public function determinePasswordHashMethod()
    {
        if (! $password = $this->password) {
            return;
        }

        if (! $method = Password::getHashMethod($password)) {
            return;
        }

        [,$algo] = array_pad(
            Password::getHashMethodAndAlgo($password) ?? [],
            $length = 2,
            $value = null
        );

        return match ((int) $algo) {
            Password::CRYPT_SALT_TYPE_MD5 => 'md5'.$method,
            Password::CRYPT_SALT_TYPE_SHA256 => 'sha256'.$method,
            Password::CRYPT_SALT_TYPE_SHA512 => 'sha512'.$method,
            default => $method,
        };
    }
}
