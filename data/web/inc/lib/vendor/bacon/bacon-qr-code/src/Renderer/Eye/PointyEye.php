<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Eye;

use BaconQrCode\Renderer\Path\Path;

/**
 * Renders the outer eye as solid with a curved corner and inner eye as a circle.
 */
final class PointyEye implements EyeInterface
{
    /**
     * @var self|null
     */
    private static $instance;

    private function __construct()
    {
    }

    public static function instance() : self
    {
        return self::$instance ?: self::$instance = new self();
    }

    public function getExternalPath() : Path
    {
        return (new Path())
            ->move(-3.5, 3.5)
            ->line(-3.5, 0)
            ->ellipticArc(3.5, 3.5, 0, false, true, 0, -3.5)
            ->line(3.5, -3.5)
            ->line(3.5, 3.5)
            ->close()
            ->move(2.5, 0)
            ->ellipticArc(2.5, 2.5, 0, false, true, 0, 2.5)
            ->ellipticArc(2.5, 2.5, 0, false, true, -2.5, 0)
            ->ellipticArc(2.5, 2.5, 0, false, true, 0, -2.5)
            ->ellipticArc(2.5, 2.5, 0, false, true, 2.5, 0)
            ->close()
        ;
    }

    public function getInternalPath() : Path
    {
        return (new Path())
            ->move(1.5, 0)
            ->ellipticArc(1.5, 1.5, 0., false, true, 0., 1.5)
            ->ellipticArc(1.5, 1.5, 0., false, true, -1.5, 0.)
            ->ellipticArc(1.5, 1.5, 0., false, true, 0., -1.5)
            ->ellipticArc(1.5, 1.5, 0., false, true, 1.5, 0.)
            ->close()
        ;
    }
}
