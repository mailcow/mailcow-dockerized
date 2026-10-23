<?php
declare(strict_types = 1);

namespace BaconQrCode\Encoder;

use SplFixedArray;
use Traversable;

/**
 * Byte matrix.
 */
final class ByteMatrix
{
    /**
     * Bytes in the matrix, represented as array.
     *
     * @var SplFixedArray<SplFixedArray<int>>
     */
    private $bytes;

    /**
     * Width of the matrix.
     *
     * @var int
     */
    private $width;

    /**
     * Height of the matrix.
     *
     * @var int
     */
    private $height;

    public function __construct(int $width, int $height)
    {
        $this->height = $height;
        $this->width = $width;
        $this->bytes = new SplFixedArray($height);

        for ($y = 0; $y < $height; ++$y) {
            $this->bytes[$y] = SplFixedArray::fromArray(array_fill(0, $width, 0));
        }
    }

    /**
     * Gets the width of the matrix.
     */
    public function getWidth() : int
    {
        return $this->width;
    }

    /**
     * Gets the height of the matrix.
     */
    public function getHeight() : int
    {
        return $this->height;
    }

    /**
     * Gets the internal representation of the matrix.
     *
     * @return SplFixedArray<SplFixedArray<int>>
     */
    public function getArray() : SplFixedArray
    {
        return $this->bytes;
    }

    /**
     * @return Traversable<int>
     */
    public function getBytes() : Traversable
    {
        foreach ($this->bytes as $row) {
            foreach ($row as $byte) {
                yield $byte;
            }
        }
    }

    /**
     * Gets the byte for a specific position.
     */
    public function get(int $x, int $y) : int
    {
        return $this->bytes[$y][$x];
    }

    /**
     * Sets the byte for a specific position.
     */
    public function set(int $x, int $y, int $value) : void
    {
        $this->bytes[$y][$x] = $value;
    }

    /**
     * Clears the matrix with a specific value.
     */
    public function clear(int $value) : void
    {
        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                $this->bytes[$y][$x] = $value;
            }
        }
    }

    public function __clone()
    {
        $this->bytes = clone $this->bytes;

        foreach ($this->bytes as $index => $row) {
            $this->bytes[$index] = clone $row;
        }
    }

    /**
     * Returns a string representation of the matrix.
     */
    public function __toString() : string
    {
        $result = '';

        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                switch ($this->bytes[$y][$x]) {
                    case 0:
                        $result .= ' 0';
                        break;

                    case 1:
                        $result .= ' 1';
                        break;

                    default:
                        $result .= '  ';
                        break;
                }
            }

            $result .= "\n";
        }

        return $result;
    }
}
