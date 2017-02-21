<?php
/**
 * The Mail_mimePart class is used to create MIME E-mail messages
 *
 * This class enables you to manipulate and build a mime email
 * from the ground up. The Mail_Mime class is a userfriendly api
 * to this class for people who aren't interested in the internals
 * of mime mail.
 * This class however allows full control over the email.
 *
 * Compatible with PHP version 5
 *
 * LICENSE: This LICENSE is in the BSD license style.
 * Copyright (c) 2002-2003, Richard Heyes <richard@phpguru.org>
 * Copyright (c) 2003-2006, PEAR <pear-group@php.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met:
 *
 * - Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 * - Neither the name of the authors, nor the names of its contributors
 *   may be used to endorse or promote products derived from this
 *   software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF
 * THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Mail
 * @package   Mail_Mime
 * @author    Richard Heyes  <richard@phpguru.org>
 * @author    Cipriano Groenendal <cipri@php.net>
 * @author    Sean Coates <sean@php.net>
 * @author    Aleksander Machniak <alec@php.net>
 * @copyright 2003-2006 PEAR <pear-group@php.net>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Mail_mime
 */

/**
 * require PEAR
 *
 * This package depends on PEAR to raise errors.
 */
require_once 'PEAR.php';

/**
 * The Mail_mimePart class is used to create MIME E-mail messages
 *
 * This class enables you to manipulate and build a mime email
 * from the ground up. The Mail_Mime class is a userfriendly api
 * to this class for people who aren't interested in the internals
 * of mime mail.
 * This class however allows full control over the email.
 *
 * @category  Mail
 * @package   Mail_Mime
 * @author    Richard Heyes  <richard@phpguru.org>
 * @author    Cipriano Groenendal <cipri@php.net>
 * @author    Sean Coates <sean@php.net>
 * @author    Aleksander Machniak <alec@php.net>
 * @copyright 2003-2006 PEAR <pear-group@php.net>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Mail_mime
 */
class Mail_mimePart
{
    /**
     * The encoding type of this part
     *
     * @var string
     */
    protected $encoding;

    /**
     * An array of subparts
     *
     * @var array
     */
    protected $subparts;

    /**
     * The output of this part after being built
     *
     * @var string
     */
    protected $encoded;

    /**
     * Headers for this part
     *
     * @var array
     */
    protected $headers;

    /**
     * The body of this part (not encoded)
     *
     * @var string
     */
    protected $body;

    /**
     * The location of file with body of this part (not encoded)
     *
     * @var string
     */
    protected $body_file;

    /**
     * The short text of multipart part preamble (RFC2046 5.1.1)
     *
     * @var string
     */
    protected $preamble;

    /**
     * The end-of-line sequence
     *
     * @var string
     */
    protected $eol = "\r\n";


