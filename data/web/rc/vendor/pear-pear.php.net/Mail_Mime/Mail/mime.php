<?php

/**
 * The Mail_Mime class is used to create MIME E-mail messages
 *
 * The Mail_Mime class provides an OO interface to create MIME
 * enabled email messages. This way you can create emails that
 * contain plain-text bodies, HTML bodies, attachments, inline
 * images and specific headers.
 *
 * Compatible with PHP >= 5
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
 * @author    Tomas V.V. Cox <cox@idecnet.com>
 * @author    Cipriano Groenendal <cipri@php.net>
 * @author    Sean Coates <sean@php.net>
 * @author    Aleksander Machniak <alec@php.net>
 * @copyright 2003-2006 PEAR <pear-group@php.net>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Mail_mime
 *
 *            This class is based on HTML Mime Mail class from
 *            Richard Heyes <richard@phpguru.org> which was based also
 *            in the mime_mail.class by Tobias Ratschiller <tobias@dnet.it>
 *            and Sascha Schumann <sascha@schumann.cx>
 */


require_once 'PEAR.php';
require_once 'Mail/mimePart.php';


/**
 * The Mail_Mime class provides an OO interface to create MIME
 * enabled email messages. This way you can create emails that
 * contain plain-text bodies, HTML bodies, attachments, inline
 * images and specific headers.
 *
 * @category  Mail
 * @package   Mail_Mime
 * @author    Richard Heyes  <richard@phpguru.org>
 * @author    Tomas V.V. Cox <cox@idecnet.com>
 * @author    Cipriano Groenendal <cipri@php.net>
 * @author    Sean Coates <sean@php.net>
 * @copyright 2003-2006 PEAR <pear-group@php.net>
 * @license   http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Mail_mime
 */
class Mail_mime
{
    /**
     * Contains the plain text part of the email
     *
     * @var string
     */
    protected $txtbody;

    /**
     * Contains the html part of the email
     *
     * @var string
     */
    protected $htmlbody;

    /**
     * Contains the text/calendar part of the email
     *
     * @var string
     */
    protected $calbody;

    /**
     * list of the attached images
     *
     * @var array
     */
    protected $html_images = array();

    /**
     * list of the attachements
     *
     * @var array
     */
    protected $parts = array();

    /**
     * Headers for the mail
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Build parameters
     *
     * @var array
     */
    protected $build_params = array(
        // What encoding to use for the headers
        // Options: quoted-printable or base64
        'head_encoding' => 'quoted-printable',
        // What encoding to use for plain text
        // Options: 7bit, 8bit, base64, or quoted-printable
        'text_encoding' => 'quoted-printable',
        // What encoding to use for html
        // Options: 7bit, 8bit, base64, or quoted-printable
        'html_encoding' => 'quoted-printable',
        // What encoding to use for calendar part
        // Options: 7bit, 8bit, base64, or quoted-printable
        'calendar_encoding' => 'quoted-printable',
        // The character set to use for html
        'html_charset'  => 'ISO-8859-1',
        // The character set to use for text
        'text_charset'  => 'ISO-8859-1',
        // The character set to use for calendar part
        'calendar_charset'  => 'UTF-8',
        // The character set to use for headers
        'head_charset'  => 'ISO-8859-1',
        // End-of-line sequence
        'eol'           => "\r\n",
        // Delay attachment files IO until building the message
        'delay_file_io' => false,
        // Default calendar method
        'calendar_method' => 'request',
        // multipart part preamble (RFC2046 5.1.1)
        'preamble' => '',
    );


    /**
     * Constructor function
     *
     * @param mixed $params Build parameters that change the way the email
     *                      is built. Should be an associative array.
     *                      See $_build_params.
     *
     * @return void
     */
    public function __construct($params = array())
    {
        // Backward-compatible EOL setting
        if (is_string($params)) {
            $this->build_params['eol'] = $params;
        } else if (defined('MAIL_MIME_CRLF') && !isset($params['eol'])) {
            $this->build_params['eol'] = MAIL_MIME_CRLF;
        }

        // Update build parameters
        if (!empty($params) && is_array($params)) {
            while (list($key, $value) = each($params)) {
                $this->build_params[$key] = $value;
            }
        }
    }

    /**
     * Set build parameter value
     *
     * @param string $name  Parameter name
     * @param string $value Parameter value
     *
     * @return void
     * @since 1.6.0
     */
    public function setParam($name, $value)
    {
        $this->build_params[$name] = $value;
    }

    /**
     * Get build parameter value
     *
     * @param string $name Parameter name
     *
     * @return mixed Parameter value
     * @since 1.6.0
     */
    public function getParam($name)
    {
        return isset($this->build_params[$name]) ? $this->build_params[$name] : null;
    }

    /**
     * Accessor function to set the body text. Body text is used if
     * it's not an html mail being sent or else is used to fill the
     * text/plain part that emails clients who don't support
     * html should show.
     *
     * @param string $data   Either a string or the file name with the contents
     * @param bool   $isfile If true the first param should be treated
     *                       as a file name, else as a string (default)
     * @param bool   $append If true the text or file is appended to
     *                       the existing body, else the old body is
     *                       overwritten
     *
     * @return mixed True on success or PEAR_Error object
     */
    public function setTXTBody($data, $isfile = false, $append = false)
    {
        return $this->setBody('txtbody', $data, $isfile, $append);
    }

    /**
     * Get message text body
     *
     * @return string Text body
     * @since 1.6.0
     */
    public function getTXTBody()
    {
        return $this->txtbody;
    }

