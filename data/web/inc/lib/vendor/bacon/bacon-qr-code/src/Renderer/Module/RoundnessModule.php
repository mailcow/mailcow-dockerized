<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Module;

use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Exception\InvalidArgumentException;
use BaconQrCode\Renderer\Module\EdgeIterator\EdgeIterator;
use BaconQrCode\Renderer\Path\Path;

/**
 * Rounds the corners of module groups.
 */
final class RoundnessModule implements ModuleInterface
{
    public const STRONG = 1;
    public const MEDIUM = .5;
    public const SOFT = .25;

    /**
     * @var float
     */
    private $intensity;

    public function __construct(float $intensity)
    {
        if ($intensity <= 0 || $intensity > 1) {
            throw new InvalidArgumentException('Intensity must between 0 (exclusive) and 1 (inclusive)');
        }

        $this->intensity = $intensity / 2;
    }

    public function createPath(ByteMatrix $matrix) : Path
    {
        $path = new Path();

        foreach (new EdgeIterator($matrix) as $edge) {
            $points = $edge->getSimplifiedPoints();
            $length = count($points);

            $currentPoint = $points[0];
            $nextPoint = $points[1];
            $horizontal = ($currentPoint[1] === $nextPoint[1]);

            if ($horizontal) {
                $right = $nextPoint[0] > $currentPoint[0];
                $path = $path->move(
                    $currentPoint[0] + ($right ? $this->intensity : -$this->intensity),
                    $currentPoint[1]
                );
            } else {
                $up = $nextPoint[0] < $currentPoint[0];
                $path = $path->move(
                    $currentPoint[0],
                    $currentPoint[1] + ($up ? -$this->intensity : $this->intensity)
                );
            }

            for ($i = 1; $i <= $length; ++$i) {
                if ($i === $length) {
                    $previousPoint = $points[$length - 1];
                    $currentPoint = $points[0];
                    $nextPoint = $points[1];
                } else {
                    $previousPoint = $points[(0 === $i ? $length : $i) - 1];
                    $currentPoint = $points[$i];
                    $nextPoint = $points[($length - 1 === $i ? -1 : $i) + 1];
                }

                $horizontal = ($previousPoint[1] === $currentPoint[1]);

                if ($horizontal) {
                    $right = $previousPoint[0] < $currentPoint[0];
                    $up = $nextPoint[1] < $currentPoint[1];
                    $sweep = ($up xor $right);

                    if ($this->intensity < 0.5
                        || ($right && $previousPoint[0] !== $currentPoint[0] - 1)
                        || (! $right && $previousPoint[0] - 1 !== $currentPoint[0])
                    ) {
                        $path = $path->line(
                            $currentPoint[0] + ($right ? -$this->intensity : $this->intensity),
                            $currentPoint[1]
                        );
                    }

                    $path = $path->ellipticArc(
                        $this->intensity,
                        $this->intensity,
                        0,
                        false,
                        $sweep,
                        $currentPoint[0],
                        $currentPoint[1] + ($up ? -$this->intensity : $this->intensity)
                    );
                } else {
                    $up = $previousPoint[1] > $currentPoint[1];
                    $right = $nextPoint[0] > $currentPoint[0];
                    $sweep = ! ($up xor $right);

                    if ($this->intensity < 0.5
                        || ($up && $previousPoint[1] !== $currentPoint[1] + 1)
                        || (! $up && $previousPoint[0] + 1 !== $currentPoint[0])
                    ) {
                        $path = $path->line(
                            $currentPoint[0],
                            $currentPoint[1] + ($up ? $this->intensity : -$this->intensity)
                        );
                    }

                    $path = $path->ellipticArc(
                        $this->intensity,
                        $this->intensity,
                        0,
                        false,
                        $sweep,
                        $currentPoint[0] + ($right ? $this->intensity : -$this->intensity),
                        $currentPoint[1]
                    );
                }
            }

            $path = $path->close();
        }

        return $path;
    }
}