    /**
     * Constructor.
     *
     * Sets up the object.
     *
     * @param string $body   The body of the mime part if any.
     * @param array  $params An associative array of optional parameters:
     *     content_type      - The content type for this part eg multipart/mixed
     *     encoding          - The encoding to use, 7bit, 8bit,
     *                         base64, or quoted-printable
     *     charset           - Content character set
     *     cid               - Content ID to apply
     *     disposition       - Content disposition, inline or attachment
     *     filename          - Filename parameter for content disposition
     *     description       - Content description
     *     name_encoding     - Encoding of the attachment name (Content-Type)
     *                         By default filenames are encoded using RFC2231
     *                         Here you can set RFC2047 encoding (quoted-printable
     *                         or base64) instead
     *     filename_encoding - Encoding of the attachment filename (Content-Disposition)
     *                         See 'name_encoding'
     *     headers_charset   - Charset of the headers e.g. filename, description.
     *                         If not set, 'charset' will be used
     *     eol               - End of line sequence. Default: "\r\n"
     *     headers           - Hash array with additional part headers. Array keys can be
     *                         in form of <header_name>:<parameter_name>
     *     body_file         - Location of file with part's body (instead of $body)
     *     preamble          - short text of multipart part preamble (RFC2046 5.1.1)
     */
    public function __construct($body = '', $params = array())
    {
        if (!empty($params['eol'])) {
            $this->eol = $params['eol'];
        } else if (defined('MAIL_MIMEPART_CRLF')) { // backward-copat.
            $this->eol = MAIL_MIMEPART_CRLF;
        }

        // Additional part headers
        if (!empty($params['headers']) && is_array($params['headers'])) {
            $headers = $params['headers'];
        }

        foreach ($params as $key => $value) {
            switch ($key) {
            case 'encoding':
                $this->encoding = $value;
                $headers['Content-Transfer-Encoding'] = $value;
                break;

            case 'cid':
                $headers['Content-ID'] = '<' . $value . '>';
                break;

            case 'location':
                $headers['Content-Location'] = $value;
                break;

            case 'body_file':
                $this->body_file = $value;
                break;

            case 'preamble':
                $this->preamble = $value;
                break;

            // for backward compatibility
            case 'dfilename':
                $params['filename'] = $value;
                break;
            }
        }

        // Default content-type
        if (empty($params['content_type'])) {
            $params['content_type'] = 'text/plain';
        }

        // Content-Type
        $headers['Content-Type'] = $params['content_type'];
        if (!empty($params['charset'])) {
            $charset = "charset={$params['charset']}";
            // place charset parameter in the same line, if possible
            if ((strlen($headers['Content-Type']) + strlen($charset) + 16) <= 76) {
                $headers['Content-Type'] .= '; ';
            } else {
                $headers['Content-Type'] .= ';' . $this->eol . ' ';
            }
            $headers['Content-Type'] .= $charset;

            // Default headers charset
            if (!isset($params['headers_charset'])) {
                $params['headers_charset'] = $params['charset'];
            }
        }

        // header values encoding parameters
        $h_charset  = !empty($params['headers_charset']) ? $params['headers_charset'] : 'US-ASCII';
        $h_language = !empty($params['language']) ? $params['language'] : null;
        $h_encoding = !empty($params['name_encoding']) ? $params['name_encoding'] : null;

        if (!empty($params['filename'])) {
            $headers['Content-Type'] .= ';' . $this->eol;
            $headers['Content-Type'] .= $this->buildHeaderParam(
                'name', $params['filename'], $h_charset, $h_language, $h_encoding
            );
        }

        // Content-Disposition
        if (!empty($params['disposition'])) {
            $headers['Content-Disposition'] = $params['disposition'];
            if (!empty($params['filename'])) {
                $headers['Content-Disposition'] .= ';' . $this->eol;
                $headers['Content-Disposition'] .= $this->buildHeaderParam(
                    'filename', $params['filename'], $h_charset, $h_language,
                    !empty($params['filename_encoding']) ? $params['filename_encoding'] : null
                );
            }

            // add attachment size
            $size = $this->body_file ? filesize($this->body_file) : strlen($body);
            if ($size) {
                $headers['Content-Disposition'] .= ';' . $this->eol . ' size=' . $size;
            }
        }

        if (!empty($params['description'])) {
            $headers['Content-Description'] = $this->encodeHeader(
                'Content-Description', $params['description'], $h_charset, $h_encoding,
                $this->eol
            );
        }

        // Search and add existing headers' parameters
        foreach ($headers as $key => $value) {
            $items = explode(':', $key);
            if (count($items) == 2) {
                $header = $items[0];
                $param  = $items[1];
                if (isset($headers[$header])) {
                    $headers[$header] .= ';' . $this->eol;
                }
                $headers[$header] .= $this->buildHeaderParam(
                    $param, $value, $h_charset, $h_language, $h_encoding
                );
                unset($headers[$key]);
            }
        }

        // Default encoding
        if (!isset($this->encoding)) {
            $this->encoding = '7bit';
        }

        // Assign stuff to member variables
        $this->encoded  = array();
        $this->headers  = $headers;
        $this->body     = $body;
    }

    /**
     * Encodes and returns the email. Also stores
     * it in the encoded member variable
     *
     * @param string $boundary Pre-defined boundary string
     *
     * @return An associative array containing two elements,
     *         body and headers. The headers element is itself
     *         an indexed array. On error returns PEAR error object.
     */
    public function encode($boundary=null)
    {
        $encoded =& $this->encoded;

        if (count($this->subparts)) {
            $boundary = $boundary ? $boundary : '=_' . md5(rand() . microtime());
            $eol = $this->eol;

            $this->headers['Content-Type'] .= ";$eol boundary=\"$boundary\"";

            $encoded['body'] = '';

            if ($this->preamble) {
                $encoded['body'] .= $this->preamble . $eol . $eol;
            }

            for ($i = 0; $i < count($this->subparts); $i++) {
                $encoded['body'] .= '--' . $boundary . $eol;
                $tmp = $this->subparts[$i]->encode();
                if (is_a($tmp, 'PEAR_Error')) {
                    return $tmp;
                }
                foreach ($tmp['headers'] as $key => $value) {
                    $encoded['body'] .= $key . ': ' . $value . $eol;
                }
                $encoded['body'] .= $eol . $tmp['body'] . $eol;
            }

            $encoded['body'] .= '--' . $boundary . '--' . $eol;
        } else if ($this->body) {
            $encoded['body'] = $this->getEncodedData($this->body, $this->encoding);
        } else if ($this->body_file) {
            // Temporarily reset magic_quotes_runtime for file reads and writes
            if ($magic_quote_setting = get_magic_quotes_runtime()) {
                @ini_set('magic_quotes_runtime', 0);
            }
            $body = $this->getEncodedDataFromFile($this->body_file, $this->encoding);
            if ($magic_quote_setting) {
                @ini_set('magic_quotes_runtime', $magic_quote_setting);
            }

            if (is_a($body, 'PEAR_Error')) {
                return $body;
            }
            $encoded['body'] = $body;
        } else {
            $encoded['body'] = '';
        }

        // Add headers to $encoded
        $encoded['headers'] =& $this->headers;

        return $encoded;
    }

