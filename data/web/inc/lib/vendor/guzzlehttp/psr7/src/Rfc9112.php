<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * @internal
 */
final class Rfc9112
{
    /**
     * An HTTP protocol version for use in a regular expression.
     */
    public const PROTOCOL_VERSION_PATTERN = '\d+(?:\.\d+)?';

    /**
     * The request-target bytes accepted by the HTTP/1 start-line grammar for
     * use in a regular expression.
     */
    public const REQUEST_TARGET_PATTERN = '[^\x00-\x20\x7F]+';

    private function __construct()
    {
    }

    /**
     * Header related regular expressions (based on amphp/http package)
     *
     * Note: header delimiter (\r\n) is modified to \r?\n to accept line feed only delimiters for BC reasons.
     *
     * @see https://github.com/amphp/http/blob/v1.0.1/src/Rfc7230.php#L12-L15
     *
     * @license https://github.com/amphp/http/blob/v1.0.1/LICENSE
     */
    public const HEADER_REGEX = "(^([^()<>@,;:\\\"/[\]?={}\x01-\x20\x7F]++):[ \t]*+((?:[ \t]*+[\x21-\x7E\x80-\xFF]++)*+)[ \t]*+\r?\n)m";
    public const HEADER_FOLD_REGEX = "(\r?\n[ \t]++)";

    public static function isValidProtocolVersion(string $version): bool
    {
        return preg_match('/^'.self::PROTOCOL_VERSION_PATTERN.'$/D', $version) === 1;
    }

    public static function isValidRequestTarget(string $target): bool
    {
        return preg_match('/^'.self::REQUEST_TARGET_PATTERN.'$/D', $target) === 1;
    }

    public static function isValidReasonPhrase(string $reasonPhrase): bool
    {
        return Rfc9110::isFieldValue($reasonPhrase);
    }

    /**
     * @return array{0: string, 1: int|null}|null
     */
    public static function parseHostHeader(string $authority): ?array
    {
        if ($authority === '') {
            return null;
        }

        $host = $authority;
        $port = null;

        if (str_starts_with($authority, '[')) {
            $closingBracket = strpos($authority, ']');
            if ($closingBracket === false) {
                return null;
            }

            $host = substr($authority, 0, $closingBracket + 1);
            $remainder = substr($authority, $closingBracket + 1);
            if ($remainder !== '') {
                if (!str_starts_with($remainder, ':')) {
                    return null;
                }

                $port = self::parsePort(substr($remainder, 1));
                if ($port === null) {
                    return null;
                }
            }
        } elseif (false !== ($colon = strpos($authority, ':'))) {
            $host = substr($authority, 0, $colon);
            $port = self::parsePort(substr($authority, $colon + 1));
            if ($port === null) {
                return null;
            }
        }

        if ($host === '' || !Rfc3986::isValidHost($host)) {
            return null;
        }

        return [$host, $port];
    }

    public static function isAbsoluteFormRequestTarget(string $target): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\/\//D', $target) === 1;
    }

    public static function isAsteriskFormRequestTarget(string $method, string $target): bool
    {
        return $method === 'OPTIONS' && $target === '*';
    }

    public static function isConnectAuthorityFormRequestTarget(string $method, string $target): bool
    {
        return $method === 'CONNECT' && strpbrk($target, '/?#') === false;
    }

    public static function parsePort(string $port): ?int
    {
        if (!Rfc3986::isValidPort($port)) {
            return null;
        }

        // A zero port is valid per RFC 3986 but meaningless for an HTTP
        // authority, so reject it on top of the generic syntax check.
        $parsed = (int) ltrim($port, '0');

        return $parsed === 0 ? null : $parsed;
    }
}
