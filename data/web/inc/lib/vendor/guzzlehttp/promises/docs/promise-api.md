# Promise API

This reference summarizes the public API provided by `guzzlehttp/promises`.
Promise APIs are documented for static analysis as
`PromiseInterface<TValue, TReason>`. `TValue` is the fulfillment value type and
`TReason` is the rejection reason type. This typing is PHPDoc-only and does not
change runtime behavior.

Callbacks registered with `then()` are queued. They are invoked when the global
task queue runs, when a returned promise is waited on, or when the queue is
drained by the default shutdown handler.

## PromiseInterface and Promise

`PromiseInterface` defines the common promise contract and the states
`pending`, `fulfilled`, and `rejected`.

When creating a `Promise`, you can provide an optional `$waitFn` and
`$cancelFn`. `$waitFn` receives a boolean argument and is expected to resolve or
reject the promise. `$cancelFn` receives no arguments and is invoked when
`cancel()` is called.

```php
use GuzzleHttp\Promise\Promise;

$promise = new Promise(
    function (bool $recursive) use (&$promise) {
        $promise->resolve('waited');
    },
    function () {
        // Cancel the underlying operation, such as closing a socket.
    }
);

assert('waited' === $promise->wait());
```

A promise has the following methods:

- `then(?callable $onFulfilled = null, ?callable $onRejected = null) : PromiseInterface` appends fulfillment and rejection handlers and returns a new promise resolving to the return value of the called handler. If a handler is omitted, the original fulfillment value or rejection reason is forwarded.
- `otherwise(callable $onRejected) : PromiseInterface` appends a rejection handler and returns a new promise resolving to the callback result if called, or to the original fulfillment value if the promise is fulfilled.
- `wait(bool $unwrap = true) : mixed` synchronously waits on the promise. When `$unwrap` is `true`, fulfilled values are returned and rejected reasons are thrown. When `$unwrap` is `false`, the promise is settled without returning or throwing its result.
- `cancel() : void` attempts to cancel the promise and dependent promises.
- `getState() : string` returns `pending`, `fulfilled`, or `rejected`.
- `resolve($value = null) : void` fulfills the promise with `$value`, or with `null` if no value is given.
- `reject($reason) : void` rejects the promise with `$reason`.

## Settled Promises

`FulfilledPromise` represents an already fulfilled promise. Fulfillment
callbacks are still queued and run when the task queue runs or the returned
promise is waited on.

```php
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Utils;

$promise = new FulfilledPromise('value');
$promise->then(function ($value) {
    echo $value;
});

Utils::queue()->run();
```

`RejectedPromise` represents an already rejected promise. Rejection callbacks
are also queued and run when the queue is drained or the returned promise is
waited on.

```php
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\Utils;

$promise = new RejectedPromise('Error');
$promise->then(null, function ($reason) {
    echo $reason;
});

Utils::queue()->run();
```

## Utils

`GuzzleHttp\Promise\Utils` provides helpers for inspecting, aggregating, and
queuing promise work.

- `Utils::queue(?TaskQueueInterface $assign = null) : TaskQueueInterface` returns the global task queue, or assigns a replacement queue when `$assign` is provided.
- `Utils::task(callable $task) : PromiseInterface` adds a task to the global queue and returns a promise that is fulfilled or rejected with the task result.
- `Utils::inspect(PromiseInterface $promise) : array` waits for one promise to settle and returns an inspection array with `state` and either `value` or `reason`.
- `Utils::inspectAll(iterable $promises) : array` inspects each promise and returns inspection arrays keyed like the input iterable.
- `Utils::unwrap(iterable $promises) : array` waits on all promises and returns fulfilled values, throwing if any promise rejects.
- `Utils::all(iterable $promises, bool $recursive = false, array $config = []) : PromiseInterface` returns a promise fulfilled with all values, or rejected when any input rejects.
- `Utils::settle(iterable $promises, bool $recursive = false, array $config = []) : PromiseInterface` returns a promise fulfilled with inspection arrays after all inputs settle.
- `Utils::some(int $count, iterable $promises) : PromiseInterface` fulfills with the values of the first `$count` promises to fulfill, in the order they appear in the input, or rejects with `AggregateException` if too few fulfill.
- `Utils::any(iterable $promises) : PromiseInterface` fulfills with the first fulfilled value, or rejects with `AggregateException` if none fulfill.