    /**
     * Encodes and saves the email into file or stream.
     * Data will be appended to the file/stream.
     *
     * @param mixed   $filename  Existing file location
     *                           or file pointer resource
     * @param string  $boundary  Pre-defined boundary string
     * @param boolean $skip_head True if you don't want to save headers
     *
     * @return array An associative array containing message headers
     *               or PEAR error object
     * @since 1.6.0
     */
    public function encodeToFile($filename, $boundary = null, $skip_head = false)
    {
        if (!is_resource($filename)) {
            if (file_exists($filename) && !is_writable($filename)) {
                $err = self::raiseError('File is not writeable: ' . $filename);
                return $err;
            }

            if (!($fh = fopen($filename, 'ab'))) {
                $err = self::raiseError('Unable to open file: ' . $filename);
                return $err;
            }
        } else {
            $fh = $filename;
        }

        // Temporarily reset magic_quotes_runtime for file reads and writes
        if ($magic_quote_setting = get_magic_quotes_runtime()) {
            @ini_set('magic_quotes_runtime', 0);
        }

        $res = $this->encodePartToFile($fh, $boundary, $skip_head);

        if (!is_resource($filename)) {
            fclose($fh);
        }

        if ($magic_quote_setting) {
            @ini_set('magic_quotes_runtime', $magic_quote_setting);
        }

        return is_a($res, 'PEAR_Error') ? $res : $this->headers;
    }

    /**
     * Encodes given email part into file
     *
     * @param string  $fh        Output file handle
     * @param string  $boundary  Pre-defined boundary string
     * @param boolean $skip_head True if you don't want to save headers
     *
     * @return array True on sucess or PEAR error object
     */
    protected function encodePartToFile($fh, $boundary = null, $skip_head = false)
    {
        $eol = $this->eol;

        if (count($this->subparts)) {
            $boundary = $boundary ? $boundary : '=_' . md5(rand() . microtime());
            $this->headers['Content-Type'] .= ";$eol boundary=\"$boundary\"";
        }

        if (!$skip_head) {
            foreach ($this->headers as $key => $value) {
                fwrite($fh, $key . ': ' . $value . $eol);
            }
            $f_eol = $eol;
        } else {
            $f_eol = '';
        }

        if (count($this->subparts)) {
            if ($this->preamble) {
                fwrite($fh, $f_eol . $this->preamble . $eol);
                $f_eol = $eol;
            }

            for ($i = 0; $i < count($this->subparts); $i++) {
                fwrite($fh, $f_eol . '--' . $boundary . $eol);
                $res = $this->subparts[$i]->encodePartToFile($fh);
                if (is_a($res, 'PEAR_Error')) {
                    return $res;
                }
                $f_eol = $eol;
            }

            fwrite($fh, $eol . '--' . $boundary . '--' . $eol);
        } else if ($this->body) {
            fwrite($fh, $f_eol);
            fwrite($fh, $this->getEncodedData($this->body, $this->encoding));
        } else if ($this->body_file) {
            fwrite($fh, $f_eol);
            $res = $this->getEncodedDataFromFile(
                $this->body_file, $this->encoding, $fh
            );
            if (is_a($res, 'PEAR_Error')) {
                return $res;
            }
        }

        return true;
    }

    /**
     * Adds a subpart to current mime part and returns
     * a reference to it
     *
     * @param mixed $body   The body of the subpart or Mail_mimePart object
     * @param array $params The parameters for the subpart, same
     *                      as the $params argument for constructor
     *
     * @return Mail_mimePart A reference to the part you just added.
     */
    public function addSubpart($body, $params = null)
    {
        if ($body instanceof Mail_mimePart) {
            $part = $body;
        } else {
            $part = new Mail_mimePart($body, $params);
        }

        $this->subparts[] = $part;

        return $part;
    }

    /**
     * Returns encoded data based upon encoding passed to it
     *
     * @param string $data     The data to encode.
     * @param string $encoding The encoding type to use, 7bit, base64,
     *                         or quoted-printable.
     *
     * @return string Encoded data string
     */
    protected function getEncodedData($data, $encoding)
    {
        switch ($encoding) {
        case 'quoted-printable':
            return self::quotedPrintableEncode($data, 76, $this->eol);
            break;

        case 'base64':
            return rtrim(chunk_split(base64_encode($data), 76, $this->eol));
            break;

        case '8bit':
        case '7bit':
        default:
            return $data;
        }
    }

