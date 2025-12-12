<?php

declare(strict_types=1);

namespace BaconQrCode\Renderer;

use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Encoder\MatrixUtil;
use BaconQrCode\Encoder\QrCode;
use BaconQrCode\Exception\InvalidArgumentException;
use BaconQrCode\Exception\RuntimeException;
use BaconQrCode\Renderer\Color\Alpha;
use BaconQrCode\Renderer\Color\ColorInterface;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\Fill;
use GdImage;

final class GDLibRenderer implements RendererInterface
{
    private ?GdImage $image;

    /**
     * @var array<string, int>
     */
    private array $colors;

    public function __construct(
        private int $size,
        private int $margin = 4,
        private string $imageFormat = 'png',
        private int $compressionQuality = 9,
        private ?Fill $fill = null
    ) {
        if (! extension_loaded('gd') || ! function_exists('gd_info')) {
            throw new RuntimeException('You need to install the GD extension to use this back end');
        }

        if ($this->fill === null) {
            $this->fill = Fill::default();
        }
        if ($this->fill->hasGradientFill()) {
            throw new InvalidArgumentException('GDLibRenderer does not support gradients');
        }
    }

    /**
     * @throws InvalidArgumentException if matrix width doesn't match height
     */
    public function render(QrCode $qrCode): string
    {
        $matrix = $qrCode->getMatrix();
        $matrixSize = $matrix->getWidth();

        if ($matrixSize !== $matrix->getHeight()) {
            throw new InvalidArgumentException('Matrix must have the same width and height');
        }

        MatrixUtil::removePositionDetectionPatterns($matrix);
        $this->newImage();
        $this->draw($matrix);

        return $this->renderImage();
    }

    private function newImage(): void
    {
        $img = imagecreatetruecolor($this->size, $this->size);
        if ($img === false) {
            throw new RuntimeException('Failed to create image of that size');
        }

        $this->image = $img;
        imagealphablending($this->image, false);
        imagesavealpha($this->image, true);


        $bg = $this->getColor($this->fill->getBackgroundColor());
        imagefilledrectangle($this->image, 0, 0, $this->size, $this->size, $bg);
        imagealphablending($this->image, true);
    }

    private function draw(ByteMatrix $matrix): void
    {
        $matrixSize = $matrix->getWidth();

        $pointsOnSide = $matrix->getWidth() + $this->margin * 2;
        $pointInPx = $this->size / $pointsOnSide;

        $this->drawEye(0, 0, $pointInPx, $this->fill->getTopLeftEyeFill());
        $this->drawEye($matrixSize - 7, 0, $pointInPx, $this->fill->getTopRightEyeFill());
        $this->drawEye(0, $matrixSize - 7, $pointInPx, $this->fill->getBottomLeftEyeFill());

        $rows = $matrix->getArray()->toArray();
        $color = $this->getColor($this->fill->getForegroundColor());
        for ($y = 0; $y < $matrixSize; $y += 1) {
            for ($x = 0; $x < $matrixSize; $x += 1) {
                if (! $rows[$y][$x]) {
                    continue;
                }

                $points = $this->normalizePoints([
                    ($this->margin + $x) * $pointInPx, ($this->margin + $y) * $pointInPx,
                    ($this->margin + $x + 1) * $pointInPx, ($this->margin + $y) * $pointInPx,
                    ($this->margin + $x + 1) * $pointInPx, ($this->margin + $y + 1) * $pointInPx,
                    ($this->margin + $x) * $pointInPx, ($this->margin + $y + 1) * $pointInPx,
                ]);
                imagefilledpolygon($this->image, $points, $color);
            }
        }
    }

