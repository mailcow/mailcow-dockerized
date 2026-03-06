<?php
declare(strict_types = 1);

namespace BaconQrCodeTest\Integration;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Eye\SquareEye;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\Gradient;
use BaconQrCode\Renderer\RendererStyle\GradientType;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

/**
 * @group integration
 */
final class ImagickRenderingTest extends TestCase
{
    use MatchesSnapshots;

    /**
     * @requires extension imagick
     */
    public function testGenericQrCode() : void
    {
        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new ImagickImageBackEnd()
        );
        $writer = new Writer($renderer);
        $tempName = tempnam(sys_get_temp_dir(), 'test') . '.png';
        $writer->writeFile('Hello World!', $tempName);

        $this->assertMatchesFileSnapshot($tempName);
        unlink($tempName);
    }

    /**
     * @requires extension imagick
     */
    public function testIssue79() : void
    {
        $eye = SquareEye::instance();
        $squareModule = SquareModule::instance();

        $eyeFill = new EyeFill(new Rgb(100, 100, 55), new Rgb(100, 100, 255));
        $gradient = new Gradient(new Rgb(100, 100, 55), new Rgb(100, 100, 255), GradientType::HORIZONTAL());

        $renderer = new ImageRenderer(
            new RendererStyle(
                400,
                2,
                $squareModule,
                $eye,
                Fill::withForegroundGradient(new Rgb(255, 255, 255), $gradient, $eyeFill, $eyeFill, $eyeFill)
            ),
            new ImagickImageBackEnd()
        );
        $writer = new Writer($renderer);
        $tempName = tempnam(sys_get_temp_dir(), 'test') . '.png';
        $writer->writeFile('https://apiroad.net/very-long-url', $tempName);

        $this->assertMatchesFileSnapshot($tempName);
        unlink($tempName);
    }
}
