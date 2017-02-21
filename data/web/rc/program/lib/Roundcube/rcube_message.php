<?php

/**
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2008-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Logical representation of a mail message with all its data          |
 |   and related functions                                               |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

/**
 * Logical representation of a mail message with all its data
 * and related functions
 *
 * @package    Framework
 * @subpackage Storage
 * @author     Thomas Bruederli <roundcube@gmail.com>
 */
class rcube_message
{
    /**
     * Instace of framework class.
     *
     * @var rcube
     */
    private $app;

    /**
     * Instance of storage class
     *
     * @var rcube_storage
     */
    private $storage;

    /**
     * Instance of mime class
     *
     * @var rcube_mime
     */
    private $mime;
    private $opt = array();
    private $parse_alternative = false;

    public $uid;
    public $folder;
    public $headers;
    public $sender;
    public $context;
    public $parts        = array();
    public $mime_parts   = array();
    public $inline_parts = array();
    public $attachments  = array();
    public $subject      = '';
    public $is_safe      = false;

    const BODY_MAX_SIZE = 1048576; // 1MB


    /**
     * __construct
     *
     * Provide a uid, and parse message structure.
     *
     * @param string $uid     The message UID.
     * @param string $folder  Folder name
     * @param bool   $is_safe Security flag
     *
     * @see self::$app, self::$storage, self::$opt, self::$parts
     */
    function __construct($uid, $folder = null, $is_safe = false)
    {
        // decode combined UID-folder identifier
        if (preg_match('/^[0-9.]+-.+/', $uid)) {
            list($uid, $folder) = explode('-', $uid, 2);
        }

        if (preg_match('/^([0-9]+)\.([0-9.]+)$/', $uid, $matches)) {
            $uid     = $matches[1];
            $context = $matches[2];
        }

        $this->uid     = $uid;
        $this->context = $context;
        $this->app     = rcube::get_instance();
        $this->storage = $this->app->get_storage();
        $this->folder  = strlen($folder) ? $folder : $this->storage->get_folder();

        // Set current folder
        $this->storage->set_folder($this->folder);
        $this->storage->set_options(array('all_headers' => true));

        $this->headers = $this->storage->get_message($uid);

        if (!$this->headers) {
            return;
        }

        $this->set_safe($is_safe || $_SESSION['safe_messages'][$this->folder.':'.$uid]);
        $this->opt = array(
            'safe'        => $this->is_safe,
            'prefer_html' => $this->app->config->get('prefer_html'),
            'get_url'     => $this->app->url(array(
                    'action' => 'get',
                    'mbox'   => $this->folder,
                    'uid'    => $uid),
                false, false, true)
        );

        if (!empty($this->headers->structure)) {
            $this->get_mime_numbers($this->headers->structure);
            $this->parse_structure($this->headers->structure);
        }
        else if ($this->context === null) {
            $this->body = $this->storage->get_body($uid);
        }

        $this->mime    = new rcube_mime($this->headers->charset);
        $this->subject = $this->headers->get('subject');
        list(, $this->sender) = each($this->mime->decode_address_list($this->headers->from, 1));

        // notify plugins and let them analyze this structured message object
        $this->app->plugins->exec_hook('message_load', array('object' => $this));
    }

    /**
     * Return a (decoded) message header
     *
     * @param string $name Header name
     * @param bool   $row  Don't mime-decode the value
     * @return string Header value
     */
    public function get_header($name, $raw = false)
    {
        if (empty($this->headers)) {
            return null;
        }

        return $this->headers->get($name, !$raw);
    }

    /**
     * Set is_safe var and session data
     *
     * @param bool $safe enable/disable
     */
    public function set_safe($safe = true)
    {
        $_SESSION['safe_messages'][$this->folder.':'.$this->uid] = $this->is_safe = $safe;
    }

    /**
     * Compose a valid URL for getting a message part
     *
     * @param string $mime_id Part MIME-ID
     * @param mixed  $embed Mimetype class for parts to be embedded
     * @return string URL or false if part does not exist
     */
    public function get_part_url($mime_id, $embed = false)
    {
        if ($this->mime_parts[$mime_id])
            return $this->opt['get_url'] . '&_part=' . $mime_id . ($embed ? '&_embed=1&_mimeclass=' . $embed : '');
        else
            return false;
    }

