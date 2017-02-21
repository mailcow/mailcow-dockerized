<?php
/**
 * Net_Socket
 *
 * PHP Version 4
 *
 * Copyright (c) 1997-2013 The PHP Group
 *
 * This source file is subject to version 2.0 of the PHP license,
 * that is bundled with this package in the file LICENSE, and is
 * available at through the world-wide-web at
 * http://www.php.net/license/2_02.txt.
 * If you did not receive a copy of the PHP license and are unable to
 * obtain it through the world-wide-web, please send a note to
 * license@php.net so we can mail you a copy immediately.
 *
 * Authors: Stig Bakken <ssb@php.net>
 *          Chuck Hagenbuch <chuck@horde.org>
 *
 * @category  Net
 * @package   Net_Socket
 * @author    Stig Bakken <ssb@php.net>
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @copyright 1997-2003 The PHP Group
 * @license   http://www.php.net/license/2_02.txt PHP 2.02
 * @link      http://pear.php.net/packages/Net_Socket
 */

require_once 'PEAR.php';

define('NET_SOCKET_READ', 1);
define('NET_SOCKET_WRITE', 2);
define('NET_SOCKET_ERROR', 4);

/**
 * Generalized Socket class.
 *
 * @category  Net
 * @package   Net_Socket
 * @author    Stig Bakken <ssb@php.net>
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @copyright 1997-2003 The PHP Group
 * @license   http://www.php.net/license/2_02.txt PHP 2.02
 * @link      http://pear.php.net/packages/Net_Socket
 */
class Net_Socket extends PEAR
{
    /**
     * Socket file pointer.
     * @var resource $fp
     */
    var $fp = null;

    /**
     * Whether the socket is blocking. Defaults to true.
     * @var boolean $blocking
     */
    var $blocking = true;

    /**
     * Whether the socket is persistent. Defaults to false.
     * @var boolean $persistent
     */
    var $persistent = false;

    /**
     * The IP address to connect to.
     * @var string $addr
     */
    var $addr = '';

    /**
     * The port number to connect to.
     * @var integer $port
     */
    var $port = 0;

    /**
     * Number of seconds to wait on socket operations before assuming
     * there's no more data. Defaults to no timeout.
     * @var integer|float $timeout
     */
    var $timeout = null;

    /**
     * Number of bytes to read at a time in readLine() and
     * readAll(). Defaults to 2048.
     * @var integer $lineLength
     */
    var $lineLength = 2048;

    /**
     * The string to use as a newline terminator. Usually "\r\n" or "\n".
     * @var string $newline
     */
    var $newline = "\r\n";

    /**
     * Connect to the specified port. If called when the socket is
     * already connected, it disconnects and connects again.
     *
     * @param string  $addr       IP address or host name (may be with protocol prefix).
     * @param integer $port       TCP port number.
     * @param boolean $persistent (optional) Whether the connection is
     *                            persistent (kept open between requests
     *                            by the web server).
     * @param integer $timeout    (optional) Connection socket timeout.
     * @param array   $options    See options for stream_context_create.
     *
     * @access public
     *
     * @return boolean|PEAR_Error  True on success or a PEAR_Error on failure.
     */
    function connect($addr, $port = 0, $persistent = null,
                     $timeout = null, $options = null)
    {
        if (is_resource($this->fp)) {
            @fclose($this->fp);
            $this->fp = null;
        }

        if (!$addr) {
            return $this->raiseError('$addr cannot be empty');
        } else if (strspn($addr, ':.0123456789') == strlen($addr)) {
            $this->addr = strpos($addr, ':') !== false ? '['.$addr.']' : $addr;
        } else {
            $this->addr = $addr;
        }

        $this->port = $port % 65536;

        if ($persistent !== null) {
            $this->persistent = $persistent;
        }

        $openfunc = $this->persistent ? 'pfsockopen' : 'fsockopen';
        $errno    = 0;
        $errstr   = '';

        $old_track_errors = @ini_set('track_errors', 1);

        if ($timeout <= 0) {
            $timeout = @ini_get('default_socket_timeout');
        }

        if ($options && function_exists('stream_context_create')) {
            $context = stream_context_create($options);

            // Since PHP 5 fsockopen doesn't allow context specification
            if (function_exists('stream_socket_client')) {
                $flags = STREAM_CLIENT_CONNECT;

                if ($this->persistent) {
                    $flags = STREAM_CLIENT_PERSISTENT;
                }

                $addr = $this->addr . ':' . $this->port;
                $fp   = stream_socket_client($addr, $errno, $errstr,
                                             $timeout, $flags, $context);
            } else {
                $fp = @$openfunc($this->addr, $this->port, $errno,
                                 $errstr, $timeout, $context);
            }
        } else {
            $fp = @$openfunc($this->addr, $this->port, $errno, $errstr, $timeout);
        }

        if (!$fp) {
            if ($errno == 0 && !strlen($errstr) && isset($php_errormsg)) {
                $errstr = $php_errormsg;
            }
            @ini_set('track_errors', $old_track_errors);
            return $this->raiseError($errstr, $errno);
        }

        @ini_set('track_errors', $old_track_errors);
        $this->fp = $fp;
        $this->setTimeout();
        return $this->setBlocking($this->blocking);
    }