    /**
     * Adds a html part to the mail.
     *
     * @param string $data   Either a string or the file name with the contents
     * @param bool   $isfile A flag that determines whether $data is a
     *                       filename, or a string(false, default)
     *
     * @return bool True on success or PEAR_Error object
     */
    public function setHTMLBody($data, $isfile = false)
    {
        return $this->setBody('htmlbody', $data, $isfile);
    }

    /**
     * Get message HTML body
     *
     * @return string HTML body
     * @since 1.6.0
     */
    public function getHTMLBody()
    {
        return $this->htmlbody;
    }

    /**
     * Function to set a body of text/calendar part (not attachment)
     *
     * @param string $data     Either a string or the file name with the contents
     * @param bool   $isfile   If true the first param should be treated
     *                         as a file name, else as a string (default)
     * @param bool   $append   If true the text or file is appended to
     *                         the existing body, else the old body is
     *                         overwritten
     * @param string $method   iCalendar object method
     * @param string $charset  iCalendar character set
     * @param string $encoding Transfer encoding
     *
     * @return mixed True on success or PEAR_Error object
     * @since 1.9.0
     */
    public function setCalendarBody($data, $isfile = false, $append = false,
        $method = 'request', $charset = 'UTF-8', $encoding = 'quoted-printable'
    ) {
        $result = $this->setBody('calbody', $data, $isfile, $append);

        if ($result === true) {
            $this->build_params['calendar_method']  = $method;
            $this->build_params['calendar_charset'] = $charset;
            $this->build_params['calendar_encoding'] = $encoding;
        }
    }

    /**
     * Get body of calendar part
     *
     * @return string Calendar part body
     * @since 1.9.0
     */
    public function getCalendarBody()
    {
        return $this->calbody;
    }

    /**
     * Adds an image to the list of embedded images.
     * Images added this way will be added as related parts of the HTML message.
     *
     * To correctly match the HTML image with the related attachment
     * HTML should refer to it by a filename (specified in $file or $name
     * arguments) or by cid:<content-id> (specified in $content_id arg).
     *
     * @param string $file       The image file name OR image data itself
     * @param string $c_type     The content type
     * @param string $name       The filename of the image. Used to find
     *                           the image in HTML content.
     * @param bool   $isfile     Whether $file is a filename or not.
     *                           Defaults to true
     * @param string $content_id Desired Content-ID of MIME part
     *                           Defaults to generated unique ID
     *
     * @return bool True on success
     */
    public function addHTMLImage($file,
        $c_type = 'application/octet-stream',
        $name = '',
        $isfile = true,
        $content_id = null
    ) {
        $bodyfile = null;

        if ($isfile) {
            // Don't load file into memory
            if ($this->build_params['delay_file_io']) {
                $filedata = null;
                $bodyfile = $file;
            } else {
                if (self::isError($filedata = $this->file2str($file))) {
                    return $filedata;
                }
            }

            $filename = $name ? $name : $file;
        } else {
            $filedata = $file;
            $filename = $name;
        }

        if (!$content_id) {
            $content_id = preg_replace('/[^0-9a-zA-Z]/', '', uniqid(time(), true));
        }

        $this->html_images[] = array(
            'body'      => $filedata,
            'body_file' => $bodyfile,
            'name'      => $filename,
            'c_type'    => $c_type,
            'cid'       => $content_id
        );

        return true;
    }

    /**
     * Adds a file to the list of attachments.
     *
     * @param mixed  $file        The file name or the file contents itself,
     *                            it can be also Mail_mimePart object
     * @param string $c_type      The content type
     * @param string $name        The filename of the attachment
     *                            Only use if $file is the contents
     * @param bool   $isfile      Whether $file is a filename or not. Defaults to true
     * @param string $encoding    The type of encoding to use. Defaults to base64.
     *                            Possible values: 7bit, 8bit, base64 or quoted-printable.
     * @param string $disposition The content-disposition of this file
     *                            Defaults to attachment.
     *                            Possible values: attachment, inline.
     * @param string $charset     The character set of attachment's content.
     * @param string $language    The language of the attachment
     * @param string $location    The RFC 2557.4 location of the attachment
     * @param string $n_encoding  Encoding of the attachment's name in Content-Type
     *                            By default filenames are encoded using RFC2231 method
     *                            Here you can set RFC2047 encoding (quoted-printable
     *                            or base64) instead
     * @param string $f_encoding  Encoding of the attachment's filename
     *                            in Content-Disposition header.
     * @param string $description Content-Description header
     * @param string $h_charset   The character set of the headers e.g. filename
     *                            If not specified, $charset will be used
     * @param array  $add_headers Additional part headers. Array keys can be in form
     *                            of <header_name>:<parameter_name>
     *
     * @return mixed True on success or PEAR_Error object
     */
    public function addAttachment($file,
        $c_type      = 'application/octet-stream',
        $name        = '',
        $isfile      = true,
        $encoding    = 'base64',
        $disposition = 'attachment',
        $charset     = '',
        $language    = '',
        $location    = '',
        $n_encoding  = null,
        $f_encoding  = null,
        $description = '',
        $h_charset   = null,
        $add_headers = array()
    ) {
        if ($file instanceof Mail_mimePart) {
            $this->parts[] = $file;
            return true;
        }

        $bodyfile = null;

        if ($isfile) {
            // Don't load file into memory
            if ($this->build_params['delay_file_io']) {
                $filedata = null;
                $bodyfile = $file;
            } else {
                if (self::isError($filedata = $this->file2str($file))) {
                    return $filedata;
                }
            }
            // Force the name the user supplied, otherwise use $file
            $filename = ($name ? $name : $this->basename($file));
        } else {
            $filedata = $file;
            $filename = $name;
        }

        if (!strlen($filename)) {
            $msg = "The supplied filename for the attachment can't be empty";
            return self::raiseError($msg);
        }

        $this->parts[] = array(
            'body'        => $filedata,
            'body_file'   => $bodyfile,
            'name'        => $filename,
            'c_type'      => $c_type,
            'charset'     => $charset,
            'encoding'    => $encoding,
            'language'    => $language,
            'location'    => $location,
            'disposition' => $disposition,
            'description' => $description,
            'add_headers' => $add_headers,
            'name_encoding'     => $n_encoding,
            'filename_encoding' => $f_encoding,
            'headers_charset'   => $h_charset,
        );

        return true;
    }

