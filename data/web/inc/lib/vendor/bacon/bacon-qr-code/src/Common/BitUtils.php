<?php
declare(strict_types = 1);

namespace BaconQrCode\Common;

/**
 * General bit utilities.
 *
 * All utility methods are based on 32-bit integers and also work on 64-bit
 * systems.
 */
final class BitUtils
{
    private function __construct()
    {
    }

    /**
     * Performs an unsigned right shift.
     *
     * This is the same as the unsigned right shift operator ">>>" in other
     * languages.
     */
    public static function unsignedRightShift(int $a, int $b) : int
    {
        return (
            $a >= 0
            ? $a >> $b
            : (($a & 0x7fffffff) >> $b) | (0x40000000 >> ($b - 1))
        );
    }

    /**
     * Gets the number of trailing zeros.
     */
    public static function numberOfTrailingZeros(int $i) : int
    {
        $lastPos = strrpos(str_pad(decbin($i), 32, '0', STR_PAD_LEFT), '1');
        return $lastPos === false ? 32 : 31 - $lastPos;
    }
}
