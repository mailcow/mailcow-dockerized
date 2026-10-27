<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Path;

final class EllipticArc implements OperationInterface
{
    private const ZERO_TOLERANCE = 1e-05;

    /**
     * @var float
     */
    private $xRadius;

    /**
     * @var float
     */
    private $yRadius;

    /**
     * @var float
     */
    private $xAxisAngle;

    /**
     * @var bool
     */
    private $largeArc;

    /**
     * @var bool
     */
    private $sweep;

    /**
     * @var float
     */
    private $x;

    /**
     * @var float
     */
    private $y;

    public function __construct(
        float $xRadius,
        float $yRadius,
        float $xAxisAngle,
        bool $largeArc,
        bool $sweep,
        float $x,
        float $y
    ) {
        $this->xRadius = abs($xRadius);
        $this->yRadius = abs($yRadius);
        $this->xAxisAngle = $xAxisAngle % 360;
        $this->largeArc = $largeArc;
        $this->sweep = $sweep;
        $this->x = $x;
        $this->y = $y;
    }

    public function getXRadius() : float
    {
        return $this->xRadius;
    }

    public function getYRadius() : float
    {
        return $this->yRadius;
    }

    public function getXAxisAngle() : float
    {
        return $this->xAxisAngle;
    }

    public function isLargeArc() : bool
    {
        return $this->largeArc;
    }

    public function isSweep() : bool
    {
        return $this->sweep;
    }

    public function getX() : float
    {
        return $this->x;
    }

    public function getY() : float
    {
        return $this->y;
    }

    /**
     * @return self
     */
    public function translate(float $x, float $y) : OperationInterface
    {
        return new self(
            $this->xRadius,
            $this->yRadius,
            $this->xAxisAngle,
            $this->largeArc,
            $this->sweep,
            $this->x + $x,
            $this->y + $y
        );
    }

    /**
     * Converts the elliptic arc to multiple curves.
     *
     * Since not all image back ends support elliptic arcs, this method allows to convert the arc into multiple curves
     * resembling the same result.
     *
     * @see https://mortoray.com/2017/02/16/rendering-an-svg-elliptical-arc-as-bezier-curves/
     * @return array<Curve|Line>
     */
    public function toCurves(float $fromX, float $fromY) : array
    {
        if (sqrt(($fromX - $this->x) ** 2 + ($fromY - $this->y) ** 2) < self::ZERO_TOLERANCE) {
            return [];
        }

        if ($this->xRadius < self::ZERO_TOLERANCE || $this->yRadius < self::ZERO_TOLERANCE) {
            return [new Line($this->x, $this->y)];
        }

        return $this->createCurves($fromX, $fromY);
    }

    /**
     * @return Curve[]
     */
    private function createCurves(float $fromX, float $fromY) : array
    {
        $xAngle = deg2rad($this->xAxisAngle);
        list($centerX, $centerY, $radiusX, $radiusY, $startAngle, $deltaAngle) =
            $this->calculateCenterPointParameters($fromX, $fromY, $xAngle);

        $s = $startAngle;
        $e = $s + $deltaAngle;
        $sign = ($e < $s) ? -1 : 1;
        $remain = abs($e - $s);
        $p1 = self::point($centerX, $centerY, $radiusX, $radiusY, $xAngle, $s);
        $curves = [];

        while ($remain > self::ZERO_TOLERANCE) {
            $step = min($remain, pi() / 2);
            $signStep = $step * $sign;
            $p2 = self::point($centerX, $centerY, $radiusX, $radiusY, $xAngle, $s + $signStep);

            $alphaT = tan($signStep / 2);
            $alpha = sin($signStep) * (sqrt(4 + 3 * $alphaT ** 2) - 1) / 3;
            $d1 = self::derivative($radiusX, $radiusY, $xAngle, $s);
            $d2 = self::derivative($radiusX, $radiusY, $xAngle, $s + $signStep);

            $curves[] = new Curve(
                $p1[0] + $alpha * $d1[0],
                $p1[1] + $alpha * $d1[1],
                $p2[0] - $alpha * $d2[0],
                $p2[1] - $alpha * $d2[1],
                $p2[0],
                $p2[1]
            );

            $s += $signStep;
            $remain -= $step;
            $p1 = $p2;
        }

        return $curves;
    }

