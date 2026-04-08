<?php

namespace LdapRecord\Models\Attributes;

class MbString
{
    /**
     * Get the integer value of a specific character.
     */
    public static function ord(string $string): int
    {
        if (static::isLoaded()) {
            $result = unpack('N', mb_convert_encoding($string, 'UCS-4BE', 'UTF-8'));

            if (is_array($result)) {
                return $result[1];
            }
        }

        return ord($string);
    }

    /**
     * Get the character for a specific integer value.
     */
    public static function chr(int $int): string
    {
        if (static::isLoaded()) {
            return mb_convert_encoding(pack('n', $int), 'UTF-8', 'UTF-16BE');
        }

        return chr($int);
    }

    /**
     * Split a string into its individual characters and return it as an array.
     */
    public static function split(string $value): array
    {
        return preg_split('/(?<!^)(?!$)/u', $value);
    }

    /**
     * Detects if the given string is UTF 8.
     */
    public static function isUtf8(string $string): bool
    {
        if (static::isLoaded()) {
            return mb_detect_encoding($string, 'UTF-8', true) === 'UTF-8';
        }

        return false;
    }

    /**
     * Checks if the mbstring extension is enabled in PHP.
     */
    public static function isLoaded(): bool
    {
        return extension_loaded('mbstring');
    }
}
