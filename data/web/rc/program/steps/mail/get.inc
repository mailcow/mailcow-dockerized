<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/get.inc                                            |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Delivering a specific uploaded file or mail message attachment      |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/


// show loading page
if (!empty($_GET['_preload'])) {
    unset($_GET['_preload']);
    unset($_GET['_safe']);

    $url     = $RCMAIL->url($_GET + array('_mimewarning' => 1, '_embed' => 1));
    $message = $RCMAIL->gettext('loadingdata');

    header('Content-Type: text/html; charset=' . RCUBE_CHARSET);
    print "<html>\n<head>\n"
        . '<meta http-equiv="refresh" content="0; url='.rcube::Q($url).'">' . "\n"
        . '<meta http-equiv="content-type" content="text/html; charset='.RCUBE_CHARSET.'">' . "\n"
        . "</head>\n<body>\n$message\n</body>\n</html>";
    exit;
}

$attachment = new rcmail_attachment_handler;
$mimetype   = $attachment->mimetype;
$filename   = $attachment->filename;

// show part page
if (!empty($_GET['_frame'])) {
    $OUTPUT->set_pagetitle($filename);

    // register UI objects
    $OUTPUT->add_handlers(array(
        'messagepartframe'    => 'rcmail_message_part_frame',
        'messagepartcontrols' => 'rcmail_message_part_controls',
    ));

    $part_id = rcube_utils::get_input_value('_part', rcube_utils::INPUT_GET);
    $uid     = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GET);

    // message/rfc822 preview (Note: handle also multipart/ parts, they can
    // come from Enigma, which replaces message/rfc822 with real mimetype)
    if ($part_id && ($mimetype == 'message/rfc822' || strpos($mimetype, 'multipart/') === 0)) {
        $uid = preg_replace('/\.[0-9.]+/', '', $uid);
        $uid .= '.' . $part_id;

        $OUTPUT->set_env('is_message', true);
    }

    $OUTPUT->set_env('mailbox', $RCMAIL->storage->get_folder());
    $OUTPUT->set_env('uid', $uid);
    $OUTPUT->set_env('part', $part_id);
    $OUTPUT->set_env('filename', $filename);
    $OUTPUT->set_env('mimetype', $mimetype);

    $OUTPUT->send('messagepart');
    exit;
}

// render thumbnail of an image attachment
if (!empty($_GET['_thumb']) && $attachment->is_valid()) {
    $thumbnail_size = $RCMAIL->config->get('image_thumbnail_size', 240);
    $temp_dir       = $RCMAIL->config->get('temp_dir');
    $file_ident     = $attachment->ident;
    $cache_basename = $temp_dir . '/' . md5($file_ident . ':' . $RCMAIL->user->ID . ':' . $thumbnail_size);
    $cache_file     = $cache_basename . '.thumb';

    // render thumbnail image if not done yet
    if (!is_file($cache_file) && $attachment->body_to_file($orig_name = $cache_basename . '.tmp')) {
        $image = new rcube_image($orig_name);

        if ($imgtype = $image->resize($thumbnail_size, $cache_file, true)) {
            $mimetype = 'image/' . $imgtype;
            unlink($orig_name);
        }
        else {
            // Resize failed, we need to check the file mimetype
            // So, we do not exit here, but goto generic file body handler below
            $_GET['_thumb']     = 0;
            $_REQUEST['_embed'] = 1;
        }
    }

    if (!empty($_GET['_thumb'])) {
        if (is_file($cache_file)) {
            $RCMAIL->output->future_expire_header(3600);
            header('Content-Type: ' . $mimetype);
            header('Content-Length: ' . filesize($cache_file));
            readfile($cache_file);
        }

        exit;
    }
}

