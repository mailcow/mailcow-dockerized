<?php
declare(strict_types = 1);

namespace BaconQrCodeTest\Common;

use BaconQrCode\Common\Mode;
use PHPUnit\Framework\TestCase;

class ModeTest extends TestCase
{
    public function testBitsMatchConstants() : void
    {
        $this->assertSame(0x0, Mode::TERMINATOR()->getBits());
        $this->assertSame(0x1, Mode::NUMERIC()->getBits());
        $this->assertSame(0x2, Mode::ALPHANUMERIC()->getBits());
        $this->assertSame(0x4, Mode::BYTE()->getBits());
        $this->assertSame(0x8, Mode::KANJI()->getBits());
    }
}
