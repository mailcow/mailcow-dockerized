<?php

namespace Tests\Providers\Qr;

use PHPUnit\Framework\TestCase;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\TwoFactorAuthException;
use RobThree\Auth\Providers\Qr\HandlesDataUri;

class IQRCodeProviderTest extends TestCase
{
    use HandlesDataUri;

    /**
     * @return void
     */
    public function testTotpUriIsCorrect()
    {
        $qr = new TestQrProvider();

        $tfa = new TwoFactorAuth('Test&Issuer', 6, 30, 'sha1', $qr);
        $data = $this->DecodeDataUri($tfa->getQRCodeImageAsDataUri('Test&Label', 'VMR466AB62ZBOKHE'));
        $this->assertEquals('test/test', $data['mimetype']);
        $this->assertEquals('base64', $data['encoding']);
        $this->assertEquals('otpauth://totp/Test%26Label?secret=VMR466AB62ZBOKHE&issuer=Test%26Issuer&period=30&algorithm=SHA1&digits=6@200', $data['data']);
    }

    /**
     * @return void
     */
    public function testTotpUriIsCorrectNoIssuer()
    {
        $qr = new TestQrProvider();

        /**
         * The library specifies the issuer is null by default however in PHP 8.1
         * there is a deprecation warning for passing null as a string argument to rawurlencode
         */

        $tfa = new TwoFactorAuth(null, 6, 30, 'sha1', $qr);
        $data = $this->DecodeDataUri($tfa->getQRCodeImageAsDataUri('Test&Label', 'VMR466AB62ZBOKHE'));
        $this->assertEquals('test/test', $data['mimetype']);
        $this->assertEquals('base64', $data['encoding']);
        $this->assertEquals('otpauth://totp/Test%26Label?secret=VMR466AB62ZBOKHE&issuer=&period=30&algorithm=SHA1&digits=6@200', $data['data']);
    }

    /**
     * @return void
     */
    public function testGetQRCodeImageAsDataUriThrowsOnInvalidSize()
    {
        $qr = new TestQrProvider();

        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1', $qr);

        $this->expectException(TwoFactorAuthException::class);

        $tfa->getQRCodeImageAsDataUri('Test', 'VMR466AB62ZBOKHE', 0);
    }
}