// Handle attachment body (display or download)
if (empty($_GET['_thumb']) && $attachment->is_valid()) {
    // require CSRF protected url for downloads
    if (!empty($_GET['_download'])) {
        $RCMAIL->request_security_check(rcube_utils::INPUT_GET);
    }

    $extensions = rcube_mime::get_mime_extensions($mimetype);

    // compare file mimetype with the stated content-type headers and file extension to avoid malicious operations
    if (!empty($_REQUEST['_embed']) && empty($_REQUEST['_nocheck'])) {
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // 1. compare filename suffix with expected suffix derived from mimetype
        $valid = $file_extension && in_array($file_extension, (array)$extensions) || empty($extensions) || !empty($_REQUEST['_mimeclass']);

        // 2. detect the real mimetype of the attachment part and compare it with the stated mimetype and filename extension
        if ($valid || !$file_extension || $mimetype == 'application/octet-stream' || stripos($mimetype, 'text/') === 0) {
            $tmp_body = $attachment->body(2048);

            // detect message part mimetype
            $real_mimetype = rcube_mime::file_content_type($tmp_body, $filename, $mimetype, true, true);
            list($real_ctype_primary, $real_ctype_secondary) = explode('/', $real_mimetype);

            // accept text/plain with any extension
            if ($real_mimetype == 'text/plain' && $real_mimetype == $mimetype) {
                $valid_extension = true;
            }
            // ignore differences in text/* mimetypes. Filetype detection isn't very reliable here
            else if ($real_ctype_primary == 'text' && strpos($mimetype, $real_ctype_primary) === 0) {
                $real_mimetype   = $mimetype;
                $valid_extension = true;
            }
            // ignore filename extension if mimeclass matches (#1489029)
            else if (!empty($_REQUEST['_mimeclass']) && $real_ctype_primary == $_REQUEST['_mimeclass']) {
                $valid_extension = true;
            }
            else {
                // get valid file extensions
                $extensions      = rcube_mime::get_mime_extensions($real_mimetype);
                $valid_extension = !$file_extension || empty($extensions) || in_array($file_extension, (array)$extensions);
            }

            // fix mimetype for images wrongly declared as octet-stream
            if ($mimetype == 'application/octet-stream' && strpos($real_mimetype, 'image/') === 0 && $valid_extension) {
                $mimetype = $real_mimetype;
            }
            // fix mimetype for images with wrong mimetype
            else if (strpos($real_mimetype, 'image/') === 0 && strpos($mimetype, 'image/') === 0) {
                $mimetype = $real_mimetype;
            }

            // "fix" real mimetype the same way the original is before comparison
            $real_mimetype = rcmail_fix_mimetype($real_mimetype);

            $valid = $real_mimetype == $mimetype && $valid_extension;
        }
        else {
            $real_mimetype = $mimetype;
        }

        // show warning if validity checks failed
        if (!$valid) {
            // send blocked.gif for expected images
            if (empty($_REQUEST['_mimewarning']) && strpos($mimetype, 'image/') === 0) {
                // Do not cache. Failure might be the result of a misconfiguration, thus real content should be returned once fixed. 
                $content = $RCMAIL->get_resource_content('blocked.gif');
                $OUTPUT->nocacheing_headers();
                header("Content-Type: image/gif");
                header("Content-Transfer-Encoding: binary");
                header("Content-Length: " . strlen($content));
                echo $content;
            }
            else {  // html warning with a button to load the file anyway
                $OUTPUT = new rcmail_html_page();
                $OUTPUT->write(html::tag('html', null, html::tag('body', 'embed',
                    html::div(array('class' => 'rcmail-inline-message rcmail-inline-warning'),
                        $RCMAIL->gettext(array(
                            'name' => 'attachmentvalidationerror',
                            'vars' => array(
                                'expected' => $mimetype . ($file_extension ? " (.$file_extension)" : ''),
                                'detected' => $real_mimetype . ($extensions[0] ? " (.$extensions[0])" : ''),
                            )
                        ))
                        . html::p(array('class' => 'rcmail-inline-buttons'),
                            html::tag('button', array(
                                'onclick' => "location.href='" . $RCMAIL->url(array_merge($_GET, array('_nocheck' => 1))) . "'"
                            ),
                            $RCMAIL->gettext('showanyway'))
                        )
                    ))));
            }

            exit;
        }
    }

    // TIFF/WEBP to JPEG conversion, if needed
    foreach (array('tiff', 'webp') as $type) {
        $img_support = !empty($_SESSION['browser_caps']) && !empty($_SESSION['browser_caps'][$type]);
        if (!empty($_REQUEST['_embed']) && !$img_support
            && $attachment->image_type() == 'image/' . $type
            && rcube_image::is_convertable('image/' . $type)
        ) {
            $convert2jpeg = true;
            $mimetype     = 'image/jpeg';
            break;
        }
    }

    $browser = $RCMAIL->output->browser;
    list($ctype_primary, $ctype_secondary) = explode('/', $mimetype);

    if (!empty($_GET['_download']) && $ctype_primary == 'text') {
        header("Content-Type: text/$ctype_secondary; charset=" . $attachment->charset);
    }
    else {
        header("Content-Type: $mimetype");
        header("Content-Transfer-Encoding: binary");
    }

    // deliver part content
    if ($mimetype == 'text/html' && empty($_GET['_download'])) {
        // Check if we have enough memory to handle the message in it
        // #1487424: we need up to 10x more memory than the body
        if (!rcube_utils::mem_check($attachment->size * 10)) {
            $out = '<html><body>'
                . $RCMAIL->gettext('messagetoobig'). ' '
                . html::a($RCMAIL->url(array_merge($_GET, array('download' => 1))), $RCMAIL->gettext('download'))
                . '</body></html>';
        }
        else {
            // render HTML body
            $out = $attachment->html();

            // insert remote objects warning into HTML body
            if ($REMOTE_OBJECTS) {
                $body_start = 0;
                if ($body_pos = strpos($out, '<body')) {
                    $body_start = strpos($out, '>', $body_pos) + 1;
                }

                $out = substr($out, 0, $body_start)
                    . html::div(array('class' => 'rcmail-inline-message rcmail-inline-warning'),
                        rcube::Q($RCMAIL->gettext('blockedimages')) . '&nbsp;' .
                        html::tag('button',
                            array('onclick' => "location.href='" . $RCMAIL->url(array_merge($_GET, array('_safe' => 1))) . "'"),
                            rcube::Q($RCMAIL->gettext('showimages')))
                    )
                    . substr($out, $body_start);
            }
        }

        $OUTPUT = new rcmail_html_page();
        $OUTPUT->write($out);
        exit;
    }

    // don't kill the connection if download takes some more time
    @set_time_limit(3600);

    $filename    = $browser->ie ? rawurlencode($filename) : addcslashes($filename, '"');
    $disposition = !empty($_GET['_download']) ? 'attachment' : 'inline';

    // add filename extension if missing
    if (!pathinfo($filename, PATHINFO_EXTENSION) && ($extensions = rcube_mime::get_mime_extensions($mimetype))) {
        $filename .= '.' . $extensions[0];
    }

    header("Content-Disposition: $disposition; filename=\"$filename\"");

    // handle tiff to jpeg conversion
    if (!empty($convert2jpeg)) {
        $temp_dir  = unslashify($RCMAIL->config->get('temp_dir'));
        $file_path = tempnam($temp_dir, 'rcmAttmnt');

        // convert image to jpeg and send it to the browser
        if ($attachment->body_to_file($file_path)) {
            $image = new rcube_image($file_path);
            if ($image->convert(rcube_image::TYPE_JPG, $file_path)) {
                header("Content-Length: " . filesize($file_path));
                readfile($file_path);
            }
            unlink($file_path);
        }
    }
    else {
        $attachment->output($mimetype);
    }

    exit;
}

