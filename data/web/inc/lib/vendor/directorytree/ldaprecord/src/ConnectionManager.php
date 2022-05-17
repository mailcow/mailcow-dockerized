<?php

namespace LdapRecord;

use BadMethodCallException;
use LdapRecord\Events\Dispatcher;
use LdapRecord\Events\DispatcherInterface;
use LdapRecord\Events\Logger;
use Psr\Log\LoggerInterface;

class ConnectionManager
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * The event dispatcher instance.
     *
     * @var DispatcherInterface|null
     */
    protected $dispatcher;

    /**
     * The added LDAP connections.
     *
     * @var Connection[]
     */
    protected $connections = [];

    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $default = 'default';

    /**
     * The events to register listeners for during initialization.
     *
     * @var array
     */
    protected $listen = [
        'LdapRecord\Auth\Events\*',
        'LdapRecord\Query\Events\*',
        'LdapRecord\Models\Events\*',
    ];

    /**
     * The method calls to proxy for compatibility.
     *
     * To be removed in the next major version.
     *
     * @var array
     */
    protected $proxy = [
        'reset' => 'flush',
        'addConnection' => 'add',
        'getConnection' => 'get',
        'allConnections' => 'all',
        'removeConnection' => 'remove',
        'getDefaultConnection' => 'getDefault',
        'setDefaultConnection' => 'setDefault',
        'getEventDispatcher' => 'dispatcher',
        'setEventDispatcher' => 'setDispatcher',
    ];

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->dispatcher = new Dispatcher();
    }

    /**
     * Forward missing method calls onto the instance.
     *
     * @param string $method
     * @param mixed  $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        $method = $this->proxy[$method] ?? $method;

        if (! method_exists($this, $method)) {
            throw new BadMethodCallException(sprintf(
                'Call to undefined method %s::%s()',
                static::class,
                $method
            ));
        }

        return $this->{$method}(...$args);
    }

    /**
     * Add a new connection.
     *
     * @param Connection  $connection
     * @param string|null $name
     *
     * @return $this
     */
    public function add(Connection $connection, $name = null)
    {
        $this->connections[$name ?? $this->default] = $connection;

        if ($this->dispatcher) {
            $connection->setDispatcher($this->dispatcher);
        }

        return $this;
    }

    /**
     * Remove a connection.
     *
     * @param $name
     *
     * @return $this
     */
    public function remove($name)
    {
        unset($this->connections[$name]);

        return $this;
    }

    /**
     * Get all of the connections.
     *
     * @return Connection[]
     */
    public function all()
    {
        return $this->connections;
    }

    /**
     * Get a connection by name or return the default.
     *
     * @param string|null $name
     *
     * @return Connection
     *
     * @throws ContainerException If the given connection does not exist.
     */
    public function get($name = null)
    {
        if ($this->exists($name = $name ?? $this->default)) {
            return $this->connections[$name];
        }

        throw new ContainerException("The LDAP connection [$name] does not exist.");
    }

    /**
     * Return the default connection.
     *
     * @return Connection
     */
    public function getDefault()
    {
        return $this->get($this->default);
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnectionName()
    {
        return $this->default;
    }

    /**
     * Checks if the connection exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function exists($name)
    {
        return array_key_exists($name, $this->connections);
    }

    /**
     * Set the default connection name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setDefault($name = null)
    {
        $this->default = $name;

        return $this;
    }

    /**
     * Flush the manager of all instances and connections.
     *
     * @return $this
     */
    public function flush()
    {
        $this->logger = null;

        $this->connections = [];

        $this->dispatcher = new Dispatcher();

        return $this;
    }

    /**
     * Get the logger instance.
     *
     * @return LoggerInterface|null
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set the event logger to use.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        $this->initEventLogger();
    }

    /**
     * Initialize the event logger.
     *
     * @return void
     */
    public function initEventLogger()
    {
        $logger = $this->newEventLogger();

        foreach ($this->listen as $event) {
            $this->dispatcher->listen($event, function ($eventName, $events) use ($logger) {
                foreach ($events as $event) {
                    $logger->log($event);
                }
            });
        }
    }

    /**
     * Make a new event logger instance.
     *
     * @return Logger
     */
    protected function newEventLogger()
    {
        return new Logger($this->logger);
    }

    /**
     * Unset the logger instance.
     *
     * @return void
     */
    public function unsetLogger()
    {
        $this->logger = null;
    }

    /**
     * Get the event dispatcher.
     *
     * @return DispatcherInterface|null
     */
    public function dispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Set the event dispatcher.
     *
     * @param DispatcherInterface $dispatcher
     *
     * @return void
     */
    public function setDispatcher(DispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher.
     *
     * @return void
     */
    public function unsetEventDispatcher()
    {
        $this->dispatcher = null;
    }
}