    /**
     * Returns encoded data based upon encoding passed to it
     *
     * @param string   $filename Data file location
     * @param string   $encoding The encoding type to use, 7bit, base64,
     *                           or quoted-printable.
     * @param resource $fh       Output file handle. If set, data will be
     *                           stored into it instead of returning it
     *
     * @return string Encoded data or PEAR error object
     */
    protected function getEncodedDataFromFile($filename, $encoding, $fh = null)
    {
        if (!is_readable($filename)) {
            $err = self::raiseError('Unable to read file: ' . $filename);
            return $err;
        }

        if (!($fd = fopen($filename, 'rb'))) {
            $err = self::raiseError('Could not open file: ' . $filename);
            return $err;
        }

        $data = '';

        switch ($encoding) {
        case 'quoted-printable':
            while (!feof($fd)) {
                $buffer = self::quotedPrintableEncode(fgets($fd), 76, $this->eol);
                if ($fh) {
                    fwrite($fh, $buffer);
                } else {
                    $data .= $buffer;
                }
            }
            break;

        case 'base64':
            while (!feof($fd)) {
                // Should read in a multiple of 57 bytes so that
                // the output is 76 bytes per line. Don't use big chunks
                // because base64 encoding is memory expensive
                $buffer = fread($fd, 57 * 9198); // ca. 0.5 MB
                $buffer = base64_encode($buffer);
                $buffer = chunk_split($buffer, 76, $this->eol);
                if (feof($fd)) {
                    $buffer = rtrim($buffer);
                }

                if ($fh) {
                    fwrite($fh, $buffer);
                } else {
                    $data .= $buffer;
                }
            }
            break;

        case '8bit':
        case '7bit':
        default:
            while (!feof($fd)) {
                $buffer = fread($fd, 1048576); // 1 MB
                if ($fh) {
                    fwrite($fh, $buffer);
                } else {
                    $data .= $buffer;
                }
            }
        }

        fclose($fd);

        if (!$fh) {
            return $data;
        }
    }

    /**
     * Encodes data to quoted-printable standard.
     *
     * @param string $input    The data to encode
     * @param int    $line_max Optional max line length. Should
     *                         not be more than 76 chars
     * @param string $eol      End-of-line sequence. Default: "\r\n"
     *
     * @return string Encoded data
     */
    public static function quotedPrintableEncode($input , $line_max = 76, $eol = "\r\n")
    {
        /*
        // imap_8bit() is extremely fast, but doesn't handle properly some characters
        if (function_exists('imap_8bit') && $line_max == 76) {
            $input = preg_replace('/\r?\n/', "\r\n", $input);
            $input = imap_8bit($input);
            if ($eol != "\r\n") {
                $input = str_replace("\r\n", $eol, $input);
            }
            return $input;
        }
        */
        $lines  = preg_split("/\r?\n/", $input);
        $escape = '=';
        $output = '';

        while (list($idx, $line) = each($lines)) {
            $newline = '';
            $i = 0;

            while (isset($line[$i])) {
                $char = $line[$i];
                $dec  = ord($char);
                $i++;

                if (($dec == 32) && (!isset($line[$i]))) {
                    // convert space at eol only
                    $char = '=20';
                } elseif ($dec == 9 && isset($line[$i])) {
                    ; // Do nothing if a TAB is not on eol
                } elseif (($dec == 61) || ($dec < 32) || ($dec > 126)) {
                    $char = $escape . sprintf('%02X', $dec);
                } elseif (($dec == 46) && (($newline == '')
                    || ((strlen($newline) + strlen("=2E")) >= $line_max))
                ) {
                    // Bug #9722: convert full-stop at bol,
                    // some Windows servers need this, won't break anything (cipri)
                    // Bug #11731: full-stop at bol also needs to be encoded
                    // if this line would push us over the line_max limit.
                    $char = '=2E';
                }

                // Note, when changing this line, also change the ($dec == 46)
                // check line, as it mimics this line due to Bug #11731
                // EOL is not counted
                if ((strlen($newline) + strlen($char)) >= $line_max) {
                    // soft line break; " =\r\n" is okay
                    $output  .= $newline . $escape . $eol;
                    $newline  = '';
                }
                $newline .= $char;
            } // end of for
            $output .= $newline . $eol;
            unset($lines[$idx]);
        }
        // Don't want last crlf
        $output = substr($output, 0, -1 * strlen($eol));
        return $output;
    }

