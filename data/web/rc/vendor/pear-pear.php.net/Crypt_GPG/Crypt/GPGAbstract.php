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
 * This file contains an abstract implementation of a user of the
 * {@link Crypt_GPG_Engine} class.
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
 * GPG key class
 */
require_once 'Crypt/GPG/Key.php';

/**
 * GPG sub-key class
 */
require_once 'Crypt/GPG/SubKey.php';

/**
 * GPG user id class
 */
require_once 'Crypt/GPG/UserId.php';

/**
 * GPG process and I/O engine class
 */
require_once 'Crypt/GPG/Engine.php';

// {{{ class Crypt_GPGAbstract

/**
 * Base class for implementing a user of {@link Crypt_GPG_Engine}
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
abstract class Crypt_GPGAbstract
{
    // {{{ class error constants

    /**
     * Error code returned when there is no error.
     */
    const ERROR_NONE = 0;

    /**
     * Error code returned when an unknown or unhandled error occurs.
     */
    const ERROR_UNKNOWN = 1;

    /**
     * Error code returned when a bad passphrase is used.
     */
    const ERROR_BAD_PASSPHRASE = 2;

    /**
     * Error code returned when a required passphrase is missing.
     */
    const ERROR_MISSING_PASSPHRASE = 3;

    /**
     * Error code returned when a key that is already in the keyring is
     * imported.
     */
    const ERROR_DUPLICATE_KEY = 4;

    /**
     * Error code returned the required data is missing for an operation.
     *
     * This could be missing key data, missing encrypted data or missing
     * signature data.
     */
    const ERROR_NO_DATA = 5;

    /**
     * Error code returned when an unsigned key is used.
     */
    const ERROR_UNSIGNED_KEY = 6;

    /**
     * Error code returned when a key that is not self-signed is used.
     */
    const ERROR_NOT_SELF_SIGNED = 7;

    /**
     * Error code returned when a public or private key that is not in the
     * keyring is used.
     */
    const ERROR_KEY_NOT_FOUND = 8;

    /**
     * Error code returned when an attempt to delete public key having a
     * private key is made.
     */
    const ERROR_DELETE_PRIVATE_KEY = 9;

    /**
     * Error code returned when one or more bad signatures are detected.
     */
    const ERROR_BAD_SIGNATURE = 10;

    /**
     * Error code returned when there is a problem reading GnuPG data files.
     */
    const ERROR_FILE_PERMISSIONS = 11;

    /**
     * Error code returned when a key could not be created.
     */
    const ERROR_KEY_NOT_CREATED = 12;

    /**
     * Error code returned when bad key parameters are used during key
     * generation.
     */
    const ERROR_BAD_KEY_PARAMS = 13;

    // }}}
    // {{{ other class constants

    /**
     * URI at which package bugs may be reported.
     */
    const BUG_URI = 'http://pear.php.net/bugs/report.php?package=Crypt_GPG';

    // }}}
    // {{{ protected class properties

    /**
     * Engine used to control the GPG subprocess
     *
     * @var Crypt_GPG_Engine
     *
     * @see Crypt_GPGAbstract::setEngine()
     */
    protected $engine = null;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new GPG object
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
     * - <kbd>boolean debug</kbd>          - whether or not to use debug mode.
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
        $this->setEngine(new Crypt_GPG_Engine($options));
    }

    // }}}
    // {{{ setEngine()

    /**
     * Sets the I/O engine to use for GnuPG operations
     *
     * Normally this method does not need to be used. It provides a means for
     * dependency injection.
     *
     * @param Crypt_GPG_Engine $engine the engine to use.
     *
     * @return Crypt_GPGAbstract the current object, for fluent interface.
     */
    public function setEngine(Crypt_GPG_Engine $engine)
    {
        $this->engine = $engine;
        return $this;
    }

    // }}}
    // {{{ getVersion()

    /**
     * Returns version of the engine (GnuPG) used for operation.
     *
     * @return string GnuPG version.
     *
     * @throws Crypt_GPG_Exception if an unknown or unexpected error occurs.
     *         Use the <kbd>debug</kbd> option and file a bug report if these
     *         exceptions occur.
     */
    public function getVersion()
    {
        return $this->engine->getVersion();
    }

    // }}}
    // {{{ _getKeys()

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
    protected function _getKeys($keyId = '')
    {
        // get private key fingerprints
        if ($keyId == '') {
            $operation = '--list-secret-keys';
        } else {
            $operation = '--utf8-strings --list-secret-keys ' . escapeshellarg($keyId);
        }

        // According to The file 'doc/DETAILS' in the GnuPG distribution, using
        // double '--with-fingerprint' also prints the fingerprint for subkeys.
        $arguments = array(
            '--with-colons',
            '--with-fingerprint',
            '--with-fingerprint',
            '--fixed-list-mode'
        );

        $output = '';

        $this->engine->reset();
        $this->engine->setOutput($output);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        $privateKeyFingerprints = array();

        foreach (explode(PHP_EOL, $output) as $line) {
            $lineExp = explode(':', $line);
            if ($lineExp[0] == 'fpr') {
                $privateKeyFingerprints[] = $lineExp[9];
            }
        }

        // get public keys
        if ($keyId == '') {
            $operation = '--list-public-keys';
        } else {
            $operation = '--utf8-strings --list-public-keys ' . escapeshellarg($keyId);
        }

        $output = '';

        $this->engine->reset();
        $this->engine->setOutput($output);
        $this->engine->setOperation($operation, $arguments);
        $this->engine->run();

        $keys   = array();
        $key    = null; // current key
        $subKey = null; // current sub-key

        foreach (explode(PHP_EOL, $output) as $line) {
            $lineExp = explode(':', $line);

            if ($lineExp[0] == 'pub') {

                // new primary key means last key should be added to the array
                if ($key !== null) {
                    $keys[] = $key;
                }

                $key = new Crypt_GPG_Key();

                $subKey = Crypt_GPG_SubKey::parse($line);
                $key->addSubKey($subKey);

            } elseif ($lineExp[0] == 'sub') {

                $subKey = Crypt_GPG_SubKey::parse($line);
                $key->addSubKey($subKey);

            } elseif ($lineExp[0] == 'fpr') {

                $fingerprint = $lineExp[9];

                // set current sub-key fingerprint
                $subKey->setFingerprint($fingerprint);

                // if private key exists, set has private to true
                if (in_array($fingerprint, $privateKeyFingerprints)) {
                    $subKey->setHasPrivate(true);
                }

            } elseif ($lineExp[0] == 'uid') {

                $string = stripcslashes($lineExp[9]); // as per documentation
                $userId = new Crypt_GPG_UserId($string);

                if ($lineExp[1] == 'r') {
                    $userId->setRevoked(true);
                }

                $key->addUserId($userId);

            }
        }

        // add last key
        if ($key !== null) {
            $keys[] = $key;
        }

        return $keys;
    }

    // }}}
}

// }}}

?>
