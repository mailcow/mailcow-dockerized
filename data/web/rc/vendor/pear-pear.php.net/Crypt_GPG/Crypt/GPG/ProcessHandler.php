<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Crypt_GPG is a package to use GPG from PHP
 *
 * This file contains handler for status and error pipes of GPG process.
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
 * @author    Aleksander Machniak <alec@alec.pl>
 * @copyright 2005-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 * @link      http://www.gnupg.org/
 */

/**
 * GPG exception classes.
 */
require_once 'Crypt/GPG/Exceptions.php';

/**
 * Signature object class definition
 */
require_once 'Crypt/GPG/Signature.php';

// {{{ class Crypt_GPG_ProcessHandler

/**
 * Status/Error handler for GPG process pipes.
 *
 * This class is used internally by Crypt_GPG_Engine and does not need to be used
 * directly. See the {@link Crypt_GPG} class for end-user API.
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Nathan Fredrickson <nathan@silverorange.com>
 * @author    Michael Gauthier <mike@silverorange.com>
 * @author    Aleksander Machniak <alec@alec.pl>
 * @copyright 2005-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 * @link      http://www.gnupg.org/
 */
class Crypt_GPG_ProcessHandler
{
    // {{{ protected class properties

    /**
     * Engine used to control the GPG subprocess
     *
     * @var Crypt_GPG_Engine
     */
    protected $engine;

    /**
     * The error code of the current operation
     *
     * @var integer
     */
    protected $errorCode = Crypt_GPG::ERROR_NONE;

    /**
     * The number of currently needed passphrases
     *
     * If this is not zero when the GPG command is completed, the error code is
     * set to {@link Crypt_GPG::ERROR_MISSING_PASSPHRASE}.
     *
     * @var integer
     */
    protected $needPassphrase = 0;

    /**
     * Some data collected while processing the operation
     * or set for the operation
     *
     * @var array
     * @see self::setData()
     * @see self::getData()
     */
    protected $data = array();

    /**
     * The name of the current operation
     *
     * @var string
     * @see self::setOperation()
     */
    protected $operation = null;

    /**
     * The value of the argument of current operation
     *
     * @var string
     * @see self::setOperation()
     */
    protected $operationArg = null;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new instance
     *
     * @param Crypt_GPG_Engine $engine Engine object
     */
    public function __construct($engine)
    {
        $this->engine = $engine;
    }

    // }}}
    // {{{ setOperation()

    /**
     * Sets the operation that is being performed by the engine.
     *
     * @param string $operation The GPG operation to perform.
     *
     * @return void
     */
    public function setOperation($operation)
    {
        $op    = null;
        $opArg = null;

        // Regexp matching all GPG "operational" arguments
        $regexp = '/--('
            . 'version|import|list-public-keys|list-secret-keys'
            . '|list-keys|delete-key|delete-secret-key|encrypt|sign|clearsign'
            . '|detach-sign|decrypt|verify|export-secret-keys|export|gen-key'
            . ')/';

        if (strpos($operation, ' ') === false) {
            $op = trim($operation, '- ');
        } else if (preg_match($regexp, $operation, $matches, PREG_OFFSET_CAPTURE)) {
            $op      = trim($matches[0][0], '-');
            $op_len  = $matches[0][1] + mb_strlen($op, '8bit') + 3;
            $command = mb_substr($operation, $op_len, null, '8bit');

            // we really need the argument if it is a key ID/fingerprint or email
            // address se we can use simplified regexp to "revert escapeshellarg()"
            if (preg_match('/^[\'"]([a-zA-Z0-9:@._-]+)[\'"]/', $command, $matches)) {
                $opArg = $matches[1];
            }
        }

        $this->operation    = $op;
        $this->operationArg = $opArg;
    }

    // }}}
    // {{{ handleStatus()

