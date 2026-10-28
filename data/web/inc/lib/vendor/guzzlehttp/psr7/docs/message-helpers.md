# Message Helpers

This page covers static helper methods for converting, parsing, summarizing,
rewinding, and cloning PSR-7 messages. For conceptual request and response
behavior, start with [PSR-7 Messages](psr-7-messages.md).

## `GuzzleHttp\Psr7\Message::toString`

`public static function toString(MessageInterface $message): string`

Returns the string representation of an HTTP message.

```php
$request = new GuzzleHttp\Psr7\Request('GET', 'http://example.com');
echo GuzzleHttp\Psr7\Message::toString($request);
```

## `GuzzleHttp\Psr7\Message::bodySummary`

`public static function bodySummary(MessageInterface $message, ?int $truncateAt = null): string|null`

Get a short summary of the message body.

Will return `null` if the response is not printable.

Reads seekable bodies from the beginning and restores the original cursor
position before returning. Pass `null` for `$truncateAt` to use the default
summary length.

## `GuzzleHttp\Psr7\Message::rewindBody`

`public static function rewindBody(MessageInterface $message): void`

Attempts to rewind a message body and throws an exception on failure.

The body of the message will only be rewound if a call to `tell()` returns a
value other than `0`.

## `GuzzleHttp\Psr7\Message::parseMessage`

`public static function parseMessage(string $message): array`

Parses an HTTP message into an associative array.

The array contains the `start-line` key containing the start line of the
message, `headers` key containing an associative array of header array values,
and a `body` key containing the body of the message.

## `GuzzleHttp\Psr7\Message::parseRequestUri`

`public static function parseRequestUri(string $path, array $headers): string`

Constructs a URI for an HTTP request message.

The URI is composed from the start-line path and the `Host` header, using
`https` when the host's port is `443` and `http` otherwise. Without a `Host`
header, only the path is returned, with extra leading slashes collapsed so an
origin-form target cannot be parsed as a network-path reference with its own
authority. An `InvalidArgumentException` is thrown when the `Host` header is
invalid.

## `GuzzleHttp\Psr7\Message::parseRequest`

`public static function parseRequest(string $message): RequestInterface`

Parses a request message string into a request object.

The request-target must be in origin form, absolute form (without a userinfo
component), authority form (`CONNECT`), or asterisk form (`OPTIONS`), and any
`Host` header must be a single valid value; otherwise an
`InvalidArgumentException` is thrown. Non-origin-form targets are preserved on
the returned request via `withRequestTarget()`.

## `GuzzleHttp\Psr7\Message::parseResponse`

`public static function parseResponse(string $message): ResponseInterface`

Parses a response message string into a response object.

## `GuzzleHttp\Psr7\Utils::modifyRequest`

`public static function modifyRequest(RequestInterface $request, array $changes): RequestInterface`

Clone and modify a request with the given changes.

This method is useful for reducing the number of clones needed to mutate a
message.

The changes can be one of:

- method: (string) Changes the HTTP method.
- set_headers: (array) Sets the given headers. Values must be strings or
  non-empty arrays of strings.
- remove_headers: (array) Remove the given headers. Values may be strings or
  integers.
- body: (mixed) Sets the given body. Present non-null values are converted with
  `GuzzleHttp\Psr7\Utils::streamFor()`, including resources, streams, iterators,
  callable arrays, closures, invokable objects, and stringable objects. String
  inputs remain literal bodies.
- uri: (UriInterface) Set the URI. When the URI contains a host, the Host header
  is updated from it, and combining this with an explicit Host entry in
  set_headers throws an InvalidArgumentException. Apply an intentional Host
  override separately with withHeader() afterwards.
- query: (string) Set the query string value of the URI.
- version: (string) Set the protocol version.

## Related

- [PSR-7 Messages](psr-7-messages.md)
- [Header and Query Helpers](header-and-query-helpers.md)
- [Stream Helpers](stream-helpers.md)
- [URI and MIME Helpers](uri-and-mime-helpers.md)
