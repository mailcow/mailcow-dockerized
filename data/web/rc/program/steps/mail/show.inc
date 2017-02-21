<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/show.inc                                           |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Display a mail message similar as a usual mail application does     |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

$PRINT_MODE = $RCMAIL->action == 'print';

// Read browser capabilities and store them in session
if ($caps = rcube_utils::get_input_value('_caps', rcube_utils::INPUT_GET)) {
    $browser_caps = array();
    foreach (explode(',', $caps) as $cap) {
        $cap = explode('=', $cap);
        $browser_caps[$cap[0]] = $cap[1];
    }
    $_SESSION['browser_caps'] = $browser_caps;
}

$msg_id    = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GET);
$uid       = preg_replace('/\.[0-9.]+$/', '', $msg_id);
$mbox_name = $RCMAIL->storage->get_folder();

// similar code as in program/steps/mail/get.inc
if ($uid) {
    // set message format (need to be done before rcube_message construction)
    if (!empty($_GET['_format'])) {
        $prefer_html = $_GET['_format'] == 'html';
        $RCMAIL->config->set('prefer_html', $prefer_html);
        $_SESSION['msg_formats'][$mbox_name.':'.$uid] = $prefer_html;
    }
    else if (isset($_SESSION['msg_formats'][$mbox_name.':'.$uid])) {
        $RCMAIL->config->set('prefer_html', $_SESSION['msg_formats'][$mbox_name.':'.$uid]);
    }

    $MESSAGE = new rcube_message($msg_id, $mbox_name, intval($_GET['_safe']));

    // if message not found (wrong UID)...
    if (empty($MESSAGE->headers)) {
        rcmail_message_error($uid);
    }

    // show images?
    rcmail_check_safe($MESSAGE);

    // set message charset as default
    if (!empty($MESSAGE->headers->charset)) {
        $RCMAIL->storage->set_charset($MESSAGE->headers->charset);
    }

    $OUTPUT->set_pagetitle(abbreviate_string($MESSAGE->subject, 128, '...', true));

    // set message environment
    $OUTPUT->set_env('uid', $msg_id);
    $OUTPUT->set_env('safemode', $MESSAGE->is_safe);
    $OUTPUT->set_env('message_context', $MESSAGE->context);
    $OUTPUT->set_env('sender', $MESSAGE->sender['string']);
    $OUTPUT->set_env('mailbox', $mbox_name);
    $OUTPUT->set_env('username', $RCMAIL->get_user_name());
    $OUTPUT->set_env('permaurl', $RCMAIL->url(array('_action' => 'show', '_uid' => $msg_id, '_mbox' => $mbox_name)));

    if ($MESSAGE->headers->get('list-post', false)) {
        $OUTPUT->set_env('list_post', true);
    }

    // set environment
    $OUTPUT->set_env('delimiter', $RCMAIL->storage->get_hierarchy_delimiter());
    $OUTPUT->set_env('mimetypes', rcmail_supported_mimetypes());

    // set configuration
    $RCMAIL->set_env_config(array('delete_junk', 'flag_for_deletion', 'read_when_deleted',
        'skip_deleted', 'display_next', 'forward_attachment'));

    // set special folders
    foreach (array('drafts', 'trash', 'junk') as $mbox) {
        if ($folder = $RCMAIL->config->get($mbox . '_mbox')) {
            $OUTPUT->set_env($mbox . '_mailbox', $folder);
        }
    }

    if ($MESSAGE->has_html_part()) {
        $prefer_html = $RCMAIL->config->get('prefer_html');
        $OUTPUT->set_env('optional_format', $prefer_html ? 'text' : 'html');
    }

    if (!$OUTPUT->ajax_call) {
        $OUTPUT->add_label('checkingmail', 'deletemessage', 'movemessagetotrash',
            'movingmessage', 'deletingmessage', 'markingmessage', 'replyall', 'replylist');
    }

    // check for unset disposition notification
    if ($MESSAGE->headers->mdn_to
        && $MESSAGE->context === null
        && empty($MESSAGE->headers->flags['MDNSENT'])
        && empty($MESSAGE->headers->flags['SEEN'])
        && ($RCMAIL->storage->check_permflag('MDNSENT') || $RCMAIL->storage->check_permflag('*'))
        && $mbox_name != $RCMAIL->config->get('drafts_mbox')
        && $mbox_name != $RCMAIL->config->get('sent_mbox')
    ) {
        $mdn_cfg = intval($RCMAIL->config->get('mdn_requests'));

        if ($mdn_cfg == 1 || (($mdn_cfg == 3 || $mdn_cfg ==  4) && rcmail_contact_exists($MESSAGE->sender['mailto']))) {
            // Send MDN
            if (rcmail_send_mdn($MESSAGE, $smtp_error))
                $OUTPUT->show_message('receiptsent', 'confirmation');
            else if ($smtp_error)
                $OUTPUT->show_message($smtp_error['label'], 'error', $smtp_error['vars']);
            else
                $OUTPUT->show_message('errorsendingreceipt', 'error');
        }
        else if ($mdn_cfg != 2 && $mdn_cfg != 4) {
            // Ask user
            $OUTPUT->add_label('mdnrequest');
            $OUTPUT->set_env('mdn_request', true);
        }
    }

    if (empty($MESSAGE->headers->flags['SEEN']) && $MESSAGE->context === null) {
        $v = intval($RCMAIL->config->get('mail_read_time'));
        if ($v > 0) {
            $OUTPUT->set_env('mail_read_time', $v);
        }
        else if ($v == 0) {
            $RCMAIL->output->command('set_unread_message', $MESSAGE->uid, $mbox_name);
            $RCMAIL->plugins->exec_hook('message_read', array(
                    'uid'     => $MESSAGE->uid,
                    'mailbox' => $mbox_name,
                    'message' => $MESSAGE,
            ));

            $set_seen_flag = true;
        }
    }
}


