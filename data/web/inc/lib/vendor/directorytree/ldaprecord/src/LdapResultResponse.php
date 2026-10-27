<?php

namespace LdapRecord;

class LdapResultResponse
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly int $errorCode = 0,
        public readonly ?string $matchedDn = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $referrals = null,
        public readonly ?array $controls = null,
    ) {}

    /**
     * Determine if the LDAP response indicates a successful status.
     */
    public function successful(): bool
    {
        return $this->errorCode === 0;
    }

    /**
     * Determine if the LDAP response indicates a failed status.
     */
    public function failed(): bool
    {
        return ! $this->successful();
    }
}
