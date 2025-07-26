<?php
/**
 * BaconQrCode
 *
 * @link      http://github.com/Bacon/BaconQrCode For the canonical source repository
 * @copyright 2013 Ben 'DASPRiD' Scholzen
 * @license   http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */

namespace BaconQrCode\Common;

/**
 * Encapsulates a QR Code's format information, including the data mask used and error correction level.
 */
class FormatInformation
{
    /**
     * Mask for format information.
     */
    private const FORMAT_INFO_MASK_QR = 0x5412;

    /**
     * Lookup table for decoding format information.
     *
     * See ISO 18004:2006, Annex C, Table C.1
     */
    private const FORMAT_INFO_DECODE_LOOKUP = [
        [0x5412, 0x00],
        [0x5125, 0x01],
        [0x5e7c, 0x02],
        [0x5b4b, 0x03],
        [0x45f9, 0x04],
        [0x40ce, 0x05],
        [0x4f97, 0x06],
        [0x4aa0, 0x07],
        [0x77c4, 0x08],
        [0x72f3, 0x09],
        [0x7daa, 0x0a],
        [0x789d, 0x0b],
        [0x662f, 0x0c],
        [0x6318, 0x0d],
        [0x6c41, 0x0e],
        [0x6976, 0x0f],
        [0x1689, 0x10],
        [0x13be, 0x11],
        [0x1ce7, 0x12],
        [0x19d0, 0x13],
        [0x0762, 0x14],
        [0x0255, 0x15],
        [0x0d0c, 0x16],
        [0x083b, 0x17],
        [0x355f, 0x18],
        [0x3068, 0x19],
        [0x3f31, 0x1a],
        [0x3a06, 0x1b],
        [0x24b4, 0x1c],
        [0x2183, 0x1d],
        [0x2eda, 0x1e],
        [0x2bed, 0x1f],
    ];

    /**
     * Offset i holds the number of 1 bits in the binary representation of i.
     *
     * @var int[]
     */
    private const BITS_SET_IN_HALF_BYTE = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];

    /**
     * Error correction level.
     */
    private ErrorCorrectionLevel $ecLevel;

    private int $dataMask;

    protected function __construct(int $formatInfo)
    {
        $this->ecLevel = ErrorCorrectionLevel::forBits(($formatInfo >> 3) & 0x3);
        $this->dataMask = $formatInfo & 0x7;
    }

    /**
     * Checks how many bits are different between two integers.
     */
    public static function numBitsDiffering(int $a, int $b) : int
    {
        $a ^= $b;

        return (
            self::BITS_SET_IN_HALF_BYTE[$a & 0xf]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 4) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 8) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 12) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 16) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 20) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 24) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 28) & 0xf)]
        );
    }

    /**
     * Decodes format information.
     */
    public static function decodeFormatInformation(int $maskedFormatInfo1, int $maskedFormatInfo2) : ?self
    {
        $formatInfo = self::doDecodeFormatInformation($maskedFormatInfo1, $maskedFormatInfo2);

        if (null !== $formatInfo) {
            return $formatInfo;
        }

        // Should return null, but, some QR codes apparently do not mask this info. Try again by actually masking the
        // pattern first.
        return self::doDecodeFormatInformation(
            $maskedFormatInfo1 ^ self::FORMAT_INFO_MASK_QR,
            $maskedFormatInfo2 ^ self::FORMAT_INFO_MASK_QR
        );
    }

    /**
     * Internal method for decoding format information.
     */
    private static function doDecodeFormatInformation(int $maskedFormatInfo1, int $maskedFormatInfo2) : ?self
    {
        $bestDifference = PHP_INT_MAX;
        $bestFormatInfo = 0;

        foreach (self::FORMAT_INFO_DECODE_LOOKUP as $decodeInfo) {
            $targetInfo = $decodeInfo[0];

            if ($targetInfo === $maskedFormatInfo1 || $targetInfo === $maskedFormatInfo2) {
                // Found an exact match
                return new self($decodeInfo[1]);
            }

            $bitsDifference = self::numBitsDiffering($maskedFormatInfo1, $targetInfo);

            if ($bitsDifference < $bestDifference) {
                $bestFormatInfo = $decodeInfo[1];
                $bestDifference = $bitsDifference;
            }

            if ($maskedFormatInfo1 !== $maskedFormatInfo2) {
                // Also try the other option
                $bitsDifference = self::numBitsDiffering($maskedFormatInfo2, $targetInfo);

                if ($bitsDifference < $bestDifference) {
                    $bestFormatInfo = $decodeInfo[1];
                    $bestDifference = $bitsDifference;
                }
            }
        }

        // Hamming distance of the 32 masked codes is 7, by construction, so <= 3 bits differing means we found a match.
        if ($bestDifference <= 3) {
            return new self($bestFormatInfo);
        }

        return null;
    }

    /**
     * Returns the error correction level.
     */
    public function getErrorCorrectionLevel() : ErrorCorrectionLevel
    {
        return $this->ecLevel;
    }

    /**
     * Returns the data mask.
     */
    public function getDataMask() : int
    {
        return $this->dataMask;
    }

    /**
     * Hashes the code of the EC level.
     */
    public function hashCode() : int
    {
        return ($this->ecLevel->getBits() << 3) | $this->dataMask;
    }

    /**
     * Verifies if this instance equals another one.
     */
    public function equals(self $other) : bool
    {
        return (
            $this->ecLevel === $other->ecLevel
            && $this->dataMask === $other->dataMask
        );
    }
}