    /**
     * Checks if the current message has many parts
     *
     * @return bool True if the message has many parts, False otherwise.
     * @since 1.9.0
     */
    public function isMultipart()
    {
        return count($this->parts) > 0 || count($this->html_images) > 0
            || (strlen($this->htmlbody) > 0 && strlen($this->txtbody) > 0);
    }

    /**
     * Get the contents of the given file name as string
     *
     * @param string $file_name Path of file to process
     *
     * @return string Contents of $file_name
     */
    protected function file2str($file_name)
    {
        // Check state of file and raise an error properly
        if (!file_exists($file_name)) {
            return self::raiseError('File not found: ' . $file_name);
        }
        if (!is_file($file_name)) {
            return self::raiseError('Not a regular file: ' . $file_name);
        }
        if (!is_readable($file_name)) {
            return self::raiseError('File is not readable: ' . $file_name);
        }

        // Temporarily reset magic_quotes_runtime and read file contents
        if ($magic_quote_setting = get_magic_quotes_runtime()) {
            @ini_set('magic_quotes_runtime', 0);
        }

        $cont = file_get_contents($file_name);

        if ($magic_quote_setting) {
            @ini_set('magic_quotes_runtime', $magic_quote_setting);
        }

        return $cont;
    }

    /**
     * Adds a text subpart to the mimePart object and
     * returns it during the build process.
     *
     * @param mixed $obj The object to add the part to, or
     *                   anything else if a new object is to be created.
     *
     * @return object The text mimePart object
     */
    protected function addTextPart($obj = null)
    {
        return $this->addBodyPart($obj, $this->txtbody, 'text/plain', 'text');
    }

    /**
     * Adds a html subpart to the mimePart object and
     * returns it during the build process.
     *
     * @param mixed $obj The object to add the part to, or
     *                   anything else if a new object is to be created.
     *
     * @return object The html mimePart object
     */
    protected function addHtmlPart($obj = null)
    {
        return $this->addBodyPart($obj, $this->htmlbody, 'text/html', 'html');
    }

    /**
     * Adds a calendar subpart to the mimePart object and
     * returns it during the build process.
     *
     * @param mixed $obj The object to add the part to, or
     *                   anything else if a new object is to be created.
     *
     * @return object The text mimePart object
     */
    protected function addCalendarPart($obj = null)
    {
        $ctype = 'text/calendar; method='. $this->build_params['calendar_method'];

        return $this->addBodyPart($obj, $this->calbody, $ctype, 'calendar');
    }

    /**
     * Creates a new mimePart object, using multipart/mixed as
     * the initial content-type and returns it during the
     * build process.
     *
     * @param array $params Additional part parameters
     *
     * @return object The multipart/mixed mimePart object
     */
    protected function addMixedPart($params = array())
    {
        $params['content_type'] = 'multipart/mixed';
        $params['eol']          = $this->build_params['eol'];

        // Create empty multipart/mixed Mail_mimePart object to return
        return new Mail_mimePart('', $params);
    }

    /**
     * Adds a multipart/alternative part to a mimePart
     * object (or creates one), and returns it during
     * the build process.
     *
     * @param mixed $obj The object to add the part to, or
     *                   anything else if a new object is to be created.
     *
     * @return object The multipart/mixed mimePart object
     */
    protected function addAlternativePart($obj = null)
    {
        $params['content_type'] = 'multipart/alternative';
        $params['eol']          = $this->build_params['eol'];

        if (is_object($obj)) {
            $ret = $obj->addSubpart('', $params);
        } else {
            $ret = new Mail_mimePart('', $params);
        }

        return $ret;
    }

    /**
     * Adds a multipart/related part to a mimePart
     * object (or creates one), and returns it during
     * the build process.
     *
     * @param mixed $obj The object to add the part to, or
     *                   anything else if a new object is to be created
     *
     * @return object The multipart/mixed mimePart object
     */
    protected function addRelatedPart($obj = null)
    {
        $params['content_type'] = 'multipart/related';
        $params['eol']          = $this->build_params['eol'];

        if (is_object($obj)) {
            $ret = $obj->addSubpart('', $params);
        } else {
            $ret = new Mail_mimePart('', $params);
        }

        return $ret;
    }

    /**
     * Adds an html image subpart to a mimePart object
     * and returns it during the build process.
     *
     * @param object $obj   The mimePart to add the image to
     * @param array  $value The image information
     *
     * @return object The image mimePart object
     */
    protected function addHtmlImagePart($obj, $value)
    {
        $params['content_type'] = $value['c_type'];
        $params['encoding']     = 'base64';
        $params['disposition']  = 'inline';
        $params['filename']     = $value['name'];
        $params['cid']          = $value['cid'];
        $params['body_file']    = $value['body_file'];
        $params['eol']          = $this->build_params['eol'];

        if (!empty($value['name_encoding'])) {
            $params['name_encoding'] = $value['name_encoding'];
        }
        if (!empty($value['filename_encoding'])) {
            $params['filename_encoding'] = $value['filename_encoding'];
        }

        return $obj->addSubpart($value['body'], $params);
    }