    /**
     * Disconnects from the peer, closes the socket.
     *
     * @access public
     * @return mixed true on success or a PEAR_Error instance otherwise
     */
    function disconnect()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        @fclose($this->fp);
        $this->fp = null;
        return true;
    }

    /**
     * Set the newline character/sequence to use.
     *
     * @param string $newline  Newline character(s)
     * @return boolean True
     */
    function setNewline($newline)
    {
        $this->newline = $newline;
        return true;
    }

    /**
     * Find out if the socket is in blocking mode.
     *
     * @access public
     * @return boolean  The current blocking mode.
     */
    function isBlocking()
    {
        return $this->blocking;
    }

    /**
     * Sets whether the socket connection should be blocking or
     * not. A read call to a non-blocking socket will return immediately
     * if there is no data available, whereas it will block until there
     * is data for blocking sockets.
     *
     * @param boolean $mode True for blocking sockets, false for nonblocking.
     *
     * @access public
     * @return mixed true on success or a PEAR_Error instance otherwise
     */
    function setBlocking($mode)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $this->blocking = $mode;
        stream_set_blocking($this->fp, (int)$this->blocking);
        return true;
    }

    /**
     * Sets the timeout value on socket descriptor,
     * expressed in the sum of seconds and microseconds
     *
     * @param integer $seconds      Seconds.
     * @param integer $microseconds Microseconds, optional.
     *
     * @access public
     * @return mixed True on success or false on failure or
     *               a PEAR_Error instance when not connected
     */
    function setTimeout($seconds = null, $microseconds = null)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        if ($seconds === null && $microseconds === null) {
            $seconds      = (int) $this->timeout;
            $microseconds = (int) (($this->timeout - $seconds) * 1000000);
        } else {
            $this->timeout = $seconds + $microseconds/1000000;
        }

        if ($this->timeout > 0) {
            return stream_set_timeout($this->fp, (int) $seconds, (int) $microseconds);
        }
        else {
            return false;
        }
    }

    /**
     * Sets the file buffering size on the stream.
     * See php's stream_set_write_buffer for more information.
     *
     * @param integer $size Write buffer size.
     *
     * @access public
     * @return mixed on success or an PEAR_Error object otherwise
     */
    function setWriteBuffer($size)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $returned = stream_set_write_buffer($this->fp, $size);
        if ($returned == 0) {
            return true;
        }
        return $this->raiseError('Cannot set write buffer.');
    }

    /**
     * Returns information about an existing socket resource.
     * Currently returns four entries in the result array:
     *
     * <p>
     * timed_out (bool) - The socket timed out waiting for data<br>
     * blocked (bool) - The socket was blocked<br>
     * eof (bool) - Indicates EOF event<br>
     * unread_bytes (int) - Number of bytes left in the socket buffer<br>
     * </p>
     *
     * @access public
     * @return mixed Array containing information about existing socket
     *               resource or a PEAR_Error instance otherwise
     */
    function getStatus()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return stream_get_meta_data($this->fp);
    }

    /**
     * Get a specified line of data
     *
     * @param int $size Reading ends when size - 1 bytes have been read,
     *                  or a newline or an EOF (whichever comes first).
     *                  If no size is specified, it will keep reading from
     *                  the stream until it reaches the end of the line.
     *
     * @access public
     * @return mixed $size bytes of data from the socket, or a PEAR_Error if
     *         not connected. If an error occurs, FALSE is returned.
     */
    function gets($size = null)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        if (is_null($size)) {
            return @fgets($this->fp);
        } else {
            return @fgets($this->fp, $size);
        }
    }

    /**
     * Read a specified amount of data. This is guaranteed to return,
     * and has the added benefit of getting everything in one fread()
     * chunk; if you know the size of the data you're getting
     * beforehand, this is definitely the way to go.
     *
     * @param integer $size The number of bytes to read from the socket.
     *
     * @access public
     * @return $size bytes of data from the socket, or a PEAR_Error if
     *         not connected.
     */
    function read($size)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return @fread($this->fp, $size);
    }

    /**
     * Write a specified amount of data.
     *
     * @param string  $data      Data to write.
     * @param integer $blocksize Amount of data to write at once.
     *                           NULL means all at once.
     *
     * @access public
     * @return mixed If the socket is not connected, returns an instance of
     *               PEAR_Error.
     *               If the write succeeds, returns the number of bytes written.
     *               If the write fails, returns false.
     *               If the socket times out, returns an instance of PEAR_Error.
     */
    function write($data, $blocksize = null)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        if (is_null($blocksize) && !OS_WINDOWS) {
            $written = @fwrite($this->fp, $data);

            // Check for timeout or lost connection
            if (!$written) {
                $meta_data = $this->getStatus();

                if (!is_array($meta_data)) {
                    return $meta_data; // PEAR_Error
                }

                if (!empty($meta_data['timed_out'])) {
                    return $this->raiseError('timed out');
                }
            }

            return $written;
        } else {
            if (is_null($blocksize)) {
                $blocksize = 1024;
            }

            $pos  = 0;
            $size = strlen($data);
            while ($pos < $size) {
                $written = @fwrite($this->fp, substr($data, $pos, $blocksize));

                // Check for timeout or lost connection
                if (!$written) {
                    $meta_data = $this->getStatus();

                    if (!is_array($meta_data)) {
                        return $meta_data; // PEAR_Error
                    }

                    if (!empty($meta_data['timed_out'])) {
                        return $this->raiseError('timed out');
                    }

                    return $written;
                }

                $pos += $written;
            }

            return $pos;
        }
    }

    /**
     * Write a line of data to the socket, followed by a trailing newline.
     *
     * @param string $data Data to write
     *
     * @access public
     * @return mixed fwrite() result, or PEAR_Error when not connected
     */
    function writeLine($data)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return fwrite($this->fp, $data . $this->newline);
    }

    /**
     * Tests for end-of-file on a socket descriptor.
     *
     * Also returns true if the socket is disconnected.
     *
     * @access public
     * @return bool
     */
    function eof()
    {
        return (!is_resource($this->fp) || feof($this->fp));
    }

    /**
     * Reads a byte of data
     *
     * @access public
     * @return 1 byte of data from the socket, or a PEAR_Error if
     *         not connected.
     */
    function readByte()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        return ord(@fread($this->fp, 1));
    }

    /**
     * Reads a word of data
     *
     * @access public
     * @return 1 word of data from the socket, or a PEAR_Error if
     *         not connected.
     */
    function readWord()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $buf = @fread($this->fp, 2);
        return (ord($buf[0]) + (ord($buf[1]) << 8));
    }

    /**
     * Reads an int of data
     *
     * @access public
     * @return integer  1 int of data from the socket, or a PEAR_Error if
     *                  not connected.
     */
    function readInt()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $buf = @fread($this->fp, 4);
        return (ord($buf[0]) + (ord($buf[1]) << 8) +
                (ord($buf[2]) << 16) + (ord($buf[3]) << 24));
    }

    /**
     * Reads a zero-terminated string of data
     *
     * @access public
     * @return string, or a PEAR_Error if
     *         not connected.
     */
    function readString()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $string = '';
        while (($char = @fread($this->fp, 1)) != "\x00") {
            $string .= $char;
        }
        return $string;
    }

    /**
     * Reads an IP Address and returns it in a dot formatted string
     *
     * @access public
     * @return Dot formatted string, or a PEAR_Error if
     *         not connected.
     */
    function readIPAddress()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $buf = @fread($this->fp, 4);
        return sprintf('%d.%d.%d.%d', ord($buf[0]), ord($buf[1]),
                       ord($buf[2]), ord($buf[3]));
    }

    /**
     * Read until either the end of the socket or a newline, whichever
     * comes first. Strips the trailing newline from the returned data.
     *
     * @access public
     * @return All available data up to a newline, without that
     *         newline, or until the end of the socket, or a PEAR_Error if
     *         not connected.
     */
    function readLine()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $line = '';

        $timeout = time() + $this->timeout;

        while (!feof($this->fp) && (!$this->timeout || time() < $timeout)) {
            $line .= @fgets($this->fp, $this->lineLength);
            if (substr($line, -1) == "\n") {
                return rtrim($line, $this->newline);
            }
        }
        return $line;
    }

    /**
     * Read until the socket closes, or until there is no more data in
     * the inner PHP buffer. If the inner buffer is empty, in blocking
     * mode we wait for at least 1 byte of data. Therefore, in
     * blocking mode, if there is no data at all to be read, this
     * function will never exit (unless the socket is closed on the
     * remote end).
     *
     * @access public
     *
     * @return string  All data until the socket closes, or a PEAR_Error if
     *                 not connected.
     */
    function readAll()
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $data = '';
        while (!feof($this->fp)) {
            $data .= @fread($this->fp, $this->lineLength);
        }
        return $data;
    }

    /**
     * Runs the equivalent of the select() system call on the socket
     * with a timeout specified by tv_sec and tv_usec.
     *
     * @param integer $state   Which of read/write/error to check for.
     * @param integer $tv_sec  Number of seconds for timeout.
     * @param integer $tv_usec Number of microseconds for timeout.
     *
     * @access public
     * @return False if select fails, integer describing which of read/write/error
     *         are ready, or PEAR_Error if not connected.
     */
    function select($state, $tv_sec, $tv_usec = 0)
    {
        if (!is_resource($this->fp)) {
            return $this->raiseError('not connected');
        }

        $read   = null;
        $write  = null;
        $except = null;
        if ($state & NET_SOCKET_READ) {
            $read[] = $this->fp;
        }
        if ($state & NET_SOCKET_WRITE) {
            $write[] = $this->fp;
        }
        if ($state & NET_SOCKET_ERROR) {
            $except[] = $this->fp;
        }
        if (false === ($sr = stream_select($read, $write, $except,
                                          $tv_sec, $tv_usec))) {
            return false;
        }

        $result = 0;
        if (count($read)) {
            $result |= NET_SOCKET_READ;
        }
        if (count($write)) {
            $result |= NET_SOCKET_WRITE;
        }
        if (count($except)) {
            $result |= NET_SOCKET_ERROR;
        }
        return $result;
    }

    /**
     * Turns encryption on/off on a connected socket.
     *
     * @param bool    $enabled Set this parameter to true to enable encryption
     *                         and false to disable encryption.
     * @param integer $type    Type of encryption. See stream_socket_enable_crypto()
     *                         for values.
     *
     * @see    http://se.php.net/manual/en/function.stream-socket-enable-crypto.php
     * @access public
     * @return false on error, true on success and 0 if there isn't enough data
     *         and the user should try again (non-blocking sockets only).
     *         A PEAR_Error object is returned if the socket is not
     *         connected
     */
    function enableCrypto($enabled, $type)
    {
        if (version_compare(phpversion(), "5.1.0", ">=")) {
            if (!is_resource($this->fp)) {
                return $this->raiseError('not connected');
            }
            return @stream_socket_enable_crypto($this->fp, $enabled, $type);
        } else {
            $msg = 'Net_Socket::enableCrypto() requires php version >= 5.1.0';
            return $this->raiseError($msg);
        }
    }

}
