<?php

namespace LdapRecord\Testing;

use Closure;
use LdapRecord\DetailedError;
use LdapRecord\DetectsErrors;
use LdapRecord\HandlesConnection;
use LdapRecord\LdapInterface;
use LdapRecord\LdapResultResponse;
use LdapRecord\Support\Arr;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\Constraint\Constraint;

class LdapFake implements LdapInterface
{
    use DetectsErrors;
    use HandlesConnection;

    /**
     * The expectations of the LDAP fake.
     *
     * @var array<string,LdapExpectation[]>
     */
    protected array $expectations = [];

    /**
     * The default fake error number.
     */
    protected int $errNo = 1;

    /**
     * The default fake last error string.
     */
    protected string $lastError = 'Unknown error';

    /**
     * The default fake diagnostic message string.
     */
    protected ?string $diagnosticMessage = null;

    /**
     * Create a new expected operation.
     */
    public static function operation(string $method): LdapExpectation
    {
        return new LdapExpectation($method);
    }

    /**
     * Set the fake LDAP host.
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * Add an LDAP method expectation.
     */
    public function expect(LdapExpectation|array $expectations = []): static
    {
        $expectations = Arr::wrap($expectations);

        foreach ($expectations as $key => $expectation) {
            if (! is_int($key)) {
                $operation = static::operation($key);

                $expectation instanceof Closure
                    ? $expectation($operation)
                    : $operation->andReturn($expectation);

                $expectation = $operation;
            }

            if (! $expectation instanceof LdapExpectation) {
                $expectation = static::operation($expectation);
            }

            $this->expectations[$expectation->getMethod()][] = $expectation;
        }

        return $this;
    }

    /**
     * Determine if the method has any expectations.
     */
    public function hasExpectations(string $method): bool
    {
        return count($this->getExpectations($method)) > 0;
    }

    /**
     * Get expectations by method.
     *
     * @return LdapExpectation[]
     */
    public function getExpectations(string $method): array
    {
        return $this->expectations[$method] ?? [];
    }

    /**
     * Remove an expectation by method and key.
     */
    public function removeExpectation(string $method, int $key): void
    {
        unset($this->expectations[$method][$key]);
    }

    /**
     * Set the fake to allow any bind attempt.
     */
    public function shouldAllowAnyBind(): static
    {
        return $this->expect(
            static::operation('bind')->andReturnResponse()
        );
    }

    /**
     * Set the fake to allow any bind attempt with the given DN.
     */
    public function shouldAllowBindWith(string $dn): static
    {
        return $this->expect(
            static::operation('bind')->with($dn, PHPUnit::anything())->andReturnResponse()
        );
    }

    /**
     * Set the user that will pass binding.
     *
     * @deprecated Use shouldAllowBindWith instead.
     */
    public function shouldAuthenticateWith(string $dn): static
    {
        return $this->shouldAllowBindWith($dn);
    }

    /**
     * Set the error number of a failed bind attempt.
     */
    public function shouldReturnErrorNumber(int $number = 1): static
    {
        $this->errNo = $number;

        return $this;
    }

    /**
     * Set the last error of a failed bind attempt.
     */
    public function shouldReturnError(string $message): static
    {
        $this->lastError = $message;

        return $this;
    }

    /**
     * Set the diagnostic message of a failed bind attempt.
     */
    public function shouldReturnDiagnosticMessage(?string $message): static
    {
        $this->diagnosticMessage = $message;

        return $this;
    }

    /**
     * Return a fake error number.
     */
    public function errNo(): int
    {
        return $this->errNo;
    }

    /**
     * Return a fake error.
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Return a fake diagnostic message.
     */
    public function getDiagnosticMessage(): ?string
    {
        return $this->diagnosticMessage;
    }

