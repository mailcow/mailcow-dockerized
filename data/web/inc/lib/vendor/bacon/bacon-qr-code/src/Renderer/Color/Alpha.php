<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Color;

use BaconQrCode\Exception;

final class Alpha implements ColorInterface
{
    /**
     * @param int $alpha the alpha value, 0 to 100
     */
    public function __construct(private readonly int $alpha, private readonly ColorInterface $baseColor)
    {
        if ($alpha < 0 || $alpha > 100) {
            throw new Exception\InvalidArgumentException('Alpha must be between 0 and 100');
        }
    }

    public function getAlpha() : int
    {
        return $this->alpha;
    }

    public function getBaseColor() : ColorInterface
    {
        return $this->baseColor;
    }

    public function toRgb() : Rgb
    {
        return $this->baseColor->toRgb();
    }

    public function toCmyk() : Cmyk
    {
        return $this->baseColor->toCmyk();
    }

    public function toGray() : Gray
    {
        return $this->baseColor->toGray();
    }
}
