<?php
declare(strict_types = 1);

namespace BaconQrCode\Common;

use BaconQrCode\Exception\InvalidArgumentException;
use SplFixedArray;

/**
 * A simple, fast array of bits.
 */
final class BitArray
{
    /**
     * Bits represented as an array of integers.
     *
     * @var SplFixedArray<int>
     */
    private $bits;

    /**
     * Size of the bit array in bits.
     *
     * @var int
     */
    private $size;

    /**
     * Creates a new bit array with a given size.
     */
    public function __construct(int $size = 0)
    {
        $this->size = $size;
        $this->bits = SplFixedArray::fromArray(array_fill(0, ($this->size + 31) >> 3, 0));
    }

    /**
     * Gets the size in bits.
     */
    public function getSize() : int
    {
        return $this->size;
    }

    /**
     * Gets the size in bytes.
     */
    public function getSizeInBytes() : int
    {
        return ($this->size + 7) >> 3;
    }

    /**
     * Ensures that the array has a minimum capacity.
     */
    public function ensureCapacity(int $size) : void
    {
        if ($size > count($this->bits) << 5) {
            $this->bits->setSize(($size + 31) >> 5);
        }
    }

    /**
     * Gets a specific bit.
     */
    public function get(int $i) : bool
    {
        return 0 !== ($this->bits[$i >> 5] & (1 << ($i & 0x1f)));
    }

    /**
     * Sets a specific bit.
     */
    public function set(int $i) : void
    {
        $this->bits[$i >> 5] = $this->bits[$i >> 5] | 1 << ($i & 0x1f);
    }

    /**
     * Flips a specific bit.
     */
    public function flip(int $i) : void
    {
        $this->bits[$i >> 5] ^= 1 << ($i & 0x1f);
    }

    /**
     * Gets the next set bit position from a given position.
     */
    public function getNextSet(int $from) : int
    {
        if ($from >= $this->size) {
            return $this->size;
        }

        $bitsOffset = $from >> 5;
        $currentBits = $this->bits[$bitsOffset];
        $bitsLength = count($this->bits);
        $currentBits &= ~((1 << ($from & 0x1f)) - 1);

        while (0 === $currentBits) {
            if (++$bitsOffset === $bitsLength) {
                return $this->size;
            }

            $currentBits = $this->bits[$bitsOffset];
        }

        $result = ($bitsOffset << 5) + BitUtils::numberOfTrailingZeros($currentBits);
        return $result > $this->size ? $this->size : $result;
    }

    /**
     * Gets the next unset bit position from a given position.
     */
    public function getNextUnset(int $from) : int
    {
        if ($from >= $this->size) {
            return $this->size;
        }

        $bitsOffset = $from >> 5;
        $currentBits = ~$this->bits[$bitsOffset];
        $bitsLength = count($this->bits);
        $currentBits &= ~((1 << ($from & 0x1f)) - 1);

        while (0 === $currentBits) {
            if (++$bitsOffset === $bitsLength) {
                return $this->size;
            }

            $currentBits = ~$this->bits[$bitsOffset];
        }

        $result = ($bitsOffset << 5) + BitUtils::numberOfTrailingZeros($currentBits);
        return $result > $this->size ? $this->size : $result;
    }

    /**
     * Sets a bulk of bits.
     */
    public function setBulk(int $i, int $newBits) : void
    {
        $this->bits[$i >> 5] = $newBits;
    }

    /**
     * Sets a range of bits.
     *
     * @throws InvalidArgumentException if end is smaller than start
     */
    public function setRange(int $start, int $end) : void
    {
        if ($end < $start) {
            throw new InvalidArgumentException('End must be greater or equal to start');
        }

        if ($end === $start) {
            return;
        }

        --$end;

        $firstInt = $start >> 5;
        $lastInt = $end >> 5;

        for ($i = $firstInt; $i <= $lastInt; ++$i) {
            $firstBit = $i > $firstInt ? 0 : $start & 0x1f;
            $lastBit = $i < $lastInt ? 31 : $end & 0x1f;

            if (0 === $firstBit && 31 === $lastBit) {
                $mask = 0x7fffffff;
            } else {
                $mask = 0;

                for ($j = $firstBit; $j < $lastBit; ++$j) {
                    $mask |= 1 << $j;
                }
            }

            $this->bits[$i] = $this->bits[$i] | $mask;
        }
    }

