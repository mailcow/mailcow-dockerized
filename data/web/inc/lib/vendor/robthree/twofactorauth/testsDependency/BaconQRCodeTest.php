<?php

namespace TestsDependency;

use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use PHPUnit\Framework\TestCase;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\HandlesDataUri;

class BaconQRCodeTest extends TestCase
{
    use HandlesDataUri;

    public function testDependency()
    {
        // php < 7.1 will install an older Bacon QR Code
        if (! class_exists(ImagickImageBackEnd::class)) {
            $this->expectException(\RuntimeException::class);

            $qr = new BaconQrCodeProvider(1, '#000', '#FFF', 'svg');
        } else {
            $qr = new BaconQrCodeProvider(1, '#000', '#FFF', 'svg');

            $tfa = new TwoFactorAuth('Test&Issuer', 6, 30, 'sha1', $qr);

            $data = $this->DecodeDataUri($tfa->getQRCodeImageAsDataUri('Test&Label', 'VMR466AB62ZBOKHE'));
            $this->assertEquals('image/svg+xml', $data['mimetype']);
        }
    }

    public function testBadTextColour()
    {
        $this->expectException(\RuntimeException::class);

        new BaconQrCodeProvider(1, 'not-a-colour', '#FFF');
    }

    public function testBadBackgroundColour()
    {
        $this->expectException(\RuntimeException::class);

        new BaconQrCodeProvider(1, '#000', 'not-a-colour');
    }

    public function testBadTextColourHexRef()
    {
        $this->expectException(\RuntimeException::class);

        new BaconQrCodeProvider(1, '#AAAA', '#FFF');
    }

    public function testBadBackgroundColourHexRef()
    {
        $this->expectException(\RuntimeException::class);

        new BaconQrCodeProvider(1, '#000', '#AAAA');
    }


}
