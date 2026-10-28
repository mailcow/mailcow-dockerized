<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\UriInterface;

/**
 * Provides methods to normalize and compare URIs.
 *
 * @author Tobias Schultze
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-6
 */
final class UriNormalizer
{
    /**
     * Default normalizations which only include the ones that preserve
     * semantics.
     */
    public const PRESERVING_NORMALIZATIONS =
        self::CAPITALIZE_PERCENT_ENCODING |
        self::DECODE_UNRESERVED_CHARACTERS |
        self::CONVERT_EMPTY_PATH |
        self::REMOVE_DEFAULT_HOST |
        self::REMOVE_DEFAULT_PORT |
        self::REMOVE_DOT_SEGMENTS |
        self::CANONICALIZE_IPV6_HOST;

    /**
     * All letters within a percent-encoding triplet (e.g., "%3A") are
     * case-insensitive, and should be capitalized. This applies to the
     * userinfo, host, path, query, and fragment components. Bracketed
     * IP-literal hosts are skipped as a legacy tolerance for nonstandard values
     * other implementations may carry; zone-identifier text was briefly valid
     * URI syntax under RFC 6874, which RFC 9844 obsoleted and reverted. The
     * userinfo and host are only rewritten when the value returned by the
     * implementation matches the normalized form, and a userinfo with an empty
     * user segment is never rewritten. No percent-encoding normalization is
     * applied to a component that contains malformed percent syntax, such as a
     * `%` not followed by two hexadecimal digits.
     *
     * Example: http://example.org/a%c2%b1b → http://example.org/a%C2%B1b
     */
    public const CAPITALIZE_PERCENT_ENCODING = 1;

    /**
     * Decodes percent-encoded octets of unreserved characters.
     *
     * For consistency, percent-encoded octets in the ranges of ALPHA (%41–%5A
     * and %61–%7A), DIGIT (%30–%39), hyphen (%2D), period (%2E), underscore
     * (%5F), or tilde (%7E) should not be created by URI producers and, when
     * found in a URI, should be decoded to their corresponding unreserved
     * characters by URI normalizers. This applies to the userinfo, host, path,
     * query, and fragment components. Since the host is case-insensitive and
     * PSR-7 requires it to be lowercase, octets decoded in the host are
     * lowercased (e.g., "%41" becomes "a"). Bracketed IP-literal hosts are
     * skipped as a legacy tolerance for nonstandard values other
     * implementations may carry; zone-identifier text was briefly valid URI
     * syntax under RFC 6874, which RFC 9844 obsoleted and reverted. The
     * userinfo and host are only rewritten when the value returned by the
     * implementation matches the normalized form, and a userinfo with an empty
     * user segment is never rewritten. No percent-encoding normalization is
     * applied to a component that contains malformed percent syntax, such as a
     * `%` not followed by two hexadecimal digits.
     *
     * Example: http://example.org/%7Eusern%61me/ → http://example.org/~username/
     */
    public const DECODE_UNRESERVED_CHARACTERS = 2;

    /**
     * Converts the empty path to "/" for http and https URIs.
     *
     * Example: http://example.org → http://example.org/
     */
    public const CONVERT_EMPTY_PATH = 4;

    /**
     * Removes the default host of the given URI scheme from the URI.
     *
     * Only the "file" scheme defines the default host "localhost". All of
     * `file:/myfile`, `file:///myfile`, and `file://localhost/myfile` are
     * equivalent according to RFC 3986. The first format is not accepted by
     * PHPs stream functions and thus already normalized implicitly to the
     * second format in the Uri class. See
     * `GuzzleHttp\Psr7\Uri::composeComponents`.
     *
     * When removing the host leaves a URI without an authority whose path
     * begins with `//`, the path is serialized with a `/.` prefix.
     *
     * Example: file://localhost/myfile → file:///myfile
     * Example: file://localhost//x → file:///.//x
     */
    public const REMOVE_DEFAULT_HOST = 8;

    /**
     * Removes the default port of the given URI scheme from the URI.
     *
     * Example: http://example.org:80/ → http://example.org/
     */
    public const REMOVE_DEFAULT_PORT = 16;

    /**
     * Removes unnecessary dot-segments.
     *
     * Dot-segments in relative-path references are not removed as it would
     * change the semantics of the URI reference.
     *
     * Example: http://example.org/../a/b/../c/./d.html → http://example.org/a/c/d.html
     */
    public const REMOVE_DOT_SEGMENTS = 32;

