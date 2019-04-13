<?php

declare(strict_types=1);

namespace Ddeboer\Imap;

/**
 * An IMAP server.
 */
interface ServerInterface
{
    /**
     * Authenticate connection.
     *
     * @param string $username Username
     * @param string $password Password
     *
     * @return ConnectionInterface
     */
    public function authenticate(string $username, string $password): ConnectionInterface;
}
