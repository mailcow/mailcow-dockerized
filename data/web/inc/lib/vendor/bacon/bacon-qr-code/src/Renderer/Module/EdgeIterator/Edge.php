<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Module\EdgeIterator;

final class Edge
{
    /**
     * @var bool
     */
    private $positive;

    /**
     * @var array<int[]>
     */
    private $points = [];

    /**
     * @var array<int[]>|null
     */
    private $simplifiedPoints;

    /**
     * @var int
     */
    private $minX = PHP_INT_MAX;

    /**
     * @var int
     */
    private $minY = PHP_INT_MAX;

    /**
     * @var int
     */
    private $maxX = -1;

    /**
     * @var int
     */
    private $maxY = -1;

    public function __construct(bool $positive)
    {
        $this->positive = $positive;
    }

    public function addPoint(int $x, int $y) : void
    {
        $this->points[] = [$x, $y];
        $this->minX = min($this->minX, $x);
        $this->minY = min($this->minY, $y);
        $this->maxX = max($this->maxX, $x);
        $this->maxY = max($this->maxY, $y);
    }

    public function isPositive() : bool
    {
        return $this->positive;
    }

    /**
     * @return array<int[]>
     */
    public function getPoints() : array
    {
        return $this->points;
    }

    public function getMaxX() : int
    {
        return $this->maxX;
    }

    public function getSimplifiedPoints() : array
    {
        if (null !== $this->simplifiedPoints) {
            return $this->simplifiedPoints;
        }

        $points = [];
        $length = count($this->points);

        for ($i = 0; $i < $length; ++$i) {
            $previousPoint = $this->points[(0 === $i ? $length : $i) - 1];
            $nextPoint = $this->points[($length - 1 === $i ? -1 : $i) + 1];
            $currentPoint = $this->points[$i];

            if (($previousPoint[0] === $currentPoint[0] && $currentPoint[0] === $nextPoint[0])
                || ($previousPoint[1] === $currentPoint[1] && $currentPoint[1] === $nextPoint[1])
            ) {
                continue;
            }

            $points[] = $currentPoint;
        }

        return $this->simplifiedPoints = $points;
    }
}