    /**
     * Paths which include two or more adjacent slashes are converted to one.
     *
     * Webservers usually ignore duplicate slashes and treat those URIs
     * equivalent. But in theory those URIs do not need to be equivalent. So
     * this normalization may change the semantics. Encoded slashes (%2F) are
     * not removed.
     *
     * Example: http://example.org//foo///bar.html → http://example.org/foo/bar.html
     */
    public const REMOVE_DUPLICATE_SLASHES = 64;

    /**
     * Sort query parameters with their values in alphabetical order.
     *
     * However, the order of parameters in a URI may be significant (this is not
     * defined by the standard). So this normalization is not safe and may
     * change the semantics of the URI.
     *
     * Example: ?lang=en&article=fred → ?article=fred&lang=en
     *
     * Note: The sorting is neither locale nor Unicode aware (the URI query does
     * not get decoded at all) as the purpose is to be able to compare URIs in a
     * reproducible way, not to have the params sorted perfectly.
     */
    public const SORT_QUERY_PARAMETERS = 128;

    /**
     * Canonicalizes IPv6 hosts to their RFC 5952 form.
     *
     * IPv6 addresses allow leading zeros and multiple placements of the `::`
     * elision, so the same address has many textual spellings. The canonical
     * form is required for IPv6 literals in URIs by RFC 5952 Section 6 and
     * never changes what the URI refers to. Native `Uri` instances already
     * guarantee canonical output; for other implementations, the canonical
     * host is requested through `withHost()` and the result is kept only when
     * the returned `getHost()` exactly matches the requested spelling,
     * otherwise this step leaves the URI unchanged while other selected
     * normalizations still apply, and setter exceptions propagate.
     *
     * Example: http://[::0:0a]/ → http://[::a]/
     */
    public const CANONICALIZE_IPV6_HOST = 256;

    /**
     * Returns a normalized URI.
     *
     * The scheme and host component are already normalized to lowercase per
     * PSR-7 UriInterface. This method adds additional normalizations that can
     * be configured with the `$flags` parameter, which is a bitmask of
     * normalizations to apply.
     *
     * PSR-7 UriInterface cannot distinguish between an empty component and a
     * missing component as `getQuery()`, `getFragment()` etc. always return a
     * string. This means the URIs `/?#` and `/` are treated equivalent which is
     * not necessarily true according to RFC 3986. But that difference is highly
     * uncommon in reality. So this potential normalization is implied in PSR-7
     * as well.
     *
     * A path the URI cannot hold, such as a `//`-leading path without an
     * authority or a relative-path reference whose first segment contains a
     * colon, is prefixed with `/.` or `./` respectively instead of throwing, as
     * `UriResolver::resolve()` does. The percent-encoding normalizations only
     * do so where they rewrote the path. For example, decoding `a%41:` yields
     * `./aA:`, since `aA:` would be an absolute URI with the scheme `aa`.
     *
     * @param UriInterface $uri   The URI to normalize
     * @param int          $flags A bitmask of normalizations to apply, see constants
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-6.2
     */
    public static function normalize(UriInterface $uri, int $flags = self::PRESERVING_NORMALIZATIONS): UriInterface
    {
        if ($flags & self::CAPITALIZE_PERCENT_ENCODING) {
            $uri = self::capitalizePercentEncoding($uri);
        }

        if ($flags & self::DECODE_UNRESERVED_CHARACTERS) {
            $uri = self::decodeUnreservedCharacters($uri);
        }

        if ($flags & self::CONVERT_EMPTY_PATH && $uri->getPath() === ''
            && ($uri->getScheme() === 'http' || $uri->getScheme() === 'https')
        ) {
            $uri = $uri->withPath('/');
        }

        if ($flags & self::REMOVE_DEFAULT_HOST && $uri->getScheme() === 'file' && $uri->getHost() === 'localhost') {
            if ($uri->getUserInfo() === '' && $uri->getPort() === null) {
                $path = Uri::rawPath($uri);
                if (str_starts_with($path, '//')) {
                    // "/." keeps a "//" path unambiguous once the authority is gone
                    $uri = $uri->withPath('/.'.$path);
                }
            }

            $uri = $uri->withHost('');
        }

        if ($flags & self::REMOVE_DEFAULT_PORT && $uri->getPort() !== null && Uri::isDefaultPort($uri)) {
            $uri = $uri->withPort(null);
        }

        $removeDotSegments = ($flags & self::REMOVE_DOT_SEGMENTS) && !Uri::isRelativePathReference($uri);

        if ($removeDotSegments || $flags & self::REMOVE_DUPLICATE_SLASHES) {
            $path = Uri::rawPath($uri);

            if ($removeDotSegments) {
                $path = UriResolver::removeDotSegments($path);
            }

            if ($flags & self::REMOVE_DUPLICATE_SLASHES) {
                $path = preg_replace('#//++#', '/', $path);

                if ($path === null) {
                    throw new \RuntimeException('Unable to remove duplicate slashes from URI path: '.preg_last_error_msg());
                }
            }

            $uri = $uri->withPath(UriResolver::guardedPath($uri, $path));
        }

        if ($flags & self::SORT_QUERY_PARAMETERS && $uri->getQuery() !== '') {
            $queryKeyValues = explode('&', $uri->getQuery());
            sort($queryKeyValues);
            $uri = $uri->withQuery(implode('&', $queryKeyValues));
        }

        if ($flags & self::CANONICALIZE_IPV6_HOST) {
            $uri = self::canonicalizeIpv6Host($uri);
        }

        return $uri;
    }

