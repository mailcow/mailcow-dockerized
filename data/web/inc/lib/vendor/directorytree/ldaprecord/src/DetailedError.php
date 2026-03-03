<?php

namespace LdapRecord;

class DetailedError
{
    /**
     * Constructor.
     */
    public function __construct(
        protected int $errorCode,
        protected string $errorMessage,
        protected ?string $diagnosticMessage
    ) {}

    /**
     * Get the LDAP error code.
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * Get the LDAP error message.
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Get the LDAP diagnostic message.
     */
    public function getDiagnosticMessage(): ?string
    {
        return $this->diagnosticMessage;
    }
}
