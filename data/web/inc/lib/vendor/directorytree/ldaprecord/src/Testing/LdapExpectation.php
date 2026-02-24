<?php

namespace LdapRecord\Testing;

use Closure;
use Exception;
use LdapRecord\LdapRecordException;
use LdapRecord\LdapResultResponse;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\IsEqual;
use UnexpectedValueException;

class LdapExpectation
{
    /**
     * The value to return from the expectation.
     */
    protected mixed $value = null;

    /**
     * The exception to throw from the expectation.
     */
    protected ?Exception $exception = null;

    /**
     * The tracked number of times the expectation should be called.
     */
    protected int $count = 1;

    /**
     * The original number of times the expectation should be called.
     */
    protected int $originalCount = 1;

    /**
     * The actual number of times the expectation was called.
     */
    protected int $called = 0;

    /**
     * The method that the expectation belongs to.
     */
    protected ?string $method = null;

    /**
     * The methods argument's.
     */
    protected array $args = [];

    /**
     * Whether the same expectation should be returned indefinitely.
     */
    protected bool $indefinitely = true;

    /**
     * Whether the expectation should return an error.
     */
    protected bool $errors = false;

    /**
     * The error code to return.
     */
    protected int $errorCode = 1;

    /**
     * The error message to return.
     */
    protected string $errorMessage = 'Unknown error';

    /**
     * The diagnostic message string to return.
     */
    protected ?string $errorDiagnosticMessage = null;

    /**
     * Constructor.
     */
    public function __construct(string $method)
    {
        $this->method = $method;
    }

    /**
     * Set the arguments that the operation should receive.
     */
    public function with(mixed $args): static
    {
        $this->args = array_map(function ($arg) {
            if ($arg instanceof Closure) {
                return new Callback($arg);
            }

            if (! $arg instanceof Constraint) {
                return new IsEqual($arg);
            }

            return $arg;
        }, is_array($args) ? $args : func_get_args());

        return $this;
    }

    /**
     * Set the expected value to return.
     */
    public function andReturn(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Set the expected value to return true.
     */
    public function andReturnTrue(): static
    {
        return $this->andReturn(true);
    }

    /**
     * Set the expected value to return false.
     */
    public function andReturnFalse(): static
    {
        return $this->andReturn(false);
    }

    /**
     * The error message to return from the expectation.
     */
    public function andReturnError(int $errorCode = 1, string $errorMessage = 'Unknown error', ?string $diagnosticMessage = null): static
    {
        $this->errors = true;

        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->errorDiagnosticMessage = $diagnosticMessage;

        return $this;
    }

    /**
     * Return an error LDAP result response.
     */
    public function andReturnErrorResponse(int $code = 1, ?string $errorMessage = null): static
    {
        return $this->andReturnResponse($code, $errorMessage);
    }

    /**
     * Return an LDAP result response.
     */
    public function andReturnResponse(
        int $errorCode = 0,
        ?string $matchedDn = null,
        ?string $errorMessage = null,
        array $referrals = [],
        array $controls = []
    ): static {
        return $this->andReturn(
            new LdapResultResponse($errorCode, $matchedDn, $errorMessage, $referrals, $controls)
        );
    }

    /**
     * Set the expected exception to throw.
     */
    public function andThrow(string|Exception $exception): static
    {
        if (is_string($exception)) {
            $exception = new LdapRecordException($exception);
        }

        $this->exception = $exception;

        return $this;
    }

    /**
     * Set the expectation to be only called once.
     */
    public function once(): static
    {
        return $this->times();
    }

    /**
     * Set the expectation to be only called twice.
     */
    public function twice(): static
    {
        return $this->times(2);
    }

    /**
     * Set the expectation to be called the given number of times.
     */
    public function times(int $count = 1): static
    {
        $this->indefinitely = false;

        $this->originalCount = $this->count = $count;

        return $this;
    }

    /**
     * Get the method the expectation belongs to.
     */
    public function getMethod(): string
    {
        if (is_null($this->method)) {
            throw new UnexpectedValueException('An expectation must have a method.');
        }

        return $this->method;
    }

    /**
     * Get the expected call count.
     */
    public function getExpectedCount(): int
    {
        return $this->count;
    }

    /**
     * Get the original expected call count.
     */
    public function getOriginalExpectedCount(): int
    {
        return $this->originalCount;
    }

    /**
     * Get the count that the expectation was called.
     */
    public function getCalledCount(): int
    {
        return $this->called;
    }

    /**
     * Get the expected arguments.
     *
     * @return Constraint[]
     */
    public function getExpectedArgs(): array
    {
        return $this->args;
    }

    /**
     * Get the expected exception.
     */
    public function getExpectedException(): ?Exception
    {
        return $this->exception;
    }

    /**
     * Get the expected value.
     */
    public function getExpectedValue(): mixed
    {
        return $this->value;
    }

    /**
     * Determine whether the expectation can be called indefinitely.
     */
    public function isIndefinite(): bool
    {
        return $this->indefinitely;
    }

    /**
     * Determine whether the expectation is returning an error.
     */
    public function isReturningError(): bool
    {
        return $this->errors;
    }

    /**
     * Get the expected error code.
     */
    public function getExpectedErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * Get the expected error message.
     */
    public function getExpectedErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get the expected diagnostic message.
     */
    public function getExpectedErrorDiagnosticMessage(): ?string
    {
        return $this->errorDiagnosticMessage;
    }

    /**
     * Decrement the expected count of the expectation.
     */
    public function decrementExpectedCount(): static
    {
        if (! $this->indefinitely) {
            $this->count -= 1;
        }

        $this->called++;

        return $this;
    }
}
