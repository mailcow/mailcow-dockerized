<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/compose.inc                                        |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2016, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Compose a new mail message with all headers and attachments         |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

// define constants for message compose mode
define('RCUBE_COMPOSE_REPLY', 'reply');
define('RCUBE_COMPOSE_FORWARD', 'forward');
define('RCUBE_COMPOSE_DRAFT', 'draft');
define('RCUBE_COMPOSE_EDIT', 'edit');

$MESSAGE_FORM = null;
$COMPOSE_ID   = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET);
$COMPOSE      = null;

if ($COMPOSE_ID && $_SESSION['compose_data_'.$COMPOSE_ID])
  $COMPOSE =& $_SESSION['compose_data_'.$COMPOSE_ID];

// give replicated session storage some time to synchronize
$retries = 0;
while ($COMPOSE_ID && !is_array($COMPOSE) && $RCMAIL->db->is_replicated() && $retries++ < 5) {
    usleep(500000);
    $RCMAIL->session->reload();
    if ($_SESSION['compose_data_'.$COMPOSE_ID]) {
        $COMPOSE =& $_SESSION['compose_data_'.$COMPOSE_ID];
    }
}

// Nothing below is called during message composition, only at "new/forward/reply/draft" initialization or
// if a compose-ID is given (i.e. when the compose step is opened in a new window/tab).
if (!is_array($COMPOSE)) {
    // Infinite redirect prevention in case of broken session (#1487028)
    if ($COMPOSE_ID) {
        // if we know the message with specified ID was already sent
        // we can ignore the error and compose a new message (#1490009)
        if ($COMPOSE_ID != $_SESSION['last_compose_session']) {
            rcube::raise_error(array('code' => 450), false, true);
        }
    }

    $COMPOSE_ID = uniqid(mt_rand());
    $params     = rcube_utils::request2param(rcube_utils::INPUT_GET, 'task|action', true);

    $_SESSION['compose_data_'.$COMPOSE_ID] = array(
        'id'      => $COMPOSE_ID,
        'param'   => $params,
        'mailbox' => $params['mbox'] ?: $RCMAIL->storage->get_folder(),
    );
    $COMPOSE =& $_SESSION['compose_data_'.$COMPOSE_ID];
    rcmail_process_compose_params($COMPOSE);

    // check if folder for saving sent messages exists and is subscribed (#1486802)
    if ($sent_folder = $COMPOSE['param']['sent_mbox']) {
        rcmail_check_sent_folder($sent_folder, true);
    }

    // redirect to a unique URL with all parameters stored in session
    $OUTPUT->redirect(array(
        '_action' => 'compose',
        '_id'     => $COMPOSE['id'],
        '_search' => $_REQUEST['_search'],
    ));
}


// add some labels to client
$OUTPUT->add_label('nosubject', 'nosenderwarning', 'norecipientwarning', 'nosubjectwarning', 'cancel',
    'nobodywarning', 'notsentwarning', 'notuploadedwarning', 'savingmessage', 'sendingmessage', 
    'messagesaved', 'converting', 'editorwarning', 'searching', 'uploading', 'uploadingmany',
    'fileuploaderror', 'sendmessage', 'newresponse', 'responsename', 'responsetext', 'save',
    'savingresponse', 'restoresavedcomposedata', 'restoremessage', 'delete', 'restore', 'ignore',
    'selectimportfile', 'messageissent', 'loadingdata', 'nopubkeyfor', 'nopubkeyforsender',
    'encryptnoattachments','encryptedsendialog','searchpubkeyservers', 'importpubkeys',
    'encryptpubkeysfound',  'search', 'close', 'import', 'keyid', 'keylength', 'keyexpired',
    'keyrevoked', 'keyimportsuccess', 'keyservererror', 'attaching', 'namex', 'attachmentrename',
    'disclosedrecipwarning', 'disclosedreciptitle', 'bccinstead', 'nosubjecttitle');

$OUTPUT->set_pagetitle($RCMAIL->gettext('compose'));

$OUTPUT->set_env('compose_id', $COMPOSE['id']);
$OUTPUT->set_env('session_id', session_id());
$OUTPUT->set_env('mailbox', $RCMAIL->storage->get_folder());
$OUTPUT->set_env('top_posting', intval($RCMAIL->config->get('reply_mode')) > 0);
$OUTPUT->set_env('sig_below', $RCMAIL->config->get('sig_below'));
$OUTPUT->set_env('recipients_separator', trim($RCMAIL->config->get('recipients_separator', ',')));
$OUTPUT->set_env('save_localstorage', (bool)$RCMAIL->config->get('compose_save_localstorage'));
$OUTPUT->set_env('max_disclosed_recipients', (int) $RCMAIL->config->get('max_disclosed_recipients', 5));
$OUTPUT->set_env('is_sent', false);
$OUTPUT->set_env('mimetypes', rcmail_supported_mimetypes());

$drafts_mbox     = $RCMAIL->config->get('drafts_mbox');
$config_show_sig = $RCMAIL->config->get('show_sig', 1);

// add config parameters to client script
if (strlen($drafts_mbox)) {
    $OUTPUT->set_env('drafts_mailbox', $drafts_mbox);
    $OUTPUT->set_env('draft_autosave', $RCMAIL->config->get('draft_autosave'));
}

// default font for HTML editor
$font = rcmail::font_defs($RCMAIL->config->get('default_font'));
if ($font && !is_array($font)) {
    $OUTPUT->set_env('default_font', $font);
}

// default font size for HTML editor
if ($font_size = $RCMAIL->config->get('default_font_size')) {
    $OUTPUT->set_env('default_font_size', $font_size);
}

// get reference message and set compose mode
if ($msg_uid = $COMPOSE['param']['draft_uid']) {
    $compose_mode = RCUBE_COMPOSE_DRAFT;
    $OUTPUT->set_env('draft_id', $msg_uid);
    $RCMAIL->storage->set_folder($drafts_mbox);
}
else if ($msg_uid = $COMPOSE['param']['reply_uid']) {
    $compose_mode = RCUBE_COMPOSE_REPLY;
}
else if ($msg_uid = $COMPOSE['param']['forward_uid']) {
    $compose_mode = RCUBE_COMPOSE_FORWARD;
    $COMPOSE['forward_uid']   = $msg_uid;
    $COMPOSE['as_attachment'] = !empty($COMPOSE['param']['attachment']);
}
else if ($msg_uid = $COMPOSE['param']['uid']) {
    $compose_mode = RCUBE_COMPOSE_EDIT;
}

if ($compose_mode) {
    $COMPOSE['mode'] = $compose_mode;
    $OUTPUT->set_env('compose_mode', $compose_mode);
}

if ($compose_mode == RCUBE_COMPOSE_EDIT || $compose_mode == RCUBE_COMPOSE_DRAFT) {
    // don't add signature in draft/edit mode, we'll also not remove the old-one
    // but only on page display, later we should be able to change identity/sig (#1489229)
    if ($config_show_sig == 1 || $config_show_sig == 2) {
        $OUTPUT->set_env('show_sig_later', true);
    }
}
else if ($config_show_sig == 1)
    $OUTPUT->set_env('show_sig', true);
else if ($config_show_sig == 2 && empty($compose_mode))
    $OUTPUT->set_env('show_sig', true);
else if ($config_show_sig == 3 && ($compose_mode == RCUBE_COMPOSE_REPLY || $compose_mode == RCUBE_COMPOSE_FORWARD))
    $OUTPUT->set_env('show_sig', true);

// set line length for body wrapping
$LINE_LENGTH = $RCMAIL->config->get('line_length', 72);

