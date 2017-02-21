<?php

/*
 * (c) Jeroen van den Enden <info@endroid.nl>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Endroid\Tests\QrCode;

use Endroid\QrCode\Exceptions\ImageFunctionFailedException;
use Endroid\QrCode\Exceptions\ImageFunctionUnknownException;
use Endroid\QrCode\QrCode;
use PHPUnit_Framework_TestCase;

class QrCodeTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var QrCode
     */
    protected $qrCode;

    /**
     * Tests if a valid data uri is returned.
     */
    public function testGetDataUri()
    {
        $qrCode = $this->getQrCode();
        $dataUri = $qrCode->getDataUri();

        $this->assertTrue(is_string($dataUri));
    }

    /**
     * Tests if a valid image string is returned.
     *
     * @throws ImageFunctionFailedException
     * @throws ImageFunctionUnknownException
     */
    public function testGetImageString()
    {
        $qrCode = $this->getQrCode();
        $imageString = $qrCode->get('png');

        $this->assertTrue(is_string($imageString));
    }

    /**
     * Tests if a valid image string is returned.
     *
     * @throws ImageFunctionFailedException
     * @throws ImageFunctionUnknownException
     */
    public function testGetQrCodeWithLogoString()
    {
        $qrCode = $this->createQrCodeWithLogo();
        $imageString = $qrCode->get('png');

        $this->assertTrue(is_string($imageString));
    }

    /**
     * Returns a QR code.
     */
    protected function getQrCode()
    {
        if (!$this->qrCode) {
            $this->qrCode = $this->createQrCode();
        }

        return $this->qrCode;
    }

    /**
     * Creates a QR code.
     *
     * @return QrCode
     */
    protected function createQrCode()
    {
        $qrCode = new QrCode();
        $qrCode->setText('Life is too short to be generating QR codes');
        $qrCode->setSize(300);

        return $qrCode;
    }

    protected function createQrCodeWithLogo()
    {
        $qrCode = new QrCode();
        $qrCode->setText('Life is too short to be generating QR codes')
        ->setSize(300)
        ->setLogo(dirname(__DIR__).'/assets/image/logo.png')
        ->setLogoSize(60);

        return $qrCode;
    }
}
