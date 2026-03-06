<?php

namespace LdapRecord;

use Carbon\Carbon;
use Closure;
use LdapRecord\Auth\Guard;
use LdapRecord\Configuration\ConfigurationException;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Events\DispatcherInterface;
use LdapRecord\Query\Builder;
use LdapRecord\Query\Cache;
use Psr\SimpleCache\CacheInterface;

class Connection
{
    use DetectsErrors;

    /**
     * The underlying LDAP connection.
     */
    protected LdapInterface $ldap;

    /**
     * The cache driver.
     */
    protected ?Cache $cache = null;

    /**
     * The domain configuration.
     */
    protected DomainConfiguration $configuration;

    /**
     * The event dispatcher.
     */
    protected ?DispatcherInterface $dispatcher = null;

    /**
     * The currently connected host.
     */
    protected string $host;

    /**
     * The configured domain hosts.
     */
    protected array $hosts = [];

    /**
     * The attempted hosts that failed connecting to.
     */
    protected array $attempted = [];

    /**
     * The callback to execute upon total connection failure.
     */
    protected Closure $failed;

    /**
     * The authentication guard resolver.
     */
    protected Closure $authGuardResolver;

    /**
     * Whether the connection is retrying the initial connection attempt.
     */
    protected bool $retryingInitialConnection = false;

    /**
     * Constructor.
     *
     * @throws ConfigurationException
     */
    public function __construct(DomainConfiguration|array $config = [], ?LdapInterface $ldap = null)
    {
        $this->setConfiguration($config);

        $this->setLdapConnection($ldap ?? new Ldap);

        $this->failed = function () {
            $this->dispatch(new Events\ConnectionFailed($this));
        };

        $this->authGuardResolver = function () {
            return new Guard($this->ldap, $this->configuration);
        };
    }

    /**
     * Set the connection configuration.
     *
     * @throws Configuration\ConfigurationException
     */
    public function setConfiguration(DomainConfiguration|array $config = []): void
    {
        if (! $config instanceof DomainConfiguration) {
            $config = new DomainConfiguration($config);
        }

        $this->configuration = $config;

        $this->hosts = $this->configuration->get('hosts');

        $this->host = reset($this->hosts);
    }

    /**
     * Set the LDAP connection.
     */
    public function setLdapConnection(LdapInterface $ldap): void
    {
        $this->ldap = $ldap;
    }

