<?php

namespace LdapRecord;

use Closure;
use ErrorException;
use Exception;

trait HandlesConnection
{
    /**
     * The LDAP host that is currently connected.
     *
     * @var string|null
     */
    protected $host;

    /**
     * The LDAP connection resource.
     *
     * @var resource|null
     */
    protected $connection;

    /**
     * The bound status of the connection.
     *
     * @var bool
     */
    protected $bound = false;

    /**
     * Whether the connection must be bound over SSL.
     *
     * @var bool
     */
    protected $useSSL = false;

    /**
     * Whether the connection must be bound over TLS.
     *
     * @var bool
     */
    protected $useTLS = false;

    /**
     * @inheritdoc
     */
    public function isUsingSSL()
    {
        return $this->useSSL;
    }

    /**
     * @inheritdoc
     */
    public function isUsingTLS()
    {
        return $this->useTLS;
    }

    /**
     * @inheritdoc
     */
    public function isBound()
    {
        return $this->bound;
    }

    /**
     * @inheritdoc
     */
    public function isConnected()
    {
        return ! is_null($this->connection);
    }

    /**
     * @inheritdoc
     */
    public function canChangePasswords()
    {
        return $this->isUsingSSL() || $this->isUsingTLS();
    }

    /**
     * @inheritdoc
     */
    public function ssl($enabled = true)
    {
        $this->useSSL = $enabled;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function tls($enabled = true)
    {
        $this->useTLS = $enabled;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setOptions(array $options = [])
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @inheritdoc
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @inheritdoc
     */
    public function getProtocol()
    {
        return $this->isUsingSSL() ? LdapInterface::PROTOCOL_SSL : LdapInterface::PROTOCOL;
    }

    /**
     * @inheritdoc
     */
    public function getExtendedError()
    {
        return $this->getDiagnosticMessage();
    }

    /**
     * Convert warnings to exceptions for the given operation.
     *
     * @param Closure $operation
     *
     * @return mixed
     *
     * @throws LdapRecordException
     */
    protected function executeFailableOperation(Closure $operation)
    {
        // If some older versions of PHP, errors are reported instead of throwing
        // exceptions, which could be a significant detriment to our application.
        // Here, we will enforce these operations to throw exceptions instead.
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (! $this->shouldBypassError($message)) {
                throw new ErrorException($message, $severity, $severity, $file, $line);
            }

            return true;
        });

        try {
            if (($result = $operation()) !== false) {
                return $result;
            }

            // If the failed query operation was a based on a query being executed
            // -- such as a search, read, or listing, then we can safely return
            // the failed response here and prevent throwning an exception.
            if ($this->shouldBypassFailure($method = debug_backtrace()[1]['function'])) {
                return $result;
            }

            throw new Exception("LDAP operation [$method] failed.");
        } catch (ErrorException $e) {
            throw LdapRecordException::withDetailedError($e, $this->getDetailedError());
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Determine if the failed operation should be bypassed.
     *
     * @param string $method
     *
     * @return bool
     */
    protected function shouldBypassFailure($method)
    {
        return in_array($method, ['search', 'read', 'listing']);
    }

    /**
     * Determine if the error should be bypassed.
     *
     * @param string $error
     *
     * @return bool
     */
    protected function shouldBypassError($error)
    {
        return $this->causedByPaginationSupport($error) || $this->causedBySizeLimit($error) || $this->causedByNoSuchObject($error);
    }

    /**
     * Determine if the current PHP version supports server controls.
     *
     * @deprecated since v2.5.0
     *
     * @return bool
     */
    public function supportsServerControlsInMethods()
    {
        return version_compare(PHP_VERSION, '7.3.0') >= 0;
    }

    /**
     * Generates an LDAP connection string for each host given.
     *
     * @param string|array $hosts
     * @param string       $port
     *
     * @return string
     */
    protected function makeConnectionUris($hosts, $port)
    {
        // If an attempt to connect via SSL protocol is being performed,
        // and we are still using the default port, we will swap it
        // for the default SSL port, for developer convenience.
        if ($this->isUsingSSL() && $port == LdapInterface::PORT) {
            $port = LdapInterface::PORT_SSL;
        }

        // The blank space here is intentional. PHP's LDAP extension
        // requires additional hosts to be seperated by a blank
        // space, so that it can parse each individually.
        return implode(' ', $this->assembleHostUris($hosts, $port));
    }

    /**
     * Assemble the host URI strings.
     *
     * @param array|string $hosts
     * @param string       $port
     *
     * @return array
     */
    protected function assembleHostUris($hosts, $port)
    {
        return array_map(function ($host) use ($port) {
            return "{$this->getProtocol()}{$host}:{$port}";
        }, (array) $hosts);
    }
}