    /**
     * Whether two URIs can be considered equivalent.
     *
     * Both URIs are normalized automatically before comparison with the given
     * `$normalizations` bitmask. The method also accepts relative URI
     * references and returns true when they are equivalent. This of course
     * assumes they will be resolved against the same base URI. If this is not
     * the case, determination of equivalence or difference of relative
     * references does not mean anything.
     *
     * @param UriInterface $uri1           An URI to compare
     * @param UriInterface $uri2           An URI to compare
     * @param int          $normalizations A bitmask of normalizations to apply, see constants
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-6.1
     */
    public static function isEquivalent(UriInterface $uri1, UriInterface $uri2, int $normalizations = self::PRESERVING_NORMALIZATIONS): bool
    {
        return (string) self::normalize($uri1, $normalizations) === (string) self::normalize($uri2, $normalizations);
    }

    private static function capitalizePercentEncoding(UriInterface $uri): UriInterface
    {
        $regex = '/(?:%'.Rfc3986::HEX_OCTET.')++/';

        $callback = function (array $match): string {
            return Utils::asciiToUpper($match[0]);
        };

        $uri = self::withNormalizedUserInfo($uri, $regex, $callback);
        $uri = self::withNormalizedHost($uri, $regex, $callback);

        return self::withGuardedPath($uri, self::normalizePercentEncodingInComponent(Uri::rawPath($uri), $regex, $callback))
            ->withQuery(self::normalizePercentEncodingInComponent($uri->getQuery(), $regex, $callback))
            ->withFragment(self::normalizePercentEncodingInComponent($uri->getFragment(), $regex, $callback));
    }

    private static function decodeUnreservedCharacters(UriInterface $uri): UriInterface
    {
        $regex = '/%(?:2D|2E|5F|7E|3[0-9]|[46][1-9A-F]|[57][0-9A])/i';

        $callback = function (array $match): string {
            return rawurldecode($match[0]);
        };

        // The host is case-insensitive and PSR-7 requires it to be lowercase,
        // so decoded ALPHA octets (e.g. "%41") must land lowercase even for
        // implementations whose withHost() does not normalize the case.
        $hostCallback = function (array $match): string {
            return Utils::asciiToLower(rawurldecode($match[0]));
        };

        $uri = self::withNormalizedUserInfo($uri, $regex, $callback);
        $uri = self::withNormalizedHost($uri, $regex, $hostCallback);

        return self::withGuardedPath($uri, self::normalizePercentEncodingInComponent(Uri::rawPath($uri), $regex, $callback))
            ->withQuery(self::normalizePercentEncodingInComponent($uri->getQuery(), $regex, $callback))
            ->withFragment(self::normalizePercentEncodingInComponent($uri->getFragment(), $regex, $callback));
    }

    /**
     * Writes the given path only when it differs from the current one, guarded
     * so the write cannot throw.
     */
    private static function withGuardedPath(UriInterface $uri, string $path): UriInterface
    {
        if ($path === Uri::rawPath($uri)) {
            return $uri;
        }

        return $uri->withPath(UriResolver::guardedPath($uri, $path));
    }

