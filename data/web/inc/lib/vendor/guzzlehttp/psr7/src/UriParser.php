<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * @internal
 */
final class UriParser
{
    private function __construct()
    {
    }

    /**
     * UTF-8 aware \parse_url() replacement.
     *
     * The internal function produces broken output for non ASCII domain names
     * (IDN) when used with locales other than "C".
     *
     * On the other hand, cURL understands IDN correctly only when UTF-8 locale
     * is configured ("C.UTF-8", "en_US.UTF-8", etc.).
     *
     * @see https://bugs.php.net/bug.php?id=52923
     * @see https://www.php.net/manual/en/function.parse-url.php#114817
     * @see https://curl.se/libcurl/c/CURLOPT_URL.html#ENCODING
     *
     * @return array|false
     */
    public static function parse(string $url)
    {
        if (self::isPathNoSchemeReference($url)) {
            return self::parsePathNoSchemeReference($url);
        }

        // Preserve bracketed IP-literals (IPv6 or IPvFuture) in scheme, userinfo,
        // and network-path authorities before encoding. Userinfo is encoded
        // separately so raw bytes cannot reach parse_url(), which mutates
        // control characters instead of failing.
        $prefix = '';
        $ipv6Prefix = preg_match('%\A((?:[0-9A-Za-z+.-]+:)?//)(?:([^/?#@]*)(@))?(\[[^\]\x00-\x20\x7F/?#@]+\])(.*)\z%s', $url, $matches);

        if ($ipv6Prefix === false) {
            return false;
        }

        if ($ipv6Prefix === 1) {
            /** @var array{0:string, 1:string, 2:string, 3:string, 4:string, 5:string} $matches */
            $suffix = $matches[5];

            // After the bracketed host only an optional numeric port and/or a
            // path, query, or fragment may follow. Anything else (for example
            // `:80@evil` or `:80x`) would let parse_url() reinterpret a
            // different host.
            if (preg_match('%\A(?::[0-9]*)?(?:[/?#].*)?\z%s', $suffix) !== 1) {
                return false;
            }

            // RFC 3986 IP-literals contain no percent-encoding, so reject any
            // "%" in the bracketed host rather than letting the urldecode()
            // below turn an encoded octet into a different literal. This keeps
            // parsing aligned with withHost()/Rfc3986::isValidHost().
            if (str_contains($matches[4], '%')) {
                return false;
            }

            $prefix = $matches[1];

            if ($matches[3] === '@') {
                /** @var string|null */
                $encodedUserInfo = preg_replace_callback(
                    '%[^:/@?&=#]+%usD',
                    static function (array $matches): string {
                        return urlencode($matches[0]);
                    },
                    $matches[2]
                );

                if ($encodedUserInfo === null) {
                    return false;
                }

                $prefix .= $encodedUserInfo.'@';
            }

            $prefix .= $matches[4];
            $url = $suffix;
        }

        /** @var string|null */
        $encodedUrl = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            static function (array $matches): string {
                return urlencode($matches[0]);
            },
            $url
        );

        if ($encodedUrl === null) {
            return false;
        }

        $result = parse_url($prefix.$encodedUrl);

        if ($result === false) {
            return false;
        }

        return array_map('urldecode', $result);
    }

    private static function isPathNoSchemeReference(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '?') || str_starts_with($url, '#')) {
            return false;
        }

        $firstSegment = substr($url, 0, strcspn($url, '/?#'));

        return !str_contains($firstSegment, ':');
    }

    /**
     * @return array{path: string, query?: string, fragment?: string}
     */
    private static function parsePathNoSchemeReference(string $url): array
    {
        $parts = [];

        if (false !== ($fragmentPosition = strpos($url, '#'))) {
            $parts['fragment'] = substr($url, $fragmentPosition + 1);
            $url = substr($url, 0, $fragmentPosition);
        }

        if (false !== ($queryPosition = strpos($url, '?'))) {
            $parts['query'] = substr($url, $queryPosition + 1);
            $url = substr($url, 0, $queryPosition);
        }

        $parts['path'] = $url;

        return $parts;
    }
}
