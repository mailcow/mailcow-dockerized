# Guzzle PSR-7

`guzzlehttp/psr7` is a PSR-7 HTTP message implementation for PHP. It provides
request, response, URI, uploaded file, and stream objects that work with Guzzle
and any other library using the PSR-7 interfaces.

Use this package directly when you need to create or inspect PSR-7 messages
without sending HTTP requests. If you only want to make HTTP requests, install
[`guzzlehttp/guzzle`](https://github.com/guzzle/guzzle/blob/8.0/README.md)
instead; it already depends on this package.

## Installation

```bash
composer require guzzlehttp/psr7
```

## Version Guidance

| Version | Status       | PHP Version  |
|---------|--------------|--------------|
| 3.2     | Latest       | >=7.4,<8.7   |
| 2.13    | Maintenance  | >=7.2.5,<8.7 |
| 1.9     | End of Life  | >=5.4,<8.2   |

## Quick Start

```php
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

$request = new Request('GET', 'https://example.com/api');
$response = new Response(200, ['Content-Type' => 'text/plain'], 'OK');
$stream = Utils::streamFor('request or response body');

echo $request->getMethod();
echo $response->getStatusCode();
echo $stream;
```

PSR-7 messages and URIs are immutable. Methods such as `withHeader()` and
`withUri()` return a changed copy instead of modifying the original object.
Streams are mutable body handles; reading, writing, seeking, and closing a
stream can change its cursor, contents, or usability.

```php
$request = $request->withHeader('Accept', 'application/json');
```

## Documentation

- [PSR-7 Messages](docs/psr-7-messages.md)
- [Streams and Decorators](docs/streams-and-decorators.md)
- [URI Helpers](docs/uri-helpers.md)
- [PSR-17 Factories](docs/psr-17-factories.md)
- [Message Helpers](docs/message-helpers.md)
- [Diagnostic Values](docs/diagnostic-values.md)
- [Header and Query Helpers](docs/header-and-query-helpers.md)
- [Stream Helpers](docs/stream-helpers.md)
- [URI and MIME Helpers](docs/uri-and-mime-helpers.md)
- [Upgrade Guide](UPGRADING.md)
- [Changelog](CHANGELOG.md)

## Security

If you discover a security vulnerability within this package, please send an
email to security@tidelift.com. All security vulnerabilities will be promptly
addressed by the security maintainers listed in the
[Security Policy](https://github.com/guzzle/psr7/security/policy). Please do not
disclose security-related issues publicly until a fix has been announced.

## License

Guzzle is made available under the MIT License (MIT). Please see
[License File](LICENSE) for more information.

## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of Guzzle and thousands of other packages are working with
Tidelift to deliver commercial support and maintenance for the open source
dependencies you use to build your applications. Save time, reduce risk, and
improve code health, while paying the maintainers of the exact dependencies you
use.
[Learn more.](https://tidelift.com/subscription/pkg/packagist-guzzlehttp-psr7?utm_source=packagist-guzzlehttp-psr7&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