    /**
     * Encodes the parameter of a header.
     *
     * @param string $name      The name of the header-parameter
     * @param string $value     The value of the paramter
     * @param string $charset   The characterset of $value
     * @param string $language  The language used in $value
     * @param string $encoding  Parameter encoding. If not set, parameter value
     *                          is encoded according to RFC2231
     * @param int    $maxLength The maximum length of a line. Defauls to 75
     *
     * @return string
     */
    protected function buildHeaderParam($name, $value, $charset = null,
        $language = null, $encoding = null, $maxLength = 75
    ) {
        // RFC 2045:
        // value needs encoding if contains non-ASCII chars or is longer than 78 chars
        if (!preg_match('#[^\x20-\x7E]#', $value)) {
            $token_regexp = '#([^\x21\x23-\x27\x2A\x2B\x2D'
                . '\x2E\x30-\x39\x41-\x5A\x5E-\x7E])#';
            if (!preg_match($token_regexp, $value)) {
                // token
                if (strlen($name) + strlen($value) + 3 <= $maxLength) {
                    return " {$name}={$value}";
                }
            } else {
                // quoted-string
                $quoted = addcslashes($value, '\\"');
                if (strlen($name) + strlen($quoted) + 5 <= $maxLength) {
                    return " {$name}=\"{$quoted}\"";
                }
            }
        }

        // RFC2047: use quoted-printable/base64 encoding
        if ($encoding == 'quoted-printable' || $encoding == 'base64') {
            return $this->buildRFC2047Param($name, $value, $charset, $encoding);
        }

        // RFC2231:
        $encValue = preg_replace_callback(
            '/([^\x21\x23\x24\x26\x2B\x2D\x2E\x30-\x39\x41-\x5A\x5E-\x7E])/',
            array($this, 'encodeReplaceCallback'), $value
        );
        $value = "$charset'$language'$encValue";

        $header = " {$name}*={$value}";
        if (strlen($header) <= $maxLength) {
            return $header;
        }

        $preLength = strlen(" {$name}*0*=");
        $maxLength = max(16, $maxLength - $preLength - 3);
        $maxLengthReg = "|(.{0,$maxLength}[^\%][^\%])|";

        $headers = array();
        $headCount = 0;
        while ($value) {
            $matches = array();
            $found = preg_match($maxLengthReg, $value, $matches);
            if ($found) {
                $headers[] = " {$name}*{$headCount}*={$matches[0]}";
                $value = substr($value, strlen($matches[0]));
            } else {
                $headers[] = " {$name}*{$headCount}*={$value}";
                $value = '';
            }
            $headCount++;
        }

        $headers = implode(';' . $this->eol, $headers);
        return $headers;
    }

    /**
     * Encodes header parameter as per RFC2047 if needed
     *
     * @param string $name      The parameter name
     * @param string $value     The parameter value
     * @param string $charset   The parameter charset
     * @param string $encoding  Encoding type (quoted-printable or base64)
     * @param int    $maxLength Encoded parameter max length. Default: 76
     *
     * @return string Parameter line
     */
    protected function buildRFC2047Param($name, $value, $charset,
        $encoding = 'quoted-printable', $maxLength = 76
    ) {
        // WARNING: RFC 2047 says: "An 'encoded-word' MUST NOT be used in
        // parameter of a MIME Content-Type or Content-Disposition field",
        // but... it's supported by many clients/servers
        $quoted = '';

        if ($encoding == 'base64') {
            $value = base64_encode($value);
            $prefix = '=?' . $charset . '?B?';
            $suffix = '?=';

            // 2 x SPACE, 2 x '"', '=', ';'
            $add_len = strlen($prefix . $suffix) + strlen($name) + 6;
            $len = $add_len + strlen($value);

            while ($len > $maxLength) { 
                // We can cut base64-encoded string every 4 characters
                $real_len = floor(($maxLength - $add_len) / 4) * 4;
                $_quote = substr($value, 0, $real_len);
                $value = substr($value, $real_len);

                $quoted .= $prefix . $_quote . $suffix . $this->eol . ' ';
                $add_len = strlen($prefix . $suffix) + 4; // 2 x SPACE, '"', ';'
                $len = strlen($value) + $add_len;
            }
            $quoted .= $prefix . $value . $suffix;

        } else {
            // quoted-printable
            $value = $this->encodeQP($value);
            $prefix = '=?' . $charset . '?Q?';
            $suffix = '?=';

            // 2 x SPACE, 2 x '"', '=', ';'
            $add_len = strlen($prefix . $suffix) + strlen($name) + 6;
            $len = $add_len + strlen($value);

            while ($len > $maxLength) {
                $length = $maxLength - $add_len;
                // don't break any encoded letters
                if (preg_match("/^(.{0,$length}[^\=][^\=])/", $value, $matches)) {
                    $_quote = $matches[1];
                }

                $quoted .= $prefix . $_quote . $suffix . $this->eol . ' ';
                $value = substr($value, strlen($_quote));
                $add_len = strlen($prefix . $suffix) + 4; // 2 x SPACE, '"', ';'
                $len = strlen($value) + $add_len;
            }

            $quoted .= $prefix . $value . $suffix;
        }

        return " {$name}=\"{$quoted}\"";
    }

