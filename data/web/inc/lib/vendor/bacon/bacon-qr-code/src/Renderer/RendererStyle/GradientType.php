<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\RendererStyle;

use DASPRiD\Enum\AbstractEnum;

/**
 * @method static self VERTICAL()
 * @method static self HORIZONTAL()
 * @method static self DIAGONAL()
 * @method static self INVERSE_DIAGONAL()
 * @method static self RADIAL()
 */
final class GradientType extends AbstractEnum
{
    protected const VERTICAL = null;
    protected const HORIZONTAL = null;
    protected const DIAGONAL = null;
    protected const INVERSE_DIAGONAL = null;
    protected const RADIAL = null;
}
