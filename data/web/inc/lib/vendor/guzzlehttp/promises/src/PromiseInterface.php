<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

/**
 * A promise represents the eventual result of an asynchronous operation.
 *
 * The primary way of interacting with a promise is through its then method,
 * which registers callbacks to receive either a promise’s eventual value or
 * the reason why the promise cannot be fulfilled.
 *
 * @template TValue = mixed
 * @template TReason = mixed
 *
 * @see https://promisesaplus.com/
 */
interface PromiseInterface
{
    public const PENDING = 'pending';
    public const FULFILLED = 'fulfilled';
    public const REJECTED = 'rejected';

    /**
     * Appends fulfillment and rejection handlers to the promise, and returns
     * a new promise resolving to the return value of the called handler.
     *
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
    ): PromiseInterface;

    /**
     * Appends a rejection handler callback to the promise, and returns a new
     * promise resolving to the return value of the callback if it is called,
     * or to its original fulfillment value if the promise is instead
     * fulfilled.
     *
     * @template TRejectedValue = never
     * @template TRejectedReason = never
     *
     * @param callable(TReason): (TRejectedValue|PromiseInterface<TRejectedValue, TRejectedReason>) $onRejected Invoked when the promise is rejected.
     *
     * @return PromiseInterface<TValue|TRejectedValue, TRejectedReason|\Throwable>
     */
    public function otherwise(callable $onRejected): PromiseInterface;

    /**
     * Get the state of the promise ("pending", "rejected", or "fulfilled").
     *
     * The three states can be checked against the constants defined on
     * PromiseInterface: PENDING, FULFILLED, and REJECTED.
     *
     * The state describes how this promise was settled, not the eventual
     * outcome: a promise that was resolved with another promise, directly or
     * by returning one from a then() handler, reports FULFILLED while the
     * inner promise may still be pending, and wait() can still throw if the
     * inner promise rejects. Poll the state only on promises settled with
     * plain values, or call wait() first.
     *
     * @return self::PENDING|self::FULFILLED|self::REJECTED
     *
     * @see https://github.com/guzzle/promises/issues/101
     */
    public function getState(): string;

    /**
     * Resolve the promise with the given value, or with null if no value is given.
     *
     * @param TValue|PromiseInterface<TValue, TReason>|null $value
     *
     * @throws \LogicException if the promise is already settled with a
     *                         conflicting resolution.
     */
    public function resolve($value = null): void;

    /**
     * Reject the promise with the given reason.
     *
     * @param TReason $reason
     *
     * @throws \LogicException if the promise is already settled with a
     *                         conflicting resolution.
     */
    public function reject($reason): void;

    /**
     * Cancels the promise if possible.
     *
     * @see https://github.com/promises-aplus/cancellation-spec/issues/7
     */
    public function cancel(): void;

    /**
     * Waits until the promise completes if possible.
     *
     * Pass $unwrap as true to unwrap the result of the promise, either
     * returning the resolved value or throwing the rejected exception.
     *
     * If the promise cannot be waited on, then the promise will be rejected.
     *
     * @return ($unwrap is true ? TValue : null)
     *
     * @throws \LogicException if the promise has no wait function or if the
     *                         promise does not settle after waiting.
     */
    public function wait(bool $unwrap = true);
}