    /**
     * Encodes a header as per RFC2047
     *
     * @param string $name     The header name
     * @param string $value    The header data to encode
     * @param string $charset  Character set name
     * @param string $encoding Encoding name (base64 or quoted-printable)
     * @param string $eol      End-of-line sequence. Default: "\r\n"
     *
     * @return string Encoded header data (without a name)
     * @since 1.6.1
     */
    public static function encodeHeader($name, $value, $charset = 'ISO-8859-1',
        $encoding = 'quoted-printable', $eol = "\r\n"
    ) {
        // Structured headers
        $comma_headers = array(
            'from', 'to', 'cc', 'bcc', 'sender', 'reply-to',
            'resent-from', 'resent-to', 'resent-cc', 'resent-bcc',
            'resent-sender', 'resent-reply-to',
            'mail-reply-to', 'mail-followup-to',
            'return-receipt-to', 'disposition-notification-to',
        );
        $other_headers = array(
            'references', 'in-reply-to', 'message-id', 'resent-message-id',
        );

        $name = strtolower($name);

        if (in_array($name, $comma_headers)) {
            $separator = ',';
        } else if (in_array($name, $other_headers)) {
            $separator = ' ';
        }

        if (!$charset) {
            $charset = 'ISO-8859-1';
        }

        // Structured header (make sure addr-spec inside is not encoded)
        if (!empty($separator)) {
            // Simple e-mail address regexp
            $email_regexp = '([^\s<]+|("[^\r\n"]+"))@\S+';

            $parts = Mail_mimePart::explodeQuotedString("[\t$separator]", $value);
            $value = '';

            foreach ($parts as $part) {
                $part = preg_replace('/\r?\n[\s\t]*/', $eol . ' ', $part);
                $part = trim($part);

                if (!$part) {
                    continue;
                }
                if ($value) {
                    $value .= $separator == ',' ? $separator . ' ' : ' ';
                } else {
                    $value = $name . ': ';
                }

                // let's find phrase (name) and/or addr-spec
                if (preg_match('/^<' . $email_regexp . '>$/', $part)) {
                    $value .= $part;
                } else if (preg_match('/^' . $email_regexp . '$/', $part)) {
                    // address without brackets and without name
                    $value .= $part;
                } else if (preg_match('/<*' . $email_regexp . '>*$/', $part, $matches)) {
                    // address with name (handle name)
                    $address = $matches[0];
                    $word = str_replace($address, '', $part);
                    $word = trim($word);
                    // check if phrase requires quoting
                    if ($word) {
                        // non-ASCII: require encoding
                        if (preg_match('#([^\s\x21-\x7E]){1}#', $word)) {
                            if ($word[0] == '"' && $word[strlen($word)-1] == '"') {
                                // de-quote quoted-string, encoding changes
                                // string to atom
                                $search = array("\\\"", "\\\\");
                                $replace = array("\"", "\\");
                                $word = str_replace($search, $replace, $word);
                                $word = substr($word, 1, -1);
                            }
                            // find length of last line
                            if (($pos = strrpos($value, $eol)) !== false) {
                                $last_len = strlen($value) - $pos;
                            } else {
                                $last_len = strlen($value);
                            }
                            $word = Mail_mimePart::encodeHeaderValue(
                                $word, $charset, $encoding, $last_len, $eol
                            );
                        } else if (($word[0] != '"' || $word[strlen($word)-1] != '"')
                            && preg_match('/[\(\)\<\>\\\.\[\]@,;:"]/', $word)
                        ) {
                            // ASCII: quote string if needed
                            $word = '"'.addcslashes($word, '\\"').'"';
                        }
                    }
                    $value .= $word.' '.$address;
                } else {
                    // addr-spec not found, don't encode (?)
                    $value .= $part;
                }

                // RFC2822 recommends 78 characters limit, use 76 from RFC2047
                $value = wordwrap($value, 76, $eol . ' ');
            }

            // remove header name prefix (there could be EOL too)
            $value = preg_replace(
                '/^'.$name.':('.preg_quote($eol, '/').')* /', '', $value
            );
        } else {
            // Unstructured header
            // non-ASCII: require encoding
            if (preg_match('#([^\s\x21-\x7E]){1}#', $value)) {
                if ($value[0] == '"' && $value[strlen($value)-1] == '"') {
                    // de-quote quoted-string, encoding changes
                    // string to atom
                    $search = array("\\\"", "\\\\");
                    $replace = array("\"", "\\");
                    $value = str_replace($search, $replace, $value);
                    $value = substr($value, 1, -1);
                }
                $value = Mail_mimePart::encodeHeaderValue(
                    $value, $charset, $encoding, strlen($name) + 2, $eol
                );
            } else if (strlen($name.': '.$value) > 78) {
                // ASCII: check if header line isn't too long and use folding
                $value = preg_replace('/\r?\n[\s\t]*/', $eol . ' ', $value);
                $tmp = wordwrap($name.': '.$value, 78, $eol . ' ');
                $value = preg_replace('/^'.$name.':\s*/', '', $tmp);
                // hard limit 998 (RFC2822)
                $value = wordwrap($value, 998, $eol . ' ', true);
            }
        }

        return $value;
    }