    /**
     * Handles error values in the status output from GPG
     *
     * This method is responsible for setting the
     * {@link self::$errorCode}. See <b>doc/DETAILS</b> in the
     * {@link http://www.gnupg.org/download/ GPG distribution} for detailed
     * information on GPG's status output.
     *
     * @param string $line the status line to handle.
     *
     * @return void
     */
    public function handleStatus($line)
    {
        $tokens = explode(' ', $line);
        switch ($tokens[0]) {
        case 'NODATA':
            $this->errorCode = Crypt_GPG::ERROR_NO_DATA;
            break;

        case 'DECRYPTION_OKAY':
            // If the message is encrypted, this is the all-clear signal.
            $this->data['DecryptionOkay'] = true;
            $this->errorCode = Crypt_GPG::ERROR_NONE;
            break;

        case 'DELETE_PROBLEM':
            if ($tokens[1] == '1') {
                $this->errorCode = Crypt_GPG::ERROR_KEY_NOT_FOUND;
                break;
            } elseif ($tokens[1] == '2') {
                $this->errorCode = Crypt_GPG::ERROR_DELETE_PRIVATE_KEY;
                break;
            }
            break;

        case 'IMPORT_OK':
            $this->data['Import']['fingerprint'] = $tokens[2];

            if (empty($this->data['Import']['fingerprints'])) {
                $this->data['Import']['fingerprints'] = array($tokens[2]);
            } else if (!in_array($tokens[2], $this->data['Import']['fingerprints'])) {
                $this->data['Import']['fingerprints'][] = $tokens[2];
            }

            break;

        case 'IMPORT_RES':
            $this->data['Import']['public_imported']   = intval($tokens[3]);
            $this->data['Import']['public_unchanged']  = intval($tokens[5]);
            $this->data['Import']['private_imported']  = intval($tokens[11]);
            $this->data['Import']['private_unchanged'] = intval($tokens[12]);
            break;

        case 'NO_PUBKEY':
        case 'NO_SECKEY':
            $this->data['ErrorKeyId'] = $tokens[1];

            if ($this->errorCode != Crypt_GPG::ERROR_MISSING_PASSPHRASE
                && $this->errorCode != Crypt_GPG::ERROR_BAD_PASSPHRASE
            ) {
                $this->errorCode = Crypt_GPG::ERROR_KEY_NOT_FOUND;
            }

            // note: this message is also received if there are multiple
            // recipients and a previous key had a correct passphrase.
            $this->data['MissingKeys'][$tokens[1]] = $tokens[1];

            // @FIXME: remove missing passphrase registered in ENC_TO handler
            //         This is for GnuPG 2.1
            unset($this->data['MissingPassphrases'][$tokens[1]]);
            break;

        case 'KEY_CONSIDERED':
            // In GnuPG 2.1.x exporting/importing a secret key requires passphrase
            // However, no NEED_PASSPRASE is returned, https://bugs.gnupg.org/gnupg/issue2667
            // So, handling KEY_CONSIDERED and GET_HIDDEN is needed.
            if (!array_key_exists('KeyConsidered', $this->data)) {
                $this->data['KeyConsidered'] = $tokens[1];
            }
            break;

        case 'USERID_HINT':
            // remember the user id for pretty exception messages
            // GnuPG 2.1.15 gives me: "USERID_HINT 0000000000000000 [?]"
            $keyId = $tokens[1];
            if (strcspn($keyId, '0')) {
                $username = implode(' ', array_splice($tokens, 2));
                $this->data['BadPassphrases'][$keyId] = $username;
            }
            break;

        case 'ENC_TO':
            // Now we know the message is encrypted. Set flag to check if
            // decryption succeeded.
            $this->data['DecryptionOkay'] = false;

            // this is the new key message
            $this->data['CurrentSubKeyId'] = $keyId = $tokens[1];

            // For some reason in GnuPG 2.1.11 I get only ENC_TO and no
            // NEED_PASSPHRASE/MISSING_PASSPHRASE/USERID_HINT
            // This is not needed for GnuPG 2.1.15
            if (!empty($_ENV['PINENTRY_USER_DATA'])) {
                $passphrases = json_decode($_ENV['PINENTRY_USER_DATA'], true);
            } else {
                $passphrases = array();
            }

            // @TODO: Get user name/email
            $this->data['BadPassphrases'][$keyId] = $keyId;
            if (empty($passphrases) || empty($passphrases[$keyId])) {
                $this->data['MissingPassphrases'][$keyId] = $keyId;
            }
            break;

        case 'GOOD_PASSPHRASE':
            // if we got a good passphrase, remove the key from the list of
            // bad passphrases.
            if (isset($this->data['CurrentSubKeyId'])) {
                unset($this->data['BadPassphrases'][$this->data['CurrentSubKeyId']]);
                unset($this->data['MissingPassphrases'][$this->data['CurrentSubKeyId']]);
            }

            $this->needPassphrase--;
            break;

        case 'BAD_PASSPHRASE':
            $this->errorCode = Crypt_GPG::ERROR_BAD_PASSPHRASE;
            break;

        case 'MISSING_PASSPHRASE':
            if (isset($this->data['CurrentSubKeyId'])) {
                $this->data['MissingPassphrases'][$this->data['CurrentSubKeyId']]
                    = $this->data['CurrentSubKeyId'];
            }

            $this->errorCode = Crypt_GPG::ERROR_MISSING_PASSPHRASE;
            break;

        case 'GET_HIDDEN':
            if ($tokens[1] == 'passphrase.enter' && isset($this->data['KeyConsidered'])) {
                $tokens[1] = $this->data['KeyConsidered'];
            } else {
                break;
            }
            // no break

        case 'NEED_PASSPHRASE':
            $passphrase = $this->getPin($tokens[1]);

            $this->engine->sendCommand($passphrase);

            if ($passphrase === '') {
                $this->needPassphrase++;
            }
            break;

        case 'SIG_CREATED':
            $this->data['SigCreated'] = $line;
            break;

        case 'SIG_ID':
            // note: signature id comes before new signature line and may not
            // exist for some signature types
            $this->data['SignatureId'] = $tokens[1];
            break;

        case 'EXPSIG':
        case 'EXPKEYSIG':
        case 'REVKEYSIG':
        case 'BADSIG':
        case 'ERRSIG':
            $this->errorCode = Crypt_GPG::ERROR_BAD_SIGNATURE;
            // no break
        case 'GOODSIG':
            $signature = new Crypt_GPG_Signature();

            // if there was a signature id, set it on the new signature
            if (!empty($this->data['SignatureId'])) {
                $signature->setId($this->data['SignatureId']);
                $this->data['SignatureId'] = '';
            }

            // Detect whether fingerprint or key id was returned and set
            // signature values appropriately. Key ids are strings of either
            // 16 or 8 hexadecimal characters. Fingerprints are strings of 40
            // hexadecimal characters. The key id is the last 16 characters of
            // the key fingerprint.
            if (mb_strlen($tokens[1], '8bit') > 16) {
                $signature->setKeyFingerprint($tokens[1]);
                $signature->setKeyId(mb_substr($tokens[1], -16, null, '8bit'));
            } else {
                $signature->setKeyId($tokens[1]);
            }

            // get user id string
            if ($tokens[0] != 'ERRSIG') {
                $string = implode(' ', array_splice($tokens, 2));
                $string = rawurldecode($string);

                $signature->setUserId(Crypt_GPG_UserId::parse($string));
            }

            $this->data['Signatures'][] = $signature;
            break;

        case 'VALIDSIG':
            if (empty($this->data['Signatures'])) {
                break;
            }

            $signature = end($this->data['Signatures']);

            $signature->setValid(true);
            $signature->setKeyFingerprint($tokens[1]);

            if (strpos($tokens[3], 'T') === false) {
                $signature->setCreationDate($tokens[3]);
            } else {
                $signature->setCreationDate(strtotime($tokens[3]));
            }

            if (array_key_exists(4, $tokens)) {
                if (strpos($tokens[4], 'T') === false) {
                    $signature->setExpirationDate($tokens[4]);
                } else {
                    $signature->setExpirationDate(strtotime($tokens[4]));
                }
            }

            break;

        case 'KEY_CREATED':
            if (isset($this->data['Handle']) && $tokens[3] == $this->data['Handle']) {
                $this->data['KeyCreated'] = $tokens[2];
            }
            break;

        case 'KEY_NOT_CREATED':
            if (isset($this->data['Handle']) && $tokens[1] == $this->data['Handle']) {
                $this->errorCode = Crypt_GPG::ERROR_KEY_NOT_CREATED;
            }
            break;

        case 'PROGRESS':
            // todo: at some point, support reporting status async
            break;

        // GnuPG 2.1 uses FAILURE and ERROR responses
        case 'FAILURE':
        case 'ERROR':
            $errnum  = (int) $tokens[2];
            $source  = $errnum >> 24;
            $errcode = $errnum & 0xFFFFFF;

            switch ($errcode) {
            case 11: // bad passphrase
            case 87: // bad PIN
                $this->errorCode = Crypt_GPG::ERROR_BAD_PASSPHRASE;
                break;

            case 177: // no passphrase
            case 178: // no PIN
                $this->errorCode = Crypt_GPG::ERROR_MISSING_PASSPHRASE;
                break;

            case 58:
                $this->errorCode = Crypt_GPG::ERROR_NO_DATA;
                break;
            }

            break;
        }
    }

