<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/sendmail.inc                                       |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Compose a new mail message with all headers and attachments         |
 |   and send it using the PEAR::Net_SMTP class or with PHP mail()       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// remove all scripts and act as called in frame
$OUTPUT->reset();
$OUTPUT->framed = TRUE;

$saveonly       = !empty($_GET['_saveonly']);
$savedraft      = !empty($_POST['_draft']) && !$saveonly;
$sendmail_delay = (int) $RCMAIL->config->get('sendmail_delay');
$drafts_mbox    = $RCMAIL->config->get('drafts_mbox');

$COMPOSE_ID = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
$COMPOSE    =& $_SESSION['compose_data_'.$COMPOSE_ID];

/****** checks ********/

if (!isset($COMPOSE['id'])) {
    rcube::raise_error(array('code' => 500, 'type' => 'php',
        'file' => __FILE__, 'line' => __LINE__,
        'message' => "Invalid compose ID"), true, false);

    $OUTPUT->show_message('internalerror', 'error');
    $OUTPUT->send('iframe');
}

if (!$savedraft) {
    if (empty($_POST['_to']) && empty($_POST['_cc']) && empty($_POST['_bcc']) && $_POST['_message']) {
        $OUTPUT->show_message('sendingfailed', 'error');
        $OUTPUT->send('iframe');
    }

    if ($sendmail_delay) {
        $wait_sec = time() - $sendmail_delay - intval($RCMAIL->config->get('last_message_time'));
        if ($wait_sec < 0) {
            $OUTPUT->show_message('senttooquickly', 'error', array('sec' => $wait_sec * -1));
            $OUTPUT->send('iframe');
        }
    }
}


/****** compose message ********/

// set default charset
$message_charset = isset($_POST['_charset']) ? $_POST['_charset'] : $OUTPUT->get_charset();

$EMAIL_FORMAT_ERROR = NULL;
$RECIPIENT_COUNT    = 0;

$mailto  = rcmail_email_input_format(rcube_utils::get_input_value('_to', rcube_utils::INPUT_POST, TRUE, $message_charset), true);
$mailcc  = rcmail_email_input_format(rcube_utils::get_input_value('_cc', rcube_utils::INPUT_POST, TRUE, $message_charset), true);
$mailbcc = rcmail_email_input_format(rcube_utils::get_input_value('_bcc', rcube_utils::INPUT_POST, TRUE, $message_charset), true);

if ($EMAIL_FORMAT_ERROR && !$savedraft) {
    $OUTPUT->show_message('emailformaterror', 'error', array('email' => $EMAIL_FORMAT_ERROR));
    $OUTPUT->send('iframe');
}

if (empty($mailto) && !empty($mailcc)) {
    $mailto = $mailcc;
    $mailcc = null;
}
else if (empty($mailto)) {
    $mailto = 'undisclosed-recipients:;';
}

// Get sender name and address...
$from = rcube_utils::get_input_value('_from', rcube_utils::INPUT_POST, true, $message_charset);
// ... from identity...
if (is_numeric($from)) {
    if (is_array($identity_arr = rcmail_get_identity($from))) {
        if ($identity_arr['mailto'])
            $from = $identity_arr['mailto'];
        if ($identity_arr['string'])
            $from_string = $identity_arr['string'];
    }
    else {
        $from = null;
    }
}
// ... if there is no identity record, this might be a custom from
else if (($from_string = rcmail_email_input_format($from))
    && preg_match('/(\S+@\S+)/', $from_string, $m)
) {
    $from = trim($m[1], '<>');
}
// ... otherwise it's empty or invalid
else {
    $from = null;
}

// check 'From' address (identity may be incomplete)
if (!$savedraft && !$saveonly && empty($from)) {
    $OUTPUT->show_message('nofromaddress', 'error');
    $OUTPUT->send('iframe');
}

if (!$from_string && $from) {
    $from_string = $from;
}

if (empty($COMPOSE['param']['message-id'])) {
    $COMPOSE['param']['message-id'] = $RCMAIL->gen_message_id($from);
}
$message_id = $COMPOSE['param']['message-id'];

// compose headers array
$headers = array();

