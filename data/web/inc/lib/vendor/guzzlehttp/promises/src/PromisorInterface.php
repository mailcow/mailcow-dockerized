<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

/**
 * Interface used with classes that return a promise.
 *
 * @template TValue = mixed
 * @template TReason = mixed
 */
interface PromisorInterface
{
    /**
     * Returns a promise.
     *
     * @return PromiseInterface<TValue, TReason>
     */
    public function promise(): PromiseInterface;
}
