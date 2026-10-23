<?php
declare(strict_types = 1);

namespace BaconQrCodeTest\Common;

use BaconQrCode\Common\BitArray;
use BaconQrCode\Common\BitMatrix;
use PHPUnit\Framework\TestCase;

class BitMatrixTest extends TestCase
{
    public function testGetSet() : void
    {
        $matrix = new BitMatrix(33);
        $this->assertEquals(33, $matrix->getHeight());

        for ($y = 0; $y < 33; ++$y) {
            for ($x = 0; $x < 33; ++$x) {
                if ($y * $x % 3 === 0) {
                    $matrix->set($x, $y);
                }
            }
        }

        for ($y = 0; $y < 33; $y++) {
            for ($x = 0; $x < 33; ++$x) {
                $this->assertSame(0 === $x * $y % 3, $matrix->get($x, $y));
            }
        }
    }

    public function testSetRegion() : void
    {
        $matrix = new BitMatrix(5);
        $matrix->setRegion(1, 1, 3, 3);

        for ($y = 0; $y < 5; ++$y) {
            for ($x = 0; $x < 5; ++$x) {
                $this->assertSame($y >= 1 && $y <= 3 && $x >= 1 && $x <= 3, $matrix->get($x, $y));
            }
        }
    }

    public function testRectangularMatrix() : void
    {
        $matrix = new BitMatrix(75, 20);
        $this->assertSame(75, $matrix->getWidth());
        $this->assertSame(20, $matrix->getHeight());

        $matrix->set(10, 0);
        $matrix->set(11, 1);
        $matrix->set(50, 2);
        $matrix->set(51, 3);
        $matrix->flip(74, 4);
        $matrix->flip(0, 5);

        $this->assertTrue($matrix->get(10, 0));
        $this->assertTrue($matrix->get(11, 1));
        $this->assertTrue($matrix->get(50, 2));
        $this->assertTrue($matrix->get(51, 3));
        $this->assertTrue($matrix->get(74, 4));
        $this->assertTrue($matrix->get(0, 5));

        $matrix->flip(50, 2);
        $matrix->flip(51, 3);

        $this->assertFalse($matrix->get(50, 2));
        $this->assertFalse($matrix->get(51, 3));
    }

    public function testRectangularSetRegion() : void
    {
        $matrix = new BitMatrix(320, 240);
        $this->assertSame(320, $matrix->getWidth());
        $this->assertSame(240, $matrix->getHeight());

        $matrix->setRegion(105, 22, 80, 12);

        for ($y = 0; $y < 240; ++$y) {
            for ($x = 0; $x < 320; ++$x) {
                $this->assertEquals($y >= 22 && $y < 34 && $x >= 105 && $x < 185, $matrix->get($x, $y));
            }
        }
    }

    public function testGetRow() : void
    {
        $matrix = new BitMatrix(102, 5);

        for ($x = 0; $x < 102; ++$x) {
            if (0 === ($x & 3)) {
                $matrix->set($x, 2);
            }
        }

        $array1 = $matrix->getRow(2, null);
        $this->assertSame(102, $array1->getSize());

        $array2 = new BitArray(60);
        $array2 = $matrix->getRow(2, $array2);
        $this->assertSame(102, $array2->getSize());

        $array3 = new BitArray(200);
        $array3 = $matrix->getRow(2, $array3);
        $this->assertSame(200, $array3->getSize());

        for ($x = 0; $x < 102; ++$x) {
            $on = (0 === ($x & 3));

            $this->assertSame($on, $array1->get($x));
            $this->assertSame($on, $array2->get($x));
            $this->assertSame($on, $array3->get($x));
        }
    }
}
