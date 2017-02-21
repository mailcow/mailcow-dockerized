<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 | Copyright (C) 2011, Kolab Systems AG                                  |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide database supported session management                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 | Author: Cor Bosman <cor@roundcu.be>                                   |
 +-----------------------------------------------------------------------+
*/

/**
 * Class to provide native php session storage
 *
 * @package    Framework
 * @subpackage Core
 * @author     Thomas Bruederli <roundcube@gmail.com>
 * @author     Aleksander Machniak <alec@alec.pl>
 * @author     Cor Bosman <cor@roundcu.be>
 */
class rcube_session_php extends rcube_session {

    /**
     * native php sessions don't need a save handler
     * we do need to define abstract function implementations but they are not used.
     */

    public function open($save_path, $session_name) {}
    public function close() {}
    public function destroy($key) {}
    public function read($key) {}
    public function write($key, $vars) {}
    public function update($key, $newvars, $oldvars) {}

    /**
     * @param Object $config
     */
    public function __construct($config)
    {
        parent::__construct($config);
    }

    /**
     * Wrapper for session_write_close()
     */
    public function write_close()
    {
        $_SESSION['__IP'] = $this->ip;
        $_SESSION['__MTIME'] = time();

        parent::write_close();
    }

    /**
     * Wrapper for session_start()
     */
    public function start()
    {
        parent::start();

        $this->key     = session_id();
        $this->ip      = $_SESSION['__IP'];
        $this->changed = $_SESSION['__MTIME'];

    }
}