    /**
     * Explode quoted string
     *
     * @param string $delimiter Delimiter expression string for preg_match()
     * @param string $string    Input string
     *
     * @return array String tokens array
     */
    protected static function explodeQuotedString($delimiter, $string)
    {
        $result = array();
        $strlen = strlen($string);

        for ($q=$p=$i=0; $i < $strlen; $i++) {
            if ($string[$i] == "\""
                && (empty($string[$i-1]) || $string[$i-1] != "\\")
            ) {
                $q = $q ? false : true;
            } else if (!$q && preg_match("/$delimiter/", $string[$i])) {
                $result[] = substr($string, $p, $i - $p);
                $p = $i + 1;
            }
        }

        $result[] = substr($string, $p);
        return $result;
    }

    /**
     * Encodes a header value as per RFC2047
     *
     * @param string $value      The header data to encode
     * @param string $charset    Character set name
     * @param string $encoding   Encoding name (base64 or quoted-printable)
     * @param int    $prefix_len Prefix length. Default: 0
     * @param string $eol        End-of-line sequence. Default: "\r\n"
     *
     * @return string Encoded header data
     * @since 1.6.1
     */
    public static function encodeHeaderValue($value, $charset, $encoding, $prefix_len = 0, $eol = "\r\n")
    {
        // #17311: Use multibyte aware method (requires mbstring extension)
        if ($result = Mail_mimePart::encodeMB($value, $charset, $encoding, $prefix_len, $eol)) {
            return $result;
        }

        // Generate the header using the specified params and dynamicly
        // determine the maximum length of such strings.
        // 75 is the value specified in the RFC.
        $encoding = $encoding == 'base64' ? 'B' : 'Q';
        $prefix = '=?' . $charset . '?' . $encoding .'?';
        $suffix = '?=';
        $maxLength = 75 - strlen($prefix . $suffix);
        $maxLength1stLine = $maxLength - $prefix_len;

        if ($encoding == 'B') {
            // Base64 encode the entire string
            $value = base64_encode($value);

            // We can cut base64 every 4 characters, so the real max
            // we can get must be rounded down.
            $maxLength = $maxLength - ($maxLength % 4);
            $maxLength1stLine = $maxLength1stLine - ($maxLength1stLine % 4);

            $cutpoint = $maxLength1stLine;
            $output = '';

            while ($value) {
                // Split translated string at every $maxLength
                $part = substr($value, 0, $cutpoint);
                $value = substr($value, $cutpoint);
                $cutpoint = $maxLength;
                // RFC 2047 specifies that any split header should
                // be separated by a CRLF SPACE.
                if ($output) {
                    $output .= $eol . ' ';
                }
                $output .= $prefix . $part . $suffix;
            }
            $value = $output;
        } else {
            // quoted-printable encoding has been selected
            $value = Mail_mimePart::encodeQP($value);

            // This regexp will break QP-encoded text at every $maxLength
            // but will not break any encoded letters.
            $reg1st = "|(.{0,$maxLength1stLine}[^\=][^\=])|";
            $reg2nd = "|(.{0,$maxLength}[^\=][^\=])|";

            if (strlen($value) > $maxLength1stLine) {
                // Begin with the regexp for the first line.
                $reg = $reg1st;
                $output = '';
                while ($value) {
                    // Split translated string at every $maxLength
                    // But make sure not to break any translated chars.
                    $found = preg_match($reg, $value, $matches);

                    // After this first line, we need to use a different
                    // regexp for the first line.
                    $reg = $reg2nd;

                    // Save the found part and encapsulate it in the
                    // prefix & suffix. Then remove the part from the
                    // $value_out variable.
                    if ($found) {
                        $part = $matches[0];
                        $len = strlen($matches[0]);
                        $value = substr($value, $len);
                    } else {
                        $part = $value;
                        $value = '';
                    }

                    // RFC 2047 specifies that any split header should
                    // be separated by a CRLF SPACE
                    if ($output) {
                        $output .= $eol . ' ';
                    }
                    $output .= $prefix . $part . $suffix;
                }
                $value = $output;
            } else {
                $value = $prefix . $value . $suffix;
            }
        }

        return $value;
    }