    /**
     * Get content of a specific part of this message
     *
     * @param string   $mime_id           Part MIME-ID
     * @param resource $fp                File pointer to save the message part
     * @param boolean  $skip_charset_conv Disables charset conversion
     * @param int      $max_bytes         Only read this number of bytes
     * @param boolean  $formatted         Enables formatting of text/* parts bodies
     *
     * @return string Part content
     * @deprecated
     */
    public function get_part_content($mime_id, $fp = null, $skip_charset_conv = false, $max_bytes = 0, $formatted = true)
    {
        if ($part = $this->mime_parts[$mime_id]) {
            // stored in message structure (winmail/inline-uuencode)
            if (!empty($part->body) || $part->encoding == 'stream') {
                if ($fp) {
                    fwrite($fp, $part->body);
                }
                return $fp ? true : $part->body;
            }

            // get from IMAP
            $this->storage->set_folder($this->folder);

            return $this->storage->get_message_part($this->uid, $mime_id, $part,
                NULL, $fp, $skip_charset_conv, $max_bytes, $formatted);
        }
    }

    /**
     * Get content of a specific part of this message
     *
     * @param string  $mime_id   Part ID
     * @param boolean $formatted Enables formatting of text/* parts bodies
     * @param int     $max_bytes Only return/read this number of bytes
     * @param mixed   $mode      NULL to return a string, -1 to print body
     *                           or file pointer to save the body into
     *
     * @return string|bool Part content or operation status
     */
    public function get_part_body($mime_id, $formatted = false, $max_bytes = 0, $mode = null)
    {
        if (!($part = $this->mime_parts[$mime_id])) {
            return;
        }

        // allow plugins to modify part body
        $plugin = $this->app->plugins->exec_hook('message_part_body',
            array('object' => $this, 'part' => $part));

        // only text parts can be formatted
        $formatted = $formatted && $part->ctype_primary == 'text';

        // part body not fetched yet... save in memory if it's small enough
        if ($part->body === null && is_numeric($mime_id) && $part->size < self::BODY_MAX_SIZE) {
            $this->storage->set_folder($this->folder);
            // Warning: body here should be always unformatted
            $part->body = $this->storage->get_message_part($this->uid, $mime_id, $part,
                null, null, true, 0, false);
        }

        // body stored in message structure (winmail/inline-uuencode)
        if ($part->body !== null || $part->encoding == 'stream') {
            $body = $part->body;

            if ($formatted && $body) {
                $body = self::format_part_body($body, $part, $this->headers->charset);
            }

            if ($max_bytes && strlen($body) > $max_bytes) {
                $body = substr($body, 0, $max_bytes);
            }

            if (is_resource($mode)) {
                if ($body !== false) {
                    fwrite($mode, $body);
                    rewind($mode);
                }

                return $body !== false;
            }

            if ($mode === -1) {
                if ($body !== false) {
                    print($body);
                }

                return $body !== false;
            }

            return $body;
        }

        // get the body from IMAP
        $this->storage->set_folder($this->folder);

        $body = $this->storage->get_message_part($this->uid, $mime_id, $part,
            $mode === -1, is_resource($mode) ? $mode : null,
            !($mode && $formatted), $max_bytes, $mode && $formatted);

        if (is_resource($mode)) {
            rewind($mode);
            return $body !== false;
        }

        if (!$mode && $body && $formatted) {
            $body = self::format_part_body($body, $part, $this->headers->charset);
        }

        return $body;
    }

    /**
     * Format text message part for display
     *
     * @param string             $body            Part body
     * @param rcube_message_part $part            Part object
     * @param string             $default_charset Fallback charset if part charset is not specified
     *
     * @return string Formatted body
     */
    public static function format_part_body($body, $part, $default_charset = null)
    {
        // remove useless characters
        $body = preg_replace('/[\t\r\0\x0B]+\n/', "\n", $body);

        // remove NULL characters if any (#1486189)
        if (strpos($body, "\x00") !== false) {
            $body = str_replace("\x00", '', $body);
        }

        // detect charset...
        if (!$part->charset || strtoupper($part->charset) == 'US-ASCII') {
            // try to extract charset information from HTML meta tag (#1488125)
            if ($part->ctype_secondary == 'html' && preg_match('/<meta[^>]+charset=([a-z0-9-_]+)/i', $body, $m)) {
                $part->charset = strtoupper($m[1]);
            }
            else if ($default_charset) {
                $part->charset = $default_charset;
            }
            else {
                $rcube         = rcube::get_instance();
                $part->charset = $rcube->config->get('default_charset', RCUBE_CHARSET);
            }
        }

        // ..convert charset encoding
        $body = rcube_charset::convert($body, $part->charset);

        return $body;
    }

