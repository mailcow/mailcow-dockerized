<?php

namespace LdapRecord\Models\Attributes;

use InvalidArgumentException;
use LdapRecord\LdapRecordException;
use ReflectionMethod;

class Password
{
    public const CRYPT_SALT_TYPE_MD5 = 1;

    public const CRYPT_SALT_TYPE_SHA256 = 5;

    public const CRYPT_SALT_TYPE_SHA512 = 6;

    /**
     * Make an encoded password for transmission over LDAP.
     */
    public static function encode(string $password): string
    {
        return iconv('UTF-8', 'UTF-16LE', '"'.$password.'"');
    }

    /**
     * Make a salted md5 password.
     */
    public static function smd5(string $password, ?string $salt = null): string
    {
        return '{SMD5}'.static::makeHash($password, 'md5', null, $salt ?? random_bytes(4));
    }

    /**
     * Make a salted SHA password.
     */
    public static function ssha(string $password, ?string $salt = null): string
    {
        return '{SSHA}'.static::makeHash($password, 'sha1', null, $salt ?? random_bytes(4));
    }

    /**
     * Make a salted SSHA256 password.
     */
    public static function ssha256(string $password, ?string $salt = null): string
    {
        return '{SSHA256}'.static::makeHash($password, 'hash', 'sha256', $salt ?? random_bytes(4));
    }

    /**
     * Make a salted SSHA384 password.
     */
    public static function ssha384(string $password, ?string $salt = null): string
    {
        return '{SSHA384}'.static::makeHash($password, 'hash', 'sha384', $salt ?? random_bytes(4));
    }

    /**
     * Make a salted SSHA512 password.
     */
    public static function ssha512(string $password, ?string $salt = null): string
    {
        return '{SSHA512}'.static::makeHash($password, 'hash', 'sha512', $salt ?? random_bytes(4));
    }

    /**
     * Make a non-salted SHA password.
     */
    public static function sha(string $password): string
    {
        return '{SHA}'.static::makeHash($password, 'sha1');
    }

    /**
     * Make a non-salted SHA256 password.
     */
    public static function sha256(string $password): string
    {
        return '{SHA256}'.static::makeHash($password, 'hash', 'sha256');
    }

    /**
     * Make a non-salted SHA384 password.
     */
    public static function sha384(string $password): string
    {
        return '{SHA384}'.static::makeHash($password, 'hash', 'sha384');
    }

    /**
     * Make a non-salted SHA512 password.
     */
    public static function sha512(string $password): string
    {
        return '{SHA512}'.static::makeHash($password, 'hash', 'sha512');
    }

    /**
     * Make a non-salted md5 password.
     */
    public static function md5(string $password): string
    {
        return '{MD5}'.static::makeHash($password, 'md5');
    }

    /**
     * Make a non-salted NThash password.
     */
    public static function nthash(string $password): string
    {
        return '{NTHASH}'.strtoupper(hash('md4', iconv('UTF-8', 'UTF-16LE', $password)));
    }

    /**
     * Crypt password with an MD5 salt.
     */
    public static function md5Crypt(string $password, ?string $salt = null): string
    {
        return '{CRYPT}'.static::makeCrypt($password, static::CRYPT_SALT_TYPE_MD5, $salt);
    }

    /**
     * Crypt password with a SHA256 salt.
     */
    public static function sha256Crypt(string $password, ?string $salt = null): string
    {
        return '{CRYPT}'.static::makeCrypt($password, static::CRYPT_SALT_TYPE_SHA256, $salt);
    }

    /**
     * Crypt a password with a SHA512 salt.
     */
    public static function sha512Crypt(string $password, ?string $salt = null): string
    {
        return '{CRYPT}'.static::makeCrypt($password, static::CRYPT_SALT_TYPE_SHA512, $salt);
    }

    /**
     * Make a new password hash.
     */
    protected static function makeHash(string $password, string $method, ?string $algo = null, ?string $salt = null): string
    {
        $params = $algo ? [$algo, $password.$salt] : [$password.$salt];

        return base64_encode(pack('H*', call_user_func($method, ...$params)).$salt);
    }

    /**
     * Make a hashed password.
     */
    protected static function makeCrypt(string $password, int $type, ?string $salt = null): string
    {
        return crypt($password, $salt ?? static::makeCryptSalt($type));
    }

    /**
     * Make a salt for the crypt() method using the given type.
     */
    protected static function makeCryptSalt(int $type): string
    {
        [$prefix, $length] = static::makeCryptPrefixAndLength($type);

        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        while (strlen($prefix) < $length) {
            $prefix .= substr($chars, random_int(0, strlen($chars) - 1), 1);
        }

        return $prefix;
    }

    /**
     * Determine the crypt prefix and length.
     *
     *
     * @throws InvalidArgumentException
     */
    protected static function makeCryptPrefixAndLength(int $type): array
    {
        return match ((int) $type) {
            static::CRYPT_SALT_TYPE_MD5 => ['$1$', 12],
            static::CRYPT_SALT_TYPE_SHA256 => ['$5$', 16],
            static::CRYPT_SALT_TYPE_SHA512 => ['$6$', 16],
            default => throw new InvalidArgumentException("Invalid crypt type [$type]."),
        };
    }

    /**
     * Attempt to retrieve the hash method used for the password.
     */
    public static function getHashMethod(string $password): ?string
    {
        if (! preg_match('/^\{(\w+)\}/', $password, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Attempt to retrieve the hash method and algorithm used for the password.
     */
    public static function getHashMethodAndAlgo(string $password): ?array
    {
        if (! preg_match('/^\{(\w+)\}\$([0-9a-z]{1})\$/', $password, $matches)) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }

    /**
     * Attempt to retrieve a salt from the encrypted password.
     *
     * @throws LdapRecordException
     */
    public static function getSalt(string $encryptedPassword): string
    {
        // crypt() methods.
        if (preg_match('/^\{(\w+)\}(\$.*\$).*$/', $encryptedPassword, $matches)) {
            return $matches[2];
        }

        // All other methods.
        if (preg_match('/{([^}]+)}(.*)/', $encryptedPassword, $matches)) {
            return substr(base64_decode($matches[2]), -4);
        }

        throw new LdapRecordException('Could not extract salt from encrypted password.');
    }

    /**
     * Determine if the hash method requires a salt to be given.
     *
     * @throws \ReflectionException
     */
    public static function hashMethodRequiresSalt(string $method): bool
    {
        $parameters = (new ReflectionMethod(static::class, $method))->getParameters();

        foreach ($parameters as $parameter) {
            if ($parameter->name === 'salt') {
                return true;
            }
        }

        return false;
    }
}
