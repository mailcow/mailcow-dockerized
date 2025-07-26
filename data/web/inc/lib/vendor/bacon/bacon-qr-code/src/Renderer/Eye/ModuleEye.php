<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Eye;

use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Renderer\Module\ModuleInterface;
use BaconQrCode\Renderer\Path\Path;

/**
 * Renders an eye based on a module renderer.
 */
final class ModuleEye implements EyeInterface
{
    public function __construct(private readonly ModuleInterface $module)
    {
    }

    public function getExternalPath() : Path
    {
        $matrix = new ByteMatrix(7, 7);

        for ($x = 0; $x < 7; ++$x) {
            $matrix->set($x, 0, 1);
            $matrix->set($x, 6, 1);
        }

        for ($y = 1; $y < 6; ++$y) {
            $matrix->set(0, $y, 1);
            $matrix->set(6, $y, 1);
        }

        return $this->module->createPath($matrix)->translate(-3.5, -3.5);
    }

    public function getInternalPath() : Path
    {
        $matrix = new ByteMatrix(3, 3);

        for ($x = 0; $x < 3; ++$x) {
            for ($y = 0; $y < 3; ++$y) {
                $matrix->set($x, $y, 1);
            }
        }

        return $this->module->createPath($matrix)->translate(-1.5, -1.5);
    }
}
