Guzzle Promises Upgrade Guide
=============================

2.x to 3.0
----------

Guzzle Promises 3.0 is a major release that raises the minimum PHP version,
updates promise and collection helper signatures, adds generic PHPDoc types for
static analyzers, tightens collection helper inputs, improves recursive
collection behavior, and clarifies rejection inspection and late rejection
callback behavior.

#### PHP Version and Dependencies

Guzzle Promises 3.0 requires PHP `^7.4 || ^8.0`. Guzzle Promises 2.x supported
PHP `^7.2.5 || ^8.0`.

If your application still supports PHP 7.2 or 7.3, continue using Guzzle
Promises 2.x until your minimum PHP version is raised.

Guzzle Promises 3.0 has no runtime package dependencies beyond PHP. It removes
the 2.x runtime dependency on `symfony/deprecation-contracts`; require that
package directly if your application uses it.

#### Optional Promise Resolution Values

`PromiseInterface::resolve()` now accepts an optional value. Calling `resolve()`
without an argument fulfills the promise with `null`.

Custom implementations of `PromiseInterface`, and subclasses that override
`resolve()` on `Promise`, `FulfilledPromise`, or `RejectedPromise`, must update
their method signature from `resolve($value): void` to
`resolve($value = null): void`.

#### Generic PHPDoc Types

`PromiseInterface`, `PromisorInterface`, and the built-in promise classes now
include generic PHPDoc annotations for static analysis tools. The first template
type represents the fulfillment value and the second represents the rejection
reason. This is a static-analysis-only change and does not alter runtime
behavior, but projects with stricter static analysis may see new or different
diagnostics:

```php
use GuzzleHttp\Promise\PromiseInterface;

/** @var PromiseInterface<string, \Throwable> */
$promise = $factory->createPromise();

$value = $promise->wait();
```

Code that uses unparameterized promise types continues to work and is treated as
`PromiseInterface<mixed, mixed>`. If your project implements promise interfaces,
extends promise classes, or has stricter static analysis, you may need to update
your PHPDoc annotations to include the generic value and reason types. The
expanded PHPDoc also preserves promise-chain fulfillment and rejection types
more precisely through `then()` and `otherwise()`, and documents collection
callbacks with value or reason, key, and aggregate-promise arguments so
callbacks may declare only the arguments they use.

#### Collection Helper Inputs

Promise collection helpers now require iterable inputs. Passing a single promise
or scalar value directly now throws a `TypeError`.

Wrap single promises or values in an array before passing them to
`Create::iterFor()`, `Each::of()`, `Each::ofLimit()`, `Each::ofLimitAll()`,
`EachPromise`, or the `Utils` collection helpers.

```php
use GuzzleHttp\Promise\Each;

// 2.x
$promise = Each::ofLimit($singlePromise, 2);

// 3.0
$promise = Each::ofLimit([$singlePromise], 2);
```

`IteratorAggregate` inputs are now iterated via `getIterator()`. In 2.x an
aggregate was treated as a single value and produced one result; in 3.0 its
entries are consumed individually.

```php
use GuzzleHttp\Promise\Utils;

$aggregate = new \ArrayObject([$promiseA, $promiseB]);

// 2.x: one result — the ArrayObject itself
// 3.0: two results — the fulfillment values of $promiseA and $promiseB
$promise = Utils::all($aggregate);
```

#### Collection Helper Signatures

`Utils::all()`, `Utils::settle()`, and `Each::of()` now accept trailing optional
arguments. Direct calls using the 2.x argument lists continue to work, and the
affected helper classes are final so subclass signatures do not need to change.
Code that mirrors or reflects exact helper signatures may need to be updated.

Pass the recursive flag before the config array when using `Utils::all()` or
`Utils::settle()`:

```php
use GuzzleHttp\Promise\Utils;

$promise = Utils::all($promises, false, ['concurrency' => 5]);
$promise = Utils::settle($promises, false, ['concurrency' => 5]);
```

Only `concurrency` is honored by these helper config arrays. Callback config
keys such as `fulfilled` and `rejected` are ignored; pass callbacks to
`Each::of()` directly or use `EachPromise`.

#### Recursive Collection Helpers

Existing `Utils::all($promises, true)` calls may return different results in
3.0. Recursive mode now detects dynamically-added settled promises and raw
values. In 2.x, recursive mode only checked for pending promises.

If you previously worked around the lack of recursive `Utils::settle()` support,
you can replace that workaround with the new `$recursive` argument:

```php
use GuzzleHttp\Promise\Utils;

$promise = Utils::settle($promises, true);
```

