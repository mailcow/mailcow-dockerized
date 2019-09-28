<?php

namespace OAuth2\Encryption;

use Exception;
use InvalidArgumentException;

/**
 * @link https://github.com/F21/jwt
 * @author F21
 */
class Jwt implements EncryptionInterface
{
    /**
     * @param $payload
     * @param $key
     * @param string $algo
     * @return string
     */
    public function encode($payload, $key, $algo = 'HS256')
    {
        $header = $this->generateJwtHeader($payload, $algo);

        $segments = array(
            $this->urlSafeB64Encode(json_encode($header)),
            $this->urlSafeB64Encode(json_encode($payload))
        );

        $signing_input = implode('.', $segments);

        $signature = $this->sign($signing_input, $key, $algo);
        $segments[] = $this->urlsafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * @param string      $jwt
     * @param null        $key
     * @param array|bool  $allowedAlgorithms
     * @return bool|mixed
     */
    public function decode($jwt, $key = null, $allowedAlgorithms = true)
    {
        if (!strpos($jwt, '.')) {
            return false;
        }

        $tks = explode('.', $jwt);

        if (count($tks) != 3) {
            return false;
        }

        list($headb64, $payloadb64, $cryptob64) = $tks;

        if (null === ($header = json_decode($this->urlSafeB64Decode($headb64), true))) {
            return false;
        }

        if (null === $payload = json_decode($this->urlSafeB64Decode($payloadb64), true)) {
            return false;
        }

        $sig = $this->urlSafeB64Decode($cryptob64);

        if ((bool) $allowedAlgorithms) {
            if (!isset($header['alg'])) {
                return false;
            }

            // check if bool arg supplied here to maintain BC
            if (is_array($allowedAlgorithms) && !in_array($header['alg'], $allowedAlgorithms)) {
                return false;
            }

            if (!$this->verifySignature($sig, "$headb64.$payloadb64", $key, $header['alg'])) {
                return false;
            }
        }

        return $payload;
    }

    /**
     * @param $signature
     * @param $input
     * @param $key
     * @param string $algo
     * @return bool
     * @throws InvalidArgumentException
     */
    private function verifySignature($signature, $input, $key, $algo = 'HS256')
    {
        // use constants when possible, for HipHop support
        switch ($algo) {
            case'HS256':
            case'HS384':
            case'HS512':
                return $this->hash_equals(
                    $this->sign($input, $key, $algo),
                    $signature
                );

            case 'RS256':
                return openssl_verify($input, $signature, $key, defined('OPENSSL_ALGO_SHA256') ? OPENSSL_ALGO_SHA256 : 'sha256')  === 1;

            case 'RS384':
                return @openssl_verify($input, $signature, $key, defined('OPENSSL_ALGO_SHA384') ? OPENSSL_ALGO_SHA384 : 'sha384') === 1;

            case 'RS512':
                return @openssl_verify($input, $signature, $key, defined('OPENSSL_ALGO_SHA512') ? OPENSSL_ALGO_SHA512 : 'sha512') === 1;

            default:
                throw new InvalidArgumentException("Unsupported or invalid signing algorithm.");
        }
    }

    /**
     * @param $input
     * @param $key
     * @param string $algo
     * @return string
     * @throws Exception
     */
    private function sign($input, $key, $algo = 'HS256')
    {
        switch ($algo) {
            case 'HS256':
                return hash_hmac('sha256', $input, $key, true);

            case 'HS384':
                return hash_hmac('sha384', $input, $key, true);

            case 'HS512':
                return hash_hmac('sha512', $input, $key, true);

            case 'RS256':
                return $this->generateRSASignature($input, $key, defined('OPENSSL_ALGO_SHA256') ? OPENSSL_ALGO_SHA256 : 'sha256');

            case 'RS384':
                return $this->generateRSASignature($input, $key, defined('OPENSSL_ALGO_SHA384') ? OPENSSL_ALGO_SHA384 : 'sha384');

            case 'RS512':
                return $this->generateRSASignature($input, $key, defined('OPENSSL_ALGO_SHA512') ? OPENSSL_ALGO_SHA512 : 'sha512');

            default:
                throw new Exception("Unsupported or invalid signing algorithm.");
        }
    }

    /**
     * @param $input
     * @param $key
     * @param string $algo
     * @return mixed
     * @throws Exception
     */
    private function generateRSASignature($input, $key, $algo)
    {
        if (!openssl_sign($input, $signature, $key, $algo)) {
            throw new Exception("Unable to sign data.");
        }

        return $signature;
    }

    /**
     * @param string $data
     * @return string
     */
    public function urlSafeB64Encode($data)
    {
        $b64 = base64_encode($data);
        $b64 = str_replace(array('+', '/', "\r", "\n", '='),
                array('-', '_'),
                $b64);

        return $b64;
    }

    /**
     * @param string $b64
     * @return mixed|string
     */
    public function urlSafeB64Decode($b64)
    {
        $b64 = str_replace(array('-', '_'),
                array('+', '/'),
                $b64);

        return base64_decode($b64);
    }

    /**
     * Override to create a custom header
     */
    protected function generateJwtHeader($payload, $algorithm)
    {
        return array(
            'typ' => 'JWT',
            'alg' => $algorithm,
        );
    }

    /**
     * @param string $a
     * @param string $b
     * @return bool
     */
    protected function hash_equals($a, $b)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        $diff = strlen($a) ^ strlen($b);
        for ($i = 0; $i < strlen($a) && $i < strlen($b); $i++) {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }

        return $diff === 0;
    }
}