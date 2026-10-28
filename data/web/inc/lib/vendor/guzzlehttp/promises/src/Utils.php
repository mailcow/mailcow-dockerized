<?php

declare(strict_types=1);

namespace GuzzleHttp\Promise;

final class Utils
{
    private function __construct()
    {
    }

    /**
     * Get the global task queue used for promise resolution.
     *
     * This task queue MUST be run in an event loop in order for promises to be
     * settled asynchronously. It will be automatically run when synchronously
     * waiting on a promise.
     *
     * <code>
     * while ($eventLoop->isRunning()) {
     *     GuzzleHttp\Promise\Utils::queue()->run();
     * }
     * </code>
     *
     * @param TaskQueueInterface|null $assign Optionally specify a new queue instance.
     */
    public static function queue(?TaskQueueInterface $assign = null): TaskQueueInterface
    {
        static $queue;

        if ($assign) {
            $queue = $assign;
        } elseif (!$queue) {
            $queue = new TaskQueue();
        }

        return $queue;
    }

    /**
     * Adds a task to the global queue and returns a promise that is fulfilled
     * or rejected with the task result.
     *
     * @template TValue
     *
     * @param callable(): TValue $task Task function to run.
     *
     * @return PromiseInterface<TValue, \Throwable>
     */
    public static function task(callable $task): PromiseInterface
    {
        $queue = self::queue();
        $promise = new Promise([$queue, 'run']);
        $queue->add(function () use ($task, $promise): void {
            try {
                if (State::isPending($promise)) {
                    $promise->resolve($task());
                }
            } catch (\Throwable $e) {
                $promise->reject($e);
            }
        });

        return $promise;
    }

    /**
     * Synchronously waits on a promise to settle and returns an inspection
     * state array.
     *
     * Returns a state associative array containing a "state" key mapping to a
     * valid promise state. If the state of the promise is "fulfilled", the
     * array will contain a "value" key mapping to the fulfilled value of the
     * promise. If the promise is rejected, the array will contain a "reason"
     * key mapping to the rejection reason of the promise.
     *
     * @template TValue
     * @template TReason
     *
     * @param PromiseInterface<TValue, TReason> $promise Promise to inspect.
     *
     * @return array{state: PromiseInterface::FULFILLED, value: TValue}|array{state: PromiseInterface::REJECTED, reason: TReason|\Throwable}|array{state: PromiseInterface::PENDING}
     */
    public static function inspect(PromiseInterface $promise): array
    {
        $result = null;
        $getResult = static function () use (&$result): ?array {
            return $result;
        };

        $inspection = $promise->then(
            static function ($value) use (&$result): void {
                $result = ['state' => PromiseInterface::FULFILLED, 'value' => $value];
            },
            static function ($reason) use (&$result): void {
                $result = ['state' => PromiseInterface::REJECTED, 'reason' => $reason];
            }
        );

        try {
            $inspection->wait(false);
        } catch (\Throwable $e) {
            $settled = $getResult();
            if (null !== $settled) {
                return $settled;
            }

            if (State::isSettled($promise)) {
                try {
                    self::queue()->run();
                } catch (\Throwable $queueError) {
                    return ['state' => PromiseInterface::REJECTED, 'reason' => $queueError];
                }

                $settled = $getResult();
                if (null !== $settled) {
                    return $settled;
                }
            }

            return ['state' => PromiseInterface::REJECTED, 'reason' => $e];
        }

        return $getResult() ?? ['state' => $promise->getState()];
    }

    /**
     * Waits on all of the provided promises, but does not unwrap rejected
     * promises as a thrown exception.
     *
     * Returns an array of inspection state arrays keyed like the input
     * iterable.
     *
     * @see inspect for the inspection state array format.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TReason
     *
     * @param iterable<TKey, PromiseInterface<TValue, TReason>> $promises Traversable of promises to wait upon.
     *
     * @return array<TKey, array{state: PromiseInterface::FULFILLED, value: TValue}|array{state: PromiseInterface::REJECTED, reason: TReason|\Throwable}|array{state: PromiseInterface::PENDING}>
     */
    public static function inspectAll(iterable $promises): array
    {
        $results = [];
        foreach ($promises as $key => $promise) {
            $results[$key] = self::inspect($promise);
        }

        return $results;
    }

    /**
     * Waits on all of the provided promises and returns the fulfilled values.
     *
     * Returns an array that contains the value of each promise (in the same
     * order the promises were provided). An exception is thrown if any of the
     * promises are rejected.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TReason
     *
     * @param iterable<TKey, PromiseInterface<TValue, TReason>> $promises Iterable of PromiseInterface objects to wait on.
     *
     * @return array<TKey, TValue>
     *
     * @throws \Throwable on error
     */
    public static function unwrap(iterable $promises): array
    {
        $results = [];
        foreach ($promises as $key => $promise) {
            $results[$key] = $promise->wait();
        }

        return $results;
    }

