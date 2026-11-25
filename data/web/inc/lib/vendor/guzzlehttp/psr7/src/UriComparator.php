<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\UriInterface;

/**
 * Provides methods to determine if a modified URI should be considered
 * cross-origin.
 *
 * @author Graham Campbell
 */
final class UriComparator
{
    /**
     * Determines if a modified URI should be considered cross-origin with
     * respect to an original URI.
     *
     * Two URIs are cross-origin when their scheme, host, or effective port
     * differ. Host comparison is case-insensitive, and bracketed IPv6 literals
     * are canonicalized to their RFC 5952 form from any PSR-7 implementation
     * before comparison, so equivalent spellings of the same address are
     * same-origin. IPvFuture literals and bracketed values that cannot be
     * parsed as an IPv6 address, such as those carrying zone identifiers,
     * still compare as case-insensitive text. Missing ports use the default
     * port for `http`, `https`, `ws`, or `wss`. Other schemes do not receive
     * implicit default ports.
     *
     * This helper only compares URI origins. It does not implement redirect
     * handling or credential policy.
     */
    public static function isCrossOrigin(UriInterface $original, UriInterface $modified): bool
    {
        if (!Utils::caselessEquals(self::normalizeHost($original), self::normalizeHost($modified))) {
            return true;
        }

        if ($original->getScheme() !== $modified->getScheme()) {
            return true;
        }

        if (self::computePort($original) !== self::computePort($modified)) {
            return true;
        }

        return false;
    }

    private static function normalizeHost(UriInterface $uri): string
    {
        $host = $uri->getHost();
        if (!str_starts_with($host, '[') || !str_ends_with($host, ']')) {
            return $host;
        }

        // Foreign UriInterface implementations may carry non-canonical IPv6
        // spellings; canonicalize what is unambiguously an IPv6 address so
        // equivalent literals compare as same-origin, and leave IPvFuture,
        // zone-identifier, and invalid text to the caseless textual
        // comparison. Validation is platform-independent, so a spelling only
        // some OS parsers accept, such as zero-padded dotted octets, is
        // cross-origin everywhere instead of same-origin on some systems.
        $canonical = Rfc3986::tryCanonicalizeIpv6(substr($host, 1, -1));
        if ($canonical === null) {
            return $host;
        }

        return '['.$canonical.']';
    }

    private static function computePort(UriInterface $uri): ?int
    {
        $port = $uri->getPort();

        if (null !== $port) {
            return $port;
        }

        if (\in_array($uri->getScheme(), ['http', 'ws'], true)) {
            return 80;
        }

        if (\in_array($uri->getScheme(), ['https', 'wss'], true)) {
            return 443;
        }

        return null;
    }

    private function __construct()
    {
        // cannot be instantiated
    }
}
