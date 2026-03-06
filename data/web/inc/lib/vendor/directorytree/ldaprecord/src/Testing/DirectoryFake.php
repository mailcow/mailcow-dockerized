<?php

namespace LdapRecord\Testing;

use LdapRecord\Container;

class DirectoryFake
{
    /**
     * The LDAP connections that were replaced with fakes.
     *
     * @var \LdapRecord\Connection[]
     */
    protected static array $replaced = [];

    /**
     * Replace a connection a fake.
     *
     * @throws \LdapRecord\ContainerException
     */
    public static function setup(?string $name = null): ConnectionFake
    {
        $name = $name ?? Container::getDefaultConnectionName();

        $connection = static::$replaced[$name] = Container::getConnection($name);

        $fake = static::makeConnectionFake(
            $connection->getConfiguration()->all()
        );

        Container::addConnection($fake, $name);

        return $fake;
    }

    /**
     * Replace all faked connections with their original.
     */
    public static function tearDown(): void
    {
        foreach (static::$replaced as $name => $connection) {
            Container::getConnection($name)->tearDown();
        }

        Container::flush();

        static::$replaced = [];
    }

    /**
     * Make a connection fake.
     */
    public static function makeConnectionFake(array $config = []): ConnectionFake
    {
        return ConnectionFake::make($config)->shouldBeConnected();
    }
}
