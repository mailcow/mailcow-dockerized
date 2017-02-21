<?php
/**
 * Part of Crypt_GPG
 *
 * PHP version 5
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Christian Weiske <cweiske@php.net>
 * @copyright 2015 PEAR
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 * @link      http://pear.php.net/manual/en/package.encryption.crypt-gpg.php
 * @link      http://www.gnupg.org/
 */

/**
 * Information about a recently created signature.
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Christian Weiske <cweiske@php.net>
 * @copyright 2015 PEAR
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 * @link      http://pear.php.net/manual/en/package.encryption.crypt-gpg.php
 * @link      http://www.gnupg.org/
 */
class Crypt_GPG_SignatureCreationInfo
{
    /**
     * One of the three signature types:
     * - {@link Crypt_GPG::SIGN_MODE_NORMAL}
     * - {@link Crypt_GPG::SIGN_MODE_CLEAR}
     * - {@link Crypt_GPG::SIGN_MODE_DETACHED}
     *
     * @var integer
     */
    protected $mode;

    /**
     * Public Key algorithm
     *
     * @var integer
     */
    protected $pkAlgorithm;

    /**
     * Algorithm to hash the data
     *
     * @see RFC 2440 / 9.4. Hash Algorithm
     * @var integer
     */
    protected $hashAlgorithm;

    /**
     * OpenPGP signature class
     *
     * @var mixed
     */
    protected $class;

    /**
     * Unix timestamp when the signature was created
     *
     * @var integer
     */
    protected $timestamp;

    /**
     * Key fingerprint
     *
     * @var string
     */
    protected $keyFingerprint;

    /**
     * If the line given to the constructor was valid
     *
     * @var boolean
     */
    protected $valid;

    /**
     * Names for the hash algorithm IDs.
     *
     * Names taken from RFC 3156, without the leading "pgp-".
     *
     * @see RFC 2440 / 9.4. Hash Algorithm
     * @see RFC 3156 / 5. OpenPGP signed data
     * @var array
     */
    protected static $hashAlgorithmNames = array(
        1 => 'md5',
        2 => 'sha1',
        3 => 'ripemd160',
        5 => 'md2',
        6 => 'tiger192',
        7 => 'haval-5-160',
    );

    /**
     * Parse a SIG_CREATED line from gnupg
     *
     * @param string $sigCreatedLine Line beginning with "SIG_CREATED "
     */
    public function __construct($sigCreatedLine = null)
    {
        if ($sigCreatedLine === null) {
            $this->valid = false;
            return;
        }

        $parts = explode(' ', $sigCreatedLine);
        if (count($parts) !== 7) {
            $this->valid = false;
            return;
        }
        list(
            $title, $mode, $pkAlgorithm, $hashAlgorithm,
            $class, $timestamp, $keyFingerprint
        ) = $parts;

        switch (strtoupper($mode[0])) {
        case 'D':
            $this->mode = Crypt_GPG::SIGN_MODE_DETACHED;
            break;
        case 'C':
            $this->mode = Crypt_GPG::SIGN_MODE_CLEAR;
            break;
        case 'S':
            $this->mode = Crypt_GPG::SIGN_MODE_NORMAL;
            break;
        }

        $this->pkAlgorithm    = (int) $pkAlgorithm;
        $this->hashAlgorithm  = (int) $hashAlgorithm;
        $this->class          = $class;
        if (is_numeric($timestamp)) {
            $this->timestamp  = (int) $timestamp;
        } else {
            $this->timestamp  = strtotime($timestamp);
        }
        $this->keyFingerprint = $keyFingerprint;
        $this->valid = true;
    }

    /**
     * Get the signature type
     * - {@link Crypt_GPG::SIGN_MODE_NORMAL}
     * - {@link Crypt_GPG::SIGN_MODE_CLEAR}
     * - {@link Crypt_GPG::SIGN_MODE_DETACHED}
     *
     * @return integer
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Return the public key algorithm used.
     *
     * @return integer
     */
    public function getPkAlgorithm()
    {
        return $this->pkAlgorithm;
    }

    /**
     * Return the hash algorithm used to hash the data to sign.
     *
     * @return integer
     */
    public function getHashAlgorithm()
    {
        return $this->hashAlgorithm;
    }

    /**
     * Get a name for the used hashing algorithm.
     *
     * @return string|null
     */
    public function getHashAlgorithmName()
    {
        if (!isset(self::$hashAlgorithmNames[$this->hashAlgorithm])) {
            return null;
        }
        return self::$hashAlgorithmNames[$this->hashAlgorithm];
    }

    /**
     * Return the timestamp at which the signature was created
     *
     * @return integer
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Return the key's fingerprint
     *
     * @return string
     */
    public function getKeyFingerprint()
    {
        return $this->keyFingerprint;
    }

    /**
     * Tell if the fingerprint line given to the constructor was valid
     *
     * @return boolean
     */
    public function isValid()
    {
        return $this->valid;
    }
}
?>
