<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

/**
 * A promise that has been fulfilled.
 *
 * Thenning off of this promise will invoke the onFulfilled callback
 * immediately and ignore other callbacks.
 *
 * @template TValue = mixed
 * @template TReason = never
 *
 * @implements PromiseInterface<TValue, TReason>
 *
 * @final
 */
class FulfilledPromise implements PromiseInterface
{
    /** @var TValue */
    private $value;

    /**
     * @param TValue $value
     */
    public function __construct($value)
    {
        if (is_object($value) && method_exists($value, 'then')) {
            throw new \InvalidArgumentException(
                'You cannot create a FulfilledPromise with a promise.'
            );
        }

        $this->value = $value;
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
     * @return ($onFulfilled is null ? self<TValue, TReason> : PromiseInterface<TFulfilledValue, TFulfilledReason|\Throwable>)
     */
    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null
    ): PromiseInterface {
        // Return itself if there is no onFulfilled function.
        if (!$onFulfilled) {
            return $this;
        }

        $queue = Utils::queue();
        $p = new Promise([$queue, 'run']);
        $value = $this->value;
        $queue->add(static function () use ($p, $value, $onFulfilled): void {
            if (State::isPending($p)) {
                try {
                    $p->resolve($onFulfilled($value));
                } catch (\Throwable $e) {
                    $p->reject($e);
                }
            }
        });

        return $p;
    }

    /**
     * @param callable(TReason): mixed $onRejected Invoked when the promise is rejected.
     *
     * @return self<TValue, TReason>
     */
    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
    }

    public function wait(bool $unwrap = true)
    {
        return $unwrap ? $this->value : null;
    }

    public function getState(): string
    {
        return self::FULFILLED;
    }

    public function resolve($value = null): void
    {
        if ($value !== $this->value) {
            throw new \LogicException('Cannot resolve a fulfilled promise');
        }
    }

    public function reject($reason): void
    {
        throw new \LogicException('Cannot reject a fulfilled promise');
    }

    public function cancel(): void
    {
        // pass
    }
}