$OUTPUT->add_handlers(array(
    'messageattachments' => 'rcmail_message_attachments',
    'mailboxname'        => 'rcmail_mailbox_name_display',
    'messageobjects'     => 'rcmail_message_objects',
    'contactphoto'       => 'rcmail_message_contactphoto',
));


if ($RCMAIL->action == 'print' && $OUTPUT->template_exists('messageprint'))
    $OUTPUT->send('messageprint', false);
else if ($RCMAIL->action == 'preview' && $OUTPUT->template_exists('messagepreview'))
    $OUTPUT->send('messagepreview', false);
else
    $OUTPUT->send('message', false);


// mark message as read
if (!empty($set_seen_flag)) {
    if ($RCMAIL->storage->set_flag($MESSAGE->uid, 'SEEN', $mbox_name)) {
        if ($count = rcmail_get_unseen_count($mbox_name)) {
            rcmail_set_unseen_count($mbox_name, $count - 1);
        }
    }
}

exit;


function rcmail_message_attachments($attrib)
{
    global $PRINT_MODE, $MESSAGE, $RCMAIL;

    $out = $ol = '';
    $attachments = array();

    if (sizeof($MESSAGE->attachments)) {
        foreach ($MESSAGE->attachments as $attach_prop) {
            $filename = rcmail_attachment_name($attach_prop, true);
            $filesize = $RCMAIL->message_part_size($attach_prop);

            if ($PRINT_MODE) {
                $ol .= html::tag('li', array('id' => 'attach' . $attach_prop->mime_id),
                    rcube::Q(sprintf("%s (%s)", $filename, $filesize)));
            }
            else {
                if ($attrib['maxlength'] && mb_strlen($filename) > $attrib['maxlength']) {
                    $title    = $filename;
                    $filename = abbreviate_string($filename, $attrib['maxlength']);
                }
                else {
                    $title = '';
                }

                $size     = ' ' . html::span('attachment-size', '(' . rcube::Q($filesize) . ')');
                $mimetype = rcmail_fix_mimetype($attach_prop->mimetype);
                $class    = rcube_utils::file2class($mimetype, $filename);
                $id       = 'attach' . $attach_prop->mime_id;
                $link     = html::a(array(
                    'href'        => $MESSAGE->get_part_url($attach_prop->mime_id, false),
                    'onclick'     => sprintf('return %s.command(\'load-attachment\',\'%s\',this)',
                        rcmail_output::JS_OBJECT_NAME, $attach_prop->mime_id),
                    'onmouseover' => $title ? '' : 'rcube_webmail.long_subject_title_ex(this, 0)',
                    'title'       => $title,
                    ), rcube::Q($filename) . $size);

                $ol .= html::tag('li', array('class' => $class, 'id' => $id), $link);

                $attachments[$attach_prop->mime_id] = $mimetype;
            }
        }

        $out = html::tag('ul', $attrib, $ol, html::$common_attrib);

        $RCMAIL->output->set_env('attachments', $attachments);
        $RCMAIL->output->add_gui_object('attachments', $attrib['id']);
    }

    return $out;
}