    /**
     * Set the event dispatcher.
     */
    public function setDispatcher(DispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Get the event dispatcher.
     */
    public function getDispatcher(): ?DispatcherInterface
    {
        return $this->dispatcher;
    }

    /**
     * Initialize the LDAP connection.
     */
    public function initialize(): void
    {
        $this->configure();

        $this->ldap->connect(
            $this->host,
            $this->configuration->get('port'),
            $this->configuration->get('protocol')
        );
    }

    /**
     * Configure the LDAP connection.
     */
    protected function configure(): void
    {
        if ($this->configuration->get('use_ssl')) {
            $this->ldap->ssl();
        } elseif ($this->configuration->get('use_tls')) {
            $this->ldap->tls();
        }

        $this->ldap->setOptions(array_replace(
            $this->configuration->get('options'),
            [
                LDAP_OPT_PROTOCOL_VERSION => $this->configuration->get('version'),
                LDAP_OPT_NETWORK_TIMEOUT => $this->configuration->get('timeout'),
                LDAP_OPT_REFERRALS => $this->configuration->get('follow_referrals'),
            ]
        ));
    }

    /**
     * Set the cache store.
     */
    public function setCache(CacheInterface $store): void
    {
        $this->cache = new Cache($store);
    }

    /**
     * Get the cache store.
     */
    public function getCache(): ?Cache
    {
        return $this->cache;
    }

    /**
     * Get the LDAP configuration instance.
     */
    public function getConfiguration(): DomainConfiguration
    {
        return $this->configuration;
    }

    /**
     * Get the LDAP connection instance.
     */
    public function getLdapConnection(): LdapInterface
    {
        return $this->ldap;
    }

    /**
     * Set the auth guard resolver callback.
     */
    public function setGuardResolver(Closure $callback): void
    {
        $this->authGuardResolver = $callback;
    }

    /**
     * Bind to the LDAP server.
     *
     * If no username or password is specified, then the configured credentials are used.
     *
     * @throws Auth\BindException
     * @throws LdapRecordException
     */
    public function connect(?string $username = null, ?string $password = null): void
    {
        $attempt = function () use ($username, $password) {
            $this->dispatch(new Events\Connecting($this));

            is_null($username) && is_null($password)
                ? $this->auth()->bindAsConfiguredUser()
                : $this->auth()->bind($username, $password);

            $this->dispatch(new Events\Connected($this));

            $this->retryingInitialConnection = false;
        };

        try {
            $this->runOperationCallback($attempt);
        } catch (LdapRecordException $e) {
            $this->retryingInitialConnection = true;

            $this->retryOnNextHost($e, $attempt);
        }
    }

    /**
     * Reconnect to the LDAP server.
     *
     * @throws Auth\BindException
     * @throws ConnectionException
     * @throws LdapRecordException
     */
    public function reconnect(): void
    {
        $this->reinitialize();

        $this->connect();
    }

    /**
     * Reinitialize the connection.
     */
    protected function reinitialize(): void
    {
        $this->disconnect();

        $this->initialize();
    }

    /**
     * Clone the connection.
     */
    public function replicate(): static
    {
        return new static($this->configuration, new $this->ldap);
    }

    /**
     * Disconnect from the LDAP server.
     */
    public function disconnect(): void
    {
        $this->ldap->close();
    }

    /**
     * Dispatch an event.
     */
    public function dispatch(object $event): void
    {
        if (isset($this->dispatcher)) {
            $this->dispatcher->dispatch($event);
        }
    }

    /**
     * Get the attempted hosts that failed connecting to.
     */
    public function attempted(): array
    {
        return $this->attempted;
    }

    /**
     * Perform the operation on the LDAP connection.
     */
    public function run(Closure $operation): mixed
    {
        try {
            // Before running the operation, we will check if the current
            // connection is bound and connect if necessary. Otherwise,
            // some LDAP operations will not be executed properly.
            if (! $this->isConnected()) {
                $this->connect();
            }

            return $this->runOperationCallback($operation);
        } catch (LdapRecordException $e) {
            if ($exception = $this->getExceptionForCauseOfFailure($e)) {
                throw $exception;
            }

            return $this->tryAgainIfCausedByLostConnection($e, $operation);
        }
    }

    /**
     * Perform the operation on an isolated LDAP connection.
     */
    public function isolate(Closure $operation): mixed
    {
        $connection = $this->replicate();

        try {
            return $operation($connection);
        } finally {
            $connection->disconnect();
        }
    }

    /**
     * Attempt to get an exception for the cause of failure.
     */
    protected function getExceptionForCauseOfFailure(LdapRecordException $e): ?LdapRecordException
    {
        switch (true) {
            case $this->errorContainsMessage($e->getMessage(), 'Already exists'):
                return Exceptions\AlreadyExistsException::withDetailedError($e, $e->getDetailedError());
            case $this->errorContainsMessage($e->getMessage(), 'Insufficient access'):
                return Exceptions\InsufficientAccessException::withDetailedError($e, $e->getDetailedError());
            case $this->errorContainsMessage($e->getMessage(), 'Constraint violation'):
                return Exceptions\ConstraintViolationException::withDetailedError($e, $e->getDetailedError());
            default:
                return null;
        }
    }

    /**
     * Run the operation callback on the current LDAP connection.
     */
    protected function runOperationCallback(Closure $operation): mixed
    {
        return $operation($this->ldap);
    }

    /**
     * Get a new auth guard instance.
     */
    public function auth(): Guard
    {
        if (! $this->ldap->isConnected()) {
            $this->initialize();
        }

        $guard = call_user_func($this->authGuardResolver);

        $guard->setDispatcher(
            Container::getInstance()->getDispatcher()
        );

        return $guard;
    }

    /**
     * Get a new query builder for the connection.
     */
    public function query(): Builder
    {
        return (new Builder($this))
            ->setCache($this->cache)
            ->setBaseDn($this->configuration->get('base_dn'));
    }

    /**
     * Determine if the LDAP connection is bound.
     */
    public function isConnected(): bool
    {
        return $this->ldap->isBound();
    }

    /**
     * Attempt to retry an LDAP operation if due to a lost connection.
     *
     * @throws LdapRecordException
     */
    protected function tryAgainIfCausedByLostConnection(LdapRecordException $e, Closure $operation): mixed
    {
        // If the operation failed due to a lost or failed connection,
        // we'll attempt reconnecting and running the operation again
        // underneath the same host, and then move onto the next.
        if ($this->causedByLostConnection($e->getMessage())) {
            return $this->retry($operation);
        }

        throw $e;
    }

    /**
     * Retry the operation on the current host.
     *
     * @throws LdapRecordException
     */
    protected function retry(Closure $operation): mixed
    {
        try {
            $this->retryingInitialConnection
                ? $this->reinitialize()
                : $this->reconnect();

            return $this->runOperationCallback($operation);
        } catch (LdapRecordException $e) {
            return $this->retryOnNextHost($e, $operation);
        }
    }

    /**
     * Attempt the operation again on the next host.
     *
     * @throws LdapRecordException
     */
    protected function retryOnNextHost(LdapRecordException $e, Closure $operation): mixed
    {
        $this->attempted[$this->host] = Carbon::now();

        if (($key = array_search($this->host, $this->hosts)) !== false) {
            unset($this->hosts[$key]);
        }

        if ($next = reset($this->hosts)) {
            $this->host = $next;

            return $this->tryAgainIfCausedByLostConnection($e, $operation);
        }

        call_user_func($this->failed, $this->ldap);

        throw $e;
    }
}
