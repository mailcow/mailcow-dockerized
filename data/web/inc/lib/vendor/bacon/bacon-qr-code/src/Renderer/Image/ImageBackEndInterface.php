<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer\Image;

use BaconQrCode\Exception\RuntimeException;
use BaconQrCode\Renderer\Color\ColorInterface;
use BaconQrCode\Renderer\Path\Path;
use BaconQrCode\Renderer\RendererStyle\Gradient;

/**
 * Interface for back ends able to to produce path based images.
 */
interface ImageBackEndInterface
{
    /**
     * Starts a new image.
     *
     * If a previous image was already started, previous data get erased.
     */
    public function new(int $size, ColorInterface $backgroundColor) : void;

    /**
     * Transforms all following drawing operation coordinates by scaling them by a given factor.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function scale(float $size) : void;

    /**
     * Transforms all following drawing operation coordinates by translating them by a given amount.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function translate(float $x, float $y) : void;

    /**
     * Transforms all following drawing operation coordinates by rotating them by a given amount.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function rotate(int $degrees) : void;

    /**
     * Pushes the current coordinate transformation onto a stack.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function push() : void;

    /**
     * Pops the last coordinate transformation from a stack.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function pop() : void;

    /**
     * Draws a path with a given color.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function drawPathWithColor(Path $path, ColorInterface $color) : void;

    /**
     * Draws a path with a given gradient which spans the box described by the position and size.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function drawPathWithGradient(
        Path $path,
        Gradient $gradient,
        float $x,
        float $y,
        float $width,
        float $height
    ) : void;

    /**
     * Ends the image drawing operation and returns the resulting blob.
     *
     * This should reset the state of the back end and thus this method should only be callable once per image.
     *
     * @throws RuntimeException if no image was started yet.
     */
    public function done() : string;
}
