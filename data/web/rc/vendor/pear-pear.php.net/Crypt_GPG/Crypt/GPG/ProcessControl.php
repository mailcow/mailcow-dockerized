<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * A class for monitoring and terminating processes
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
 * @copyright 2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */

// {{{ class Crypt_GPG_ProcessControl

/**
 * A class for monitoring and terminating processes by PID
 *
 * This is used to safely terminate the gpg-agent for GnuPG 2.x. This class
 * is limited in its abilities and can only check if a PID is running and
 * send a PID SIGTERM.
 *
 * @category  Encryption
 * @package   Crypt_GPG
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://pear.php.net/package/Crypt_GPG
 */
class Crypt_GPG_ProcessControl
{
    // {{{ protected properties

    /**
     * The PID (process identifier) being monitored
     *
     * @var integer
     */
    protected $pid;

    // }}}
    // {{{ __construct()

    /**
     * Creates a new process controller from the given PID (process identifier)
     *
     * @param integer $pid the PID (process identifier).
     */
    public function __construct($pid)
    {
        $this->pid = $pid;
    }

    // }}}
    // {{{ public function getPid()

    /**
     * Gets the PID (process identifier) being controlled
     *
     * @return integer the PID being controlled.
     */
    public function getPid()
    {
        return $this->pid;
    }

    // }}}
    // {{{ isRunning()

    /**
     * Checks if the process is running
     *
     * If the <kbd>posix</kbd> extension is available, <kbd>posix_getpgid()</kbd>
     * is used. Otherwise <kbd>ps</kbd> is used on UNIX-like systems and
     * <kbd>tasklist</kbd> on Windows.
     *
     * @return boolean true if the process is running, false if not.
     */
    public function isRunning()
    {
        $running = false;

        if (function_exists('posix_getpgid')) {
            $running = false !== posix_getpgid($this->pid);
        } elseif (PHP_OS === 'WINNT') {
            $command = 'tasklist /fo csv /nh /fi '
                . escapeshellarg('PID eq ' . $this->pid);

            $result  = exec($command);
            $parts   = explode(',', $result);
            $running = (count($parts) > 1 && trim($parts[1], '"') == $this->pid);
        } else {
            $result  = exec('ps -p ' . escapeshellarg($this->pid) . ' -o pid=');
            $running = (trim($result) == $this->pid);
        }

        return $running;
    }

    // }}}
    // {{{ terminate()

    /**
     * Ends the process gracefully
     *
     * The signal SIGTERM is sent to the process. The gpg-agent process will
     * end gracefully upon receiving the SIGTERM signal. Upon 3 consecutive
     * SIGTERM signals the gpg-agent will forcefully shut down.
     *
     * If the <kbd>posix</kbd> extension is available, <kbd>posix_kill()</kbd>
     * is used. Otherwise <kbd>kill</kbd> is used on UNIX-like systems and
     * <kbd>taskkill</kbd> is used in Windows.
     *
     * @return void
     */
    public function terminate()
    {
        if (function_exists('posix_kill')) {
            posix_kill($this->pid, 15);
        } elseif (PHP_OS === 'WINNT') {
            exec('taskkill /PID ' . escapeshellarg($this->pid));
        } else {
            exec('kill -15 ' . escapeshellarg($this->pid));
        }
    }

    // }}}
}

// }}}

?>
