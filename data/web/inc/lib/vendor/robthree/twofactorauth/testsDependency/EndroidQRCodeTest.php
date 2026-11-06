<?php

namespace TestsDependency;

use PHPUnit\Framework\TestCase;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\Providers\Qr\HandlesDataUri;

class EndroidQRCodeTest extends TestCase
{
    use HandlesDataUri;

    public function testDependency()
    {
        $qr = new EndroidQrCodeProvider();
        $tfa = new TwoFactorAuth('Test&Issuer', 6, 30, 'sha1', $qr);
        $data = $this->DecodeDataUri($tfa->getQRCodeImageAsDataUri('Test&Label', 'VMR466AB62ZBOKHE'));
        $this->assertEquals('image/png', $data['mimetype']);
        $this->assertEquals('base64', $data['encoding']);
        $this->assertNotEmpty($data['data']);

    }
}
