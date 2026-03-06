<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Path;

final class Curve implements OperationInterface
{
    /**
     * @var float
     */
    private $x1;

    /**
     * @var float
     */
    private $y1;

    /**
     * @var float
     */
    private $x2;

    /**
     * @var float
     */
    private $y2;

    /**
     * @var float
     */
    private $x3;

    /**
     * @var float
     */
    private $y3;

    public function __construct(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3)
    {
        $this->x1 = $x1;
        $this->y1 = $y1;
        $this->x2 = $x2;
        $this->y2 = $y2;
        $this->x3 = $x3;
        $this->y3 = $y3;
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
}
