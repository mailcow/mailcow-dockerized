<?php

/**
 +-------------------------------------------------------------------------+
 | Signature class for the Enigma Plugin                                   |
 |                                                                         |
 | Copyright (C) 2010-2015 The Roundcube Dev Team                          |
 |                                                                         |
 | Licensed under the GNU General Public License version 3 or              |
 | any later version with exceptions for skins & plugins.                  |
 | See the README file for a full license statement.                       |
 |                                                                         |
 +-------------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                              |
 +-------------------------------------------------------------------------+
*/

class enigma_signature
{
    public $id;
    public $valid;
    public $fingerprint;
    public $created;
    public $expires;
    public $name;
    public $comment;
    public $email;

    // Set it to true if signature is valid, but part of the message
    // was out of the signed block
    public $partial;
}
