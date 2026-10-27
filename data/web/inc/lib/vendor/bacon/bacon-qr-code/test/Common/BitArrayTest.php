<?php
declare(strict_types = 1);

namespace BaconQrCodeTest\Common;

use BaconQrCode\Common\BitArray;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Version as PHPUnitVersion;

final class BitArrayTest extends TestCase
{
    private function getPhpUnitMajorVersion(): int
    {
        return (int) explode('.', PHPUnitVersion::id())[0];
    }

    public function testGetSet() : void
    {
        $array = new BitArray(33);

        for ($i = 0; $i < 33; ++$i) {
            $this->assertFalse($array->get($i));
            $array->set($i);
            $this->assertTrue($array->get($i));
        }
    }

    public function testGetNextSet1() : void
    {
        $array = new BitArray(32);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            if ($this->getPhpUnitMajorVersion() === 7) {
                $this->assertEquals($i, 32, '', $array->getNextSet($i));
            } else {
                $this->assertEqualsWithDelta($i, 32, $array->getNextSet($i));
            }
        }

        $array = new BitArray(33);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            if ($this->getPhpUnitMajorVersion() === 7) {
                $this->assertEquals($i, 33, '', $array->getNextSet($i));
            } else {
                $this->assertEqualsWithDelta($i, 33, $array->getNextSet($i));
            }
        }
    }

    public function testGetNextSet2() : void
    {
        $array = new BitArray(33);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            if ($this->getPhpUnitMajorVersion() === 7) {
                $this->assertEquals($i, $i <= 31 ? 31 : 33, '', $array->getNextSet($i));
            } else {
                $this->assertEqualsWithDelta($i, $i <= 31 ? 31 : 33, $array->getNextSet($i));
            }
        }

        $array = new BitArray(33);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            if ($this->getPhpUnitMajorVersion() === 7) {
                $this->assertEquals($i, 32, '', $array->getNextSet($i));
            } else {
                $this->assertEqualsWithDelta($i, 32, $array->getNextSet($i));
            }
        }
    }

    public function testGetNextSet3() : void
    {
        $array = new BitArray(63);
        $array->set(31);
        $array->set(32);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            if ($i <= 31) {
                $expected = 31;
            } elseif ($i <= 32) {
                $expected = 32;
            } else {
                $expected = 63;
            }

            if ($this->getPhpUnitMajorVersion() === 7) {
                $this->assertEquals($i, $expected, '', $array->getNextSet($i));
            } else {
                $this->assertEqualsWithDelta($i, $expected, $array->getNextSet($i));
            }
        }
    }

    public function testGetNextSet4() : void
    {
        $array = new BitArray(63);
        $array->set(33);
        $array->set(40);

        for ($i = 0; $i < $array->getSize(); ++$i) {
            if ($i <= 33) {
                $expected = 33;
            } elseif ($i <= 40) {
                $expected = 40;
            } else {
                $expected = 63;
            }

            if ($this->getPhpUnitMajorVersion() === 7) {
                $this->assertEquals($i, $expected, '', $array->getNextSet($i));
            } else {
                $this->assertEqualsWithDelta($i, $expected, $array->getNextSet($i));
            }
        }
    }

    public function testGetNextSet5() : void
    {
        mt_srand(0xdeadbeef, MT_RAND_PHP);

        for ($i = 0; $i < 10; ++$i) {
            $array = new BitArray(mt_rand(1, 100));
            $numSet = mt_rand(0, 19);

            for ($j = 0; $j < $numSet; ++$j) {
                $array->set(mt_rand(0, $array->getSize() - 1));
            }

            $numQueries = mt_rand(0, 19);

            for ($j = 0; $j < $numQueries; ++$j) {
                $query = mt_rand(0, $array->getSize() - 1);
                $expected = $query;

                while ($expected < $array->getSize() && ! $array->get($expected)) {
                    ++$expected;
                }

                $actual = $array->getNextSet($query);

                if ($actual !== $expected) {
                    $array->getNextSet($query);
                }

                $this->assertEquals($expected, $actual);
            }
        }
    }

    public function testSetBulk() : void
    {
        $array = new BitArray(64);
        $array->setBulk(32, 0xFFFF0000);

        for ($i = 0; $i < 48; ++$i) {
            $this->assertFalse($array->get($i));
        }

        for ($i = 48; $i < 64; ++$i) {
            $this->assertTrue($array->get($i));
        }
    }

    public function testClear() : void
    {
        $array = new BitArray(32);

        for ($i = 0; $i < 32; ++$i) {
            $array->set($i);
        }

        $array->clear();

        for ($i = 0; $i < 32; ++$i) {
            $this->assertFalse($array->get($i));
        }
    }

    public function testGetArray() : void
    {
        $array = new BitArray(64);
        $array->set(0);
        $array->set(63);

        $ints = $array->getBitArray();

        $this->assertSame(1, $ints[0]);
        $this->assertSame(0x80000000, $ints[1]);
    }

    public function testIsRange() : void
    {
        $array = new BitArray(64);
        $this->assertTrue($array->isRange(0, 64, false));
        $this->assertFalse($array->isRange(0, 64, true));

        $array->set(32);
        $this->assertTrue($array->isRange(32, 33, true));

        $array->set(31);
        $this->assertTrue($array->isRange(31, 33, true));

        $array->set(34);
        $this->assertFalse($array->isRange(31, 35, true));

        for ($i = 0; $i < 31; ++$i) {
            $array->set($i);
        }

        $this->assertTrue($array->isRange(0, 33, true));

        for ($i = 33; $i < 64; ++$i) {
            $array->set($i);
        }

        $this->assertTrue($array->isRange(0, 64, true));
        $this->assertFalse($array->isRange(0, 64, false));
    }
}