// if configured, the Received headers goes to top, for good measure
if ($RCMAIL->config->get('http_received_header')) {
    $nldlm       = "\r\n\t";
    $http_header = 'from ';

    // FROM/VIA
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $hosts        = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'], 2);
        $http_header .= rcmail_received_host($hosts[0]) . $nldlm . ' via ';
    }

    $http_header .= rcmail_received_host($_SERVER['REMOTE_ADDR']);

    // BY
    $http_header .= $nldlm . 'by ' . $_SERVER['HTTP_HOST'];

    // WITH
    $http_header .= $nldlm . 'with HTTP (' . $_SERVER['SERVER_PROTOCOL']
        . ' ' . $_SERVER['REQUEST_METHOD'] . '); ' . date('r');

    $headers['Received'] = wordwrap($http_header, 69, $nldlm);
}

$headers['Date'] = $RCMAIL->user_date();
$headers['From'] = rcube_charset::convert($from_string, RCUBE_CHARSET, $message_charset);
$headers['To']   = $mailto;

// additional recipients
if (!empty($mailcc)) {
    $headers['Cc'] = $mailcc;
}
if (!empty($mailbcc)) {
    $headers['Bcc'] = $mailbcc;
}

if (($max_recipients = (int) $RCMAIL->config->get('max_recipients')) > 0) {
    if ($RECIPIENT_COUNT > $max_recipients) {
        $OUTPUT->show_message('toomanyrecipients', 'error', array('max' => $max_recipients));
        $OUTPUT->send('iframe');
    }
}

$dont_override = (array) $RCMAIL->config->get('dont_override');
$mdn_enabled   = in_array('mdn_default', $dont_override) ? $RCMAIL->config->get('mdn_default') : !empty($_POST['_mdn']);
$dsn_enabled   = in_array('dsn_default', $dont_override) ? $RCMAIL->config->get('dsn_default') : !empty($_POST['_dsn']);
$subject       = trim(rcube_utils::get_input_value('_subject', rcube_utils::INPUT_POST, TRUE, $message_charset));

if (strlen($subject)) {
    $headers['Subject'] = $subject;
}
if (!empty($identity_arr['organization'])) {
    $headers['Organization'] = $identity_arr['organization'];
}
if ($hdr = rcube_utils::get_input_value('_replyto', rcube_utils::INPUT_POST, TRUE, $message_charset)) {
    $headers['Reply-To'] = rcmail_email_input_format($hdr);
}
if (!empty($headers['Reply-To'])) {
    $headers['Mail-Reply-To'] = $headers['Reply-To'];
}
if ($hdr = rcube_utils::get_input_value('_followupto', rcube_utils::INPUT_POST, TRUE, $message_charset)) {
    $headers['Mail-Followup-To'] = rcmail_email_input_format($hdr);
}

// remember reply/forward UIDs in special headers
if ($savedraft) {
    // Note: We ignore <UID>.<PART> forwards/replies here
    if (($uid = $COMPOSE['reply_uid']) && !preg_match('/^\d+\.[0-9.]+$/', $uid)) {
        $headers['X-Draft-Info'] = array('type' => 'reply', 'uid' => $uid);
    }
    else if (!empty($COMPOSE['forward_uid'])
        && ($uid = rcube_imap_generic::compressMessageSet($COMPOSE['forward_uid']))
        && !preg_match('/^\d+[0-9.]+$/', $uid)
    ) {
        $headers['X-Draft-Info'] = array('type' => 'forward', 'uid' => $uid);
    }
}

if (!empty($COMPOSE['reply_msgid'])) {
    $headers['In-Reply-To'] = $COMPOSE['reply_msgid'];
}
if (!empty($COMPOSE['references'])) {
    $headers['References'] = $COMPOSE['references'];
}

if (!empty($_POST['_priority'])) {
    $priority     = intval($_POST['_priority']);
    $a_priorities = array(1 => 'highest', 2 => 'high', 4 => 'low', 5 => 'lowest');

    if ($str_priority = $a_priorities[$priority]) {
        $headers['X-Priority'] = sprintf("%d (%s)", $priority, ucfirst($str_priority));
    }
}

if ($mdn_enabled) {
    $headers['Return-Receipt-To']           = $from_string;
    $headers['Disposition-Notification-To'] = $from_string;
}

// additional headers
$headers['Message-ID'] = $message_id;
$headers['X-Sender']   = $from;

if (is_array($headers['X-Draft-Info'])) {
    $headers['X-Draft-Info'] = rcmail_draftinfo_encode($headers['X-Draft-Info'] + array('folder' => $COMPOSE['mailbox']));
}
if ($hdr = $RCMAIL->config->get('useragent')) {
    $headers['User-Agent'] = $hdr;
}