function rcmail_remote_objects_msg()
{
    global $MESSAGE, $RCMAIL;

    $attrib['id']    = 'remote-objects-message';
    $attrib['class'] = 'notice';
    $attrib['style'] = 'display: none';

    $msg = rcube::Q($RCMAIL->gettext('blockedimages')) . '&nbsp;';
    $msg .= html::a(array(
            'href'    => "#loadimages",
            'onclick' => rcmail_output::JS_OBJECT_NAME.".command('load-images')"
        ),
        rcube::Q($RCMAIL->gettext('showimages')));

    // add link to save sender in addressbook and reload message
    if ($MESSAGE->sender['mailto'] && $RCMAIL->config->get('show_images') == 1) {
        $msg .= ' ' . html::a(array(
                'href'    => "#alwaysload",
                'onclick' => rcmail_output::JS_OBJECT_NAME.".command('always-load')",
                'style'   => "white-space:nowrap"
            ),
            rcube::Q($RCMAIL->gettext(array('name' => 'alwaysshow', 'vars' => array('sender' => $MESSAGE->sender['mailto'])))));
    }

    $RCMAIL->output->add_gui_object('remoteobjectsmsg', $attrib['id']);

    return html::div($attrib, $msg);
}

function rcmail_message_buttons()
{
    global $RCMAIL, $MESSAGE;

    $delim = $RCMAIL->storage->get_hierarchy_delimiter();
    $dbox  = $RCMAIL->config->get('drafts_mbox');

    // the message is not a draft
    if ($MESSAGE->folder != $dbox && strpos($MESSAGE->folder, $dbox.$delim) !== 0) {
        return '';
    }

    $attrib['id']    = 'message-buttons';
    $attrib['class'] = 'notice';

    $msg = rcube::Q($RCMAIL->gettext('isdraft')) . '&nbsp;';
    $msg .= html::a(array(
            'href'    => "#edit",
            'onclick' => rcmail_output::JS_OBJECT_NAME.".command('edit')"
        ),
        rcube::Q($RCMAIL->gettext('edit')));

    return html::div($attrib, $msg);
}

function rcmail_message_objects($attrib)
{
    global $RCMAIL, $MESSAGE;

    if (!$attrib['id'])
        $attrib['id'] = 'message-objects';

    $content = array(
        rcmail_message_buttons(),
        rcmail_remote_objects_msg(),
    );

    $plugin = $RCMAIL->plugins->exec_hook('message_objects',
        array('content' => $content, 'message' => $MESSAGE));

    $content = implode("\n", $plugin['content']);

    return html::div($attrib, $content);
}

function rcmail_contact_exists($email)
{
    global $RCMAIL;

    if ($email) {
        // @TODO: search in all address books?
        $CONTACTS = $RCMAIL->get_address_book(-1, true);

        if (is_object($CONTACTS)) {
            $existing = $CONTACTS->search('email', $email, 1, false);
            if ($existing->count) {
                return true;
            }
        }
    }

    return false;
}

function rcmail_message_contactphoto($attrib)
{
    global $RCMAIL, $MESSAGE;

    $placeholder = $attrib['placeholder'] ? $RCMAIL->output->abs_url($attrib['placeholder'], true) : null;
    $placeholder = $RCMAIL->output->asset_url($placeholder ?: 'program/resources/blank.gif');

    if ($MESSAGE->sender) {
        $photo_img = $RCMAIL->url(array(
            '_task'   => 'addressbook',
            '_action' => 'photo',
            '_email'  => $MESSAGE->sender['mailto'],
        ));

        $attrib['onerror'] = "this.src = '$placeholder'";
    }
    else {
        $photo_img = $placeholder;
    }

    return html::img(array('src' => $photo_img, 'alt' => $RCMAIL->gettext('contactphoto')) + $attrib);
}
