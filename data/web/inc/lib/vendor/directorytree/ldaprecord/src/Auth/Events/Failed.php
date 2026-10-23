<?php

namespace LdapRecord\Auth\Events;

use Exception;
use LdapRecord\Auth\BindException;
use LdapRecord\LdapInterface;

class Failed extends Event
{
    /**
     * The exception that was thrown during the bind attempt.
     */
    protected BindException $exception;

    /**
     * Constructor.
     */
    public function __construct(LdapInterface $connection, ?string $username, ?string $password, BindException $exception)
    {
        parent::__construct($connection, $username, $password);

        $this->exception = $exception;
    }

    /**
     * Get the exception that was thrown during the bind attempt.
     */
    public function getException(): BindException
    {
        return $this->exception;
    }
}
