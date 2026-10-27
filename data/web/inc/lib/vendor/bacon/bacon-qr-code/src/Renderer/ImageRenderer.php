<?php
declare(strict_types = 1);

namespace BaconQrCode\Renderer;

use BaconQrCode\Encoder\MatrixUtil;
use BaconQrCode\Encoder\QrCode;
use BaconQrCode\Exception\InvalidArgumentException;
use BaconQrCode\Renderer\Image\ImageBackEndInterface;
use BaconQrCode\Renderer\Path\Path;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

final class ImageRenderer implements RendererInterface
{
    /**
     * @var RendererStyle
     */
    private $rendererStyle;

    /**
     * @var ImageBackEndInterface
     */
    private $imageBackEnd;

    public function __construct(RendererStyle $rendererStyle, ImageBackEndInterface $imageBackEnd)
    {
        $this->rendererStyle = $rendererStyle;
        $this->imageBackEnd = $imageBackEnd;
    }

    /**
     * @throws InvalidArgumentException if matrix width doesn't match height
     */
    public function render(QrCode $qrCode) : string
    {
        $size = $this->rendererStyle->getSize();
        $margin = $this->rendererStyle->getMargin();
        $matrix = $qrCode->getMatrix();
        $matrixSize = $matrix->getWidth();

        if ($matrixSize !== $matrix->getHeight()) {
            throw new InvalidArgumentException('Matrix must have the same width and height');
        }

        $totalSize = $matrixSize + ($margin * 2);
        $moduleSize = $size / $totalSize;
        $fill = $this->rendererStyle->getFill();

        $this->imageBackEnd->new($size, $fill->getBackgroundColor());
        $this->imageBackEnd->scale((float) $moduleSize);
        $this->imageBackEnd->translate((float) $margin, (float) $margin);

        $module = $this->rendererStyle->getModule();
        $moduleMatrix = clone $matrix;
        MatrixUtil::removePositionDetectionPatterns($moduleMatrix);
        $modulePath = $this->drawEyes($matrixSize, $module->createPath($moduleMatrix));

        if ($fill->hasGradientFill()) {
            $this->imageBackEnd->drawPathWithGradient(
                $modulePath,
                $fill->getForegroundGradient(),
                0,
                0,
                $matrixSize,
                $matrixSize
            );
        } else {
            $this->imageBackEnd->drawPathWithColor($modulePath, $fill->getForegroundColor());
        }

        return $this->imageBackEnd->done();
    }

    private function drawEyes(int $matrixSize, Path $modulePath) : Path
    {
        $fill = $this->rendererStyle->getFill();

        $eye = $this->rendererStyle->getEye();
        $externalPath = $eye->getExternalPath();
        $internalPath = $eye->getInternalPath();

        $modulePath = $this->drawEye(
            $externalPath,
            $internalPath,
            $fill->getTopLeftEyeFill(),
            3.5,
            3.5,
            0,
            $modulePath
        );
        $modulePath = $this->drawEye(
            $externalPath,
            $internalPath,
            $fill->getTopRightEyeFill(),
            $matrixSize - 3.5,
            3.5,
            90,
            $modulePath
        );
        $modulePath = $this->drawEye(
            $externalPath,
            $internalPath,
            $fill->getBottomLeftEyeFill(),
            3.5,
            $matrixSize - 3.5,
            -90,
            $modulePath
        );

        return $modulePath;
    }

    private function drawEye(
        Path $externalPath,
        Path $internalPath,
        EyeFill $fill,
        float $xTranslation,
        float $yTranslation,
        int $rotation,
        Path $modulePath
    ) : Path {
        if ($fill->inheritsBothColors()) {
            return $modulePath
                ->append($externalPath->translate($xTranslation, $yTranslation))
                ->append($internalPath->translate($xTranslation, $yTranslation));
        }

        $this->imageBackEnd->push();
        $this->imageBackEnd->translate($xTranslation, $yTranslation);

        if (0 !== $rotation) {
            $this->imageBackEnd->rotate($rotation);
        }

        if ($fill->inheritsExternalColor()) {
            $modulePath = $modulePath->append($externalPath->translate($xTranslation, $yTranslation));
        } else {
            $this->imageBackEnd->drawPathWithColor($externalPath, $fill->getExternalColor());
        }

        if ($fill->inheritsInternalColor()) {
            $modulePath = $modulePath->append($internalPath->translate($xTranslation, $yTranslation));
        } else {
            $this->imageBackEnd->drawPathWithColor($internalPath, $fill->getInternalColor());
        }

        $this->imageBackEnd->pop();

        return $modulePath;
    }
}