// exec hook for header checking and manipulation
// Depracated: use message_before_send hook instead
$data = $RCMAIL->plugins->exec_hook('message_outgoing_headers', array('headers' => $headers));

// sending aborted by plugin
if ($data['abort'] && !$savedraft) {
    $OUTPUT->show_message($data['message'] ?: 'sendingfailed');
    $OUTPUT->send('iframe');
}
else {
    $headers = $data['headers'];
}

$isHtml = (bool) rcube_utils::get_input_value('_is_html', rcube_utils::INPUT_POST);

// fetch message body
$message_body = rcube_utils::get_input_value('_message', rcube_utils::INPUT_POST, TRUE, $message_charset);

if (isset($_POST['_pgpmime'])) {
    $pgp_mime     = rcube_utils::get_input_value('_pgpmime', rcube_utils::INPUT_POST);
    $isHtml       = false;
    $message_body = '';

    // clear unencrypted attachments
    foreach ((array) $COMPOSE['attachments'] as $attach) {
        $RCMAIL->plugins->exec_hook('attachment_delete', $attach);
    }

    $COMPOSE['attachments'] = array();
}

if ($isHtml) {
    $bstyle = array();

    if ($font_size = $RCMAIL->config->get('default_font_size')) {
        $bstyle[] = 'font-size: ' . $font_size;
    }
    if ($font_family = $RCMAIL->config->get('default_font')) {
        $bstyle[] = 'font-family: ' . rcmail::font_defs($font_family);
    }

    // append doctype and html/body wrappers
    $bstyle       = !empty($bstyle) ? (" style='" . implode($bstyle, '; ') . "'") : '';
    $message_body = '<html><head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=' . $message_charset . '" /></head>'
        . "<body" . $bstyle . ">\r\n" . $message_body;
}

if (!$savedraft) {
    if ($isHtml) {
        $b_style   = 'padding: 0 0.4em; border-left: #1010ff 2px solid; margin: 0';
        $pre_style = 'margin: 0; padding: 0; font-family: monospace';

        $message_body = preg_replace(
            array(
                // remove empty signature div
                '/<div id="_rc_sig">(&nbsp;)?<\/div>[\s\r\n]*$/',
                // remove signature's div ID
                '/\s*id="_rc_sig"/',
                // add inline css for blockquotes and container
                '/<blockquote>/',
                '/<div class="pre">/',
                // convert TinyMCE's new-line sequences (#1490463)
                '/<p>&nbsp;<\/p>/',
            ),
            array(
                '',
                '',
                '<blockquote type="cite" style="'.$b_style.'">',
                '<div class="pre" style="'.$pre_style.'">',
                '<p><br /></p>',
            ),
            $message_body);
    }

    // Check spelling before send
    if ($RCMAIL->config->get('spellcheck_before_send') && $RCMAIL->config->get('enable_spellcheck')
        && empty($COMPOSE['spell_checked']) && !empty($message_body)
    ) {
        $message_body = str_replace("\r\n", "\n", $message_body);
        $spellchecker = new rcube_spellchecker(rcube_utils::get_input_value('_lang', rcube_utils::INPUT_GPC));
        $spell_result = $spellchecker->check($message_body, $isHtml);

        $COMPOSE['spell_checked'] = true;

        if (!$spell_result) {
            if ($isHtml) {
                $result['words']      = $spellchecker->get();
                $result['dictionary'] = (bool) $RCMAIL->config->get('spellcheck_dictionary');
            }
            else {
                $result = $spellchecker->get_xml();
            }

            $OUTPUT->show_message('mispellingsfound', 'error');
            $OUTPUT->command('spellcheck_resume', $result);
            $OUTPUT->send('iframe');
        }
    }

    // generic footer for all messages
    if ($footer = rcmail_generic_message_footer($isHtml)) {
        $footer = rcube_charset::convert($footer, RCUBE_CHARSET, $message_charset);
        $message_body .= "\r\n" . $footer;
    }
}

if ($isHtml) {
    $message_body .= "\r\n</body></html>\r\n";
}

