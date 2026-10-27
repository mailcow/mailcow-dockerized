<?php

namespace LdapRecord\Events;

use LdapRecord\Connection;

abstract class ConnectionEvent
{
    /**
     * The LDAP connection.
     */
    protected Connection $connection;

    /**
     * Constructor.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the connection pertaining to the event.
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
