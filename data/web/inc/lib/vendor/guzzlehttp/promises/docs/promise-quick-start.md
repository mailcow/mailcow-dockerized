# Promise Quick Start

This guide covers the common promise operations needed when using
`guzzlehttp/promises` directly: registering callbacks, resolving or rejecting
promises, waiting synchronously, composing chains, and using generator-based
flows. For the full public surface, see the [Promise API](promise-api.md).

A *promise* represents the eventual result of an asynchronous operation. The
primary way of interacting with a promise is through its `then` method, which
registers callbacks to receive either the eventual value or the reason why the
promise cannot be fulfilled.

Guzzle promise callbacks are queued. They run when the task queue is drained,
for example by `Utils::queue()->run()`, by waiting on a returned promise, or by
the default shutdown handler at the end of the PHP process. Examples that show
callback output drain the queue explicitly.

## Callbacks

Callbacks are registered with the `then` method by providing an optional
`$onFulfilled` followed by an optional `$onRejected` function.

```php
use GuzzleHttp\Promise\Promise;

$promise = new Promise();
$promise->then(
    // $onFulfilled
    function ($value) {
        echo 'The promise was fulfilled.';
    },
    // $onRejected
    function ($reason) {
        echo 'The promise was rejected.';
    }
);
```

*Resolving* a promise means that you either fulfill a promise with a *value* or
reject a promise with a *reason*. Callbacks registered with `then` are invoked
only once and in the order in which they were added when the queue is drained.

## Resolving a Promise

Promises are fulfilled using the `resolve($value = null)` method. Calling
`resolve()` without an argument fulfills the promise with `null`. Resolving a
promise with any value other than a `GuzzleHttp\Promise\RejectedPromise` queues
the `$onFulfilled` callbacks. Resolving with a rejected promise rejects the
promise and queues the `$onRejected` callbacks.

```php
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;

$promise = new Promise();
$promise
    ->then(function ($value) {
        return 'Hello, ' . $value;
    })
    ->then(function ($value) {
        echo $value;
    });

$promise->resolve('reader.');
Utils::queue()->run();
// Outputs "Hello, reader."
```

## Promise Forwarding

Promises can be chained one after the other. Each `then` call returns a new
promise. The return value of a callback is forwarded to the next promise in the
chain. Returning a promise from a callback makes the next promise wait for that
returned promise to settle.

```php
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;

$promise = new Promise();
$nextPromise = new Promise();

$promise
    ->then(function ($value) use ($nextPromise) {
        echo $value;

        return $nextPromise;
    })
    ->then(function ($value) {
        echo $value;
    });

$promise->resolve('A');
Utils::queue()->run();
// Outputs "A"

$nextPromise->resolve('B');
Utils::queue()->run();
// Outputs "B"
```

## Promise Rejection

When a promise is rejected, the `$onRejected` callbacks are invoked with the
rejection reason when the queue is drained.

```php
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;

$promise = new Promise();
$promise->then(null, function ($reason) {
    echo $reason;
});

$promise->reject('Error!');
Utils::queue()->run();
// Outputs "Error!"
```

## Rejection Forwarding

If an exception is thrown in an `$onRejected` callback, subsequent
`$onRejected` callbacks receive the thrown exception as the reason.

```php
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;

$promise = new Promise();
$promise->then(null, function ($reason) {
    throw new Exception($reason);
})->then(null, function ($reason) {
    assert($reason->getMessage() === 'Error!');
});

$promise->reject('Error!');
Utils::queue()->run();
```

You can also forward a rejection down the promise chain by returning a
`GuzzleHttp\Promise\RejectedPromise` in either an `$onFulfilled` or
`$onRejected` callback.

```php
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\Utils;

$promise = new Promise();
$promise->then(null, function ($reason) {
    return new RejectedPromise($reason);
})->then(null, function ($reason) {
    assert($reason === 'Error!');
});

$promise->reject('Error!');
Utils::queue()->run();
```

If an exception is not thrown in an `$onRejected` callback and the callback
does not return a rejected promise, downstream `$onFulfilled` callbacks are
invoked using the value returned from the `$onRejected` callback.

