# Implementation Notes

This document describes implementation details that explain observable behavior
in `guzzlehttp/promises`, especially queue-based callback execution, iterative
resolution, and why `Promise` also acts as the deferred value. Application code
usually only needs the [Promise Quick Start](promise-quick-start.md) and
[Promise API](promise-api.md).

## Iterative Resolution and Chaining

Promises are resolved iteratively by moving pending handlers between promises.
This keeps stack size constant even for very long `then()` chains.

```php
<?php
require 'vendor/autoload.php';

use GuzzleHttp\Promise\Promise;

$parent = new Promise();
$p = $parent;

for ($i = 0; $i < 1000; $i++) {
    $p = $p->then(function ($v) {
        // The stack size remains constant.
        echo xdebug_get_stack_depth() . ', ';
        return $v + 1;
    });
}

$parent->resolve(0);
var_dump($p->wait()); // int(1000)

```

When a promise is fulfilled or rejected with a non-promise value, the promise
takes ownership of each child promise's handlers and delivers values down the
chain without recursion.

When a promise is resolved with another promise, the original promise transfers
all of its pending handlers to the new promise. When the new promise is
eventually resolved, all pending handlers receive the forwarded
value.

## A Promise Is the Deferred

Some promise libraries implement promises using a deferred object to represent
a computation and a promise object to represent the delivery of the result of
the computation. That separation prevents consumers from modifying the value
that will eventually be delivered.

Iterative resolution requires one promise to move handlers from another promise.
To do that without making handlers publicly mutable, `Promise` is also the
deferred value. Promises of the same class can modify each other's private
state, including handler ownership. This means a consumer that receives a
`Promise` can also resolve or reject it, but it keeps chaining efficient and
stack safe.

```php
$promise = new Promise();
$promise->then(function ($value) { echo $value; });
// The promise is the deferred value, so you can deliver a value to it.
$promise->resolve('foo');
GuzzleHttp\Promise\Utils::queue()->run();
// Prints "foo"
```

## Related

- [Quick Start](promise-quick-start.md)
- [Promise API](promise-api.md)
- [Promise Interoperability](promise-interoperability.md)