if (!empty($msg_uid) && empty($COMPOSE['as_attachment'])) {
    $mbox_name = $RCMAIL->storage->get_folder();

    // set format before rcube_message construction
    // use the same format as for the message view
    if (isset($_SESSION['msg_formats'][$mbox_name.':'.$msg_uid])) {
        $RCMAIL->config->set('prefer_html', $_SESSION['msg_formats'][$mbox_name.':'.$msg_uid]);
    }
    else {
        $prefer_html = $RCMAIL->config->get('prefer_html') || $RCMAIL->config->get('htmleditor')
            || $compose_mode == RCUBE_COMPOSE_DRAFT || $compose_mode == RCUBE_COMPOSE_EDIT;

        $RCMAIL->config->set('prefer_html', $prefer_html);
    }

    $MESSAGE = new rcube_message($msg_uid);

    // make sure message is marked as read
    if ($MESSAGE->headers && $MESSAGE->context === null && empty($MESSAGE->headers->flags['SEEN'])) {
        $RCMAIL->storage->set_flag($msg_uid, 'SEEN');
    }

    if (!empty($MESSAGE->headers->charset)) {
        $RCMAIL->storage->set_charset($MESSAGE->headers->charset);
    }

    if (!$MESSAGE->headers) {
        // error
    }
    else if ($compose_mode == RCUBE_COMPOSE_FORWARD || $compose_mode == RCUBE_COMPOSE_REPLY) {
        if ($compose_mode == RCUBE_COMPOSE_REPLY) {
            $COMPOSE['reply_uid'] = $MESSAGE->context === null ? $msg_uid : null;

            if (!empty($COMPOSE['param']['all'])) {
                $MESSAGE->reply_all = $COMPOSE['param']['all'];
            }
        }
        else {
            $COMPOSE['forward_uid'] = $msg_uid;
        }

        $COMPOSE['reply_msgid'] = $MESSAGE->headers->messageID;
        $COMPOSE['references']  = trim($MESSAGE->headers->references . " " . $MESSAGE->headers->messageID);

        // Save the sent message in the same folder of the message being replied to
        if ($RCMAIL->config->get('reply_same_folder') && ($sent_folder = $COMPOSE['mailbox'])
            && rcmail_check_sent_folder($sent_folder, false)
        ) {
            $COMPOSE['param']['sent_mbox'] = $sent_folder;
        }
    }
    else if ($compose_mode == RCUBE_COMPOSE_DRAFT || $compose_mode == RCUBE_COMPOSE_EDIT) {
        if ($compose_mode == RCUBE_COMPOSE_DRAFT) {
            if ($draft_info = $MESSAGE->headers->get('x-draft-info')) {
                // get reply_uid/forward_uid to flag the original message when sending
                $info = rcmail_draftinfo_decode($draft_info);

                if ($info['type'] == 'reply')
                    $COMPOSE['reply_uid'] = $info['uid'];
                else if ($info['type'] == 'forward')
                    $COMPOSE['forward_uid'] = $info['uid'];

                $COMPOSE['mailbox'] = $info['folder'];

                // Save the sent message in the same folder of the message being replied to
                if ($RCMAIL->config->get('reply_same_folder') && ($sent_folder = $info['folder'])
                    && rcmail_check_sent_folder($sent_folder, false)
                ) {
                    $COMPOSE['param']['sent_mbox'] = $sent_folder;
                }
            }

            $COMPOSE['param']['message-id'] = $MESSAGE->headers->get('message-id');

            // use message UID as draft_id
            $OUTPUT->set_env('draft_id', $msg_uid);
        }

        if ($in_reply_to = $MESSAGE->headers->get('in-reply-to')) {
            $COMPOSE['reply_msgid'] = '<' . $in_reply_to . '>';
        }

        $COMPOSE['references'] = $MESSAGE->headers->references;
    }
}
else {
    $MESSAGE = new stdClass();

    // apply mailto: URL parameters
    if (!empty($COMPOSE['param']['in-reply-to'])) {
        $COMPOSE['reply_msgid'] = '<' . $COMPOSE['param']['in-reply-to'] . '>';
    }

    if (!empty($COMPOSE['param']['references'])) {
        $COMPOSE['references'] = $COMPOSE['param']['references'];
    }
}

if (!empty($COMPOSE['reply_msgid'])) {
    $OUTPUT->set_env('reply_msgid', $COMPOSE['reply_msgid']);
}

$MESSAGE->compose = array();

// get user's identities
$MESSAGE->identities = $RCMAIL->user->list_identities(null, true);

// Set From field value
if (!empty($_POST['_from'])) {
    $MESSAGE->compose['from'] = rcube_utils::get_input_value('_from', rcube_utils::INPUT_POST);
}
else if (!empty($COMPOSE['param']['from'])) {
    $MESSAGE->compose['from'] = $COMPOSE['param']['from'];
}
else if (count($MESSAGE->identities)) {
    $ident = rcmail_identity_select($MESSAGE, $MESSAGE->identities, $compose_mode);

    $MESSAGE->compose['from']  = $ident['identity_id'];
    $MESSAGE->compose['ident'] = $ident;
}

// process $MESSAGE body/attachments, set $MESSAGE_BODY/$HTML_MODE vars and some session data
$MESSAGE_BODY = rcmail_prepare_message_body();

$OUTPUT->include_script('publickey.js');

// register UI objects
$OUTPUT->add_handlers(array(
    'composeheaders'        => 'rcmail_compose_headers',
    'composesubject'        => 'rcmail_compose_subject',
    'composebody'           => 'rcmail_compose_body',
    'composeattachmentlist' => 'rcmail_compose_attachment_list',
    'composeattachmentform' => 'rcmail_compose_attachment_form',
    'composeattachment'     => 'rcmail_compose_attachment_field',
    'filedroparea'          => 'compose_file_drop_area',
    'priorityselector'      => 'rcmail_priority_selector',
    'editorselector'        => 'rcmail_editor_selector',
    'receiptcheckbox'       => 'rcmail_mdn_checkbox', // deprecated
    'mdncheckbox'           => 'rcmail_mdn_checkbox',
    'dsncheckbox'           => 'rcmail_dsn_checkbox',
    'storetarget'           => 'rcmail_store_target_selection',
    'addressbooks'          => 'rcmail_addressbook_list',
    'addresslist'           => 'rcmail_contacts_list',
    'responseslist'         => 'rcmail_compose_responses_list',
));

$OUTPUT->send('compose');


/****** compose mode functions ********/

// process compose request parameters
function rcmail_process_compose_params(&$COMPOSE)
{
    if ($COMPOSE['param']['to']) {
        $mailto = explode('?', $COMPOSE['param']['to'], 2);

        // #1486037: remove "mailto:" prefix
        $COMPOSE['param']['to'] = preg_replace('/^mailto:/i', '', $mailto[0]);
        // #1490346: decode the recipient address
        // #1490510: use raw encoding for correct "+" character handling as specified in RFC6068
        $COMPOSE['param']['to'] = rawurldecode($COMPOSE['param']['to']);

        // Supported case-insensitive tokens in mailto URL
        $url_tokens = array('to', 'cc', 'bcc', 'reply-to', 'in-reply-to', 'references', 'subject', 'body');

        if (!empty($mailto[1])) {
            parse_str($mailto[1], $query);
            foreach ($query as $f => $val) {
                if (($key = array_search(strtolower($f), $url_tokens)) !== false) {
                    $f = $url_tokens[$key];
                }

                // merge mailto: addresses with addresses from 'to' parameter
                if ($f == 'to' && !empty($COMPOSE['param']['to'])) {
                    $to_addresses  = rcube_mime::decode_address_list($COMPOSE['param']['to'], null, true, null, true);
                    $add_addresses = rcube_mime::decode_address_list($val, null, true);

                    foreach ($add_addresses as $addr) {
                        if (!in_array($addr['mailto'], $to_addresses)) {
                            $to_addresses[]         = $addr['mailto'];
                            $COMPOSE['param']['to'] = (!empty($to_addresses) ? ', ' : '') . $addr['string'];
                        }
                    }
                }
                else {
                    $COMPOSE['param'][$f] = $val;
                }
            }
        }
    }

    // resolve _forward_uid=* to an absolute list of messages from a search result
    if ($COMPOSE['param']['forward_uid'] == '*' && is_object($_SESSION['search'][1])) {
        $COMPOSE['param']['forward_uid'] = $_SESSION['search'][1]->get();
    }

    // clean HTML message body which can be submitted by URL
    if (!empty($COMPOSE['param']['body'])) {
        if ($COMPOSE['param']['html'] = strpos($COMPOSE['param']['body'], '<') !== false) {
            $wash_params = array('safe' => false, 'inline_html' => true);
            $COMPOSE['param']['body'] = rcmail_wash_html($COMPOSE['param']['body'], $wash_params, array());
            $COMPOSE['param']['body'] = preg_replace('/<!--[^>\n]+>/', '', $COMPOSE['param']['body']);
            $COMPOSE['param']['body'] = preg_replace('/<\/?body>/', '', $COMPOSE['param']['body']);
        }
    }

    $RCMAIL = rcmail::get_instance();

    // select folder where to save the sent message
    $COMPOSE['param']['sent_mbox'] = $RCMAIL->config->get('sent_mbox');

    // pipe compose parameters thru plugins
    $plugin = $RCMAIL->plugins->exec_hook('message_compose', $COMPOSE);
    $COMPOSE['param'] = array_merge($COMPOSE['param'], $plugin['param']);

    // add attachments listed by message_compose hook
    if (is_array($plugin['attachments'])) {
        foreach ($plugin['attachments'] as $attach) {
            // we have structured data
            if (is_array($attach)) {
                $attachment = $attach + array('group' => $COMPOSE_ID);
            }
            // only a file path is given
            else {
                $filename   = basename($attach);
                $attachment = array(
                    'group'    => $COMPOSE_ID,
                    'name'     => $filename,
                    'mimetype' => rcube_mime::file_content_type($attach, $filename),
                    'size'     => filesize($attach),
                    'path'     => $attach,
                );
            }

            // save attachment if valid
            if (($attachment['data'] && $attachment['name']) || ($attachment['path'] && file_exists($attachment['path']))) {
                $attachment = rcmail::get_instance()->plugins->exec_hook('attachment_save', $attachment);
            }

            if ($attachment['status'] && !$attachment['abort']) {
                unset($attachment['data'], $attachment['status'], $attachment['abort']);
                $COMPOSE['attachments'][$attachment['id']] = $attachment;
            }
        }
    }
}

