<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2014, The Roundcube Dev Team                       |
 | Copyright (C) 2002-2010, The Horde Project (http://www.horde.org/)    |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   MS-TNEF format decoder                                              |
 +-----------------------------------------------------------------------+
 | Author: Jan Schneider <jan@horde.org>                                 |
 | Author: Michael Slusarz <slusarz@horde.org>                           |
 +-----------------------------------------------------------------------+
*/

/**
 * MS-TNEF format decoder based on code by:
 *   Graham Norbury <gnorbury@bondcar.com>
 * Original design by:
 *   Thomas Boll <tb@boll.ch>, Mark Simpson <damned@world.std.com>
 *
 * @package    Framework
 * @subpackage Storage
 */
class rcube_tnef_decoder
{
    const SIGNATURE = 0x223e9f78;
    const LVL_MESSAGE = 0x01;
    const LVL_ATTACHMENT = 0x02;

    const ASUBJECT = 0x88004;
    const AMCLASS = 0x78008;
    const ATTACHDATA = 0x6800f;
    const AFILENAME = 0x18010;
    const ARENDDATA = 0x69002;
    const AMAPIATTRS = 0x69005;
    const AVERSION = 0x89006;

    const MAPI_NULL = 0x0001;
    const MAPI_SHORT = 0x0002;
    const MAPI_INT = 0x0003;
    const MAPI_FLOAT = 0x0004;
    const MAPI_DOUBLE = 0x0005;
    const MAPI_CURRENCY = 0x0006;
    const MAPI_APPTIME = 0x0007;
    const MAPI_ERROR = 0x000a;
    const MAPI_BOOLEAN = 0x000b;
    const MAPI_OBJECT = 0x000d;
    const MAPI_INT8BYTE = 0x0014;
    const MAPI_STRING = 0x001e;
    const MAPI_UNICODE_STRING = 0x001f;
    const MAPI_SYSTIME = 0x0040;
    const MAPI_CLSID = 0x0048;
    const MAPI_BINARY = 0x0102;

    const MAPI_ATTACH_LONG_FILENAME = 0x3707;
    const MAPI_ATTACH_MIME_TAG = 0x370E;

    const MAPI_NAMED_TYPE_ID = 0x0000;
    const MAPI_NAMED_TYPE_STRING = 0x0001;
    const MAPI_MV_FLAG = 0x1000;

    /**
     * Decompress the data.
     *
     * @param string $data   The data to decompress.
     * @param array $params  An array of arguments needed to decompress the
     *                       data.
     *
     * @return mixed  The decompressed data.
     */
    public function decompress($data, $params = array())
    {
        $out = array();

        if ($this->_geti($data, 32) == self::SIGNATURE) {
            $this->_geti($data, 16);

            while (strlen($data) > 0) {
                switch ($this->_geti($data, 8)) {
                case self::LVL_MESSAGE:
                    $this->_decodeMessage($data);
                    break;

                case self::LVL_ATTACHMENT:
                    $this->_decodeAttachment($data, $out);
                    break;
                }
            }
        }

        return array_reverse($out);
    }

    /**
     * TODO
     *
     * @param string &$data  The data string.
     * @param integer $bits  How many bits to retrieve.
     *
     * @return TODO
     */
    protected function _getx(&$data, $bits)
    {
        $value = null;

        if (strlen($data) >= $bits) {
            $value = substr($data, 0, $bits);
            $data = substr_replace($data, '', 0, $bits);
        }

        return $value;
    }

    /**
     * TODO
     *
     * @param string &$data  The data string.
     * @param integer $bits  How many bits to retrieve.
     *
     * @return TODO
     */
    protected function _geti(&$data, $bits)
    {
        $bytes = $bits / 8;
        $value = null;

        if (strlen($data) >= $bytes) {
            $value = ord($data[0]);
            if ($bytes >= 2) {
                $value += (ord($data[1]) << 8);
            }
            if ($bytes >= 4) {
                $value += (ord($data[2]) << 16) + (ord($data[3]) << 24);
            }
            $data = substr_replace($data, '', 0, $bytes);
        }

        return $value;
    }

    /**
     * TODO
     *
     * @param string &$data      The data string.
     * @param string $attribute  TODO
     */
    protected function _decodeAttribute(&$data, $attribute)
    {
        /* Data. */
        $this->_getx($data, $this->_geti($data, 32));

        /* Checksum. */
        $this->_geti($data, 16);
    }

    /**
     * TODO
     *
     * @param string $data             The data string.
     * @param array &$attachment_data  TODO
     */
    protected function _extractMapiAttributes($data, &$attachment_data)
    {
        /* Number of attributes. */
        $number = $this->_geti($data, 32);

        while ((strlen($data) > 0) && $number--) {
            $have_mval = false;
            $num_mval = 1;
            $named_id = $value = null;
            $attr_type = $this->_geti($data, 16);
            $attr_name = $this->_geti($data, 16);

            if (($attr_type & self::MAPI_MV_FLAG) != 0) {
                $have_mval = true;
                $attr_type = $attr_type & ~self::MAPI_MV_FLAG;
            }

            if (($attr_name >= 0x8000) && ($attr_name < 0xFFFE)) {
                $this->_getx($data, 16);
                $named_type = $this->_geti($data, 32);

                switch ($named_type) {
                case self::MAPI_NAMED_TYPE_ID:
                    $named_id = $this->_geti($data, 32);
                    $attr_name = $named_id;
                    break;

                case self::MAPI_NAMED_TYPE_STRING:
                    $attr_name = 0x9999;
                    $idlen = $this->_geti($data, 32);
                    $datalen = $idlen + ((4 - ($idlen % 4)) % 4);
                    $named_id = substr($this->_getx($data, $datalen), 0, $idlen);
                    break;
                }
            }

            if ($have_mval) {
                $num_mval = $this->_geti($data, 32);
            }

            switch ($attr_type) {
            case self::MAPI_SHORT:
                $value = $this->_geti($data, 16);
                break;

            case self::MAPI_INT:
            case self::MAPI_BOOLEAN:
                for ($i = 0; $i < $num_mval; $i++) {
                    $value = $this->_geti($data, 32);
                }
                break;

            case self::MAPI_FLOAT:
            case self::MAPI_ERROR:
                $value = $this->_getx($data, 4);
                break;

            case self::MAPI_DOUBLE:
            case self::MAPI_APPTIME:
            case self::MAPI_CURRENCY:
            case self::MAPI_INT8BYTE:
            case self::MAPI_SYSTIME:
                $value = $this->_getx($data, 8);
                break;

            case self::MAPI_STRING:
            case self::MAPI_UNICODE_STRING:
            case self::MAPI_BINARY:
            case self::MAPI_OBJECT:
                $num_vals = $have_mval ? $num_mval : $this->_geti($data, 32);
                for ($i = 0; $i < $num_vals; $i++) {
                    $length = $this->_geti($data, 32);

                    /* Pad to next 4 byte boundary. */
                    $datalen = $length + ((4 - ($length % 4)) % 4);

                    if ($attr_type == self::MAPI_STRING) {
                        --$length;
                    }

                    /* Read and truncate to length. */
                    $value = substr($this->_getx($data, $datalen), 0, $length);
                }
                break;
            }

            /* Store any interesting attributes. */
            switch ($attr_name) {
            case self::MAPI_ATTACH_LONG_FILENAME:
                $value = str_replace("\0", '', $value);
                /* Used in preference to AFILENAME value. */
                $attachment_data[0]['name'] = preg_replace('/.*[\/](.*)$/', '\1', $value);
                break;

            case self::MAPI_ATTACH_MIME_TAG:
                $value = str_replace("\0", '', $value);
                /* Is this ever set, and what is format? */
                $attachment_data[0]['type']    = preg_replace('/^(.*)\/.*/', '\1', $value);
                $attachment_data[0]['subtype'] = preg_replace('/.*\/(.*)$/', '\1', $value);
                break;
            }
        }
    }

    /**
     * TODO
     *
     * @param string &$data  The data string.
     */
    protected function _decodeMessage(&$data)
    {
        $this->_decodeAttribute($data, $this->_geti($data, 32));
    }

    /**
     * TODO
     *
     * @param string &$data            The data string.
     * @param array &$attachment_data  TODO
     */
    protected function _decodeAttachment(&$data, &$attachment_data)
    {
        $attribute = $this->_geti($data, 32);

        switch ($attribute) {
        case self::ARENDDATA:
            /* Marks start of new attachment. */
            $this->_getx($data, $this->_geti($data, 32));

            /* Checksum */
            $this->_geti($data, 16);

            /* Add a new default data block to hold details of this
               attachment. Reverse order is easier to handle later! */
            array_unshift($attachment_data, array('type'    => 'application',
                                                  'subtype' => 'octet-stream',
                                                  'name'    => 'unknown',
                                                  'stream'  => ''));
            break;

        case self::AFILENAME:
            $value = $this->_getx($data, $this->_geti($data, 32));
            $value = str_replace("\0", '', $value);
            /* Strip path. */
            $attachment_data[0]['name'] = preg_replace('/.*[\/](.*)$/', '\1', $value);

            /* Checksum */
            $this->_geti($data, 16);
            break;

        case self::ATTACHDATA:
            /* The attachment itself. */
            $length = $this->_geti($data, 32);
            $attachment_data[0]['size'] = $length;
            $attachment_data[0]['stream'] = $this->_getx($data, $length);

            /* Checksum */
            $this->_geti($data, 16);
            break;

        case self::AMAPIATTRS:
            $length = $this->_geti($data, 32);
            $value = $this->_getx($data, $length);

            /* Checksum */
            $this->_geti($data, 16);
            $this->_extractMapiAttributes($value, $attachment_data);
            break;

        default:
            $this->_decodeAttribute($data, $attribute);
        }
    }
}
