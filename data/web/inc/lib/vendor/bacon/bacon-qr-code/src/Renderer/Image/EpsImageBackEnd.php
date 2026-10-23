<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Image;

use BaconQrCode\Exception\RuntimeException;
use BaconQrCode\Renderer\Color\Alpha;
use BaconQrCode\Renderer\Color\Cmyk;
use BaconQrCode\Renderer\Color\ColorInterface;
use BaconQrCode\Renderer\Color\Gray;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Path\Close;
use BaconQrCode\Renderer\Path\Curve;
use BaconQrCode\Renderer\Path\EllipticArc;
use BaconQrCode\Renderer\Path\Line;
use BaconQrCode\Renderer\Path\Move;
use BaconQrCode\Renderer\Path\Path;
use BaconQrCode\Renderer\RendererStyle\Gradient;
use BaconQrCode\Renderer\RendererStyle\GradientType;

final class EpsImageBackEnd implements ImageBackEndInterface
{
    private const PRECISION = 3;

    /**
     * @var string|null
     */
    private $eps;

    public function new(int $size, ColorInterface $backgroundColor) : void
    {
        $this->eps = "%!PS-Adobe-3.0 EPSF-3.0\n"
            . "%%Creator: BaconQrCode\n"
            . sprintf("%%%%BoundingBox: 0 0 %d %d \n", $size, $size)
            . "%%BeginProlog\n"
            . "save\n"
            . "50 dict begin\n"
            . "/q { gsave } bind def\n"
            . "/Q { grestore } bind def\n"
            . "/s { scale } bind def\n"
            . "/t { translate } bind def\n"
            . "/r { rotate } bind def\n"
            . "/n { newpath } bind def\n"
            . "/m { moveto } bind def\n"
            . "/l { lineto } bind def\n"
            . "/c { curveto } bind def\n"
            . "/z { closepath } bind def\n"
            . "/f { eofill } bind def\n"
            . "/rgb { setrgbcolor } bind def\n"
            . "/cmyk { setcmykcolor } bind def\n"
            . "/gray { setgray } bind def\n"
            . "%%EndProlog\n"
            . "1 -1 s\n"
            . sprintf("0 -%d t\n", $size);

        if ($backgroundColor instanceof Alpha && 0 === $backgroundColor->getAlpha()) {
            return;
        }

        $this->eps .= wordwrap(
            '0 0 m'
            . sprintf(' %s 0 l', (string) $size)
            . sprintf(' %s %s l', (string) $size, (string) $size)
            . sprintf(' 0 %s l', (string) $size)
            . ' z'
            . ' ' .$this->getColorSetString($backgroundColor) . " f\n",
            75,
            "\n "
        );
    }

    public function scale(float $size) : void
    {
        if (null === $this->eps) {
            throw new RuntimeException('No image has been started');
        }

        $this->eps .= sprintf("%1\$s %1\$s s\n", round($size, self::PRECISION));
    }

    public function translate(float $x, float $y) : void
    {
        if (null === $this->eps) {
            throw new RuntimeException('No image has been started');
        }

        $this->eps .= sprintf("%s %s t\n", round($x, self::PRECISION), round($y, self::PRECISION));
    }

    public function rotate(int $degrees) : void
    {
        if (null === $this->eps) {
            throw new RuntimeException('No image has been started');
        }

        $this->eps .= sprintf("%d r\n", $degrees);
    }

    public function push() : void
    {
        if (null === $this->eps) {
            throw new RuntimeException('No image has been started');
        }

        $this->eps .= "q\n";
    }

    public function pop() : void
    {
        if (null === $this->eps) {
            throw new RuntimeException('No image has been started');
        }

        $this->eps .= "Q\n";
    }

    public function drawPathWithColor(Path $path, ColorInterface $color) : void
    {
        if (null === $this->eps) {
            throw new RuntimeException('No image has been started');
        }

        $fromX = 0;
        $fromY = 0;
        $this->eps .= wordwrap(
            'n '
            . $this->drawPathOperations($path, $fromX, $fromY)
            . ' ' . $this->getColorSetString($color) . " f\n",
            75,
            "\n "
        );
    }

    public function drawPathWithGradient(
        Path $path,
        Gradient $gradient,
        float $x,
        float $y,
        float $width,
        float $height
    ) : void {
        if (null === $this->eps) {
            throw new RuntimeException('No image has been started');
        }

        $fromX = 0;
        $fromY = 0;
        $this->eps .= wordwrap(
            'q n ' . $this->drawPathOperations($path, $fromX, $fromY) . "\n",
            75,
            "\n "
        );

        $this->createGradientFill($gradient, $x, $y, $width, $height);
    }

    public function done() : string
    {
        if (null === $this->eps) {
            throw new RuntimeException('No image has been started');
        }

        $this->eps .= "%%TRAILER\nend restore\n%%EOF";
        $blob = $this->eps;
        $this->eps = null;

        return $blob;
    }

