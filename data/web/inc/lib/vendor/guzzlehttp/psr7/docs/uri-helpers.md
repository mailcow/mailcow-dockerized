# URI Helpers

This page covers this package's `Psr\Http\Message\UriInterface` implementation
and URI helper classes for classifying, composing, resolving, normalizing,
comparing, and safely modifying URIs.

Aside from the standard `Psr\Http\Message\UriInterface` implementation provided
by the `GuzzleHttp\Psr7\Uri` class, this library also provides additional static
methods for working with URIs.

## URI Types

An instance of `Psr\Http\Message\UriInterface` can either be an absolute URI or
a relative reference. An absolute URI has a scheme. A relative reference is used
to express a URI relative to another URI, the base URI. Relative references can
be divided into several forms according to
[RFC 3986 Section 4.2](https://datatracker.ietf.org/doc/html/rfc3986#section-4.2):

- network-path references, e.g. `//example.com/path`
- absolute-path references, e.g. `/path`
- relative-path references, e.g. `subpath`

The following methods can be used to identify the type of the URI.

### `GuzzleHttp\Psr7\Uri::isAbsolute`

`public static function isAbsolute(UriInterface $uri): bool`

Whether the URI is absolute, i.e. it has a scheme.

### `GuzzleHttp\Psr7\Uri::isNetworkPathReference`

`public static function isNetworkPathReference(UriInterface $uri): bool`

Whether the URI is a network-path reference. A relative reference that begins
with two slash characters is termed a network-path reference.

### `GuzzleHttp\Psr7\Uri::isAbsolutePathReference`

`public static function isAbsolutePathReference(UriInterface $uri): bool`

Whether the URI is an absolute-path reference. A relative reference that begins
with a single slash character is termed an absolute-path reference.

### `GuzzleHttp\Psr7\Uri::isRelativePathReference`

`public static function isRelativePathReference(UriInterface $uri): bool`

Whether the URI is a relative-path reference. A relative reference that does not
begin with a slash character is termed a relative-path reference.

### `GuzzleHttp\Psr7\Uri::isSameDocumentReference`

`public static function isSameDocumentReference(UriInterface $uri, ?UriInterface $base = null): bool`

Whether the URI is a same-document reference. A same-document reference refers
to a URI that is, aside from its fragment component, identical to the base URI.
When no base URI is given, only an empty URI reference (apart from its fragment)
is considered a same-document reference.

## URI Syntax Validation

`GuzzleHttp\Psr7\Rfc3986` provides static methods for validating and
canonicalizing individual URI components against the grammar defined by
[RFC 3986](https://datatracker.ietf.org/doc/html/rfc3986). They operate on raw
component strings rather than on `Psr\Http\Message\UriInterface` instances.

### `GuzzleHttp\Psr7\Rfc3986::isValidScheme`

`public static function isValidScheme(string $scheme): bool`

Whether the string is a valid URI scheme. Per
[RFC 3986 Section 3.1](https://datatracker.ietf.org/doc/html/rfc3986#section-3.1),
a scheme must start with a letter, followed by any number of letters, digits,
`+`, `-`, or `.`. The empty string is also accepted, since a URI reference may
omit the scheme.

### `GuzzleHttp\Psr7\Rfc3986::isValidHost`

`public static function isValidHost(string $host): bool`

Whether the string is a valid URI host. Per
[RFC 3986 Section 3.2.2](https://datatracker.ietf.org/doc/html/rfc3986#section-3.2.2),
the host is an IP-literal, IPv4 address, or registered name. An empty host is
accepted, since the authority, and thus the host, may be empty. Bracketed values
are validated as IPv6 or IPvFuture literals; any other value is rejected if it
contains control characters, whitespace, an authority or path delimiter (`/`,
`?`, `#`, `@`, `\`), or an embedded colon denoting a port. Percent-encoding is
validated the same way: malformed sequences (a `%` not followed by two hex
digits) and percent-encoded octets that decode to one of the rejected bytes, to
a bracket (`[`, `]`), or to `%` itself are invalid, while all other
percent-encoded octets are accepted. Rejecting these percent-encoded octets is a
deliberate guzzle host policy, stricter than the RFC 3986 `reg-name` grammar,
which permits any well-formed `pct-encoded` octet; it matches the stricter
policy used throughout the library. RFC 6874 IPv6 zone identifiers (for example
`[fe80::1%25eth0]`) are not supported.

Registered names are otherwise intentionally permissive: single-label hosts such
as `localhost`, underscores, sub-delims, and raw or percent-encoded non-ASCII
(IDN) data are accepted and preserved as given, with no punycode conversion.
IDNA is treated as a client concern. Consumers that need DNS IDNs must perform
the conversion themselves, for example via Guzzle's `idn_conversion` request
option.

Validity here is a statement about URI syntax only. It does not imply that the
host resolves or that a client will interpret it exactly as written. A PSR-7 URI
object does not know which resolver or transport will consume it, so it cannot
establish destination identity. Consumers that restrict the destination must
enforce that policy at the client or transport boundary and ensure the
connection uses an approved address; checking the spelling or a separate DNS
lookup is insufficient.

### `GuzzleHttp\Psr7\Rfc3986::isValidPort`

`public static function isValidPort(string $port): bool`

Whether the string is a valid port number. RFC 3986 defines the port as
`*DIGIT`, which also permits an empty port and has no upper bound; this applies
the stricter policy used throughout the library instead, accepting a non-empty
run of digits (leading zeros are accepted and normalized) that resolves to a
value in the range 0-65535.

### `GuzzleHttp\Psr7\Rfc3986::canonicalizeIpv6`

`public static function canonicalizeIpv6(string $address): string`

Returns the [RFC 5952](https://datatracker.ietf.org/doc/html/rfc5952#section-4)
canonical form of a valid IPv6 address. The address must be a valid textual
IPv6 address without brackets and without a zone identifier, such as the
inside of an IP-literal accepted by `isValidHost()`. Canonicalization
lowercases the hexadecimal fields, suppresses leading zeros, and collapses the
longest run of two or more zero fields (the leftmost on a tie) with `::`.
Embedded dotted-decimal notation follows the rendering policy of BIND-derived
`inet_ntop()` implementations and curl: exactly the IPv4-mapped
(`::ffff:0:0/96`) and deprecated IPv4-compatible (`::/96`) layouts use it,
while other embedded-IPv4 forms, including translated (NAT64) well-known
prefixes such as `64:ff9b::/96` (RFC 6052), serialize in pure hexadecimal
fields. An `\InvalidArgumentException` is thrown if the address cannot be
parsed.

Validation is strict and platform-independent: the address is checked against
the RFC 3986 `IPv6address` grammar with PHP's `FILTER_VALIDATE_IP` filter and
parsed in pure PHP, so spellings that only some platform parsers accept, such as
the zero-padded dotted octets in `::ffff:192.168.001.001`, are rejected
everywhere.

Emitting dotted-decimal notation for these two selected layouts is the
BIND-derived `inet_ntop()` and curl compatibility policy used here.
[RFC 5952 Section 5](https://datatracker.ietf.org/doc/html/rfc5952#section-5)
permits mixed notation for recognizable embedded-IPv4 prefixes but does not
limit that category to these layouts; RFC 6052, for example, defines
`64:ff9b::/96` as a Well-Known Prefix, which this implementation renders in
pure hexadecimal. The WHATWG URL Standard always emits pure hexadecimal
fields.

## URI Components

Additional methods to work with URI components.

### `GuzzleHttp\Psr7\Uri::isDefaultPort`

`public static function isDefaultPort(UriInterface $uri): bool`

Whether the URI has the default port of the current scheme.
`Psr\Http\Message\UriInterface::getPort` may return null or the standard port.
This method can be used independently of the implementation.

### `GuzzleHttp\Psr7\Uri::composeComponents`

`public static function composeComponents(?string $scheme, ?string $authority, string $path, ?string $query, ?string $fragment): string`

Composes a URI reference string from its various components according to
[RFC 3986 Section 5.3](https://datatracker.ietf.org/doc/html/rfc3986#section-5.3).
Usually this method does not need to be called manually but instead is used
indirectly via `Psr\Http\Message\UriInterface::__toString`.

PSR-7 UriInterface treats an empty component the same as a missing component as
`getQuery()`, `getFragment()` etc. always return a string. This explains the
slight difference to RFC 3986 Section 5.3.

Another adjustment is that the authority separator is added even when the
authority is missing/empty for the "file" scheme. This is because PHP stream
functions like `file_get_contents` only work with `file:///myfile` but not with
`file:/myfile` although they are equivalent according to RFC 3986. But
`file:///` is the more common syntax for the file scheme anyway (Chrome for
example redirects to that format). The separator is omitted when such a URI has
a rootless or empty path: adding it would turn the first path segment into the
authority of the composed URI, or compose the string `file://`, which cannot be
parsed back into a URI.

### `GuzzleHttp\Psr7\Uri::fromParts`

`public static function fromParts(array $parts): UriInterface`

Creates a URI from a hash of
[`parse_url`](https://www.php.net/manual/en/function.parse-url.php) components.

### `GuzzleHttp\Psr7\Uri::withQueryValue`

`public static function withQueryValue(UriInterface $uri, string $key, ?string $value): UriInterface`

Creates a new URI with a specific query string value. Any existing query string
values that exactly match the provided key are removed and replaced with the
given key value pair. A value of null will set the query string key without a
value, e.g. "key" instead of "key=value".

### `GuzzleHttp\Psr7\Uri::withQueryValues`

`public static function withQueryValues(UriInterface $uri, array $keyValueArray): UriInterface`

Creates a new URI with multiple query string values. It has the same behavior as
`withQueryValue()` but for an associative array of key => value.

### `GuzzleHttp\Psr7\Uri::withoutQueryValue`

`public static function withoutQueryValue(UriInterface $uri, string $key): UriInterface`

Creates a new URI with a specific query string value removed. Any existing query
string values that exactly match the provided key are removed.

## Cross-Origin Detection

`GuzzleHttp\Psr7\UriComparator` provides methods to determine if a modified URI
should be considered cross-origin.

### `GuzzleHttp\Psr7\UriComparator::isCrossOrigin`

`public static function isCrossOrigin(UriInterface $original, UriInterface $modified): bool`

Determines if a modified URI should be considered cross-origin with respect to
an original URI.

Two URIs are cross-origin when their scheme, host, or effective port differ.
Host comparison is case-insensitive, and bracketed IPv6 literals are
canonicalized to their RFC 5952 form from any PSR-7 implementation before
comparison, so equivalent spellings of the same address are same-origin.
IPvFuture literals and bracketed values that cannot be parsed as an IPv6
address, such as those carrying zone identifiers, still compare as
case-insensitive text. Missing ports use the default port for `http`, `https`,
`ws`, or `wss`. Other schemes do not receive implicit default ports.

This helper only compares URI origins. It does not implement redirect handling
or credential policy.

## Reference Resolution

`GuzzleHttp\Psr7\UriResolver` provides methods to resolve a URI reference in the
context of a base URI according to
[RFC 3986 Section 5](https://datatracker.ietf.org/doc/html/rfc3986#section-5).
This is also what web browsers do when resolving a link in a document based on
the current request URI.

### `GuzzleHttp\Psr7\UriResolver::resolve`

`public static function resolve(UriInterface $base, UriInterface $rel): UriInterface`

Converts the relative URI into a new URI that is resolved against the base URI.

When the resolved path is a relative-path reference whose first segment contains
a colon, which would be mistaken for a scheme name (RFC 3986 Section 4.2), it is
prefixed with `./`, e.g. `./a:b`.

### `GuzzleHttp\Psr7\UriResolver::removeDotSegments`

`public static function removeDotSegments(string $path): string`

Removes dot segments from a path and returns the new path according to
[RFC 3986 Section 5.2.4](https://datatracker.ietf.org/doc/html/rfc3986#section-5.2.4).

Excess `..` segments above the root of an absolute path are dropped without
consuming the root, so the result can begin with `//` (e.g. `/..//a` becomes
`//a`). Such a path is not valid for a URI without an authority (RFC 3986
Section 3.3); `resolve()` and `UriNormalizer::normalize()` serialize it with a
`/.` prefix in that case, like the WHATWG URL Standard.

### `GuzzleHttp\Psr7\UriResolver::relativize`

`public static function relativize(UriInterface $base, UriInterface $target): UriInterface`

Returns the target URI as a relative reference from the base URI. This method is
the counterpart to `resolve()`:

```php
(string) $target === (string) UriResolver::resolve($base, UriResolver::relativize($base, $target))
```

One use case is to use the current request URI as the base URI and then generate
relative links in your documents to reduce the document size or offer
self-contained downloadable document archives.

```php
$base = new Uri('http://example.com/a/b/');
echo UriResolver::relativize($base, new Uri('http://example.com/a/b/c'));  // prints 'c'.
echo UriResolver::relativize($base, new Uri('http://example.com/a/x/y'));  // prints '../x/y'.
echo UriResolver::relativize($base, new Uri('http://example.com/a/b/?q')); // prints '?q'.
echo UriResolver::relativize($base, new Uri('http://example.org/a/b/'));   // prints '//example.org/a/b/'.
echo UriResolver::relativize($base, new Uri('http://example.com'));         // prints '//example.com'.
```

This method also accepts a target that is already relative and will try to
relativize it further. Only a relative-path reference will be returned as-is.

```php
echo UriResolver::relativize($base, new Uri('/a/b/c'));  // prints 'c' as well
```

## Normalization and Comparison

`GuzzleHttp\Psr7\UriNormalizer` provides methods to normalize and compare URIs
according to
[RFC 3986 Section 6](https://datatracker.ietf.org/doc/html/rfc3986#section-6).

### `GuzzleHttp\Psr7\UriNormalizer::normalize`

`public static function normalize(UriInterface $uri, int $flags = self::PRESERVING_NORMALIZATIONS): UriInterface`

Returns a normalized URI. The scheme and host component are already normalized
to lowercase per PSR-7 UriInterface. This method adds additional normalizations
that can be configured with the `$flags` parameter, which is a bitmask of
normalizations to apply.

PSR-7 UriInterface cannot distinguish between an empty component and a missing
component as `getQuery()`, `getFragment()` etc. always return a string. This
means the URIs `/?#` and `/` are treated equivalent which is not necessarily
true according to RFC 3986. But that difference is highly uncommon in reality.
So this potential normalization is implied in PSR-7 as well.

A path the URI cannot hold, such as a `//`-leading path without an authority or
a relative-path reference whose first segment contains a colon, is prefixed with
`/.` or `./` respectively instead of throwing, as `UriResolver::resolve()` does.
The percent-encoding normalizations only do so where they rewrote the path. For
example, decoding `a%41:` yields `./aA:`, since `aA:` would be an absolute URI
with the scheme `aa`.

The following normalizations are available:

- `UriNormalizer::PRESERVING_NORMALIZATIONS`

    Default normalizations which only include the ones that preserve semantics.

- `UriNormalizer::CAPITALIZE_PERCENT_ENCODING`

    All letters within a percent-encoding triplet (e.g., "%3A") are
    case-insensitive, and should be capitalized. This applies to the userinfo,
    host, path, query, and fragment components. Bracketed IP-literal hosts are
    skipped as a legacy tolerance for nonstandard values other implementations
    may carry; zone-identifier text was briefly valid URI syntax under RFC 6874,
    which RFC 9844 obsoleted and reverted. The userinfo and host are only
    rewritten when the value returned by the implementation matches the
    normalized form, and a userinfo with an empty user segment is never
    rewritten. No percent-encoding normalization is applied to a component that
    contains malformed percent syntax, such as a `%` not followed by two
    hexadecimal digits.

    Example: `http://example.org/a%c2%b1b` → `http://example.org/a%C2%B1b`

- `UriNormalizer::DECODE_UNRESERVED_CHARACTERS`

    Decodes percent-encoded octets of unreserved characters. For consistency,
    percent-encoded octets in the ranges of ALPHA (%41–%5A and %61–%7A), DIGIT
    (%30–%39), hyphen (%2D), period (%2E), underscore (%5F), or tilde (%7E)
    should not be created by URI producers and, when found in a URI, should be
    decoded to their corresponding unreserved characters by URI normalizers.
    This applies to the userinfo, host, path, query, and fragment components.
    Since the host is case-insensitive and PSR-7 requires it to be lowercase,
    octets decoded in the host are lowercased (e.g., "%41" becomes "a").
    Bracketed IP-literal hosts are skipped as a legacy tolerance for nonstandard
    values other implementations may carry; zone-identifier text was briefly
    valid URI syntax under RFC 6874, which RFC 9844 obsoleted and reverted. The
    userinfo and host are only rewritten when the value returned by the
    implementation matches the normalized form, and a userinfo with an empty
    user segment is never rewritten. No percent-encoding normalization is
    applied to a component that contains malformed percent syntax, such as a `%`
    not followed by two hexadecimal digits.

    Example: `http://example.org/%7Eusern%61me/` → `http://example.org/~username/`

- `UriNormalizer::CONVERT_EMPTY_PATH`

    Converts the empty path to "/" for http and https URIs.

    Example: `http://example.org` → `http://example.org/`

- `UriNormalizer::REMOVE_DEFAULT_HOST`

    Removes the default host of the given URI scheme from the URI. Only the
    "file" scheme defines the default host "localhost". All of `file:/myfile`,
    `file:///myfile`, and `file://localhost/myfile` are equivalent according to
    RFC 3986.

    When removing the host leaves a URI without an authority whose path begins
    with `//`, the path is serialized with a `/.` prefix.

    Example: `file://localhost/myfile` → `file:///myfile`,
    `file://localhost//x` → `file:///.//x`

- `UriNormalizer::REMOVE_DEFAULT_PORT`

    Removes the default port of the given URI scheme from the URI.

    Example: `http://example.org:80/` → `http://example.org/`

- `UriNormalizer::REMOVE_DOT_SEGMENTS`

    Removes unnecessary dot-segments. Dot-segments in relative-path references
    are not removed as it would change the semantics of the URI reference.

    Example: `http://example.org/../a/b/../c/./d.html` → `http://example.org/a/c/d.html`

- `UriNormalizer::REMOVE_DUPLICATE_SLASHES`

    Paths which include two or more adjacent slashes are converted to one.
    Webservers usually ignore duplicate slashes and treat those URIs equivalent.
    But in theory those URIs do not need to be equivalent. So this normalization
    may change the semantics. Encoded slashes (%2F) are not removed.

    Example: `http://example.org//foo///bar.html` → `http://example.org/foo/bar.html`

- `UriNormalizer::SORT_QUERY_PARAMETERS`

    Sort query parameters with their values in alphabetical order. However, the
    order of parameters in a URI may be significant (this is not defined by the
    standard). So this normalization is not safe and may change the semantics of
    the URI.

    Example: `?lang=en&article=fred` → `?article=fred&lang=en`

- `UriNormalizer::CANONICALIZE_IPV6_HOST`

    Canonicalizes IPv6 hosts to their RFC 5952 form. IPv6 addresses allow
    leading zeros and multiple placements of the `::` elision, so the same
    address has many textual spellings. The canonical form is required for
    IPv6 literals in URIs by RFC 5952 Section 6 and never changes what the URI
    refers to. Native `Uri` instances already guarantee canonical output; for
    other implementations, the canonical host is requested through
    `withHost()` and the result is kept only when the returned `getHost()`
    exactly matches the requested spelling, otherwise this step leaves the URI
    unchanged while other selected normalizations still apply, and setter
    exceptions propagate.

    Example: `http://[::0:0a]/` → `http://[::a]/`

### `GuzzleHttp\Psr7\UriNormalizer::isEquivalent`

`public static function isEquivalent(UriInterface $uri1, UriInterface $uri2, int $normalizations = self::PRESERVING_NORMALIZATIONS): bool`

Whether two URIs can be considered equivalent. Both URIs are normalized
automatically before comparison with the given `$normalizations` bitmask. The
method also accepts relative URI references and returns true when they are
equivalent. This of course assumes they will be resolved against the same base
URI. If this is not the case, determination of equivalence or difference of
relative references does not mean anything.

## Related

- [PSR-7 Messages](psr-7-messages.md)
- [Streams and Decorators](streams-and-decorators.md)
- [URI and MIME Helpers](uri-and-mime-helpers.md)
- [Header and Query Helpers](header-and-query-helpers.md)
