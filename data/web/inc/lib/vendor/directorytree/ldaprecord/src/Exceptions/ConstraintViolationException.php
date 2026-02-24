<?php

namespace LdapRecord\Exceptions;

use LdapRecord\DetectsErrors;
use LdapRecord\LdapRecordException;

class ConstraintViolationException extends LdapRecordException
{
    use DetectsErrors;

    /**
     * Determine if the exception was generated due to the password policy.
     */
    public function causedByPasswordPolicy(): bool
    {
        return isset($this->detailedError) && $this->errorContainsMessage($this->detailedError->getDiagnosticMessage(), '0000052D');
    }

    /**
     * Determine if the exception was generated due to an incorrect password.
     */
    public function causedByIncorrectPassword(): bool
    {
        return isset($this->detailedError) && $this->errorContainsMessage($this->detailedError->getDiagnosticMessage(), '00000056');
    }
}
