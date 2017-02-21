<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Contains a data class representing a GPG user id
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
 * @copyright 2008-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */

// {{{ class Crypt_GPG_UserId

/**
 * A class for GPG user id information
 *
 * This class is used to store the results of the {@link Crypt_GPG::getKeys()}
 * method. User id objects are members of a {@link Crypt_GPG_Key} object.
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2008-2010 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 * @see       Crypt_GPG::getKeys()
 * @see       Crypt_GPG_Key::getUserIds()
 */
class Crypt_GPG_UserId
{
    // {{{ class properties

    /**
     * The name field of this user id
     *
     * @var string
     */
    private $_name = '';

    /**
     * The comment field of this user id
     *
     * @var string
     */
    private $_comment = '';

    /**
     * The email field of this user id
     *
     * @var string
     */
    private $_email = '';

    /**
     * Whether or not this user id is revoked
     *
     * @var boolean
     */
    private $_isRevoked = false;

    /**
     * Whether or not this user id is valid
     *
     * @var boolean
     */
    private $_isValid = true;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new user id
     *
     * User ids can be initialized from an array of named values. Available
     * names are:
     *
     * - <kbd>string  name</kbd>    - the name field of the user id.
     * - <kbd>string  comment</kbd> - the comment field of the user id.
     * - <kbd>string  email</kbd>   - the email field of the user id.
     * - <kbd>boolean valid</kbd>   - whether or not the user id is valid.
     * - <kbd>boolean revoked</kbd> - whether or not the user id is revoked.
     *
     * @param Crypt_GPG_UserId|string|array $userId optional. Either an
     *        existing user id object, which is copied; a user id string, which
     *        is parsed; or an array of initial values.
     */
    public function __construct($userId = null)
    {
        // parse from string
        if (is_string($userId)) {
            $userId = self::parse($userId);
        }

        // copy from object
        if ($userId instanceof Crypt_GPG_UserId) {
            $this->_name      = $userId->_name;
            $this->_comment   = $userId->_comment;
            $this->_email     = $userId->_email;
            $this->_isRevoked = $userId->_isRevoked;
            $this->_isValid   = $userId->_isValid;
        }

        // initialize from array
        if (is_array($userId)) {
            if (array_key_exists('name', $userId)) {
                $this->setName($userId['name']);
            }

            if (array_key_exists('comment', $userId)) {
                $this->setComment($userId['comment']);
            }

            if (array_key_exists('email', $userId)) {
                $this->setEmail($userId['email']);
            }

            if (array_key_exists('revoked', $userId)) {
                $this->setRevoked($userId['revoked']);
            }

            if (array_key_exists('valid', $userId)) {
                $this->setValid($userId['valid']);
            }
        }
    }

    // }}}
    // {{{ getName()

    /**
     * Gets the name field of this user id
     *
     * @return string the name field of this user id.
     */
    public function getName()
    {
        return $this->_name;
    }

    // }}}
    // {{{ getComment()

    /**
     * Gets the comments field of this user id
     *
     * @return string the comments field of this user id.
     */
    public function getComment()
    {
        return $this->_comment;
    }

    // }}}
    // {{{ getEmail()

    /**
     * Gets the email field of this user id
     *
     * @return string the email field of this user id.
     */
    public function getEmail()
    {
        return $this->_email;
    }

    // }}}
    // {{{ isRevoked()

    /**
     * Gets whether or not this user id is revoked
     *
     * @return boolean true if this user id is revoked and false if it is not.
     */
    public function isRevoked()
    {
        return $this->_isRevoked;
    }

    // }}}
    // {{{ isValid()

    /**
     * Gets whether or not this user id is valid
     *
     * @return boolean true if this user id is valid and false if it is not.
     */
    public function isValid()
    {
        return $this->_isValid;
    }

    // }}}
    // {{{ __toString()

    /**
     * Gets a string representation of this user id
     *
     * The string is formatted as:
     * <b><kbd>name (comment) <email-address></kbd></b>.
     *
     * @return string a string representation of this user id.
     */
    public function __toString()
    {
        $components = array();

        if (mb_strlen($this->_name, '8bit') > 0) {
            $components[] = $this->_name;
        }

        if (mb_strlen($this->_comment, '8bit') > 0) {
            $components[] = '(' . $this->_comment . ')';
        }

        if (mb_strlen($this->_email, '8bit') > 0) {
            $components[] = '<' . $this->_email. '>';
        }

        return implode(' ', $components);
    }

    // }}}
    // {{{ setName()

    /**
     * Sets the name field of this user id
     *
     * @param string $name the name field of this user id.
     *
     * @return Crypt_GPG_UserId the current object, for fluent interface.
     */
    public function setName($name)
    {
        $this->_name = strval($name);
        return $this;
    }

    // }}}
    // {{{ setComment()

    /**
     * Sets the comment field of this user id
     *
     * @param string $comment the comment field of this user id.
     *
     * @return Crypt_GPG_UserId the current object, for fluent interface.
     */
    public function setComment($comment)
    {
        $this->_comment = strval($comment);
        return $this;
    }

    // }}}
    // {{{ setEmail()

    /**
     * Sets the email field of this user id
     *
     * @param string $email the email field of this user id.
     *
     * @return Crypt_GPG_UserId the current object, for fluent interface.
     */
    public function setEmail($email)
    {
        $this->_email = strval($email);
        return $this;
    }

    // }}}
    // {{{ setRevoked()

    /**
     * Sets whether or not this user id is revoked
     *
     * @param boolean $isRevoked whether or not this user id is revoked.
     *
     * @return Crypt_GPG_UserId the current object, for fluent interface.
     */
    public function setRevoked($isRevoked)
    {
        $this->_isRevoked = ($isRevoked) ? true : false;
        return $this;
    }

    // }}}
    // {{{ setValid()

    /**
     * Sets whether or not this user id is valid
     *
     * @param boolean $isValid whether or not this user id is valid.
     *
     * @return Crypt_GPG_UserId the current object, for fluent interface.
     */
    public function setValid($isValid)
    {
        $this->_isValid = ($isValid) ? true : false;
        return $this;
    }

    // }}}
    // {{{ parse()

    /**
     * Parses a user id object from a user id string
     *
     * A user id string is of the form:
     * <b><kbd>name (comment) <email-address></kbd></b> with the <i>comment</i>
     * and <i>email-address</i> fields being optional.
     *
     * @param string $string the user id string to parse.
     *
     * @return Crypt_GPG_UserId the user id object parsed from the string.
     */
    public static function parse($string)
    {
        $userId  = new Crypt_GPG_UserId();
        $name    = '';
        $email   = '';
        $comment = '';

        // get email address from end of string if it exists
        $matches = array();
        if (preg_match('/^(.*?)<([^>]+)>$/', $string, $matches) === 1) {
            $string = trim($matches[1]);
            $email  = $matches[2];
        }

        // get comment from end of string if it exists
        $matches = array();
        if (preg_match('/^(.+?) \(([^\)]+)\)$/', $string, $matches) === 1) {
            $string  = $matches[1];
            $comment = $matches[2];
        }

        // there can be an email without a name
        if (!$email && preg_match('/^[\S]+@[\S]+$/', $string, $matches) === 1) {
            $email = $string;
        } else {
            $name = $string;
        }

        $userId->setName($name);
        $userId->setComment($comment);
        $userId->setEmail($email);

        return $userId;
    }

    // }}}
}

// }}}

?>
