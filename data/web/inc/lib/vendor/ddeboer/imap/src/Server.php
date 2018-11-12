<?php

declare(strict_types=1);

namespace Ddeboer\Imap;

use Ddeboer\Imap\Exception\AuthenticationFailedException;

/**
 * An IMAP server.
 */
final class Server implements ServerInterface
{
    /**
     * @var string Internet domain name or bracketed IP address of server
     */
    private $hostname;

    /**
     * @var string TCP port number
     */
    private $port;

    /**
     * @var string Optional flags
     */
    private $flags;

    /**
     * @var array
     */
    private $parameters;

    /**
     * Constructor.
     *
     * @param string $hostname   Internet domain name or bracketed IP address
     *                           of server
     * @param string $port       TCP port number
     * @param string $flags      Optional flags
     * @param array  $parameters Connection parameters
     */
    public function __construct(
        string $hostname,
        string $port = '993',
        string $flags = '/imap/ssl/validate-cert',
        array $parameters = []
    ) {
        if (!\function_exists('imap_open')) {
            throw new \RuntimeException('IMAP extension must be enabled');
        }

        $this->hostname = $hostname;
        $this->port = $port;
        $this->flags = $flags ? '/' . \ltrim($flags, '/') : '';
        $this->parameters = $parameters;
    }

    /**
     * Authenticate connection.
     *
     * @param string $username Username
     * @param string $password Password
     *
     * @throws AuthenticationFailedException
     *
     * @return ConnectionInterface
     */
    public function authenticate(string $username, string $password): ConnectionInterface
    {
        $errorMessage = null;
        $errorNumber = 0;
        \set_error_handler(function ($nr, $message) use (&$errorMessage, &$errorNumber) {
            $errorMessage = $message;
            $errorNumber = $nr;
        });

        $resource = \imap_open(
            $this->getServerString(),
            $username,
            $password,
            0,
            1,
            $this->parameters
        );

        \restore_error_handler();

        if (false === $resource || null !== $errorMessage) {
            throw new AuthenticationFailedException(\sprintf(
                'Authentication failed for user "%s"%s',
                $username,
                null !== $errorMessage ? ': ' . $errorMessage : ''
            ), $errorNumber);
        }

        $check = \imap_check($resource);
        $mailbox = $check->Mailbox;
        $connection = \substr($mailbox, 0, \strpos($mailbox, '}') + 1);

        // These are necessary to get rid of PHP throwing IMAP errors
        \imap_errors();
        \imap_alerts();

        return new Connection(new ImapResource($resource), $connection);
    }

    /**
     * Glues hostname, port and flags and returns result.
     *
     * @return string
     */
    private function getServerString(): string
    {
        return \sprintf(
            '{%s%s%s}',
            $this->hostname,
            '' !== $this->port ? ':' . $this->port : '',
            $this->flags
        );
    }
}
