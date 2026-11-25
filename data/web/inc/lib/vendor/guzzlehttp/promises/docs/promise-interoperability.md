# Promise Interoperability

This guide explains how Guzzle promises interact with foreign promise
implementations. A foreign promise is any object with a `then` method, such as a
[React promise](https://github.com/reactphp/promise). When a foreign promise is
returned from a `then` callback, Guzzle forwards resolution to that promise.

## Foreign Promises

Capture the promise returned from `then()`. That chained promise is the Guzzle
promise that follows the foreign promise's eventual result.

```php
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;

$deferred = new React\Promise\Deferred();
$reactPromise = $deferred->promise();

$guzzlePromise = new Promise();
$chained = $guzzlePromise->then(function ($value) use ($reactPromise) {
    // Use the Guzzle value, then continue with the React promise.
    return $reactPromise;
});

$chained->then(function ($value) {
    echo $value;
});

$guzzlePromise->resolve('start');
Utils::queue()->run();

$deferred->resolve('done');
Utils::queue()->run();
```

Forwarding a foreign promise does not make Guzzle able to synchronously wait on
or cancel the foreign operation. The chained Guzzle promise settles only after
the foreign implementation invokes the callbacks registered through `then()`.
If the foreign promise has no compatible `wait()` or `cancel()` behavior, Guzzle
cannot invent that behavior.

Use `Create::promiseFor($foreignPromise)` to shadow a foreign thenable as a
Guzzle promise. If the foreign object exposes `wait()` or `cancel()` methods,
the wrapper uses them. Otherwise, use the foreign implementation's event loop or
completion mechanism and drain Guzzle's task queue when callbacks need to run.

## Event Loop Integration

Guzzle promises use a task queue to keep stack size constant and to run promise
callbacks asynchronously. When waiting on promises synchronously, the task queue
is automatically run while resolving the blocking promise and forwarded Guzzle
promises.

When using promises asynchronously in an event loop, run the task queue on loop
ticks. If you do not run the task queue, Guzzle promise callbacks may remain
queued.

```php
$queue = GuzzleHttp\Promise\Utils::queue();
$queue->run();
```

For example, you could use Guzzle promises with React using a short periodic
timer. Avoid zero-interval timers because they may keep the loop busy even when
there is no promise work to run.

```php
$queue = GuzzleHttp\Promise\Utils::queue();
$loop = React\EventLoop\Factory::create();
$loop->addPeriodicTimer(0.01, [$queue, 'run']);
```

## Related

- [Quick Start](promise-quick-start.md)
- [Promise API](promise-api.md)
- [Implementation Notes](implementation-notes.md)