function rcmail_compose_headers($attrib)
{
    global $RCMAIL, $MESSAGE;

    list($form_start,) = get_form_tags($attrib);

    $out  = '';
    $part = strtolower($attrib['part']);

    switch ($part) {
    case 'from':
        return $form_start . rcmail_compose_header_from($attrib);

    case 'to':
    case 'cc':
    case 'bcc':
        $fname  = '_' . $part;
        $header = $param = $part;

        $allow_attrib = array('id', 'class', 'style', 'cols', 'rows', 'tabindex');
        $field_type   = 'html_textarea';
        break;

    case 'replyto':
    case 'reply-to':
        $fname  = '_replyto';
        $param  = 'replyto';
        $header = 'reply-to';

    case 'followupto':
    case 'followup-to':
        if (!$fname) {
            $fname  = '_followupto';
            $param  = 'followupto';
            $header = 'mail-followup-to';
        }

        $allow_attrib = array('id', 'class', 'style', 'size', 'tabindex');
        $field_type   = 'html_inputfield';
        break;
    }

    if ($fname && $field_type) {
        // pass the following attributes to the form class
        $field_attrib = array('name' => $fname, 'spellcheck' => 'false');
        foreach ($attrib as $attr => $value) {
            if (in_array($attr, $allow_attrib)) {
                $field_attrib[$attr] = $value;
            }
        }

        // create teaxtarea object
        $input = new $field_type($field_attrib);
        $out   = $input->show(rcmail_compose_header_value($param));
    }

    if ($form_start) {
        $out = $form_start . $out;
    }

    // configure autocompletion
    $RCMAIL->autocomplete_init();

    return $out;
}


function rcmail_compose_header_from($attrib)
{
    global $MESSAGE, $OUTPUT, $RCMAIL, $COMPOSE;

    // pass the following attributes to the form class
    $field_attrib = array('name' => '_from');
    foreach ($attrib as $attr => $value) {
        if (in_array($attr, array('id', 'class', 'style', 'size', 'tabindex'))) {
            $field_attrib[$attr] = $value;
        }
    }

    if (count($MESSAGE->identities)) {
        $a_signatures = array();
        $identities   = array();
        $top_posting  = intval($RCMAIL->config->get('reply_mode')) > 0
            && !$RCMAIL->config->get('sig_below')
            && ($COMPOSE['mode'] == RCUBE_COMPOSE_REPLY || $COMPOSE['mode'] == RCUBE_COMPOSE_FORWARD);

        $separator     = $top_posting ? '---' : '-- ';
        $add_separator = (bool) $RCMAIL->config->get('sig_separator');

        $field_attrib['onchange'] = rcmail_output::JS_OBJECT_NAME.".change_identity(this)";
        $select_from = new html_select($field_attrib);

        // create SELECT element
        foreach ($MESSAGE->identities as $sql_arr) {
            $identity_id = $sql_arr['identity_id'];
            $select_from->add(format_email_recipient($sql_arr['email'], $sql_arr['name']), $identity_id);

            // add signature to array
            if (!empty($sql_arr['signature']) && empty($COMPOSE['param']['nosig'])) {
                $text = $html = $sql_arr['signature'];

                if ($sql_arr['html_signature']) {
                    $text = $RCMAIL->html2text($html, array('links' => false));
                    $text = trim($text, "\r\n");
                }
                else {
                    $t2h  = new rcube_text2html($text, false);
                    $html = $t2h->get_html();
                }

                if ($add_separator && !preg_match('/^--[ -]\r?\n/m', $text)) {
                    $text = $separator . "\n" . ltrim($text, "\r\n");
                    $html = $separator . "<br>" . $html;
                }

                $a_signatures[$identity_id]['text'] = $text;
                $a_signatures[$identity_id]['html'] = $html;
            }

            // add bcc and reply-to
            if (!empty($sql_arr['reply-to'])) {
                $identities[$identity_id]['replyto'] = $sql_arr['reply-to'];
            }
            if (!empty($sql_arr['bcc'])) {
                $identities[$identity_id]['bcc'] = $sql_arr['bcc'];
            }

            $identities[$identity_id]['email'] = $sql_arr['email'];
        }

        $out = $select_from->show($MESSAGE->compose['from']);

        // add signatures to client
        $OUTPUT->set_env('signatures', $a_signatures);
        $OUTPUT->set_env('identities', $identities);
    }
    // no identities, display text input field
    else {
        $field_attrib['class'] = 'from_address';
        $input_from = new html_inputfield($field_attrib);
        $out = $input_from->show($MESSAGE->compose['from']);
    }

    return $out;
}

function rcmail_compose_header_value($header)
{
    global $COMPOSE, $MESSAGE;

    $RCMAIL        = rcube::get_instance();
    $fvalue        = '';
    $decode_header = true;
    $charset       = $MESSAGE->headers->charset;
    $separator     = trim($RCMAIL->config->get('recipients_separator', ',')) . ' ';

    // we have a set of recipients stored is session
    if ($header == 'to' && ($mailto_id = $COMPOSE['param']['mailto'])
        && $_SESSION['mailto'][$mailto_id]
    ) {
        $fvalue        = urldecode($_SESSION['mailto'][$mailto_id]);
        $decode_header = false;
        $charset       = $RCMAIL->output->charset;

        // make session to not grow up too much
        unset($_SESSION['mailto'][$mailto_id]);
        $COMPOSE['param']['to'] = $fvalue;
    }
    else if (!empty($_POST['_' . $header])) {
        $fvalue  = rcube_utils::get_input_value('_' . $header, rcube_utils::INPUT_POST, true);
        $charset = $RCMAIL->output->charset;
    }
    else if (!empty($COMPOSE['param'][$header])) {
        $fvalue  = $COMPOSE['param'][$header];
        $charset = $RCMAIL->output->charset;
    }
    else if ($COMPOSE['mode'] == RCUBE_COMPOSE_REPLY) {
        // get recipent address(es) out of the message headers
        if ($header == 'to') {
            $mailfollowup = $MESSAGE->headers->others['mail-followup-to'];
            $mailreplyto  = $MESSAGE->headers->others['mail-reply-to'];

            // Reply to mailing list...
            if ($MESSAGE->reply_all == 'list' && $mailfollowup) {
                $fvalue = $mailfollowup;
            }
            else if ($MESSAGE->reply_all == 'list'
                && preg_match('/<mailto:([^>]+)>/i', $MESSAGE->headers->others['list-post'], $m)
            ) {
                $fvalue = $m[1];
            }
            // Reply to...
            else if ($MESSAGE->reply_all && $mailfollowup) {
                $fvalue = $mailfollowup;
            }
            else if ($mailreplyto) {
                $fvalue = $mailreplyto;
            }
            else if (!empty($MESSAGE->headers->replyto)) {
                $fvalue  = $MESSAGE->headers->replyto;
                $replyto = true;
            }
            else if (!empty($MESSAGE->headers->from)) {
                $fvalue = $MESSAGE->headers->from;
            }

            // Reply to message sent by yourself (#1487074, #1489230, #1490439)
            // Reply-To address need to be unset (#1490233)
            if (!empty($MESSAGE->compose['ident']) && empty($replyto)) {
                foreach (array($fvalue, $MESSAGE->headers->from) as $sender) {
                    $senders = rcube_mime::decode_address_list($sender, null, false, $charset, true);

                    if (in_array($MESSAGE->compose['ident']['email_ascii'], $senders)) {
                        $fvalue = $MESSAGE->headers->to;
                        break;
                    }
                }
            }
        }
        // add recipient of original message if reply to all
        else if ($header == 'cc' && !empty($MESSAGE->reply_all) && $MESSAGE->reply_all != 'list') {
            if ($v = $MESSAGE->headers->to) {
                $fvalue .= $v;
            }
            if ($v = $MESSAGE->headers->cc) {
                $fvalue .= (!empty($fvalue) ? $separator : '') . $v;
            }
            // Use Sender header (#1489011)
            if ($v = $MESSAGE->headers->get('Sender', false)) {
                // Skip common mailing lists addresses: *-bounces@ and *-request@ (#1490452)
                if (empty($MESSAGE->headers->others['list-post']) || !preg_match('/-(bounces|request)@/', $v)) {
                    $fvalue .= (!empty($fvalue) ? $separator : '') . $v;
                }
            }

            // When To: and Reply-To: are the same we add From: address to the list (#1489037)
            if ($v = $MESSAGE->headers->from) {
                $from    = rcube_mime::decode_address_list($v, null, false, $charset, true);
                $to      = rcube_mime::decode_address_list($MESSAGE->headers->to, null, false, $charset, true);
                $replyto = rcube_mime::decode_address_list($MESSAGE->headers->replyto, null, false, $charset, true);

                if (count($replyto) && !count(array_diff($to, $replyto)) && count(array_diff($from, $to))) {
                    $fvalue .= (!empty($fvalue) ? $separator : '') . $v;
                }
            }
        }
    }
    else if (in_array($COMPOSE['mode'], array(RCUBE_COMPOSE_DRAFT, RCUBE_COMPOSE_EDIT))) {
        // get drafted headers
        if ($header == 'to' && !empty($MESSAGE->headers->to)) {
            $fvalue = $MESSAGE->get_header('to', true);
        }
        else if ($header == 'cc' && !empty($MESSAGE->headers->cc)) {
            $fvalue = $MESSAGE->get_header('cc', true);
        }
        else if ($header == 'bcc' && !empty($MESSAGE->headers->bcc)) {
            $fvalue = $MESSAGE->get_header('bcc', true);
        }
        else if ($header == 'replyto' && !empty($MESSAGE->headers->others['mail-reply-to'])) {
            $fvalue = $MESSAGE->get_header('mail-reply-to');
        }
        else if ($header == 'replyto' && !empty($MESSAGE->headers->replyto)) {
            $fvalue = $MESSAGE->get_header('reply-to');
        }
        else if ($header == 'followupto' && !empty($MESSAGE->headers->others['mail-followup-to'])) {
            $fvalue = $MESSAGE->get_header('mail-followup-to');
        }
    }

    // split recipients and put them back together in a unique way
    if (!empty($fvalue) && in_array($header, array('to', 'cc', 'bcc'))) {
        $from_email   = @mb_strtolower($MESSAGE->compose['ident']['email']);
        $to_addresses = rcube_mime::decode_address_list($fvalue, null, $decode_header, $charset);
        $fvalue       = array();

        foreach ($to_addresses as $addr_part) {
            if (empty($addr_part['mailto'])) {
                continue;
            }

            // According to RFC5321 local part of email address is case-sensitive
            // however, here it is better to compare addresses in case-insensitive manner
            $mailto    = format_email(rcube_utils::idn_to_utf8($addr_part['mailto']));
            $mailto_lc = mb_strtolower($addr_part['mailto']);

            if (($header == 'to' || $COMPOSE['mode'] != RCUBE_COMPOSE_REPLY || $mailto_lc != $from_email)
                && !in_array($mailto_lc, (array) $MESSAGE->recipients)
            ) {
                if ($addr_part['name'] && $mailto != $addr_part['name']) {
                    $mailto = format_email_recipient($mailto, $addr_part['name']);
                }

                $fvalue[]              = $mailto;
                $MESSAGE->recipients[] = $mailto_lc;
            }
        }

        $fvalue = implode($separator, $fvalue);
    }

    return $fvalue;
}

