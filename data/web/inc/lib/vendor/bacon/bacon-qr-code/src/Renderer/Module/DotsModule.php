<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Module;

use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Exception\InvalidArgumentException;
use BaconQrCode\Renderer\Path\Path;

/**
 * Renders individual modules as dots.
 */
final class DotsModule implements ModuleInterface
{
    public const LARGE = 1;
    public const MEDIUM = .8;
    public const SMALL = .6;

    public function __construct(private readonly float $size)
    {
        if ($size <= 0 || $size > 1) {
            throw new InvalidArgumentException('Size must between 0 (exclusive) and 1 (inclusive)');
        }
    }

    public function createPath(ByteMatrix $matrix) : Path
    {
        $width = $matrix->getWidth();
        $height = $matrix->getHeight();
        $path = new Path();
        $halfSize = $this->size / 2;
        $margin = (1 - $this->size) / 2;

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                if (! $matrix->get($x, $y)) {
                    continue;
                }

                $pathX = $x + $margin;
                $pathY = $y + $margin;

                $path = $path
                    ->move($pathX + $this->size, $pathY + $halfSize)
                    ->ellipticArc($halfSize, $halfSize, 0, false, true, $pathX + $halfSize, $pathY + $this->size)
                    ->ellipticArc($halfSize, $halfSize, 0, false, true, $pathX, $pathY + $halfSize)
                    ->ellipticArc($halfSize, $halfSize, 0, false, true, $pathX + $halfSize, $pathY)
                    ->ellipticArc($halfSize, $halfSize, 0, false, true, $pathX + $this->size, $pathY + $halfSize)
                    ->close()
                ;
            }
        }

        return $path;
    }
}
