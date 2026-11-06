<?php

namespace Firebase\JWT;

use ArrayAccess;
use DateTime;
use DomainException;
use Exception;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use stdClass;
use UnexpectedValueException;

/**
 * JSON Web Token implementation, based on this spec:
 * https://tools.ietf.org/html/rfc7519
 *
 * PHP version 5
 *
 * @category Authentication
 * @package  Authentication_JWT
 * @author   Neuman Vong <neuman@twilio.com>
 * @author   Anant Narayanan <anant@php.net>
 * @license  http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link     https://github.com/firebase/php-jwt
 */
class JWT
{
    private const ASN1_INTEGER = 0x02;
    private const ASN1_SEQUENCE = 0x10;
    private const ASN1_BIT_STRING = 0x03;

    private const RSA_KEY_MIN_LENGTH = 2048;

    /**
     * When checking nbf, iat or expiration times,
     * we want to provide some extra leeway time to
     * account for clock skew.
     *
     * @var int
     */
    public static $leeway = 0;

    /**
     * Allow the current timestamp to be specified.
     * Useful for fixing a value within unit testing.
     * Will default to PHP time() value if null.
     *
     * @var ?int
     */
    public static $timestamp = null;

    /**
     * @var array<string, string[]>
     */
    public static $supported_algs = [
        'ES384' => ['openssl', 'SHA384'],
        'ES256' => ['openssl', 'SHA256'],
        'ES256K' => ['openssl', 'SHA256'],
        'HS256' => ['hash_hmac', 'SHA256'],
        'HS384' => ['hash_hmac', 'SHA384'],
        'HS512' => ['hash_hmac', 'SHA512'],
        'RS256' => ['openssl', 'SHA256'],
        'RS384' => ['openssl', 'SHA384'],
        'RS512' => ['openssl', 'SHA512'],
        'PS256' => ['openssl', 'SHA256'],
        'EdDSA' => ['sodium_crypto', 'EdDSA']
    ];

