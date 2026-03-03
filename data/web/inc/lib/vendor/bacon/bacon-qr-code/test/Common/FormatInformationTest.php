<?php
declare(strict_types = 1);

namespace BaconQrCodeTest\Common;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Common\FormatInformation;
use PHPUnit\Framework\TestCase;

class FormatInformationTest extends TestCase
{
    private const MASKED_TEST_FORMAT_INFO = 0x2bed;
    private const UNMAKSED_TEST_FORMAT_INFO = self::MASKED_TEST_FORMAT_INFO ^ 0x5412;

    public function testBitsDiffering() : void
    {
        $this->assertSame(0, FormatInformation::numBitsDiffering(1, 1));
        $this->assertSame(1, FormatInformation::numBitsDiffering(0, 2));
        $this->assertSame(2, FormatInformation::numBitsDiffering(1, 2));
        $this->assertEquals(32, FormatInformation::numBitsDiffering(-1, 0));
    }

    public function testDecode() : void
    {
        $expected = FormatInformation::decodeFormatInformation(
            self::MASKED_TEST_FORMAT_INFO,
            self::MASKED_TEST_FORMAT_INFO
        );

        $this->assertNotNull($expected);
        $this->assertSame(7, $expected->getDataMask());
        $this->assertSame(ErrorCorrectionLevel::Q(), $expected->getErrorCorrectionLevel());

        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::UNMAKSED_TEST_FORMAT_INFO,
                self::MASKED_TEST_FORMAT_INFO
            )
        );
    }

    public function testDecodeWithBitDifference() : void
    {
        $expected = FormatInformation::decodeFormatInformation(
            self::MASKED_TEST_FORMAT_INFO,
            self::MASKED_TEST_FORMAT_INFO
        );

        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0x1,
                self::MASKED_TEST_FORMAT_INFO ^ 0x1
            )
        );
        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0x3,
                self::MASKED_TEST_FORMAT_INFO ^ 0x3
            )
        );
        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0x7,
                self::MASKED_TEST_FORMAT_INFO ^ 0x7
            )
        );
        $this->assertNull(
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0xf,
                self::MASKED_TEST_FORMAT_INFO ^ 0xf
            )
        );
    }

    public function testDecodeWithMisRead() : void
    {
        $expected = FormatInformation::decodeFormatInformation(
            self::MASKED_TEST_FORMAT_INFO,
            self::MASKED_TEST_FORMAT_INFO
        );

        $this->assertEquals(
            $expected,
            FormatInformation::decodeFormatInformation(
                self::MASKED_TEST_FORMAT_INFO ^ 0x3,
                self::MASKED_TEST_FORMAT_INFO ^ 0xf
            )
        );
    }
}