    /**
     * Determine if the message contains a HTML part. This must to be
     * a real part not an attachment (or its part)
     *
     * @param bool               $enriched Enables checking for text/enriched parts too
     * @param rcube_message_part &$part    Reference to the part if found
     *
     * @return bool True if a HTML is available, False if not
     */
    public function has_html_part($enriched = false, &$part = null)
    {
        // check all message parts
        foreach ($this->mime_parts as $part) {
            if ($part->mimetype == 'text/html' || ($enriched && $part->mimetype == 'text/enriched')) {
                // Skip if part is an attachment, don't use is_attachment() here
                if ($part->filename) {
                    continue;
                }

                if (!$part->size) {
                    continue;
                }

                if (!$this->check_context($part)) {
                    continue;
                }

                $level = explode('.', $part->mime_id);
                $depth = count($level);
                $last  = '';

                // Check if the part belongs to higher-level's multipart part
                // this can be alternative/related/signed/encrypted or mixed
                while (array_pop($level) !== null) {
                    $parent_depth = count($level);
                    if (!$parent_depth) {
                        return true;
                    }

                    $parent = $this->mime_parts[join('.', $level)];

                    if (!$this->check_context($parent)) {
                        return true;
                    }

                    $max_delta = $depth - (1 + ($last == 'multipart/alternative' ? 1 : 0));
                    $last      = $parent->real_mimetype ?: $parent->mimetype;

                    if (!preg_match('/^multipart\/(alternative|related|signed|encrypted|mixed)$/', $last)
                        || ($last == 'multipart/mixed' && $parent_depth < $max_delta)) {
                        continue 2;
                    }
                }

                return true;
            }
        }

        $part = null;

        return false;
    }

    /**
     * Determine if the message contains a text/plain part. This must to be
     * a real part not an attachment (or its part)
     *
     * @param rcube_message_part &$part Reference to the part if found
     *
     * @return bool True if a plain text part is available, False if not
     */
    public function has_text_part(&$part = null)
    {
        // check all message parts
        foreach ($this->mime_parts as $part) {
            if ($part->mimetype == 'text/plain') {
                // Skip if part is an attachment, don't use is_attachment() here
                if ($part->filename) {
                    continue;
                }

                if (!$part->size) {
                    continue;
                }

                if (!$this->check_context($part)) {
                    continue;
                }

                $level = explode('.', $part->mime_id);

                // Check if the part belongs to higher-level's alternative/related
                while (array_pop($level) !== null) {
                    if (!count($level)) {
                        return true;
                    }

                    $parent = $this->mime_parts[join('.', $level)];

                    if (!$this->check_context($parent)) {
                        return true;
                    }

                    if ($parent->mimetype != 'multipart/alternative' && $parent->mimetype != 'multipart/related') {
                        continue 2;
                    }
                }

                return true;
            }
        }

        $part = null;

        return false;
    }

    /**
     * Return the first HTML part of this message
     *
     * @param rcube_message_part &$part    Reference to the part if found
     * @param bool               $enriched Enables checking for text/enriched parts too
     *
     * @return string HTML message part content
     */
    public function first_html_part(&$part = null, $enriched = false)
    {
        if ($this->has_html_part($enriched, $part)) {
            $body = $this->get_part_body($part->mime_id, true);

            if ($part->mimetype == 'text/enriched') {
                $body = rcube_enriched::to_html($body);
            }

            return $body;
        }
    }

    /**
     * Return the first text part of this message.
     * If there's no text/plain part but $strict=true and text/html part
     * exists, it will be returned in text/plain format.
     *
     * @param rcube_message_part &$part  Reference to the part if found
     * @param bool               $strict Check only text/plain parts
     *
     * @return string Plain text message/part content
     */
    public function first_text_part(&$part = null, $strict = false)
    {
        // no message structure, return complete body
        if (empty($this->parts)) {
            return $this->body;
        }

        if ($this->has_text_part($part)) {
            return $this->get_part_body($part->mime_id, true);
        }

        if (!$strict && ($body = $this->first_html_part($part, true))) {
            // create instance of html2text class
            $h2t  = new rcube_html2text($body);
            return $h2t->get_text();
        }
    }

