<?php
declare(strict_types = 1);

namespace BaconQrCode\Encoder;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Common\Mode;
use BaconQrCode\Common\Version;

/**
 * QR code.
 */
final class QrCode
{
    /**
     * Number of possible mask patterns.
     */
    public const NUM_MASK_PATTERNS = 8;

    /**
     * Mode of the QR code.
     *
     * @var Mode
     */
    private $mode;

    /**
     * EC level of the QR code.
     *
     * @var ErrorCorrectionLevel
     */
    private $errorCorrectionLevel;

    /**
     * Version of the QR code.
     *
     * @var Version
     */
    private $version;

    /**
     * Mask pattern of the QR code.
     *
     * @var int
     */
    private $maskPattern = -1;

    /**
     * Matrix of the QR code.
     *
     * @var ByteMatrix
     */
    private $matrix;

    public function __construct(
        Mode $mode,
        ErrorCorrectionLevel $errorCorrectionLevel,
        Version $version,
        int $maskPattern,
        ByteMatrix $matrix
    ) {
        $this->mode = $mode;
        $this->errorCorrectionLevel = $errorCorrectionLevel;
        $this->version = $version;
        $this->maskPattern = $maskPattern;
        $this->matrix = $matrix;
    }

    /**
     * Gets the mode.
     */
    public function getMode() : Mode
    {
        return $this->mode;
    }

    /**
     * Gets the EC level.
     */
    public function getErrorCorrectionLevel() : ErrorCorrectionLevel
    {
        return $this->errorCorrectionLevel;
    }

    /**
     * Gets the version.
     */
    public function getVersion() : Version
    {
        return $this->version;
    }

    /**
     * Gets the mask pattern.
     */
    public function getMaskPattern() : int
    {
        return $this->maskPattern;
    }

    /**
     * Gets the matrix.
     *
     * @return ByteMatrix
     */
    public function getMatrix()
    {
        return $this->matrix;
    }

    /**
     * Validates whether a mask pattern is valid.
     */
    public static function isValidMaskPattern(int $maskPattern) : bool
    {
        return $maskPattern > 0 && $maskPattern < self::NUM_MASK_PATTERNS;
    }

    /**
     * Returns a string representation of the QR code.
     */
    public function __toString() : string
    {
        $result = "<<\n"
                . ' mode: ' . $this->mode . "\n"
                . ' ecLevel: ' . $this->errorCorrectionLevel . "\n"
                . ' version: ' . $this->version . "\n"
                . ' maskPattern: ' . $this->maskPattern . "\n";

        if ($this->matrix === null) {
            $result .= " matrix: null\n";
        } else {
            $result .= " matrix:\n";
            $result .= $this->matrix;
        }

        $result .= ">>\n";

        return $result;
    }
}