    /**
     * Encodes the given string using quoted-printable
     *
     * @param string $str String to encode
     *
     * @return string Encoded string
     * @since 1.6.0
     */
    public static function encodeQP($str)
    {
        // Bug #17226 RFC 2047 restricts some characters
        // if the word is inside a phrase, permitted chars are only:
        // ASCII letters, decimal digits, "!", "*", "+", "-", "/", "=", and "_"

        // "=",  "_",  "?" must be encoded
        $regexp = '/([\x22-\x29\x2C\x2E\x3A-\x40\x5B-\x60\x7B-\x7E\x80-\xFF])/';
        $str = preg_replace_callback(
            $regexp, array('Mail_mimePart', 'qpReplaceCallback'), $str
        );

        return str_replace(' ', '_', $str);
    }

    /**
     * Encodes the given string using base64 or quoted-printable.
     * This method makes sure that encoded-word represents an integral
     * number of characters as per RFC2047.
     *
     * @param string $str        String to encode
     * @param string $charset    Character set name
     * @param string $encoding   Encoding name (base64 or quoted-printable)
     * @param int    $prefix_len Prefix length. Default: 0
     * @param string $eol        End-of-line sequence. Default: "\r\n"
     *
     * @return string Encoded string
     * @since 1.8.0
     */
    public static function encodeMB($str, $charset, $encoding, $prefix_len=0, $eol="\r\n")
    {
        if (!function_exists('mb_substr') || !function_exists('mb_strlen')) {
            return;
        }

        $encoding = $encoding == 'base64' ? 'B' : 'Q';
        // 75 is the value specified in the RFC
        $prefix = '=?' . $charset . '?'.$encoding.'?';
        $suffix = '?=';
        $maxLength = 75 - strlen($prefix . $suffix);

        // A multi-octet character may not be split across adjacent encoded-words
        // So, we'll loop over each character
        // mb_stlen() with wrong charset will generate a warning here and return null
        $length      = mb_strlen($str, $charset);
        $result      = '';
        $line_length = $prefix_len;

        if ($encoding == 'B') {
            // base64
            $start = 0;
            $prev  = '';

            for ($i=1; $i<=$length; $i++) {
                // See #17311
                $chunk = mb_substr($str, $start, $i-$start, $charset);
                $chunk = base64_encode($chunk);
                $chunk_len = strlen($chunk);

                if ($line_length + $chunk_len == $maxLength || $i == $length) {
                    if ($result) {
                        $result .= "\n";
                    }
                    $result .= $chunk;
                    $line_length = 0;
                    $start = $i;
                } else if ($line_length + $chunk_len > $maxLength) {
                    if ($result) {
                        $result .= "\n";
                    }
                    if ($prev) {
                        $result .= $prev;
                    }
                    $line_length = 0;
                    $start = $i - 1;
                } else {
                    $prev = $chunk;
                }
            }
        } else {
            // quoted-printable
            // see encodeQP()
            $regexp = '/([\x22-\x29\x2C\x2E\x3A-\x40\x5B-\x60\x7B-\x7E\x80-\xFF])/';

            for ($i=0; $i<=$length; $i++) {
                $char = mb_substr($str, $i, 1, $charset);
                // RFC recommends underline (instead of =20) in place of the space
                // that's one of the reasons why we're not using iconv_mime_encode()
                if ($char == ' ') {
                    $char = '_';
                    $char_len = 1;
                } else {
                    $char = preg_replace_callback(
                        $regexp, array('Mail_mimePart', 'qpReplaceCallback'), $char
                    );
                    $char_len = strlen($char);
                }

                if ($line_length + $char_len > $maxLength) {
                    if ($result) {
                        $result .= "\n";
                    }
                    $line_length = 0;
                }

                $result      .= $char;
                $line_length += $char_len;
            }
        }

        if ($result) {
            $result = $prefix
                .str_replace("\n", $suffix.$eol.' '.$prefix, $result).$suffix;
        }

        return $result;
    }

    /**
     * Callback function to replace extended characters (\x80-xFF) with their
     * ASCII values (RFC2047: quoted-printable)
     *
     * @param array $matches Preg_replace's matches array
     *
     * @return string Encoded character string
     */
    protected static function qpReplaceCallback($matches)
    {
        return sprintf('=%02X', ord($matches[1]));
    }

    /**
     * Callback function to replace extended characters (\x80-xFF) with their
     * ASCII values (RFC2231)
     *
     * @param array $matches Preg_replace's matches array
     *
     * @return string Encoded character string
     */
    protected static function encodeReplaceCallback($matches)
    {
        return sprintf('%%%02X', ord($matches[1]));
    }

    /**
     * PEAR::raiseError implementation
     *
     * @param string $message A text error message
     *
     * @return PEAR_Error Instance of PEAR_Error
     */
    public static function raiseError($message)
    {
        // PEAR::raiseError() is not PHP 5.4 compatible
        return new PEAR_Error($message);
    }
}