function rcmail_compose_editor_mode()
{
    global $RCMAIL, $COMPOSE;
    static $useHtml;

    if ($useHtml !== null) {
        return $useHtml;
    }

    $html_editor  = intval($RCMAIL->config->get('htmleditor'));
    $compose_mode = $COMPOSE['mode'];

    if (is_bool($COMPOSE['param']['html'])) {
        $useHtml = $COMPOSE['param']['html'];
    }
    else if (isset($_POST['_is_html'])) {
        $useHtml = !empty($_POST['_is_html']);
    }
    else if ($compose_mode == RCUBE_COMPOSE_DRAFT || $compose_mode == RCUBE_COMPOSE_EDIT) {
        $useHtml = rcmail_message_is_html();
    }
    else if ($compose_mode == RCUBE_COMPOSE_REPLY) {
        $useHtml = $html_editor == 1 || ($html_editor >= 2 && rcmail_message_is_html());
    }
    else if ($compose_mode == RCUBE_COMPOSE_FORWARD) {
        $useHtml = $html_editor == 1 || $html_editor == 4
            || ($html_editor == 3 && rcmail_message_is_html());
    }
    else {
        $useHtml = $html_editor == 1 || $html_editor == 4;
    }

    return $useHtml;
}

function rcmail_message_is_html()
{
    global $RCMAIL, $MESSAGE;

    return $RCMAIL->config->get('prefer_html') && ($MESSAGE instanceof rcube_message) && $MESSAGE->has_html_part(true);
}

function rcmail_prepare_message_body()
{
    global $RCMAIL, $MESSAGE, $COMPOSE, $HTML_MODE;

    // use posted message body
    if (!empty($_POST['_message'])) {
        $body   = rcube_utils::get_input_value('_message', rcube_utils::INPUT_POST, true);
        $isHtml = (bool) rcube_utils::get_input_value('_is_html', rcube_utils::INPUT_POST);
    }
    else if ($COMPOSE['param']['body']) {
        $body   = $COMPOSE['param']['body'];
        $isHtml = (bool) $COMPOSE['param']['html'];
    }
    // forward as attachment
    else if ($COMPOSE['mode'] == RCUBE_COMPOSE_FORWARD && $COMPOSE['as_attachment']) {
        $isHtml = rcmail_compose_editor_mode();
        $body   = '';

        rcmail_write_forward_attachments();
    }
    // reply/edit/draft/forward
    else if ($COMPOSE['mode'] && ($COMPOSE['mode'] != RCUBE_COMPOSE_REPLY || intval($RCMAIL->config->get('reply_mode')) != -1)) {
        $isHtml   = rcmail_compose_editor_mode();
        $messages = array();

        if (!empty($MESSAGE->parts)) {
            // collect IDs of message/rfc822 parts
            foreach ($MESSAGE->mime_parts() as $part) {
                if ($part->mimetype == 'message/rfc822') {
                    $messages[] = $part->mime_id;
                }
            }

            foreach ($MESSAGE->parts as $part) {
                if ($part->realtype == 'multipart/encrypted') {
                    // find the encrypted message payload part
                    if ($pgp_mime_part = $MESSAGE->get_multipart_encrypted_part()) {
                        $RCMAIL->output->set_env('pgp_mime_message', array(
                            '_mbox' => $RCMAIL->storage->get_folder(),
                            '_uid'  => $MESSAGE->uid,
                            '_part' => $pgp_mime_part->mime_id,
                        ));
                    }
                    continue;
                }

                // skip no-content and attachment parts (#1488557)
                if ($part->type != 'content' || !$part->size || $MESSAGE->is_attachment($part)) {
                    continue;
                }

                // skip all content parts inside the message/rfc822 part
                foreach ($messages as $mimeid) {
                    if (strpos($part->mime_id, $mimeid . '.') === 0) {
                        continue 2;
                    }
                }

                if ($part_body = rcmail_compose_part_body($part, $isHtml)) {
                    $body .= ($body ? ($isHtml ? '<br/>' : "\n") : '') . $part_body;
                }
            }
        }
        else {
            $body = rcmail_compose_part_body($MESSAGE, $isHtml);
        }

        // compose reply-body
        if ($COMPOSE['mode'] == RCUBE_COMPOSE_REPLY) {
            $body = rcmail_create_reply_body($body, $isHtml);

            if ($MESSAGE->pgp_mime) {
                $RCMAIL->output->set_env('compose_reply_header', rcmail_get_reply_header($MESSAGE));
            }
        }
        // forward message body inline
        else if ($COMPOSE['mode'] == RCUBE_COMPOSE_FORWARD) {
            $body = rcmail_create_forward_body($body, $isHtml);
        }
        // load draft message body
        else if ($COMPOSE['mode'] == RCUBE_COMPOSE_DRAFT || $COMPOSE['mode'] == RCUBE_COMPOSE_EDIT) {
            $body = rcmail_create_draft_body($body, $isHtml);
        }
    }
    else { // new message
        $isHtml = rcmail_compose_editor_mode();
    }

    $plugin = $RCMAIL->plugins->exec_hook('message_compose_body',
        array('body' => $body, 'html' => $isHtml, 'mode' => $COMPOSE['mode']));

    $body = $plugin['body'];
    unset($plugin);

    // add blocked.gif attachment (#1486516)
    $regexp = '# src="program/resources/blocked\.gif"#';
    if ($isHtml && preg_match($regexp, $body)) {
        $content = $RCMAIL->get_resource_content('blocked.gif');

        if ($content && ($attachment = rcmail_save_image('blocked.gif', 'image/gif', $content))) {
            $COMPOSE['attachments'][$attachment['id']] = $attachment;
            $url = sprintf('%s&_id=%s&_action=display-attachment&_file=rcmfile%s',
                $RCMAIL->comm_path, $COMPOSE['id'], $attachment['id']);
            $body = preg_replace($regexp, ' src="' . $url . '"', $body);
        }
    }

    $HTML_MODE = $isHtml;

    return $body;
}