    // }}}
    // {{{ handleError()

    /**
     * Handles error values in the error output from GPG
     *
     * This method is responsible for setting the
     * {@link Crypt_GPG_Engine::$_errorCode}.
     *
     * @param string $line the error line to handle.
     *
     * @return void
     */
    public function handleError($line)
    {
        if ($this->errorCode === Crypt_GPG::ERROR_NONE) {
            $pattern = '/no valid OpenPGP data found/';
            if (preg_match($pattern, $line) === 1) {
                $this->errorCode = Crypt_GPG::ERROR_NO_DATA;
            }
        }

        if ($this->errorCode === Crypt_GPG::ERROR_NONE) {
            $pattern = '/No secret key|secret key not available/';
            if (preg_match($pattern, $line) === 1) {
                $this->errorCode = Crypt_GPG::ERROR_KEY_NOT_FOUND;
            }
        }

        if ($this->errorCode === Crypt_GPG::ERROR_NONE) {
            $pattern = '/No public key|public key not found/';
            if (preg_match($pattern, $line) === 1) {
                $this->errorCode = Crypt_GPG::ERROR_KEY_NOT_FOUND;
            }
        }

        if ($this->errorCode === Crypt_GPG::ERROR_NONE) {
            $matches = array();
            $pattern = '/can\'t (?:access|open) `(.*?)\'/';
            if (preg_match($pattern, $line, $matches) === 1) {
                $this->data['ErrorFilename'] = $matches[1];
                $this->errorCode = Crypt_GPG::ERROR_FILE_PERMISSIONS;
            }
        }

        // GnuPG 2.1: It should return MISSING_PASSPHRASE, but it does not
        // we have to detect it this way. This happens e.g. on private key import
        if ($this->errorCode === Crypt_GPG::ERROR_NONE) {
            $matches = array();
            $pattern = '/key ([0-9A-F]+).* (Bad|No) passphrase/';
            if (preg_match($pattern, $line, $matches) === 1) {
                $keyId = $matches[1];
                // @TODO: Get user name/email
                if (empty($this->data['BadPassphrases'][$keyId])) {
                    $this->data['BadPassphrases'][$keyId] = $keyId;
                }
                if ($matches[2] == 'Bad') {
                    $this->errorCode = Crypt_GPG::ERROR_BAD_PASSPHRASE;
                } else {
                    $this->errorCode = Crypt_GPG::ERROR_MISSING_PASSPHRASE;
                    if (empty($this->data['MissingPassphrases'][$keyId])) {
                        $this->data['MissingPassphrases'][$keyId] = $keyId;
                    }
                }
            }
        }

        if ($this->errorCode === Crypt_GPG::ERROR_NONE && $this->operation == 'gen-key') {
            $pattern = '/:([0-9]+): invalid algorithm$/';
            if (preg_match($pattern, $line, $matches) === 1) {
                $this->errorCode          = Crypt_GPG::ERROR_BAD_KEY_PARAMS;
                $this->data['LineNumber'] = intval($matches[1]);
            }
        }
    }

