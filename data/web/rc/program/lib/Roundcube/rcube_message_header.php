<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2012, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2012, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   E-mail message headers representation                               |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Struct representing an e-mail message header
 *
 * @package    Framework
 * @subpackage Storage
 * @author     Aleksander Machniak <alec@alec.pl>
 */
class rcube_message_header
{
    /**
     * Message sequence number
     *
     * @var int
     */
    public $id;

    /**
     * Message unique identifier
     *
     * @var int
     */
    public $uid;

    /**
     * Message subject
     *
     * @var string
     */
    public $subject;

    /**
     * Message sender (From)
     *
     * @var string
     */
    public $from;

    /**
     * Message recipient (To)
     *
     * @var string
     */
    public $to;

    /**
     * Message additional recipients (Cc)
     *
     * @var string
     */
    public $cc;

    /**
     * Message Reply-To header
     *
     * @var string
     */
    public $replyto;

    /**
     * Message In-Reply-To header
     *
     * @var string
     */
    public $in_reply_to;

    /**
     * Message date (Date)
     *
     * @var string
     */
    public $date;

    /**
     * Message identifier (Message-ID)
     *
     * @var string
     */
    public $messageID;

    /**
     * Message size
     *
     * @var int
     */
    public $size;

    /**
     * Message encoding
     *
     * @var string
     */
    public $encoding;

    /**
     * Message charset
     *
     * @var string
     */
    public $charset;

    /**
     * Message Content-type
     *
     * @var string
     */
    public $ctype;

    /**
     * Message timestamp (based on message date)
     *
     * @var int
     */
    public $timestamp;

    /**
     * IMAP bodystructure string
     *
     * @var string
     */
    public $bodystructure;

    /**
     * IMAP internal date
     *
     * @var string
     */
    public $internaldate;

    /**
     * Message References header
     *
     * @var string
     */
    public $references;

    /**
     * Message priority (X-Priority)
     *
     * @var int
     */
    public $priority;

    /**
     * Message receipt recipient
     *
     * @var string
     */
    public $mdn_to;

    /**
     * IMAP folder this message is stored in
     *
     * @var string
     */
    public $folder;

    /**
     * Other message headers
     *
     * @var array
     */
    public $others = array();

    /**
     * Message flags
     *
     * @var array
     */
    public $flags = array();

    // map header to rcube_message_header object property
    private $obj_headers = array(
        'date'      => 'date',
        'from'      => 'from',
        'to'        => 'to',
        'subject'   => 'subject',
        'reply-to'  => 'replyto',
        'cc'        => 'cc',
        'bcc'       => 'bcc',
        'mbox'      => 'folder',
        'folder'    => 'folder',
        'content-transfer-encoding' => 'encoding',
        'in-reply-to'               => 'in_reply_to',
        'content-type'              => 'ctype',
        'charset'                   => 'charset',
        'references'                => 'references',
        'return-receipt-to'         => 'mdn_to',
        'disposition-notification-to' => 'mdn_to',
        'x-confirm-reading-to'      => 'mdn_to',
        'message-id'                => 'messageID',
        'x-priority'                => 'priority',
    );

    /**
     * Returns header value
     */
    public function get($name, $decode = true)
    {
        $name = strtolower($name);

        if (isset($this->obj_headers[$name])) {
            $value = $this->{$this->obj_headers[$name]};
        }
        else {
            $value = $this->others[$name];
        }

        if ($decode) {
            if (is_array($value)) {
                foreach ($value as $key => $val) {
                    $val         = rcube_mime::decode_header($val, $this->charset);
                    $value[$key] = rcube_charset::clean($val);
                }
            }
            else {
                $value = rcube_mime::decode_header($value, $this->charset);
                $value = rcube_charset::clean($value);
            }
        }

        return $value;
    }

    /**
     * Sets header value
     */
    public function set($name, $value)
    {
        $name = strtolower($name);

        if (isset($this->obj_headers[$name])) {
            $this->{$this->obj_headers[$name]} = $value;
        }
        else {
            $this->others[$name] = $value;
        }
    }


    /**
     * Factory method to instantiate headers from a data array
     *
     * @param array Hash array with header values
     * @return object rcube_message_header instance filled with headers values
     */
    public static function from_array($arr)
    {
        $obj = new rcube_message_header;
        foreach ($arr as $k => $v)
            $obj->set($k, $v);

        return $obj;
    }
}


/**
 * Class for sorting an array of rcube_message_header objects in a predetermined order.
 *
 * @package    Framework
 * @subpackage Storage
 * @author  Aleksander Machniak <alec@alec.pl>
 */
class rcube_message_header_sorter
{
    private $uids = array();


    /**
     * Set the predetermined sort order.
     *
     * @param array $index  Numerically indexed array of IMAP UIDs
     */
    function set_index($index)
    {
        $index = array_flip($index);

        $this->uids = $index;
    }

    /**
     * Sort the array of header objects
     *
     * @param array $headers Array of rcube_message_header objects indexed by UID
     */
    function sort_headers(&$headers)
    {
        uksort($headers, array($this, "compare_uids"));
    }

    /**
     * Sort method called by uksort()
     *
     * @param int $a Array key (UID)
     * @param int $b Array key (UID)
     */
    function compare_uids($a, $b)
    {
        // then find each sequence number in my ordered list
        $posa = isset($this->uids[$a]) ? intval($this->uids[$a]) : -1;
        $posb = isset($this->uids[$b]) ? intval($this->uids[$b]) : -1;

        // return the relative position as the comparison value
        return $posa - $posb;
    }
}
