<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Path;

final class Close implements OperationInterface
{
    private static ?Close $instance = null;

    private function __construct()
    {
    }

    public static function instance() : self
    {
        return self::$instance ?: self::$instance = new self();
    }

    /**
     * @return self
     */
    public function translate(float $x, float $y) : OperationInterface
    {
        return $this;
    }

    /**
     * @return self
     */
    public function rotate(int $degrees) : OperationInterface
    {
        return $this;
    }
}
