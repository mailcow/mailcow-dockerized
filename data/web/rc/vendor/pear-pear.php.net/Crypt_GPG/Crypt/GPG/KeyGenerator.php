<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Crypt_GPG is a package to use GPG from PHP
 *
 * This file contains an object that handles GnuPG key generation.
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * This library is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation; either version 2.1 of the
 * License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, see
 * <http://www.gnu.org/licenses/>
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2011-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 * @link      http://www.gnupg.org/
 */

/**
 * Base class for GPG methods
 */
require_once 'Crypt/GPGAbstract.php';

// {{{ class Crypt_GPG_KeyGenerator

/**
 * GnuPG key generator
 *
 * This class provides an object oriented interface for generating keys with
 * the GNU Privacy Guard (GPG).
 *
 * Secure key generation requires true random numbers, and as such can be slow.
 * If the operating system runs out of entropy, key generation will block until
 * more entropy is available.
 *
 * If quick key generation is important, a hardware entropy generator, or an
 * entropy gathering daemon may be installed. For example, administrators of
 * Debian systems may want to install the 'randomsound' package.
 *
 * This class uses the experimental automated key generation support available
 * in GnuPG. See <b>doc/DETAILS</b> in the
 * {@link http://www.gnupg.org/download/ GPG distribution} for detailed
 * information on the key generation format.
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Nathan Fredrickson <nathan@silverorange.com>
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2005-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 * @link      http://www.gnupg.org/
 */
class Crypt_GPG_KeyGenerator extends Crypt_GPGAbstract
{
    // {{{ protected properties

    /**
     * The expiration date of generated keys
     *
     * @var integer
     *
     * @see Crypt_GPG_KeyGenerator::setExpirationDate()
     */
    protected $expirationDate = 0;

    /**
     * The passphrase of generated keys
     *
     * @var string
     *
     * @see Crypt_GPG_KeyGenerator::setPassphrase()
     */
    protected $passphrase = '';

    /**
     * The algorithm for generated primary keys
     *
     * @var integer
     *
     * @see Crypt_GPG_KeyGenerator::setKeyParams()
     */
    protected $keyAlgorithm = Crypt_GPG_SubKey::ALGORITHM_DSA;

    /**
     * The size of generated primary keys
     *
     * @var integer
     *
     * @see Crypt_GPG_KeyGenerator::setKeyParams()
     */
    protected $keySize = 1024;

    /**
     * The usages of generated primary keys
     *
     * This is a bitwise combination of the usage constants in
     * {@link Crypt_GPG_SubKey}.
     *
     * @var integer
     *
     * @see Crypt_GPG_KeyGenerator::setKeyParams()
     */
    protected $keyUsage = 6; // USAGE_SIGN | USAGE_CERTIFY

    /**
     * The algorithm for generated sub-keys
     *
     * @var integer
     *
     * @see Crypt_GPG_KeyGenerator::setSubKeyParams()
     */
    protected $subKeyAlgorithm = Crypt_GPG_SubKey::ALGORITHM_ELGAMAL_ENC;

    /**
     * The size of generated sub-keys
     *
     * @var integer
     *
     * @see Crypt_GPG_KeyGenerator::setSubKeyParams()
     */
    protected $subKeySize = 2048;