// if we arrive here, the requested part was not found
header('HTTP/1.1 404 Not Found');
exit;


/**
 * Attachment properties table
 */
function rcmail_message_part_controls($attrib)
{
    global $attachment, $RCMAIL;

    if (!$attachment->is_valid()) {
        return '';
    }

    $table = new html_table(array('cols' => 2));

    $table->add('title', rcube::Q($RCMAIL->gettext('namex')).':');
    $table->add('header', rcube::Q($attachment->filename));

    $table->add('title', rcube::Q($RCMAIL->gettext('type')).':');
    $table->add('header', rcube::Q($attachment->mimetype));

    $table->add('title', rcube::Q($RCMAIL->gettext('size')).':');
    $table->add('header', rcube::Q($attachment->size()));

    return $table->show($attrib);
}

/**
 * Attachment preview frame
 */
function rcmail_message_part_frame($attrib)
{
    global $RCMAIL;

    if ($RCMAIL->output->get_env('is_message')) {
        $attrib['src'] = $RCMAIL->url(array(
                'task'   => 'mail',
                'action' => 'preview',
                'uid'    => $RCMAIL->output->get_env('uid'),
                'mbox'   => $RCMAIL->output->get_env('mailbox'),
                'framed' => 1,
        ));
    }
    else {
        $mimetype      = $RCMAIL->output->get_env('mimetype');
        $frame_replace = strpos($mimetype, 'text/') === 0 ? '_embed=' : '_preload=';
        $attrib['src'] = './?' . str_replace('_frame=', $frame_replace, $_SERVER['QUERY_STRING']);
    }

    $RCMAIL->output->add_gui_object('messagepartframe', $attrib['id']);

    return html::iframe($attrib);
}

/**
 * Wrapper class with unified access to attachment properties and body
 *
 * Unified for message parts as well as uploaded attachments
 */
class rcmail_attachment_handler
{
    public $filename;
    public $size;
    public $mimetype;
    public $ident;
    public $charset = RCUBE_CHARSET;

    private $message;
    private $part;
    private $upload;
    private $body;
    private $body_file;
    private $download = false;

