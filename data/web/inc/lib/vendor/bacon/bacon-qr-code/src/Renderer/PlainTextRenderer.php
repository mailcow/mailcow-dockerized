<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer;

use BaconQrCode\Encoder\QrCode;
use BaconQrCode\Exception\InvalidArgumentException;

final class PlainTextRenderer implements RendererInterface
{
    /**
     * UTF-8 full block (U+2588)
     */
    private const FULL_BLOCK = "\xe2\x96\x88";

    /**
     * UTF-8 upper half block (U+2580)
     */
    private const UPPER_HALF_BLOCK = "\xe2\x96\x80";

    /**
     * UTF-8 lower half block (U+2584)
     */
    private const LOWER_HALF_BLOCK = "\xe2\x96\x84";

    /**
     * UTF-8 no-break space (U+00A0)
     */
    private const EMPTY_BLOCK = "\xc2\xa0";

    public function __construct(private readonly int $margin = 2)
    {
    }

    /**
     * @throws InvalidArgumentException if matrix width doesn't match height
     */
    public function render(QrCode $qrCode) : string
    {
        $matrix = $qrCode->getMatrix();
        $matrixSize = $matrix->getWidth();

        if ($matrixSize !== $matrix->getHeight()) {
            throw new InvalidArgumentException('Matrix must have the same width and height');
        }

        $rows = $matrix->getArray()->toArray();

        if (0 !== $matrixSize % 2) {
            $rows[] = array_fill(0, $matrixSize, 0);
        }

        $horizontalMargin = str_repeat(self::EMPTY_BLOCK, $this->margin);
        $result = str_repeat("\n", (int) ceil($this->margin / 2));

        for ($i = 0; $i < $matrixSize; $i += 2) {
            $result .= $horizontalMargin;

            $upperRow = $rows[$i];
            $lowerRow = $rows[$i + 1];

            for ($j = 0; $j < $matrixSize; ++$j) {
                $upperBit = $upperRow[$j];
                $lowerBit = $lowerRow[$j];

                if ($upperBit) {
                    $result .= $lowerBit ? self::FULL_BLOCK : self::UPPER_HALF_BLOCK;
                } else {
                    $result .= $lowerBit ? self::LOWER_HALF_BLOCK : self::EMPTY_BLOCK;
                }
            }

            $result .= $horizontalMargin . "\n";
        }

        $result .= str_repeat("\n", (int) ceil($this->margin / 2));

        return $result;
    }
}
