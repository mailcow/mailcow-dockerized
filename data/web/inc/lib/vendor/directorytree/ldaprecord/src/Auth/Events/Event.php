<?php

namespace LdapRecord\Auth\Events;

use LdapRecord\LdapInterface;

abstract class Event
{
    /**
     * The connection that the username and password is being bound on.
     */
    protected LdapInterface $connection;

    /**
     * The username that is being used for binding.
     */
    protected ?string $username;

    /**
     * The password that is being used for binding.
     */
    protected ?string $password;

    /**
     * Constructor.
     */
    public function __construct(LdapInterface $connection, ?string $username = null, ?string $password = null)
    {
        $this->connection = $connection;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Get the event's connection.
     */
    public function getConnection(): LdapInterface
    {
        return $this->connection;
    }

    /**
     * Get the authentication event's username.
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Get the authentication event's password.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }
}