function rcmail_compose_part_body($part, $isHtml = false)
{
    global $RCMAIL, $COMPOSE, $MESSAGE, $LINE_LENGTH;

    // Check if we have enough memory to handle the message in it
    // #1487424: we need up to 10x more memory than the body
    if (!rcube_utils::mem_check($part->size * 10)) {
        return '';
    }

    // fetch part if not available
    $body = $MESSAGE->get_part_body($part->mime_id, true);

    // message is cached but not exists (#1485443), or other error
    if ($body === false) {
        return '';
    }

    // register this part as pgp encrypted
    if (strpos($body, '-----BEGIN PGP MESSAGE-----') !== false) {
        $MESSAGE->pgp_mime = true;
        $RCMAIL->output->set_env('pgp_mime_message', array(
            '_mbox' => $RCMAIL->storage->get_folder(), '_uid' => $MESSAGE->uid, '_part' => $part->mime_id,
        ));
    }

    if ($isHtml) {
        if ($part->ctype_secondary == 'html') {
        }
        else if ($part->ctype_secondary == 'enriched') {
            $body = rcube_enriched::to_html($body);
        }
        else {
            // try to remove the signature
            if ($COMPOSE['mode'] != RCUBE_COMPOSE_DRAFT && $COMPOSE['mode'] != RCUBE_COMPOSE_EDIT) {
                if ($RCMAIL->config->get('strip_existing_sig', true)) {
                    $body = rcmail_remove_signature($body);
                }
            }

            // add HTML formatting
            $body = rcmail_plain_body($body, $part->ctype_parameters['format'] == 'flowed');
        }
    }
    else {
        if ($part->ctype_secondary == 'enriched') {
            $body = rcube_enriched::to_html($body);
            $part->ctype_secondary = 'html';
        }

        if ($part->ctype_secondary == 'html') {
            // use html part if it has been used for message (pre)viewing
            // decrease line length for quoting
            $len  = $COMPOSE['mode'] == RCUBE_COMPOSE_REPLY ? $LINE_LENGTH-2 : $LINE_LENGTH;
            $body = $RCMAIL->html2text($body, array('width' => $len));
        }
        else {
            if ($part->ctype_secondary == 'plain' && $part->ctype_parameters['format'] == 'flowed') {
                $body = rcube_mime::unfold_flowed($body);
            }

            // try to remove the signature
            if ($COMPOSE['mode'] != RCUBE_COMPOSE_DRAFT && $COMPOSE['mode'] != RCUBE_COMPOSE_EDIT) {
                if ($RCMAIL->config->get('strip_existing_sig', true)) {
                    $body = rcmail_remove_signature($body);
                }
            }
        }
    }

    return $body;
}

function rcmail_compose_body($attrib)
{
    global $RCMAIL, $OUTPUT, $HTML_MODE, $MESSAGE_BODY;

    list($form_start, $form_end) = get_form_tags($attrib);
    unset($attrib['form']);

    if (empty($attrib['id'])) {
        $attrib['id'] = 'rcmComposeBody';
    }

    // If desired, set this textarea to be editable by TinyMCE
    if ($HTML_MODE) {
        $attrib['class'] = 'mce_editor';
    }

    $attrib['name'] = '_message';

    $textarea = new html_textarea($attrib);
    $hidden   = new html_hiddenfield();

    $hidden->add(array('name' => '_draft_saveid', 'value' => $RCMAIL->output->get_env('draft_id')));
    $hidden->add(array('name' => '_draft', 'value' => ''));
    $hidden->add(array('name' => '_is_html', 'value' => $HTML_MODE ? "1" : "0"));
    $hidden->add(array('name' => '_framed', 'value' => '1'));

    $OUTPUT->set_env('composebody', $attrib['id']);

    // include HTML editor
    $RCMAIL->html_editor();

    // Set language list
    if ($RCMAIL->config->get('enable_spellcheck')) {
        $engine           = new rcube_spellchecker();
        $dictionary       = (bool) $RCMAIL->config->get('spellcheck_dictionary');
        $spellcheck_langs = $engine->languages();
        $lang             = $_SESSION['language'];

        // if not found in the list, try with two-letter code
        if (!$spellcheck_langs[$lang]) {
            $lang = strtolower(substr($lang, 0, 2));
        }

        if (!$spellcheck_langs[$lang]) {
            $lang = 'en';
        }

        $editor_lang_set = array();
        foreach ($spellcheck_langs as $key => $name) {
            $editor_lang_set[] = ($key == $lang ? '+' : '') . rcube::JQ($name).'='.rcube::JQ($key);
        }

        // include GoogieSpell
        $OUTPUT->include_script('googiespell.js');
        $OUTPUT->add_script(sprintf(
            "var googie = new GoogieSpell('%s/images/googiespell/','%s&lang=', %s);\n".
            "googie.lang_chck_spell = \"%s\";\n".
            "googie.lang_rsm_edt = \"%s\";\n".
            "googie.lang_close = \"%s\";\n".
            "googie.lang_revert = \"%s\";\n".
            "googie.lang_no_error_found = \"%s\";\n".
            "googie.lang_learn_word = \"%s\";\n".
            "googie.setLanguages(%s);\n".
            "googie.setCurrentLanguage('%s');\n".
            "googie.setDecoration(false);\n".
            "googie.decorateTextarea('%s');\n",
            $RCMAIL->output->asset_url($RCMAIL->output->get_skin_path()),
            $RCMAIL->url(array('_task' => 'utils', '_action' => 'spell', '_remote' => 1)),
                !empty($dictionary) ? 'true' : 'false',
            rcube::JQ(rcube::Q($RCMAIL->gettext('checkspelling'))),
            rcube::JQ(rcube::Q($RCMAIL->gettext('resumeediting'))),
            rcube::JQ(rcube::Q($RCMAIL->gettext('close'))),
            rcube::JQ(rcube::Q($RCMAIL->gettext('revertto'))),
            rcube::JQ(rcube::Q($RCMAIL->gettext('nospellerrors'))),
            rcube::JQ(rcube::Q($RCMAIL->gettext('addtodict'))),
            rcube_output::json_serialize($spellcheck_langs),
            $lang,
            $attrib['id']), 'foot');

        $OUTPUT->add_label('checking');
        $OUTPUT->set_env('spellcheck_langs', join(',', $editor_lang_set));
        $OUTPUT->set_env('spell_langs', $spellcheck_langs);
        $OUTPUT->set_env('spell_lang', $lang);
    }

    return ($form_start ? "$form_start\n" : '')
        . "\n" . $hidden->show() . "\n" . $textarea->show($MESSAGE_BODY)
        . ($form_end ? "\n$form_end\n" : '')
        . '<iframe name="savetarget" src="program/resources/blank.gif"'
        . ' style="width:0;height:0;border:none;visibility:hidden;" aria-hidden="true"></iframe>';
}


function rcmail_create_reply_body($body, $bodyIsHtml)
{
    global $RCMAIL, $MESSAGE, $LINE_LENGTH;

    $prefix     = rcmail_get_reply_header($MESSAGE);
    $reply_mode = intval($RCMAIL->config->get('reply_mode'));

    if (!$bodyIsHtml) {
        // soft-wrap and quote message text
        $body = rcmail_wrap_and_quote($body, $LINE_LENGTH);

        $prefix .= "\n";

        if ($reply_mode > 0) { // top-posting
            $prefix = "\n\n\n" . $prefix;
            $suffix = '';
        }
        else {
            $suffix = "\n";
        }
    }
    else {
        // save inline images to files
        $cid_map = rcmail_write_inline_attachments($MESSAGE);
        // set is_safe flag (we need this for html body washing)
        rcmail_check_safe($MESSAGE);
        // clean up html tags
        $body = rcmail_wash_html($body, array('safe' => $MESSAGE->is_safe), $cid_map);

        // build reply (quote content)
        $prefix = '<p>' . rcube::Q($prefix) . "</p>\n";
        $prefix .= '<blockquote>';

        if ($reply_mode > 0) { // top-posting
            $prefix = '<br>' . $prefix;
            $suffix = '</blockquote>';
        }
        else {
            $suffix = '</blockquote><p><br/></p>';
        }
    }

    return $prefix . $body . $suffix;
}

function rcmail_get_reply_header($message)
{
    global $RCMAIL;

    $from = array_pop(rcube_mime::decode_address_list($message->get_header('from'), 1, false, $message->headers->charset));
    return $RCMAIL->gettext(array(
        'name' => 'mailreplyintro',
        'vars' => array(
            'date'   => $RCMAIL->format_date($message->headers->date, $RCMAIL->config->get('date_long')),
            'sender' => $from['name'] ?: rcube_utils::idn_to_utf8($from['mailto']),
        )
    ));
}

