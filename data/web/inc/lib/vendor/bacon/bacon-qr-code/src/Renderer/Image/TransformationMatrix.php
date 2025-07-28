<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Image;

final class TransformationMatrix
{
    /**
     * @var float[]
     */
    private array $values;

    public function __construct()
    {
        $this->values = [1, 0, 0, 1, 0, 0];
    }

    public function multiply(self $other) : self
    {
        $matrix = new self();
        $matrix->values[0] = $this->values[0] * $other->values[0] + $this->values[2] * $other->values[1];
        $matrix->values[1] = $this->values[1] * $other->values[0] + $this->values[3] * $other->values[1];
        $matrix->values[2] = $this->values[0] * $other->values[2] + $this->values[2] * $other->values[3];
        $matrix->values[3] = $this->values[1] * $other->values[2] + $this->values[3] * $other->values[3];
        $matrix->values[4] = $this->values[0] * $other->values[4] + $this->values[2] * $other->values[5]
            + $this->values[4];
        $matrix->values[5] = $this->values[1] * $other->values[4] + $this->values[3] * $other->values[5]
            + $this->values[5];

        return $matrix;
    }

    public static function scale(float $size) : self
    {
        $matrix = new self();
        $matrix->values = [$size, 0, 0, $size, 0, 0];
        return $matrix;
    }

    public static function translate(float $x, float $y) : self
    {
        $matrix = new self();
        $matrix->values = [1, 0, 0, 1, $x, $y];
        return $matrix;
    }

    public static function rotate(int $degrees) : self
    {
        $matrix = new self();
        $rad = deg2rad($degrees);
        $matrix->values = [cos($rad), sin($rad), -sin($rad), cos($rad), 0, 0];
        return $matrix;
    }


    /**
     * Applies this matrix onto a point and returns the resulting viewport point.
     *
     * @return float[]
     */
    public function apply(float $x, float $y) : array
    {
        return [
            $x * $this->values[0] + $y * $this->values[2] + $this->values[4],
            $x * $this->values[1] + $y * $this->values[3] + $this->values[5],
        ];
    }
}