    /**
     * Adds an attachment subpart to a mimePart object
     * and returns it during the build process.
     *
     * @param object $obj   The mimePart to add the image to
     * @param mixed  $value The attachment information array or Mail_mimePart object
     *
     * @return object The image mimePart object
     */
    protected function addAttachmentPart($obj, $value)
    {
        if ($value instanceof Mail_mimePart) {
            return $obj->addSubpart($value);
        }

        $params['eol']          = $this->build_params['eol'];
        $params['filename']     = $value['name'];
        $params['encoding']     = $value['encoding'];
        $params['content_type'] = $value['c_type'];
        $params['body_file']    = $value['body_file'];
        $params['disposition']  = isset($value['disposition']) ?
                                  $value['disposition'] : 'attachment';

        // content charset
        if (!empty($value['charset'])) {
            $params['charset'] = $value['charset'];
        }
        // headers charset (filename, description)
        if (!empty($value['headers_charset'])) {
            $params['headers_charset'] = $value['headers_charset'];
        }
        if (!empty($value['language'])) {
            $params['language'] = $value['language'];
        }
        if (!empty($value['location'])) {
            $params['location'] = $value['location'];
        }
        if (!empty($value['name_encoding'])) {
            $params['name_encoding'] = $value['name_encoding'];
        }
        if (!empty($value['filename_encoding'])) {
            $params['filename_encoding'] = $value['filename_encoding'];
        }
        if (!empty($value['description'])) {
            $params['description'] = $value['description'];
        }
        if (is_array($value['add_headers'])) {
            $params['headers'] = $value['add_headers'];
        }

        return $obj->addSubpart($value['body'], $params);
    }

    /**
     * Returns the complete e-mail, ready to send using an alternative
     * mail delivery method. Note that only the mailpart that is made
     * with Mail_Mime is created. This means that,
     * YOU WILL HAVE NO TO: HEADERS UNLESS YOU SET IT YOURSELF
     * using the $headers parameter!
     *
     * @param string $separation The separation between these two parts.
     * @param array  $params     The Build parameters passed to the
     *                           get() function. See get() for more info.
     * @param array  $headers    The extra headers that should be passed
     *                           to the headers() method.
     *                           See that function for more info.
     * @param bool   $overwrite  Overwrite the existing headers with new.
     *
     * @return mixed The complete e-mail or PEAR error object
     */
    public function getMessage($separation = null, $params = null, $headers = null,
        $overwrite = false
    ) {
        if ($separation === null) {
            $separation = $this->build_params['eol'];
        }

        $body = $this->get($params);

        if (self::isError($body)) {
            return $body;
        }

        return $this->txtHeaders($headers, $overwrite) . $separation . $body;
    }

    /**
     * Returns the complete e-mail body, ready to send using an alternative
     * mail delivery method.
     *
     * @param array $params The Build parameters passed to the
     *                      get() method. See get() for more info.
     *
     * @return mixed The e-mail body or PEAR error object
     * @since 1.6.0
     */
    public function getMessageBody($params = null)
    {
        return $this->get($params, null, true);
    }

    /**
     * Writes (appends) the complete e-mail into file.
     *
     * @param string $filename  Output file location
     * @param array  $params    The Build parameters passed to the
     *                          get() method. See get() for more info.
     * @param array  $headers   The extra headers that should be passed
     *                          to the headers() function.
     *                          See that function for more info.
     * @param bool   $overwrite Overwrite the existing headers with new.
     *
     * @return mixed True or PEAR error object
     * @since 1.6.0
     */
    public function saveMessage($filename, $params = null, $headers = null, $overwrite = false)
    {
        // Check state of file and raise an error properly
        if (file_exists($filename) && !is_writable($filename)) {
            return self::raiseError('File is not writable: ' . $filename);
        }

        // Temporarily reset magic_quotes_runtime and read file contents
        if ($magic_quote_setting = get_magic_quotes_runtime()) {
            @ini_set('magic_quotes_runtime', 0);
        }

        if (!($fh = fopen($filename, 'ab'))) {
            return self::raiseError('Unable to open file: ' . $filename);
        }

        // Write message headers into file (skipping Content-* headers)
        $head = $this->txtHeaders($headers, $overwrite, true);
        if (fwrite($fh, $head) === false) {
            return self::raiseError('Error writing to file: ' . $filename);
        }

        fclose($fh);

        if ($magic_quote_setting) {
            @ini_set('magic_quotes_runtime', $magic_quote_setting);
        }

        // Write the rest of the message into file
        $res = $this->get($params, $filename);

        return $res ? $res : true;
    }

    /**
     * Writes (appends) the complete e-mail body into file or stream.
     *
     * @param mixed $filename Output filename or file pointer where to save
     *                        the message instead of returning it
     * @param array $params   The Build parameters passed to the
     *                        get() method. See get() for more info.
     *
     * @return mixed True or PEAR error object
     * @since 1.6.0
     */
    public function saveMessageBody($filename, $params = null)
    {
        if (!is_resource($filename)) {
            // Check state of file and raise an error properly
            if (!file_exists($filename) || !is_writable($filename)) {
                return self::raiseError('File is not writable: ' . $filename);
            }

            if (!($fh = fopen($filename, 'ab'))) {
                return self::raiseError('Unable to open file: ' . $filename);
            }
        } else {
            $fh = $filename;
        }

        // Temporarily reset magic_quotes_runtime and read file contents
        if ($magic_quote_setting = get_magic_quotes_runtime()) {
            @ini_set('magic_quotes_runtime', 0);
        }

        // Write the rest of the message into file
        $res = $this->get($params, $fh, true);

        if (!is_resource($filename)) {
            fclose($fh);
        }

        if ($magic_quote_setting) {
            @ini_set('magic_quotes_runtime', $magic_quote_setting);
        }

        return $res ? $res : true;
    }

