<?php

namespace LdapRecord;

trait DetectsErrors
{
    /**
     * Determine if the error was caused by a lost connection.
     */
    protected function causedByLostConnection(string $error): bool
    {
        return $this->errorContainsMessage($error, ["Can't contact LDAP server", 'Operations error']);
    }

    /**
     * Determine if the error was caused by lack of pagination support.
     */
    protected function causedByPaginationSupport(string $error): bool
    {
        return $this->errorContainsMessage($error, 'No server controls in result');
    }

    /**
     * Determine if the error was caused by a size limit warning.
     */
    protected function causedBySizeLimit(string $error): bool
    {
        return $this->errorContainsMessage($error, ['Partial search results returned', 'Size limit exceeded']);
    }

    /**
     * Determine if the error was caused by a "No such object" warning.
     */
    protected function causedByNoSuchObject(string $error): bool
    {
        return $this->errorContainsMessage($error, ['No such object']);
    }

    /**
     * Determine if the error contains any of the messages.
     */
    protected function errorContainsMessage(string $error, string|array $messages = []): bool
    {
        foreach ((array) $messages as $message) {
            if (str_contains($error, $message)) {
                return true;
            }
        }

        return false;
    }
}
