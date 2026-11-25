# Header and Query Helpers

This page covers helper methods for parsing structured header values, splitting
list headers, and parsing or building query strings. For basic message header
behavior, see [PSR-7 Messages](psr-7-messages.md).

## `GuzzleHttp\Psr7\Header::parse`

`public static function parse(string|array $header): array`

Parses semicolon-separated header parameters into associative arrays, one per
comma-separated header value. Parameters without a value are appended as values
under integer keys.

## `GuzzleHttp\Psr7\Header::splitList`

`public static function splitList(string|string[] $header): string[]`

Splits an HTTP header defined to contain a comma-separated list into each
individual value. Empty values are removed:

```php
$knownEtags = Header::splitList($request->getHeader('if-none-match'));
```

Example headers include `accept`, `cache-control`, and `if-none-match`.

This method must not be used to parse headers that are not defined as a list,
such as `user-agent` or `set-cookie`.

## `GuzzleHttp\Psr7\Query::parse`

`public static function parse(string $str, int|bool $urlEncoding = true): array`

Parse a query string into an associative array.

If multiple values are found for the same key, the value of that key-value pair
becomes an array. This function does not parse nested PHP style arrays into an
associative array. For example, `foo[a]=1&foo[b]=2` will be parsed into
`['foo[a]' => '1', 'foo[b]' => '2']`.

## `GuzzleHttp\Psr7\Query::build`

`public static function build(array $params, int|false $encoding = PHP_QUERY_RFC3986, bool $treatBoolsAsInts = true): string`

Build a query string from an array of key-value pairs.

This function can use the return value of `parse()` to build a query string.
This function does not modify the provided keys when an array is encountered,
unlike `http_build_query()`.

## `GuzzleHttp\Psr7\Utils::asciiToLower`

`public static function asciiToLower(string $string): string`

Converts ASCII uppercase letters in a string to lowercase.

Unlike `strtolower()`, which honors `LC_CTYPE` before PHP 8.2, the conversion is
locale-independent and leaves every non-ASCII byte unchanged, as HTTP protocol
elements require.

## `GuzzleHttp\Psr7\Utils::asciiToUpper`

`public static function asciiToUpper(string $string): string`

Converts ASCII lowercase letters in a string to uppercase.

Unlike `strtoupper()`, which honors `LC_CTYPE` before PHP 8.2, the conversion is
locale-independent and leaves every non-ASCII byte unchanged, as HTTP protocol
elements require.

## `GuzzleHttp\Psr7\Utils::asciiUcFirst`

`public static function asciiUcFirst(string $string): string`

Converts the first character of a string to uppercase when it is an ASCII
lowercase letter.

Unlike `ucfirst()`, which honors `LC_CTYPE` before PHP 8.2, the conversion is
locale-independent and leaves every non-ASCII byte unchanged, as HTTP protocol
elements require.

## `GuzzleHttp\Psr7\Utils::caselessContains`

`public static function caselessContains(string $haystack, string $needle): bool`

Checks whether the haystack contains the needle, comparing ASCII letters
case-insensitively and without locale sensitivity.

## `GuzzleHttp\Psr7\Utils::caselessEquals`

`public static function caselessEquals(string $left, string $right): bool`

Checks whether two strings are equal, comparing ASCII letters case-insensitively
and without locale sensitivity.

## `GuzzleHttp\Psr7\Utils::caselessRemove`

`public static function caselessRemove(array $keys, array $data): array`

Remove the items given by the keys from the data, case-insensitively.

## Related

- [PSR-7 Messages](psr-7-messages.md)
- [Message Helpers](message-helpers.md)
- [URI Helpers](uri-helpers.md)
