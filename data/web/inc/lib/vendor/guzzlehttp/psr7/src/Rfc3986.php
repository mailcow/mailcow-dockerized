<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * Syntax predicates and canonicalization helpers for the URI grammar defined
 * by RFC 3986.
 */
final class Rfc3986
{
    private function __construct()
    {
    }

    /**
     * Sub-delims for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.2
     *
     * @internal
     */
    public const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    /**
     * Unreserved characters for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.3
     *
     * @internal
     */
    public const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    /**
     * The two hex digits of a percent-encoded octet (the "3A" in "%3A"), for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.1
     *
     * @internal
     */
    public const HEX_OCTET = '[A-Fa-f0-9]{2}';

    /**
     * Whether the string is a valid URI scheme.
     *
     * Per RFC 3986 a scheme must start with a letter, followed by letters,
     * digits, `+`, `-`, or `.`. The empty string is also accepted, since a URI
     * reference may omit the scheme.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.1
     */
    public static function isValidScheme(string $scheme): bool
    {
        return $scheme === '' || preg_match('/^[A-Za-z][A-Za-z0-9.+-]*$/D', $scheme) === 1;
    }

    /**
     * Whether the string is a valid URI host.
     *
     * Per RFC 3986 the host is `IP-literal / IPv4address / reg-name`. An empty
     * host is accepted, since the authority (and thus the host) may be empty.
     * Bracketed values are validated as IPv6 / IPvFuture literals; any other
     * value is rejected if it contains control characters, whitespace, an
     * authority or path delimiter (`/ ? # @ \`), an embedded colon denoting
     * a port, a malformed percent-sequence, or a percent-encoded octet that
     * decodes to one of those rejected bytes, to a bracket, or to `%` itself.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.2.2
     */
    public static function isValidHost(string $host): bool
    {
        if ($host === '') {
            return true;
        }

        $invalidHost = preg_match('/[\x00-\x20\x7F\/\?#@\\\\]/', $host);

        if ($invalidHost === false) {
            return false;
        }

        if ($invalidHost === 1) {
            return false;
        }

        if (str_contains($host, '[') || str_contains($host, ']')) {
            return self::isValidIpLiteralHost($host);
        }

        if (str_contains($host, ':')) {
            return false;
        }

        return !str_contains($host, '%') || self::hasValidHostPercentEncoding($host);
    }

    /**
     * Whether the string is a valid port number (0-65535).
     *
     * RFC 3986 defines the port as `*DIGIT`, which also permits an empty port
     * and has no upper bound. This applies the stricter policy used throughout
     * the library instead: the value must be a non-empty run of digits (leading
     * zeros are accepted and normalized) that resolves to 0-65535.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.2.3
     */
    public static function isValidPort(string $port): bool
    {
        if ($port === '' || !ctype_digit($port)) {
            return false;
        }

        $normalized = ltrim($port, '0');
        if ($normalized === '') {
            return true;
        }

        return strlen($normalized) <= 5 && (int) $normalized <= 0xFFFF;
    }

    /**
     * Returns the RFC 5952 canonical form of a valid IPv6 address.
     *
     * The address must be a valid textual IPv6 address without brackets and
     * without a zone identifier, such as the inside of an IP-literal accepted
     * by `isValidHost()`. Canonicalization lowercases the hexadecimal fields,
     * suppresses leading zeros, and collapses the longest run of two or more
     * zero fields (the leftmost on a tie) with `::`. Embedded dotted-decimal
     * notation follows the rendering policy of BIND-derived `inet_ntop()`
     * implementations and curl: exactly the IPv4-mapped (`::ffff:0:0/96`) and
     * deprecated IPv4-compatible (`::/96`) layouts use it, while other
     * embedded-IPv4 forms, including translated (NAT64) well-known prefixes
     * such as `64:ff9b::/96` (RFC 6052), serialize in pure hexadecimal fields.
     *
     * Validation is strict and platform-independent: the address is checked
     * against the RFC 3986 `IPv6address` grammar with PHP's
     * `FILTER_VALIDATE_IP` filter and parsed in pure PHP, so spellings that
     * only some platform parsers accept, such as the zero-padded dotted octets
     * in `::ffff:192.168.001.001`, are rejected everywhere.
     *
     * @throws \InvalidArgumentException If the address cannot be parsed.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc5952#section-4
     */
    public static function canonicalizeIpv6(string $address): string
    {
        $canonical = self::tryCanonicalizeIpv6($address);
        if ($canonical === null) {
            throw new \InvalidArgumentException('Invalid IPv6 address');
        }

        return $canonical;
    }

    /**
     * Returns the RFC 5952 canonical form of a valid IPv6 address, or null
     * when the address cannot be parsed.
     *
     * @internal
     */
    public static function tryCanonicalizeIpv6(string $address): ?string
    {
        // Platform parsers disagree on which spellings are valid: Apple libc
        // and OpenBSD inet_pton() accept zero-padded dotted octets such as
        // "::ffff:192.168.001.001", and macOS additionally accepts and silently
        // strips zone IDs ("fe80::1%eth0"), while glibc, musl, and PHP's own
        // filter reject both. Origin classification built on this helper must
        // fail closed and must not vary by operating system, so the address is
        // validated with the platform-independent FILTER_VALIDATE_IP filter and
        // parsed in pure PHP; no OS parser is consulted.
        if (\filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) === false) {
            return null;
        }

        $words = self::parseIpv6Words($address);
        if ($words === null) {
            return null;
        }

