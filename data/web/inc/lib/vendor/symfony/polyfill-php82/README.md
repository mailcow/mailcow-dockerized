Symfony Polyfill / Php82
========================

This component provides features added to PHP 8.2 core:

- [`AllowDynamicProperties`](https://wiki.php.net/rfc/deprecate_dynamic_properties)
- [`SensitiveParameter`](https://wiki.php.net/rfc/redact_parameters_in_back_traces)
- [`SensitiveParameterValue`](https://wiki.php.net/rfc/redact_parameters_in_back_traces)
- [`Random\Engine`](https://wiki.php.net/rfc/rng_extension)
- [`Random\Engine\CryptoSafeEngine`](https://wiki.php.net/rfc/rng_extension)
- [`Random\Engine\Secure`](https://wiki.php.net/rfc/rng_extension) (check [arokettu/random-polyfill](https://packagist.org/packages/arokettu/random-polyfill) for more engines)
- [`odbc_connection_string_is_quoted()`](https://php.net/odbc_connection_string_is_quoted)
- [`odbc_connection_string_should_quote()`](https://php.net/odbc_connection_string_should_quote)
- [`odbc_connection_string_quote()`](https://php.net/odbc_connection_string_quote)
- [`ini_parse_quantity()`](https://php.net/ini_parse_quantity)

More information can be found in the
[main Polyfill README](https://github.com/symfony/polyfill/blob/main/README.md).

License
=======

This library is released under the [MIT license](LICENSE).
