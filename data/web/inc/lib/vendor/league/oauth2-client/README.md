# OAuth 2.0 Client

This package provides a base for integrating with [OAuth 2.0](http://oauth.net/2/) service providers.

[![Gitter Chat](https://img.shields.io/badge/gitter-join_chat-brightgreen.svg?style=flat-square)](https://gitter.im/thephpleague/oauth2-client)
[![Source Code](https://img.shields.io/badge/source-thephpleague/oauth2--client-blue.svg?style=flat-square)](https://github.com/thephpleague/oauth2-client)
[![Latest Version](https://img.shields.io/github/release/thephpleague/oauth2-client.svg?style=flat-square)](https://github.com/thephpleague/oauth2-client/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/thephpleague/oauth2-client/blob/master/LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/thephpleague/oauth2-client/continuous-integration.yml?label=CI&logo=github&style=flat-square)](https://github.com/thephpleague/oauth2-client/actions?query=workflow%3ACI)
[![Codecov Code Coverage](https://img.shields.io/codecov/c/gh/thephpleague/oauth2-client?label=codecov&logo=codecov&style=flat-square)](https://codecov.io/gh/thephpleague/oauth2-client)
[![Total Downloads](https://img.shields.io/packagist/dt/league/oauth2-client.svg?style=flat-square)](https://packagist.org/packages/league/oauth2-client)

---

The OAuth 2.0 login flow, seen commonly around the web in the form of "Connect with Facebook/Google/etc." buttons, is a common integration added to web applications, but it can be tricky and tedious to do right. To help, we've created the `league/oauth2-client` package, which provides a base for integrating with various OAuth 2.0 providers, without overburdening your application with the concerns of [RFC 6749](http://tools.ietf.org/html/rfc6749).

This OAuth 2.0 client library will work with any OAuth 2.0 provider that conforms to the OAuth 2.0 Authorization Framework. Out-of-the-box, we provide a `GenericProvider` class to connect to any service provider that uses [Bearer tokens](http://tools.ietf.org/html/rfc6750). See our [basic usage guide](https://oauth2-client.thephpleague.com/usage/) for examples using `GenericProvider`.

Many service providers provide additional functionality above and beyond the OAuth 2.0 specification. For this reason, you may extend and wrap this library to support additional behavior. There are already many [official](https://oauth2-client.thephpleague.com/providers/league/) and [third-party](https://oauth2-client.thephpleague.com/providers/thirdparty/) provider clients available (e.g., Facebook, GitHub, Google, Instagram, LinkedIn, etc.). If your provider isn't in the list, feel free to add it.

This package is compliant with [PSR-1][], [PSR-2][], [PSR-4][], and [PSR-7][]. If you notice compliance oversights, please send a patch via pull request. If you're interested in contributing to this library, please take a look at our [contributing guidelines](https://github.com/thephpleague/oauth2-client/blob/master/CONTRIBUTING.md).

## Requirements

We support the following versions of PHP:

* PHP 8.1
* PHP 8.0
* PHP 7.4
* PHP 7.3
* PHP 7.2
* PHP 7.1
* PHP 7.0
* PHP 5.6

## Provider Clients

We provide a list of [official PHP League provider clients](https://oauth2-client.thephpleague.com/providers/league/), as well as [third-party provider clients](https://oauth2-client.thephpleague.com/providers/thirdparty/).

To build your own provider client, please refer to "[Implementing a Provider Client](https://oauth2-client.thephpleague.com/providers/implementing/)."

## Usage

For usage and code examples, check out our [basic usage guide](https://oauth2-client.thephpleague.com/usage/).

## Contributing

Please see [our contributing guidelines](https://github.com/thephpleague/oauth2-client/blob/master/CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [LICENSE](https://github.com/thephpleague/oauth2-client/blob/master/LICENSE) for more information.


[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md
[PSR-7]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md
