<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * @internal
 */
final class Rfc9110
{
    /**
     * A token for use in a regular expression.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9110#section-5.6.2
     */
    public const TOKEN_PATTERN = '[!#$%&\'*+.^_`|~0-9A-Za-z-]+';

    /**
     * A field value for use in a regular expression.
     *
     * Obsolete line folding is intentionally excluded.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc9110#section-5.5
     */
    public const FIELD_VALUE_PATTERN = '[\x09\x20-\x7E\x80-\xFF]*';

    private function __construct()
    {
    }

    public static function isToken(string $value): bool
    {
        return preg_match('/^'.self::TOKEN_PATTERN.'$/D', $value) === 1;
    }

    public static function isFieldValue(string $value): bool
    {
        return preg_match('/^'.self::FIELD_VALUE_PATTERN.'$/D', $value) === 1;
    }
}
