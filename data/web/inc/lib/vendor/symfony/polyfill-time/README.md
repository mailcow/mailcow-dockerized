Symfony Polyfill / Time
=======================

This component provides the [`Time` API](https://wiki.php.net/rfc/duration_class)
added to PHP 8.6 core, for PHP >= 8.1:

- `Time\TimeException`, `Time\Duration`

The phpt tests of the native implementation, borrowed from php-src, run against
the polyfill as part of the test suite. One divergence remains: `Duration` is a
plain final class with readonly properties instead of a `readonly` class, so
`ReflectionClass::newInstanceWithoutConstructor()` succeeds where the native
class rejects it. Dynamic properties are rejected as they are natively.

More information can be found in the
[main Polyfill README](https://github.com/symfony/polyfill/blob/main/README.md).

License
=======

This library is released under the [MIT license](LICENSE).
