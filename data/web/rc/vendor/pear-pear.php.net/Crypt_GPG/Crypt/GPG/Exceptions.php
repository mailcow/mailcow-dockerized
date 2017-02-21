<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Various exception handling classes for Crypt_GPG
 *
 * Crypt_GPG provides an object oriented interface to GNU Privacy
 * Guard (GPG). It requires the GPG executable to be on the system.
 *
 * This file contains various exception classes used by the Crypt_GPG package.
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
 * @copyright 2005-2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */

/**
 * PEAR Exception handler and base class
 */
require_once 'PEAR/Exception.php';

// {{{ class Crypt_GPG_Exception

/**
 * An exception thrown by the Crypt_GPG package
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2005 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_Exception extends PEAR_Exception
{
}

// }}}
// {{{ class Crypt_GPG_FileException

/**
 * An exception thrown when a file is used in ways it cannot be used
 *
 * For example, if an output file is specified and the file is not writeable, or
 * if an input file is specified and the file is not readable, this exception
 * is thrown.
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2007-2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_FileException extends Crypt_GPG_Exception
{
    // {{{ private class properties

    /**
     * The name of the file that caused this exception
     *
     * @var string
     */
    private $_filename = '';

    // }}}
    // {{{ __construct()

    /**
     * Creates a new Crypt_GPG_FileException
     *
     * @param string  $message  an error message.
     * @param integer $code     a user defined error code.
     * @param string  $filename the name of the file that caused this exception.
     */
    public function __construct($message, $code = 0, $filename = '')
    {
        $this->_filename = $filename;
        parent::__construct($message, $code);
    }

    // }}}
    // {{{ getFilename()

    /**
     * Returns the filename of the file that caused this exception
     *
     * @return string the filename of the file that caused this exception.
     *
     * @see Crypt_GPG_FileException::$_filename
     */
    public function getFilename()
    {
        return $this->_filename;
    }

    // }}}
}

// }}}
// {{{ class Crypt_GPG_OpenSubprocessException

/**
 * An exception thrown when the GPG subprocess cannot be opened
 *
 * This exception is thrown when the {@link Crypt_GPG_Engine} tries to open a
 * new subprocess and fails.
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2005 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_OpenSubprocessException extends Crypt_GPG_Exception
{
    // {{{ private class properties

    /**
     * The command used to try to open the subprocess
     *
     * @var string
     */
    private $_command = '';

    // }}}
    // {{{ __construct()

    /**
     * Creates a new Crypt_GPG_OpenSubprocessException
     *
     * @param string  $message an error message.
     * @param integer $code    a user defined error code.
     * @param string  $command the command that was called to open the
     *                         new subprocess.
     *
     * @see Crypt_GPG::_openSubprocess()
     */
    public function __construct($message, $code = 0, $command = '')
    {
        $this->_command = $command;
        parent::__construct($message, $code);
    }

    // }}}
    // {{{ getCommand()

    /**
     * Returns the contents of the internal _command property
     *
     * @return string the command used to open the subprocess.
     *
     * @see Crypt_GPG_OpenSubprocessException::$_command
     */
    public function getCommand()
    {
        return $this->_command;
    }

    // }}}
}

// }}}
// {{{ class Crypt_GPG_InvalidOperationException

/**
 * An exception thrown when an invalid GPG operation is attempted
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_InvalidOperationException extends Crypt_GPG_Exception
{
    // {{{ private class properties

    /**
     * The attempted operation
     *
     * @var string
     */
    private $_operation = '';

    // }}}
    // {{{ __construct()

    /**
     * Creates a new Crypt_GPG_OpenSubprocessException
     *
     * @param string  $message   an error message.
     * @param integer $code      a user defined error code.
     * @param string  $operation the operation.
     */
    public function __construct($message, $code = 0, $operation = '')
    {
        $this->_operation = $operation;
        parent::__construct($message, $code);
    }

    // }}}
    // {{{ getOperation()

    /**
     * Returns the contents of the internal _operation property
     *
     * @return string the attempted operation.
     *
     * @see Crypt_GPG_InvalidOperationException::$_operation
     */
    public function getOperation()
    {
        return $this->_operation;
    }

    // }}}
}

// }}}
// {{{ class Crypt_GPG_KeyNotFoundException

/**
 * An exception thrown when Crypt_GPG fails to find the key for various
 * operations
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2005 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_KeyNotFoundException extends Crypt_GPG_Exception
{
    // {{{ private class properties

    /**
     * The key identifier that was searched for
     *
     * @var string
     */
    private $_keyId = '';

    // }}}
    // {{{ __construct()

    /**
     * Creates a new Crypt_GPG_KeyNotFoundException
     *
     * @param string  $message an error message.
     * @param integer $code    a user defined error code.
     * @param string  $keyId   the key identifier of the key.
     */
    public function __construct($message, $code = 0, $keyId= '')
    {
        $this->_keyId = $keyId;
        parent::__construct($message, $code);
    }

    // }}}
    // {{{ getKeyId()

    /**
     * Gets the key identifier of the key that was not found
     *
     * @return string the key identifier of the key that was not found.
     */
    public function getKeyId()
    {
        return $this->_keyId;
    }

    // }}}
}

// }}}
// {{{ class Crypt_GPG_NoDataException