    /**
     * Class constructor.
     * Reads request parameters and initializes attachment/part props.
     */
    public function __construct()
    {
        ob_end_clean();

        $part_id    = rcube_utils::get_input_value('_part', rcube_utils::INPUT_GET);
        $file_id    = rcube_utils::get_input_value('_file', rcube_utils::INPUT_GET);
        $compose_id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET);
        $uid        = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GET);
        $rcube      = rcube::get_instance();

        $this->download = !empty($_GET['_download']);

        // similar code as in program/steps/mail/show.inc
        if (!empty($uid)) {
            $rcube->config->set('prefer_html', true);
            $this->message = new rcube_message($uid, null, intval($_GET['_safe']));

            if ($this->part = $this->message->mime_parts[$part_id]) {
                $this->filename = rcmail_attachment_name($this->part);
                $this->mimetype = $this->part->mimetype;
                $this->size     = $this->part->size;
                $this->ident    = $this->message->headers->messageID . ':' . $this->part->mime_id . ':' . $this->size . ':' . $this->mimetype;
                $this->charset  = $this->part->charset ?: RCUBE_CHARSET;

                if (empty($_GET['_frame'])) {
                    // allow post-processing of the attachment body
                    $plugin = $rcube->plugins->exec_hook('message_part_get', array(
                            'uid'      => $uid,
                            'id'       => $this->part->mime_id,
                            'mimetype' => $this->mimetype,
                            'part'     => $this->part,
                            'download' => $this->download,
                    ));

                    if ($plugin['abort']) {
                        exit;
                    }

                    // overwrite modified vars from plugin
                    $this->mimetype = $plugin['mimetype'];

                    if ($plugin['body']) {
                        $this->body = $plugin['body'];
                        $this->size = strlen($this->body);
                    }
                }
            }
        }
        else if ($file_id && $compose_id) {
            $file_id = preg_replace('/^rcmfile/', '', $file_id);

            if (($compose = $_SESSION['compose_data_' . $compose_id])
                && ($this->upload = $compose['attachments'][$file_id])
            ) {
                $this->filename = $this->upload['name'];
                $this->mimetype = $this->upload['mimetype'];
                $this->size     = $this->upload['size'];
                $this->ident    = sprintf('%s:%s%s', $compose_id, $file_id, $this->size);
                $this->charset  = $this->upload['charset'] ?: RCUBE_CHARSET;
            }
        }

        if (empty($this->part) && empty($this->upload)) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        // check connection status
        self::check_storage_status();

        $this->mimetype = rcmail_fix_mimetype($this->mimetype);
    }

    /**
     * Remove temp files, etc.
     */
    public function __destruct()
    {
        if ($this->body_file) {
            @unlink($this->body_file);
        }
    }

    /**
     * Check if the object is a message part not uploaded file
     *
     * @return bool True if the object is a meesage part
     */
    public function is_message_part()
    {
        return !empty($this->message);
    }

    /**
     * Object/request status
     *
     * @return bool Status
     */
    public function is_valid()
    {
        return !empty($this->part) || !empty($this->upload);
    }

    /**
     * Return attachment/part mimetype if this is an image
     * of supported type.
     *
     * @return string Image mimetype
     */
    public function image_type()
    {
        $part = (object) array(
            'filename' => $this->filename,
            'mimetype' => $this->mimetype,
        );

        return rcmail_part_image_type($part);
    }

    /**
     * Formatted attachment/part size (with units)
     *
     * @return string Attachment/part size (with units)
     */
    public function size()
    {
        $part = $this->part ?: ((object) array('size' => $this->size, 'exact_size' => true));
        return rcube::get_instance()->message_part_size($part);
    }

    /**
     * Returns, prints or saves the attachment/part body
     */
    public function body($size = null, $fp = null)
    {
        // we may have the body in memory or file already
        if ($this->body !== null) {
            if ($fp == -1) {
                echo $size ? substr($this->body, 0, $size) : $this->body;
            }
            else if ($fp) {
                $result = fwrite($fp, $size ? substr($this->body, $size) : $this->body) !== false;
            }
            else {
                $result = $size ? substr($this->body, 0, $size) : $this->body;
            }
        }
        else if ($this->body_file) {
            if ($size) {
                $result = file_get_contents($this->body_file, false, null, 0, $size);
            }
            else {
                $result = file_get_contents($this->body_file);
            }

            if ($fp == -1) {
                echo $result;
            }
            else if ($fp) {
                $result = fwrite($fp, $result) !== false;
            }
        }
        else if ($this->message) {
            $result = $this->message->get_part_body($this->part->mime_id, false, 0, $fp);

            // check connection status
            if (!$fp && $this->size && empty($result)) {
                self::check_storage_status();
            }
        }
        else if ($this->upload) {
            // This hook retrieves the attachment contents from the file storage backend
            $attachment = rcube::get_instance()->plugins->exec_hook('attachment_get', $this->upload);

            if ($fp && $fp != -1) {
                if ($attachment['data']) {
                    $result = fwrite($fp, $size ? substr($attachment['data'], 0, $size) : $attachment['data']) !== false;
                }
                else if ($attachment['path']) {
                    if ($fh = fopen($attachment['path'], 'rb')) {
                        $result = stream_copy_to_stream($fh, $fp, $size ? $size : -1);
                    }
                }
            }
            else {
                $data = $attachment['data'];
                if (!$data && $attachment['path']) {
                    $data = file_get_contents($attachment['path']);
                }

                if ($fp == -1) {
                    echo $size ? substr($data, 0, $size) : $data;
                }
                else {
                    $result = $size ? substr($data, 0, $size) : $data;
                }
            }
        }

        return $result;
    }

    /**
     * Save the body to a file
     *
     * @param string $filename File name with path
     *
     * @return bool True on success, False on failure
     */
    public function body_to_file($filename)
    {
        if ($filename && $this->size && ($fp = fopen($filename, 'w'))) {
            $this->body(0, $fp);
            $this->body_file = $filename;
            fclose($fp);

            return true;
        }

        return false;
    }

    /**
     * Output attachment body with content filtering
     */
    public function output($mimetype)
    {
        if (!$this->size) {
            return false;
        }

        $secure = stripos($mimetype, 'image/') === false || $this->download;

        // Remove <script> in SVG images
        if (!$secure && stripos($mimetype, 'image/svg') === 0) {
            if (!$this->body) {
                $this->body = $this->body();
                if (empty($this->body)) {
                    return false;
                }
            }

            echo self::svg_filter($this->body);
            return true;
        }

        if ($this->body !== null && !$this->download) {
            header("Content-Length: " . strlen($this->body));
            echo $this->body;
            return true;
        }

        // Don't be tempted to set Content-Length to $part->d_parameters['size'] (#1490482)
        // RFC2183 says "The size parameter indicates an approximate size"

        return $this->body(0, -1);
    }

    /**
     * Returns formatted HTML if the attachment is HTML
     */
    public function html()
    {
        list($type, $subtype) = explode($this->mimetype, '/');
        $part = (object) array(
            'charset'         => $this->charset,
            'ctype_secondary' => $subtype,
        );

        // get part body if not available
        // fix formatting and charset
        $body = rcube_message::format_part_body($this->body(), $part);

        // show images?
        $is_safe = $this->is_safe();

        return rcmail_wash_html($body, array('safe' => $is_safe, 'inline_html' => false));
    }

    /**
     * Remove <script> in SVG images
     */
    public static function svg_filter($body)
    {
        // clean SVG with washtml
        $wash_opts = array(
            'show_washed'   => false,
            'allow_remote'  => false,
            'charset'       => RCUBE_CHARSET,
            'html_elements' => array('title'),
//            'blocked_src'   => 'program/resources/blocked.gif',
        );

        // initialize HTML washer
        $washer = new rcube_washtml($wash_opts);

        // allow CSS styles, will be sanitized by rcmail_washtml_callback()
        $washer->add_callback('style', 'rcmail_washtml_callback');

        return $washer->wash($body);
    }

    /**
     * Handles nicely storage connection errors
     */
    public static function check_storage_status()
    {
        $error = rcmail::get_instance()->storage->get_error_code();

        // Check if we have a connection error
        if ($error == rcube_imap_generic::ERROR_BAD) {
            ob_end_clean();

            // Get action is often executed simultanously.
            // Some servers have MAXPERIP or other limits.
            // To workaround this we'll wait for some time
            // and try again (once).
            // Note: Random sleep interval is used to minimize concurency
            // in getting message parts

            if (!isset($_GET['_redirected'])) {
                usleep(rand(10,30)*100000); // 1-3 sec.
                header('Location: ' . $_SERVER['REQUEST_URI'] . '&_redirected=1');
            }
            else {
                rcube::raise_error(array(
                    'code' => 500, 'file' => __FILE__, 'line' => __LINE__,
                    'message' => 'Unable to get/display message part. IMAP connection error'),
                    true, true);
            }

            // Don't kill session, just quit (#1486995)
            exit;
        }
    }

    public function is_safe()
    {
        if ($this->message) {
            return rcmail_check_safe($this->message);
        }

        return !empty($_GET['_safe']);
    }
}