    /**
     * Return message parts in current context
     */
    public function mime_parts()
    {
        if ($this->context === null) {
            return $this->mime_parts;
        }

        $parts = array();

        foreach ($this->mime_parts as $part_id => $part) {
            if ($this->check_context($part)) {
                $parts[$part_id] = $part;
            }
        }

        return $parts;
    }

    /**
     * Checks if part of the message is an attachment (or part of it)
     *
     * @param rcube_message_part $part Message part
     *
     * @return bool True if the part is an attachment part
     */
    public function is_attachment($part)
    {
        foreach ($this->attachments as $att_part) {
            if ($att_part->mime_id == $part->mime_id) {
                return true;
            }

            // check if the part is a subpart of another attachment part (message/rfc822)
            if ($att_part->mimetype == 'message/rfc822') {
                if (in_array($part, (array)$att_part->parts)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * In a multipart/encrypted encrypted message,
     * find the encrypted message payload part.
     *
     * @return rcube_message_part
     */
    public function get_multipart_encrypted_part()
    {
        foreach ($this->mime_parts as $mime_id => $mpart) {
            if ($mpart->mimetype == 'multipart/encrypted') {
                $this->pgp_mime = true;
            }
            if ($this->pgp_mime && ($mpart->mimetype == 'application/octet-stream' ||
                    (!empty($mpart->filename) && $mpart->filename != 'version.txt'))) {
                $this->encrypted_part = $mime_id;
                return $mpart;
            }
        }

        return false;
    }

    /**
     * Read the message structure returend by the IMAP server
     * and build flat lists of content parts and attachments
     *
     * @param rcube_message_part $structure Message structure node
     * @param bool               $recursive True when called recursively
     */
    private function parse_structure($structure, $recursive = false)
    {
        // real content-type of message/rfc822 part
        if ($structure->mimetype == 'message/rfc822' && $structure->real_mimetype) {
            $mimetype = $structure->real_mimetype;

            // parse headers from message/rfc822 part
            if (!isset($structure->headers['subject']) && !isset($structure->headers['from'])) {
                list($headers, ) = explode("\r\n\r\n", $this->get_part_body($structure->mime_id, false, 32768));
                $structure->headers = rcube_mime::parse_headers($headers);

                if ($this->context == $structure->mime_id) {
                    $this->headers = rcube_message_header::from_array($structure->headers);
                }
            }
        }
        else {
            $mimetype = $structure->mimetype;
        }

        // show message headers
        if ($recursive && is_array($structure->headers) &&
            (isset($structure->headers['subject']) || $structure->headers['from'] || $structure->headers['to'])
        ) {
            $c = new stdClass;
            $c->type = 'headers';
            $c->headers = $structure->headers;
            $this->add_part($c);
        }

        // Allow plugins to handle message parts
        $plugin = $this->app->plugins->exec_hook('message_part_structure',
            array('object' => $this, 'structure' => $structure,
                'mimetype' => $mimetype, 'recursive' => $recursive));

        if ($plugin['abort']) {
            return;
        }

        $structure = $plugin['structure'];
        $mimetype  = $plugin['mimetype'];
        $recursive = $plugin['recursive'];

        list($message_ctype_primary, $message_ctype_secondary) = explode('/', $mimetype);

        // print body if message doesn't have multiple parts
        if ($message_ctype_primary == 'text' && !$recursive) {
            // parts with unsupported type add to attachments list
            if (!in_array($message_ctype_secondary, array('plain', 'html', 'enriched'))) {
                $this->add_part($structure, 'attachment');
                return;
            }

            $structure->type = 'content';
            $this->add_part($structure);

            // Parse simple (plain text) message body
            if ($message_ctype_secondary == 'plain') {
                foreach ((array)$this->uu_decode($structure) as $uupart) {
                    $this->mime_parts[$uupart->mime_id] = $uupart;
                    $this->add_part($uupart, 'attachment');
                }
            }
        }
        // the same for pgp signed messages
        else if ($mimetype == 'application/pgp' && !$recursive) {
            $structure->type = 'content';
            $this->add_part($structure);
        }
        // message contains (more than one!) alternative parts
        else if ($mimetype == 'multipart/alternative'
            && is_array($structure->parts) && count($structure->parts) > 1
        ) {
            // get html/plaintext parts, other add to attachments list
            foreach ($structure->parts as $p => $sub_part) {
                $sub_mimetype = $sub_part->mimetype;
                $is_multipart = preg_match('/^multipart\/(related|relative|mixed|alternative)/', $sub_mimetype);

                // skip empty text parts
                if (!$sub_part->size && !$is_multipart) {
                    continue;
                }

                // We've encountered (malformed) messages with more than
                // one text/plain or text/html part here. There's no way to choose
                // which one is better, so we'll display first of them and add
                // others as attachments (#1489358)

                // check if sub part is
                if ($is_multipart)
                    $related_part = $p;
                else if ($sub_mimetype == 'text/plain' && !$plain_part)
                    $plain_part = $p;
                else if ($sub_mimetype == 'text/html' && !$html_part) {
                    $html_part = $p;
                    $this->got_html_part = true;
                }
                else if ($sub_mimetype == 'text/enriched' && !$enriched_part)
                    $enriched_part = $p;
                else {
                    // add unsupported/unrecognized parts to attachments list
                    $this->add_part($sub_part, 'attachment');
                }
            }

            // parse related part (alternative part could be in here)
            if ($related_part !== null && !$this->parse_alternative) {
                $this->parse_alternative = true;
                $this->parse_structure($structure->parts[$related_part], true);
                $this->parse_alternative = false;

                // if plain part was found, we should unset it if html is preferred
                if ($this->opt['prefer_html'] && count($this->parts))
                    $plain_part = null;
            }

            // choose html/plain part to print
            if ($html_part !== null && $this->opt['prefer_html']) {
                $print_part = $structure->parts[$html_part];
            }
            else if ($enriched_part !== null) {
                $print_part = $structure->parts[$enriched_part];
            }
            else if ($plain_part !== null) {
                $print_part = $structure->parts[$plain_part];
            }

            // add the right message body
            if (is_object($print_part)) {
                $print_part->type = 'content';
                $this->add_part($print_part);
            }
            // show plaintext warning
            else if ($html_part !== null && empty($this->parts)) {
                $c = new stdClass;
                $c->type            = 'content';
                $c->ctype_primary   = 'text';
                $c->ctype_secondary = 'plain';
                $c->mimetype        = 'text/plain';
                $c->realtype        = 'text/html';

                $this->add_part($c);
            }
        }
        // this is an ecrypted message -> create a plaintext body with the according message
        else if ($mimetype == 'multipart/encrypted') {
            $p = new stdClass;
            $p->type            = 'content';
            $p->ctype_primary   = 'text';
            $p->ctype_secondary = 'plain';
            $p->mimetype        = 'text/plain';
            $p->realtype        = 'multipart/encrypted';
            $p->mime_id         = $structure->mime_id;

            $this->add_part($p);

            // add encrypted payload part as attachment
            if (is_array($structure->parts)) {
                for ($i=0; $i < count($structure->parts); $i++) {
                    $subpart = $structure->parts[$i];
                    if ($subpart->mimetype == 'application/octet-stream' || !empty($subpart->filename)) {
                        $this->add_part($subpart, 'attachment');
                    }
                }
            }
        }
        // this is an S/MIME ecrypted message -> create a plaintext body with the according message
        else if ($mimetype == 'application/pkcs7-mime') {
            $p = new stdClass;
            $p->type            = 'content';
            $p->ctype_primary   = 'text';
            $p->ctype_secondary = 'plain';
            $p->mimetype        = 'text/plain';
            $p->realtype        = 'application/pkcs7-mime';
            $p->mime_id         = $structure->mime_id;

            $this->add_part($p);

            if (!empty($structure->filename)) {
                $this->add_part($structure, 'attachment');
            }
        }
        // message contains multiple parts
        else if (is_array($structure->parts) && !empty($structure->parts)) {
            // iterate over parts
            for ($i=0; $i < count($structure->parts); $i++) {
                $mail_part      = &$structure->parts[$i];
                $primary_type   = $mail_part->ctype_primary;
                $secondary_type = $mail_part->ctype_secondary;
                $part_mimetype  = $mail_part->mimetype;

                // multipart/alternative or message/rfc822
                if ($primary_type == 'multipart' || $part_mimetype == 'message/rfc822') {
                    $this->parse_structure($mail_part, true);

                    // list message/rfc822 as attachment as well (mostly .eml)
                    if ($primary_type == 'message' && !empty($mail_part->filename)) {
                        $this->add_part($mail_part, 'attachment');
                    }
                }
                // part text/[plain|html] or delivery status
                else if ((($part_mimetype == 'text/plain' || $part_mimetype == 'text/html') && $mail_part->disposition != 'attachment') ||
                    in_array($part_mimetype, array('message/delivery-status', 'text/rfc822-headers', 'message/disposition-notification'))
                ) {
                    // Allow plugins to handle also this part
                    $plugin = $this->app->plugins->exec_hook('message_part_structure',
                        array('object' => $this, 'structure' => $mail_part,
                            'mimetype' => $part_mimetype, 'recursive' => true));

                    if ($plugin['abort']) {
                        continue;
                    }

                    if ($part_mimetype == 'text/html' && $mail_part->size) {
                        $this->got_html_part = true;
                    }

                    $mail_part = $plugin['structure'];
                    list($primary_type, $secondary_type) = explode('/', $plugin['mimetype']);

                    // add text part if it matches the prefs
                    if (!$this->parse_alternative ||
                        ($secondary_type == 'html' && $this->opt['prefer_html']) ||
                        ($secondary_type == 'plain' && !$this->opt['prefer_html'])
                    ) {
                        $mail_part->type = 'content';
                        $this->add_part($mail_part);
                    }

                    // list as attachment as well
                    if (!empty($mail_part->filename)) {
                        $this->add_part($mail_part, 'attachment');
                    }
                }
                // ignore "virtual" protocol parts
                else if ($primary_type == 'protocol') {
                    continue;
                }
                // part is Microsoft Outlook TNEF (winmail.dat)
                else if ($part_mimetype == 'application/ms-tnef') {
                    $tnef_parts = (array) $this->tnef_decode($mail_part);
                    foreach ($tnef_parts as $tpart) {
                        $this->mime_parts[$tpart->mime_id] = $tpart;
                        $this->add_part($tpart, 'attachment');
                    }

                    // add winmail.dat to the list if it's content is unknown
                    if (empty($tnef_parts) && !empty($mail_part->filename)) {
                        $this->mime_parts[$mail_part->mime_id] = $mail_part;
                        $this->add_part($mail_part, 'attachment');
                    }
                }
                // part is a file/attachment
                else if (preg_match('/^(inline|attach)/', $mail_part->disposition) ||
                    $mail_part->headers['content-id'] ||
                    ($mail_part->filename &&
                        (empty($mail_part->disposition) || preg_match('/^[a-z0-9!#$&.+^_-]+$/i', $mail_part->disposition)))
                ) {
                    // skip apple resource forks
                    if ($message_ctype_secondary == 'appledouble' && $secondary_type == 'applefile')
                        continue;

                    // part belongs to a related message and is linked
                    if (preg_match('/^multipart\/(related|relative)/', $mimetype)
                        && ($mail_part->headers['content-id'] || $mail_part->headers['content-location'])
                    ) {
                        if ($mail_part->headers['content-id'])
                            $mail_part->content_id = preg_replace(array('/^</', '/>$/'), '', $mail_part->headers['content-id']);
                        if ($mail_part->headers['content-location'])
                            $mail_part->content_location = $mail_part->headers['content-base'] . $mail_part->headers['content-location'];

                        $this->add_part($mail_part, 'inline');
                    }
                    // regular attachment with valid content type
                    // (content-type name regexp according to RFC4288.4.2)
                    else if (preg_match('/^[a-z0-9!#$&.+^_-]+\/[a-z0-9!#$&.+^_-]+$/i', $part_mimetype)) {
                        $this->add_part($mail_part, 'attachment');
                    }
                    // attachment with invalid content type
                    // replace malformed content type with application/octet-stream (#1487767)
                    else if ($mail_part->filename) {
                        $mail_part->ctype_primary   = 'application';
                        $mail_part->ctype_secondary = 'octet-stream';
                        $mail_part->mimetype        = 'application/octet-stream';

                        $this->add_part($mail_part, 'attachment');
                    }
                }
                // calendar part not marked as attachment (#1490325)
                else if ($part_mimetype == 'text/calendar') {
                    if (!$mail_part->filename) {
                        $mail_part->filename = 'calendar.ics';
                    }

                    $this->add_part($mail_part, 'attachment');
                }
            }

            // if this was a related part try to resolve references
            if (preg_match('/^multipart\/(related|relative)/', $mimetype) && sizeof($this->inline_parts)) {
                $a_replaces = array();
                $img_regexp = '/^image\/(gif|jpe?g|png|tiff|bmp|svg)/';

                foreach ($this->inline_parts as $inline_object) {
                    $part_url = $this->get_part_url($inline_object->mime_id, $inline_object->ctype_primary);
                    if (isset($inline_object->content_id))
                        $a_replaces['cid:'.$inline_object->content_id] = $part_url;
                    if ($inline_object->content_location) {
                        $a_replaces[$inline_object->content_location] = $part_url;
                    }

                    if (!empty($inline_object->filename)) {
                        // MS Outlook sends sometimes non-related attachments as related
                        // In this case multipart/related message has only one text part
                        // We'll add all such attachments to the attachments list
                        if (!isset($this->got_html_part)) {
                            $this->add_part($inline_object, 'attachment');
                        }
                        // MS Outlook sometimes also adds non-image attachments as related
                        // We'll add all such attachments to the attachments list
                        // Warning: some browsers support pdf in <img/>
                        else if (!preg_match($img_regexp, $inline_object->mimetype)) {
                            $this->add_part($inline_object, 'attachment');
                        }
                        // @TODO: we should fetch HTML body and find attachment's content-id
                        // to handle also image attachments without reference in the body
                        // @TODO: should we list all image attachments in text mode?
                    }
                }

                // add replace array to each content part
                // (will be applied later when part body is available)
                foreach ($this->parts as $i => $part) {
                    if ($part->type == 'content')
                        $this->parts[$i]->replaces = $a_replaces;
                }
            }
        }
        // message is a single part non-text
        else if ($structure->filename || preg_match('/^application\//i', $mimetype)) {
            $this->add_part($structure, 'attachment');
        }
    }

    /**
     * Fill a flat array with references to all parts, indexed by part numbers
     *
     * @param rcube_message_part $part Message body structure
     */
    private function get_mime_numbers(&$part)
    {
        if (strlen($part->mime_id))
            $this->mime_parts[$part->mime_id] = &$part;

        if (is_array($part->parts))
            for ($i=0; $i<count($part->parts); $i++)
                $this->get_mime_numbers($part->parts[$i]);
    }

    /**
     * Add a part to object parts array(s) (with context check)
     */
    private function add_part($part, $type = null)
    {
        if ($this->check_context($part)) {
            switch ($type) {
                case 'inline': $this->inline_parts[] = $part; break;
                case 'attachment': $this->attachments[] = $part; break;
                default: $this->parts[] = $part; break;
            }
        }
    }

    /**
     * Check if specified part belongs to the current context
     */
    private function check_context($part)
    {
        return $this->context === null || strpos($part->mime_id, $this->context . '.') === 0;
    }

    /**
     * Decode a Microsoft Outlook TNEF part (winmail.dat)
     *
     * @param rcube_message_part $part Message part to decode
     * @return array
     */
    function tnef_decode(&$part)
    {
        // @TODO: attachment may be huge, handle body via file
        $body     = $this->get_part_body($part->mime_id);
        $tnef     = new rcube_tnef_decoder;
        $tnef_arr = $tnef->decompress($body);
        $parts    = array();

        unset($body);

        foreach ($tnef_arr as $pid => $winatt) {
            $tpart = new rcube_message_part;

            $tpart->filename        = $this->fix_attachment_name(trim($winatt['name']), $part);
            $tpart->encoding        = 'stream';
            $tpart->ctype_primary   = trim(strtolower($winatt['type']));
            $tpart->ctype_secondary = trim(strtolower($winatt['subtype']));
            $tpart->mimetype        = $tpart->ctype_primary . '/' . $tpart->ctype_secondary;
            $tpart->mime_id         = 'winmail.' . $part->mime_id . '.' . $pid;
            $tpart->size            = $winatt['size'];
            $tpart->body            = $winatt['stream'];

            $parts[] = $tpart;
            unset($tnef_arr[$pid]);
        }

        return $parts;
    }

    /**
     * Parse message body for UUencoded attachments bodies
     *
     * @param rcube_message_part $part Message part to decode
     * @return array
     */
    function uu_decode(&$part)
    {
        // @TODO: messages may be huge, handle body via file
        $part->body = $this->get_part_body($part->mime_id);
        $parts      = array();
        $pid        = 0;

        // FIXME: line length is max.65?
        $uu_regexp_begin = '/begin [0-7]{3,4} ([^\r\n]+)\r?\n/s';
        $uu_regexp_end   = '/`\r?\nend((\r?\n)|($))/s';

        while (preg_match($uu_regexp_begin, $part->body, $matches, PREG_OFFSET_CAPTURE)) {
            $startpos = $matches[0][1];

            if (!preg_match($uu_regexp_end, $part->body, $m, PREG_OFFSET_CAPTURE, $startpos)) {
                break;
            }

            $endpos    = $m[0][1];
            $begin_len = strlen($matches[0][0]);
            $end_len   = strlen($m[0][0]);

            // extract attachment body
            $filebody = substr($part->body, $startpos + $begin_len, $endpos - $startpos - $begin_len - 1);
            $filebody = str_replace("\r\n", "\n", $filebody);

            // remove attachment body from the message body
            $part->body = substr_replace($part->body, '', $startpos, $endpos + $end_len - $startpos);
            // mark body as modified so it will not be cached by rcube_imap_cache
            $part->body_modified = true;

            // add attachments to the structure
            $uupart = new rcube_message_part;
            $uupart->filename = trim($matches[1][0]);
            $uupart->encoding = 'stream';
            $uupart->body     = convert_uudecode($filebody);
            $uupart->size     = strlen($uupart->body);
            $uupart->mime_id  = 'uu.' . $part->mime_id . '.' . $pid;

            $ctype = rcube_mime::file_content_type($uupart->body, $uupart->filename, 'application/octet-stream', true);
            $uupart->mimetype = $ctype;
            list($uupart->ctype_primary, $uupart->ctype_secondary) = explode('/', $ctype);

            $parts[] = $uupart;
            $pid++;
        }

        return $parts;
    }

    /**
     * Fix attachment name encoding if needed/possible
     */
    protected function fix_attachment_name($name, $part)
    {
        if ($name == rcube_charset::clean($name)) {
            return $name;
        }

        // find charset from part or its parent(s)
        if ($part->charset) {
            $charsets[] = $part->charset;
        }
        else {
            // check first part (common case)
            $n = strpos($part->mime_id, '.') ? preg_replace('/\.[0-9]+$/', '', $part->mime_id) . '.1' : 1;
            if (($_part = $this->mime_parts[$n]) && $_part->charset) {
                $charsets[] = $_part->charset;
            }

            // check parents' charset
            $items = explode('.', $part->mime_id);
            for ($i = count($items)-1; $i > 0; $i--) {
                $last   = array_pop($items);
                $parent = $this->mime_parts[join('.', $items)];

                if ($parent && $parent->charset) {
                    $charsets[] = $parent->charset;
                }
            }
        }

        if ($this->headers->charset) {
            $charsets[] = $this->headers->charset;
        }

        if (empty($charsets)) {
            $rcube      = rcube::get_instance();
            $charsets[] = rcube_charset::detect($name, $rcube->config->get('default_charset', RCUBE_CHARSET));
        }

        foreach (array_unique($charsets) as $charset) {
            $_name = rcube_charset::convert($name, $charset);

            if ($_name == rcube_charset::clean($_name)) {
                if (!$part->charset) {
                    $part->charset = $charset;
                }

                return $_name;
            }
        }

        return $name;
    }

    /**
     * Deprecated methods (to be removed)
     */

    public static function unfold_flowed($text)
    {
        return rcube_mime::unfold_flowed($text);
    }

    public static function format_flowed($text, $length = 72)
    {
        return rcube_mime::format_flowed($text, $length);
    }
}
