# PSR-7 Messages

This page covers the PSR-7 message objects provided by this package: requests, responses, server requests, uploaded files, and the message-specific header, URI, and body APIs. Use these objects when you need HTTP messages that can move between Guzzle, PSR-18 clients, PSR-15 middleware, and other PSR-7 compatible libraries.

HTTP requests and responses are both messages. A message has a start line, headers, and an optional body stream. Message and URI objects are immutable; methods named `with*()` return changed copies. Body streams are mutable handles, so reads and writes can change their cursor or contents. For body details, see [Streams and Decorators](streams-and-decorators.md). For URI helpers, see [URI Helpers](uri-helpers.md).

## Creating Requests

You can create a request with `GuzzleHttp\Psr7\Request`.

```php
use GuzzleHttp\Psr7\Request;

$request = new Request('GET', 'https://example.com/users/123');

// You can provide optional headers and a body.
$headers = ['Accept' => 'application/json'];
$body = 'request body';
$request = new Request('PUT', 'https://example.com/users/123', $headers, $body);
```

## Creating Responses

You can create a response with `GuzzleHttp\Psr7\Response`.

```php
use GuzzleHttp\Psr7\Response;

// The constructor requires no arguments.
$response = new Response();
echo $response->getStatusCode();
// 200
echo $response->getProtocolVersion();
// 1.1

// You can provide a status, headers, body, and protocol version.
$response = new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}', '1.1');
```

## Creating Server Requests

Server requests represent incoming HTTP requests on the server side. They include the normal request method, URI, headers, and body, plus server parameters, cookies, query parameters, parsed body data, attributes, and uploaded files.

```php
use GuzzleHttp\Psr7\ServerRequest;

$request = new ServerRequest('POST', 'https://example.com/form', [], 'name=Guzzle', '1.1', [
    'REMOTE_ADDR' => '192.0.2.1',
]);

$request = $request
    ->withCookieParams(['session' => 'abc'])
    ->withQueryParams(['page' => '1'])
    ->withParsedBody(['name' => 'Guzzle'])
    ->withAttribute('route', 'profile');

echo $request->getServerParams()['REMOTE_ADDR'];
echo $request->getCookieParams()['session'];
echo $request->getQueryParams()['page'];
echo $request->getParsedBody()['name'];
echo $request->getAttribute('route');
```

Use `ServerRequest::fromGlobals()` to create a server request from PHP superglobals. It reads `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, and `$_FILES`, and attempts to include request headers when available.

```php
use GuzzleHttp\Psr7\ServerRequest;

$request = ServerRequest::fromGlobals();
```

Use `ServerRequest::getUriFromGlobals()` when you only need the URI derived from `$_SERVER`.

```php
use GuzzleHttp\Psr7\ServerRequest;

$uri = ServerRequest::getUriFromGlobals();
```

For URI construction and normalization helpers, see [URI Helpers](uri-helpers.md).

## Requests

```php
use GuzzleHttp\Psr7\Request;

$request = new Request('GET', 'https://example.com/users/123', [
    'Accept' => 'application/json',
]);

echo $request->getMethod();
echo $request->getUri();
```

PSR-7 messages are immutable. Methods such as `withHeader()` and `withUri()` return a modified copy.

```php
$jsonRequest = $request->withHeader('Accept', 'application/json');
```

## Responses

```php
use GuzzleHttp\Psr7\Response;

$response = new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}');

echo $response->getStatusCode();
echo $response->getHeaderLine('Content-Type');
echo $response->getBody();
```

## URIs

```php
use GuzzleHttp\Psr7\Uri;

$uri = new Uri('https://example.com/users?active=1');

echo $uri->getHost();
echo $uri->getQuery();
```

For URI-specific helper methods, see [URI Helpers](uri-helpers.md).

## Headers

Both request and response messages contain HTTP headers.

### Accessing Headers

You can check if a request or response has a specific header using `hasHeader()`.

```php
use GuzzleHttp\Psr7\Request;

$request = new Request('GET', '/', ['X-Foo' => 'bar']);

if ($request->hasHeader('X-Foo')) {
    echo 'It is there';
}
```

Retrieve all header values as an array of strings with `getHeader()`.

```php
$request->getHeader('X-Foo');
// ['bar']

// Missing headers return an empty array.
$request->getHeader('X-Bar');
// []
```

Iterate over the headers of a message with `getHeaders()`.

```php
foreach ($request->getHeaders() as $name => $values) {
    echo $name . ': ' . implode(', ', $values) . "\r\n";
}
```

### Complex Headers

Some headers contain additional key-value pair information. For example, `Link` headers contain a link and additional parameters:

```http
<https://example.com/front.jpeg>; rel="front"; type="image/jpeg"
```

Use `GuzzleHttp\Psr7\Header::parse()` to parse these headers.

```php
use GuzzleHttp\Psr7\Header;
use GuzzleHttp\Psr7\Request;

$request = new Request('GET', '/', [
    'Link' => '<https://example.com/front.jpeg>; rel="front"; type="image/jpeg"',
]);

$parsed = Header::parse($request->getHeader('Link'));
var_export($parsed);
```

This outputs:

```php
array (
  0 =>
  array (
    0 => '<https://example.com/front.jpeg>',
    'rel' => 'front',
    'type' => 'image/jpeg',
  ),
)
```

The result contains key-value pairs. Header values that have no key are indexed numerically, while header parts that form a key-value pair are added with their parameter name.

## Body

Request and response bodies are `Psr\Http\Message\StreamInterface` instances. Streams are used for both uploading data and downloading data.

```php
use GuzzleHttp\Psr7\Response;

