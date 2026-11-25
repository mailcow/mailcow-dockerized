# PSR-17 Factories

This page explains `GuzzleHttp\Psr7\HttpFactory`, the PSR-17 factory implementation provided by this package. Use it when code expects PSR-17 factory interfaces and you want factories that create Guzzle PSR-7 messages, streams, uploaded files, and URIs.

## `GuzzleHttp\Psr7\HttpFactory`

`GuzzleHttp\Psr7\HttpFactory` implements all PSR-17 factory interfaces: `RequestFactoryInterface`, `ResponseFactoryInterface`, `ServerRequestFactoryInterface`, `StreamFactoryInterface`, `UploadedFileFactoryInterface`, and `UriFactoryInterface`.

```php
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory();

$request = $factory->createRequest('GET', 'https://example.com');
$response = $factory->createResponse(200);
$serverRequest = $factory->createServerRequest('POST', '/submit', ['REMOTE_ADDR' => '192.0.2.1']);
$stream = $factory->createStream('body');
$uri = $factory->createUri('https://example.com/path');
```

It also creates streams from files and resources, and uploaded files from streams.

```php
$stream = $factory->createStreamFromFile('/path/to/file.txt', 'r');
$upload = $factory->createUploadedFile($stream, $stream->getSize(), UPLOAD_ERR_OK, 'file.txt', 'text/plain');
```

## Related

- [PSR-7 Messages](psr-7-messages.md)
- [Streams and Decorators](streams-and-decorators.md)
- [URI Helpers](uri-helpers.md)
- [Message Helpers](message-helpers.md)
