<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\RendererStyle;

use BaconQrCode\Exception\RuntimeException;
use BaconQrCode\Renderer\Color\ColorInterface;

final class EyeFill
{
    /**
     * @var ColorInterface|null
     */
    private $externalColor;

    /**
     * @var ColorInterface|null
     */
    private $internalColor;

    /**
     * @var self|null
     */
    private static $inherit;

    public function __construct(?ColorInterface $externalColor, ?ColorInterface $internalColor)
    {
        $this->externalColor = $externalColor;
        $this->internalColor = $internalColor;
    }

    public static function uniform(ColorInterface $color) : self
    {
        return new self($color, $color);
    }

    public static function inherit() : self
    {
        return self::$inherit ?: self::$inherit = new self(null, null);
    }

    public function inheritsBothColors() : bool
    {
        return null === $this->externalColor && null === $this->internalColor;
    }

    public function inheritsExternalColor() : bool
    {
        return null === $this->externalColor;
    }

    public function inheritsInternalColor() : bool
    {
        return null === $this->internalColor;
    }

    public function getExternalColor() : ColorInterface
    {
        if (null === $this->externalColor) {
            throw new RuntimeException('External eye color inherits foreground color');
        }

        return $this->externalColor;
    }

    public function getInternalColor() : ColorInterface
    {
        if (null === $this->internalColor) {
            throw new RuntimeException('Internal eye color inherits foreground color');
        }

        return $this->internalColor;
    }
}