function rcmail_create_forward_body($body, $bodyIsHtml)
{
    global $RCMAIL, $MESSAGE, $COMPOSE;

    // add attachments
    if (!isset($COMPOSE['forward_attachments']) && is_array($MESSAGE->mime_parts)) {
        $cid_map = rcmail_write_compose_attachments($MESSAGE, $bodyIsHtml);
    }

    $date = $RCMAIL->format_date($MESSAGE->headers->date, $RCMAIL->config->get('date_long'));

    if (!$bodyIsHtml) {
        $prefix = "\n\n\n-------- " . $RCMAIL->gettext('originalmessage') . " --------\n";
        $prefix .= $RCMAIL->gettext('subject') . ': ' . $MESSAGE->subject . "\n";
        $prefix .= $RCMAIL->gettext('date')    . ': ' . $date . "\n";
        $prefix .= $RCMAIL->gettext('from')    . ': ' . $MESSAGE->get_header('from') . "\n";
        $prefix .= $RCMAIL->gettext('to')      . ': ' . $MESSAGE->get_header('to') . "\n";

        if ($cc = $MESSAGE->headers->get('cc')) {
            $prefix .= $RCMAIL->gettext('cc') . ': ' . $cc . "\n";
        }
        if (($replyto = $MESSAGE->headers->get('reply-to')) && $replyto != $MESSAGE->get_header('from')) {
            $prefix .= $RCMAIL->gettext('replyto') . ': ' . $replyto . "\n";
        }

        $prefix .= "\n";
        $body = trim($body, "\r\n");
    }
    else {
        // set is_safe flag (we need this for html body washing)
        rcmail_check_safe($MESSAGE);

        // clean up html tags
        $body = rcmail_wash_html($body, array('safe' => $MESSAGE->is_safe), $cid_map);

        $prefix = sprintf(
            "<br /><p>-------- " . $RCMAIL->gettext('originalmessage') . " --------</p>" .
            "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tbody>" .
            "<tr><th align=\"right\" nowrap=\"nowrap\" valign=\"baseline\">%s: </th><td>%s</td></tr>" .
            "<tr><th align=\"right\" nowrap=\"nowrap\" valign=\"baseline\">%s: </th><td>%s</td></tr>" .
            "<tr><th align=\"right\" nowrap=\"nowrap\" valign=\"baseline\">%s: </th><td>%s</td></tr>" .
            "<tr><th align=\"right\" nowrap=\"nowrap\" valign=\"baseline\">%s: </th><td>%s</td></tr>",
            $RCMAIL->gettext('subject'), rcube::Q($MESSAGE->subject),
            $RCMAIL->gettext('date'), rcube::Q($date),
            $RCMAIL->gettext('from'), rcube::Q($MESSAGE->get_header('from'), 'replace'),
            $RCMAIL->gettext('to'), rcube::Q($MESSAGE->get_header('to'), 'replace'));

        if ($cc = $MESSAGE->headers->get('cc'))
            $prefix .= sprintf("<tr><th align=\"right\" nowrap=\"nowrap\" valign=\"baseline\">%s: </th><td>%s</td></tr>",
                $RCMAIL->gettext('cc'), rcube::Q($cc, 'replace'));

        if (($replyto = $MESSAGE->headers->get('reply-to')) && $replyto != $MESSAGE->get_header('from'))
            $prefix .= sprintf("<tr><th align=\"right\" nowrap=\"nowrap\" valign=\"baseline\">%s: </th><td>%s</td></tr>",
                $RCMAIL->gettext('replyto'), rcube::Q($replyto, 'replace'));

        $prefix .= "</tbody></table><br>";
    }

    return $prefix . $body;
}


function rcmail_create_draft_body($body, $bodyIsHtml)
{
    global $MESSAGE, $COMPOSE;

    // add attachments
    // sizeof($MESSAGE->mime_parts can be 1 - e.g. attachment, but no text!
    if (empty($COMPOSE['forward_attachments'])
        && is_array($MESSAGE->mime_parts)
        && count($MESSAGE->mime_parts) > 0
    ) {
        $cid_map = rcmail_write_compose_attachments($MESSAGE, $bodyIsHtml);
    }

    // clean up HTML tags - XSS prevention (#1489251)
    if ($bodyIsHtml) {
        $body = rcmail_wash_html($body, array('safe' => 1), $cid_map);

        // cleanup
        $body = preg_replace(array(
                // remove comments (produced by washtml)
                '/<!--[^>]+-->/',
                // remove <body> tags
                '/<body([^>]*)>/i',
                '/<\/body>/i',
                // convert TinyMCE's empty-line sequence (#1490463)
                '/<p>\xC2\xA0<\/p>/',
            ),
            array(
                '',
                '',
                '',
                '<p><br /></p>',
            ),
            $body
        );

        // replace cid with href in inline images links
        if (!empty($cid_map)) {
            $body = str_replace(array_keys($cid_map), array_values($cid_map), $body);
        }
    }

    return $body;
}


function rcmail_remove_signature($body)
{
    global $RCMAIL;

    $body = str_replace("\r\n", "\n", $body);
    $len  = strlen($body);
    $sig_max_lines = $RCMAIL->config->get('sig_max_lines', 15);

    while (($sp = strrpos($body, "-- \n", $sp ? -$len+$sp-1 : 0)) !== false) {
        if ($sp == 0 || $body[$sp-1] == "\n") {
            // do not touch blocks with more that X lines
            if (substr_count($body, "\n", $sp) < $sig_max_lines) {
                $body = substr($body, 0, max(0, $sp-1));
            }
            break;
        }
    }

    return $body;
}


function rcmail_write_compose_attachments(&$message, $bodyIsHtml)
{
    global $RCMAIL, $COMPOSE;

    $loaded_attachments = array();
    foreach ((array)$COMPOSE['attachments'] as $attachment) {
        $loaded_attachments[$attachment['name'] . $attachment['mimetype']] = $attachment;
    }

    $cid_map  = array();
    $messages = array();

    if ($message->pgp_mime) {
        return $cid_map;
    }

    foreach ((array) $message->mime_parts() as $pid => $part) {
        if ($part->mimetype == 'message/rfc822') {
            $messages[] = $part->mime_id;
        }

        if ($part->disposition == 'attachment' || ($part->disposition == 'inline' && $bodyIsHtml) || $part->filename) {
            // skip parts that aren't valid attachments
            if ($part->ctype_primary == 'multipart' || $part->mimetype == 'application/ms-tnef') {
                continue;
            }

            // skip message attachments in reply mode
            if ($part->ctype_primary == 'message' && $COMPOSE['mode'] == RCUBE_COMPOSE_REPLY) {
                continue;
            }

            // skip inline images when forwarding in text mode
            if ($part->content_id && $part->disposition == 'inline' && !$bodyIsHtml && $COMPOSE['mode'] == RCUBE_COMPOSE_FORWARD) {
                continue;
            }

            // skip version.txt parts of multipart/encrypted messages
            if ($message->pgp_mime && $part->mimetype == 'application/pgp-encrypted' && $part->filename == 'version.txt') {
                continue;
            }

            // skip attachments included in message/rfc822 attachment (#1486487, #1490607)
            foreach ($messages as $mimeid) {
                if (strpos($part->mime_id, $mimeid . '.') === 0) {
                    continue 2;
                }
            }

            if (($attachment = $loaded_attachments[rcmail_attachment_name($part) . $part->mimetype])
                || ($attachment = rcmail_save_attachment($message, $pid, $COMPOSE['id']))
            ) {
                if ($bodyIsHtml && ($part->content_id || $part->content_location)) {
                    $url = sprintf('%s&_id=%s&_action=display-attachment&_file=rcmfile%s',
                        $RCMAIL->comm_path, $COMPOSE['id'], $attachment['id']);

                    if ($part->content_id)
                        $cid_map['cid:'.$part->content_id] = $url;
                    else
                        $cid_map[$part->content_location] = $url;
                }
            }
        }
    }

    $COMPOSE['forward_attachments'] = true;

    return $cid_map;
}


function rcmail_write_inline_attachments(&$message)
{
    global $RCMAIL, $COMPOSE;

    $cid_map  = array();
    $messages = array();

    if ($message->pgp_mime) {
        return $cid_map;
    }

    foreach ((array) $message->mime_parts() as $pid => $part) {
        if ($part->mimetype == 'message/rfc822') {
            $messages[] = $part->mime_id;
        }

        if (($part->content_id || $part->content_location) && $part->filename) {
            // skip attachments included in message/rfc822 attachment (#1486487, #1490607)
            foreach ($messages as $mimeid) {
                if (strpos($part->mime_id, $mimeid . '.') === 0) {
                    continue 2;
                }
            }

            if ($attachment = rcmail_save_attachment($message, $pid, $COMPOSE['id'])) {
                $url = sprintf('%s&_id=%s&_action=display-attachment&_file=rcmfile%s',
                    $RCMAIL->comm_path, $COMPOSE['id'], $attachment['id']);

                if ($part->content_id)
                    $cid_map['cid:'.$part->content_id] = $url;
                else
                    $cid_map[$part->content_location] = $url;
            }
        }
    }

    return $cid_map;
}