    /**
     * The usages of generated sub-keys
     *
     * This is a bitwise combination of the usage constants in
     * {@link Crypt_GPG_SubKey}.
     *
     * @var integer
     *
     * @see Crypt_GPG_KeyGenerator::setSubKeyParams()
     */
    protected $subKeyUsage = Crypt_GPG_SubKey::USAGE_ENCRYPT;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new GnuPG key generator
     *
     * Available options are:
     *
     * - <kbd>string  homedir</kbd>        - the directory where the GPG
     *                                       keyring files are stored. If not
     *                                       specified, Crypt_GPG uses the
     *                                       default of <kbd>~/.gnupg</kbd>.
     * - <kbd>string  publicKeyring</kbd>  - the file path of the public
     *                                       keyring. Use this if the public
     *                                       keyring is not in the homedir, or
     *                                       if the keyring is in a directory
     *                                       not writable by the process
     *                                       invoking GPG (like Apache). Then
     *                                       you can specify the path to the
     *                                       keyring with this option
     *                                       (/foo/bar/pubring.gpg), and specify
     *                                       a writable directory (like /tmp)
     *                                       using the <i>homedir</i> option.
     * - <kbd>string  privateKeyring</kbd> - the file path of the private
     *                                       keyring. Use this if the private
     *                                       keyring is not in the homedir, or
     *                                       if the keyring is in a directory
     *                                       not writable by the process
     *                                       invoking GPG (like Apache). Then
     *                                       you can specify the path to the
     *                                       keyring with this option
     *                                       (/foo/bar/secring.gpg), and specify
     *                                       a writable directory (like /tmp)
     *                                       using the <i>homedir</i> option.
     * - <kbd>string  trustDb</kbd>        - the file path of the web-of-trust
     *                                       database. Use this if the trust
     *                                       database is not in the homedir, or
     *                                       if the database is in a directory
     *                                       not writable by the process
     *                                       invoking GPG (like Apache). Then
     *                                       you can specify the path to the
     *                                       trust database with this option
     *                                       (/foo/bar/trustdb.gpg), and specify
     *                                       a writable directory (like /tmp)
     *                                       using the <i>homedir</i> option.
     * - <kbd>string  binary</kbd>         - the location of the GPG binary. If
     *                                       not specified, the driver attempts
     *                                       to auto-detect the GPG binary
     *                                       location using a list of known
     *                                       default locations for the current
     *                                       operating system. The option
     *                                       <kbd>gpgBinary</kbd> is a
     *                                       deprecated alias for this option.
     * - <kbd>string  agent</kbd>          - the location of the GnuPG agent
     *                                       binary. The gpg-agent is only
     *                                       used for GnuPG 2.x. If not
     *                                       specified, the engine attempts
     *                                       to auto-detect the gpg-agent
     *                                       binary location using a list of
     *                                       know default locations for the
     *                                       current operating system.
     * - <kbd>mixed debug</kbd>            - whether or not to use debug mode.
     *                                       When debug mode is on, all
     *                                       communication to and from the GPG
     *                                       subprocess is logged. This can be
     *
     * @param array $options optional. An array of options used to create the
     *                       GPG object. All options are optional and are
     *                       represented as key-value pairs.
     *
     * @throws Crypt_GPG_FileException if the <kbd>homedir</kbd> does not exist
     *         and cannot be created. This can happen if <kbd>homedir</kbd> is
     *         not specified, Crypt_GPG is run as the web user, and the web
     *         user has no home directory. This exception is also thrown if any
     *         of the options <kbd>publicKeyring</kbd>,
     *         <kbd>privateKeyring</kbd> or <kbd>trustDb</kbd> options are
     *         specified but the files do not exist or are are not readable.
     *         This can happen if the user running the Crypt_GPG process (for
     *         example, the Apache user) does not have permission to read the
     *         files.
     *
     * @throws PEAR_Exception if the provided <kbd>binary</kbd> is invalid, or
     *         if no <kbd>binary</kbd> is provided and no suitable binary could
     *         be found.
     *
     * @throws PEAR_Exception if the provided <kbd>agent</kbd> is invalid, or
     *         if no <kbd>agent</kbd> is provided and no suitable gpg-agent
     *         cound be found.
     */
    public function __construct(array $options = array())
    {
        parent::__construct($options);
    }

    // }}}
    // {{{ setExpirationDate()