    /**
     * Given an array of promises, return a promise that is fulfilled when all
     * the items in the array are fulfilled.
     *
     * The promise's fulfillment value is an array with fulfillment values at
     * respective positions to the original array. If any promise in the array
     * rejects, the returned promise is rejected with the rejection reason.
     *
     * The config array accepts a concurrency option for lazy iterables. Other
     * config keys are ignored by this wrapper.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TReason
     *
     * @param iterable<TKey, TValue|PromiseInterface<TValue, TReason>> $promises  Promises or values.
     * @param bool                                                     $recursive If true, resolves newly-added entries until no unprocessed entries or pending promises remain.
     * @param array{concurrency?: int|(callable(int): int)}            $config    Configuration options.
     *
     * @return PromiseInterface<array<TKey, TValue>, TReason|\Throwable>
     */
    public static function all(iterable $promises, bool $recursive = false, array $config = []): PromiseInterface
    {
        $results = [];
        $promise = Each::of(
            $promises,
            function ($value, $idx) use (&$results): void {
                $results[$idx] = $value;
            },
            function ($reason, $idx, PromiseInterface $aggregate): void {
                if (State::isPending($aggregate)) {
                    $aggregate->reject($reason);
                }
            },
            $config
        )->then(function () use (&$results) {
            ksort($results);

            return $results;
        });

        if (true === $recursive) {
            $promise = $promise->then(function ($results) use (&$promises, $config) {
                if (self::shouldRecurse($promises, $results)) {
                    return self::all($promises, true, $config);
                }

                return $results;
            });
        }

        return $promise;
    }

    /**
     * Initiate a competitive race between multiple promises or values (values
     * will become immediately fulfilled promises).
     *
     * When count amount of promises have been fulfilled, the returned promise
     * is fulfilled with an array that contains the fulfillment values of the
     * winners, in the order they appear in the input.
     *
     * This promise is rejected with a {@see AggregateException} if the number
     * of fulfilled promises is less than the desired $count.
     *
     * @template TValue
     * @template TReason
     *
     * @param int                                                $count    Total number of promises.
     * @param iterable<TValue|PromiseInterface<TValue, TReason>> $promises Promises or values.
     *
     * @return PromiseInterface<list<TValue>, \Throwable>
     */
    public static function some(int $count, iterable $promises): PromiseInterface
    {
        $results = [];
        $rejections = [];

        $promise = Each::of(
            $promises,
            function ($value, $idx, PromiseInterface $p) use (&$results, $count): void {
                if (State::isSettled($p)) {
                    return;
                }
                $results[$idx] = $value;
                if (count($results) >= $count) {
                    $p->resolve(null);
                }
            },
            function ($reason) use (&$rejections): void {
                $rejections[] = $reason;
            }
        )->then(
            function () use (&$results, &$rejections, $count) {
                if (count($results) !== $count) {
                    throw new AggregateException(
                        'Not enough promises to fulfill count',
                        $rejections
                    );
                }
                ksort($results);

                return array_values($results);
            }
        );

        /** @var PromiseInterface<list<TValue>, \Throwable> $promise */
        return $promise;
    }

    /**
     * Like some(), with 1 as count. However, if the promise fulfills, the
     * fulfillment value is not an array of 1 but the value directly.
     *
     * @template TValue
     * @template TReason
     *
     * @param iterable<TValue|PromiseInterface<TValue, TReason>> $promises Promises or values.
     *
     * @return PromiseInterface<TValue, \Throwable>
     */
    public static function any(iterable $promises): PromiseInterface
    {
        return self::some(1, $promises)->then(function (array $values) {
            return $values[0];
        });
    }

    /**
     * Returns a promise that is fulfilled when all of the provided promises
     * have been fulfilled or rejected.
     *
     * The returned promise is fulfilled with an array of inspection state
     * arrays.
     *
     * The config array accepts a concurrency option for lazy iterables. Other
     * config keys are ignored by this wrapper.
     *
     * @see inspect for the inspection state array format.
     *
     * @template TKey of array-key
     * @template TValue
     * @template TReason
     *
     * @param iterable<TKey, TValue|PromiseInterface<TValue, TReason>> $promises  Promises or values.
     * @param bool                                                     $recursive If true, settles newly-added entries until no unprocessed entries or pending promises remain.
     * @param array{concurrency?: int|(callable(int): int)}            $config    Configuration options.
     *
     * @return PromiseInterface<array<TKey, array{state: PromiseInterface::FULFILLED, value: TValue}|array{state: PromiseInterface::REJECTED, reason: TReason|\Throwable}>, \Throwable>
     */
    public static function settle(iterable $promises, bool $recursive = false, array $config = []): PromiseInterface
    {
        $results = [];

        $promise = Each::of(
            $promises,
            function ($value, $idx) use (&$results): void {
                $results[$idx] = ['state' => PromiseInterface::FULFILLED, 'value' => $value];
            },
            function ($reason, $idx) use (&$results): void {
                $results[$idx] = ['state' => PromiseInterface::REJECTED, 'reason' => $reason];
            },
            $config
        )->then(function () use (&$results) {
            ksort($results);

            return $results;
        });

        if (true === $recursive) {
            $promise = $promise->then(function ($results) use (&$promises, $config) {
                if (self::shouldRecurse($promises, $results)) {
                    return self::settle($promises, true, $config);
                }

                return $results;
            });
        }

        return $promise;
    }

    /**
     * @template TKey of array-key
     *
     * @param iterable<TKey, mixed> $promises Promises or values.
     * @param array<TKey, mixed>    $results  Results already collected for a pass.
     */
    private static function shouldRecurse(iterable $promises, array $results): bool
    {
        // A consumed generator cannot be traversed again, so a recursive
        // pass has nothing further to observe.
        if ($promises instanceof \Generator) {
            return false;
        }

        foreach ($promises as $key => $promise) {
            if (!array_key_exists($key, $results)) {
                return true;
            }

            if ($promise instanceof PromiseInterface && State::isPending($promise)) {
                return true;
            }
        }

        return false;
    }
}