`Utils::all()` and `Utils::settle()` accept `['concurrency' => 5]` or
`['concurrency' => callable]` for lazy iterables. This limits how many items are
pulled from the iterable at one time; it does not throttle promises that have
already been created or started.

```php
use GuzzleHttp\Promise\Utils;

$promise = Utils::all($promises, false, ['concurrency' => 5]);
```

## Create

`GuzzleHttp\Promise\Create` provides factories used by the promise
implementation and by callers that need to normalize values.

- `Create::promiseFor($value) : PromiseInterface` returns `$value` when it is already a Guzzle promise, wraps foreign thenables in a Guzzle promise, or returns a fulfilled promise for plain values.
- `Create::rejectionFor($reason) : PromiseInterface` returns `$reason` when it is already a promise, or returns a rejected promise for plain reasons.
- `Create::exceptionFor($reason) : Throwable` returns throwable reasons as-is, or wraps non-throwable reasons in `RejectionException`.
- `Create::iterFor(iterable $value) : Iterator` returns an iterator for arrays, iterators, iterator aggregates, and traversables.

## State

`GuzzleHttp\Promise\State` provides readable state checks:

- `State::isPending(PromiseInterface $promise) : bool`
- `State::isSettled(PromiseInterface $promise) : bool`
- `State::isFulfilled(PromiseInterface $promise) : bool`
- `State::isRejected(PromiseInterface $promise) : bool`

`GuzzleHttp\Promise\Is` is deprecated because PHP 8.6 deprecates `is` as a class
name, and will be removed in Guzzle Promises 4.0. Replace `Is::pending()`,
`Is::settled()`, `Is::fulfilled()`, and `Is::rejected()` with the matching
`State` methods.

## Each and EachPromise

`Each::of()` consumes an iterable of promises or values and invokes callbacks as
items settle. Fulfillment callbacks receive the fulfilled value, iterable key,
and aggregate promise. Rejection callbacks receive the rejection reason,
iterable key, and aggregate promise. Callback return values are ignored.

```php
use GuzzleHttp\Promise\Each;

$promise = Each::of($promises, $onFulfilled, $onRejected, ['concurrency' => 5]);
```

- `Each::of(iterable $iterable, ?callable $onFulfilled = null, ?callable $onRejected = null, array $config = []) : PromiseInterface` consumes the iterable and optionally limits lazy iteration with `concurrency`.
- `Each::ofLimit(iterable $iterable, $concurrency, ?callable $onFulfilled = null, ?callable $onRejected = null) : PromiseInterface` is a convenience wrapper for `Each::of()` with a concurrency limit.
- `Each::ofLimitAll(iterable $iterable, $concurrency, ?callable $onFulfilled = null) : PromiseInterface` is like `ofLimit()`, but rejects the aggregate promise on the first rejection.
- `EachPromise` is the configurable class behind `Each`; use it directly when you need `fulfilled`, `rejected`, and `concurrency` keys in one configuration array.

The concurrency options limit lazy promise creation. For HTTP request
concurrency, use `GuzzleHttp\Pool` from `guzzlehttp/guzzle`.

## Coroutine

`Coroutine::of(callable $generatorFn) : Coroutine` creates a promise resolved by
a generator that yields values or promises. The generator resumes with each
fulfilled value, and rejections are thrown into the generator.

## Task Queue

`TaskQueueInterface` exposes `isEmpty()`, `add(callable $task)`, and `run()`.
`TaskQueue` executes queued tasks in FIFO order. The default queue runs at
process shutdown unless disabled, and `wait()` drains the queue while resolving
the promise being waited on.

## Exceptions

- `RejectionException` is thrown by `wait()` when a rejected promise has a non-throwable reason. The original reason is available through `getReason()`.
- `AggregateException` extends `RejectionException` and is used by `Utils::some()` and `Utils::any()` when too few promises fulfill.
- `CancellationException` extends `RejectionException` and is used as the rejection reason for cancelled promises.

## Related

- [Quick Start](promise-quick-start.md)
- [Promise Interoperability](promise-interoperability.md)
- [Implementation Notes](implementation-notes.md)
- [Upgrade Guide](../UPGRADING.md)
