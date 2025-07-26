<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\RendererStyle;

use BaconQrCode\Exception\RuntimeException;
use BaconQrCode\Renderer\Color\ColorInterface;
use BaconQrCode\Renderer\Color\Gray;

final class Fill
{
    private static ?Fill $default = null;

    private function __construct(
        private readonly ColorInterface  $backgroundColor,
        private readonly ?ColorInterface $foregroundColor,
        private readonly ?Gradient       $foregroundGradient,
        private readonly EyeFill         $topLeftEyeFill,
        private readonly EyeFill         $topRightEyeFill,
        private readonly EyeFill $bottomLeftEyeFill
    ) {
    }

    public static function default() : self
    {
        return self::$default ?: self::$default = self::uniformColor(new Gray(100), new Gray(0));
    }

    public static function withForegroundColor(
        ColorInterface $backgroundColor,
        ColorInterface $foregroundColor,
        EyeFill $topLeftEyeFill,
        EyeFill $topRightEyeFill,
        EyeFill $bottomLeftEyeFill
    ) : self {
        return new self(
            $backgroundColor,
            $foregroundColor,
            null,
            $topLeftEyeFill,
            $topRightEyeFill,
            $bottomLeftEyeFill
        );
    }

    public static function withForegroundGradient(
        ColorInterface $backgroundColor,
        Gradient $foregroundGradient,
        EyeFill $topLeftEyeFill,
        EyeFill $topRightEyeFill,
        EyeFill $bottomLeftEyeFill
    ) : self {
        return new self(
            $backgroundColor,
            null,
            $foregroundGradient,
            $topLeftEyeFill,
            $topRightEyeFill,
            $bottomLeftEyeFill
        );
    }

    public static function uniformColor(ColorInterface $backgroundColor, ColorInterface $foregroundColor) : self
    {
        return new self(
            $backgroundColor,
            $foregroundColor,
            null,
            EyeFill::inherit(),
            EyeFill::inherit(),
            EyeFill::inherit()
        );
    }

    public static function uniformGradient(ColorInterface $backgroundColor, Gradient $foregroundGradient) : self
    {
        return new self(
            $backgroundColor,
            null,
            $foregroundGradient,
            EyeFill::inherit(),
            EyeFill::inherit(),
            EyeFill::inherit()
        );
    }

    public function hasGradientFill() : bool
    {
        return null !== $this->foregroundGradient;
    }

    public function getBackgroundColor() : ColorInterface
    {
        return $this->backgroundColor;
    }

    public function getForegroundColor() : ColorInterface
    {
        if (null === $this->foregroundColor) {
            throw new RuntimeException('Fill uses a gradient, thus no foreground color is available');
        }

        return $this->foregroundColor;
    }

    public function getForegroundGradient() : Gradient
    {
        if (null === $this->foregroundGradient) {
            throw new RuntimeException('Fill uses a single color, thus no foreground gradient is available');
        }

        return $this->foregroundGradient;
    }

    public function getTopLeftEyeFill() : EyeFill
    {
        return $this->topLeftEyeFill;
    }

    public function getTopRightEyeFill() : EyeFill
    {
        return $this->topRightEyeFill;
    }

    public function getBottomLeftEyeFill() : EyeFill
    {
        return $this->bottomLeftEyeFill;
    }
}