    /**
     * Decodes a JWT string into a PHP object.
     *
     * @param string                 $jwt            The JWT
     * @param Key|ArrayAccess<string,Key>|array<string,Key> $keyOrKeyArray  The Key or associative array of key IDs
     *                                                                      (kid) to Key objects.
     *                                                                      If the algorithm used is asymmetric, this is
     *                                                                      the public key.
     *                                                                      Each Key object contains an algorithm and
     *                                                                      matching key.
     *                                                                      Supported algorithms are 'ES384','ES256',
     *                                                                      'HS256', 'HS384', 'HS512', 'RS256', 'RS384'
     *                                                                      and 'RS512'.
     * @param stdClass               $headers                               Optional. Populates stdClass with headers.
     *
     * @return stdClass The JWT's payload as a PHP object
     *
     * @throws InvalidArgumentException     Provided key/key-array was empty or malformed
     * @throws DomainException              Provided JWT is malformed
     * @throws UnexpectedValueException     Provided JWT was invalid
     * @throws SignatureInvalidException    Provided JWT was invalid because the signature verification failed
     * @throws BeforeValidException         Provided JWT is trying to be used before it's eligible as defined by 'nbf'
     * @throws BeforeValidException         Provided JWT is trying to be used before it's
     *                                      been created as defined by 'iat'
     * @throws ExpiredException             Provided JWT has since expired, as defined by the 'exp' claim
     *
     * @uses jsonDecode
     * @uses urlsafeB64Decode
     */
    public static function decode(
        string $jwt,
        #[\SensitiveParameter] $keyOrKeyArray,
        ?stdClass &$headers = null
    ): stdClass {
        // Validate JWT
        $timestamp = \is_null(static::$timestamp) ? \time() : static::$timestamp;

        if (empty($keyOrKeyArray)) {
            throw new InvalidArgumentException('Key may not be empty');
        }
        $tks = \explode('.', $jwt, 4);
        if (\count($tks) !== 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
        $headerRaw = static::urlsafeB64Decode($headb64);
        if (null === ($header = static::jsonDecode($headerRaw))) {
            throw new UnexpectedValueException('Invalid header encoding');
        }
        if ($headers !== null) {
            $headers = $header;
        }
        $payloadRaw = static::urlsafeB64Decode($bodyb64);
        if (null === ($payload = static::jsonDecode($payloadRaw))) {
            throw new UnexpectedValueException('Invalid claims encoding');
        }
        if (\is_array($payload)) {
            // prevent PHP Fatal Error in edge-cases when payload is empty array
            $payload = (object) $payload;
        }
        if (!$payload instanceof stdClass) {
            throw new UnexpectedValueException('Payload must be a JSON object');
        }
        if (isset($payload->iat) && !\is_numeric($payload->iat)) {
            throw new UnexpectedValueException('Payload iat must be a number');
        }
        if (isset($payload->nbf) && !\is_numeric($payload->nbf)) {
            throw new UnexpectedValueException('Payload nbf must be a number');
        }
        if (isset($payload->exp) && !\is_numeric($payload->exp)) {
            throw new UnexpectedValueException('Payload exp must be a number');
        }

        $sig = static::urlsafeB64Decode($cryptob64);
        if (empty($header->alg)) {
            throw new UnexpectedValueException('Empty algorithm');
        }
        if (empty(static::$supported_algs[$header->alg])) {
            throw new UnexpectedValueException('Algorithm not supported');
        }

        $key = self::getKey($keyOrKeyArray, property_exists($header, 'kid') ? $header->kid : null);

        // Check the algorithm
        if (!self::constantTimeEquals($key->getAlgorithm(), $header->alg)) {
            // See issue #351
            throw new UnexpectedValueException('Incorrect key for this algorithm');
        }
        if (\in_array($header->alg, ['ES256', 'ES256K', 'ES384'], true)) {
            // OpenSSL expects an ASN.1 DER sequence for ES256/ES256K/ES384 signatures
            $sig = self::signatureToDER($sig);
        }
        if (!self::verify("{$headb64}.{$bodyb64}", $sig, $key->getKeyMaterial(), $header->alg)) {
            throw new SignatureInvalidException('Signature verification failed');
        }

        // Check the nbf if it is defined. This is the time that the
        // token can actually be used. If it's not yet that time, abort.
        if (isset($payload->nbf) && floor($payload->nbf) > ($timestamp + static::$leeway)) {
            $ex = new BeforeValidException(
                'Cannot handle token with nbf prior to ' . \date(DateTime::ATOM, (int) floor($payload->nbf))
            );
            $ex->setPayload($payload);
            throw $ex;
        }

        // Check that this token has been created before 'now'. This prevents
        // using tokens that have been created for later use (and haven't
        // correctly used the nbf claim).
        if (!isset($payload->nbf) && isset($payload->iat) && floor($payload->iat) > ($timestamp + static::$leeway)) {
            $ex = new BeforeValidException(
                'Cannot handle token with iat prior to ' . \date(DateTime::ATOM, (int) floor($payload->iat))
            );
            $ex->setPayload($payload);
            throw $ex;
        }

        // Check if this token has expired.
        if (isset($payload->exp) && ($timestamp - static::$leeway) >= $payload->exp) {
            $ex = new ExpiredException('Expired token');
            $ex->setPayload($payload);
            $ex->setTimestamp($timestamp);
            throw $ex;
        }

        return $payload;
    }

    /**
     * Converts and signs a PHP array into a JWT string.
     *
     * @param array<mixed>          $payload PHP array
     * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate $key The secret key.
     * @param string                $alg     Supported algorithms are 'ES384','ES256', 'ES256K', 'HS256',
     *                                       'HS384', 'HS512', 'RS256', 'RS384', and 'RS512'
     * @param string                $keyId
     * @param array<string, string|string[]> $head  An array with header elements to attach
     *
     * @return string A signed JWT
     *
     * @uses jsonEncode
     * @uses urlsafeB64Encode
     */
    public static function encode(
        array $payload,
        #[\SensitiveParameter] $key,
        string $alg,
        ?string $keyId = null,
        ?array $head = null
    ): string {
        $header = ['typ' => 'JWT'];
        if (isset($head)) {
            $header = \array_merge($header, $head);
        }
        $header['alg'] = $alg;
        if ($keyId !== null) {
            $header['kid'] = $keyId;
        }
        if (isset($payload['nbf']) && !\is_numeric($payload['nbf'])) {
            throw new UnexpectedValueException('Payload nbf must be a number');
        }
        if (isset($payload['iat']) && !\is_numeric($payload['iat'])) {
            throw new UnexpectedValueException('Payload iat must be a number');
        }
        if (isset($payload['exp']) && !\is_numeric($payload['exp'])) {
            throw new UnexpectedValueException('Payload exp must be a number');
        }
        $segments = [];
        $segments[] = static::urlsafeB64Encode((string) static::jsonEncode($header));
        $segments[] = static::urlsafeB64Encode((string) static::jsonEncode($payload));
        $signing_input = \implode('.', $segments);

        $signature = static::sign($signing_input, $key, $alg);
        $segments[] = static::urlsafeB64Encode($signature);

        return \implode('.', $segments);
    }

    /**
     * Sign a string with a given key and algorithm.
     *
     * @param string $msg  The message to sign
     * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate  $key  The secret key.
     * @param string $alg  Supported algorithms are 'EdDSA', 'ES384', 'ES256', 'ES256K', 'HS256',
     *                    'HS384', 'HS512', 'RS256', 'RS384', 'PS256' and 'RS512'
     *
     * @return string An encrypted message
     *
     * @throws DomainException Unsupported algorithm or bad key was specified
     */
    public static function sign(
        string $msg,
        #[\SensitiveParameter] $key,
        string $alg
    ): string {
        if (empty(static::$supported_algs[$alg])) {
            throw new DomainException('Algorithm not supported');
        }
        list($function, $algorithm) = static::$supported_algs[$alg];
        switch ($function) {
            case 'hash_hmac':
                if (!\is_string($key)) {
                    throw new InvalidArgumentException('key must be a string when using hmac');
                }
                self::validateHmacKeyLength($key, $algorithm);
                return \hash_hmac($algorithm, $msg, $key, true);
            case 'openssl':
                if ($alg === 'PS256') {
                    return self::signPS256($key, $msg);
                }
                $signature = '';
                if (!$key = openssl_pkey_get_private($key)) {
                    throw new DomainException('OpenSSL unable to validate key');
                }
                if (str_starts_with($alg, 'RS')) {
                    self::validateRsaKeyLength($key);
                } elseif (str_starts_with($alg, 'ES')) {
                    self::validateEcKeyLength($key, $alg);
                }
                $success = \openssl_sign($msg, $signature, $key, $algorithm);
                if (!$success) {
                    throw new DomainException('OpenSSL unable to sign data');
                }
                if ($alg === 'ES256' || $alg === 'ES256K') {
                    $signature = self::signatureFromDER($signature, 256);
                } elseif ($alg === 'ES384') {
                    $signature = self::signatureFromDER($signature, 384);
                }
                return $signature;
            case 'sodium_crypto':
                try {
                    return sodium_crypto_sign_detached($msg, self::validateEdDSAKey($key));
                } catch (Exception $e) {
                    throw new DomainException($e->getMessage(), 0, $e);
                }
        }

        throw new DomainException('Algorithm not supported');
    }

    /**
     * Verify a signature with the message, key and method. Not all methods
     * are symmetric, so we must have a separate verify and sign method.
     *
     * @param string $msg         The original message (header and body)
     * @param string $signature   The original signature
     * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate  $keyMaterial For Ed*, ES*, HS*, a string key works.
     *                                                        for RS*, must be an instance of OpenSSLAsymmetricKey
     * @param string $alg         The algorithm
     *
     * @return bool
     *
     * @throws DomainException Invalid Algorithm, bad key, or OpenSSL failure
     */
    private static function verify(
        string $msg,
        string $signature,
        #[\SensitiveParameter] $keyMaterial,
        string $alg
    ): bool {
        if (empty(static::$supported_algs[$alg])) {
            throw new DomainException('Algorithm not supported');
        }

        list($function, $algorithm) = static::$supported_algs[$alg];
        switch ($function) {
            case 'openssl':
                if ($alg === 'PS256') {
                    return self::verifyPS256($keyMaterial, $msg, $signature);
                }
                if (!$key = openssl_pkey_get_public($keyMaterial)) {
                    throw new DomainException('OpenSSL unable to validate key');
                }
                if (str_starts_with($alg, 'RS')) {
                    self::validateRsaKeyLength($key);
                } elseif (str_starts_with($alg, 'ES')) {
                    self::validateEcKeyLength($key, $alg);
                }
                $success = \openssl_verify($msg, $signature, $keyMaterial, $algorithm);
                if ($success === 1) {
                    return true;
                }
                if ($success === 0) {
                    return false;
                }
                // returns 1 on success, 0 on failure, -1 on error.
                throw new DomainException(
                    'OpenSSL error: ' . \openssl_error_string()
                );
            case 'sodium_crypto':
                try {
                    $key = self::validateEdDSAKey($keyMaterial);
                    if (\strlen($signature) === 0) {
                        throw new DomainException('Signature cannot be empty string');
                    }
                    return sodium_crypto_sign_verify_detached($signature, $msg, $key);
                } catch (Exception $e) {
                    throw new DomainException($e->getMessage(), 0, $e);
                }
            case 'hash_hmac':
            default:
                if (!\is_string($keyMaterial)) {
                    throw new InvalidArgumentException('key must be a string when using hmac');
                }
                self::validateHmacKeyLength($keyMaterial, $algorithm);
                $hash = \hash_hmac($algorithm, $msg, $keyMaterial, true);
                return self::constantTimeEquals($hash, $signature);
        }
    }

    /**
     * Decode a JSON string into a PHP object.
     *
     * @param string $input JSON string
     *
     * @return mixed The decoded JSON string
     *
     * @throws DomainException Provided string was invalid JSON
     */
    public static function jsonDecode(string $input)
    {
        $obj = \json_decode($input, false, 512, JSON_BIGINT_AS_STRING);

        if ($errno = \json_last_error()) {
            self::handleJsonError($errno);
        } elseif ($obj === null && $input !== 'null') {
            throw new DomainException('Null result with non-null input');
        }
        return $obj;
    }

    /**
     * Encode a PHP array into a JSON string.
     *
     * @param array<mixed> $input A PHP array
     *
     * @return string JSON representation of the PHP array
     *
     * @throws DomainException Provided object could not be encoded to valid JSON
     */
    public static function jsonEncode(array $input): string
    {
        $json = \json_encode($input, \JSON_UNESCAPED_SLASHES);
        if ($errno = \json_last_error()) {
            self::handleJsonError($errno);
        } elseif ($json === 'null') {
            throw new DomainException('Null result with non-null input');
        }
        if ($json === false) {
            throw new DomainException('Provided object could not be encoded to valid JSON');
        }
        return $json;
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     *
     * @return string A decoded string
     *
     * @throws InvalidArgumentException invalid base64 characters
     */
    public static function urlsafeB64Decode(string $input): string
    {
        return \base64_decode(self::convertBase64UrlToBase64($input));
    }

    /**
     * Convert a string in the base64url (URL-safe Base64) encoding to standard base64.
     *
     * @param string $input A Base64 encoded string with URL-safe characters (-_ and no padding)
     *
     * @return string A Base64 encoded string with standard characters (+/) and padding (=), when
     * needed.
     *
     * @see https://www.rfc-editor.org/rfc/rfc4648
     */
    public static function convertBase64UrlToBase64(string $input): string
    {
        $remainder = \strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= \str_repeat('=', $padlen);
        }
        return \strtr($input, '-_', '+/');
    }

    /**
     * Encode a string with URL-safe Base64.
     *
     * @param string $input The string you want encoded
     *
     * @return string The base64 encode of what you passed in
     */
    public static function urlsafeB64Encode(string $input): string
    {
        return \str_replace('=', '', \strtr(\base64_encode($input), '+/', '-_'));
    }

    /**
     * Determine if an algorithm has been provided for each Key
     *
     * @param Key|ArrayAccess<string,Key>|array<string,Key> $keyOrKeyArray
     * @param string|null            $kid
     *
     * @throws UnexpectedValueException
     *
     * @return Key
     */
    private static function getKey(
        #[\SensitiveParameter] $keyOrKeyArray,
        ?string $kid
    ): Key {
        if ($keyOrKeyArray instanceof Key) {
            return $keyOrKeyArray;
        }

        if (empty($kid) && $kid !== '0') {
            throw new UnexpectedValueException('"kid" empty, unable to lookup correct key');
        }

        if ($keyOrKeyArray instanceof CachedKeySet) {
            // Skip "isset" check, as this will automatically refresh if not set
            return $keyOrKeyArray[$kid];
        }

        if (!isset($keyOrKeyArray[$kid])) {
            throw new UnexpectedValueException('"kid" invalid, unable to lookup correct key');
        }

        return $keyOrKeyArray[$kid];
    }

    /**
     * @param string $left  The string of known length to compare against
     * @param string $right The user-supplied string
     * @return bool
     */
    public static function constantTimeEquals(string $left, string $right): bool
    {
        if (\function_exists('hash_equals')) {
            return \hash_equals($left, $right);
        }
        $len = \min(self::safeStrlen($left), self::safeStrlen($right));

        $status = 0;
        for ($i = 0; $i < $len; $i++) {
            $status |= (\ord($left[$i]) ^ \ord($right[$i]));
        }
        $status |= (self::safeStrlen($left) ^ self::safeStrlen($right));

        return ($status === 0);
    }

    /**
     * Helper method to create a JSON error.
     *
     * @param int $errno An error number from json_last_error()
     *
     * @throws DomainException
     *
     * @return void
     */
    private static function handleJsonError(int $errno): void
    {
        $messages = [
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters' //PHP >= 5.3.3
        ];
        throw new DomainException(
            isset($messages[$errno])
            ? $messages[$errno]
            : 'Unknown JSON error: ' . $errno
        );
    }

    /**
     * Get the number of bytes in cryptographic strings.
     *
     * @param string $str
     *
     * @return int
     */
    private static function safeStrlen(string $str): int
    {
        if (\function_exists('mb_strlen')) {
            return \mb_strlen($str, '8bit');
        }
        return \strlen($str);
    }

    /**
     * Convert an ECDSA signature to an ASN.1 DER sequence
     *
     * @param   string $sig The ECDSA signature to convert
     * @return  string The encoded DER object
     */
    private static function signatureToDER(string $sig): string
    {
        // Separate the signature into r-value and s-value
        $length = max(1, (int) (\strlen($sig) / 2));
        list($r, $s) = \str_split($sig, $length);

        // Trim leading zeros
        $r = \ltrim($r, "\x00");
        $s = \ltrim($s, "\x00");

        // Convert r-value and s-value from unsigned big-endian integers to
        // signed two's complement
        if (\ord($r[0]) > 0x7f) {
            $r = "\x00" . $r;
        }
        if (\ord($s[0]) > 0x7f) {
            $s = "\x00" . $s;
        }

        return self::encodeDER(
            self::ASN1_SEQUENCE,
            self::encodeDER(self::ASN1_INTEGER, $r) .
            self::encodeDER(self::ASN1_INTEGER, $s)
        );
    }

    /**
     * Encodes a value into a DER object.
     *
     * @param   int     $type DER tag
     * @param   string  $value the value to encode
     *
     * @return  string  the encoded object
     */
    private static function encodeDER(int $type, string $value): string
    {
        $tag_header = 0;
        if ($type === self::ASN1_SEQUENCE) {
            $tag_header |= 0x20;
        }

        // Type
        $der = \chr($tag_header | $type);

        // Length
        $der .= \chr(\strlen($value));

        return $der . $value;
    }

    /**
     * Encodes signature from a DER object.
     *
     * @param   string  $der binary signature in DER format
     * @param   int     $keySize the number of bits in the key
     *
     * @return  string  the signature
     */
    private static function signatureFromDER(string $der, int $keySize): string
    {
        // OpenSSL returns the ECDSA signatures as a binary ASN.1 DER SEQUENCE
        list($offset, $_) = self::readDER($der);
        list($offset, $r) = self::readDER($der, $offset);
        list($offset, $s) = self::readDER($der, $offset);

        // Convert r-value and s-value from signed two's compliment to unsigned
        // big-endian integers
        $r = \ltrim($r, "\x00");
        $s = \ltrim($s, "\x00");

        // Pad out r and s so that they are $keySize bits long
        $r = \str_pad($r, $keySize / 8, "\x00", STR_PAD_LEFT);
        $s = \str_pad($s, $keySize / 8, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    /**
     * Reads binary DER-encoded data and decodes into a single object
     *
     * @param string $der the binary data in DER format
     * @param int $offset the offset of the data stream containing the object
     * to decode
     *
     * @return array{int, string|null} the new offset and the decoded object
     */
    private static function readDER(string $der, int $offset = 0): array
    {
        $pos = $offset;
        $size = \strlen($der);
        $constructed = (\ord($der[$pos]) >> 5) & 0x01;
        $type = \ord($der[$pos++]) & 0x1f;

        // Length
        $len = \ord($der[$pos++]);
        if ($len & 0x80) {
            $n = $len & 0x1f;
            $len = 0;
            while ($n-- && $pos < $size) {
                $len = ($len << 8) | \ord($der[$pos++]);
            }
        }

        // Value
        if ($type === self::ASN1_BIT_STRING) {
            $pos++; // Skip the first contents octet (padding indicator)
            $data = \substr($der, $pos, $len - 1);
            $pos += $len - 1;
        } elseif (!$constructed) {
            $data = \substr($der, $pos, $len);
            $pos += $len;
        } else {
            $data = null;
        }

        return [$pos, $data];
    }

    /**
     * Validate HMAC key length
     *
     * @param string $key HMAC key material
     * @param string $algorithm The algorithm
     *
     * @throws DomainException Provided key is too short
     */
    private static function validateHmacKeyLength(string $key, string $algorithm): void
    {
        $keyLength = \strlen($key) * 8;
        $minKeyLength = (int) \str_replace('SHA', '', $algorithm);
        if ($keyLength < $minKeyLength) {
            throw new DomainException('Provided key is too short');
        }
    }

    /**
     * Validate RSA key length
     *
     * @param OpenSSLAsymmetricKey $key RSA key material
     * @throws DomainException Provided key is too short
     */
    private static function validateRsaKeyLength(#[\SensitiveParameter] OpenSSLAsymmetricKey $key): void
    {
        if (!$keyDetails = openssl_pkey_get_details($key)) {
            throw new DomainException('Unable to validate key');
        }
        if ($keyDetails['bits'] < self::RSA_KEY_MIN_LENGTH) {
            throw new DomainException('Provided key is too short');
        }
    }

    /**
     * Validate RSA key length
     *
     * @param OpenSSLAsymmetricKey $key RSA key material
     * @param string $algorithm The algorithm
     * @throws DomainException Provided key is too short
     */
    private static function validateEcKeyLength(
        #[\SensitiveParameter] OpenSSLAsymmetricKey $key,
        string $algorithm
    ): void {
        if (!$keyDetails = openssl_pkey_get_details($key)) {
            throw new DomainException('Unable to validate key');
        }
        $minKeyLength = (int) \str_replace('ES', '', $algorithm);
        if ($keyDetails['bits'] < $minKeyLength) {
            throw new DomainException('Provided key is too short');
        }
    }

    /**
     * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate  $keyMaterial
     * @return non-empty-string
     */
    private static function validateEdDSAKey(#[\SensitiveParameter] $keyMaterial): string
    {
        if (!\function_exists('sodium_crypto_sign_verify_detached')) {
            throw new DomainException('libsodium is not available');
        }
        if (!\is_string($keyMaterial)) {
            throw new InvalidArgumentException('key must be a string when using EdDSA');
        }
        // The last non-empty line is used as the key.
        $lines = array_filter(explode("\n", $keyMaterial));
        $key = self::urlsafeB64Decode((string) end($lines));
        if (\strlen($key) === 0) {
            throw new DomainException('Key cannot be empty string');
        }
        return $key;
    }

    /**
     * Signs a message with a PS256 algorithm
     *
     * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate $key
     * @param string $message
     * @throws DomainException Provided key is invalid
     */
    private static function signPS256(
        #[\SensitiveParameter] string|OpenSSLAsymmetricKey|OpenSSLCertificate $key,
        string $message
    ): string {
        if (!class_exists('\phpseclib3\Crypt\RSA')) {
            throw new DomainException('phpseclib/phpseclib is required for PS256 support');
        }

        if ($key instanceof OpenSSLCertificate) {
            throw new DomainException('Cannot sign with an X.509 certificate. A private key is required.');
        }

        if ($key instanceof OpenSSLAsymmetricKey) {
            if (!openssl_pkey_export($key, $pem)) {
                throw new DomainException('OpenSSL unable to export the AsymmetricKey');
            }
            $key = $pem;
        }

        /** @var \phpseclib3\Crypt\RSA\PrivateKey $rsa */
        $rsa = \phpseclib3\Crypt\PublicKeyLoader::load($key);

        return $rsa->withPadding(\phpseclib3\Crypt\RSA::SIGNATURE_PSS)
            ->withHash('sha256')
            ->sign($message);
    }

    /**
     * Validates a PS256 algorithm signature.
     *
     * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate $key
     * @param string $message
     * @param string $signature
     * @throws DomainException Provided key is invalid
     */
    private static function verifyPS256(
        #[\SensitiveParameter] string|OpenSSLAsymmetricKey|OpenSSLCertificate $key,
        string $message,
        string $signature
    ): bool {
        if (!class_exists('\phpseclib3\Crypt\RSA')) {
            throw new DomainException('phpseclib/phpseclib is required for PS256 support');
        }

        if ($key instanceof OpenSSLAsymmetricKey) {
            $details = openssl_pkey_get_details($key);
            if (!$details || !isset($details['key'])) {
                throw new DomainException('OpenSSL unable to extract public key');
            }
            $key = $details['key'];
        } elseif ($key instanceof OpenSSLCertificate) {
            if (!openssl_x509_export($key, $pem)) {
                throw new DomainException('OpenSSL unable to export certificate');
            }
            $key = $pem;
        }

        /** @var \phpseclib3\Crypt\RSA\PublicKey $rsa */
        $rsa = \phpseclib3\Crypt\PublicKeyLoader::load($key);

        return $rsa->withPadding(\phpseclib3\Crypt\RSA::SIGNATURE_PSS)
            ->withHash('sha256')
            ->verify($message, $signature);
    }
}