// sort attachments to make sure the order is the same as in the UI (#1488423)
if ($files = rcube_utils::get_input_value('_attachments', rcube_utils::INPUT_POST)) {
    $files = explode(',', $files);
    $files = array_flip($files);
    foreach ($files as $idx => $val) {
        $files[$idx] = $COMPOSE['attachments'][$idx];
        unset($COMPOSE['attachments'][$idx]);
    }

    $COMPOSE['attachments'] = array_merge(array_filter($files), $COMPOSE['attachments']);
}

// set line length for body wrapping
$LINE_LENGTH = $RCMAIL->config->get('line_length', 72);

// Since we can handle big messages with disk usage, we need more time to work
@set_time_limit(0);

// create PEAR::Mail_mime instance
$MAIL_MIME = new Mail_mime("\r\n");

// Check if we have enough memory to handle the message in it
// It's faster than using files, so we'll do this if we only can
if (is_array($COMPOSE['attachments']) && ($mem_limit = parse_bytes(ini_get('memory_limit')))) {
    $memory = 0;
    foreach ($COMPOSE['attachments'] as $id => $attachment) {
        $memory += $attachment['size'];
    }

    // Yeah, Net_SMTP needs up to 12x more memory, 1.33 is for base64
    if (!rcube_utils::mem_check($memory * 1.33 * 12)) {
        $MAIL_MIME->setParam('delay_file_io', true);
    }
}

// For HTML-formatted messages, construct the MIME message with both
// the HTML part and the plain-text part
if ($isHtml) {
    $plugin = $RCMAIL->plugins->exec_hook('message_outgoing_body',
        array('body' => $message_body, 'type' => 'html', 'message' => $MAIL_MIME));

    $MAIL_MIME->setHTMLBody($plugin['body']);

    $plainTextPart = $RCMAIL->html2text($plugin['body'], array('width' => 0, 'charset' => $message_charset));
    $plainTextPart = rcube_mime::wordwrap($plainTextPart, $LINE_LENGTH, "\r\n", false, $message_charset);
    $plainTextPart = wordwrap($plainTextPart, 998, "\r\n", true);

    // There's no sense to use multipart/alternative if the text/plain
    // part would be blank. Completely blank text/plain part may confuse
    // some mail clients (#5283)
    if (strlen(trim($plainTextPart)) > 0) {
        // make sure all line endings are CRLF (#1486712)
        $plainTextPart = preg_replace('/\r?\n/', "\r\n", $plainTextPart);

        $plugin = $RCMAIL->plugins->exec_hook('message_outgoing_body',
            array('body' => $plainTextPart, 'type' => 'alternative', 'message' => $MAIL_MIME));

        // add a plain text version of the e-mail as an alternative part.
        $MAIL_MIME->setTXTBody($plugin['body']);
    }

    // Extract image Data URIs into message attachments (#1488502)
    rcmail_extract_inline_images($MAIL_MIME, $from);
}
else {
    $plugin = $RCMAIL->plugins->exec_hook('message_outgoing_body',
        array('body' => $message_body, 'type' => 'plain', 'message' => $MAIL_MIME));

    $message_body = $plugin['body'];

    // compose format=flowed content if enabled
    if ($flowed = ($savedraft || $RCMAIL->config->get('send_format_flowed', true)))
        $message_body = rcube_mime::format_flowed($message_body, min($LINE_LENGTH+2, 79), $message_charset);
    else
        $message_body = rcube_mime::wordwrap($message_body, $LINE_LENGTH, "\r\n", false, $message_charset);

    $message_body = wordwrap($message_body, 998, "\r\n", true);

    $MAIL_MIME->setTXTBody($message_body, false, true);
}

