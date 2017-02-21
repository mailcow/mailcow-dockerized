<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Crypt_GPG is a package to use GPG from PHP
 *
 * This package provides an object oriented interface to GNU Privacy
 * Guard (GPG). It requires the GPG executable to be on the system.
 *
 * Though GPG can support symmetric-key cryptography, this package is intended
 * only to facilitate public-key cryptography.
 *
 * This file contains the main GPG class. The class in this file lets you
 * encrypt, decrypt, sign and verify data; import and delete keys; and perform
 * other useful GPG tasks.
 *
 * Example usage:
 * <code>
 * <?php
 * // encrypt some data
 * $gpg = new Crypt_GPG();
 * $gpg->addEncryptKey($mySecretKeyId);
 * $encryptedData = $gpg->encrypt($data);
 * ?>
 * </code>
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
 * @author    Nathan Fredrickson <nathan@silverorange.com>
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2005-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 * @link      http://pear.php.net/manual/en/package.encryption.crypt-gpg.php
 * @link      http://www.gnupg.org/
 */

/**
 * Base class for GPG methods
 */
require_once 'Crypt/GPGAbstract.php';

/**
 * GPG exception classes.
 */
require_once 'Crypt/GPG/Exceptions.php';

// {{{ class Crypt_GPG

/**
 * A class to use GPG from PHP
 *
 * This class provides an object oriented interface to GNU Privacy Guard (GPG).
 *
 * Though GPG can support symmetric-key cryptography, this class is intended
 * only to facilitate public-key cryptography.
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
class Crypt_GPG extends Crypt_GPGAbstract
{
    // {{{ class constants for data signing modes

    /**
     * Signing mode for normal signing of data. The signed message will not
     * be readable without special software.
     *
     * This is the default signing mode.
     *
     * @see Crypt_GPG::sign()
     * @see Crypt_GPG::signFile()
     */
    const SIGN_MODE_NORMAL = 1;

    /**
     * Signing mode for clearsigning data. Clearsigned signatures are ASCII
     * armored data and are readable without special software. If the signed
     * message is unencrypted, the message will still be readable. The message
     * text will be in the original encoding.
     *
     * @see Crypt_GPG::sign()
     * @see Crypt_GPG::signFile()
     */
    const SIGN_MODE_CLEAR = 2;

    /**
     * Signing mode for creating a detached signature. When using detached
     * signatures, only the signature data is returned. The original message
     * text may be distributed separately from the signature data. This is
     * useful for miltipart/signed email messages as per
     * {@link http://www.ietf.org/rfc/rfc3156.txt RFC 3156}.
     *
     * @see Crypt_GPG::sign()
     * @see Crypt_GPG::signFile()
     */
    const SIGN_MODE_DETACHED = 3;

    // }}}
    // {{{ class constants for fingerprint formats

    /**
     * No formatting is performed.
     *
     * Example: C3BC615AD9C766E5A85C1F2716D27458B1BBA1C4
     *
     * @see Crypt_GPG::getFingerprint()
     */
    const FORMAT_NONE = 1;

    /**
     * Fingerprint is formatted in the format used by the GnuPG gpg command's
     * default output.
     *
     * Example: C3BC 615A D9C7 66E5 A85C  1F27 16D2 7458 B1BB A1C4
     *
     * @see Crypt_GPG::getFingerprint()
     */
    const FORMAT_CANONICAL = 2;

    /**
     * Fingerprint is formatted in the format used when displaying X.509
     * certificates
     *
     * Example: C3:BC:61:5A:D9:C7:66:E5:A8:5C:1F:27:16:D2:74:58:B1:BB:A1:C4
     *
     * @see Crypt_GPG::getFingerprint()
     */
    const FORMAT_X509 = 3;

    // }}}
    // {{{ class constants for boolean options

    /**
     * Use to specify ASCII armored mode for returned data
     */
    const ARMOR_ASCII = true;

    /**
     * Use to specify binary mode for returned data
     */
    const ARMOR_BINARY = false;

    /**
     * Use to specify that line breaks in signed text should be normalized
     */
    const TEXT_NORMALIZED = true;

    /**
     * Use to specify that line breaks in signed text should not be normalized
     */
    const TEXT_RAW = false;

    // }}}
    // {{{ protected class properties

    /**
     * Keys used to encrypt
     *
     * The array is of the form:
     * <code>
     * array(
     *   $key_id => array(
     *     'fingerprint' => $fingerprint,
     *     'passphrase'  => null
     *   )
     * );
     * </code>
     *
     * @var array
     * @see Crypt_GPG::addEncryptKey()
     * @see Crypt_GPG::clearEncryptKeys()
     */
    protected $encryptKeys = array();

    /**
     * Keys used to decrypt
     *
     * The array is of the form:
     * <code>
     * array(
     *   $key_id => array(
     *     'fingerprint' => $fingerprint,
     *     'passphrase'  => $passphrase
     *   )
     * );
     * </code>
     *
     * @var array
     * @see Crypt_GPG::addSignKey()
     * @see Crypt_GPG::clearSignKeys()
     */
    protected $signKeys = array();

    /**
     * Keys used to sign
     *
     * The array is of the form:
     * <code>
     * array(
     *   $key_id => array(
     *     'fingerprint' => $fingerprint,
     *     'passphrase'  => $passphrase
     *   )
     * );
     * </code>
     *
     * @var array
     * @see Crypt_GPG::addDecryptKey()
     * @see Crypt_GPG::clearDecryptKeys()
     */
    protected $decryptKeys = array();

    /**
     * Passphrases used on import/export of private keys in GnuPG 2.1
     *
     * The array is of the form:
     * <code>
     * array($key_id => $passphrase);
     * </code>
     *
     * @var array
     * @see Crypt_GPG::addPassphrase()
     * @see Crypt_GPG::clearPassphrases()
     */
    protected $passphrases = array();

    // }}}
    // {{{ importKey()

    /**
     * Imports a public or private key into the keyring
     *
     * Keys may be removed from the keyring using
     * {@link Crypt_GPG::deletePublicKey()} or
     * {@link Crypt_GPG::deletePrivateKey()}.
     *
     * @param string $data the key data to be imported.
     *
     * @return array an associative array containing the following elements:
     *               - <kbd>fingerprint</kbd>       - the fingerprint of the
     *                                                imported key,
     *               - <kbd>public_imported</kbd>   - the number of public
     *                                                keys imported,
     *               - <kbd>public_unchanged</kbd>  - the number of unchanged
     *                                                public keys,
     *               - <kbd>private_imported</kbd>  - the number of private
     *                                                keys imported,
     *               - <kbd>private_unchanged</kbd> - the number of unchanged
     *                                                private keys.
     *
     * @throws Crypt_GPG_NoDataException if the key data is missing or if the
     *         data is is not valid key data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addPassphrase()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG::addPassphrase()
     * @see Crypt_GPG::clearPassphrases()
     */
    public function importKey($data)
    {
        return $this->_importKey($data, false);
    }

    // }}}
    // {{{ importKeyFile()

    /**
     * Imports a public or private key file into the keyring
     *
     * Keys may be removed from the keyring using
     * {@link Crypt_GPG::deletePublicKey()} or
     * {@link Crypt_GPG::deletePrivateKey()}.
     *
     * @param string $filename the key file to be imported.
     *
     * @return array an associative array containing the following elements:
     *               - <kbd>fingerprint</kbd>       - the fingerprint of the
     *                                                imported key,
     *               - <kbd>public_imported</kbd>   - the number of public
     *                                                keys imported,
     *               - <kbd>public_unchanged</kbd>  - the number of unchanged
     *                                                public keys,
     *               - <kbd>private_imported</kbd>  - the number of private
     *                                                keys imported,
     *               - <kbd>private_unchanged</kbd> - the number of unchanged
     *                                                private keys.
     *                                                  private keys.
     *
     * @throws Crypt_GPG_NoDataException if the key data is missing or if the
     *         data is is not valid key data.
     *
     * @throws Crypt_GPG_FileException if the key file is not readable.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addPassphrase()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function importKeyFile($filename)
    {
        return $this->_importKey($filename, true);
    }

    // }}}
    // {{{ exportPrivateKey()

    /**
     * Exports a private key from the keyring
     *
     * The exported key remains on the keyring. To delete the key, use
     * {@link Crypt_GPG::deletePrivateKey()}.
     *
     * If more than one key fingerprint is available for the specified
     * <kbd>$keyId</kbd> (for example, if you use a non-unique uid) only the
     * first private key is exported.
     *
     * @param string  $keyId either the full uid of the private key, the email
     *                       part of the uid of the private key or the key id of
     *                       the private key. For example,
     *                       "Test User (example) <test@example.com>",
     *                       "test@example.com" or a hexadecimal string.
     * @param boolean $armor optional. If true, ASCII armored data is returned;
     *                       otherwise, binary data is returned. Defaults to
     *                       true.
     *
     * @return string the private key data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if a private key with the given
     *         <kbd>$keyId</kbd> is not found.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addPassphrase()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function exportPrivateKey($keyId, $armor = true)
    {
        return $this->_exportKey($keyId, $armor, true);
    }

    // }}}
    // {{{ exportPublicKey()

    /**
     * Exports a public key from the keyring
     *
     * The exported key remains on the keyring. To delete the public key, use
     * {@link Crypt_GPG::deletePublicKey()}.
     *
     * If more than one key fingerprint is available for the specified
     * <kbd>$keyId</kbd> (for example, if you use a non-unique uid) only the
     * first public key is exported.
     *
     * @param string  $keyId either the full uid of the public key, the email
     *                       part of the uid of the public key or the key id of
     *                       the public key. For example,
     *                       "Test User (example) <test@example.com>",
     *                       "test@example.com" or a hexadecimal string.
     * @param boolean $armor optional. If true, ASCII armored data is returned;
     *                       otherwise, binary data is returned. Defaults to
     *                       true.
     *
     * @return string the public key data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if a public key with the given
     *         <kbd>$keyId</kbd> is not found.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function exportPublicKey($keyId, $armor = true)
    {
        return $this->_exportKey($keyId, $armor, false);
    }

    // }}}
    // {{{ deletePublicKey()

    /**
     * Deletes a public key from the keyring
     *
     * If more than one key fingerprint is available for the specified
     * <kbd>$keyId</kbd> (for example, if you use a non-unique uid) only the
     * first public key is deleted.
     *
     * The private key must be deleted first or an exception will be thrown.
     * In GnuPG >= 2.1 this limitation does not exist.
     * See {@link Crypt_GPG::deletePrivateKey()}.
     *
     * @param string $keyId either the full uid of the public key, the email
     *                      part of the uid of the public key or the key id of
     *                      the public key. For example,
     *                      "Test User (example) <test@example.com>",
     *                      "test@example.com" or a hexadecimal string.
     *
     * @return void
     *
     * @throws Crypt_GPG_KeyNotFoundException if a public key with the given
     *         <kbd>$keyId</kbd> is not found.
     *
     * @throws Crypt_GPG_DeletePrivateKeyException if the specified public key
     *         has an associated private key on the keyring. The private key
     *         must be deleted first (when using GnuPG < 2.1).
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function deletePublicKey($keyId)
    {
        $fingerprint = $this->getFingerprint($keyId);

        if ($fingerprint === null) {
            throw new Crypt_GPG_KeyNotFoundException(
                'Public key not found: ' . $keyId,
                self::ERROR_KEY_NOT_FOUND,
                $keyId
            );
        }

        $operation = '--delete-key ' . escapeshellarg($fingerprint);
        $arguments = array(
            '--batch',
            '--yes'
        );

        $this->engine->reset();
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();
    }

    // }}}
    // {{{ deletePrivateKey()

    /**
     * Deletes a private key from the keyring
     *
     * If more than one key fingerprint is available for the specified
     * <kbd>$keyId</kbd> (for example, if you use a non-unique uid) only the
     * first private key is deleted.
     *
     * Calls GPG with the <kbd>--delete-secret-key</kbd> command.
     *
     * @param string $keyId either the full uid of the private key, the email
     *                      part of the uid of the private key or the key id of
     *                      the private key. For example,
     *                      "Test User (example) <test@example.com>",
     *                      "test@example.com" or a hexadecimal string.
     *
     * @return void
     *
     * @throws Crypt_GPG_KeyNotFoundException if a private key with the given
     *         <kbd>$keyId</kbd> is not found.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function deletePrivateKey($keyId)
    {
        $fingerprint = $this->getFingerprint($keyId);

        if ($fingerprint === null) {
            throw new Crypt_GPG_KeyNotFoundException(
                'Private key not found: ' . $keyId,
                self::ERROR_KEY_NOT_FOUND,
                $keyId
            );
        }

        $operation = '--delete-secret-key ' . escapeshellarg($fingerprint);
        $arguments = array(
            '--batch',
            '--yes'
        );

        $this->engine->reset();
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();
    }

    // }}}
    // {{{ getKeys()

    /**
     * Gets the available keys in the keyring
     *
     * Calls GPG with the <kbd>--list-keys</kbd> command and grabs keys. See
     * the first section of <b>doc/DETAILS</b> in the
     * {@link http://www.gnupg.org/download/ GPG package} for a detailed
     * description of how the GPG command output is parsed.
     *
     * @param string $keyId optional. Only keys with that match the specified
     *                      pattern are returned. The pattern may be part of
     *                      a user id, a key id or a key fingerprint. If not
     *                      specified, all keys are returned.
     *
     * @return array an array of {@link Crypt_GPG_Key} objects. If no keys
     *               match the specified <kbd>$keyId</kbd> an empty array is
     *               returned.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG_Key
     */
    public function getKeys($keyId = '')
    {
        return parent::_getKeys($keyId);
    }

    // }}}
    // {{{ getFingerprint()

    /**
     * Gets a key fingerprint from the keyring
     *
     * If more than one key fingerprint is available (for example, if you use
     * a non-unique user id) only the first key fingerprint is returned.
     *
     * Calls the GPG <kbd>--list-keys</kbd> command with the
     * <kbd>--with-fingerprint</kbd> option to retrieve a public key
     * fingerprint.
     *
     * @param string  $keyId  either the full user id of the key, the email
     *                        part of the user id of the key, or the key id of
     *                        the key. For example,
     *                        "Test User (example) <test@example.com>",
     *                        "test@example.com" or a hexadecimal string.
     * @param integer $format optional. How the fingerprint should be formatted.
     *                        Use {@link Crypt_GPG::FORMAT_X509} for X.509
     *                        certificate format,
     *                        {@link Crypt_GPG::FORMAT_CANONICAL} for the format
     *                        used by GnuPG output and
     *                        {@link Crypt_GPG::FORMAT_NONE} for no formatting.
     *                        Defaults to <code>Crypt_GPG::FORMAT_NONE</code>.
     *
     * @return string the fingerprint of the key, or null if no fingerprint
     *                is found for the given <kbd>$keyId</kbd>.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function getFingerprint($keyId, $format = self::FORMAT_NONE)
    {
        $output    = '';
        $operation = '--list-keys ' . escapeshellarg($keyId);
        $arguments = array(
            '--with-colons',
            '--with-fingerprint'
        );

        $this->engine->reset();
        $this->engine->setOutput($output);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        $fingerprint = null;

        foreach (explode(PHP_EOL, $output) as $line) {
            if (mb_substr($line, 0, 3, '8bit') == 'fpr') {
                $lineExp     = explode(':', $line);
                $fingerprint = $lineExp[9];

                switch ($format) {
                case self::FORMAT_CANONICAL:
                    $fingerprintExp = str_split($fingerprint, 4);
                    $format         = '%s %s %s %s %s  %s %s %s %s %s';
                    $fingerprint    = vsprintf($format, $fingerprintExp);
                    break;

                case self::FORMAT_X509:
                    $fingerprintExp = str_split($fingerprint, 2);
                    $fingerprint    = implode(':', $fingerprintExp);
                    break;
                }

                break;
            }
        }

        return $fingerprint;
    }

    // }}}
    // {{{ getLastSignatureInfo()

    /**
     * Get information about the last signature that was created.
     *
     * @return Crypt_GPG_SignatureCreationInfo
     */
    public function getLastSignatureInfo()
    {
        return $this->engine->getProcessData('SignatureInfo');
    }

    // }}}
    // {{{ encrypt()

    /**
     * Encrypts string data
     *
     * Data is ASCII armored by default but may optionally be returned as
     * binary.
     *
     * @param string  $data  the data to be encrypted.
     * @param boolean $armor optional. If true, ASCII armored data is returned;
     *                       otherwise, binary data is returned. Defaults to
     *                       true.
     *
     * @return string the encrypted data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no encryption key is specified.
     *         See {@link Crypt_GPG::addEncryptKey()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     *
     * @sensitive $data
     */
    public function encrypt($data, $armor = self::ARMOR_ASCII)
    {
        return $this->_encrypt($data, false, null, $armor);
    }

    // }}}
    // {{{ encryptFile()

    /**
     * Encrypts a file
     *
     * Encrypted data is ASCII armored by default but may optionally be saved
     * as binary.
     *
     * @param string  $filename      the filename of the file to encrypt.
     * @param string  $encryptedFile optional. The filename of the file in
     *                               which to store the encrypted data. If null
     *                               or unspecified, the encrypted data is
     *                               returned as a string.
     * @param boolean $armor         optional. If true, ASCII armored data is
     *                               returned; otherwise, binary data is
     *                               returned. Defaults to true.
     *
     * @return void|string if the <kbd>$encryptedFile</kbd> parameter is null,
     *                     a string containing the encrypted data is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no encryption key is specified.
     *         See {@link Crypt_GPG::addEncryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function encryptFile(
        $filename,
        $encryptedFile = null,
        $armor = self::ARMOR_ASCII
    ) {
        return $this->_encrypt($filename, true, $encryptedFile, $armor);
    }

    // }}}
    // {{{ encryptAndSign()

    /**
     * Encrypts and signs data
     *
     * Data is encrypted and signed in a single pass.
     *
     * NOTE: Until GnuPG version 1.4.10, it was not possible to verify
     * encrypted-signed data without decrypting it at the same time. If you try
     * to use {@link Crypt_GPG::verify()} method on encrypted-signed data with
     * earlier GnuPG versions, you will get an error. Please use
     * {@link Crypt_GPG::decryptAndVerify()} to verify encrypted-signed data.
     *
     * @param string  $data  the data to be encrypted and signed.
     * @param boolean $armor optional. If true, ASCII armored data is returned;
     *                       otherwise, binary data is returned. Defaults to
     *                       true.
     *
     * @return string the encrypted signed data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no encryption key is specified
     *         or if no signing key is specified. See
     *         {@link Crypt_GPG::addEncryptKey()} and
     *         {@link Crypt_GPG::addSignKey()}.
     *
     * @throws Crypt_GPG_BadPassphraseException if a specified passphrase is
     *         incorrect or if a required passphrase is not specified.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG::decryptAndVerify()
     */
    public function encryptAndSign($data, $armor = self::ARMOR_ASCII)
    {
        return $this->_encryptAndSign($data, false, null, $armor);
    }

    // }}}
    // {{{ encryptAndSignFile()

    /**
     * Encrypts and signs a file
     *
     * The file is encrypted and signed in a single pass.
     *
     * NOTE: Until GnuPG version 1.4.10, it was not possible to verify
     * encrypted-signed files without decrypting them at the same time. If you
     * try to use {@link Crypt_GPG::verify()} method on encrypted-signed files
     * with earlier GnuPG versions, you will get an error. Please use
     * {@link Crypt_GPG::decryptAndVerifyFile()} to verify encrypted-signed
     * files.
     *
     * @param string  $filename   the name of the file containing the data to
     *                            be encrypted and signed.
     * @param string  $signedFile optional. The name of the file in which the
     *                            encrypted, signed data should be stored. If
     *                            null or unspecified, the encrypted, signed
     *                            data is returned as a string.
     * @param boolean $armor      optional. If true, ASCII armored data is
     *                            returned; otherwise, binary data is returned.
     *                            Defaults to true.
     *
     * @return void|string if the <kbd>$signedFile</kbd> parameter is null, a
     *                     string containing the encrypted, signed data is
     *                     returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no encryption key is specified
     *         or if no signing key is specified. See
     *         {@link Crypt_GPG::addEncryptKey()} and
     *         {@link Crypt_GPG::addSignKey()}.
     *
     * @throws Crypt_GPG_BadPassphraseException if a specified passphrase is
     *         incorrect or if a required passphrase is not specified.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG::decryptAndVerifyFile()
     */
    public function encryptAndSignFile(
        $filename,
        $signedFile = null,
        $armor = self::ARMOR_ASCII
    ) {
        return $this->_encryptAndSign($filename, true, $signedFile, $armor);
    }

    // }}}
    // {{{ decrypt()

    /**
     * Decrypts string data
     *
     * This method assumes the required private key is available in the keyring
     * and throws an exception if the private key is not available. To add a
     * private key to the keyring, use the {@link Crypt_GPG::importKey()} or
     * {@link Crypt_GPG::importKeyFile()} methods.
     *
     * @param string $encryptedData the data to be decrypted.
     *
     * @return string the decrypted data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if the private key needed to
     *         decrypt the data is not in the user's keyring.
     *
     * @throws Crypt_GPG_NoDataException if specified data does not contain
     *         GPG encrypted data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addDecryptKey()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function decrypt($encryptedData)
    {
        return $this->_decrypt($encryptedData, false, null);
    }

    // }}}
    // {{{ decryptFile()

    /**
     * Decrypts a file
     *
     * This method assumes the required private key is available in the keyring
     * and throws an exception if the private key is not available. To add a
     * private key to the keyring, use the {@link Crypt_GPG::importKey()} or
     * {@link Crypt_GPG::importKeyFile()} methods.
     *
     * @param string $encryptedFile the name of the encrypted file data to
     *                              decrypt.
     * @param string $decryptedFile optional. The name of the file to which the
     *                              decrypted data should be written. If null
     *                              or unspecified, the decrypted data is
     *                              returned as a string.
     *
     * @return void|string if the <kbd>$decryptedFile</kbd> parameter is null,
     *                     a string containing the decrypted data is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if the private key needed to
     *         decrypt the data is not in the user's keyring.
     *
     * @throws Crypt_GPG_NoDataException if specified data does not contain
     *         GPG encrypted data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addDecryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function decryptFile($encryptedFile, $decryptedFile = null)
    {
        return $this->_decrypt($encryptedFile, true, $decryptedFile);
    }

    // }}}
    // {{{ decryptAndVerify()

    /**
     * Decrypts and verifies string data
     *
     * This method assumes the required private key is available in the keyring
     * and throws an exception if the private key is not available. To add a
     * private key to the keyring, use the {@link Crypt_GPG::importKey()} or
     * {@link Crypt_GPG::importKeyFile()} methods.
     *
     * @param string $encryptedData the encrypted, signed data to be decrypted
     *                              and verified.
     *
     * @return array two element array. The array has an element 'data'
     *               containing the decrypted data and an element
     *               'signatures' containing an array of
     *               {@link Crypt_GPG_Signature} objects for the signed data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if the private key needed to
     *         decrypt the data is not in the user's keyring.
     *
     * @throws Crypt_GPG_NoDataException if specified data does not contain
     *         GPG encrypted data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addDecryptKey()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function decryptAndVerify($encryptedData)
    {
        return $this->_decryptAndVerify($encryptedData, false, null);
    }

    // }}}
    // {{{ decryptAndVerifyFile()

    /**
     * Decrypts and verifies a signed, encrypted file
     *
     * This method assumes the required private key is available in the keyring
     * and throws an exception if the private key is not available. To add a
     * private key to the keyring, use the {@link Crypt_GPG::importKey()} or
     * {@link Crypt_GPG::importKeyFile()} methods.
     *
     * @param string $encryptedFile the name of the signed, encrypted file to
     *                              to decrypt and verify.
     * @param string $decryptedFile optional. The name of the file to which the
     *                              decrypted data should be written. If null
     *                              or unspecified, the decrypted data is
     *                              returned in the results array.
     *
     * @return array two element array. The array has an element 'data'
     *               containing the decrypted data and an element
     *               'signatures' containing an array of
     *               {@link Crypt_GPG_Signature} objects for the signed data.
     *               If the decrypted data is written to a file, the 'data'
     *               element is null.
     *
     * @throws Crypt_GPG_KeyNotFoundException if the private key needed to
     *         decrypt the data is not in the user's keyring.
     *
     * @throws Crypt_GPG_NoDataException if specified data does not contain
     *         GPG encrypted data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addDecryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function decryptAndVerifyFile($encryptedFile, $decryptedFile = null)
    {
        return $this->_decryptAndVerify($encryptedFile, true, $decryptedFile);
    }

    // }}}
    // {{{ sign()

    /**
     * Signs data
     *
     * Data may be signed using any one of the three available signing modes:
     * - {@link Crypt_GPG::SIGN_MODE_NORMAL}
     * - {@link Crypt_GPG::SIGN_MODE_CLEAR}
     * - {@link Crypt_GPG::SIGN_MODE_DETACHED}
     *
     * @param string  $data     the data to be signed.
     * @param boolean $mode     optional. The data signing mode to use. Should
     *                          be one of {@link Crypt_GPG::SIGN_MODE_NORMAL},
     *                          {@link Crypt_GPG::SIGN_MODE_CLEAR} or
     *                          {@link Crypt_GPG::SIGN_MODE_DETACHED}. If not
     *                          specified, defaults to
     *                          <kbd>Crypt_GPG::SIGN_MODE_NORMAL</kbd>.
     * @param boolean $armor    optional. If true, ASCII armored data is
     *                          returned; otherwise, binary data is returned.
     *                          Defaults to true. This has no effect if the
     *                          mode <kbd>Crypt_GPG::SIGN_MODE_CLEAR</kbd> is
     *                          used.
     * @param boolean $textmode optional. If true, line-breaks in signed data
     *                          are normalized. Use this option when signing
     *                          e-mail, or for greater compatibility between
     *                          systems with different line-break formats.
     *                          Defaults to false. This has no effect if the
     *                          mode <kbd>Crypt_GPG::SIGN_MODE_CLEAR</kbd> is
     *                          used as clear-signing always uses textmode.
     *
     * @return string the signed data, or the signature data if a detached
     *                signature is requested.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no signing key is specified.
     *         See {@link Crypt_GPG::addSignKey()}.
     *
     * @throws Crypt_GPG_BadPassphraseException if a specified passphrase is
     *         incorrect or if a required passphrase is not specified.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function sign(
        $data,
        $mode = self::SIGN_MODE_NORMAL,
        $armor = self::ARMOR_ASCII,
        $textmode = self::TEXT_RAW
    ) {
        return $this->_sign($data, false, null, $mode, $armor, $textmode);
    }

    // }}}
    // {{{ signFile()

    /**
     * Signs a file
     *
     * The file may be signed using any one of the three available signing
     * modes:
     * - {@link Crypt_GPG::SIGN_MODE_NORMAL}
     * - {@link Crypt_GPG::SIGN_MODE_CLEAR}
     * - {@link Crypt_GPG::SIGN_MODE_DETACHED}
     *
     * @param string  $filename   the name of the file containing the data to
     *                            be signed.
     * @param string  $signedFile optional. The name of the file in which the
     *                            signed data should be stored. If null or
     *                            unspecified, the signed data is returned as a
     *                            string.
     * @param boolean $mode       optional. The data signing mode to use. Should
     *                            be one of {@link Crypt_GPG::SIGN_MODE_NORMAL},
     *                            {@link Crypt_GPG::SIGN_MODE_CLEAR} or
     *                            {@link Crypt_GPG::SIGN_MODE_DETACHED}. If not
     *                            specified, defaults to
     *                            <kbd>Crypt_GPG::SIGN_MODE_NORMAL</kbd>.
     * @param boolean $armor      optional. If true, ASCII armored data is
     *                            returned; otherwise, binary data is returned.
     *                            Defaults to true. This has no effect if the
     *                            mode <kbd>Crypt_GPG::SIGN_MODE_CLEAR</kbd> is
     *                            used.
     * @param boolean $textmode   optional. If true, line-breaks in signed data
     *                            are normalized. Use this option when signing
     *                            e-mail, or for greater compatibility between
     *                            systems with different line-break formats.
     *                            Defaults to false. This has no effect if the
     *                            mode <kbd>Crypt_GPG::SIGN_MODE_CLEAR</kbd> is
     *                            used as clear-signing always uses textmode.
     *
     * @return void|string if the <kbd>$signedFile</kbd> parameter is null, a
     *                     string containing the signed data (or the signature
     *                     data if a detached signature is requested) is
     *                     returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no signing key is specified.
     *         See {@link Crypt_GPG::addSignKey()}.
     *
     * @throws Crypt_GPG_BadPassphraseException if a specified passphrase is
     *         incorrect or if a required passphrase is not specified.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function signFile(
        $filename,
        $signedFile = null,
        $mode = self::SIGN_MODE_NORMAL,
        $armor = self::ARMOR_ASCII,
        $textmode = self::TEXT_RAW
    ) {
        return $this->_sign(
            $filename,
            true,
            $signedFile,
            $mode,
            $armor,
            $textmode
        );
    }

    // }}}
    // {{{ verify()

    /**
     * Verifies signed data
     *
     * The {@link Crypt_GPG::decrypt()} method may be used to get the original
     * message if the signed data is not clearsigned and does not use a
     * detached signature.
     *
     * @param string $signedData the signed data to be verified.
     * @param string $signature  optional. If verifying data signed using a
     *                           detached signature, this must be the detached
     *                           signature data. The data that was signed is
     *                           specified in <kbd>$signedData</kbd>.
     *
     * @return array an array of {@link Crypt_GPG_Signature} objects for the
     *               signed data. For each signature that is valid, the
     *               {@link Crypt_GPG_Signature::isValid()} will return true.
     *
     * @throws Crypt_GPG_NoDataException if the provided data is not signed
     *         data.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG_Signature
     */
    public function verify($signedData, $signature = '')
    {
        return $this->_verify($signedData, false, $signature);
    }

    // }}}
    // {{{ verifyFile()

    /**
     * Verifies a signed file
     *
     * The {@link Crypt_GPG::decryptFile()} method may be used to get the
     * original message if the signed data is not clearsigned and does not use
     * a detached signature.
     *
     * @param string $filename  the signed file to be verified.
     * @param string $signature optional. If verifying a file signed using a
     *                          detached signature, this must be the detached
     *                          signature data. The file that was signed is
     *                          specified in <kbd>$filename</kbd>.
     *
     * @return array an array of {@link Crypt_GPG_Signature} objects for the
     *               signed data. For each signature that is valid, the
     *               {@link Crypt_GPG_Signature::isValid()} will return true.
     *
     * @throws Crypt_GPG_NoDataException if the provided data is not signed
     *         data.
     *
     * @throws Crypt_GPG_FileException if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG_Signature
     */
    public function verifyFile($filename, $signature = '')
    {
        return $this->_verify($filename, true, $signature);
    }

    // }}}
    // {{{ addDecryptKey()

    /**
     * Adds a key to use for decryption
     *
     * @param mixed  $key        the key to use. This may be a key identifier,
     *                           user id, fingerprint, {@link Crypt_GPG_Key} or
     *                           {@link Crypt_GPG_SubKey}. The key must be able
     *                           to encrypt.
     * @param string $passphrase optional. The passphrase of the key required
     *                           for decryption.
     *
     * @return Crypt_GPG the current object, for fluent interface.
     *
     * @see Crypt_GPG::decrypt()
     * @see Crypt_GPG::decryptFile()
     * @see Crypt_GPG::clearDecryptKeys()
     * @see Crypt_GPG::_addKey()
     *
     * @sensitive $passphrase
     */
    public function addDecryptKey($key, $passphrase = null)
    {
        $this->_addKey($this->decryptKeys, false, false, $key, $passphrase);
        return $this;
    }

    // }}}
    // {{{ addEncryptKey()

    /**
     * Adds a key to use for encryption
     *
     * @param mixed $key the key to use. This may be a key identifier, user id
     *                   user id, fingerprint, {@link Crypt_GPG_Key} or
     *                   {@link Crypt_GPG_SubKey}. The key must be able to
     *                   encrypt.
     *
     * @return Crypt_GPG the current object, for fluent interface.
     *
     * @see Crypt_GPG::encrypt()
     * @see Crypt_GPG::encryptFile()
     * @see Crypt_GPG::clearEncryptKeys()
     * @see Crypt_GPG::_addKey()
     */
    public function addEncryptKey($key)
    {
        $this->_addKey($this->encryptKeys, true, false, $key);
        return $this;
    }

    // }}}
    // {{{ addSignKey()

    /**
     * Adds a key to use for signing
     *
     * @param mixed  $key        the key to use. This may be a key identifier,
     *                           user id, fingerprint, {@link Crypt_GPG_Key} or
     *                           {@link Crypt_GPG_SubKey}. The key must be able
     *                           to sign.
     * @param string $passphrase optional. The passphrase of the key required
     *                           for signing.
     *
     * @return Crypt_GPG the current object, for fluent interface.
     *
     * @see Crypt_GPG::sign()
     * @see Crypt_GPG::signFile()
     * @see Crypt_GPG::clearSignKeys()
     * @see Crypt_GPG::_addKey()
     *
     * @sensitive $passphrase
     */
    public function addSignKey($key, $passphrase = null)
    {
        $this->_addKey($this->signKeys, false, true, $key, $passphrase);
        return $this;
    }

    // }}}
    // {{{ addPassphrase()

    /**
     * Register a private key passphrase for import/export (GnuPG 2.1)
     *
     * @param mixed  $key        The key to use. This must be a key identifier,
     *                           or fingerprint.
     * @param string $passphrase The passphrase of the key.
     *
     * @return Crypt_GPG the current object, for fluent interface.
     *
     * @see Crypt_GPG::clearPassphrases()
     * @see Crypt_GPG::importKey()
     * @see Crypt_GPG::exportKey()
     *
     * @sensitive $passphrase
     */
    public function addPassphrase($key, $passphrase)
    {
        $this->passphrases[$key] = $passphrase;
        return $this;
    }

    // }}}
    // {{{ clearDecryptKeys()

    /**
     * Clears all decryption keys
     *
     * @return Crypt_GPG the current object, for fluent interface.
     *
     * @see Crypt_GPG::decrypt()
     * @see Crypt_GPG::addDecryptKey()
     */
    public function clearDecryptKeys()
    {
        $this->decryptKeys = array();
        return $this;
    }

    // }}}
    // {{{ clearEncryptKeys()

    /**
     * Clears all encryption keys
     *
     * @return Crypt_GPG the current object, for fluent interface.
     *
     * @see Crypt_GPG::encrypt()
     * @see Crypt_GPG::addEncryptKey()
     */
    public function clearEncryptKeys()
    {
        $this->encryptKeys = array();
        return $this;
    }

    // }}}
    // {{{ clearSignKeys()

    /**
     * Clears all signing keys
     *
     * @return Crypt_GPG the current object, for fluent interface.
     *
     * @see Crypt_GPG::sign()
     * @see Crypt_GPG::addSignKey()
     */
    public function clearSignKeys()
    {
        $this->signKeys = array();
        return $this;
    }

    // }}}
    // {{{ clearPassphrases()

    /**
     * Clears all private key passphrases
     *
     * @return Crypt_GPG the current object, for fluent interface.
     *
     * @see Crypt_GPG::importKey()
     * @see Crypt_GPG::exportKey()
     * @see Crypt_GPG::addPassphrase()
     */
    public function clearPassphrases()
    {
        $this->passphrases = array();
        return $this;
    }

    // }}}
    // {{{ hasEncryptKeys()

    /**
     * Tell if there are encryption keys registered
     *
     * @return boolean True if the data shall be encrypted
     */
    public function hasEncryptKeys()
    {
        return count($this->encryptKeys) > 0;
    }

    // }}}
    // {{{ hasSignKeys()

    /**
     * Tell if there are signing keys registered
     *
     * @return boolean True if the data shall be signed
     */
    public function hasSignKeys()
    {
        return count($this->signKeys) > 0;
    }

    // }}}
    // {{{ _addKey()

    /**
     * Adds a key to one of the internal key arrays
     *
     * This handles resolving full key objects from the provided
     * <kbd>$key</kbd> value.
     *
     * @param array   &$array     the array to which the key should be added.
     * @param boolean $encrypt    whether or not the key must be able to
     *                            encrypt.
     * @param boolean $sign       whether or not the key must be able to sign.
     * @param mixed   $key        the key to add. This may be a key identifier,
     *                            user id, fingerprint, {@link Crypt_GPG_Key} or
     *                            {@link Crypt_GPG_SubKey}.
     * @param string  $passphrase optional. The passphrase associated with the
     *                            key.
     *
     * @return void
     *
     * @sensitive $passphrase
     */
    protected function _addKey(array &$array, $encrypt, $sign, $key,
        $passphrase = null
    ) {
        $subKeys = array();

        if (is_scalar($key)) {
            $keys = $this->getKeys($key);
            if (count($keys) == 0) {
                throw new Crypt_GPG_KeyNotFoundException(
                    'Key not found: ' . $key,
                    self::ERROR_KEY_NOT_FOUND,
                    $key
                );
            }
            $key = $keys[0];
        }

        if ($key instanceof Crypt_GPG_Key) {
            if ($encrypt && !$key->canEncrypt()) {
                throw new InvalidArgumentException(
                    'Key "' . $key . '" cannot encrypt.'
                );
            }

            if ($sign && !$key->canSign()) {
                throw new InvalidArgumentException(
                    'Key "' . $key . '" cannot sign.'
                );
            }

            foreach ($key->getSubKeys() as $subKey) {
                $canEncrypt = $subKey->canEncrypt();
                $canSign    = $subKey->canSign();
                if (($encrypt && $sign && $canEncrypt && $canSign)
                    || ($encrypt && !$sign && $canEncrypt)
                    || (!$encrypt && $sign && $canSign)
                    || (!$encrypt && !$sign)
                ) {
                    // We add all subkeys that meet the requirements because we
                    // were not told which subkey is required.
                    $subKeys[] = $subKey;
                }
            }
        } elseif ($key instanceof Crypt_GPG_SubKey) {
            $subKeys[] = $key;
        }

        if (count($subKeys) === 0) {
            throw new InvalidArgumentException(
                'Key "' . $key . '" is not in a recognized format.'
            );
        }

        foreach ($subKeys as $subKey) {
            if ($encrypt && !$subKey->canEncrypt()) {
                throw new InvalidArgumentException(
                    'Key "' . $key . '" cannot encrypt.'
                );
            }

            if ($sign && !$subKey->canSign()) {
                throw new InvalidArgumentException(
                    'Key "' . $key . '" cannot sign.'
                );
            }

            $array[$subKey->getId()] = array(
                'fingerprint' => $subKey->getFingerprint(),
                'passphrase'  => $passphrase
            );
        }
    }

    // }}}
    // {{{ _importKey()

    /**
     * Imports a public or private key into the keyring
     *
     * @param string  $key    the key to be imported.
     * @param boolean $isFile whether or not the input is a filename.
     *
     * @return array an associative array containing the following elements:
     *               - <kbd>fingerprint</kbd>       - the fingerprint of the
     *                                                imported key,
     *               - <kbd>public_imported</kbd>   - the number of public
     *                                                keys imported,
     *               - <kbd>public_unchanged</kbd>  - the number of unchanged
     *                                                public keys,
     *               - <kbd>private_imported</kbd>  - the number of private
     *                                                keys imported,
     *               - <kbd>private_unchanged</kbd> - the number of unchanged
     *                                                private keys.
     *
     * @throws Crypt_GPG_NoDataException if the key data is missing or if the
     *         data is is not valid key data.
     *
     * @throws Crypt_GPG_FileException if the key file is not readable.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addPassphrase()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    protected function _importKey($key, $isFile)
    {
        $result = array();

        if ($isFile) {
            $input = @fopen($key, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException(
                    'Could not open key file "' . $key . '" for importing.',
                    0,
                    $key
                );
            }
        } else {
            $input = strval($key);
            if ($input == '') {
                throw new Crypt_GPG_NoDataException(
                    'No valid GPG key data found.',
                    self::ERROR_NO_DATA
                );
            }
        }

        $arguments = array();
        $version   = $this->engine->getVersion();

        if (version_compare($version, '1.0.5', 'ge')
            && version_compare($version, '1.0.7', 'lt')
        ) {
            $arguments[] = '--allow-secret-key-import';
        }

        $this->engine->reset();
        $this->engine->setPins($this->passphrases);
        $this->engine->setOperation('--import', $arguments);
        $this->engine->setInput($input);
        $this->engine->run();

        return $this->engine->getProcessData('Import');
    }

    // }}}
    // {{{ _exportKey()

    /**
     * Exports a private or public key from the keyring
     *
     * If more than one key fingerprint is available for the specified
     * <kbd>$keyId</kbd> (for example, if you use a non-unique uid) only the
     * first key is exported.
     *
     * @param string  $keyId   either the full uid of the key, the email
     *                         part of the uid of the key or the key id.
     * @param boolean $armor   optional. If true, ASCII armored data is returned;
     *                         otherwise, binary data is returned. Defaults to
     *                         true.
     * @param boolean $private return private instead of public key
     *
     * @return string the key data.
     *
     * @throws Crypt_GPG_KeyNotFoundException if a key with the given
     *         <kbd>$keyId</kbd> is not found.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addPassphrase()}.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    protected function _exportKey($keyId, $armor = true, $private = false)
    {
        $fingerprint = $this->getFingerprint($keyId);

        if ($fingerprint === null) {
            throw new Crypt_GPG_KeyNotFoundException(
                'Key not found: ' . $keyId,
                self::ERROR_KEY_NOT_FOUND,
                $keyId
            );
        }

        $keyData   = '';
        $operation = $private ? '--export-secret-keys' : '--export';
        $operation .= ' ' . escapeshellarg($fingerprint);
        $arguments = $armor ? array('--armor') : array();

        $this->engine->reset();
        $this->engine->setPins($this->passphrases);
        $this->engine->setOutput($keyData);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        return $keyData;
    }

    // }}}
    // {{{ _encrypt()

    /**
     * Encrypts data
     *
     * @param string  $data       the data to encrypt.
     * @param boolean $isFile     whether or not the data is a filename.
     * @param string  $outputFile the filename of the file in which to store
     *                            the encrypted data. If null, the encrypted
     *                            data is returned as a string.
     * @param boolean $armor      if true, ASCII armored data is returned;
     *                            otherwise, binary data is returned.
     *
     * @return void|string if the <kbd>$outputFile</kbd> parameter is null, a
     *                     string containing the encrypted data is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no encryption key is specified.
     *         See {@link Crypt_GPG::addEncryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    protected function _encrypt($data, $isFile, $outputFile, $armor)
    {
        if (!$this->hasEncryptKeys()) {
            throw new Crypt_GPG_KeyNotFoundException(
                'No encryption keys specified.'
            );
        }

        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException(
                    'Could not open input file "' . $data .
                    '" for encryption.',
                    0,
                    $data
                );
            }
        } else {
            $input = strval($data);
        }

        if ($outputFile === null) {
            $output = '';
        } else {
            $output = @fopen($outputFile, 'wb');
            if ($output === false) {
                if ($isFile) {
                    fclose($input);
                }
                throw new Crypt_GPG_FileException(
                    'Could not open output file "' . $outputFile .
                    '" for storing encrypted data.',
                    0,
                    $outputFile
                );
            }
        }

        $arguments = $armor ? array('--armor') : array();
        foreach ($this->encryptKeys as $key) {
            $arguments[] = '--recipient ' . escapeshellarg($key['fingerprint']);
        }

        $this->engine->reset();
        $this->engine->setInput($input);
        $this->engine->setOutput($output);
        $this->engine->setOperation('--encrypt', $arguments);
        $this->engine->run();

        if ($outputFile === null) {
            return $output;
        }
    }

    // }}}
    // {{{ _decrypt()

    /**
     * Decrypts data
     *
     * @param string  $data       the data to be decrypted.
     * @param boolean $isFile     whether or not the data is a filename.
     * @param string  $outputFile the name of the file to which the decrypted
     *                            data should be written. If null, the decrypted
     *                            data is returned as a string.
     *
     * @return void|string if the <kbd>$outputFile</kbd> parameter is null, a
     *                     string containing the decrypted data is returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if the private key needed to
     *         decrypt the data is not in the user's keyring.
     *
     * @throws Crypt_GPG_NoDataException if specified data does not contain
     *         GPG encrypted data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addDecryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    protected function _decrypt($data, $isFile, $outputFile)
    {
        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException(
                    'Could not open input file "' . $data .
                    '" for decryption.',
                    0,
                    $data
                );
            }
        } else {
            $input = strval($data);
            if ($input == '') {
                throw new Crypt_GPG_NoDataException(
                    'Cannot decrypt data. No PGP encrypted data was found in '.
                    'the provided data.',
                    self::ERROR_NO_DATA
                );
            }
        }

        if ($outputFile === null) {
            $output = '';
        } else {
            $output = @fopen($outputFile, 'wb');
            if ($output === false) {
                if ($isFile) {
                    fclose($input);
                }
                throw new Crypt_GPG_FileException(
                    'Could not open output file "' . $outputFile .
                    '" for storing decrypted data.',
                    0,
                    $outputFile
                );
            }
        }

        $this->engine->reset();
        $this->engine->setPins($this->decryptKeys);
        $this->engine->setOperation('--decrypt');
        $this->engine->setInput($input);
        $this->engine->setOutput($output);
        $this->engine->run();

        if ($outputFile === null) {
            return $output;
        }
    }

    // }}}
    // {{{ _sign()

    /**
     * Signs data
     *
     * @param string  $data       the data to be signed.
     * @param boolean $isFile     whether or not the data is a filename.
     * @param string  $outputFile the name of the file in which the signed data
     *                            should be stored. If null, the signed data is
     *                            returned as a string.
     * @param boolean $mode       the data signing mode to use. Should be one of
     *                            {@link Crypt_GPG::SIGN_MODE_NORMAL},
     *                            {@link Crypt_GPG::SIGN_MODE_CLEAR} or
     *                            {@link Crypt_GPG::SIGN_MODE_DETACHED}.
     * @param boolean $armor      if true, ASCII armored data is returned;
     *                            otherwise, binary data is returned. This has
     *                            no effect if the mode
     *                            <kbd>Crypt_GPG::SIGN_MODE_CLEAR</kbd> is
     *                            used.
     * @param boolean $textmode   if true, line-breaks in signed data be
     *                            normalized. Use this option when signing
     *                            e-mail, or for greater compatibility between
     *                            systems with different line-break formats.
     *                            Defaults to false. This has no effect if the
     *                            mode <kbd>Crypt_GPG::SIGN_MODE_CLEAR</kbd> is
     *                            used as clear-signing always uses textmode.
     *
     * @return void|string if the <kbd>$outputFile</kbd> parameter is null, a
     *                     string containing the signed data (or the signature
     *                     data if a detached signature is requested) is
     *                     returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no signing key is specified.
     *         See {@link Crypt_GPG::addSignKey()}.
     *
     * @throws Crypt_GPG_BadPassphraseException if a specified passphrase is
     *         incorrect or if a required passphrase is not specified.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    protected function _sign($data, $isFile, $outputFile, $mode, $armor,
        $textmode
    ) {
        if (!$this->hasSignKeys()) {
            throw new Crypt_GPG_KeyNotFoundException(
                'No signing keys specified.'
            );
        }

        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException(
                    'Could not open input file "' . $data . '" for signing.',
                    0,
                    $data
                );
            }
        } else {
            $input = strval($data);
        }

        if ($outputFile === null) {
            $output = '';
        } else {
            $output = @fopen($outputFile, 'wb');
            if ($output === false) {
                if ($isFile) {
                    fclose($input);
                }
                throw new Crypt_GPG_FileException(
                    'Could not open output file "' . $outputFile .
                    '" for storing signed data.',
                    0,
                    $outputFile
                );
            }
        }

        switch ($mode) {
        case self::SIGN_MODE_DETACHED:
            $operation = '--detach-sign';
            break;
        case self::SIGN_MODE_CLEAR:
            $operation = '--clearsign';
            break;
        case self::SIGN_MODE_NORMAL:
        default:
            $operation = '--sign';
            break;
        }

        $arguments  = array();

        if ($armor) {
            $arguments[] = '--armor';
        }
        if ($textmode) {
            $arguments[] = '--textmode';
        }

        foreach ($this->signKeys as $key) {
            $arguments[] = '--local-user ' .
                escapeshellarg($key['fingerprint']);
        }

        $this->engine->reset();
        $this->engine->setPins($this->signKeys);
        $this->engine->setInput($input);
        $this->engine->setOutput($output);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        if ($outputFile === null) {
            return $output;
        }
    }

    // }}}
    // {{{ _encryptAndSign()

    /**
     * Encrypts and signs data
     *
     * @param string  $data       the data to be encrypted and signed.
     * @param boolean $isFile     whether or not the data is a filename.
     * @param string  $outputFile the name of the file in which the encrypted,
     *                            signed data should be stored. If null, the
     *                            encrypted, signed data is returned as a
     *                            string.
     * @param boolean $armor      if true, ASCII armored data is returned;
     *                            otherwise, binary data is returned.
     *
     * @return void|string if the <kbd>$outputFile</kbd> parameter is null, a
     *                     string containing the encrypted, signed data is
     *                     returned.
     *
     * @throws Crypt_GPG_KeyNotFoundException if no encryption key is specified
     *         or if no signing key is specified. See
     *         {@link Crypt_GPG::addEncryptKey()} and
     *         {@link Crypt_GPG::addSignKey()}.
     *
     * @throws Crypt_GPG_BadPassphraseException if a specified passphrase is
     *         incorrect or if a required passphrase is not specified.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    protected function _encryptAndSign($data, $isFile, $outputFile, $armor)
    {
        if (!$this->hasSignKeys()) {
            throw new Crypt_GPG_KeyNotFoundException(
                'No signing keys specified.'
            );
        }

        if (!$this->hasEncryptKeys()) {
            throw new Crypt_GPG_KeyNotFoundException(
                'No encryption keys specified.'
            );
        }


        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException(
                    'Could not open input file "' . $data .
                    '" for encrypting and signing.',
                    0,
                    $data
                );
            }
        } else {
            $input = strval($data);
        }

        if ($outputFile === null) {
            $output = '';
        } else {
            $output = @fopen($outputFile, 'wb');
            if ($output === false) {
                if ($isFile) {
                    fclose($input);
                }
                throw new Crypt_GPG_FileException(
                    'Could not open output file "' . $outputFile .
                    '" for storing encrypted, signed data.',
                    0,
                    $outputFile
                );
            }
        }

        $arguments  = $armor ? array('--armor') : array();

        foreach ($this->signKeys as $key) {
            $arguments[] = '--local-user ' .
                escapeshellarg($key['fingerprint']);
        }

        foreach ($this->encryptKeys as $key) {
            $arguments[] = '--recipient ' . escapeshellarg($key['fingerprint']);
        }

        $this->engine->reset();
        $this->engine->setPins($this->signKeys);
        $this->engine->setInput($input);
        $this->engine->setOutput($output);
        $this->engine->setOperation('--encrypt --sign', $arguments);
        $this->engine->run();

        if ($outputFile === null) {
            return $output;
        }
    }

    // }}}
    // {{{ _verify()

    /**
     * Verifies data
     *
     * @param string  $data      the signed data to be verified.
     * @param boolean $isFile    whether or not the data is a filename.
     * @param string  $signature if verifying a file signed using a detached
     *                           signature, this must be the detached signature
     *                           data. Otherwise, specify ''.
     *
     * @return array an array of {@link Crypt_GPG_Signature} objects for the
     *               signed data.
     *
     * @throws Crypt_GPG_NoDataException if the provided data is not signed
     *         data.
     *
     * @throws Crypt_GPG_FileException if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG_Signature
     */
    protected function _verify($data, $isFile, $signature)
    {
        if ($signature == '') {
            $operation = '--verify';
            $arguments = array();
        } else {
            // Signed data goes in FD_MESSAGE, detached signature data goes in
            // FD_INPUT.
            $operation = '--verify - "-&' . Crypt_GPG_Engine::FD_MESSAGE. '"';
            $arguments = array('--enable-special-filenames');
        }

        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException(
                    'Could not open input file "' . $data . '" for verifying.',
                    0,
                    $data
                );
            }
        } else {
            $input = strval($data);
            if ($input == '') {
                throw new Crypt_GPG_NoDataException(
                    'No valid signature data found.',
                    self::ERROR_NO_DATA
                );
            }
        }

        $this->engine->reset();

        if ($signature == '') {
            // signed or clearsigned data
            $this->engine->setInput($input);
        } else {
            // detached signature
            $this->engine->setInput($signature);
            $this->engine->setMessage($input);
        }

        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        return $this->engine->getProcessData('Signatures');
    }

    // }}}
    // {{{ _decryptAndVerify()

    /**
     * Decrypts and verifies encrypted, signed data
     *
     * @param string  $data       the encrypted signed data to be decrypted and
     *                            verified.
     * @param boolean $isFile     whether or not the data is a filename.
     * @param string  $outputFile the name of the file to which the decrypted
     *                            data should be written. If null, the decrypted
     *                            data is returned in the results array.
     *
     * @return array two element array. The array has an element 'data'
     *               containing the decrypted data and an element
     *               'signatures' containing an array of
     *               {@link Crypt_GPG_Signature} objects for the signed data.
     *               If the decrypted data is written to a file, the 'data'
     *               element is null.
     *
     * @throws Crypt_GPG_KeyNotFoundException if the private key needed to
     *         decrypt the data is not in the user's keyring or it the public
     *         key needed for verification is not in the user's keyring.
     *
     * @throws Crypt_GPG_NoDataException if specified data does not contain
     *         GPG signed, encrypted data.
     *
     * @throws Crypt_GPG_BadPassphraseException if a required passphrase is
     *         incorrect or if a required passphrase is not specified. See
     *         {@link Crypt_GPG::addDecryptKey()}.
     *
     * @throws Crypt_GPG_FileException if the output file is not writeable or
     *         if the input file is not readable.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     *
     * @see Crypt_GPG_Signature
     */
    protected function _decryptAndVerify($data, $isFile, $outputFile)
    {
        if ($isFile) {
            $input = @fopen($data, 'rb');
            if ($input === false) {
                throw new Crypt_GPG_FileException(
                    'Could not open input file "' . $data .
                    '" for decrypting and verifying.',
                    0,
                    $data
                );
            }
        } else {
            $input = strval($data);
            if ($input == '') {
                throw new Crypt_GPG_NoDataException(
                    'No valid encrypted signed data found.',
                    self::ERROR_NO_DATA
                );
            }
        }

        if ($outputFile === null) {
            $output = '';
        } else {
            $output = @fopen($outputFile, 'wb');
            if ($output === false) {
                if ($isFile) {
                    fclose($input);
                }
                throw new Crypt_GPG_FileException(
                    'Could not open output file "' . $outputFile .
                    '" for storing decrypted data.',
                    0,
                    $outputFile
                );
            }
        }

        $this->engine->reset();
        $this->engine->setPins($this->decryptKeys);
        $this->engine->setInput($input);
        $this->engine->setOutput($output);
        $this->engine->setOperation('--decrypt');
        $this->engine->run();

        $return = array(
            'data'       => null,
            'signatures' => $this->engine->getProcessData('Signatures')
        );

        if ($outputFile === null) {
            $return['data'] = $output;
        }

        return $return;
    }

    // }}}
}

// }}}

?>