    /**
     * Builds the multipart message from the list ($this->parts) and
     * returns the mime content.
     *
     * @param array   $params    Build parameters that change the way the email
     *                           is built. Should be associative. See $_build_params.
     * @param mixed   $filename  Output filename or file pointer where to save
     *                           the message instead of returning it
     * @param boolean $skip_head True if you want to return/save only the message
     *                           without headers
     *
     * @return mixed The MIME message content string, null or PEAR error object
     */
    public function get($params = null, $filename = null, $skip_head = false)
    {
        if (isset($params)) {
            while (list($key, $value) = each($params)) {
                $this->build_params[$key] = $value;
            }
        }

        if (isset($this->headers['From'])) {
            // Bug #11381: Illegal characters in domain ID
            if (preg_match('#(@[0-9a-zA-Z\-\.]+)#', $this->headers['From'], $matches)) {
                $domainID = $matches[1];
            } else {
                $domainID = '@localhost';
            }

            foreach ($this->html_images as $i => $img) {
                $cid = $this->html_images[$i]['cid'];
                if (!preg_match('#'.preg_quote($domainID).'$#', $cid)) {
                    $this->html_images[$i]['cid'] = $cid . $domainID;
                }
            }
        }

        if (count($this->html_images) && isset($this->htmlbody)) {
            foreach ($this->html_images as $key => $value) {
                $rval  = preg_quote($value['name'], '#');
                $regex = array(
                    '#(\s)((?i)src|background|href(?-i))\s*=\s*(["\']?)' . $rval . '\3#',
                    '#(?i)url(?-i)\(\s*(["\']?)' . $rval . '\1\s*\)#',
                );

                $rep = array(
                    '\1\2=\3cid:' . $value['cid'] .'\3',
                    'url(\1cid:' . $value['cid'] . '\1)',
                );

                $this->htmlbody = preg_replace($regex, $rep, $this->htmlbody);
                $this->html_images[$key]['name']
                    = $this->basename($this->html_images[$key]['name']);
            }
        }

        $this->checkParams();

        $attachments = count($this->parts) > 0;
        $html_images = count($this->html_images) > 0;
        $html        = strlen($this->htmlbody) > 0;
        $calendar    = strlen($this->calbody) > 0;
        $has_text    = strlen($this->txtbody) > 0;
        $text        = !$html && $has_text;
        $mixed_params = array('preamble' => $this->build_params['preamble']);

        switch (true) {
        case $calendar && !$attachments && !$text && !$html:
            $message = $this->addCalendarPart();
            break;

        case $calendar && !$attachments:
            $message = $this->addAlternativePart($mixed_params);
            if ($has_text) {
                $this->addTextPart($message);
            }
            if ($html) {
                $this->addHtmlPart($message);
            }
            $this->addCalendarPart($message);
            break;

        case $text && !$attachments:
            $message = $this->addTextPart();
            break;

        case !$text && !$html && $attachments:
            $message = $this->addMixedPart($mixed_params);
            for ($i = 0; $i < count($this->parts); $i++) {
                $this->addAttachmentPart($message, $this->parts[$i]);
            }
            break;

        case $text && $attachments:
            $message = $this->addMixedPart($mixed_params);
            $this->addTextPart($message);
            for ($i = 0; $i < count($this->parts); $i++) {
                $this->addAttachmentPart($message, $this->parts[$i]);
            }
            break;

        case $html && !$attachments && !$html_images:
            if (isset($this->txtbody)) {
                $message = $this->addAlternativePart();
                $this->addTextPart($message);
                $this->addHtmlPart($message);
            } else {
                $message = $this->addHtmlPart();
            }
            break;

        case $html && !$attachments && $html_images:
            // * Content-Type: multipart/alternative;
            //    * text
            //    * Content-Type: multipart/related;
            //       * html
            //       * image...
            if (isset($this->txtbody)) {
                $message = $this->addAlternativePart();
                $this->addTextPart($message);

                $ht = $this->addRelatedPart($message);
                $this->addHtmlPart($ht);
                for ($i = 0; $i < count($this->html_images); $i++) {
                    $this->addHtmlImagePart($ht, $this->html_images[$i]);
                }
            } else {
                // * Content-Type: multipart/related;
                //    * html
                //    * image...
                $message = $this->addRelatedPart();
                $this->addHtmlPart($message);
                for ($i = 0; $i < count($this->html_images); $i++) {
                    $this->addHtmlImagePart($message, $this->html_images[$i]);
                }
            }
            /*
            // #13444, #9725: the code below was a non-RFC compliant hack
            // * Content-Type: multipart/related;
            //    * Content-Type: multipart/alternative;
            //        * text
            //        * html
            //    * image...
            $message = $this->addRelatedPart();
            if (isset($this->txtbody)) {
                $alt = $this->addAlternativePart($message);
                $this->addTextPart($alt);
                $this->addHtmlPart($alt);
            } else {
                $this->addHtmlPart($message);
            }
            for ($i = 0; $i < count($this->html_images); $i++) {
                $this->addHtmlImagePart($message, $this->html_images[$i]);
            }
            */
            break;

        case $html && $attachments && !$html_images:
            $message = $this->addMixedPart($mixed_params);
            if (isset($this->txtbody)) {
                $alt = $this->addAlternativePart($message);
                $this->addTextPart($alt);
                $this->addHtmlPart($alt);
            } else {
                $this->addHtmlPart($message);
            }
            for ($i = 0; $i < count($this->parts); $i++) {
                $this->addAttachmentPart($message, $this->parts[$i]);
            }
            break;

        case $html && $attachments && $html_images:
            $message = $this->addMixedPart($mixed_params);
            if (isset($this->txtbody)) {
                $alt = $this->addAlternativePart($message);
                $this->addTextPart($alt);
                $rel = $this->addRelatedPart($alt);
            } else {
                $rel = $this->addRelatedPart($message);
            }
            $this->addHtmlPart($rel);
            for ($i = 0; $i < count($this->html_images); $i++) {
                $this->addHtmlImagePart($rel, $this->html_images[$i]);
            }
            for ($i = 0; $i < count($this->parts); $i++) {
                $this->addAttachmentPart($message, $this->parts[$i]);
            }
            break;
        }

        if (!isset($message)) {
            return null;
        }

        // Use saved boundary
        if (!empty($this->build_params['boundary'])) {
            $boundary = $this->build_params['boundary'];
        } else {
            $boundary = null;
        }

        // Write output to file
        if ($filename) {
            // Append mimePart message headers and body into file
            $headers = $message->encodeToFile($filename, $boundary, $skip_head);
            if (self::isError($headers)) {
                return $headers;
            }
            $this->headers = array_merge($this->headers, $headers);
            return null;
        } else {
            $output = $message->encode($boundary, $skip_head);
            if (self::isError($output)) {
                return $output;
            }
            $this->headers = array_merge($this->headers, $output['headers']);
            return $output['body'];
        }
    }

