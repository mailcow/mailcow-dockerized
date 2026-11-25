<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

final class Create
{
    private function __construct()
    {
    }

    /**
     * Returns `$value` when it is already a Guzzle promise, wraps foreign
     * thenables in a Guzzle promise, or returns a fulfilled promise for plain
     * values.
     *
     * @template TValue
     * @template TPromise of PromiseInterface<mixed, mixed> = PromiseInterface<mixed, mixed>
     *
     * @param TValue|TPromise $value Promise or value.
     *
     * @return ($value is PromiseInterface ? TPromise : FulfilledPromise<TValue, never>)
     */
    public static function promiseFor($value): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            return $value;
        }

        // Return a Guzzle promise that shadows the given promise.
        if (is_object($value) && method_exists($value, 'then')) {
            $wfn = method_exists($value, 'wait') ? [$value, 'wait'] : null;
            $cfn = method_exists($value, 'cancel') ? [$value, 'cancel'] : null;
            $promise = new Promise($wfn, $cfn);
            $value->then([$promise, 'resolve'], [$promise, 'reject']);

            return $promise;
        }

        return new FulfilledPromise($value);
    }

    /**
     * Returns `$reason` when it is already a promise, or returns a rejected
     * promise for plain reasons.
     *
     * @template TReason
     * @template TValue = never
     * @template TPromise of PromiseInterface<mixed, mixed> = PromiseInterface<mixed, mixed>
     *
     * @param TReason|TPromise $reason Promise or reason.
     *
     * @return ($reason is PromiseInterface ? TPromise : RejectedPromise<TValue, TReason>)
     */
    public static function rejectionFor($reason): PromiseInterface
    {
        if ($reason instanceof PromiseInterface) {
            return $reason;
        }

        return new RejectedPromise($reason);
    }

    /**
     * Returns throwable reasons as-is, or wraps non-throwable reasons in
     * `RejectionException`.
     *
     * @template TReason
     *
     * @param TReason $reason
     */
    public static function exceptionFor($reason): \Throwable
    {
        if ($reason instanceof \Throwable) {
            return $reason;
        }

        return new RejectionException($reason);
    }

    /**
     * Returns an iterator for arrays, iterators, iterator aggregates, and
     * traversables.
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $value
     *
     * @return \Iterator<TKey, TValue>
     */
    public static function iterFor(iterable $value): \Iterator
    {
        if ($value instanceof \Iterator) {
            return $value;
        }

        if (is_array($value)) {
            return new \ArrayIterator($value);
        }

        if ($value instanceof \IteratorAggregate) {
            return self::iterFor($value->getIterator());
        }

        return new \IteratorIterator($value);
    }
}
