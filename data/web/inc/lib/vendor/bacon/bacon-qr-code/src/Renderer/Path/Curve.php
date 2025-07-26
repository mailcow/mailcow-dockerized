<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Path;

final class Curve implements OperationInterface
{
    public function __construct(
        private readonly float $x1,
        private readonly float $y1,
        private readonly float $x2,
        private readonly float $y2,
        private readonly float $x3,
        private readonly float $y3
    ) {
    }

    public function getX1() : float
    {
        return $this->x1;
    }

    public function getY1() : float
    {
        return $this->y1;
    }

    public function getX2() : float
    {
        return $this->x2;
    }

    public function getY2() : float
    {
        return $this->y2;
    }

    public function getX3() : float
    {
        return $this->x3;
    }

    public function getY3() : float
    {
        return $this->y3;
    }

    /**
     * @return self
     */
    public function translate(float $x, float $y) : OperationInterface
    {
        return new self(
            $this->x1 + $x,
            $this->y1 + $y,
            $this->x2 + $x,
            $this->y2 + $y,
            $this->x3 + $x,
            $this->y3 + $y
        );
    }

    /**
     * @return self
     */
    public function rotate(int $degrees) : OperationInterface
    {
        $radians = deg2rad($degrees);
        $sin = sin($radians);
        $cos = cos($radians);
        $x1r = $this->x1 * $cos - $this->y1 * $sin;
        $y1r = $this->x1 * $sin + $this->y1 * $cos;
        $x2r = $this->x2 * $cos - $this->y2 * $sin;
        $y2r = $this->x2 * $sin + $this->y2 * $cos;
        $x3r = $this->x3 * $cos - $this->y3 * $sin;
        $y3r = $this->x3 * $sin + $this->y3 * $cos;
        return new self(
            $x1r,
            $y1r,
            $x2r,
            $y2r,
            $x3r,
            $y3r
        );
    }
}
