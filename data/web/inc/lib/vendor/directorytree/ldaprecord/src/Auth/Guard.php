<?php

namespace LdapRecord\Auth;

use Exception;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Events\DispatcherInterface;
use LdapRecord\LdapInterface;

class Guard
{
    /**
     * The connection to bind to.
     */
    protected LdapInterface $connection;

    /**
     * The domain configuration to utilize.
     */
    protected DomainConfiguration $configuration;

    /**
     * The event dispatcher.
     */
    protected ?DispatcherInterface $events = null;

    /**
     * Constructor.
     */
    public function __construct(LdapInterface $connection, DomainConfiguration $configuration)
    {
        $this->connection = $connection;
        $this->configuration = $configuration;
    }

    /**
     * Attempt binding a user to the LDAP server.
     *
     * @throws UsernameRequiredException
     * @throws PasswordRequiredException
     */
    public function attempt(string $username, string $password, bool $stayBound = false): bool
    {
        switch (true) {
            case empty($username):
                throw new UsernameRequiredException('A username must be specified.');
            case empty($password):
                throw new PasswordRequiredException('A password must be specified.');
        }

        $this->fireAuthEvent('attempting', $username, $password);

        try {
            $this->bind($username, $password);

            $bound = true;

            $this->fireAuthEvent('passed', $username, $password);
        } catch (BindException) {
            $bound = false;
        }

        if (! $stayBound) {
            $this->bindAsConfiguredUser();
        }

        return $bound;
    }

    /**
     * Attempt binding a user to the LDAP server. Supports sasl and anonymous binding.
     *
     * @throws BindException
     * @throws \LdapRecord\ConnectionException
     */
    public function bind(?string $username = null, ?string $password = null): void
    {
        $this->fireAuthEvent('binding', $username, $password);

        // Prior to binding, we will upgrade our connectivity to TLS on our current
        // connection and ensure we are not already bound before upgrading.
        // This is to prevent subsequent upgrading on several binds.
        if ($this->connection->isUsingTLS() && ! $this->connection->isSecure()) {
            $this->connection->startTLS();
        }

        try {
            if (! $this->authenticate($username, $password)) {
                throw new Exception($this->connection->getLastError(), $this->connection->errNo());
            }

            $this->fireAuthEvent('bound', $username, $password);
        } catch (Exception $e) {
            $exception = BindException::withDetailedError($e, $this->connection->getDetailedError());

            $this->fireAuthEvent('failed', $username, $password, $exception);

            throw $exception;
        }
    }

    /**
     * Authenticate by binding to the LDAP server.
     *
     * @throws \LdapRecord\ConnectionException
     */
    protected function authenticate(?string $username = null, ?string $password = null): bool
    {
        if ($this->configuration->get('use_sasl') ?? false) {
            return $this->connection->saslBind(
                $username, $password, $this->configuration->get('sasl_options')
            );
        }

        return $this->connection->bind($username, $password)->successful();
    }

    /**
     * Bind to the LDAP server using the configured username and password.
     *
     * @throws BindException
     * @throws \LdapRecord\ConnectionException
     * @throws \LdapRecord\Configuration\ConfigurationException
     */
    public function bindAsConfiguredUser(): void
    {
        $this->bind(
            $this->configuration->get('username'),
            $this->configuration->get('password')
        );
    }

    /**
     * Get the event dispatcher instance.
     */
    public function getDispatcher(): ?DispatcherInterface
    {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance.
     */
    public function setDispatcher(?DispatcherInterface $dispatcher = null): void
    {
        $this->events = $dispatcher;
    }

    /**
     * Fire an authentication event.
     */
    protected function fireAuthEvent(string $name, ?string $username = null, ?string $password = null, ...$args): void
    {
        if (isset($this->events)) {
            $event = implode('\\', [Events::class, ucfirst($name)]);

            $this->events->fire(new $event($this->connection, $username, $password, ...$args));
        }
    }
}