    /**
     * Sets the expiration date of generated keys
     *
     * @param string|integer $date either a string that may be parsed by
     *                             PHP's strtotime() function, or an integer
     *                             timestamp representing the number of seconds
     *                             since the UNIX epoch. This date must be at
     *                             least one date in the future. Keys that
     *                             expire in the past may not be generated. Use
     *                             an expiration date of 0 for keys that do not
     *                             expire.
     *
     * @throws InvalidArgumentException if the date is not a valid format, or
     *                                  if the date is not at least one day in
     *                                  the future, or if the date is greater
     *                                  than 2038-01-19T03:14:07.
     *
     * @return Crypt_GPG_KeyGenerator the current object, for fluent interface.
     */
    public function setExpirationDate($date)
    {
        if (is_int($date) || ctype_digit(strval($date))) {
            $expirationDate = intval($date);
        } else {
            $expirationDate = strtotime($date);
        }

        if ($expirationDate === false) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid expiration date format: "%s". Please use a ' .
                    'format compatible with PHP\'s strtotime().',
                    $date
                )
            );
        }

        if ($expirationDate !== 0 && $expirationDate < time() + 86400) {
            throw new InvalidArgumentException(
                'Expiration date must be at least a day in the future.'
            );
        }

        // GnuPG suffers from the 2038 bug
        if ($expirationDate > 2147483647) {
            throw new InvalidArgumentException(
                'Expiration date must not be greater than 2038-01-19T03:14:07.'
            );
        }

        $this->expirationDate = $expirationDate;

        return $this;
    }

    // }}}
    // {{{ setPassphrase()

    /**
     * Sets the passphrase of generated keys
     *
     * @param string $passphrase the passphrase to use for generated keys. Use
     *                           null or an empty string for no passphrase.
     *
     * @return Crypt_GPG_KeyGenerator the current object, for fluent interface.
     */
    public function setPassphrase($passphrase)
    {
        $this->passphrase = strval($passphrase);
        return $this;
    }

    // }}}
    // {{{ setKeyParams()

    /**
     * Sets the parameters for the primary key of generated key-pairs
     *
     * @param integer $algorithm the algorithm used by the key. This should be
     *                           one of the Crypt_GPG_SubKey::ALGORITHM_*
     *                           constants.
     * @param integer $size      optional. The size of the key. Different
     *                           algorithms have different size requirements.
     *                           If not specified, the default size for the
     *                           specified algorithm will be used. If an
     *                           invalid key size is used, GnuPG will do its
     *                           best to round it to a valid size.
     * @param integer $usage     optional. A bitwise combination of key usages.
     *                           If not specified, the primary key will be used
     *                           only to sign and certify. This is the default
     *                           behavior of GnuPG in interactive mode. Use
     *                           the Crypt_GPG_SubKey::USAGE_* constants here.
     *                           The primary key may be used to certify even
     *                           if the certify usage is not specified.
     *
     * @return Crypt_GPG_KeyGenerator the current object, for fluent interface.
     */
    public function setKeyParams($algorithm, $size = 0, $usage = 0)
    {
        $algorithm = intval($algorithm);

        if ($algorithm === Crypt_GPG_SubKey::ALGORITHM_ELGAMAL_ENC) {
            throw new Crypt_GPG_InvalidKeyParamsException(
                'Primary key algorithm must be capable of signing. The ' .
                'Elgamal algorithm can only encrypt.',
                0,
                $algorithm,
                $size,
                $usage
            );
        }

        if ($size != 0) {
            $size = intval($size);
        }

        if ($usage != 0) {
            $usage = intval($usage);
        }

        $usageEncrypt = Crypt_GPG_SubKey::USAGE_ENCRYPT;

        if ($algorithm === Crypt_GPG_SubKey::ALGORITHM_DSA
            && ($usage & $usageEncrypt) === $usageEncrypt
        ) {
            throw new Crypt_GPG_InvalidKeyParamsException(
                'The DSA algorithm is not capable of encrypting. Please ' .
                'specify a different algorithm or do not include encryption ' .
                'as a usage for the primary key.',
                0,
                $algorithm,
                $size,
                $usage
            );
        }

        $this->keyAlgorithm = $algorithm;

        if ($size != 0) {
            $this->keySize = $size;
        }

        if ($usage != 0) {
            $this->keyUsage = $usage;
        }

        return $this;
    }

    // }}}
    // {{{ setSubKeyParams()

    /**
     * Sets the parameters for the sub-key of generated key-pairs
     *
     * @param integer $algorithm the algorithm used by the key. This should be
     *                           one of the Crypt_GPG_SubKey::ALGORITHM_*
     *                           constants.
     * @param integer $size      optional. The size of the key. Different
     *                           algorithms have different size requirements.
     *                           If not specified, the default size for the
     *                           specified algorithm will be used. If an
     *                           invalid key size is used, GnuPG will do its
     *                           best to round it to a valid size.
     * @param integer $usage     optional. A bitwise combination of key usages.
     *                           If not specified, the sub-key will be used
     *                           only to encrypt. This is the default behavior
     *                           of GnuPG in interactive mode. Use the
     *                           Crypt_GPG_SubKey::USAGE_* constants here.
     *
     * @return Crypt_GPG_KeyGenerator the current object, for fluent interface.
     */
    public function setSubKeyParams($algorithm, $size = '', $usage = 0)
    {
        $algorithm = intval($algorithm);

        if ($size != 0) {
            $size = intval($size);
        }

        if ($usage != 0) {
            $usage = intval($usage);
        }

        $usageSign = Crypt_GPG_SubKey::USAGE_SIGN;

        if ($algorithm === Crypt_GPG_SubKey::ALGORITHM_ELGAMAL_ENC
            && ($usage & $usageSign) === $usageSign
        ) {
            throw new Crypt_GPG_InvalidKeyParamsException(
                'The Elgamal algorithm is not capable of signing. Please ' .
                'specify a different algorithm or do not include signing ' .
                'as a usage for the sub-key.',
                0,
                $algorithm,
                $size,
                $usage
            );
        }

        $usageEncrypt = Crypt_GPG_SubKey::USAGE_ENCRYPT;

        if ($algorithm === Crypt_GPG_SubKey::ALGORITHM_DSA
            && ($usage & $usageEncrypt) === $usageEncrypt
        ) {
            throw new Crypt_GPG_InvalidKeyParamsException(
                'The DSA algorithm is not capable of encrypting. Please ' .
                'specify a different algorithm or do not include encryption ' .
                'as a usage for the sub-key.',
                0,
                $algorithm,
                $size,
                $usage
            );
        }

        $this->subKeyAlgorithm = $algorithm;

        if ($size != 0) {
            $this->subKeySize = $size;
        }

        if ($usage != 0) {
            $this->subKeyUsage = $usage;
        }

        return $this;
    }

    // }}}
    // {{{ generateKey()

    /**
     * Generates a new key-pair in the current keyring
     *
     * Secure key generation requires true random numbers, and as such can be
     * solw. If the operating system runs out of entropy, key generation will
     * block until more entropy is available.
     *
     * If quick key generation is important, a hardware entropy generator, or
     * an entropy gathering daemon may be installed. For example,
     * administrators of Debian systems may want to install the 'randomsound'
     * package.
     *
     * @param string|Crypt_GPG_UserId $name    either a {@link Crypt_GPG_UserId}
     *                                         object, or a string containing
     *                                         the name of the user id.
     * @param string                  $email   optional. If <i>$name</i> is
     *                                         specified as a string, this is
     *                                         the email address of the user id.
     * @param string                  $comment optional. If <i>$name</i> is
     *                                         specified as a string, this is
     *                                         the comment of the user id.
     *
     * @return Crypt_GPG_Key the newly generated key.
     *
     * @throws Crypt_GPG_KeyNotCreatedException if the key parameters are
     *         incorrect, if an unknown error occurs during key generation, or
     *         if the newly generated key is not found in the keyring.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function generateKey($name, $email = '', $comment = '')
    {
        $handle = uniqid('key', true);

        $userId = $this->getUserId($name, $email, $comment);

        $keyParams = array(
            'Key-Type'      => $this->keyAlgorithm,
            'Key-Length'    => $this->keySize,
            'Key-Usage'     => $this->getUsage($this->keyUsage),
            'Subkey-Type'   => $this->subKeyAlgorithm,
            'Subkey-Length' => $this->subKeySize,
            'Subkey-Usage'  => $this->getUsage($this->subKeyUsage),
            'Name-Real'     => $userId->getName(),
            'Handle'        => $handle,
        );

        if ($this->expirationDate != 0) {
            // GnuPG only accepts granularity of days
            $expirationDate = date('Y-m-d', $this->expirationDate);
            $keyParams['Expire-Date'] = $expirationDate;
        }

        if (strlen($this->passphrase)) {
            $keyParams['Passphrase'] = $this->passphrase;
        }

        if ($userId->getEmail() != '') {
            $keyParams['Name-Email'] = $userId->getEmail();
        }

        if ($userId->getComment() != '') {
            $keyParams['Name-Comment'] = $userId->getComment();
        }

        $keyParamsFormatted = array();
        foreach ($keyParams as $name => $value) {
            $keyParamsFormatted[] = $name . ': ' . $value;
        }

        // This is required in GnuPG 2.1
        if (!strlen($this->passphrase)) {
            $keyParamsFormatted[] = '%no-protection';
        }

        $input = implode("\n", $keyParamsFormatted) . "\n%commit\n";

        $this->engine->reset();
        $this->engine->setProcessData('Handle', $handle);
        $this->engine->setInput($input);
        $this->engine->setOutput($output);
        $this->engine->setOperation('--gen-key', array('--batch'));

        try {
            $this->engine->run();
        } catch (Crypt_GPG_InvalidKeyParamsException $e) {
            switch ($this->engine->getProcessData('LineNumber')) {
            case 1:
                throw new Crypt_GPG_InvalidKeyParamsException(
                    'Invalid primary key algorithm specified.',
                    0,
                    $this->keyAlgorithm,
                    $this->keySize,
                    $this->keyUsage
                );
            case 4:
                throw new Crypt_GPG_InvalidKeyParamsException(
                    'Invalid sub-key algorithm specified.',
                    0,
                    $this->subKeyAlgorithm,
                    $this->subKeySize,
                    $this->subKeyUsage
                );
            default:
                throw $e;
            }
        }

        $fingerprint = $this->engine->getProcessData('KeyCreated');
        $keys        = $this->_getKeys($fingerprint);

        if (count($keys) === 0) {
            throw new Crypt_GPG_KeyNotCreatedException(
                sprintf(
                    'Newly created key "%s" not found in keyring.',
                    $fingerprint
                )
            );
        }

        return $keys[0];
    }

    // }}}
    // {{{ getUsage()

    /**
     * Builds a GnuPG key usage string suitable for key generation
     *
     * See <b>doc/DETAILS</b> in the
     * {@link http://www.gnupg.org/download/ GPG distribution} for detailed
     * information on the key usage format.
     *
     * @param integer $usage a bitwise combination of the key usages. This is
     *                       a combination of the Crypt_GPG_SubKey::USAGE_*
     *                       constants.
     *
     * @return string the key usage string.
     */
    protected function getUsage($usage)
    {
        $map = array(
            Crypt_GPG_SubKey::USAGE_ENCRYPT        => 'encrypt',
            Crypt_GPG_SubKey::USAGE_SIGN           => 'sign',
            Crypt_GPG_SubKey::USAGE_CERTIFY        => 'cert',
            Crypt_GPG_SubKey::USAGE_AUTHENTICATION => 'auth',
        );

        // cert is always used for primary keys and does not need to be
        // specified
        $usage &= ~Crypt_GPG_SubKey::USAGE_CERTIFY;

        $usageArray = array();

        foreach ($map as $key => $value) {
            if (($usage & $key) === $key) {
                $usageArray[] = $value;
            }
        }

        return implode(',', $usageArray);
    }

    // }}}
    // {{{ getUserId()

    /**
     * Gets a user id object from parameters
     *
     * @param string|Crypt_GPG_UserId $name    either a {@link Crypt_GPG_UserId}
     *                                         object, or a string containing
     *                                         the name of the user id.
     * @param string                  $email   optional. If <i>$name</i> is
     *                                         specified as a string, this is
     *                                         the email address of the user id.
     * @param string                  $comment optional. If <i>$name</i> is
     *                                         specified as a string, this is
     *                                         the comment of the user id.
     *
     * @return Crypt_GPG_UserId a user id object for the specified parameters.
     */
    protected function getUserId($name, $email = '', $comment = '')
    {
        if ($name instanceof Crypt_GPG_UserId) {
            $userId = $name;
        } else {
            $userId = new Crypt_GPG_UserId();
            $userId->setName($name)->setEmail($email)->setComment($comment);
        }

        return $userId;
    }

    // }}}
}

// }}}

?>