        // Find the longest run of two or more zero fields; ties keep the
        // leftmost run per RFC 5952 section 4.2.3.
        $bestStart = 0;
        $bestLen = 0;
        $start = -1;
        foreach ($words as $i => $word) {
            if ($word !== 0) {
                $start = -1;
                continue;
            }
            if ($start === -1) {
                $start = $i;
            }
            if ($i - $start + 1 > $bestLen) {
                $bestStart = $start;
                $bestLen = $i - $start + 1;
            }
        }
        if ($bestLen < 2) {
            $bestLen = 0;
        }

        // RFC 5952 section 5: embedded IPv4 notation for IPv4-mapped
        // (::ffff:0:0/96) and IPv4-compatible (::/96) addresses, the same
        // condition BIND-derived inet_ntop() and curl use. bestStart must be
        // zero: a five or six field zero run elsewhere is not an IPv4 prefix.
        $mixed = $bestStart === 0
            && ($bestLen === 6 || ($bestLen === 5 && $words[5] === 0xFFFF));

        $groups = [];
        for ($i = 0, $n = $mixed ? 6 : 8; $i < $n; ++$i) {
            $groups[] = dechex($words[$i]);
        }
        if ($mixed) {
            $groups[] = sprintf(
                '%d.%d.%d.%d',
                $words[6] >> 8,
                $words[6] & 0xFF,
                $words[7] >> 8,
                $words[7] & 0xFF
            );
        }

        if ($bestLen === 0) {
            return implode(':', $groups);
        }

        return implode(':', array_slice($groups, 0, $bestStart)).'::'.implode(':', array_slice($groups, $bestStart + $bestLen));
    }

    private static function hasValidHostPercentEncoding(string $host): bool
    {
        // Mirror of the raw reg-name policy above for percent-encoded octets:
        // reject malformed sequences (RFC 3986 requires "%" HEXDIG HEXDIG) and
        // octets that decode to bytes the raw grammar rejects - C0 controls,
        // SP, DEL, the delimiters / ? # @ \ [ ], the port colon, and % itself.
        // Octets decoding to any other byte (unreserved, sub-delims, and
        // non-ASCII UTF-8 data) remain accepted.
        $invalidEncoding = preg_match(
            '/%(?!'.self::HEX_OCTET.')|%(?:[01][0-9A-Fa-f]|2[035F]|3[AF]|40|5[BCD]|7F)/i',
            $host
        );

        return $invalidEncoding === 0;
    }

    private static function isValidIpLiteralHost(string $host): bool
    {
        if (!str_starts_with($host, '[') || !str_ends_with($host, ']')) {
            return false;
        }

        $address = substr($host, 1, -1);
        if (\filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) !== false) {
            return true;
        }

        // RFC 6874 IPv6 zone identifiers are intentionally not supported here.
        // Bracketed hosts are validated as IPv6 or IPvFuture only.
        return preg_match('/^v[0-9a-f]+\.['.self::CHAR_UNRESERVED.self::CHAR_SUB_DELIMS.':]+$/iD', $address) === 1;
    }

    /**
     * Parses a textual IPv6 address into its eight 16-bit words, or null
     * when the text is not a structurally valid address.
     *
     * The grammar enforced here is the RFC 3986 `IPv6address` rule: one to four
     * hexadecimal digits per field, at most one `::` eliding one or more zero
     * fields, and an optional dotted-decimal tail of four octets (0-255, no
     * leading zeros) as the final 32 bits. FILTER_VALIDATE_IP accepts exactly
     * this grammar, so the filter guard in tryCanonicalizeIpv6() and this
     * parser always agree and the null paths here can only fail closed.
     *
     * @return list<int>|null
     */
    private static function parseIpv6Words(string $address): ?array
    {
        // A dotted-decimal tail is only valid as the final 32 bits, after the
        // final colon. Rewrite it into its two hexadecimal fields so the
        // remainder of the parse handles hexadecimal fields only.
        $dot = strpos($address, '.');
        if ($dot !== false) {
            $colon = strrpos($address, ':');
            if ($colon === false || $colon > $dot) {
                return null;
            }
            $octets = explode('.', substr($address, $colon + 1));
            if (count($octets) !== 4) {
                return null;
            }
            $bytes = [];
            foreach ($octets as $octet) {
                if ($octet === '' || strlen($octet) > 3 || !ctype_digit($octet)) {
                    return null;
                }
                if ($octet[0] === '0' && $octet !== '0') {
                    return null;
                }
                $byte = (int) $octet;
                if ($byte > 255) {
                    return null;
                }
                $bytes[] = $byte;
            }
            $address = substr($address, 0, $colon + 1)
                .dechex(($bytes[0] << 8) | $bytes[1])
                .':'
                .dechex(($bytes[2] << 8) | $bytes[3]);
        }

        $halves = explode('::', $address);
        if (count($halves) > 2) {
            return null;
        }

        $head = self::parseHexFields($halves[0]);
        if ($head === null) {
            return null;
        }

        if (count($halves) === 1) {
            return count($head) === 8 ? $head : null;
        }

        $tail = self::parseHexFields($halves[1]);
        if ($tail === null) {
            return null;
        }

        // The "::" must elide at least one zero field.
        $elided = 8 - count($head) - count($tail);
        if ($elided < 1) {
            return null;
        }

        return array_merge($head, array_fill(0, $elided, 0), $tail);
    }

    /**
     * Parses a colon-separated run of 16-bit hexadecimal fields, or null
     * when a field is empty, longer than four digits, or not hexadecimal.
     *
     * @return list<int>|null
     */
    private static function parseHexFields(string $fields): ?array
    {
        if ($fields === '') {
            return [];
        }

        $words = [];
        foreach (explode(':', $fields) as $field) {
            if ($field === '' || strlen($field) > 4 || !ctype_xdigit($field)) {
                return null;
            }
            $words[] = intval($field, 16);
        }

        return $words;
    }
}
