<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * Escapes control characters and malformed UTF-8 for use in diagnostics.
 */
final class DiagnosticValue
{
    private function __construct()
    {
    }

    /**
     * Escapes C0, DEL, and C1 controls as uppercase `\xNN` sequences.
     *
     * ASCII bytes from 0x20 through 0x7E and valid UTF-8 characters outside
     * those control ranges remain unchanged. If the input is malformed UTF-8 or
     * PCRE cannot process it, every byte outside printable ASCII is escaped.
     * Valid C1 characters are rendered as `\xNN` using their Unicode code
     * points. During bytewise fallback, each original byte outside printable
     * ASCII is rendered in the same form. The result is diagnostic text, not a
     * reversible encoding.
     *
     * This does not encode values for HTML, JSON, shells, terminals, URLs, or
     * protocol fields.
     */
    public static function escape(string $value): string
    {
        $escaped = \preg_replace_callback(
            '/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u',
            static function (array $matches): string {
                $character = $matches[0];
                $codePoint = \strlen($character) === 1 ? \ord($character) : \ord($character[1]);

                return \sprintf('\\x%02X', $codePoint);
            },
            $value
        );

        return $escaped ?? self::escapeBytes($value);
    }

    private static function escapeBytes(string $value): string
    {
        $escaped = '';

        for ($offset = 0, $length = \strlen($value); $offset < $length; ++$offset) {
            $byte = \ord($value[$offset]);
            if ($byte >= 0x20 && $byte <= 0x7E) {
                $escaped .= $value[$offset];

                continue;
            }

            $escaped .= \sprintf('\\x%02X', $byte);
        }

        return $escaped;
    }
}