// add stored attachments, if any
if (is_array($COMPOSE['attachments'])) {
    foreach ($COMPOSE['attachments'] as $id => $attachment) {
        // This hook retrieves the attachment contents from the file storage backend
        $attachment = $RCMAIL->plugins->exec_hook('attachment_get', $attachment);

        if ($isHtml) {
            $dispurl      = '/[\'"]\S+display-attachment\S+file=rcmfile' . preg_quote($attachment['id']) . '[\'"]/';
            $message_body = $MAIL_MIME->getHTMLBody();
            $is_inline    = preg_match($dispurl, $message_body);
        }
        else {
            $is_inline = false;
        }

        // inline image
        if ($is_inline) {
            // Mail_Mime does not support many inline attachments with the same name (#1489406)
            // we'll generate cid: urls here to workaround this
            $cid = preg_replace('/[^0-9a-zA-Z]/', '', uniqid(time(), true));
            if (preg_match('#(@[0-9a-zA-Z\-\.]+)#', $from, $matches)) {
                $cid .= $matches[1];
            }
            else {
                $cid .= '@localhost';
            }

            $message_body = preg_replace($dispurl, '"cid:' . $cid . '"', $message_body);

            $MAIL_MIME->setHTMLBody($message_body);

            if ($attachment['data'])
                $MAIL_MIME->addHTMLImage($attachment['data'], $attachment['mimetype'], $attachment['name'], false, $cid);
            else
                $MAIL_MIME->addHTMLImage($attachment['path'], $attachment['mimetype'], $attachment['name'], true, $cid);
        }
        else {
            $ctype   = str_replace('image/pjpeg', 'image/jpeg', $attachment['mimetype']); // #1484914
            $file    = $attachment['data'] ?: $attachment['path'];
            $folding = (int) $RCMAIL->config->get('mime_param_folding');

            $MAIL_MIME->addAttachment($file,
                $ctype,
                $attachment['name'],
                $attachment['data'] ? false : true,
                $ctype == 'message/rfc822' ? '8bit' : 'base64',
                'attachment',
                $attachment['charset'],
                '', '',
                $folding ? 'quoted-printable' : NULL,
                $folding == 2 ? 'quoted-printable' : NULL,
                '', RCUBE_CHARSET
            );
        }
    }
}

// choose transfer encoding for plain/text body
if (preg_match('/[^\x00-\x7F]/', $MAIL_MIME->getTXTBody())) {
    $text_charset      = $message_charset;
    $transfer_encoding = $RCMAIL->config->get('force_7bit') ? 'quoted-printable' : '8bit';
}
else {
    $text_charset      = 'US-ASCII';
    $transfer_encoding = '7bit';
}

if ($flowed) {
    $text_charset .= ";\r\n format=flowed";
}

// compose PGP/Mime message
if ($pgp_mime) {
    $MAIL_MIME->addAttachment(new Mail_mimePart('Version: 1', array(
            'content_type' => 'application/pgp-encrypted',
            'description'  => 'PGP/MIME version identification',
    )));

    $MAIL_MIME->addAttachment(new Mail_mimePart($pgp_mime, array(
            'content_type' => 'application/octet-stream',
            'filename'     => 'encrypted.asc',
            'disposition'  => 'inline',
    )));

    $MAIL_MIME->setContentType('multipart/encrypted', array('protocol' => 'application/pgp-encrypted'));
    $MAIL_MIME->setParam('preamble', 'This is an OpenPGP/MIME encrypted message (RFC 2440 and 3156)');
}

// encoding settings for mail composing
$MAIL_MIME->setParam('text_encoding', $transfer_encoding);
$MAIL_MIME->setParam('html_encoding', 'quoted-printable');
$MAIL_MIME->setParam('head_encoding', 'quoted-printable');
$MAIL_MIME->setParam('head_charset', $message_charset);
$MAIL_MIME->setParam('html_charset', $message_charset);
$MAIL_MIME->setParam('text_charset', $text_charset);

// pass headers to message object
$MAIL_MIME->headers($headers);

// This hook allows to modify the message before send or save action
$plugin    = $RCMAIL->plugins->exec_hook('message_ready', array('message' => $MAIL_MIME));
$MAIL_MIME = $plugin['message'];

// Begin SMTP Delivery Block
if (!$savedraft && !$saveonly) {
    // Handle Delivery Status Notification request
    $smtp_opts['dsn'] = $dsn_enabled;

    $sent = $RCMAIL->deliver_message($MAIL_MIME, $from, $mailto,
        $smtp_error, $mailbody_file, $smtp_opts, true);

    // return to compose page if sending failed
    if (!$sent) {
        // remove temp file
        if ($mailbody_file) {
            unlink($mailbody_file);
        }

        if ($smtp_error && is_string($smtp_error)) {
            $OUTPUT->show_message($smtp_error, 'error');
        }
        else if ($smtp_error && !empty($smtp_error['label'])) {
            $OUTPUT->show_message($smtp_error['label'], 'error', $smtp_error['vars']);
        }
        else {
            $OUTPUT->show_message('sendingfailed', 'error');
        }

        $OUTPUT->send('iframe');
    }

    // save message sent time
    if ($sendmail_delay) {
        $RCMAIL->user->save_prefs(array('last_message_time' => time()));
    }

    // set replied/forwarded flag
    if ($COMPOSE['reply_uid']) {
        foreach (rcmail::get_uids($COMPOSE['reply_uid'], $COMPOSE['mailbox']) as $mbox => $uids) {
            // skip <UID>.<PART> replies
            if (!preg_match('/^\d+\.[0-9.]+$/', implode(',', (array) $uids))) {
                $RCMAIL->storage->set_flag($uids, 'ANSWERED', $mbox);
            }
        }
    }
    else if ($COMPOSE['forward_uid']) {
        foreach (rcmail::get_uids($COMPOSE['forward_uid'], $COMPOSE['mailbox']) as $mbox => $uids) {
            // skip <UID>.<PART> forwards
            if (!preg_match('/^\d+\.[0-9.]+$/', implode(',', (array) $uids))) {
                $RCMAIL->storage->set_flag($uids, 'FORWARDED', $mbox);
            }
        }
    }
}