When `$recursive` is true, collection helpers continue taking passes over the
collection until no new entries are found and no visible promises remain
pending. This is intended for rewindable mutable collections such as
`ArrayIterator`. Generators are safe to pass, but they cannot be traversed
again once consumed, so recursive mode degrades to a single pass; use a
rewindable mutable collection when recursion needs to observe added values.

#### Promise Inspection

`Utils::inspect()` and `Utils::inspectAll()` now return the actual rejection
reason delivered to rejection callbacks. They no longer unwrap
`RejectionException` instances to their inner reason.

For example, a promise rejected with a `RejectionException` now inspects with
that exception as the reason:

```php
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\RejectionException;
use GuzzleHttp\Promise\Utils;

$reason = new RejectionException('reason');
$result = Utils::inspect(new RejectedPromise($reason));

assert($result['reason'] === $reason);
```

Cancelled promises now inspect with a `CancellationException` reason. If you
need the string reason from a `RejectionException` or subclass, call
`getReason()` on the exception.

`Utils::inspect()` still reports wait-function failures as rejection reasons
when the wait function does not settle the promise. If the wait function
settles the promise and then throws, `inspect()` reports the settled
fulfillment or rejection state; direct `Promise::wait()` calls continue to
throw that late exception.

#### Late Rejection Callbacks

Rejection callbacks registered after a promise was resolved with a rejected
promise are now invoked with the nested rejection reason.

```php
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\Utils;

$promise = new Promise();
$promise->resolve(new RejectedPromise('reason'));

$promise->then(null, function ($reason): void {
    assert($reason === 'reason');
});

Utils::queue()->run();
```

#### Non-instantiable Helper Classes

Static helper classes such as `Create`, `Each`, `Is`, and `Utils` now have
private constructors. Replace any accidental instantiation with static method
calls.

#### Native PHP Serialization of Runtime Objects

`Promise`, `TaskQueue`, `EachPromise`, and `Coroutine` no longer support native
PHP `serialize()` or `unserialize()`. Persist application values instead of
promise runtime state.

1.x to 2.0
----------

Guzzle Promises 2.0 is a major release that removes deprecated APIs, raises the
minimum PHP version, and adds PHP 7 parameter and return types. Applications that
only use the object-oriented API should usually need small changes. Applications
that call helper functions, implement package interfaces, extend package classes,
or pass invalid argument types need closer review.

#### PHP Version and Dependencies

Guzzle Promises 2.0 requires PHP `^7.2.5 || ^8.0`. Guzzle Promises 1.x
supported PHP `>=5.5`.

#### PHP 7 Type Hints and Return Types

Type hints and return types were added wherever possible. Please make sure:

- You pass values of the documented type when calling methods and functions.
- Classes that implement `PromiseInterface`, `PromisorInterface`, or
  `TaskQueueInterface` update method signatures to remain compatible.
- Classes that extend Guzzle Promises classes update any overridden method
  signatures to remain compatible.
- Code that expected package-specific exceptions for invalid argument types may
  now receive PHP `TypeError` exceptions instead.

#### Soft-Final Classes

All previously non-final non-exception classes are now final or annotated with
`@final`. If your code extends one of these classes, replace inheritance with
composition or implement the relevant interface directly.

#### Removed Function API

The static API was introduced in 1.4.0 to mitigate problems with functions
conflicting between global and local copies of the package. The function API was
removed in 2.0.0, along with the Composer `files` autoload entry that loaded
`src/functions_include.php`.

Replace namespaced function calls with the corresponding static methods in the
`GuzzleHttp\Promise` namespace:

```php
// Before:
use function GuzzleHttp\Promise\promise_for;

$promise = promise_for('value');

// After:
use GuzzleHttp\Promise\Create;

$promise = Create::promiseFor('value');
```

| Original Function | Replacement Method |
|-------------------|--------------------|
| `queue` | `Utils::queue` |
| `task` | `Utils::task` |
| `promise_for` | `Create::promiseFor` |
| `rejection_for` | `Create::rejectionFor` |
| `exception_for` | `Create::exceptionFor` |
| `iter_for` | `Create::iterFor` |
| `inspect` | `Utils::inspect` |
| `inspect_all` | `Utils::inspectAll` |
| `unwrap` | `Utils::unwrap` |
| `all` | `Utils::all` |
| `some` | `Utils::some` |
| `any` | `Utils::any` |
| `settle` | `Utils::settle` |
| `each` | `Each::of` |
| `each_limit` | `Each::ofLimit` |
| `each_limit_all` | `Each::ofLimitAll` |
| `!is_fulfilled` | `Is::pending` |
| `is_fulfilled` | `Is::fulfilled` |
| `is_rejected` | `Is::rejected` |
| `is_settled` | `Is::settled` |
| `coroutine` | `Coroutine::of` |

For the full 2.0 diff, see
https://github.com/guzzle/promises/compare/1.5.3...2.0.0.
