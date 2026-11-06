<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

/**
 * @deprecated Is will be removed in guzzlehttp/promises:4.0. Use State instead.
 */
final class Is
{
    private function __construct()
    {
    }

    /**
     * Returns true if a promise is pending.
     *
     * @deprecated Is::pending() will be removed in guzzlehttp/promises:4.0. Use
     *             State::isPending() instead.
     */
    public static function pending(PromiseInterface $promise): bool
    {
        \trigger_deprecation(
            'guzzlehttp/promises',
            '3.1',
            '%s() is deprecated and will be removed in 4.0. Use %s::isPending() instead.',
            __METHOD__,
            State::class
        );

        return State::isPending($promise);
    }

    /**
     * Returns true if a promise is fulfilled or rejected.
     *
     * @deprecated Is::settled() will be removed in guzzlehttp/promises:4.0. Use
     *             State::isSettled() instead.
     */
    public static function settled(PromiseInterface $promise): bool
    {
        \trigger_deprecation(
            'guzzlehttp/promises',
            '3.1',
            '%s() is deprecated and will be removed in 4.0. Use %s::isSettled() instead.',
            __METHOD__,
            State::class
        );

        return State::isSettled($promise);
    }

    /**
     * Returns true if a promise is fulfilled.
     *
     * @deprecated Is::fulfilled() will be removed in guzzlehttp/promises:4.0.
     *             Use State::isFulfilled() instead.
     */
    public static function fulfilled(PromiseInterface $promise): bool
    {
        \trigger_deprecation(
            'guzzlehttp/promises',
            '3.1',
            '%s() is deprecated and will be removed in 4.0. Use %s::isFulfilled() instead.',
            __METHOD__,
            State::class
        );

        return State::isFulfilled($promise);
    }

    /**
     * Returns true if a promise is rejected.
     *
     * @deprecated Is::rejected() will be removed in guzzlehttp/promises:4.0.
     *             Use State::isRejected() instead.
     */
    public static function rejected(PromiseInterface $promise): bool
    {
        \trigger_deprecation(
            'guzzlehttp/promises',
            '3.1',
            '%s() is deprecated and will be removed in 4.0. Use %s::isRejected() instead.',
            __METHOD__,
            State::class
        );

        return State::isRejected($promise);
    }
}