    /**
     * Clears the bit array, unsetting every bit.
     */
    public function clear() : void
    {
        $bitsLength = count($this->bits);

        for ($i = 0; $i < $bitsLength; ++$i) {
            $this->bits[$i] = 0;
        }
    }

    /**
     * Checks if a range of bits is set or not set.

     * @throws InvalidArgumentException if end is smaller than start
     */
    public function isRange(int $start, int $end, bool $value) : bool
    {
        if ($end < $start) {
            throw new InvalidArgumentException('End must be greater or equal to start');
        }

        if ($end === $start) {
            return true;
        }

        --$end;

        $firstInt = $start >> 5;
        $lastInt = $end >> 5;

        for ($i = $firstInt; $i <= $lastInt; ++$i) {
            $firstBit = $i > $firstInt ? 0 : $start & 0x1f;
            $lastBit = $i < $lastInt ? 31 : $end & 0x1f;

            if (0 === $firstBit && 31 === $lastBit) {
                $mask = 0x7fffffff;
            } else {
                $mask = 0;

                for ($j = $firstBit; $j <= $lastBit; ++$j) {
                    $mask |= 1 << $j;
                }
            }

            if (($this->bits[$i] & $mask) !== ($value ? $mask : 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Appends a bit to the array.
     */
    public function appendBit(bool $bit) : void
    {
        $this->ensureCapacity($this->size + 1);

        if ($bit) {
            $this->bits[$this->size >> 5] = $this->bits[$this->size >> 5] | (1 << ($this->size & 0x1f));
        }

        ++$this->size;
    }

    /**
     * Appends a number of bits (up to 32) to the array.

     * @throws InvalidArgumentException if num bits is not between 0 and 32
     */
    public function appendBits(int $value, int $numBits) : void
    {
        if ($numBits < 0 || $numBits > 32) {
            throw new InvalidArgumentException('Num bits must be between 0 and 32');
        }

        $this->ensureCapacity($this->size + $numBits);

        for ($numBitsLeft = $numBits; $numBitsLeft > 0; $numBitsLeft--) {
            $this->appendBit((($value >> ($numBitsLeft - 1)) & 0x01) === 1);
        }
    }

    /**
     * Appends another bit array to this array.
     */
    public function appendBitArray(self $other) : void
    {
        $otherSize = $other->getSize();
        $this->ensureCapacity($this->size + $other->getSize());

        for ($i = 0; $i < $otherSize; ++$i) {
            $this->appendBit($other->get($i));
        }
    }

    /**
     * Makes an exclusive-or comparision on the current bit array.
     *
     * @throws InvalidArgumentException if sizes don't match
     */
    public function xorBits(self $other) : void
    {
        $bitsLength = count($this->bits);
        $otherBits  = $other->getBitArray();

        if ($bitsLength !== count($otherBits)) {
            throw new InvalidArgumentException('Sizes don\'t match');
        }

        for ($i = 0; $i < $bitsLength; ++$i) {
            $this->bits[$i] = $this->bits[$i] ^ $otherBits[$i];
        }
    }

    /**
     * Converts the bit array to a byte array.
     *
     * @return SplFixedArray<int>
     */
    public function toBytes(int $bitOffset, int $numBytes) : SplFixedArray
    {
        $bytes = new SplFixedArray($numBytes);

        for ($i = 0; $i < $numBytes; ++$i) {
            $byte = 0;

            for ($j = 0; $j < 8; ++$j) {
                if ($this->get($bitOffset)) {
                    $byte |= 1 << (7 - $j);
                }

                ++$bitOffset;
            }

            $bytes[$i] = $byte;
        }

        return $bytes;
    }

    /**
     * Gets the internal bit array.
     *
     * @return SplFixedArray<int>
     */
    public function getBitArray() : SplFixedArray
    {
        return $this->bits;
    }

    /**
     * Reverses the array.
     */
    public function reverse() : void
    {
        $newBits = new SplFixedArray(count($this->bits));

        for ($i = 0; $i < $this->size; ++$i) {
            if ($this->get($this->size - $i - 1)) {
                $newBits[$i >> 5] = $newBits[$i >> 5] | (1 << ($i & 0x1f));
            }
        }

        $this->bits = $newBits;
    }

    /**
     * Returns a string representation of the bit array.
     */
    public function __toString() : string
    {
        $result = '';

        for ($i = 0; $i < $this->size; ++$i) {
            if (0 === ($i & 0x07)) {
                $result .= ' ';
            }

            $result .= $this->get($i) ? 'X' : '.';
        }

        return $result;
    }
}
