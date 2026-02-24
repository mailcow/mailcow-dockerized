<?php

namespace LdapRecord;

use Closure;
use ErrorException;
use Exception;
use LDAP\Connection;

/** @mixin Ldap */
trait HandlesConnection
{
    /**
     * The LDAP host that is currently connected.
     */
    protected ?string $host = null;

    /**
     * The LDAP protocol to use (ldap:// or ldaps://).
     */
    protected ?string $protocol = null;

    /**
     * The LDAP connection resource.
     *
     * @var Connection
     */
    protected mixed $connection = null;

    /**
     * Whether the connection is bound.
     */
    protected bool $bound = false;

    /**
     * Whether the connection is secured over TLS or SSL.
     */
    protected bool $secure = false;

    /**
     * Whether the connection must be bound over SSL.
     */
    protected bool $useSSL = false;

    /**
     * Whether the connection must be bound over TLS.
     */
    protected bool $useTLS = false;

    /**
     * {@inheritdoc}
     */
    public function isUsingSSL(): bool
    {
        return $this->useSSL;
    }

    /**
     * {@inheritdoc}
     */
    public function isUsingTLS(): bool
    {
        return $this->useTLS;
    }

    /**
     * {@inheritdoc}
     */
    public function isBound(): bool
    {
        return $this->bound;
    }

    /**
     * {@inheritdoc}
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        return ! is_null($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function canChangePasswords(): bool
    {
        return $this->isUsingSSL() || $this->isUsingTLS();
    }

    /**
     * {@inheritdoc}
     */
    public function ssl(bool $enabled = true): static
    {
        $this->useSSL = $enabled;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function tls(bool $enabled = true): static
    {
        $this->useTLS = $enabled;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options = []): void
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocol(): string
    {
        return $this->protocol ?: (
            $this->isUsingSSL()
                ? LdapInterface::PROTOCOL_SSL
                : LdapInterface::PROTOCOL
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedError(): ?string
    {
        return $this->getDiagnosticMessage();
    }

    /**
     * Handle the bind response.
     */
    protected function handleBindResponse(LdapResultResponse $response): void
    {
        $this->bound = $response->successful();

        $this->secure = $this->secure ?: $this->bound && $this->isUsingSSL();
    }

    /**
     * Convert warnings to exceptions for the given operation.
     *
     * @throws LdapRecordException
     */
    protected function executeFailableOperation(Closure $operation): mixed
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
            // -- such as a search, read, or list, then we can safely return
            // the failed response here and prevent throwing an exception.
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
     */
    protected function shouldBypassFailure(string $method): bool
    {
        return in_array($method, ['search', 'read', 'list']);
    }

    /**
     * Determine if the error should be bypassed.
     */
    protected function shouldBypassError(string $error): bool
    {
        return $this->causedByPaginationSupport($error) || $this->causedBySizeLimit($error) || $this->causedByNoSuchObject($error);
    }

    /**
     * Generates an LDAP connection string for each host given.
     */
    protected function makeConnectionUris(array|string $hosts, string|int $port): string
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
     */
    protected function assembleHostUris(array|string $hosts, string|int $port): array
    {
        return array_map(fn ($host) => "{$this->getProtocol()}{$host}:{$port}", (array) $hosts);
    }
}