    // }}}
    // {{{ throwException()

    /**
     * On error throws exception
     *
     * @param int $exitcode GPG process exit code
     *
     * @return void
     * @throws Crypt_GPG_Exception
     */
    public function throwException($exitcode = 0)
    {
        if ($exitcode != 0 && $this->errorCode === Crypt_GPG::ERROR_NONE) {
            if ($this->needPassphrase > 0) {
                $this->errorCode = Crypt_GPG::ERROR_MISSING_PASSPHRASE;
            } else if ($this->operation != 'import') {
                $this->errorCode = Crypt_GPG::ERROR_UNKNOWN;
            }
        }

        if ($this->errorCode === Crypt_GPG::ERROR_NONE) {
            return;
        }

        $code = $this->errorCode;
        $note = "Please use the 'debug' option when creating the Crypt_GPG " .
            "object, and file a bug report at " . Crypt_GPG::BUG_URI;

        switch ($this->operation) {
        case 'version':
            throw new Crypt_GPG_Exception(
                'Unknown error getting GnuPG version information. ' . $note,
                $code
            );

        case 'list-secret-keys':
        case 'list-public-keys':
        case 'list-keys':
            switch ($code) {
            case Crypt_GPG::ERROR_KEY_NOT_FOUND:
                // ignore not found key errors
                break;

            case Crypt_GPG::ERROR_FILE_PERMISSIONS:
                if (!empty($this->data['ErrorFilename'])) {
                    throw new Crypt_GPG_FileException(
                        sprintf(
                            'Error reading GnuPG data file \'%s\'. Check to make ' .
                            'sure it is readable by the current user.',
                            $this->data['ErrorFilename']
                        ),
                        $code,
                        $this->data['ErrorFilename']
                    );
                }
                throw new Crypt_GPG_FileException(
                    'Error reading GnuPG data file. Check to make sure that ' .
                    'GnuPG data files are readable by the current user.',
                    $code
                );

            default:
                throw new Crypt_GPG_Exception(
                    'Unknown error getting keys. ' . $note, $code
                );
            }
            break;

        case 'delete-key':
        case 'delete-secret-key':
            switch ($code) {
            case Crypt_GPG::ERROR_KEY_NOT_FOUND:
                throw new Crypt_GPG_KeyNotFoundException(
                    'Key not found: ' . $this->operationArg,
                    $code,
                    $this->operationArg
                );

            case Crypt_GPG::ERROR_DELETE_PRIVATE_KEY:
                throw new Crypt_GPG_DeletePrivateKeyException(
                    'Private key must be deleted before public key can be ' .
                    'deleted.',
                    $code,
                    $this->operationArg
                );

            default:
                throw new Crypt_GPG_Exception(
                    'Unknown error deleting key. ' . $note, $code
                );
            }
            break;

        case 'import':
            switch ($code) {
            case Crypt_GPG::ERROR_NO_DATA:
                throw new Crypt_GPG_NoDataException(
                    'No valid GPG key data found.', $code
                );

            case Crypt_GPG::ERROR_BAD_PASSPHRASE:
            case Crypt_GPG::ERROR_MISSING_PASSPHRASE:
                throw $this->badPassException($code, 'Cannot import private key.');

            default:
                throw new Crypt_GPG_Exception(
                    'Unknown error importing GPG key. ' . $note, $code
                );
            }
            break;

        case 'export':
        case 'export-secret-keys':
            switch ($code) {
            case Crypt_GPG::ERROR_BAD_PASSPHRASE:
            case Crypt_GPG::ERROR_MISSING_PASSPHRASE:
                throw $this->badPassException($code, 'Cannot export private key.');

            default:
                throw new Crypt_GPG_Exception(
                    'Unknown error exporting a key. ' . $note, $code
                );
            }
            break;

        case 'encrypt':
        case 'sign':
        case 'clearsign':
        case 'detach-sign':
            switch ($code) {
            case Crypt_GPG::ERROR_KEY_NOT_FOUND:
                throw new Crypt_GPG_KeyNotFoundException(
                    'Cannot sign data. Private key not found. Import the '.
                    'private key before trying to sign data.',
                    $code,
                    !empty($this->data['ErrorKeyId']) ? $this->data['ErrorKeyId'] : null
                );

            case Crypt_GPG::ERROR_BAD_PASSPHRASE:
                throw new Crypt_GPG_BadPassphraseException(
                    'Cannot sign data. Incorrect passphrase provided.', $code
                );

            case Crypt_GPG::ERROR_MISSING_PASSPHRASE:
                throw new Crypt_GPG_BadPassphraseException(
                    'Cannot sign data. No passphrase provided.', $code
                );

            default:
                throw new Crypt_GPG_Exception(
                    "Unknown error {$this->operation}ing data. $note", $code
                );
            }
            break;

        case 'verify':
            switch ($code) {
            case Crypt_GPG::ERROR_BAD_SIGNATURE:
                // ignore bad signature errors
                break;

            case Crypt_GPG::ERROR_NO_DATA:
                throw new Crypt_GPG_NoDataException(
                    'No valid signature data found.', $code
                );

            case Crypt_GPG::ERROR_KEY_NOT_FOUND:
                throw new Crypt_GPG_KeyNotFoundException(
                    'Public key required for data verification not in keyring.',
                    $code,
                    !empty($this->data['ErrorKeyId']) ? $this->data['ErrorKeyId'] : null
                );

            default:
                throw new Crypt_GPG_Exception(
                    'Unknown error validating signature details. ' . $note,
                    $code
                );
            }
            break;

        case 'decrypt':
            switch ($code) {
            case Crypt_GPG::ERROR_BAD_SIGNATURE:
                // ignore bad signature errors
                break;

            case Crypt_GPG::ERROR_KEY_NOT_FOUND:
                if (!empty($this->data['MissingKeys'])) {
                    $keyId = reset($this->data['MissingKeys']);
                } else {
                    $keyId = '';
                }

                throw new Crypt_GPG_KeyNotFoundException(
                    'Cannot decrypt data. No suitable private key is in the ' .
                    'keyring. Import a suitable private key before trying to ' .
                    'decrypt this data.',
                    $code,
                    $keyId
                );

            case Crypt_GPG::ERROR_BAD_PASSPHRASE:
            case Crypt_GPG::ERROR_MISSING_PASSPHRASE:
                throw $this->badPassException($code, 'Cannot decrypt data.');

            case Crypt_GPG::ERROR_NO_DATA:
                throw new Crypt_GPG_NoDataException(
                    'Cannot decrypt data. No PGP encrypted data was found in '.
                    'the provided data.',
                    $code
                );

            default:
                throw new Crypt_GPG_Exception(
                    'Unknown error decrypting data.', $code
                );
            }
            break;

        case 'gen-key':
            switch ($code) {
            case Crypt_GPG::ERROR_BAD_KEY_PARAMS:
                throw new Crypt_GPG_InvalidKeyParamsException(
                    'Invalid key algorithm specified.', $code
                );

            default:
                throw new Crypt_GPG_Exception(
                    'Unknown error generating key-pair. ' . $note, $code
                );
            }
        }
    }

