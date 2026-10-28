# Stream Helpers

This page covers `GuzzleHttp\Psr7\Utils` helper methods for creating, copying,
hashing, reading, and safely opening PSR-7 streams. For stream implementations
and decorators, see [Streams and Decorators](streams-and-decorators.md).

## `GuzzleHttp\Psr7\Utils::copyToStream`

`public static function copyToStream(StreamInterface $source, StreamInterface $dest, int $maxLen = -1): int`

Copy the contents of a stream into another stream until the given number of
bytes have been read, returning the number of bytes copied as an `int`. On
32-bit PHP, an unbounded copy larger than `PHP_INT_MAX` bytes cannot be
represented by that return type. 64-bit PHP is not affected.

The destination must accept writes that make positive progress. Streams that
return 0 as a backpressure or drop signal (a `BufferStream` at its high water
mark, or a full `DroppingStream`) will cause this method to throw. For full
copies, use a normal writable stream such as a file or `php://temp` stream.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after a source read or destination write cannot make
progress.

When a source read times out, the bytes copied before it remain in the
destination.

## `GuzzleHttp\Psr7\Utils::copyToString`

`public static function copyToString(StreamInterface $stream, int $maxLen = -1): string`

Copy the contents of a stream into a string until the given number of bytes have
been read.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after a stream read cannot make progress.

The bytes read before a timeout are discarded, so calling this method again does
not return them.

## `GuzzleHttp\Psr7\Utils::hash`

`public static function hash(StreamInterface $stream, string $algo, bool $rawOutput = false): string`

Calculate a hash of a stream.

This method reads the entire stream to calculate a rolling hash, based on PHP's
`hash_init` functions.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after a stream read cannot make progress.

## `GuzzleHttp\Psr7\Utils::readLine`

`public static function readLine(StreamInterface $stream, ?int $maxLength = null): string`

Read a line from the stream up to the maximum allowed buffer length.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after a stream read cannot make progress.

The bytes read before a timeout are discarded, so calling this method again does
not return them.

## `GuzzleHttp\Psr7\Utils::streamFor`

`public static function streamFor(resource|string|null|StreamInterface|callable|\Iterator|\Stringable $resource = '', array $options = []): StreamInterface`

Create a new stream based on the input type.

Options are provided as an associative array that can contain the following
keys:

- metadata: Array of custom metadata.
- size: Size of the stream.

This method accepts the following `$resource` types:

- `Psr\Http\Message\StreamInterface`: Returns the value as-is.
- `string`: Creates a stream object that uses the given string as the contents.
- `resource`: Creates a stream object that wraps the given PHP stream resource.
- `Iterator`: If the provided value implements `Iterator`, then a read-only
  stream object will be created that wraps the given iterable. Each time the
  stream is read from, data from the iterator will fill a buffer and will be
  continuously called until the buffer is equal to the requested read size.
  Yielded strings, integers, finite floats, booleans, `null`, and stringable
  objects are converted to string chunks; non-finite floats and other values
  throw `UnexpectedValueException` when the stream is read. Values that
  stringify to an empty string are skipped while the iterator advances.
  Subsequent read calls will first read from the buffer and then call `next` on
  the underlying iterator until it is exhausted.
- `object` with `__toString()`: If the object has the `__toString()` method, the
  object will be cast to a string and then a stream will be returned that uses
  the string value.
- `NULL`: When `null` is passed, an empty stream object is returned.
- `callable`: When a callable array, closure, or invokable object is passed and
  no earlier resource or object rule applies, a read-only stream object will be
  created that invokes the given callable. The callable is invoked with the
  suggested number of bytes to read. The callable can return fewer or more bytes
  than requested, but MUST return a non-empty string to provide data and MUST
  return `false` or `null` when there is no more data to return. Any additional
  bytes will be buffered and used in subsequent reads. String inputs are always
  treated as string bodies, even when they name callable functions.

```php
$stream = GuzzleHttp\Psr7\Utils::streamFor('foo');
$stream = GuzzleHttp\Psr7\Utils::streamFor(fopen('/path/to/file', 'r'));

$generator = function ($bytes) {
    for ($i = 0; $i < $bytes; $i++) {
        yield ' ';
    }
};

$stream = GuzzleHttp\Psr7\Utils::streamFor($generator(100));
```

## `GuzzleHttp\Psr7\Utils::tryFopen`

`public static function tryFopen(string $filename, string $mode): resource`

Safely opens a PHP stream resource using a filename.

When `fopen()` fails, PHP normally raises a warning. This function adds an error
handler that checks for errors and throws an exception instead.

## `GuzzleHttp\Psr7\Utils::tryGetContents`

`public static function tryGetContents(resource $stream): string`

Safely gets the contents of a given stream.

When `stream_get_contents()` fails, PHP normally raises a warning. This function
adds an error handler that checks for errors and throws an exception instead.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after a stream read cannot make progress.

The bytes read before a timeout are discarded, so calling this method again does
not return them.

## Related

- [Streams and Decorators](streams-and-decorators.md)
- [PSR-17 Factories](psr-17-factories.md)
- [Message Helpers](message-helpers.md)