// Creates attachment(s) from the forwarded message(s)
function rcmail_write_forward_attachments()
{
    global $RCMAIL, $COMPOSE, $MESSAGE;

    $storage = $RCMAIL->get_storage();
    $names   = array();
    $refs    = array();

    if ($MESSAGE->pgp_mime) {
        return;
    }

    $loaded_attachments = array();
    foreach ((array)$COMPOSE['attachments'] as $attachment) {
        $loaded_attachments[$attachment['name'] . $attachment['mimetype']] = $attachment;
    }

    if ($COMPOSE['forward_uid'] == '*') {
        $index = $storage->index(null, rcmail_sort_column(), rcmail_sort_order());
        $COMPOSE['forward_uid'] = $index->get();
    }
    else if (!is_array($COMPOSE['forward_uid']) && strpos($COMPOSE['forward_uid'], ':')) {
        $COMPOSE['forward_uid'] = rcube_imap_generic::uncompressMessageSet($COMPOSE['forward_uid']);
    }
    else if (is_string($COMPOSE['forward_uid'])) {
        $COMPOSE['forward_uid'] = explode(',', $COMPOSE['forward_uid']);
    }

    foreach ((array)$COMPOSE['forward_uid'] as $uid) {
        $message = new rcube_message($uid);

        if (empty($message->headers)) {
            continue;
        }

        if (!empty($message->headers->charset)) {
            $storage->set_charset($message->headers->charset);
        }

        if (empty($MESSAGE->subject)) {
            $MESSAGE->subject = $message->subject;
        }

        // generate (unique) attachment name
        $name = strlen($message->subject) ? mb_substr($message->subject, 0, 64) : 'message_rfc822';
        if (!empty($names[$name])) {
            $names[$name]++;
            $name .= '_' . $names[$name];
        }
        $names[$name] = 1;
        $name .= '.eml';

        if (!empty($loaded_attachments[$name . 'message/rfc822'])) {
            continue;
        }

        rcmail_save_attachment($message, null, $COMPOSE['id'], array('filename' => $name));

        if ($message->headers->messageID) {
            $refs[] = $message->headers->messageID;
        }
    }

    // set In-Reply-To and References headers
    if (count($refs) == 1) {
        $COMPOSE['reply_msgid'] = $refs[0];
    }
    if (!empty($refs)) {
        $COMPOSE['references'] = implode(' ', $refs);
    }
}

function rcmail_save_image($path, $mimetype = '', $data = null)
{
    global $COMPOSE;

    // handle attachments in memory
    if (empty($data)) {
        $data    = file_get_contents($path);
        $is_file = true;
    }

    $name = rcmail_basename($path);

    if (empty($mimetype)) {
        if ($is_file) {
            $mimetype = rcube_mime::file_content_type($path, $name);
        }
        else {
            $mimetype = rcube_mime::file_content_type($data, $name, 'application/octet-stream', true);
        }
    }

    $attachment = array(
        'group'    => $COMPOSE['id'],
        'name'     => $name,
        'mimetype' => $mimetype,
        'data'     => $data,
        'size'     => strlen($data),
    );

    $attachment = rcmail::get_instance()->plugins->exec_hook('attachment_save', $attachment);

    if ($attachment['status']) {
        unset($attachment['data'], $attachment['status'], $attachment['content_id'], $attachment['abort']);
        return $attachment;
    }

    return false;
}

function rcmail_basename($filename)
{
    // basename() is not unicode safe and locale dependent
    if (stristr(PHP_OS, 'win') || stristr(PHP_OS, 'netware')) {
        return preg_replace('/^.*[\\\\\\/]/', '', $filename);
    }
    else {
        return preg_replace('/^.*[\/]/', '', $filename);
    }
}

/**
 * Creates reply subject by removing common subject
 * prefixes/suffixes from the original message subject
 */
function rcmail_reply_subject($subject)
{
    $subject = trim($subject);

    // replace Re:, Re[x]:, Re-x (#1490497)
    $prefix = '/^(re:|re\[\d\]:|re-\d:)\s*/i';
    do {
        $subject = preg_replace($prefix, '', $subject, -1, $count);
    }
    while ($count);

    // replace (was: ...) (#1489375)
    $subject = preg_replace('/\s*\([wW]as:[^\)]+\)\s*$/', '', $subject);

    return 'Re: ' . $subject;
}

function rcmail_compose_subject($attrib)
{
    global $MESSAGE, $COMPOSE;

    list($form_start, $form_end) = get_form_tags($attrib);
    unset($attrib['form']);

    $attrib['name']       = '_subject';
    $attrib['spellcheck'] = 'true';

    $textfield = new html_inputfield($attrib);
    $subject   = '';

    // use subject from post
    if (isset($_POST['_subject'])) {
        $subject = rcube_utils::get_input_value('_subject', rcube_utils::INPUT_POST, TRUE);
    }
    else if (!empty($COMPOSE['param']['subject'])) {
        $subject = $COMPOSE['param']['subject'];
    }
    // create a reply-subject
    else if ($COMPOSE['mode'] == RCUBE_COMPOSE_REPLY) {
        $subject = rcmail_reply_subject($MESSAGE->subject);
    }
    // create a forward-subject
    else if ($COMPOSE['mode'] == RCUBE_COMPOSE_FORWARD) {
        if (preg_match('/^fwd:/i', $MESSAGE->subject))
            $subject = $MESSAGE->subject;
        else
            $subject = 'Fwd: '.$MESSAGE->subject;
    }
    // creeate a draft-subject
    else if ($COMPOSE['mode'] == RCUBE_COMPOSE_DRAFT || $COMPOSE['mode'] == RCUBE_COMPOSE_EDIT) {
        $subject = $MESSAGE->subject;
    }

    $out = $form_start ? "$form_start\n" : '';
    $out .= $textfield->show($subject);
    $out .= $form_end ? "\n$form_end" : '';

    return $out;
}

function rcmail_compose_attachment_list($attrib)
{
    global $RCMAIL, $OUTPUT, $COMPOSE;

    // add ID if not given
    if (!$attrib['id'])
        $attrib['id'] = 'rcmAttachmentList';

    $out    = "\n";
    $jslist = array();
    $button = '';

    if ($attrib['icon_pos'] == 'left')
        $COMPOSE['icon_pos'] = 'left';

    if (is_array($COMPOSE['attachments'])) {
        if ($attrib['deleteicon']) {
            $button = html::img(array(
                'src' => $RCMAIL->output->abs_url($attrib['deleteicon'], true),
                'alt' => $RCMAIL->gettext('delete')
            ));
        }
        else if (rcube_utils::get_boolean($attrib['textbuttons'])) {
            $button = rcube::Q($RCMAIL->gettext('delete'));
        }

        foreach ($COMPOSE['attachments'] as $id => $a_prop) {
            if (empty($a_prop)) {
                continue;
            }

            $link_content = sprintf('%s <span class="attachment-size"> (%s)</span>',
                rcube::Q($a_prop['name']), $RCMAIL->show_bytes($a_prop['size']));

            $content_link = html::a(array(
                    'href'    => "#load",
                    'class'   => 'filename',
                    'onclick' => sprintf("return %s.command('load-attachment','rcmfile%s', this, event)", rcmail_output::JS_OBJECT_NAME, $id),
                ), $link_content);

            $delete_link = html::a(array(
                    'href'    => "#delete",
                    'title'   => $RCMAIL->gettext('delete'),
                    'onclick' => sprintf("return %s.command('remove-attachment','rcmfile%s', this, event)", rcmail_output::JS_OBJECT_NAME, $id),
                    'class'   => 'delete',
                    'tabindex' => $attrib['tabindex'] ?: '0',
                    'aria-label'   => $RCMAIL->gettext('delete') . ' ' . $a_prop['name'],
                ), $button);

            $out .= html::tag('li', array(
                    'id'          => 'rcmfile'.$id,
                    'class'       => rcube_utils::file2class($a_prop['mimetype'], $a_prop['name']),
                    'onmouseover' => "rcube_webmail.long_subject_title_ex(this, 0)",
                ),
                $COMPOSE['icon_pos'] == 'left' ? $delete_link.$content_link : $content_link.$delete_link
            );

            $jslist['rcmfile'.$id] = array(
                'name'     => $a_prop['name'],
                'complete' => true,
                'mimetype' => $a_prop['mimetype']
            );
        }
    }

    if ($attrib['deleteicon'])
        $COMPOSE['deleteicon'] = $RCMAIL->output->abs_url($attrib['deleteicon'], true);
    else if (rcube_utils::get_boolean($attrib['textbuttons']))
        $COMPOSE['textbuttons'] = true;
    if ($attrib['cancelicon'])
        $OUTPUT->set_env('cancelicon', $RCMAIL->output->abs_url($attrib['cancelicon'], true));
    if ($attrib['loadingicon'])
        $OUTPUT->set_env('loadingicon', $RCMAIL->output->abs_url($attrib['loadingicon'], true));

    $OUTPUT->set_env('attachments', $jslist);
    $OUTPUT->add_gui_object('attachmentlist', $attrib['id']);

    // put tabindex value into data-tabindex attribute
    if (isset($attrib['tabindex'])) {
        $attrib['data-tabindex'] = $attrib['tabindex'];
        unset($attrib['tabindex']);
    }

    return html::tag('ul', $attrib, $out, html::$common_attrib);
}