    // }}}
    // {{{ getData()

    /**
     * Get data from the last process execution.
     *
     * @param string $name Data element name:
     *               - SigCreated: The last SIG_CREATED status.
     *               - KeyConsidered: The last KEY_CONSIDERED status identifier.
     *               - KeyCreated: The KEY_CREATED status (for specified Handle).
     *               - Signatures: Signatures data from verification process.
     *               - LineNumber: Number of the gen-key error line.
     *               - Import: Result of IMPORT_OK/IMPORT_RES
     *
     * @return mixed
     */
    public function getData($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    // }}}
    // {{{ setData()

    /**
     * Set data for the process execution.
     *
     * @param string $name  Data element name:
     *               - Handle: The unique key handle used by this handler
     *                         The key handle is used to track GPG status output
     *                         for a particular key on --gen-key command before
     *                         the key has its own identifier.
     * @param mixed  $value Data element value
     *
     * @return void
     */
    public function setData($name, $value)
    {
        switch ($name) {
        case 'Handle':
            $this->data[$name] = strval($value);
            break;
        }
    }

    // }}}
    // {{{ setData()

    /**
     * Create Crypt_GPG_BadPassphraseException from operation data.
     *
     * @param int    $code    Error code
     * @param string $message Error message
     *
     * @return Crypt_GPG_BadPassphraseException
     */
    protected function badPassException($code, $message)
    {
        $badPassphrases = array_diff_key(
            isset($this->data['BadPassphrases']) ? $this->data['BadPassphrases'] : array(),
            isset($this->data['MissingPassphrases']) ? $this->data['MissingPassphrases'] : array()
        );

        $missingPassphrases = array_intersect_key(
            isset($this->data['BadPassphrases']) ? $this->data['BadPassphrases'] : array(),
            isset($this->data['MissingPassphrases']) ? $this->data['MissingPassphrases'] : array()
        );

        if (count($badPassphrases) > 0) {
            $message .= ' Incorrect passphrase provided for keys: "' .
                implode('", "', $badPassphrases) . '".';
        }
        if (count($missingPassphrases) > 0) {
            $message .= ' No passphrase provided for keys: "' .
                implode('", "', $missingPassphrases) . '".';
        }

        return new Crypt_GPG_BadPassphraseException(
            $message,
            $code,
            $badPassphrases,
            $missingPassphrases
        );
    }

    // }}}
    // {{{ getPin()

    /**
     * Get registered passphrase for specified key.
     *
     * @param string $key Key identifier
     *
     * @return string Passphrase
     */
    protected function getPin($key)
    {
        $passphrase  = '';
        $keyIdLength = mb_strlen($key, '8bit');

        if ($keyIdLength && !empty($_ENV['PINENTRY_USER_DATA'])) {
            $passphrases = json_decode($_ENV['PINENTRY_USER_DATA'], true);
            foreach ($passphrases as $_keyId => $pass) {
                $keyId        = $key;
                $_keyIdLength = mb_strlen($_keyId, '8bit');

                // Get last X characters of key identifier to compare
                if ($keyIdLength < $_keyIdLength) {
                    $_keyId = mb_substr($_keyId, -$keyIdLength, null, '8bit');
                } else if ($keyIdLength > $_keyIdLength) {
                    $keyId = mb_substr($keyId, -$_keyIdLength, null, '8bit');
                }

                if ($_keyId === $keyId) {
                    $passphrase = $pass;
                    break;
                }
            }
        }

        return $passphrase;
    }

    // }}}
}

// }}}

?>