    /**
     * Returns an array with the headers needed to prepend to the email
     * (MIME-Version and Content-Type). Format of argument is:
     * $array['header-name'] = 'header-value';
     *
     * @param array $xtra_headers Assoc array with any extra headers (optional)
     *                            (Don't set Content-Type for multipart messages here!)
     * @param bool  $overwrite    Overwrite already existing headers.
     * @param bool  $skip_content Don't return content headers: Content-Type,
     *                            Content-Disposition and Content-Transfer-Encoding
     *
     * @return array Assoc array with the mime headers
     */
    public function headers($xtra_headers = null, $overwrite = false, $skip_content = false)
    {
        // Add mime version header
        $headers['MIME-Version'] = '1.0';

        // Content-Type and Content-Transfer-Encoding headers should already
        // be present if get() was called, but we'll re-set them to make sure
        // we got them when called before get() or something in the message
        // has been changed after get() [#14780]
        if (!$skip_content) {
            $headers += $this->contentHeaders();
        }

        if (!empty($xtra_headers)) {
            $headers = array_merge($headers, $xtra_headers);
        }

        if ($overwrite) {
            $this->headers = array_merge($this->headers, $headers);
        } else {
            $this->headers = array_merge($headers, $this->headers);
        }

        $headers = $this->headers;

        if ($skip_content) {
            unset($headers['Content-Type']);
            unset($headers['Content-Transfer-Encoding']);
            unset($headers['Content-Disposition']);
        } else if (!empty($this->build_params['ctype'])) {
            $headers['Content-Type'] = $this->build_params['ctype'];
        }

        $encodedHeaders = $this->encodeHeaders($headers);
        return $encodedHeaders;
    }

