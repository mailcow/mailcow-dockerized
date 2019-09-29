<?php
namespace RobThree\Auth;

use RobThree\Auth\Providers\Qr\IQRCodeProvider;
use RobThree\Auth\Providers\Rng\IRNGProvider;
use RobThree\Auth\Providers\Time\ITimeProvider;

// Based on / inspired by: https://github.com/PHPGangsta/GoogleAuthenticator
// Algorithms, digits, period etc. explained: https://github.com/google/google-authenticator/wiki/Key-Uri-Format
class TwoFactorAuth
{
    private $algorithm;
    private $period;
    private $digits;
    private $issuer;
    private $qrcodeprovider = null;
    private $rngprovider = null;
    private $timeprovider = null;
    private static $_base32dict = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567=';
    private static $_base32;
    private static $_base32lookup = array();
    private static $_supportedalgos = array('sha1', 'sha256', 'sha512', 'md5');

    function __construct($issuer = null, $digits = 6, $period = 30, $algorithm = 'sha1', IQRCodeProvider $qrcodeprovider = null, IRNGProvider $rngprovider = null, ITimeProvider $timeprovider = null)
    {
        $this->issuer = $issuer;
        if (!is_int($digits) || $digits <= 0)
            throw new TwoFactorAuthException('Digits must be int > 0');
        $this->digits = $digits;

        if (!is_int($period) || $period <= 0)
            throw new TwoFactorAuthException('Period must be int > 0');
        $this->period = $period;

        $algorithm = strtolower(trim($algorithm));
        if (!in_array($algorithm, self::$_supportedalgos))
            throw new TwoFactorAuthException('Unsupported algorithm: ' . $algorithm);
        $this->algorithm = $algorithm;
        $this->qrcodeprovider = $qrcodeprovider;
        $this->rngprovider = $rngprovider;
        $this->timeprovider = $timeprovider;

        self::$_base32 = str_split(self::$_base32dict);
        self::$_base32lookup = array_flip(self::$_base32);
    }

    /**
     * Create a new secret
     */
    public function createSecret($bits = 80, $requirecryptosecure = true)
    {
        $secret = '';
        $bytes = ceil($bits / 5);   //We use 5 bits of each byte (since we have a 32-character 'alphabet' / BASE32)
        $rngprovider = $this->getRngprovider();
        if ($requirecryptosecure && !$rngprovider->isCryptographicallySecure())
            throw new TwoFactorAuthException('RNG provider is not cryptographically secure');
        $rnd = $rngprovider->getRandomBytes($bytes);
        for ($i = 0; $i < $bytes; $i++)
            $secret .= self::$_base32[ord($rnd[$i]) & 31];  //Mask out left 3 bits for 0-31 values
        return $secret;
    }

