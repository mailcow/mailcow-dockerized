<?php
declare(strict_types = 1);

namespace BaconQrCodeTest\Common;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Common\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    public function versions() : array
    {
        $array = [];

        for ($i = 1; $i <= 40; ++$i) {
            $array[] = [$i, 4 * $i + 17];
        }

        return $array;
    }

    public function decodeInformation() : array
    {
        return [
            [7, 0x07c94],
            [12, 0x0c762],
            [17, 0x1145d],
            [22, 0x168c9],
            [27, 0x1b08e],
            [32, 0x209d5],
        ];
    }

    /**
     * @dataProvider versions
     */
    public function testVersionForNumber(int $versionNumber, int $dimension) : void
    {
        $version = Version::getVersionForNumber($versionNumber);

        $this->assertNotNull($version);
        $this->assertEquals($versionNumber, $version->getVersionNumber());
        $this->assertNotNull($version->getAlignmentPatternCenters());

        if ($versionNumber > 1) {
            $this->assertTrue(count($version->getAlignmentPatternCenters()) > 0);
        }

        $this->assertEquals($dimension, $version->getDimensionForVersion());
        $this->assertNotNull($version->getEcBlocksForLevel(ErrorCorrectionLevel::H()));
        $this->assertNotNull($version->getEcBlocksForLevel(ErrorCorrectionLevel::L()));
        $this->assertNotNull($version->getEcBlocksForLevel(ErrorCorrectionLevel::M()));
        $this->assertNotNull($version->getEcBlocksForLevel(ErrorCorrectionLevel::Q()));
        $this->assertNotNull($version->buildFunctionPattern());
    }

    /**
     * @dataProvider versions
     */
    public function testGetProvisionalVersionForDimension(int $versionNumber, int $dimension) : void
    {
        $this->assertSame(
            $versionNumber,
            Version::getProvisionalVersionForDimension($dimension)->getVersionNumber()
        );
    }

    /**
     * @dataProvider decodeInformation
     */
    public function testDecodeVersionInformation(int $expectedVersion, int $mask) : void
    {
        $version = Version::decodeVersionInformation($mask);
        $this->assertNotNull($version);
        $this->assertSame($expectedVersion, $version->getVersionNumber());
    }
}