    /**
     * Get the text version of the headers
     * (usefull if you want to use the PHP mail() function)
     *
     * @param array $xtra_headers Assoc array with any extra headers (optional)
     *                            (Don't set Content-Type for multipart messages here!)
     * @param bool  $overwrite    Overwrite the existing headers with new.
     * @param bool  $skip_content Don't return content headers: Content-Type,
     *                            Content-Disposition and Content-Transfer-Encoding
     *
     * @return string Plain text headers
     */
    public function txtHeaders($xtra_headers = null, $overwrite = false, $skip_content = false)
    {
        $headers = $this->headers($xtra_headers, $overwrite, $skip_content);

        // Place Received: headers at the beginning of the message
        // Spam detectors often flag messages with it after the Subject: as spam
        if (isset($headers['Received'])) {
            $received = $headers['Received'];
            unset($headers['Received']);
            $headers = array('Received' => $received) + $headers;
        }

        $ret = '';
        $eol = $this->build_params['eol'];

        foreach ($headers as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $value) {
                    $ret .= "$key: $value" . $eol;
                }
            } else {
                $ret .= "$key: $val" . $eol;
            }
        }

        return $ret;
    }

    /**
     * Sets message Content-Type header.
     * Use it to build messages with various content-types e.g. miltipart/raport
     * not supported by contentHeaders() function.
     *
     * @param string $type   Type name
     * @param array  $params Hash array of header parameters
     *
     * @return void
     * @since 1.7.0
     */
    public function setContentType($type, $params = array())
    {
        $header = $type;

        $eol = !empty($this->build_params['eol'])
            ? $this->build_params['eol'] : "\r\n";

        // add parameters
        $token_regexp = '#([^\x21\x23-\x27\x2A\x2B\x2D'
            . '\x2E\x30-\x39\x41-\x5A\x5E-\x7E])#';

        if (is_array($params)) {
            foreach ($params as $name => $value) {
                if ($name == 'boundary') {
                    $this->build_params['boundary'] = $value;
                }
                if (!preg_match($token_regexp, $value)) {
                    $header .= ";$eol $name=$value";
                } else {
                    $value = addcslashes($value, '\\"');
                    $header .= ";$eol $name=\"$value\"";
                }
            }
        }

        // add required boundary parameter if not defined
        if (stripos($type, 'multipart/') === 0) {
            if (empty($this->build_params['boundary'])) {
                $this->build_params['boundary'] = '=_' . md5(rand() . microtime());
            }

            $header .= ";$eol boundary=\"".$this->build_params['boundary']."\"";
        }

        $this->build_params['ctype'] = $header;
    }

    /**
     * Sets the Subject header
     *
     * @param string $subject String to set the subject to.
     *
     * @return void
     */
    public function setSubject($subject)
    {
        $this->headers['Subject'] = $subject;
    }

    /**
     * Set an email to the From (the sender) header
     *
     * @param string $email The email address to use
     *
     * @return void
     */
    public function setFrom($email)
    {
        $this->headers['From'] = $email;
    }

    /**
     * Add an email to the To header
     * (multiple calls to this method are allowed)
     *
     * @param string $email The email direction to add
     *
     * @return void
     */
    public function addTo($email)
    {
        if (isset($this->headers['To'])) {
            $this->headers['To'] .= ", $email";
        } else {
            $this->headers['To'] = $email;
        }
    }

    /**
     * Add an email to the Cc (carbon copy) header
     * (multiple calls to this method are allowed)
     *
     * @param string $email The email direction to add
     *
     * @return void
     */
    public function addCc($email)
    {
        if (isset($this->headers['Cc'])) {
            $this->headers['Cc'] .= ", $email";
        } else {
            $this->headers['Cc'] = $email;
        }
    }

    /**
     * Add an email to the Bcc (blank carbon copy) header
     * (multiple calls to this method are allowed)
     *
     * @param string $email The email direction to add
     *
     * @return void
     */
    public function addBcc($email)
    {
        if (isset($this->headers['Bcc'])) {
            $this->headers['Bcc'] .= ", $email";
        } else {
            $this->headers['Bcc'] = $email;
        }
    }

    /**
     * Since the PHP send function requires you to specify
     * recipients (To: header) separately from the other
     * headers, the To: header is not properly encoded.
     * To fix this, you can use this public method to encode
     * your recipients before sending to the send function.
     *
     * @param string $recipients A comma-delimited list of recipients
     *
     * @return string Encoded data
     */
    public function encodeRecipients($recipients)
    {
        $input  = array('To' => $recipients);
        $retval = $this->encodeHeaders($input);

        return $retval['To'] ;
    }

    /**
     * Encodes headers as per RFC2047
     *
     * @param array $input  The header data to encode
     * @param array $params Extra build parameters
     *
     * @return array Encoded data
     */
    protected function encodeHeaders($input, $params = array())
    {
        $build_params = $this->build_params;

        if (!empty($params)) {
            $build_params = array_merge($build_params, $params);
        }

        foreach ($input as $hdr_name => $hdr_value) {
            if (is_array($hdr_value)) {
                foreach ($hdr_value as $idx => $value) {
                    $input[$hdr_name][$idx] = $this->encodeHeader(
                        $hdr_name, $value,
                        $build_params['head_charset'], $build_params['head_encoding']
                    );
                }
            } else if ($hdr_value !== null) {
                $input[$hdr_name] = $this->encodeHeader(
                    $hdr_name, $hdr_value,
                    $build_params['head_charset'], $build_params['head_encoding']
                );
            } else {
                unset($input[$hdr_name]);
            }
        }

        return $input;
    }

    /**
     * Encodes a header as per RFC2047
     *
     * @param string $name     The header name
     * @param string $value    The header data to encode
     * @param string $charset  Character set name
     * @param string $encoding Encoding name (base64 or quoted-printable)
     *
     * @return string Encoded header data (without a name)
     * @since 1.5.3
     */
    public function encodeHeader($name, $value, $charset, $encoding)
    {
        return Mail_mimePart::encodeHeader(
            $name, $value, $charset, $encoding, $this->build_params['eol']
        );
    }

    /**
     * Get file's basename (locale independent)
     *
     * @param string $filename Filename
     *
     * @return string Basename
     */
    protected function basename($filename)
    {
        // basename() is not unicode safe and locale dependent
        if (stristr(PHP_OS, 'win') || stristr(PHP_OS, 'netware')) {
            return preg_replace('/^.*[\\\\\\/]/', '', $filename);
        } else {
            return preg_replace('/^.*[\/]/', '', $filename);
        }
    }

    /**
     * Get Content-Type and Content-Transfer-Encoding headers of the message
     *
     * @return array Headers array
     */
    protected function contentHeaders()
    {
        $attachments = count($this->parts) > 0;
        $html_images = count($this->html_images) > 0;
        $html        = strlen($this->htmlbody) > 0;
        $calendar    = strlen($this->calbody) > 0;
        $has_text    = strlen($this->txtbody) > 0;
        $text        = !$html && $has_text;
        $headers     = array();

        // See get()
        switch (true) {
        case $calendar && !$attachments && !$html && !$has_text:
            $headers['Content-Type'] = 'text/calendar';
            break;

        case $calendar && !$attachments:
            $headers['Content-Type'] = 'multipart/alternative';
            break;

        case $text && !$attachments:
            $headers['Content-Type'] = 'text/plain';
            break;

        case !$text && !$html && $attachments:
        case $text && $attachments:
        case $html && $attachments && !$html_images:
        case $html && $attachments && $html_images:
            $headers['Content-Type'] = 'multipart/mixed';
            break;

        case $html && !$attachments && !$html_images && $has_text:
        case $html && !$attachments && $html_images && $has_text:
            $headers['Content-Type'] = 'multipart/alternative';
            break;

        case $html && !$attachments && !$html_images && !$has_text:
            $headers['Content-Type'] = 'text/html';
            break;

        case $html && !$attachments && $html_images && !$has_text:
            $headers['Content-Type'] = 'multipart/related';
            break;

        default:
            return $headers;
        }

        $this->checkParams();

        $eol = !empty($this->build_params['eol'])
            ? $this->build_params['eol'] : "\r\n";

        if ($headers['Content-Type'] == 'text/plain') {
            // single-part message: add charset and encoding
            if ($this->build_params['text_charset']) {
                $charset = 'charset=' . $this->build_params['text_charset'];
                // place charset parameter in the same line, if possible
                // 26 = strlen("Content-Type: text/plain; ")
                $headers['Content-Type']
                    .= (strlen($charset) + 26 <= 76) ? "; $charset" : ";$eol $charset";
            }

            $headers['Content-Transfer-Encoding']
                = $this->build_params['text_encoding'];
        } else if ($headers['Content-Type'] == 'text/html') {
            // single-part message: add charset and encoding
            if ($this->build_params['html_charset']) {
                $charset = 'charset=' . $this->build_params['html_charset'];
                // place charset parameter in the same line, if possible
                $headers['Content-Type']
                    .= (strlen($charset) + 25 <= 76) ? "; $charset" : ";$eol $charset";
            }
            $headers['Content-Transfer-Encoding']
                = $this->build_params['html_encoding'];
        }
        else if ($headers['Content-Type'] == 'text/calendar') {
            // single-part message: add charset and encoding
            if ($this->build_params['calendar_charset']) {
                $charset = 'charset=' . $this->build_params['calendar_charset'];
                $headers['Content-Type'] .= "; $charset";
            }

            if ($this->build_params['calendar_method']) {
                $method = 'method=' . $this->build_params['calendar_method'];
                $headers['Content-Type'] .= "; $method";
            }

            $headers['Content-Transfer-Encoding']
                = $this->build_params['calendar_encoding'];
        } else {
            // multipart message: and boundary
            if (!empty($this->build_params['boundary'])) {
                $boundary = $this->build_params['boundary'];
            } else if (!empty($this->headers['Content-Type'])
                && preg_match('/boundary="([^"]+)"/', $this->headers['Content-Type'], $m)
            ) {
                $boundary = $m[1];
            } else {
                $boundary = '=_' . md5(rand() . microtime());
            }

            $this->build_params['boundary'] = $boundary;
            $headers['Content-Type'] .= ";$eol boundary=\"$boundary\"";
        }

        return $headers;
    }

    /**
     * Validate and set build parameters
     *
     * @return void
     */
    protected function checkParams()
    {
        $encodings = array('7bit', '8bit', 'base64', 'quoted-printable');

        $this->build_params['text_encoding']
            = strtolower($this->build_params['text_encoding']);
        $this->build_params['html_encoding']
            = strtolower($this->build_params['html_encoding']);
        $this->build_params['calendar_encoding']
            = strtolower($this->build_params['calendar_encoding']);

        if (!in_array($this->build_params['text_encoding'], $encodings)) {
            $this->build_params['text_encoding'] = '7bit';
        }
        if (!in_array($this->build_params['html_encoding'], $encodings)) {
            $this->build_params['html_encoding'] = '7bit';
        }
        if (!in_array($this->build_params['calendar_encoding'], $encodings)) {
            $this->build_params['calendar_encoding'] = '7bit';
        }

        // text body
        if ($this->build_params['text_encoding'] == '7bit'
            && !preg_match('/ascii/i', $this->build_params['text_charset'])
            && preg_match('/[^\x00-\x7F]/', $this->txtbody)
        ) {
            $this->build_params['text_encoding'] = 'quoted-printable';
        }
        // html body
        if ($this->build_params['html_encoding'] == '7bit'
            && !preg_match('/ascii/i', $this->build_params['html_charset'])
            && preg_match('/[^\x00-\x7F]/', $this->htmlbody)
        ) {
            $this->build_params['html_encoding'] = 'quoted-printable';
        }
        // calendar body
        if ($this->build_params['calendar_encoding'] == '7bit'
            && !preg_match('/ascii/i', $this->build_params['calendar_charset'])
            && preg_match('/[^\x00-\x7F]/', $this->calbody)
        ) {
            $this->build_params['calendar_encoding'] = 'quoted-printable';
        }
    }

    /**
     * Set body of specified message part
     *
     * @param string $type   One of: txtbody, calbody, htmlbody
     * @param string $data   Either a string or the file name with the contents
     * @param bool   $isfile If true the first param should be treated
     *                       as a file name, else as a string (default)
     * @param bool   $append If true the text or file is appended to
     *                       the existing body, else the old body is
     *                       overwritten
     *
     * @return mixed True on success or PEAR_Error object
     */
    protected function setBody($type, $data, $isfile = false, $append = false)
    {
        if (!$isfile) {
            if (!$append) {
                $this->{$type} = $data;
            } else {
                $this->{$type} .= $data;
            }
        } else {
            $cont = $this->file2str($data);
            if (self::isError($cont)) {
                return $cont;
            }

            if (!$append) {
                $this->{$type} = $cont;
            } else {
                $this->{$type} .= $cont;
            }
        }

        return true;
    }

    /**
     * Adds a subpart to the mimePart object and
     * returns it during the build process.
     *
     * @param mixed  $obj   The object to add the part to, or
     *                      anything else if a new object is to be created.
     * @param string $body  Part body
     * @param string $ctype Part content type
     * @param string $type  Internal part type
     *
     * @return object The mimePart object
     */
    protected function addBodyPart($obj, $body, $ctype, $type)
    {
        $params['content_type'] = $ctype;
        $params['encoding']     = $this->build_params[$type . '_encoding'];
        $params['charset']      = $this->build_params[$type . '_charset'];
        $params['eol']          = $this->build_params['eol'];

        if (is_object($obj)) {
            $ret = $obj->addSubpart($body, $params);
        } else {
            $ret = new Mail_mimePart($body, $params);
        }

        return $ret;
    }

    /**
     * PEAR::isError implementation
     *
     * @param mixed $data Object
     *
     * @return bool True if object is an instance of PEAR_Error
     */
    public static function isError($data)
    {
        // PEAR::isError() is not PHP 5.4 compatible (see Bug #19473)
        if (is_a($data, 'PEAR_Error')) {
            return true;
        }

        return false;
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
