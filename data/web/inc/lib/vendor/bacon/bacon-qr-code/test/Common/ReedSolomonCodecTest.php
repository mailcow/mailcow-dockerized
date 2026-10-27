<?php
declare(strict_types = 1);

namespace BaconQrCodeTest\Common;

use BaconQrCode\Common\ReedSolomonCodec;
use PHPUnit\Framework\TestCase;
use SplFixedArray;

class ReedSolomonTest extends TestCase
{
    public function tabs() : array
    {
        return [
            [2, 0x7, 1, 1, 1],
            [3, 0xb, 1, 1, 2],
            [4, 0x13, 1, 1, 4],
            [5, 0x25, 1, 1, 6],
            [6, 0x43, 1, 1, 8],
            [7, 0x89, 1, 1, 10],
            [8, 0x11d, 1, 1, 32],
        ];
    }

    /**
     * @dataProvider tabs
     */
    public function testCodec(int $symbolSize, int $generatorPoly, int $firstRoot, int $primitive, int $numRoots) : void
    {
        mt_srand(0xdeadbeef, MT_RAND_PHP);

        $blockSize = (1 << $symbolSize) - 1;
        $dataSize  = $blockSize - $numRoots;
        $codec     = new ReedSolomonCodec($symbolSize, $generatorPoly, $firstRoot, $primitive, $numRoots, 0);

        for ($errors = 0; $errors <= $numRoots / 2; ++$errors) {
            // Load block with random data and encode
            $block = SplFixedArray::fromArray(array_fill(0, $blockSize, 0), false);

            for ($i = 0; $i < $dataSize; ++$i) {
                $block[$i] = mt_rand(0, $blockSize);
            }

            // Make temporary copy
            $tBlock = clone $block;
            $parity = SplFixedArray::fromArray(array_fill(0, $numRoots, 0), false);
            $errorLocations = SplFixedArray::fromArray(array_fill(0, $blockSize, 0), false);
            $erasures = [];

            // Create parity
            $codec->encode($block, $parity);

            // Copy parity into test blocks
            for ($i = 0; $i < $numRoots; ++$i) {
                $block[$i + $dataSize] = $parity[$i];
                $tBlock[$i + $dataSize] = $parity[$i];
            }

            // Seed with errors
            for ($i = 0; $i < $errors; ++$i) {
                $errorValue = mt_rand(1, $blockSize);

                do {
                    $errorLocation = mt_rand(0, $blockSize);
                } while (0 !== $errorLocations[$errorLocation]);

                $errorLocations[$errorLocation] = 1;

                if (mt_rand(0, 1)) {
                    $erasures[] = $errorLocation;
                }

                $tBlock[$errorLocation] ^= $errorValue;
            }

            $erasures = SplFixedArray::fromArray($erasures, false);

            // Decode the errored block
            $foundErrors = $codec->decode($tBlock, $erasures);

            if ($errors > 0 && null === $foundErrors) {
                $this->assertSame($block, $tBlock, 'Decoder failed to correct errors');
            }

            $this->assertSame($errors, $foundErrors, 'Found errors do not equal expected errors');

            for ($i = 0; $i < $foundErrors; ++$i) {
                if (0 === $errorLocations[$erasures[$i]]) {
                    $this->fail(sprintf('Decoder indicates error in location %d without error', $erasures[$i]));
                }
            }

            $this->assertEquals($block, $tBlock, 'Decoder did not correct errors');
        }
    }
}