$response = new Response(200, [], 'response body');

echo $response->getBody();
// response body
```

The body can be cast to a string, or you can read bytes from the stream as needed.

```php
$body = $response->getBody();

echo $body->read(4);
$body->seek(0);
echo $body->getContents();
```

For more stream creation and decorator examples, see [Streams and Decorators](streams-and-decorators.md).

## Uploaded Files

Uploaded files are represented by `Psr\Http\Message\UploadedFileInterface` instances. This package provides `GuzzleHttp\Psr7\UploadedFile`, which can wrap a local file path, PHP stream resource, or PSR-7 stream.

```php
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;

$stream = Utils::streamFor('file contents');
$upload = new UploadedFile($stream, $stream->getSize(), UPLOAD_ERR_OK, 'example.txt', 'text/plain');

echo $upload->getClientFilename();
echo $upload->getClientMediaType();
echo $upload->getSize();
```

Call `getStream()` to read the uploaded content, or `moveTo()` to move or copy it to a target path. After `moveTo()` succeeds, `isMoved()` returns `true`, and calls that need the active upload stream will throw.

```php
$body = $upload->getStream();
echo $body->getContents();

$upload->moveTo('/path/to/target.txt');
var_export($upload->isMoved());
// true
```

If the upload error code is not `UPLOAD_ERR_OK`, the object still exposes `getError()`, `getSize()`, `getClientFilename()`, and `getClientMediaType()`, but `getStream()` and `moveTo()` throw because no successful upload content is available.

`ServerRequest::normalizeFiles()` converts a `$_FILES`-style array into a tree of uploaded file instances. It accepts simple file specs, nested PHP `$_FILES` shapes, existing `UploadedFileInterface` instances, and nested arrays of uploaded files.

```php
use GuzzleHttp\Psr7\ServerRequest;

$files = ServerRequest::normalizeFiles([
    'avatar' => [
        'tmp_name' => '/tmp/php123',
        'size' => 1024,
        'error' => UPLOAD_ERR_OK,
        'name' => 'avatar.png',
        'type' => 'image/png',
    ],
    'photos' => [
        'tmp_name' => [
            'first' => '/tmp/php456',
        ],
        'size' => [
            'first' => 2048,
        ],
        'error' => [
            'first' => UPLOAD_ERR_OK,
        ],
        'name' => [
            'first' => 'photo.jpg',
        ],
        'type' => [
            'first' => 'image/jpeg',
        ],
    ],
]);

$request = (new ServerRequest('POST', '/upload'))->withUploadedFiles($files);
```

## HTTP Method Casing

HTTP method names are case-sensitive in PSR-7. Requests created explicitly with
`Request`, `ServerRequest`, `withMethod()`, `Message::parseRequest()`, or the
PSR-17 factories preserve the method string as provided. `ServerRequest::fromGlobals()`
normalizes `$_SERVER['REQUEST_METHOD']` to uppercase for compatibility when
hydrating requests from PHP server globals.

## Request Methods

When creating a request, provide the HTTP method you want to perform. You can specify any method, including custom methods that are not part of RFC 9110.

```php
use GuzzleHttp\Psr7\Request;

$request = new Request('MOVE', 'https://example.com/resource');

echo $request->getMethod();
// MOVE
```

## Request URI

The request URI is represented by a `Psr\Http\Message\UriInterface` object. This package provides an implementation through `GuzzleHttp\Psr7\Uri`.

When creating a request, you can provide the URI as a string or as a `UriInterface` instance.

```php
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;

$request = new Request('GET', new Uri('https://example.com/users?id=123'));
```

### Scheme

The [scheme](https://datatracker.ietf.org/doc/html/rfc3986#section-3.1) specifies the protocol. For HTTP requests, this is usually `http` or `https`.

```php
$request = new Request('GET', 'https://example.com');
echo $request->getUri()->getScheme();
// https
```

### Host

The host is accessible from the URI and is also represented by the `Host` header.

```php
$request = new Request('GET', 'https://example.com');
echo $request->getUri()->getHost();
// example.com
echo $request->getHeaderLine('Host');
// example.com
```

### Port

No port is necessary for the default `http` and `https` ports.

```php
$request = new Request('GET', 'https://example.com:8443');
echo $request->getUri()->getPort();
// 8443
```

### Path

The request path is accessible through the URI object.

```php
$request = new Request('GET', 'https://example.com/users/123');
echo $request->getUri()->getPath();
// /users/123
```

Characters that are not allowed in a URI path are percent-encoded according to [RFC 3986 section 3.3](https://datatracker.ietf.org/doc/html/rfc3986#section-3.3).

### Query String

The query string is accessible through the URI object.

```php
$request = new Request('GET', 'https://example.com/?foo=bar');
echo $request->getUri()->getQuery();
// foo=bar
```

Characters that are not allowed in a URI query are percent-encoded according to [RFC 3986 section 3.4](https://datatracker.ietf.org/doc/html/rfc3986#section-3.4).

## Response Status

Responses expose the status code, reason phrase, and protocol version.

```php
use GuzzleHttp\Psr7\Response;

$response = new Response(200, [], 'OK');

echo $response->getStatusCode();
// 200
echo $response->getReasonPhrase();
// OK
echo $response->getProtocolVersion();
// 1.1
```

## Related

- [Streams and Decorators](streams-and-decorators.md)
- [URI Helpers](uri-helpers.md)
- [Message Helpers](message-helpers.md)
- [Header and Query Helpers](header-and-query-helpers.md)
- [PSR-17 Factories](psr-17-factories.md)
