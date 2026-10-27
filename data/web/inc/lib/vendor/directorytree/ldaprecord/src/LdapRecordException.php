<?php

namespace LdapRecord;

use Exception;

class LdapRecordException extends Exception
{
    /**
     * The detailed LDAP error (if available).
     */
    protected ?DetailedError $detailedError = null;

    /**
     * Create a new Bind Exception with a detailed connection error.
     */
    public static function withDetailedError(Exception $e, ?DetailedError $error = null): static
    {
        return (new static($e->getMessage(), $e->getCode(), $e))->setDetailedError($error);
    }

    /**
     * Set the detailed error.
     */
    public function setDetailedError(?DetailedError $error = null): static
    {
        $this->detailedError = $error;

        return $this;
    }

    /**
     * Get the detailed error.
     */
    public function getDetailedError(): ?DetailedError
    {
        return $this->detailedError;
    }
}
