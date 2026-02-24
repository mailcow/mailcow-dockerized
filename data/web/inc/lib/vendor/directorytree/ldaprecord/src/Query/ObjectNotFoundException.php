<?php

namespace LdapRecord\Query;

use LdapRecord\LdapRecordException;

class ObjectNotFoundException extends LdapRecordException
{
    /**
     * The query filter that was used.
     */
    protected string $query;

    /**
     * The base DN of the query that was used.
     */
    protected ?string $baseDn;

    /**
     * Create a new exception for the executed filter.
     */
    public static function forQuery(string $query, ?string $baseDn = null): static
    {
        return (new static)->setQuery($query, $baseDn);
    }

    /**
     * Set the query that was used.
     */
    public function setQuery(string $query, ?string $baseDn = null): static
    {
        $this->query = $query;
        $this->baseDn = $baseDn;
        $this->message = "No LDAP query results for filter: [$query] in: [$baseDn]";

        return $this;
    }
}