// Determine which folder to save message
if ($savedraft) {
    $store_target = $drafts_mbox;
}
else if (!$RCMAIL->config->get('no_save_sent_messages')) {
    if (isset($_POST['_store_target'])) {
        $store_target = rcube_utils::get_input_value('_store_target', rcube_utils::INPUT_POST);
    }
    else {
        $store_target = $RCMAIL->config->get('sent_mbox');
    }
}

if ($store_target) {
    // check if folder is subscribed
    if ($RCMAIL->storage->folder_exists($store_target, true)) {
        $store_folder = true;
    }
    // folder may be existing but not subscribed (#1485241)
    else if (!$RCMAIL->storage->folder_exists($store_target)) {
        $store_folder = $RCMAIL->storage->create_folder($store_target, true);
    }
    else if ($RCMAIL->storage->subscribe($store_target)) {
        $store_folder = true;
    }

    // append message to sent box
    if ($store_folder) {
        // message body in file
        if ($mailbody_file || $MAIL_MIME->getParam('delay_file_io')) {
            $headers = $MAIL_MIME->txtHeaders();

            // file already created
            if ($mailbody_file) {
                $msg = $mailbody_file;
            }
            else {
                $temp_dir      = $RCMAIL->config->get('temp_dir');
                $mailbody_file = tempnam($temp_dir, 'rcmMsg');
                $msg           = $MAIL_MIME->saveMessageBody($mailbody_file);

                if (!is_a($msg, 'PEAR_Error')) {
                    $msg = $mailbody_file;
                }
            }
        }
        else {
            $msg     = $MAIL_MIME->getMessage();
            $headers = '';
        }

        if (is_a($msg, 'PEAR_Error')) {
            rcube::raise_error(array('code' => 650, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Could not create message: ".$msg->getMessage()),
                true, false);
        }
        else {
            $saved = $RCMAIL->storage->save_message($store_target, $msg, $headers,
                $mailbody_file ? true : false, array('SEEN'));
        }

        if ($mailbody_file) {
            unlink($mailbody_file);
            $mailbody_file = null;
        }
    }

    // raise error if saving failed
    if (!$saved) {
        rcube::raise_error(array('code' => 800, 'type' => 'imap',
            'file' => __FILE__, 'line' => __LINE__,
            'message' => "Could not save message in $store_target"), true, false);

        if ($savedraft) {
            $RCMAIL->display_server_error('errorsaving');

            // start the auto-save timer again
            $OUTPUT->command('auto_save_start');
            $OUTPUT->send('iframe');
        }
    }
}
// remove temp file
else if ($mailbody_file) {
    unlink($mailbody_file);
}

// delete previous saved draft
$old_id = rcube_utils::get_input_value('_draft_saveid', rcube_utils::INPUT_POST);
if ($old_id && ($sent || $saved)) {
    $deleted = $RCMAIL->storage->delete_message($old_id, $drafts_mbox);

    // raise error if deletion of old draft failed
    if (!$deleted) {
        rcube::raise_error(array('code' => 800, 'type' => 'imap',
            'file' => __FILE__, 'line' => __LINE__,
            'message' => "Could not delete message from $drafts_mbox"), true, false);
    }
}

if ($savedraft) {
    // remember new draft-uid ($saved could be an UID or true/false here)
    if ($saved && is_bool($saved)) {
        $index = $RCMAIL->storage->search_once($drafts_mbox, 'HEADER Message-ID ' . $message_id);
        $saved = @max($index->get());
    }

    if ($saved) {
        $plugin = $RCMAIL->plugins->exec_hook('message_draftsaved',
            array('msgid' => $message_id, 'uid' => $saved, 'folder' => $store_target));

        // display success
        $OUTPUT->show_message($plugin['message'] ?: 'messagesaved', 'confirmation');

        // update "_draft_saveid" and the "cmp_hash" to prevent "Unsaved changes" warning
        $COMPOSE['param']['draft_uid'] = $plugin['uid'];
        $OUTPUT->command('set_draft_id', $plugin['uid']);
        $OUTPUT->command('compose_field_hash', true);
    }

    // start the auto-save timer again
    $OUTPUT->command('auto_save_start');
}
else {
    // Collect folders which could contain the composed message,
    // we'll refresh the list if currently opened folder is one of them (#1490238)
    $folders = array();

    if (!$saveonly) {
        if (in_array($COMPOSE['mode'], array('reply', 'forward', 'draft'))) {
            $folders[] = $COMPOSE['mailbox'];
        }
        if (!empty($COMPOSE['param']['draft_uid']) && $drafts_mbox) {
            $folders[] = $drafts_mbox;
        }
    }

    if ($store_folder && !$saved) {
        $params = $saveonly ? null : array('prefix' => true);
        $RCMAIL->display_server_error('errorsavingsent', null, null, $params);
        if ($saveonly) {
            $OUTPUT->send('iframe');
        }

        $save_error = true;
    }
    else {
        rcmail_compose_cleanup($COMPOSE_ID);
        $OUTPUT->command('remove_compose_data', $COMPOSE_ID);

        if ($store_folder) {
            $folders[] = $store_target;
        }
    }

    $msg = $RCMAIL->gettext($saveonly ? 'successfullysaved' : 'messagesent');

    $OUTPUT->command('sent_successfully', 'confirmation', $msg, $folders, $save_error);
}

$OUTPUT->send('iframe');


/****** message sending functions ********/

function rcmail_received_host($host)
{
    $hostname = gethostbyaddr($host);

    $result = rcmail_encrypt_host($hostname);

    if ($host != $hostname) {
        $result .= ' (' . rcmail_encrypt_host($host) . ')';
    }

    return $result;
}

// encrypt host IP or hostname for Received header
function rcmail_encrypt_host($host)
{
    global $RCMAIL;

    if ($RCMAIL->config->get('http_received_header_encrypt')) {
        return $RCMAIL->encrypt($host);
    }

    if (!preg_match('/[^0-9:.]/', $host)) {
        return "[$host]";
    }

    return $host;
}

// get identity record
function rcmail_get_identity($id)
{
    global $RCMAIL, $message_charset;

    if ($sql_arr = $RCMAIL->user->get_identity($id)) {
        $out = $sql_arr;

        if ($message_charset != RCUBE_CHARSET) {
            foreach ($out as $k => $v) {
                $out[$k] = rcube_charset::convert($v, RCUBE_CHARSET, $message_charset);
            }
        }

        $out['mailto'] = $sql_arr['email'];
        $out['string'] = format_email_recipient($sql_arr['email'], $sql_arr['name']);

        return $out;
    }

    return false;
}

/**
 * Extract image attachments from HTML content (data URIs)
 */
function rcmail_extract_inline_images($mime_message, $from)
{
    $body   = $mime_message->getHTMLBody();
    $offset = 0;
    $list   = array();
    $domain = 'localhost';
    $regexp = '#img[^>]+src=[\'"](data:([^;]*);base64,([a-z0-9+/=\r\n]+))([\'"])#i';

    if (preg_match_all($regexp, $body, $matches, PREG_OFFSET_CAPTURE)) {
        // get domain for the Content-ID, must be the same as in Mail_Mime::get()
        if (preg_match('#@([0-9a-zA-Z\-\.]+)#', $from, $m)) {
            $domain = $m[1];
        }

        foreach ($matches[1] as $idx => $m) {
            $data = preg_replace('/\r\n/', '', $matches[3][$idx][0]);
            $data = base64_decode($data);

            if (empty($data)) {
                continue;
            }

            $hash      = md5($data) . '@' . $domain;
            $mime_type = $matches[2][$idx][0];
            $name      = $list[$hash];

            if (empty($mime_type)) {
                $mime_type = rcube_mime::image_content_type($data);
            }

            // add the image to the MIME message
            if (!$name) {
                $ext         = preg_replace('#^[^/]+/#', '', $mime_type);
                $name        = substr($hash, 0, 8) . '.' . $ext;
                $list[$hash] = $name;

                $mime_message->addHTMLImage($data, $mime_type, $name, false, $hash);
            }

            $body = substr_replace($body, $name, $m[1] + $offset, strlen($m[0]));
            $offset += strlen($name) - strlen($m[0]);
        }
    }

    $mime_message->setHTMLBody($body);
}

/**
 * Parse and cleanup email address input (and count addresses)
 *
 * @param string  Address input
 * @param boolean Do count recipients (saved in global $RECIPIENT_COUNT)
 * @param boolean Validate addresses (errors saved in global $EMAIL_FORMAT_ERROR)
 * @return string Canonical recipients string separated by comma
 */
function rcmail_email_input_format($mailto, $count=false, $check=true)
{
    global $RCMAIL, $EMAIL_FORMAT_ERROR, $RECIPIENT_COUNT;

    // simplified email regexp, supporting quoted local part
    $email_regexp = '(\S+|("[^"]+"))@\S+';

    $delim   = trim($RCMAIL->config->get('recipients_separator', ','));
    $regexp  = array("/[,;$delim]\s*[\r\n]+/", '/[\r\n]+/', "/[,;$delim]\s*\$/m", '/;/', '/(\S{1})(<'.$email_regexp.'>)/U');
    $replace = array($delim.' ', ', ', '', $delim, '\\1 \\2');

    // replace new lines and strip ending ', ', make address input more valid
    $mailto = trim(preg_replace($regexp, $replace, $mailto));
    $items  = rcube_utils::explode_quoted_string($delim, $mailto);
    $result = array();

    foreach ($items as $item) {
        $item = trim($item);
        // address in brackets without name (do nothing)
        if (preg_match('/^<'.$email_regexp.'>$/', $item)) {
            $item     = rcube_utils::idn_to_ascii(trim($item, '<>'));
            $result[] = $item;
        }
        // address without brackets and without name (add brackets)
        else if (preg_match('/^'.$email_regexp.'$/', $item)) {
            $item     = rcube_utils::idn_to_ascii($item);
            $result[] = $item;
        }
        // address with name (handle name)
        else if (preg_match('/<*'.$email_regexp.'>*$/', $item, $matches)) {
            $address = $matches[0];
            $name    = trim(str_replace($address, '', $item));
            if ($name[0] == '"' && $name[count($name)-1] == '"') {
                $name = substr($name, 1, -1);
            }
            $name     = stripcslashes($name);
            $address  = rcube_utils::idn_to_ascii(trim($address, '<>'));
            $result[] = format_email_recipient($address, $name);
            $item     = $address;
        }
        else if (trim($item)) {
            continue;
        }

        // check address format
        $item = trim($item, '<>');
        if ($item && $check && !rcube_utils::check_email($item)) {
            $EMAIL_FORMAT_ERROR = $item;
            return;
        }
    }

    if ($count) {
        $RECIPIENT_COUNT += count($result);
    }

    return implode(', ', $result);
}


function rcmail_generic_message_footer($isHtml)
{
    global $RCMAIL;

    if ($isHtml && ($file = $RCMAIL->config->get('generic_message_footer_html'))) {
        $html_footer = true;
    }
    else {
        $file = $RCMAIL->config->get('generic_message_footer');
        $html_footer = false;
    }

    if ($file && realpath($file)) {
        // sanity check
        if (!preg_match('/\.(php|ini|conf)$/', $file) && strpos($file, '/etc/') === false) {
            $footer = file_get_contents($file);
            if ($isHtml && !$html_footer) {
                $t2h    = new rcube_text2html($footer, false);
                $footer = $t2h->get_html();
            }
            return $footer;
        }
    }

    return false;
}

/**
 * clear message composing settings
 */
function rcmail_compose_cleanup($id)
{
    if (!isset($_SESSION['compose_data_'.$id])) {
        return;
    }

    $rcmail = rcmail::get_instance();
    $rcmail->plugins->exec_hook('attachments_cleanup', array('group' => $id));
    $rcmail->session->remove('compose_data_'.$id);

    $_SESSION['last_compose_session'] = $id;
}