/**
 * An exception thrown when Crypt_GPG cannot find valid data for various
 * operations
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_NoDataException extends Crypt_GPG_Exception
{
}

// }}}
// {{{ class Crypt_GPG_BadPassphraseException

/**
 * An exception thrown when a required passphrase is incorrect or missing
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2006-2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_BadPassphraseException extends Crypt_GPG_Exception
{
    // {{{ private class properties

    /**
     * Keys for which the passhprase is missing
     *
     * This contains primary user ids indexed by sub-key id.
     *
     * @var array
     */
    private $_missingPassphrases = array();

    /**
     * Keys for which the passhprase is incorrect
     *
     * This contains primary user ids indexed by sub-key id.
     *
     * @var array
     */
    private $_badPassphrases = array();

    // }}}
    // {{{ __construct()

    /**
     * Creates a new Crypt_GPG_BadPassphraseException
     *
     * @param string  $message            an error message.
     * @param integer $code               a user defined error code.
     * @param array   $badPassphrases     an array containing user ids of keys
     *                                    for which the passphrase is incorrect.
     * @param array   $missingPassphrases an array containing user ids of keys
     *                                    for which the passphrase is missing.
     */
    public function __construct($message, $code = 0,
        array $badPassphrases = array(), array $missingPassphrases = array()
    ) {
        $this->_badPassphrases     = (array) $badPassphrases;
        $this->_missingPassphrases = (array) $missingPassphrases;

        parent::__construct($message, $code);
    }

    // }}}
    // {{{ getBadPassphrases()

    /**
     * Gets keys for which the passhprase is incorrect
     *
     * @return array an array of keys for which the passphrase is incorrect.
     *               The array contains primary user ids indexed by the sub-key
     *               id.
     */
    public function getBadPassphrases()
    {
        return $this->_badPassphrases;
    }

    // }}}
    // {{{ getMissingPassphrases()

    /**
     * Gets keys for which the passhprase is missing 
     *
     * @return array an array of keys for which the passphrase is missing.
     *               The array contains primary user ids indexed by the sub-key
     *               id.
     */
    public function getMissingPassphrases()
    {
        return $this->_missingPassphrases;
    }

    // }}}
}

// }}}
// {{{ class Crypt_GPG_DeletePrivateKeyException

/**
 * An exception thrown when an attempt is made to delete public key that has an
 * associated private key on the keyring
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_DeletePrivateKeyException extends Crypt_GPG_Exception
{
    // {{{ private class properties

    /**
     * The key identifier the deletion attempt was made upon
     *
     * @var string
     */
    private $_keyId = '';

    // }}}
    // {{{ __construct()

    /**
     * Creates a new Crypt_GPG_DeletePrivateKeyException
     *
     * @param string  $message an error message.
     * @param integer $code    a user defined error code.
     * @param string  $keyId   the key identifier of the public key that was
     *                         attempted to delete.
     *
     * @see Crypt_GPG::deletePublicKey()
     */
    public function __construct($message, $code = 0, $keyId = '')
    {
        $this->_keyId = $keyId;
        parent::__construct($message, $code);
    }

    // }}}
    // {{{ getKeyId()

    /**
     * Gets the key identifier of the key that was not found
     *
     * @return string the key identifier of the key that was not found.
     */
    public function getKeyId()
    {
        return $this->_keyId;
    }

    // }}}
}

// }}}
// {{{ class Crypt_GPG_KeyNotCreatedException

/**
 * An exception thrown when an attempt is made to generate a key and the
 * attempt fails
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_KeyNotCreatedException extends Crypt_GPG_Exception
{
}

// }}}
// {{{ class Crypt_GPG_InvalidKeyParamsException

/**
 * An exception thrown when an attempt is made to generate a key and the
 * key parameters set on the key generator are invalid
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2011 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_InvalidKeyParamsException extends Crypt_GPG_Exception
{
    // {{{ private class properties

    /**
     * The key algorithm
     *
     * @var integer
     */
    private $_algorithm = 0;

    /**
     * The key size
     *
     * @var integer
     */
    private $_size = 0;

    /**
     * The key usage
     *
     * @var integer
     */
    private $_usage = 0;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new Crypt_GPG_InvalidKeyParamsException
     *
     * @param string  $message   an error message.
     * @param integer $code      a user defined error code.
     * @param string  $algorithm the key algorithm.
     * @param string  $size      the key size.
     * @param string  $usage     the key usage.
     */
    public function __construct(
        $message,
        $code = 0,
        $algorithm = 0,
        $size = 0,
        $usage = 0
    ) {
        parent::__construct($message, $code);

        $this->_algorithm = $algorithm;
        $this->_size      = $size;
        $this->_usage     = $usage;
    }

    // }}}
    // {{{ getAlgorithm()

    /**
     * Gets the key algorithm
     *
     * @return integer the key algorithm.
     */
    public function getAlgorithm()
    {
        return $this->_algorithm;
    }

    // }}}
    // {{{ getSize()

    /**
     * Gets the key size
     *
     * @return integer the key size.
     */
    public function getSize()
    {
        return $this->_size;
    }

    // }}}
    // {{{ getUsage()

    /**
     * Gets the key usage
     *
     * @return integer the key usage.
     */
    public function getUsage()
    {
        return $this->_usage;
    }

    // }}}
}

// }}}

?>
