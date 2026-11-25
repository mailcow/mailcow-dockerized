<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

use Generator;

/**
 * Creates a promise that is resolved using a generator that yields values or
 * promises (somewhat similar to C#'s async keyword).
 *
 * When called, the Coroutine::of method will start an instance of the generator
 * and returns a promise that is fulfilled with its final yielded value.
 *
 * Control is returned back to the generator when the yielded promise settles.
 * This can lead to less verbose code when doing lots of sequential async calls
 * with minimal processing in between.
 *
 *     use GuzzleHttp\Promise;
 *
 *     function createPromise($value) {
 *         return new Promise\FulfilledPromise($value);
 *     }
 *
 *     $promise = Promise\Coroutine::of(function () {
 *         $value = (yield createPromise('a'));
 *         try {
 *             $value = (yield createPromise($value . 'b'));
 *         } catch (\Throwable $e) {
 *             // The promise was rejected.
 *         }
 *         yield $value . 'c';
 *     });
 *
 *     // Outputs "abc"
 *     $promise->then(function ($v) { echo $v; });
 *
 * @template TValue = mixed
 * @template TReason = mixed
 *
 * @implements PromiseInterface<TValue, TReason>
 *
 * @see https://github.com/petkaantonov/bluebird/blob/master/API.md#generators inspiration
 */
final class Coroutine implements PromiseInterface
{
    use NonSerializableTrait;

    /**
     * @var PromiseInterface<mixed, mixed>|null
     */
    private ?PromiseInterface $currentPromise = null;

    /**
     * @var Generator<mixed, mixed, mixed, mixed>
     */
    private Generator $generator;

    /**
     * @var Promise<TValue, TReason>
     */
    private PromiseInterface $result;

    /**
     * @param callable(): Generator<mixed, mixed, mixed, mixed> $generatorFn
     */
    public function __construct(callable $generatorFn)
    {
        $this->generator = $generatorFn();
        $this->result = new Promise(function (): void {
            while (isset($this->currentPromise)) {
                $this->currentPromise->wait();
            }
        });
        try {
            $this->nextCoroutine($this->generator->current());
        } catch (\Throwable $throwable) {
            $this->result->reject($throwable);
        }
    }

    /**
     * Create a new coroutine.
     *
     * @param callable(): Generator<mixed, mixed, mixed, mixed> $generatorFn
     *
     * @return self<mixed, mixed>
     */
    public static function of(callable $generatorFn): self
    {
        return new self($generatorFn);
    }

    /**
     * @template TFulfilledValue = never
     * @template TFulfilledReason = never
     * @template TRejectedValue = never
     * @template TRejectedReason = never
     *
     * @param (callable(TValue): (TFulfilledValue|PromiseInterface<TFulfilledValue, TFulfilledReason>))|null $onFulfilled Invoked when the promise fulfills.
     * @param (callable(TReason): (TRejectedValue|PromiseInterface<TRejectedValue, TRejectedReason>))|null   $onRejected  Invoked when the promise is rejected.
     *
     * @return PromiseInterface<($onFulfilled is null ? TValue : TFulfilledValue)|($onRejected is null ? never : TRejectedValue), ($onFulfilled is null ? never : TFulfilledReason|\Throwable)|($onRejected is null ? TReason : TRejectedReason|\Throwable)>
     */
    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null
    ): PromiseInterface {
        return $this->result->then($onFulfilled, $onRejected);
    }

    /**
     * @template TRejectedValue = never
     * @template TRejectedReason = never
     *
     * @param callable(TReason): (TRejectedValue|PromiseInterface<TRejectedValue, TRejectedReason>) $onRejected Invoked when the promise is rejected.
     *
     * @return PromiseInterface<TValue|TRejectedValue, TRejectedReason|\Throwable>
     */
    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->result->otherwise($onRejected);
    }

    public function wait(bool $unwrap = true)
    {
        return $this->result->wait($unwrap);
    }

    public function getState(): string
    {
        return $this->result->getState();
    }

    public function resolve($value = null): void
    {
        $this->result->resolve($value);
    }

    public function reject($reason): void
    {
        $this->result->reject($reason);
    }

    public function cancel(): void
    {
        if (isset($this->currentPromise)) {
            $this->currentPromise->cancel();
        }

        $this->result->cancel();
    }

    private function nextCoroutine($yielded): void
    {
        $this->currentPromise = Create::promiseFor($yielded)
            ->then([$this, '_handleSuccess'], [$this, '_handleFailure']);
    }

    /**
     * @internal
     */
    public function _handleSuccess($value): void
    {
        $this->currentPromise = null;

        try {
            $next = $this->generator->send($value);
            if ($this->generator->valid()) {
                $this->nextCoroutine($next);
            } else {
                $this->result->resolve($value);
            }
        } catch (\Throwable $throwable) {
            $this->result->reject($throwable);
        }
    }

    /**
     * @internal
     */
    public function _handleFailure($reason): void
    {
        $this->currentPromise = null;

        try {
            $nextYield = $this->generator->throw(Create::exceptionFor($reason));
            // The throw was caught, so keep iterating on the coroutine
            $this->nextCoroutine($nextYield);
        } catch (\Throwable $throwable) {
            $this->result->reject($throwable);
        }
    }
}
