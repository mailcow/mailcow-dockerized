<?php

namespace LdapRecord;

/**
 * @method static $this reset()
 * @method static Connection[] all()
 * @method static Connection[] allConnections()
 * @method static Connection getDefaultConnection()
 * @method static Connection get(string|null $name = null)
 * @method static Connection getConnection(string|null $name = null)
 * @method static bool exists(string $name)
 * @method static $this remove(string|null $name = null)
 * @method static $this removeConnection(string|null $name = null)
 * @method static $this setDefault(string|null $name = null)
 * @method static $this setDefaultConnection(string|null $name = null)
 * @method static $this add(Connection $connection, string|null $name = null)
 * @method static $this addConnection(Connection $connection, string|null $name = null)
 */
class Container
{
    /**
     * The current container instance.
     *
     * @var Container
     */
    protected static $instance;

    /**
     * The connection manager instance.
     *
     * @var ConnectionManager
     */
    protected $manager;

    /**
     * The methods to passthru, for compatibility.
     *
     * @var array
     */
    protected $passthru = [
        'reset', 'flush',
        'add', 'addConnection',
        'remove', 'removeConnection',
        'setDefault', 'setDefaultConnection',
    ];

    /**
     * Forward missing static calls onto the current instance.
     *
     * @param string $method
     * @param mixed  $args
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return static::getInstance()->{$method}(...$args);
    }

    /**
     * Get or set the current instance of the container.
     *
     * @return Container
     */
    public static function getInstance()
    {
        return static::$instance ?? static::getNewInstance();
    }

    /**
     * Set the container instance.
     *
     * @param Container|null $container
     *
     * @return Container|null
     */
    public static function setInstance(self $container = null)
    {
        return static::$instance = $container;
    }

    /**
     * Set and get a new instance of the container.
     *
     * @return Container
     */
    public static function getNewInstance()
    {
        return static::setInstance(new static());
    }

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->manager = new ConnectionManager();
    }

    /**
     * Forward missing method calls onto the connection manager.
     *
     * @param string $method
     * @param mixed  $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        $value = $this->manager->{$method}(...$args);

        return in_array($method, $this->passthru) ? $this : $value;
    }

    /**
     * Get the connection manager.
     *
     * @return ConnectionManager
     */
    public function manager()
    {
        return $this->manager;
    }
}
