<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

final class Each
{
    private function __construct()
    {
    }

    /**
     * Given an iterator that yields promises or values, returns a promise that
     * is fulfilled with a null value when the iterator has been consumed or
     * the aggregate promise has been fulfilled or rejected.
     *
     * $onFulfilled is a function that accepts the fulfilled value, iterable
     * key, and the aggregate promise. The callback can invoke any necessary
     * side effects and choose to resolve or reject the aggregate if needed.
     *
     * $onRejected is a function that accepts the rejection reason, iterable
     * key, and the aggregate promise. The callback can invoke any necessary
     * side effects and choose to resolve or reject the aggregate if needed.
     *
     * The config array accepts a concurrency option matching {@see ofLimit}.
     * Other config keys are ignored by this wrapper.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TReason
     *
     * @param iterable<TKey, TValue|PromiseInterface<TValue, TReason>>              $iterable    Iterator or array to iterate over.
     * @param (callable(TValue, TKey, PromiseInterface<mixed, mixed>): mixed)|null  $onFulfilled
     * @param (callable(TReason, TKey, PromiseInterface<mixed, mixed>): mixed)|null $onRejected
     * @param array{concurrency?: int|(callable(int): int)}                         $config      Configuration options.
     *
     * @return PromiseInterface<mixed, mixed>
     */
    public static function of(
        iterable $iterable,
        ?callable $onFulfilled = null,
        ?callable $onRejected = null,
        array $config = []
    ): PromiseInterface {
        $eachConfig = [];

        if (null !== $onFulfilled) {
            $eachConfig['fulfilled'] = $onFulfilled;
        }

        if (null !== $onRejected) {
            $eachConfig['rejected'] = $onRejected;
        }

        if (isset($config['concurrency'])) {
            $eachConfig['concurrency'] = $config['concurrency'];
        }

        return (new EachPromise($iterable, $eachConfig))->promise();
    }

    /**
     * Like of, but only allows a certain number of outstanding promises at any
     * given time.
     *
     * $concurrency may be an integer or a function that accepts the number of
     * pending promises and returns a numeric concurrency limit value to allow
     * for a dynamic concurrency size.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TReason
     *
     * @param iterable<TKey, TValue|PromiseInterface<TValue, TReason>>              $iterable
     * @param int|(callable(int): int)                                              $concurrency
     * @param (callable(TValue, TKey, PromiseInterface<mixed, mixed>): mixed)|null  $onFulfilled
     * @param (callable(TReason, TKey, PromiseInterface<mixed, mixed>): mixed)|null $onRejected
     *
     * @return PromiseInterface<mixed, mixed>
     */
    public static function ofLimit(
        iterable $iterable,
        $concurrency,
        ?callable $onFulfilled = null,
        ?callable $onRejected = null
    ): PromiseInterface {
        return self::of($iterable, $onFulfilled, $onRejected, ['concurrency' => $concurrency]);
    }

    /**
     * Like ofLimit, but rejects the aggregate promise on the first rejection.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TReason
     *
     * @param iterable<TKey, TValue|PromiseInterface<TValue, TReason>>             $iterable
     * @param int|(callable(int): int)                                             $concurrency
     * @param (callable(TValue, TKey, PromiseInterface<mixed, mixed>): mixed)|null $onFulfilled
     *
     * @return PromiseInterface<mixed, mixed>
     */
    public static function ofLimitAll(
        iterable $iterable,
        $concurrency,
        ?callable $onFulfilled = null
    ): PromiseInterface {
        return self::ofLimit(
            $iterable,
            $concurrency,
            $onFulfilled,
            function ($reason, $idx, PromiseInterface $aggregate): void {
                $aggregate->reject($reason);
            }
        );
    }
}
