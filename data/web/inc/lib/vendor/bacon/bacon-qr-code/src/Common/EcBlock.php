<?php
declare(strict_types = 1);

namespace BaconQrCode\Common;

/**
 * Encapsulates the parameters for one error-correction block in one symbol version.
 *
 * This includes the number of data codewords, and the number of times a block with these parameters is used
 * consecutively in the QR code version's format.
 */
final class EcBlock
{
    /**
     * How many times the block is used.
     *
     * @var int
     */
    private $count;

    /**
     * Number of data codewords.
     *
     * @var int
     */
    private $dataCodewords;

    public function __construct(int $count, int $dataCodewords)
    {
        $this->count = $count;
        $this->dataCodewords = $dataCodewords;
    }

    /**
     * Returns how many times the block is used.
     */
    public function getCount() : int
    {
        return $this->count;
    }

    /**
     * Returns the number of data codewords.
     */
    public function getDataCodewords() : int
    {
        return $this->dataCodewords;
    }
}