    /**
     * @param callable(array): string $callback
     */
    private static function withNormalizedUserInfo(UriInterface $uri, string $regex, callable $callback): UriInterface
    {
        $userInfo = $uri->getUserInfo();

        if (!str_contains($userInfo, '%')) {
            return $uri;
        }

        $normalized = self::normalizePercentEncodingInComponent($userInfo, $regex, $callback);

        if ($normalized === $userInfo) {
            return $uri;
        }

        // Normalization cannot create a colon: decoding is confined to
        // unreserved characters and capitalization keeps octets encoded. So
        // splitting on the first colon preserves the user/password boundary.
        $parts = explode(':', $normalized, 2);

        // PSR-7 defines withUserInfo('') as removing the userinfo, so a
        // userinfo with an empty user segment (e.g. ":pass") cannot be
        // expressed through the setter and is preserved as-is instead.
        if ($parts[0] === '') {
            return $uri;
        }

        $candidate = $uri->withUserInfo($parts[0], $parts[1] ?? null);

        // Normalization must never lose or corrupt information, so verify the
        // representation the setter returned and leave the component untouched
        // when the implementation cannot represent the normalized form.
        if ($candidate->getUserInfo() !== $normalized) {
            return $uri;
        }

        return $candidate;
    }

    /**
     * @param callable(array): string $callback
     */
    private static function withNormalizedHost(UriInterface $uri, string $regex, callable $callback): UriInterface
    {
        $host = $uri->getHost();

        // Bracketed IP-literal hosts are skipped as a legacy tolerance for
        // nonstandard values other implementations may carry, such as a zone
        // identifier in "[fe80::1%25eth0]"; that text was briefly valid URI
        // syntax under RFC 6874, which RFC 9844 obsoleted and reverted.
        if (str_starts_with($host, '[') || !str_contains($host, '%')) {
            return $uri;
        }

        $normalized = self::normalizePercentEncodingInComponent($host, $regex, $callback);

        if ($normalized === $host) {
            return $uri;
        }

        $candidate = $uri->withHost($normalized);

        // Normalization must never lose or corrupt information, so verify the
        // representation the setter returned and leave the component untouched
        // when the implementation cannot represent the normalized form.
        if ($candidate->getHost() !== $normalized) {
            return $uri;
        }

        return $candidate;
    }

    /**
     * @param callable(array): string $callback
     */
    private static function normalizePercentEncodingInComponent(string $component, string $regex, callable $callback): string
    {
        // Decoding a valid triplet that follows a dangling "%" would complete
        // the malformed sequence into a new valid triplet ("example%6%31com"
        // becomes "example%61com"), turning malformed text valid and breaking
        // idempotence, so a component containing malformed percent syntax is
        // returned unchanged.
        $malformed = preg_match('/%(?!'.Rfc3986::HEX_OCTET.')/', $component);

        if ($malformed === false) {
            throw new \RuntimeException('Unable to scan URI component percent-encoding: '.preg_last_error_msg());
        }

        if ($malformed === 1) {
            return $component;
        }

        $normalized = preg_replace_callback($regex, $callback, $component);

        if ($normalized === null) {
            throw new \RuntimeException('Unable to normalize URI component percent-encoding: '.preg_last_error_msg());
        }

        return $normalized;
    }

    private static function canonicalizeIpv6Host(UriInterface $uri): UriInterface
    {
        $host = $uri->getHost();
        if (!str_starts_with($host, '[') || !str_ends_with($host, ']')) {
            return $uri;
        }

        // Foreign UriInterface implementations may carry IPvFuture literals,
        // IPv6 zone identifiers, uppercase text, or invalid spellings;
        // tryCanonicalizeIpv6() canonicalizes only what is unambiguously an
        // IPv6 address and leaves everything else untouched.
        $canonical = Rfc3986::tryCanonicalizeIpv6(substr($host, 1, -1));
        if ($canonical === null || '['.$canonical.']' === $host) {
            return $uri;
        }

        $candidate = $uri->withHost('['.$canonical.']');
        // Normalization must never corrupt a component, so keep the original
        // host when the implementation does not retain the canonical form.
        if ($candidate->getHost() !== '['.$canonical.']') {
            return $uri;
        }

        return $candidate;
    }

    private function __construct()
    {
        // cannot be instantiated
    }
}