    /**
     * Return a fake detailed error.
     */
    public function getDetailedError(): DetailedError
    {
        return new DetailedError(
            $this->errNo(),
            $this->getLastError(),
            $this->getDiagnosticMessage()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getEntries(mixed $result): array
    {
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getValuesLen(mixed $entry, string $attribute): array|false
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function compare(string $dn, string $attribute, string $value, ?array $controls = null): bool|int
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function setRebindCallback(callable $callback): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getFirstEntry(mixed $result): mixed
    {
        return $this->executeFailableOperation(function () use ($result) {
            return ldap_first_entry($this->connection, $result);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getNextEntry(mixed $entry): mixed
    {
        return $this->executeFailableOperation(function () use ($entry) {
            return ldap_next_entry($this->connection, $entry);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributes(mixed $entry): array|false
    {
        return $this->executeFailableOperation(function () use ($entry) {
            return ldap_get_attributes($this->connection, $entry);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function countEntries(mixed $result): int
    {
        return $this->executeFailableOperation(function () use ($result) {
            return ldap_count_entries($this->connection, $result);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isUsingSSL(): bool
    {
        return $this->hasExpectations(__FUNCTION__)
            ? $this->resolveExpectation(__FUNCTION__)
            : $this->useSSL;
    }

    /**
     * {@inheritdoc}
     */
    public function isUsingTLS(): bool
    {
        return $this->hasExpectations(__FUNCTION__)
            ? $this->resolveExpectation(__FUNCTION__)
            : $this->useTLS;
    }

    /**
     * {@inheritdoc}
     */
    public function isBound(): bool
    {
        return $this->hasExpectations(__FUNCTION__)
            ? $this->resolveExpectation(__FUNCTION__)
            : $this->bound;
    }

    /**
     * {@inheritdoc}
     */
    public function setOption(int $option, mixed $value): bool
    {
        return $this->hasExpectations(__FUNCTION__)
            ? $this->resolveExpectation(__FUNCTION__, func_get_args())
            : true;
    }

    /**
     * {@inheritdoc}
     */
    public function getOption(int $option, mixed &$value = null): mixed
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function startTLS(): bool
    {
        return $this->secure = $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function connect(string|array $hosts = [], int $port = 389, ?string $protocol = null): bool
    {
        $this->bound = false;
        $this->protocol = $protocol;
        $this->host = $this->makeConnectionUris($hosts, $port);

        return $this->connection = $this->hasExpectations(__FUNCTION__)
            ? $this->resolveExpectation(__FUNCTION__, func_get_args())
            : true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        $this->bound = false;
        $this->secure = false;

        $this->host = null;
        $this->protocol = null;
        $this->connection = null;

        return $this->hasExpectations(__FUNCTION__)
            ? $this->resolveExpectation(__FUNCTION__)
            : true;
    }

    /**
     * {@inheritdoc}
     */
    public function bind(?string $dn = null, ?string $password = null, ?array $controls = null): LdapResultResponse
    {
        $result = $this->resolveExpectation(__FUNCTION__, func_get_args());

        $this->handleBindResponse($result);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function saslBind(?string $dn = null, ?string $password = null, ?array $options = null): bool
    {
        return $this->bound = $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $dn, string $filter, array $fields, bool $onlyAttributes = false, int $size = 0, int $time = 0, int $deref = LDAP_DEREF_NEVER, ?array $controls = null): mixed
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function list(string $dn, string $filter, array $fields, bool $onlyAttributes = false, int $size = 0, int $time = 0, int $deref = LDAP_DEREF_NEVER, ?array $controls = null): mixed
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $dn, string $filter, array $fields, bool $onlyAttributes = false, int $size = 0, int $time = 0, int $deref = LDAP_DEREF_NEVER, ?array $controls = null): mixed
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function parseResult(mixed $result, int &$errorCode = 0, ?string &$dn = null, ?string &$errorMessage = null, ?array &$referrals = null, ?array &$controls = null): LdapResultResponse|false
    {
        return $this->hasExpectations(__FUNCTION__)
            ? $this->resolveExpectation(__FUNCTION__, func_get_args())
            : new LdapResultResponse;
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $dn, array $entry): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $dn): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $dn, string $newRdn, string $newParent, bool $deleteOldRdn = false): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function modify(string $dn, array $entry): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function modifyBatch(string $dn, array $values): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function modAdd(string $dn, array $entry): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function modReplace(string $dn, array $entry): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function modDelete(string $dn, array $entry): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function freeResult(mixed $result): bool
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function err2Str(int $number): string
    {
        return $this->resolveExpectation(__FUNCTION__, func_get_args());
    }

    /**
     * Resolve the methods expectations.
     *
     * @throws LdapExpectationException
     */
    protected function resolveExpectation(string $method, array $args = []): mixed
    {
        foreach ($this->getExpectations($method) as $key => $expectation) {
            $this->assertMethodArgumentsMatch($method, $expectation->getExpectedArgs(), $args);

            $expectation->decrementExpectedCount();

            if ($expectation->getExpectedCount() === 0) {
                $this->removeExpectation($method, $key);
            }

            if (! is_null($exception = $expectation->getExpectedException())) {
                throw $exception;
            }

            if ($expectation->isReturningError()) {
                $this->applyExpectationError($expectation);
            }

            return $expectation->getExpectedValue();
        }

        throw new LdapExpectationException("LDAP method [$method] was unexpected.");
    }

    /**
     * Apply the expectation error to the fake.
     */
    protected function applyExpectationError(LdapExpectation $expectation): void
    {
        $this->shouldReturnError($expectation->getExpectedErrorMessage());
        $this->shouldReturnErrorNumber($expectation->getExpectedErrorCode());
        $this->shouldReturnDiagnosticMessage($expectation->getExpectedErrorDiagnosticMessage());
    }

    /**
     * Assert that the expectations have been called their minimum amount of times.
     *
     * @throws LdapExpectationException
     */
    public function assertMinimumExpectationCounts(): void
    {
        foreach ($this->expectations as $method => $expectations) {
            foreach ($expectations as $expectation) {
                if (! $expectation->isIndefinite() && $expectation->getExpectedCount()) {
                    $remaining = ($original = $expectation->getOriginalExpectedCount()) - $expectation->getExpectedCount();

                    throw new LdapExpectationException("Method [$method] should be called $original times but was called $remaining times.");
                }
            }
        }
    }

    /**
     * Assert that the expected arguments match the operations arguments.
     *
     * @param  Constraint[]  $expectedArgs
     */
    protected function assertMethodArgumentsMatch(string $method, array $expectedArgs = [], array $methodArgs = []): void
    {
        foreach ($expectedArgs as $key => $constraint) {
            $argNumber = $key + 1;

            PHPUnit::assertArrayHasKey(
                $key,
                $methodArgs,
                "LDAP method [$method] argument #$argNumber does not exist."
            );

            $constraint->evaluate(
                $methodArgs[$key],
                "LDAP method [$method] expectation failed."
            );
        }
    }
}
