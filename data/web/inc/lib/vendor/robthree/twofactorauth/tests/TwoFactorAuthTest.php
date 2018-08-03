<?php
require_once 'lib/TwoFactorAuth.php';
require_once 'lib/TwoFactorAuthException.php';

require_once 'lib/Providers/Qr/IQRCodeProvider.php';
require_once 'lib/Providers/Qr/BaseHTTPQRCodeProvider.php';
require_once 'lib/Providers/Qr/GoogleQRCodeProvider.php';
require_once 'lib/Providers/Qr/QRException.php';

require_once 'lib/Providers/Rng/IRNGProvider.php';
require_once 'lib/Providers/Rng/RNGException.php';
require_once 'lib/Providers/Rng/CSRNGProvider.php';
require_once 'lib/Providers/Rng/MCryptRNGProvider.php';
require_once 'lib/Providers/Rng/OpenSSLRNGProvider.php';
require_once 'lib/Providers/Rng/HashRNGProvider.php';
require_once 'lib/Providers/Rng/RNGException.php';

require_once 'lib/Providers/Time/ITimeProvider.php';
require_once 'lib/Providers/Time/LocalMachineTimeProvider.php';
require_once 'lib/Providers/Time/HttpTimeProvider.php';
require_once 'lib/Providers/Time/NTPTimeProvider.php';
require_once 'lib/Providers/Time/TimeException.php';

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;
use RobThree\Auth\Providers\Rng\IRNGProvider;
use RobThree\Auth\Providers\Time\ITimeProvider;


class TwoFactorAuthTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \RobThree\Auth\TwoFactorAuthException
     */
    public function testConstructorThrowsOnInvalidDigits() {

        new TwoFactorAuth('Test', 0);
    }

    /**
     * @expectedException \RobThree\Auth\TwoFactorAuthException
     */
    public function testConstructorThrowsOnInvalidPeriod() {

        new TwoFactorAuth('Test', 6, 0);
    }

    /**
     * @expectedException \RobThree\Auth\TwoFactorAuthException
     */
    public function testConstructorThrowsOnInvalidAlgorithm() {

        new TwoFactorAuth('Test', 6, 30, 'xxx');
    }

    public function testGetCodeReturnsCorrectResults() {

        $tfa = new TwoFactorAuth('Test');
        $this->assertEquals('543160', $tfa->getCode('VMR466AB62ZBOKHE', 1426847216));
        $this->assertEquals('538532', $tfa->getCode('VMR466AB62ZBOKHE', 0));
    }

    /**
     * @expectedException \RobThree\Auth\TwoFactorAuthException
     */
    public function testCreateSecretThrowsOnInsecureRNGProvider() {
        $rng = new TestRNGProvider();

        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1', null, $rng);
        $tfa->createSecret();
    }

    public function testCreateSecretOverrideSecureDoesNotThrowOnInsecureRNG() {
        $rng = new TestRNGProvider();

        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1', null, $rng);
        $this->assertEquals('ABCDEFGHIJKLMNOP', $tfa->createSecret(80, false));
    }

    public function testCreateSecretDoesNotThrowOnSecureRNGProvider() {
        $rng = new TestRNGProvider(true);

        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1', null, $rng);
        $this->assertEquals('ABCDEFGHIJKLMNOP', $tfa->createSecret());
    }

    public function testCreateSecretGeneratesDesiredAmountOfEntropy() {
        $rng = new TestRNGProvider(true);

        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1', null, $rng);
        $this->assertEquals('A', $tfa->createSecret(5));
        $this->assertEquals('AB', $tfa->createSecret(6));
        $this->assertEquals('ABCDEFGHIJKLMNOPQRSTUVWXYZ', $tfa->createSecret(128));
        $this->assertEquals('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $tfa->createSecret(160));
        $this->assertEquals('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $tfa->createSecret(320));
        $this->assertEquals('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567ABCDEFGHIJKLMNOPQRSTUVWXYZ234567A', $tfa->createSecret(321));
    }

    public function testEnsureCorrectTimeDoesNotThrowForCorrectTime() {
        $tpr1 = new TestTimeProvider(123);
        $tpr2 = new TestTimeProvider(128);

        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1', null, null, $tpr1);
        $tfa->ensureCorrectTime(array($tpr2));   // 128 - 123 = 5 => within default leniency
    }

    /**
     * @expectedException \RobThree\Auth\TwoFactorAuthException
     */
    public function testEnsureCorrectTimeThrowsOnIncorrectTime() {
        $tpr1 = new TestTimeProvider(123);
        $tpr2 = new TestTimeProvider(124);

        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1', null, null, $tpr1);
        $tfa->ensureCorrectTime(array($tpr2), 0);    // We force a leniency of 0, 124-123 = 1 so this should throw
    }


    public function testEnsureDefaultTimeProviderReturnsCorrectTime() {
        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1');
        $tfa->ensureCorrectTime(array(new TestTimeProvider(time())), 1);    // Use a leniency of 1, should the time change between both time() calls
    }

    public function testEnsureAllTimeProvidersReturnCorrectTime() {
        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1');
        $tfa->ensureCorrectTime(array(
            new RobThree\Auth\Providers\Time\NTPTimeProvider(),                         // Uses pool.ntp.org by default
            //new RobThree\Auth\Providers\Time\NTPTimeProvider('time.google.com'),      // Somehow time.google.com and time.windows.com make travis timeout??
            new RobThree\Auth\Providers\Time\HttpTimeProvider(),                        // Uses google.com by default
            new RobThree\Auth\Providers\Time\HttpTimeProvider('https://github.com'),
            new RobThree\Auth\Providers\Time\HttpTimeProvider('https://yahoo.com'),
        ));
    }

    public function testVerifyCodeWorksCorrectly() {

        $tfa = new TwoFactorAuth('Test', 6, 30);
        $this->assertEquals(true , $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 1, 1426847190));
        $this->assertEquals(true , $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 0, 1426847190 + 29));	//Test discrepancy
        $this->assertEquals(false, $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 0, 1426847190 + 30));	//Test discrepancy
        $this->assertEquals(false, $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 0, 1426847190 - 1));	//Test discrepancy

        $this->assertEquals(true , $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 1, 1426847205 + 0));	//Test discrepancy
        $this->assertEquals(true , $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 1, 1426847205 + 35));	//Test discrepancy
        $this->assertEquals(true , $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 1, 1426847205 - 35));	//Test discrepancy

        $this->assertEquals(false, $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 1, 1426847205 + 65));	//Test discrepancy
        $this->assertEquals(false, $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 1, 1426847205 - 65));	//Test discrepancy

        $this->assertEquals(true , $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 2, 1426847205 + 65));	//Test discrepancy
        $this->assertEquals(true , $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 2, 1426847205 - 65));	//Test discrepancy
    }

    public function testVerifyCorrectTimeSliceIsReturned() {
        $tfa = new TwoFactorAuth('Test', 6, 30);

        // We test with discrepancy 3 (so total of 7 codes: c-3, c-2, c-1, c, c+1, c+2, c+3
        // Ensure each corresponding timeslice is returned correctly
        $this->assertEquals(true, $tfa->verifyCode('VMR466AB62ZBOKHE', '534113', 3, 1426847190, $timeslice1));
        $this->assertEquals(47561570, $timeslice1);
        $this->assertEquals(true, $tfa->verifyCode('VMR466AB62ZBOKHE', '819652', 3, 1426847190, $timeslice2));
        $this->assertEquals(47561571, $timeslice2);
        $this->assertEquals(true, $tfa->verifyCode('VMR466AB62ZBOKHE', '915954', 3, 1426847190, $timeslice3));
        $this->assertEquals(47561572, $timeslice3);
        $this->assertEquals(true, $tfa->verifyCode('VMR466AB62ZBOKHE', '543160', 3, 1426847190, $timeslice4));
        $this->assertEquals(47561573, $timeslice4);
        $this->assertEquals(true, $tfa->verifyCode('VMR466AB62ZBOKHE', '348401', 3, 1426847190, $timeslice5));
        $this->assertEquals(47561574, $timeslice5);
        $this->assertEquals(true, $tfa->verifyCode('VMR466AB62ZBOKHE', '648525', 3, 1426847190, $timeslice6));
        $this->assertEquals(47561575, $timeslice6);
        $this->assertEquals(true, $tfa->verifyCode('VMR466AB62ZBOKHE', '170645', 3, 1426847190, $timeslice7));
        $this->assertEquals(47561576, $timeslice7);

        // Incorrect code should return false and a 0 timeslice
        $this->assertEquals(false, $tfa->verifyCode('VMR466AB62ZBOKHE', '111111', 3, 1426847190, $timeslice8));
        $this->assertEquals(0, $timeslice8);
    }

    public function testTotpUriIsCorrect() {
        $qr = new TestQrProvider();

        $tfa = new TwoFactorAuth('Test&Issuer', 6, 30, 'sha1', $qr);
        $data = $this->DecodeDataUri($tfa->getQRCodeImageAsDataUri('Test&Label', 'VMR466AB62ZBOKHE'));
        $this->assertEquals('test/test', $data['mimetype']);
        $this->assertEquals('base64', $data['encoding']);
        $this->assertEquals('otpauth://totp/Test%26Label?secret=VMR466AB62ZBOKHE&issuer=Test%26Issuer&period=30&algorithm=SHA1&digits=6@200', $data['data']);
    }

    /**
     * @expectedException \RobThree\Auth\TwoFactorAuthException
     */
    public function testGetQRCodeImageAsDataUriThrowsOnInvalidSize() {
        $qr = new TestQrProvider();

        $tfa = new TwoFactorAuth('Test', 6, 30, 'sha1', $qr);
        $tfa->getQRCodeImageAsDataUri('Test', 'VMR466AB62ZBOKHE', 0);
    }

    /**
     * @expectedException \RobThree\Auth\TwoFactorAuthException
     */
    public function testGetCodeThrowsOnInvalidBase32String1() {
        $tfa = new TwoFactorAuth('Test');
        $tfa->getCode('FOO1BAR8BAZ9');    //1, 8 & 9 are invalid chars
    }

    /**
     * @expectedException \RobThree\Auth\TwoFactorAuthException
     */
    public function testGetCodeThrowsOnInvalidBase32String2() {
        $tfa = new TwoFactorAuth('Test');
        $tfa->getCode('mzxw6===');        //Lowercase
    }

    public function testKnownBase32DecodeTestVectors() {
        // We usually don't test internals (e.g. privates) but since we rely heavily on base32 decoding and don't want
        // to expose this method nor do we want to give people the possibility of implementing / providing their own base32
        // decoding/decoder (as we do with Rng/QR providers for example) we simply test the private base32Decode() method
        // with some known testvectors **only** to ensure base32 decoding works correctly following RFC's so there won't
        // be any bugs hiding in there. We **could** 'fool' ourselves by calling the public getCode() method (which uses
        // base32decode internally) and then make sure getCode's output (in digits) equals expected output since that would
        // mean the base32Decode() works as expected but that **could** hide some subtle bug(s) in decoding the base32 string.

        // "In general, you don't want to break any encapsulation for the sake of testing (or as Mom used to say, "don't
        // expose your privates!"). Most of the time, you should be able to test a class by exercising its public methods."
        //                                                           Dave Thomas and Andy Hunt -- "Pragmatic Unit Testing
        $tfa = new TwoFactorAuth('Test');

        $method = new ReflectionMethod('RobThree\Auth\TwoFactorAuth', 'base32Decode');
        $method->setAccessible(true);

        // Test vectors from: https://tools.ietf.org/html/rfc4648#page-12
        $this->assertEquals('', $method->invoke($tfa, ''));
        $this->assertEquals('f', $method->invoke($tfa, 'MY======'));
        $this->assertEquals('fo', $method->invoke($tfa, 'MZXQ===='));
        $this->assertEquals('foo', $method->invoke($tfa, 'MZXW6==='));
        $this->assertEquals('foob', $method->invoke($tfa, 'MZXW6YQ='));
        $this->assertEquals('fooba', $method->invoke($tfa, 'MZXW6YTB'));
        $this->assertEquals('foobar', $method->invoke($tfa, 'MZXW6YTBOI======'));
    }

    public function testKnownBase32DecodeUnpaddedTestVectors() {
        // See testKnownBase32DecodeTestVectors() for the rationale behind testing the private base32Decode() method.
        // This test ensures that strings without the padding-char ('=') are also decoded correctly.
        // https://tools.ietf.org/html/rfc4648#page-4:
        //   "In some circumstances, the use of padding ("=") in base-encoded data is not required or used."
        $tfa = new TwoFactorAuth('Test');

        $method = new ReflectionMethod('RobThree\Auth\TwoFactorAuth', 'base32Decode');
        $method->setAccessible(true);

        // Test vectors from: https://tools.ietf.org/html/rfc4648#page-12
        $this->assertEquals('', $method->invoke($tfa, ''));
        $this->assertEquals('f', $method->invoke($tfa, 'MY'));
        $this->assertEquals('fo', $method->invoke($tfa, 'MZXQ'));
        $this->assertEquals('foo', $method->invoke($tfa, 'MZXW6'));
        $this->assertEquals('foob', $method->invoke($tfa, 'MZXW6YQ'));
        $this->assertEquals('fooba', $method->invoke($tfa, 'MZXW6YTB'));
        $this->assertEquals('foobar', $method->invoke($tfa, 'MZXW6YTBOI'));
    }


    public function testKnownTestVectors_sha1() {
        //Known test vectors for SHA1: https://tools.ietf.org/html/rfc6238#page-15
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';   //== base32encode('12345678901234567890')
        $tfa = new TwoFactorAuth('Test', 8, 30, 'sha1');
        $this->assertEquals('94287082', $tfa->getCode($secret, 59));
        $this->assertEquals('07081804', $tfa->getCode($secret, 1111111109));
        $this->assertEquals('14050471', $tfa->getCode($secret, 1111111111));
        $this->assertEquals('89005924', $tfa->getCode($secret, 1234567890));
        $this->assertEquals('69279037', $tfa->getCode($secret, 2000000000));
        $this->assertEquals('65353130', $tfa->getCode($secret, 20000000000));
    }

    public function testKnownTestVectors_sha256() {
        //Known test vectors for SHA256: https://tools.ietf.org/html/rfc6238#page-15
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZA';   //== base32encode('12345678901234567890123456789012')
        $tfa = new TwoFactorAuth('Test', 8, 30, 'sha256');
        $this->assertEquals('46119246', $tfa->getCode($secret, 59));
        $this->assertEquals('68084774', $tfa->getCode($secret, 1111111109));
        $this->assertEquals('67062674', $tfa->getCode($secret, 1111111111));
        $this->assertEquals('91819424', $tfa->getCode($secret, 1234567890));
        $this->assertEquals('90698825', $tfa->getCode($secret, 2000000000));
        $this->assertEquals('77737706', $tfa->getCode($secret, 20000000000));
    }

    public function testKnownTestVectors_sha512() {
        //Known test vectors for SHA512: https://tools.ietf.org/html/rfc6238#page-15
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNA';   //== base32encode('1234567890123456789012345678901234567890123456789012345678901234')
        $tfa = new TwoFactorAuth('Test', 8, 30, 'sha512');
        $this->assertEquals('90693936', $tfa->getCode($secret, 59));
        $this->assertEquals('25091201', $tfa->getCode($secret, 1111111109));
        $this->assertEquals('99943326', $tfa->getCode($secret, 1111111111));
        $this->assertEquals('93441116', $tfa->getCode($secret, 1234567890));
        $this->assertEquals('38618901', $tfa->getCode($secret, 2000000000));
        $this->assertEquals('47863826', $tfa->getCode($secret, 20000000000));
    }

    /**
     * @requires function random_bytes
     */
    public function testCSRNGProvidersReturnExpectedNumberOfBytes() {
        $rng = new \RobThree\Auth\Providers\Rng\CSRNGProvider();
        foreach ($this->getRngTestLengths() as $l)
            $this->assertEquals($l, strlen($rng->getRandomBytes($l)));
        $this->assertEquals(true, $rng->isCryptographicallySecure());
    }

    /**
     * @requires function hash_algos
     * @requires function hash
     */
    public function testHashRNGProvidersReturnExpectedNumberOfBytes() {
        $rng = new \RobThree\Auth\Providers\Rng\HashRNGProvider();
        foreach ($this->getRngTestLengths() as $l)
            $this->assertEquals($l, strlen($rng->getRandomBytes($l)));
        $this->assertEquals(false, $rng->isCryptographicallySecure());
    }

    /**
     * @requires function mcrypt_create_iv
     */
    public function testMCryptRNGProvidersReturnExpectedNumberOfBytes() {
        if (function_exists('mcrypt_create_iv')) {
            $rng = new \RobThree\Auth\Providers\Rng\MCryptRNGProvider();
            foreach ($this->getRngTestLengths() as $l)
                $this->assertEquals($l, strlen($rng->getRandomBytes($l)));
            $this->assertEquals(true, $rng->isCryptographicallySecure());
        }
    }

    /**
     * @requires function openssl_random_pseudo_bytes
     */
    public function testStrongOpenSSLRNGProvidersReturnExpectedNumberOfBytes() {
        $rng = new \RobThree\Auth\Providers\Rng\OpenSSLRNGProvider(true);
        foreach ($this->getRngTestLengths() as $l)
            $this->assertEquals($l, strlen($rng->getRandomBytes($l)));
        $this->assertEquals(true, $rng->isCryptographicallySecure());
    }

    /**
     * @requires function openssl_random_pseudo_bytes
     */
    public function testNonStrongOpenSSLRNGProvidersReturnExpectedNumberOfBytes() {
        $rng = new \RobThree\Auth\Providers\Rng\OpenSSLRNGProvider(false);
        foreach ($this->getRngTestLengths() as $l)
            $this->assertEquals($l, strlen($rng->getRandomBytes($l)));
        $this->assertEquals(false, $rng->isCryptographicallySecure());
    }


    private function getRngTestLengths() {
        return array(1, 16, 32, 256);
    }

    private function DecodeDataUri($datauri) {
        if (preg_match('/data:(?P<mimetype>[\w\.\-\/]+);(?P<encoding>\w+),(?P<data>.*)/', $datauri, $m) === 1) {
            return array(
                'mimetype' => $m['mimetype'],
                'encoding' => $m['encoding'],
                'data' => base64_decode($m['data'])
            );
        }
        return null;
    }
}

class TestRNGProvider implements IRNGProvider {
    private $isSecure;

    function __construct($isSecure = false) {
        $this->isSecure = $isSecure;
    }

    public function getRandomBytes($bytecount) {
        $result = '';
        for ($i=0; $i<$bytecount; $i++)
            $result.=chr($i);
        return $result;

    }

    public function isCryptographicallySecure() {
        return $this->isSecure;
    }
}

class TestQrProvider implements IQRCodeProvider {
    public function getQRCodeImage($qrtext, $size) {
        return $qrtext . '@' . $size;
    }

    public function getMimeType() {
        return 'test/test';
    }
}

class TestTimeProvider implements ITimeProvider {
    private $time;

    function __construct($time) {
        $this->time = $time;
    }

    public function getTime() {
        return $this->time;
    }
}