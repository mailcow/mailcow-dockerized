# URI and MIME Helpers

This page covers small helper methods for redacting URI user info, converting
values into URI objects, and resolving MIME types from filenames or extensions.
For URI resolution, normalization, and comparison helpers, see
[URI Helpers](uri-helpers.md).

## `GuzzleHttp\Psr7\Utils::redactUserInfo`

`public static function redactUserInfo(UriInterface $uri): UriInterface`

Redact the user info part of a URI.

Returns the URI with the whole userinfo component replaced by `***` when one
is present, so neither the username nor the password survives into logs and
diagnostics. A URI without userinfo is returned unchanged.

## `GuzzleHttp\Psr7\Utils::redactUserInfoInString`

`public static function redactUserInfoInString(string $subject, string $uri): string`

Redacts the userinfo of a raw URI string wherever it appears in a subject
string.

The needle is taken verbatim from the raw URI rather than from parsed
components, so credentials that URI normalization would rewrite, such as raw
control bytes or unencoded reserved characters, are still found in text that
embeds the URI exactly as given, for example transport error messages. A URI
without `://` is treated as authority-form: a host and port with optional
userinfo.

A URI that does not parse has no trustworthy authority boundary, so everything
between any scheme and its last `@` is redacted as a safe-side fallback.

## `GuzzleHttp\Psr7\Utils::redactUriForMessage`

`public static function redactUriForMessage(UriInterface $uri): string`

Formats a URI for automatic diagnostics.

The whole userinfo component is replaced by "***", and the query and
fragment are removed. The scheme, host, port, and path are preserved.
The returned string is diagnostic-escaped and the formatter never
throws.

## `GuzzleHttp\Psr7\Utils::redactUriStringForMessage`

`public static function redactUriStringForMessage(string $uri): string`

Formats a raw URI for automatic diagnostics, including malformed input.

The whole userinfo component is replaced by "***", and the query and
fragment are removed. The scheme, host, port, and path are preserved
where their boundaries can be determined safely. The returned string is
diagnostic-escaped and the formatter never throws.

## `GuzzleHttp\Psr7\Utils::uriFor`

`public static function uriFor(string|UriInterface $uri): UriInterface`

Returns a `UriInterface` for the given value.

This function accepts a string or `UriInterface` and returns a `UriInterface`
for the given value. If the value is already a `UriInterface`, it is returned
as-is.

## `GuzzleHttp\Psr7\MimeType::fromFilename`

`public static function fromFilename(string $filename): string|null`

Determines the MIME type of a file by looking at its extension.

## `GuzzleHttp\Psr7\MimeType::fromExtension`

`public static function fromExtension(string $extension): string|null`

Maps a file extension to a MIME type.

## Related

- [URI Helpers](uri-helpers.md)
- [Header and Query Helpers](header-and-query-helpers.md)
- [Stream Helpers](stream-helpers.md)