    private function drawEye(int $xOffset, int $yOffset, float $pointInPx, EyeFill $eyeFill): void
    {
        $internalColor = $this->getColor($eyeFill->inheritsInternalColor()
            ? $this->fill->getForegroundColor()
            : $eyeFill->getInternalColor());

        $externalColor = $this->getColor($eyeFill->inheritsExternalColor()
            ? $this->fill->getForegroundColor()
            : $eyeFill->getExternalColor());

        for ($y = 0; $y < 7; $y += 1) {
            for ($x = 0; $x < 7; $x += 1) {
                if ((($y === 1 || $y === 5) && $x > 0 && $x < 6) || (($x === 1 || $x === 5) && $y > 0 && $y < 6)) {
                    continue;
                }

                $points = $this->normalizePoints([
                    ($this->margin + $x + $xOffset) * $pointInPx, ($this->margin + $y + $yOffset) * $pointInPx,
                    ($this->margin + $x + $xOffset + 1) * $pointInPx, ($this->margin + $y + $yOffset) * $pointInPx,
                    ($this->margin + $x + $xOffset + 1) * $pointInPx, ($this->margin + $y + $yOffset + 1) * $pointInPx,
                    ($this->margin + $x + $xOffset) * $pointInPx, ($this->margin + $y + $yOffset + 1) * $pointInPx,
                ]);

                if ($y > 1 && $y < 5 && $x > 1 && $x < 5) {
                    imagefilledpolygon($this->image, $points, $internalColor);
                } else {
                    imagefilledpolygon($this->image, $points, $externalColor);
                }
            }
        }
    }

    /**
     * Normalize points will trim right and bottom line by 1 pixel.
     * Otherwise pixels of neighbors are overlapping which leads to issue with transparency and small QR codes.
     */
    private function normalizePoints(array $points): array
    {
        $maxX = $maxY = 0;
        for ($i = 0; $i < count($points); $i += 2) {
            // Do manual round as GD just removes decimal part
            $points[$i] = $newX = round($points[$i]);
            $points[$i + 1] = $newY = round($points[$i + 1]);

            $maxX = max($maxX, $newX);
            $maxY = max($maxY, $newY);
        }

        // Do trimming only if there are 4 points (8 coordinates), assumes this is square.

        for ($i = 0; $i < count($points); $i += 2) {
            $points[$i] = min($points[$i], $maxX - 1);
            $points[$i + 1] = min($points[$i + 1], $maxY - 1);
        }

        return $points;
    }

    private function renderImage(): string
    {
        ob_start();
        $quality = $this->compressionQuality;
        switch ($this->imageFormat) {
            case 'png':
                if ($quality > 9 || $quality < 0) {
                    $quality = 9;
                }
                imagepng($this->image, null, $quality);
                break;

            case 'gif':
                imagegif($this->image, null);
                break;

            case 'jpeg':
            case 'jpg':
                if ($quality > 100 || $quality < 0) {
                    $quality = 85;
                }
                imagejpeg($this->image, null, $quality);
                break;
            default:
                ob_end_clean();
                throw new InvalidArgumentException(
                    'Supported image formats are jpeg, png and gif, got: ' . $this->imageFormat
                );
        }

        imagedestroy($this->image);
        $this->colors = [];
        $this->image = null;

        return ob_get_clean();
    }

    private function getColor(ColorInterface $color): int
    {
        $alpha = 100;

        if ($color instanceof Alpha) {
            $alpha = $color->getAlpha();
            $color = $color->getBaseColor();
        }

        $rgb = $color->toRgb();

        $colorKey = sprintf('%02X%02X%02X%02X', $rgb->getRed(), $rgb->getGreen(), $rgb->getBlue(), $alpha);

        if (! isset($this->colors[$colorKey])) {
            $colorId = imagecolorallocatealpha(
                $this->image,
                $rgb->getRed(),
                $rgb->getGreen(),
                $rgb->getBlue(),
                (int)((100 - $alpha) / 100 * 127) // Alpha for GD is in range 0 (opaque) - 127 (transparent)
            );

            if ($colorId === false) {
                throw new RuntimeException('Failed to create color: #' . $colorKey);
            }

            $this->colors[$colorKey] = $colorId;
        }

        return $this->colors[$colorKey];
    }
}
