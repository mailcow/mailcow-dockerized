<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Psr7\Exception\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class Utils
{
    private function __construct()
    {
    }

    /**
     * Converts ASCII uppercase letters in a string to lowercase.
     *
     * Unlike strtolower(), which honors LC_CTYPE before PHP 8.2, the
     * conversion is locale-independent and leaves every non-ASCII byte
     * unchanged, as HTTP protocol elements require.
     */
    public static function asciiToLower(string $string): string
    {
        return strtr($string, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * Converts ASCII lowercase letters in a string to uppercase.
     *
     * Unlike strtoupper(), which honors LC_CTYPE before PHP 8.2, the
     * conversion is locale-independent and leaves every non-ASCII byte
     * unchanged, as HTTP protocol elements require.
     */
    public static function asciiToUpper(string $string): string
    {
        return strtr($string, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    /**
     * Converts the first character of a string to uppercase when it is an
     * ASCII lowercase letter.
     *
     * Unlike ucfirst(), which honors LC_CTYPE before PHP 8.2, the conversion
     * is locale-independent and leaves every non-ASCII byte unchanged, as
     * HTTP protocol elements require.
     */
    public static function asciiUcFirst(string $string): string
    {
        if ($string === '') {
            return '';
        }

        return self::asciiToUpper($string[0]).substr($string, 1);
    }

    /**
     * Checks whether the haystack contains the needle, comparing ASCII
     * letters case-insensitively and without locale sensitivity.
     */
    public static function caselessContains(string $haystack, string $needle): bool
    {
        return str_contains(self::asciiToLower($haystack), self::asciiToLower($needle));
    }

    /**
     * Checks whether two strings are equal, comparing ASCII letters
     * case-insensitively and without locale sensitivity.
     */
    public static function caselessEquals(string $left, string $right): bool
    {
        return self::asciiToLower($left) === self::asciiToLower($right);
    }

    /**
     * Remove the items given by the keys from the data, case-insensitively.
     *
     * @param array<array-key, string|int> $keys
     */
    public static function caselessRemove(array $keys, array $data): array
    {
        $result = [];

        foreach ($keys as &$key) {
            $key = self::asciiToLower((string) $key);
        }

        foreach ($data as $k => $v) {
            if (!in_array(self::asciiToLower((string) $k), $keys)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }

    /**
     * Copy the contents of a stream into another stream until the given number
     * of bytes have been read, returning the number of bytes copied as an
     * `int`. On 32-bit PHP, an unbounded copy larger than `PHP_INT_MAX` bytes
     * cannot be represented by that return type. 64-bit PHP is not affected.
     *
     * The destination must accept writes that make positive progress. Streams
     * that return 0 as a backpressure or drop signal (a `BufferStream` at its
     * high water mark, or a full `DroppingStream`) will cause this method to
     * throw. For full copies, use a normal writable stream such as a file or
     * `php://temp` stream.
     *
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a source read or destination write cannot make progress.
     *
     * When a source read times out, the bytes copied before it remain in the
     * destination.
     *
     * @param StreamInterface $source Stream to read from
     * @param StreamInterface $dest   Stream to write to
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream.
     *
     * @throws \RuntimeException on error.
     */
    public static function copyToStream(StreamInterface $source, StreamInterface $dest, int $maxLen = -1): int
    {
        $bufferSize = 8192;
        $copied = 0;

        if ($maxLen === -1) {
            while (!$source->eof()) {
                $buf = StreamTimeout::read($source, $bufferSize, 'Unable to read from stream: timed out');
                if ($buf === '') {
                    break;
                }

                self::writeAll($dest, $buf);
                $copied = Integers::add($copied, strlen($buf));
            }
        } else {
            $remaining = $maxLen;
            while ($remaining > 0 && !$source->eof()) {
                $buf = StreamTimeout::read($source, min($bufferSize, $remaining), 'Unable to read from stream: timed out');
                $len = strlen($buf);
                if (!$len) {
                    break;
                }
                $remaining -= $len;
                self::writeAll($dest, $buf);
                $copied = Integers::add($copied, $len);
            }
        }

        return $copied;
    }

    private static function writeAll(StreamInterface $dest, string $buf): void
    {
        $written = 0;
        $len = strlen($buf);

        while ($written < $len) {
            try {
                $result = $dest->write(substr($buf, $written));
            } catch (TimeoutException $e) {
                throw $e;
            } catch (\RuntimeException $e) {
                StreamTimeout::throwIfWriteTimedOut($dest, $e);

                throw $e;
            }

            if ($result <= 0) {
                StreamTimeout::throwIfWriteTimedOut($dest);

                throw new \RuntimeException('Unable to write to stream');
            }

            $written += $result;
        }
    }

    /**
     * Copy the contents of a stream into a string until the given number of
     * bytes have been read.
     *
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a stream read cannot make progress.
     *
     * The bytes read before a timeout are discarded, so calling this method
     * again does not return them.
     *
     * @param StreamInterface $stream Stream to read
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream.
     *
     * @throws \RuntimeException on error.
     */
    public static function copyToString(StreamInterface $stream, int $maxLen = -1): string
    {
        $buffer = '';

        if ($maxLen === -1) {
            while (!$stream->eof()) {
                $buf = StreamTimeout::read($stream, 1048576, 'Unable to read from stream: timed out');
                if ($buf === '') {
                    break;
                }
                $buffer .= $buf;
            }

            return $buffer;
        }

        $len = 0;
        while (!$stream->eof() && $len < $maxLen) {
            $buf = StreamTimeout::read($stream, $maxLen - $len, 'Unable to read from stream: timed out');
            if ($buf === '') {
                break;
            }
            $buffer .= $buf;
            $len = strlen($buffer);
        }

        return $buffer;
    }

    /**
     * Calculate a hash of a stream.
     *
     * This method reads the entire stream to calculate a rolling hash, based on
     * PHP's `hash_init` functions.
     *
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a stream read cannot make progress.
     *
     * @param StreamInterface $stream    Stream to calculate the hash for
     * @param string          $algo      Hash algorithm (e.g. md5, crc32, etc)
     * @param bool            $rawOutput Whether or not to use raw output
     *
     * @throws \RuntimeException on error.
     */
    public static function hash(StreamInterface $stream, string $algo, bool $rawOutput = false): string
    {
        $pos = $stream->tell();

        if ($pos > 0) {
            $stream->rewind();
        }

        $ctx = hash_init($algo);
        while (!$stream->eof()) {
            $buf = StreamTimeout::read($stream, 1048576, 'Unable to calculate stream hash: timed out');
            if ($buf === '') {
                break;
            }

            hash_update($ctx, $buf);
        }

        $out = hash_final($ctx, $rawOutput);
        $stream->seek($pos);

        return $out;
    }

    /**
     * Clone and modify a request with the given changes.
     *
     * This method is useful for reducing the number of clones needed to mutate
     * a message.
     *
     * The changes can be one of:
     * - method: (string) Changes the HTTP method.
     * - set_headers: (array) Sets the given headers. Values must be strings
     *   or non-empty arrays of strings.
     * - remove_headers: (array) Remove the given headers. Values may be
     *   strings or integers.
     * - body: (mixed) Sets the given body. Present non-null values are
     *   converted with self::streamFor(), including resources, streams,
     *   iterators, callable arrays, closures, invokable objects, and stringable
     *   objects. String inputs remain literal bodies.
     * - uri: (UriInterface) Set the URI. When the URI contains a host, the
     *   Host header is updated from it, and combining this with an explicit
     *   Host entry in set_headers throws an InvalidArgumentException. Apply
     *   an intentional Host override separately with withHeader() afterwards.
     * - query: (string) Set the query string value of the URI.
     * - version: (string) Set the protocol version.
     *
     * @param RequestInterface $request Request to clone and modify.
     * @param array{
     *     method?: string,
     *     set_headers?: array<array-key, string|non-empty-array<array-key, string>>,
     *     remove_headers?: array<array-key, string|int>,
     *     body?: resource|string|StreamInterface|callable|\Iterator|\Stringable,
     *     uri?: UriInterface,
     *     query?: string,
     *     version?: string
     * } $changes Changes to apply.
     */
    public static function modifyRequest(RequestInterface $request, array $changes): RequestInterface
    {
        if (!$changes) {
            return $request;
        }

        self::assertValidModifyRequestChanges($changes);

        $headers = $request->getHeaders();

        if (!isset($changes['uri'])) {
            $uri = $request->getUri();
        } else {
            /** @var UriInterface */
            $uri = $changes['uri'];

            $host = $uri->getHost();
            if ($host !== '') {
                Uri::assertValidHost($host);

                if (isset($changes['set_headers']) && is_array($changes['set_headers'])) {
                    foreach (array_keys($changes['set_headers']) as $header) {
                        if (self::asciiToLower((string) $header) === 'host') {
                            throw new \InvalidArgumentException(
                                'Cannot modify request with both a URI containing a host and an explicit Host header.'
                            );
                        }
                    }
                }

                $changes['set_headers']['Host'] = $host;

                $port = $uri->getPort();
                if ($port !== null) {
                    $standardPorts = ['http' => 80, 'https' => 443];
                    $scheme = $uri->getScheme();
                    if (!isset($standardPorts[$scheme]) || $port != $standardPorts[$scheme]) {
                        $changes['set_headers']['Host'] .= ':'.$port;
                    }
                }
            }
        }

        if (!empty($changes['remove_headers'])) {
            $headers = self::caselessRemove($changes['remove_headers'], $headers);
        }

        if (!empty($changes['set_headers'])) {
            $headers = self::caselessRemove(array_keys($changes['set_headers']), $headers);
            $headers = $changes['set_headers'] + $headers;
        }

        if (isset($changes['query'])) {
            $uri = $uri->withQuery($changes['query']);
        }

        $hasHost = false;
        foreach (array_keys($headers) as $header) {
            if (self::asciiToLower((string) $header) === 'host') {
                $hasHost = true;
                break;
            }
        }

        // Match Request::__construct() by adding a Host header when one is not provided.
        if (!$hasHost && $uri->getHost() !== '') {
            $host = $uri->getHost();
            Uri::assertValidHost($host);

            if (($port = $uri->getPort()) !== null) {
                $host .= ':'.$port;
            }

            $headers = ['Host' => [$host]] + $headers;
        }

        $new = $request;

        if (isset($changes['method'])) {
            $new = $new->withMethod($changes['method']);
        }

        if (isset($changes['uri']) || isset($changes['query'])) {
            $new = $new->withUri($uri, true);
        }

        if ($headers !== $new->getHeaders()) {
            foreach (array_keys($new->getHeaders()) as $header) {
                /** @var RequestInterface */
                $new = $new->withoutHeader((string) $header);
            }

            $addedHeaders = [];
            foreach ($headers as $header => $value) {
                $header = (string) $header;
                $normalized = self::asciiToLower($header);

                if (isset($addedHeaders[$normalized])) {
                    /** @var RequestInterface */
                    $new = $new->withAddedHeader($addedHeaders[$normalized], $value);
                } else {
                    /** @var RequestInterface */
                    $new = $new->withHeader($header, $value);
                    $addedHeaders[$normalized] = $header;
                }
            }
        }

        if (isset($changes['body'])) {
            /** @var RequestInterface */
            $new = $new->withBody(self::streamFor($changes['body']));
        }

        if (isset($changes['version'])) {
            /** @var RequestInterface */
            $new = $new->withProtocolVersion($changes['version']);
        }

        return $new;
    }

    /**
     * @param array<array-key, mixed> $changes
     */
    private static function assertValidModifyRequestChanges(array $changes): void
    {
        foreach (['method', 'query', 'version'] as $key) {
            if (\array_key_exists($key, $changes) && !\is_string($changes[$key])) {
                self::assertValidModifyRequestChange($key, 'string', $changes[$key]);
            }
        }

        if (\array_key_exists('uri', $changes) && !$changes['uri'] instanceof UriInterface) {
            self::assertValidModifyRequestChange('uri', 'UriInterface', $changes['uri']);
        }

        if (\array_key_exists('body', $changes) && $changes['body'] === null) {
            self::assertValidModifyRequestChange('body', 'resource|string|StreamInterface|callable|\Iterator|\Stringable', $changes['body']);
        }

        if (\array_key_exists('set_headers', $changes)) {
            if (!\is_array($changes['set_headers'])) {
                self::assertValidModifyRequestChange('set_headers', 'array<array-key, string|non-empty-array<array-key, string>>', $changes['set_headers']);
            } else {
                foreach ($changes['set_headers'] as $header => $value) {
                    $headerPath = \sprintf('set_headers.%s', (string) $header);

                    if (\is_array($value)) {
                        if ($value === []) {
                            self::assertValidModifyRequestChange($headerPath, 'string|non-empty-array<array-key, string>', $value);

                            break;
                        }

                        foreach ($value as $index => $item) {
                            if (!\is_string($item)) {
                                self::assertValidModifyRequestChange(\sprintf('%s.%s', $headerPath, (string) $index), 'string', $item);

                                break 2;
                            }
                        }
                    } elseif (!\is_string($value)) {
                        self::assertValidModifyRequestChange($headerPath, 'string|non-empty-array<array-key, string>', $value);

                        break;
                    }
                }
            }
        }

        if (!\array_key_exists('remove_headers', $changes)) {
            return;
        }

        if (!\is_array($changes['remove_headers'])) {
            self::assertValidModifyRequestChange('remove_headers', 'array<array-key, string|int>', $changes['remove_headers']);

            return;
        }

        foreach ($changes['remove_headers'] as $index => $header) {
            if (!\is_string($header) && !\is_int($header)) {
                self::assertValidModifyRequestChange(\sprintf('remove_headers.%s', (string) $index), 'string|int', $header);

                return;
            }
        }
    }

    /**
     * @param mixed $value
     */
    private static function assertValidModifyRequestChange(string $key, string $expected, $value): void
    {
        throw new \InvalidArgumentException(\sprintf('Utils::modifyRequest() change "%s" must be %s; %s provided.', DiagnosticValue::escape($key), $expected, \get_debug_type($value)));
    }

    /**
     * Read a line from the stream up to the maximum allowed buffer length.
     *
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a stream read cannot make progress.
     *
     * The bytes read before a timeout are discarded, so calling this method
     * again does not return them.
     *
     * @param StreamInterface $stream    Stream to read from
     * @param int|null        $maxLength Maximum buffer length
     */
    public static function readLine(StreamInterface $stream, ?int $maxLength = null): string
    {
        $buffer = '';
        $size = 0;

        while (!$stream->eof()) {
            if ('' === ($byte = StreamTimeout::read($stream, 1, 'Unable to read line from stream: timed out'))) {
                return $buffer;
            }
            $buffer .= $byte;
            // Break when a new line is found or the max length - 1 is reached
            if ($byte === "\n" || ++$size === $maxLength - 1) {
                break;
            }
        }

        return $buffer;
    }

    /**
     * Redact the user info part of a URI.
     *
     * Returns the URI with the whole userinfo component replaced by "***"
     * when one is present, so neither the username nor the password survives
     * into logs and diagnostics. A URI without userinfo is returned
     * unchanged.
     */
    public static function redactUserInfo(
        #[\SensitiveParameter]
        UriInterface $uri
    ): UriInterface {
        return $uri->getUserInfo() === '' ? $uri : $uri->withUserInfo('***');
    }

    /**
     * Redacts the userinfo of a raw URI string wherever it appears in a
     * subject string.
     *
     * The needle is taken verbatim from the raw URI rather than from parsed
     * components, so credentials that URI normalization would rewrite, such
     * as raw control bytes or unencoded reserved characters, are still found
     * in text that embeds the URI exactly as given, for example transport
     * error messages. A URI without "://" is treated as authority-form: a
     * host and port with optional userinfo.
     *
     * A URI that does not parse has no trustworthy authority boundary, so
     * everything between any scheme and its last "@" is redacted as a safe-side
     * fallback.
     *
     * @param string $subject Text that may embed the URI
     * @param string $uri     Raw URI whose userinfo is redacted in the text
     */
    public static function redactUserInfoInString(string $subject, string $uri): string
    {
        if (\strpos($uri, '@') === false) {
            return $subject;
        }

        $schemePosition = \strpos($uri, '://');
        $remainder = $schemePosition === false ? $uri : \substr($uri, $schemePosition + 3);

        if (\parse_url($schemePosition === false ? 'http://'.$uri : $uri) === false) {
            // Raw '/', '?', or '#' separators may sit inside the credentials
            // of a URI that defeats parse_url(), so the redaction cannot stop
            // at the apparent authority.
            $atPosition = \strrpos($remainder, '@');

            if ($atPosition === false || $atPosition === 0) {
                return $subject;
            }

            return \str_replace(\substr($remainder, 0, $atPosition).'@', '***@', $subject);
        }

        $authority = \substr($remainder, 0, \strcspn($remainder, '/?#'));
        $atPosition = \strrpos($authority, '@');

        if ($atPosition === false || $atPosition === 0) {
            // A parseable URI with '@' only past its authority, or with an
            // empty userinfo, carries no credentials to redact.
            return $subject;
        }

        return \str_replace(\substr($authority, 0, $atPosition).'@', '***@', $subject);
    }

    /**
     * Formats a URI for automatic diagnostics.
     *
     * The whole userinfo component is replaced by "***", and the query and
     * fragment are removed. The scheme, host, port, and path are preserved.
     * The returned string is diagnostic-escaped and the formatter never
     * throws.
     */
    public static function redactUriForMessage(
        #[\SensitiveParameter]
        UriInterface $uri
    ): string {
        try {
            $raw = (string) $uri;
        } catch (\Throwable $e) {
            return '[unavailable URI]';
        }

        try {
            if ($uri->getUserInfo() !== '') {
                $uri = $uri->withUserInfo('***');
            }

            return DiagnosticValue::escape((string) $uri->withQuery('')->withFragment(''));
        } catch (\Throwable $e) {
            return self::redactUriStringForMessage($raw);
        }
    }

    /**
     * Formats a raw URI for automatic diagnostics, including malformed input.
     *
     * The whole userinfo component is replaced by "***", and the query and
     * fragment are removed. The scheme, host, port, and path are preserved
     * where their boundaries can be determined safely. The returned string is
     * diagnostic-escaped and the formatter never throws.
     */
    public static function redactUriStringForMessage(
        #[\SensitiveParameter]
        string $uri
    ): string {
        try {
            $uri = self::redactUserInfoInString($uri, $uri);

            return DiagnosticValue::escape(\substr($uri, 0, \strcspn($uri, '?#')));
        } catch (\Throwable $e) {
            return '[unavailable URI]';
        }
    }

    /**
     * Create a new stream based on the input type.
     *
     * Options are provided as an associative array that can contain the
     * following keys:
     * - metadata: Array of custom metadata.
     * - size: Size of the stream.
     *
     * This method accepts the following `$resource` types:
     * - `Psr\Http\Message\StreamInterface`: Returns the value as-is.
     * - `string`: Creates a stream object that uses the given string as the
     *   contents.
     * - `resource`: Creates a stream object that wraps the given PHP stream
     *   resource.
     * - `Iterator`: If the provided value implements `Iterator`, then a
     *   read-only stream object will be created that wraps the given iterable.
     *   Each time the stream is read from, data from the iterator will fill a
     *   buffer and will be continuously called until the buffer is equal to the
     *   requested read size. Yielded strings, integers, finite floats,
     *   booleans, `null`, and stringable objects are converted to string
     *   chunks; non-finite floats and other values throw
     *   `UnexpectedValueException` when the stream is read. Values that
     *   stringify to an empty string are skipped while the iterator advances.
     *   Subsequent read calls will first read from the buffer and then call
     *   `next` on the underlying iterator until it is exhausted.
     * - `object` with `__toString()`: If the object has the `__toString()`
     *   method, the object will be cast to a string and then a stream will be
     *   returned that uses the string value.
     * - `NULL`: When `null` is passed, an empty stream object is returned.
     * - `callable`: When a callable array, closure, or invokable object is
     *   passed and no earlier resource or object rule applies, a read-only
     *   stream object will be created that invokes the given callable. The
     *   callable is invoked with the suggested number of bytes to read. The
     *   callable can return fewer or more bytes than requested, but MUST return
     *   a non-empty string to provide data and MUST return `false` or `null`
     *   when there is no more data to return. Any additional bytes will be
     *   buffered and used in subsequent reads. String inputs are always treated
     *   as string bodies, even when they name callable functions.
     *
     * @param resource|string|StreamInterface|callable|\Iterator|\Stringable|null $resource Entity body data
     * @param array{size?: int, metadata?: array}                                 $options  Additional options
     *
     * @throws \InvalidArgumentException if the $resource arg is not valid.
     */
    public static function streamFor($resource = '', array $options = []): StreamInterface
    {
        if (is_scalar($resource)) {
            if (!is_string($resource)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Cannot create a stream from %s; pass a string, resource, StreamInterface, Stringable, Iterator, callable, or null.',
                    \get_debug_type($resource)
                ));
            }

            $stream = self::tryFopen('php://temp', 'r+');
            if ($resource !== '') {
                fwrite($stream, $resource);
                fseek($stream, 0);
            }

            return new Stream($stream, $options);
        }

        switch (gettype($resource)) {
            case 'resource':
                /*
                 * The 'php://input' is a special stream with quirks and inconsistencies.
                 * We avoid using that stream by reading it into php://temp
                 */

                /** @var resource $resource */
                if ((\stream_get_meta_data($resource)['uri'] ?? '') === 'php://input') {
                    $stream = self::tryFopen('php://temp', 'w+');
                    stream_copy_to_stream($resource, $stream);
                    fseek($stream, 0);
                    $resource = $stream;
                }

                return new Stream($resource, $options);
            case 'object':
                /** @var object $resource */
                if ($resource instanceof StreamInterface) {
                    return $resource;
                } elseif ($resource instanceof \Iterator) {
                    return new PumpStream(function (int $length) use ($resource) {
                        while ($resource->valid()) {
                            $result = $resource->current();
                            $resource->next();

                            if (is_float($result) && !is_finite($result)) {
                                throw new \UnexpectedValueException('Iterator must not yield non-finite float values');
                            }

                            if ($result === null || is_scalar($result)) {
                                $data = (string) $result;
                            } elseif (is_object($result) && method_exists($result, '__toString')) {
                                $data = (string) $result;
                            } else {
                                throw new \UnexpectedValueException('Iterator must yield scalar, null, or stringable values');
                            }

                            if ($data !== '') {
                                return $data;
                            }
                        }

                        return false;
                    }, $options);
                } elseif (method_exists($resource, '__toString')) {
                    return self::streamFor((string) $resource, $options);
                }
                break;
            case 'NULL':
                return new Stream(self::tryFopen('php://temp', 'r+'), $options);
        }

        if (is_callable($resource)) {
            return new PumpStream($resource, $options);
        }

        throw new \InvalidArgumentException('Invalid resource type: '.\get_debug_type($resource));
    }

    /**
     * Safely opens a PHP stream resource using a filename.
     *
     * When `fopen()` fails, PHP normally raises a warning. This function adds
     * an error handler that checks for errors and throws an exception instead.
     *
     * @param string $filename File to open
     * @param string $mode     Mode used to open the file
     *
     * @return resource
     *
     * @throws \RuntimeException if the file cannot be opened
     */
    public static function tryFopen(string $filename, string $mode)
    {
        $ex = null;
        set_error_handler(static function (int $errno, string $errstr) use ($filename, $mode, &$ex): bool {
            $ex = new \RuntimeException(sprintf('Unable to open %s using mode %s: %s', DiagnosticValue::escape($filename), DiagnosticValue::escape($mode), DiagnosticValue::escape($errstr)));

            return true;
        });

        try {
            /** @var resource $handle */
            $handle = fopen($filename, $mode);
        } catch (\Throwable $e) {
            $ex = new \RuntimeException(sprintf('Unable to open %s using mode %s: %s', DiagnosticValue::escape($filename), DiagnosticValue::escape($mode), $e->getMessage()), 0, $e);
        }

        restore_error_handler();

        if ($ex) {
            /** @var \RuntimeException $ex */
            throw $ex;
        }

        return $handle;
    }

    /**
     * Safely gets the contents of a given stream.
     *
     * When `stream_get_contents()` fails, PHP normally raises a warning. This
     * function adds an error handler that checks for errors and throws an
     * exception instead.
     *
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a stream read cannot make progress.
     *
     * The bytes read before a timeout are discarded, so calling this method
     * again does not return them.
     *
     * @param resource $stream
     *
     * @throws \RuntimeException if the stream cannot be read
     */
    public static function tryGetContents($stream): string
    {
        $ex = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$ex): bool {
            $ex = new \RuntimeException(sprintf('Unable to read stream contents: %s', DiagnosticValue::escape($errstr)));

            return true;
        });

        try {
            /** @var string|false $contents */
            $contents = stream_get_contents($stream);

            if ($contents === false) {
                $ex = StreamTimeout::isResourceReadTimedOut($stream)
                    ? new TimeoutException('Unable to read stream contents: timed out')
                    : new \RuntimeException('Unable to read stream contents');
            } elseif (StreamTimeout::isResourceReadTimedOut($stream)) {
                $ex = new TimeoutException('Unable to read stream contents: timed out');
            }
        } catch (TimeoutException $e) {
            $ex = $e;
        } catch (\Throwable $e) {
            $ex = StreamTimeout::isResourceReadTimedOut($stream)
                ? new TimeoutException('Unable to read stream contents: timed out', 0, $e)
                : new \RuntimeException(sprintf('Unable to read stream contents: %s', $e->getMessage()), 0, $e);
        }

        restore_error_handler();

        if ($ex) {
            /** @var \RuntimeException $ex */
            throw $ex;
        }

        return $contents;
    }

    /**
     * Returns a `UriInterface` for the given value.
     *
     * This function accepts a string or `UriInterface` and returns a
     * `UriInterface` for the given value. If the value is already a
     * `UriInterface`, it is returned as-is.
     *
     * @param string|UriInterface $uri
     *
     * @throws \InvalidArgumentException
     */
    public static function uriFor($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        if (is_string($uri)) {
            return new Uri($uri);
        }

        throw new \InvalidArgumentException('URI must be a string or UriInterface');
    }
}
