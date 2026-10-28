<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * @internal
 */
final class Integers
{
    private function __construct()
    {
    }

    public static function add(int $a, int $b): int
    {
        if ($a < 0 || $b < 0) {
            throw new \InvalidArgumentException('Integer operands must be non-negative');
        }

        if ($b > \PHP_INT_MAX - $a) {
            throw new \OverflowException('Stream byte count exceeds the maximum integer size supported on this platform');
        }

        return $a + $b;
    }

    public static function addSigned(int $base, int $delta): int
    {
        if ($base < 0) {
            throw new \InvalidArgumentException('Stream offset must be non-negative');
        }

        if ($delta > 0 && $delta > \PHP_INT_MAX - $base) {
            throw new \OverflowException('Stream offset exceeds the maximum integer size supported on this platform');
        }

        $value = $base + $delta;
        if ($value < 0) {
            // A negative computed offset is a seek failure at runtime, so throw
            // RuntimeException per PSR-7, unlike the precondition check above.
            throw new \RuntimeException('Stream offset must be non-negative');
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    public static function assertEngineInteger($value, string $what): ?int
    {
        if ($value === false || $value === null) {
            return null;
        }

        if (!\is_int($value) || $value < 0) {
            throw new \OverflowException($what.' exceeds the maximum integer size supported on this platform');
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    public static function assertOptionalNonNegativeSize($value, string $name): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!\is_int($value) || $value < 0) {
            throw new \InvalidArgumentException($name.' must be a non-negative integer or null');
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    public static function assertNonNegativeInteger($value, string $name): int
    {
        if (!\is_int($value) || $value < 0) {
            throw new \InvalidArgumentException($name.' must be a non-negative integer');
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    public static function assertLimitInteger($value, string $name): int
    {
        if (!\is_int($value) || $value < -1) {
            throw new \InvalidArgumentException($name.' must be -1 or a non-negative integer');
        }

        return $value;
    }
}