```php
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;

$promise = new Promise();
$promise
    ->then(null, function ($reason) {
        return "It's ok";
    })
    ->then(function ($value) {
        assert($value === "It's ok");
    });

$promise->reject('Error!');
Utils::queue()->run();
```

## Synchronous Wait

You can synchronously force promises to complete using a promise's `wait`
method. When creating a promise, you can provide a wait function that is used
to synchronously complete the promise. The wait function receives a boolean
argument and is expected to resolve or reject the promise. If the wait function
does not settle the promise, an exception is thrown.

```php
use GuzzleHttp\Promise\Promise;

$promise = new Promise(function (bool $recursive) use (&$promise) {
    $promise->resolve('foo');
});

echo $promise->wait();
// Outputs "foo"
```

If a throwable is encountered while invoking the wait function of a promise,
the promise is rejected with the throwable and the throwable is thrown.

```php
$promise = new Promise(function (bool $recursive) use (&$promise) {
    throw new Exception('foo');
});

$promise->wait(); // Throws the exception.
```

Calling `wait` on a promise that has been fulfilled will not trigger the wait
function. It will simply return the previously resolved value.

```php
$promise = new Promise(function (bool $recursive) { die('this is not called!'); });
$promise->resolve('foo');

echo $promise->wait();
// Outputs "foo"
```

Calling `wait` on a promise that has been rejected will throw. If the rejection
reason is an instance of `\Throwable`, the reason is thrown. Otherwise, a
`GuzzleHttp\Promise\RejectionException` is thrown and the reason can be
obtained by calling `getReason()` on the exception.

```php
$promise = new Promise();
$promise->reject('foo');
$promise->wait();
```

> PHP Fatal error:  Uncaught exception 'GuzzleHttp\Promise\RejectionException' with message 'The promise was rejected with reason: foo'

## Unwrapping a Promise

When synchronously waiting on a promise, you are joining the state of the
promise into the current execution: the fulfilled value is returned, or the
rejection reason is thrown. This is called "unwrapping" the promise. Waiting on
a promise unwraps by default.

You can force a promise to resolve and *not* unwrap its state by passing
`false` to `wait()`:

```php
$promise = new Promise();
$promise->reject('foo');

// This does not throw. It only ensures the promise has been resolved.
$promise->wait(false);
```

When unwrapping a promise, the resolved value of the promise will be waited on
until the unwrapped value is not a promise. This means that if promise A is
resolved with promise B, unwrapping promise A returns the value delivered to
promise B.

When you do not unwrap the promise, no value is returned.

## Inspecting a Promise

`Utils::inspect($promise)` waits for a promise to settle and returns an array
describing its final state. For rejected promises, the `reason` entry is the
actual rejection reason delivered to rejection callbacks.

This means `RejectionException` and subclasses are not unwrapped by
`inspect()`. For example, cancelled promises inspect with a
`CancellationException` reason.

## Generator-Based Async

`Coroutine::of()` creates a promise from a generator that yields values or
promises. The generator resumes each time the yielded value settles, which can
make sequential asynchronous flows easier to read.

```php
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\FulfilledPromise;

$promise = Coroutine::of(function () {
    $first = yield new FulfilledPromise('A');
    $second = yield new FulfilledPromise($first . 'B');

    yield $second . 'C';
});

echo $promise->wait();
// Outputs "ABC"
```

If a yielded promise rejects, the rejection is thrown into the generator. Catch
it inside the generator to recover, or let it reject the coroutine promise.

## Cancellation

You can cancel a promise that has not yet been fulfilled using `cancel()`. When
creating a promise, you can provide an optional cancel function that cancels the
underlying operation, such as closing a socket or aborting a query.

```php
use GuzzleHttp\Promise\Promise;

$promise = new Promise(null, function () {
    // Cancel the underlying operation.
});

$promise->cancel();
```

Cancellation rejects the promise with a `CancellationException` unless the
cancel function settles the promise first.

## Related

- [Promise API](promise-api.md)
- [Promise Interoperability](promise-interoperability.md)
- [Implementation Notes](implementation-notes.md)
- [Upgrade Guide](../UPGRADING.md)
