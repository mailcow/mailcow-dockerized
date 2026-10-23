<?php

declare(strict_types=1);

namespace Mailcow\Core;

/**
 * Helpers for loosely typed request values. Form values arrive as strings, JSON bodies as any type,
 * and 011-api.js turns JSON-looking strings into arrays, so services must not trust the PHP type.
 */
class Input
{
    /**
     * Scalar value as trimmed string; '' for null; null for arrays/objects (= invalid).
     */
    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }
        if (!is_scalar($value)) {
            return null;
        }
        return trim((string)$value);
    }

    /**
     * Integer within [$min, $max] (filter_var rules: "12" and 12 pass, "12abc" and "1.5" do not), else null.
     */
    public static function intInRange(mixed $value, int $min, int $max): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        return ($int === false) ? null : $int;
    }
}
