# QR Code generator

[![PHP CI](https://github.com/Bacon/BaconQrCode/actions/workflows/ci.yml/badge.svg)](https://github.com/Bacon/BaconQrCode/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/Bacon/BaconQrCode/branch/master/graph/badge.svg?token=rD0HcAiEEx)](https://codecov.io/gh/Bacon/BaconQrCode)
[![Latest Stable Version](https://poser.pugx.org/bacon/bacon-qr-code/v/stable)](https://packagist.org/packages/bacon/bacon-qr-code)
[![Total Downloads](https://poser.pugx.org/bacon/bacon-qr-code/downloads)](https://packagist.org/packages/bacon/bacon-qr-code)
[![License](https://poser.pugx.org/bacon/bacon-qr-code/license)](https://packagist.org/packages/bacon/bacon-qr-code)


## Introduction
BaconQrCode is a port of QR code portion of the ZXing library. It currently
only features the encoder part, but could later receive the decoder part as
well.

As the Reed Solomon codec implementation of the ZXing library performs quite
slow in PHP, it was exchanged with the implementation by Phil Karn.


## Example usage
```php
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

$renderer = new ImageRenderer(
    new RendererStyle(400),
    new ImagickImageBackEnd()
);
$writer = new Writer($renderer);
$writer->writeFile('Hello World!', 'qrcode.png');
```

## Available image renderer back ends
BaconQrCode comes with multiple back ends for rendering images. Currently included are the following:

- `ImagickImageBackEnd`: renders raster images using the Imagick library
- `SvgImageBackEnd`: renders SVG files using XMLWriter
- `EpsImageBackEnd`: renders EPS files