    /**
     * Calculate the code with given secret and point in time
     */
    public function getCode($secret, $time = null)
    {
        $secretkey = $this->base32Decode($secret);

        $timestamp = "\0\0\0\0" . pack('N*', $this->getTimeSlice($this->getTime($time)));  // Pack time into binary string
        $hashhmac = hash_hmac($this->algorithm, $timestamp, $secretkey, true);             // Hash it with users secret key
        $hashpart = substr($hashhmac, ord(substr($hashhmac, -1)) & 0x0F, 4);               // Use last nibble of result as index/offset and grab 4 bytes of the result
        $value = unpack('N', $hashpart);                                                   // Unpack binary value
        $value = $value[1] & 0x7FFFFFFF;                                                   // Drop MSB, keep only 31 bits

        return str_pad($value % pow(10, $this->digits), $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Check if the code is correct. This will accept codes starting from ($discrepancy * $period) sec ago to ($discrepancy * period) sec from now
     */
    public function verifyCode($secret, $code, $discrepancy = 1, $time = null, &$timeslice = 0)
    {
        $timetamp = $this->getTime($time);

        $timeslice = 0;

        // To keep safe from timing-attacks we iterate *all* possible codes even though we already may have
        // verified a code is correct. We use the timeslice variable to hold either 0 (no match) or the timeslice
        // of the match. Each iteration we either set the timeslice variable to the timeslice of the match
        // or set the value to itself.  This is an effort to maintain constant execution time for the code.
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $ts = $timetamp + ($i * $this->period);
            $slice = $this->getTimeSlice($ts);
            $timeslice = $this->codeEquals($this->getCode($secret, $ts), $code) ? $slice : $timeslice;
        }

        return $timeslice > 0;
    }

    /**
     * Timing-attack safe comparison of 2 codes (see http://blog.ircmaxell.com/2014/11/its-all-about-time.html)
     */
    private function codeEquals($safe, $user) {
        if (function_exists('hash_equals')) {
            return hash_equals($safe, $user);
        }
        // In general, it's not possible to prevent length leaks. So it's OK to leak the length. The important part is that
        // we don't leak information about the difference of the two strings.
        if (strlen($safe)===strlen($user)) {
            $result = 0;
            for ($i = 0; $i < strlen($safe); $i++)
                $result |= (ord($safe[$i]) ^ ord($user[$i]));
            return $result === 0;
        }
        return false;
    }

    /**
     * Get data-uri of QRCode
     */
    public function getQRCodeImageAsDataUri($label, $secret, $size = 200)
    {
        if (!is_int($size) || $size <= 0)
            throw new TwoFactorAuthException('Size must be int > 0');

        $qrcodeprovider = $this->getQrCodeProvider();
        return 'data:'
            . $qrcodeprovider->getMimeType()
            . ';base64,'
            . base64_encode($qrcodeprovider->getQRCodeImage($this->getQRText($label, $secret), $size));
    }

    /**
     * Compare default timeprovider with specified timeproviders and ensure the time is within the specified number of seconds (leniency)
     */
    public function ensureCorrectTime(array $timeproviders = null, $leniency = 5)
    {
        if ($timeproviders != null && !is_array($timeproviders))
            throw new TwoFactorAuthException('No timeproviders specified');

        if ($timeproviders == null)
            $timeproviders = array(
                new Providers\Time\NTPTimeProvider(),
                new Providers\Time\HttpTimeProvider()
            );

        // Get default time provider
        $timeprovider = $this->getTimeProvider();

        // Iterate specified time providers
        foreach ($timeproviders as $t) {
            if (!($t instanceof ITimeProvider))
                throw new TwoFactorAuthException('Object does not implement ITimeProvider');

            // Get time from default time provider and compare to specific time provider and throw if time difference is more than specified number of seconds leniency
            if (abs($timeprovider->getTime() - $t->getTime()) > $leniency)
                throw new TwoFactorAuthException(sprintf('Time for timeprovider is off by more than %d seconds when compared to %s', $leniency, get_class($t)));
        }
    }

    private function getTime($time)
    {
        return ($time === null) ? $this->getTimeProvider()->getTime() : $time;
    }

    private function getTimeSlice($time = null, $offset = 0)
    {
        return (int)floor($time / $this->period) + ($offset * $this->period);
    }

    /**
     * Builds a string to be encoded in a QR code
     */
    public function getQRText($label, $secret)
    {
        return 'otpauth://totp/' . rawurlencode($label)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($this->issuer)
            . '&period=' . intval($this->period)
            . '&algorithm=' . rawurlencode(strtoupper($this->algorithm))
            . '&digits=' . intval($this->digits);
    }

    private function base32Decode($value)
    {
        if (strlen($value)==0) return '';

        if (preg_match('/[^'.preg_quote(self::$_base32dict).']/', $value) !== 0)
            throw new TwoFactorAuthException('Invalid base32 string');

        $buffer = '';
        foreach (str_split($value) as $char)
        {
            if ($char !== '=')
                $buffer .= str_pad(decbin(self::$_base32lookup[$char]), 5, 0, STR_PAD_LEFT);
        }
        $length = strlen($buffer);
        $blocks = trim(chunk_split(substr($buffer, 0, $length - ($length % 8)), 8, ' '));

        $output = '';
        foreach (explode(' ', $blocks) as $block)
            $output .= chr(bindec(str_pad($block, 8, 0, STR_PAD_RIGHT)));
        return $output;
    }

    /**
     * @return IQRCodeProvider
     * @throws TwoFactorAuthException
     */
    public function getQrCodeProvider()
    {
        // Set default QR Code provider if none was specified
        if (null === $this->qrcodeprovider) {
            return $this->qrcodeprovider = new Providers\Qr\QRServerProvider();
        }
        return $this->qrcodeprovider;
    }

    /**
     * @return IRNGProvider
     * @throws TwoFactorAuthException
     */
    public function getRngprovider()
    {
        if (null !== $this->rngprovider) {
            return $this->rngprovider;
        }
        if (function_exists('random_bytes')) {
            return $this->rngprovider = new Providers\Rng\CSRNGProvider();
        }
        if (function_exists('mcrypt_create_iv')) {
            return $this->rngprovider = new Providers\Rng\MCryptRNGProvider();
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return $this->rngprovider = new Providers\Rng\OpenSSLRNGProvider();
        }
        if (function_exists('hash')) {
            return $this->rngprovider = new Providers\Rng\HashRNGProvider();
        }
        throw new TwoFactorAuthException('Unable to find a suited RNGProvider');
    }

    /**
     * @return ITimeProvider
     * @throws TwoFactorAuthException
     */
    public function getTimeProvider()
    {
        // Set default time provider if none was specified
        if (null === $this->timeprovider) {
            return $this->timeprovider = new Providers\Time\LocalMachineTimeProvider();
        }
        return $this->timeprovider;
    }
}