    /**
     * @return float[]
     */
    private function calculateCenterPointParameters(float $fromX, float $fromY, float $xAngle)
    {
        $rX = $this->xRadius;
        $rY = $this->yRadius;

        // F.6.5.1
        $dx2 = ($fromX - $this->x) / 2;
        $dy2 = ($fromY - $this->y) / 2;
        $x1p = cos($xAngle) * $dx2 + sin($xAngle) * $dy2;
        $y1p = -sin($xAngle) * $dx2 + cos($xAngle) * $dy2;

        // F.6.5.2
        $rxs = $rX ** 2;
        $rys = $rY ** 2;
        $x1ps = $x1p ** 2;
        $y1ps = $y1p ** 2;
        $cr = $x1ps / $rxs + $y1ps / $rys;

        if ($cr > 1) {
            $s = sqrt($cr);
            $rX *= $s;
            $rY *= $s;
            $rxs = $rX ** 2;
            $rys = $rY ** 2;
        }

        $dq = ($rxs * $y1ps + $rys * $x1ps);
        $pq = ($rxs * $rys - $dq) / $dq;
        $q = sqrt(max(0, $pq));

        if ($this->largeArc === $this->sweep) {
            $q = -$q;
        }

        $cxp = $q * $rX * $y1p / $rY;
        $cyp = -$q * $rY * $x1p / $rX;

        // F.6.5.3
        $cx = cos($xAngle) * $cxp - sin($xAngle) * $cyp + ($fromX + $this->x) / 2;
        $cy = sin($xAngle) * $cxp + cos($xAngle) * $cyp + ($fromY + $this->y) / 2;

        // F.6.5.5
        $theta = self::angle(1, 0, ($x1p - $cxp) / $rX, ($y1p - $cyp) / $rY);

        // F.6.5.6
        $delta = self::angle(($x1p - $cxp) / $rX, ($y1p - $cyp) / $rY, (-$x1p - $cxp) / $rX, (-$y1p - $cyp) / $rY);
        $delta = fmod($delta, pi() * 2);

        if (! $this->sweep) {
            $delta -= 2 * pi();
        }

        return [$cx, $cy, $rX, $rY, $theta, $delta];
    }

    private static function angle(float $ux, float $uy, float $vx, float $vy) : float
    {
        // F.6.5.4
        $dot = $ux * $vx + $uy * $vy;
        $length = sqrt($ux ** 2 + $uy ** 2) * sqrt($vx ** 2 + $vy ** 2);
        $angle = acos(min(1, max(-1, $dot / $length)));

        if (($ux * $vy - $uy * $vx) < 0) {
            return -$angle;
        }

        return $angle;
    }

    /**
     * @return float[]
     */
    private static function point(
        float $centerX,
        float $centerY,
        float $radiusX,
        float $radiusY,
        float $xAngle,
        float $angle
    ) : array {
        return [
            $centerX + $radiusX * cos($xAngle) * cos($angle) - $radiusY * sin($xAngle) * sin($angle),
            $centerY + $radiusX * sin($xAngle) * cos($angle) + $radiusY * cos($xAngle) * sin($angle),
        ];
    }

    /**
     * @return float[]
     */
    private static function derivative(float $radiusX, float $radiusY, float $xAngle, float $angle) : array
    {
        return [
            -$radiusX * cos($xAngle) * sin($angle) - $radiusY * sin($xAngle) * cos($angle),
            -$radiusX * sin($xAngle) * sin($angle) + $radiusY * cos($xAngle) * cos($angle),
        ];
    }
}