    private function drawPathOperations(Iterable $ops, &$fromX, &$fromY) : string
    {
        $pathData = [];

        foreach ($ops as $op) {
            switch (true) {
                case $op instanceof Move:
                    $fromX = $toX = round($op->getX(), self::PRECISION);
                    $fromY = $toY = round($op->getY(), self::PRECISION);
                    $pathData[] = sprintf('%s %s m', $toX, $toY);
                    break;

                case $op instanceof Line:
                    $fromX = $toX = round($op->getX(), self::PRECISION);
                    $fromY = $toY = round($op->getY(), self::PRECISION);
                    $pathData[] = sprintf('%s %s l', $toX, $toY);
                    break;

                case $op instanceof EllipticArc:
                    $pathData[] = $this->drawPathOperations($op->toCurves($fromX, $fromY), $fromX, $fromY);
                    break;

                case $op instanceof Curve:
                    $x1 = round($op->getX1(), self::PRECISION);
                    $y1 = round($op->getY1(), self::PRECISION);
                    $x2 = round($op->getX2(), self::PRECISION);
                    $y2 = round($op->getY2(), self::PRECISION);
                    $fromX = $x3 = round($op->getX3(), self::PRECISION);
                    $fromY = $y3 = round($op->getY3(), self::PRECISION);
                    $pathData[] = sprintf('%s %s %s %s %s %s c', $x1, $y1, $x2, $y2, $x3, $y3);
                    break;

                case $op instanceof Close:
                    $pathData[] = 'z';
                    break;

                default:
                    throw new RuntimeException('Unexpected draw operation: ' . get_class($op));
            }
        }

        return implode(' ', $pathData);
    }

    private function createGradientFill(Gradient $gradient, float $x, float $y, float $width, float $height) : void
    {
        $startColor = $gradient->getStartColor();
        $endColor = $gradient->getEndColor();

        if ($startColor instanceof Alpha) {
            $startColor = $startColor->getBaseColor();
        }

        $startColorType = get_class($startColor);

        if (! in_array($startColorType, [Rgb::class, Cmyk::class, Gray::class])) {
            $startColorType = Cmyk::class;
            $startColor = $startColor->toCmyk();
        }

        if (get_class($endColor) !== $startColorType) {
            switch ($startColorType) {
                case Cmyk::class:
                    $endColor = $endColor->toCmyk();
                    break;

                case Rgb::class:
                    $endColor = $endColor->toRgb();
                    break;

                case Gray::class:
                    $endColor = $endColor->toGray();
                    break;
            }
        }

        $this->eps .= "eoclip\n<<\n";

        if ($gradient->getType() === GradientType::RADIAL()) {
            $this->eps .= " /ShadingType 3\n";
        } else {
            $this->eps .= " /ShadingType 2\n";
        }

        $this->eps .= " /Extend [ true true ]\n"
            . " /AntiAlias true\n";

        switch ($startColorType) {
            case Cmyk::class:
                $this->eps .= " /ColorSpace /DeviceCMYK\n";
                break;

            case Rgb::class:
                $this->eps .= " /ColorSpace /DeviceRGB\n";
                break;

            case Gray::class:
                $this->eps .= " /ColorSpace /DeviceGray\n";
                break;
        }

        switch ($gradient->getType()) {
            case GradientType::HORIZONTAL():
                $this->eps .= sprintf(
                    " /Coords [ %s %s %s %s ]\n",
                    round($x, self::PRECISION),
                    round($y, self::PRECISION),
                    round($x + $width, self::PRECISION),
                    round($y, self::PRECISION)
                );
                break;

            case GradientType::VERTICAL():
                $this->eps .= sprintf(
                    " /Coords [ %s %s %s %s ]\n",
                    round($x, self::PRECISION),
                    round($y, self::PRECISION),
                    round($x, self::PRECISION),
                    round($y + $height, self::PRECISION)
                );
                break;

            case GradientType::DIAGONAL():
                $this->eps .= sprintf(
                    " /Coords [ %s %s %s %s ]\n",
                    round($x, self::PRECISION),
                    round($y, self::PRECISION),
                    round($x + $width, self::PRECISION),
                    round($y + $height, self::PRECISION)
                );
                break;

            case GradientType::INVERSE_DIAGONAL():
                $this->eps .= sprintf(
                    " /Coords [ %s %s %s %s ]\n",
                    round($x, self::PRECISION),
                    round($y + $height, self::PRECISION),
                    round($x + $width, self::PRECISION),
                    round($y, self::PRECISION)
                );
                break;

            case GradientType::RADIAL():
                $centerX = ($x + $width) / 2;
                $centerY = ($y + $height) / 2;

                $this->eps .= sprintf(
                    " /Coords [ %s %s 0 %s %s %s ]\n",
                    round($centerX, self::PRECISION),
                    round($centerY, self::PRECISION),
                    round($centerX, self::PRECISION),
                    round($centerY, self::PRECISION),
                    round(max($width, $height) / 2, self::PRECISION)
                );
                break;
        }

        $this->eps .= " /Function\n"
            . " <<\n"
            . "  /FunctionType 2\n"
            . "  /Domain [ 0 1 ]\n"
            . sprintf("  /C0 [ %s ]\n", $this->getColorString($startColor))
            . sprintf("  /C1 [ %s ]\n", $this->getColorString($endColor))
            . "  /N 1\n"
            . " >>\n>>\nshfill\nQ\n";
    }

    private function getColorSetString(ColorInterface $color) : string
    {
        if ($color instanceof Rgb) {
            return $this->getColorString($color) . ' rgb';
        }

        if ($color instanceof Cmyk) {
            return $this->getColorString($color) . ' cmyk';
        }

        if ($color instanceof Gray) {
            return $this->getColorString($color) . ' gray';
        }

        return $this->getColorSetString($color->toCmyk());
    }

    private function getColorString(ColorInterface $color) : string
    {
        if ($color instanceof Rgb) {
            return sprintf('%s %s %s', $color->getRed() / 255, $color->getGreen() / 255, $color->getBlue() / 255);
        }

        if ($color instanceof Cmyk) {
            return sprintf(
                '%s %s %s %s',
                $color->getCyan() / 100,
                $color->getMagenta() / 100,
                $color->getYellow() / 100,
                $color->getBlack() / 100
            );
        }

        if ($color instanceof Gray) {
            return sprintf('%s', $color->getGray() / 100);
        }

        return $this->getColorString($color->toCmyk());
    }
}
