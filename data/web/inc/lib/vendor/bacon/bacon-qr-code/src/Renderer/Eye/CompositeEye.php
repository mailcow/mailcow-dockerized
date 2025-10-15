<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Eye;

use BaconQrCode\Renderer\Path\Path;

/**
 * Combines the style of two different eyes.
 */
final class CompositeEye implements EyeInterface
{
    public function __construct(private readonly EyeInterface $externalEye, private readonly EyeInterface $internalEye)
    {
    }

    public function getExternalPath() : Path
    {
        return $this->externalEye->getExternalPath();
    }

    public function getInternalPath() : Path
    {
        return $this->internalEye->getInternalPath();
    }
}