function rcmail_compose_attachment_form($attrib)
{
    global $RCMAIL;

    return $RCMAIL->upload_form($attrib, 'uploadform', 'send-attachment', array('multiple' => true));
}

function rcmail_priority_selector($attrib)
{
    global $RCMAIL, $MESSAGE;

    list($form_start, $form_end) = get_form_tags($attrib);
    unset($attrib['form']);

    $attrib['name'] = '_priority';
    $prio_list = array(
        $RCMAIL->gettext('lowest')  => 5,
        $RCMAIL->gettext('low')     => 4,
        $RCMAIL->gettext('normal')  => 0,
        $RCMAIL->gettext('high')    => 2,
        $RCMAIL->gettext('highest') => 1,
    );

    $selector = new html_select($attrib);
    $selector->add(array_keys($prio_list), array_values($prio_list));

    if (isset($_POST['_priority']))
        $sel = $_POST['_priority'];
    else if (isset($MESSAGE->headers->priority) && intval($MESSAGE->headers->priority) != 3)
        $sel = $MESSAGE->headers->priority;
    else
        $sel = 0;

    $out = $form_start ? "$form_start\n" : '';
    $out .= $selector->show((int) $sel);
    $out .= $form_end ? "\n$form_end" : '';

    return $out;
}


function rcmail_mdn_checkbox($attrib)
{
    global $RCMAIL, $MESSAGE;

    list($form_start, $form_end) = get_form_tags($attrib);
    unset($attrib['form']);

    if (!isset($attrib['id']))
        $attrib['id'] = 'receipt';

    $attrib['name']  = '_mdn';
    $attrib['value'] = '1';

    $checkbox = new html_checkbox($attrib);

    if (isset($_POST['_mdn']))
        $mdn_default = $_POST['_mdn'];
    else if (in_array($COMPOSE['mode'], array(RCUBE_COMPOSE_DRAFT, RCUBE_COMPOSE_EDIT)))
        $mdn_default = (bool) $MESSAGE->headers->mdn_to;
    else
        $mdn_default = $RCMAIL->config->get('mdn_default');

    $out = $form_start ? "$form_start\n" : '';
    $out .= $checkbox->show($mdn_default);
    $out .= $form_end ? "\n$form_end" : '';

    return $out;
}


function rcmail_dsn_checkbox($attrib)
{
    global $RCMAIL;

    list($form_start, $form_end) = get_form_tags($attrib);
    unset($attrib['form']);

    if (!isset($attrib['id']))
        $attrib['id'] = 'dsn';

    $attrib['name']  = '_dsn';
    $attrib['value'] = '1';

    $checkbox = new html_checkbox($attrib);

    if (isset($_POST['_dsn']))
        $dsn_value = (int) $_POST['_dsn'];
    else
        $dsn_value = $RCMAIL->config->get('dsn_default');

    $out = $form_start ? "$form_start\n" : '';
    $out .= $checkbox->show($dsn_value);
    $out .= $form_end ? "\n$form_end" : '';

    return $out;
}


function rcmail_editor_selector($attrib)
{
    global $RCMAIL;

    // determine whether HTML or plain text should be checked
    $useHtml = rcmail_compose_editor_mode();

    if (empty($attrib['editorid']))
        $attrib['editorid'] = 'rcmComposeBody';

    if (empty($attrib['name']))
        $attrib['name'] = 'editorSelect';

    $attrib['onchange'] = "return rcmail.command('toggle-editor', {id: '".$attrib['editorid']."', html: this.value == 'html'}, '', event)";

    $select = new html_select($attrib);

    $select->add(rcube::Q($RCMAIL->gettext('htmltoggle')), 'html');
    $select->add(rcube::Q($RCMAIL->gettext('plaintoggle')), 'plain');

    return $select->show($useHtml ? 'html' : 'plain');
}


function rcmail_store_target_selection($attrib)
{
    global $COMPOSE, $RCMAIL;

    $attrib['name'] = '_store_target';
    $select = $RCMAIL->folder_selector(array_merge($attrib, array(
        'noselection'   => '- ' . $RCMAIL->gettext('dontsave') . ' -',
        'folder_filter' => 'mail',
        'folder_rights' => 'w',
    )));

    return $select->show(isset($_POST['_store_target']) ? $_POST['_store_target'] : $COMPOSE['param']['sent_mbox'], $attrib);
}


function rcmail_check_sent_folder($folder, $create=false)
{
    global $RCMAIL;

    // we'll not save the message, so it doesn't matter
    if ($RCMAIL->config->get('no_save_sent_messages')) {
        return true;
    }

    if ($RCMAIL->storage->folder_exists($folder, true)) {
        return true;
    }

    // folder may exist but isn't subscribed (#1485241)
    if ($create) {
        if (!$RCMAIL->storage->folder_exists($folder))
            return $RCMAIL->storage->create_folder($folder, true);
        else
            return $RCMAIL->storage->subscribe($folder);
    }

    return false;
}


function get_form_tags($attrib)
{
    global $RCMAIL, $MESSAGE_FORM, $COMPOSE;

    $form_start = '';
    if (!$MESSAGE_FORM) {
        $hiddenfields = new html_hiddenfield(array('name' => '_task', 'value' => $RCMAIL->task));
        $hiddenfields->add(array('name' => '_action', 'value' => 'send'));
        $hiddenfields->add(array('name' => '_id', 'value' => $COMPOSE['id']));
        $hiddenfields->add(array('name' => '_attachments'));

        $form_start = empty($attrib['form']) ? $RCMAIL->output->form_tag(array('name' => "form", 'method' => "post")) : '';
        $form_start .= $hiddenfields->show();
    }

    $form_end  = ($MESSAGE_FORM && !strlen($attrib['form'])) ? '</form>' : '';
    $form_name = $attrib['form'] ?: 'form';

    if (!$MESSAGE_FORM)
        $RCMAIL->output->add_gui_object('messageform', $form_name);

    $MESSAGE_FORM = $form_name;

    return array($form_start, $form_end);
}


function rcmail_addressbook_list($attrib = array())
{
    global $RCMAIL, $OUTPUT;

    $attrib += array('id' => 'rcmdirectorylist');

    $out = '';
    $line_templ = html::tag('li', array(
        'id' => 'rcmli%s', 'class' => '%s'),
        html::a(array('href' => '#list',
            'rel' => '%s',
            'onclick' => "return ".rcmail_output::JS_OBJECT_NAME.".command('list-addresses','%s',this)"), '%s'));

    foreach ($RCMAIL->get_address_sources(false, true) as $j => $source) {
        $id = strval(strlen($source['id']) ? $source['id'] : $j);
        $js_id = rcube::JQ($id);

        // set class name(s)
        $class_name = 'addressbook';
        if ($source['class_name'])
            $class_name .= ' ' . $source['class_name'];

        $out .= sprintf($line_templ,
            rcube_utils::html_identifier($id,true),
            $class_name,
            $source['id'],
            $js_id, ($source['name'] ?: $id));
    }

    $OUTPUT->add_gui_object('addressbookslist', $attrib['id']);

    return html::tag('ul', $attrib, $out, html::$common_attrib);
}

// return the contacts list as HTML table
function rcmail_contacts_list($attrib = array())
{
    global $RCMAIL, $OUTPUT;

    $attrib += array('id' => 'rcmAddressList');

    // set client env
    $OUTPUT->add_gui_object('contactslist', $attrib['id']);
    $OUTPUT->set_env('pagecount', 0);
    $OUTPUT->set_env('current_page', 0);
    $OUTPUT->include_script('list.js');

    return $RCMAIL->table_output($attrib, array(), array('name'), 'ID');
}


/**
 * Register a certain container as active area to drop files onto
 */
function compose_file_drop_area($attrib)
{
    global $OUTPUT;

    if ($attrib['id']) {
        $OUTPUT->add_gui_object('filedrop', $attrib['id']);
        $OUTPUT->set_env('filedrop', array('action' => 'upload', 'fieldname' => '_attachments'));
    }
}


/**
 *
 */
function rcmail_compose_responses_list($attrib)
{
    global $RCMAIL, $OUTPUT;

    $attrib += array('id' => 'rcmresponseslist', 'tagname' => 'ul', 'cols' => 1);

    $jsenv = array();
    $list = new html_table($attrib);
    foreach ($RCMAIL->get_compose_responses(true) as $response) {
        $key = $response['key'];
        $item = html::a(array(
            'href' => '#'.urlencode($response['name']),
            'class' => rtrim('insertresponse ' . $attrib['itemclass']),
            'unselectable' => 'on',
            'tabindex' => '0',
            'rel' => $key,
        ), rcube::Q($response['name']));

        $jsenv[$key] = $response;
        $list->add(array(), $item);
    }

    // set client env
    $OUTPUT->set_env('textresponses', $jsenv);
    $OUTPUT->add_gui_object('responseslist', $attrib['id']);

    return $list->show();
}
