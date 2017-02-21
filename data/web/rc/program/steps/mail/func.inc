<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/mail/func.inc                                           |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2014, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide webmail functionality and GUI objects                       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

// always instantiate storage object (but not connect to server yet)
$RCMAIL->storage_init();

// init environment - set current folder, page, list mode
rcmail_init_env();

// set message set for search result
if (!empty($_REQUEST['_search']) && isset($_SESSION['search'])
    && $_SESSION['search_request'] == $_REQUEST['_search']
) {
    $RCMAIL->storage->set_search_set($_SESSION['search']);

    $OUTPUT->set_env('search_request', $_REQUEST['_search']);
    $OUTPUT->set_env('search_text', $_SESSION['last_text_search']);
}

// remove mbox part from _uid
if (($_uid  = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GPC)) && !is_array($_uid) && preg_match('/^\d+-.+/', $_uid)) {
  list($_uid, $mbox) = explode('-', $_uid, 2);
  if (isset($_GET['_uid']))  $_GET['_uid']  = $_uid;
  if (isset($_POST['_uid'])) $_POST['_uid'] = $_uid;
  $_REQUEST['_uid'] = $_uid;
  unset($_uid);

  // override mbox
  if (!empty($mbox)) {
    $_GET['_mbox']  = $mbox;
    $_POST['_mbox'] = $mbox;
    $RCMAIL->storage->set_folder(($_SESSION['mbox'] = $mbox));
  }
}

if (!empty($_SESSION['browser_caps']) && !$OUTPUT->ajax_call) {
    $OUTPUT->set_env('browser_capabilities', $_SESSION['browser_caps']);
}

// set main env variables, labels and page title
if (empty($RCMAIL->action) || $RCMAIL->action == 'list') {
    // connect to storage server and trigger error on failure
    $RCMAIL->storage_connect();

    $mbox_name = $RCMAIL->storage->get_folder();

    if (empty($RCMAIL->action)) {
        $OUTPUT->set_env('search_mods', rcmail_search_mods());

        if (!empty($_SESSION['search_scope']))
            $OUTPUT->set_env('search_scope', $_SESSION['search_scope']);

        rcmail_list_pagetitle();
    }

    $threading = (bool) $RCMAIL->storage->get_threading();
    $delimiter = $RCMAIL->storage->get_hierarchy_delimiter();

    // set current mailbox and some other vars in client environment
    $OUTPUT->set_env('mailbox', $mbox_name);
    $OUTPUT->set_env('pagesize', $RCMAIL->storage->get_pagesize());
    $OUTPUT->set_env('current_page', max(1, $_SESSION['page']));
    $OUTPUT->set_env('delimiter', $delimiter);
    $OUTPUT->set_env('threading', $threading);
    $OUTPUT->set_env('threads', $threading || $RCMAIL->storage->get_capability('THREAD'));
    $OUTPUT->set_env('reply_all_mode', (int) $RCMAIL->config->get('reply_all_mode'));
    $OUTPUT->set_env('layout', $RCMAIL->config->get('layout') ?: 'widescreen');

    if ($RCMAIL->storage->get_capability('QUOTA')) {
        $OUTPUT->set_env('quota', true);
    }

    // set special folders
    foreach (array('drafts', 'trash', 'junk') as $mbox) {
        if ($folder = $RCMAIL->config->get($mbox . '_mbox')) {
            $OUTPUT->set_env($mbox . '_mailbox', $folder);
        }
    }

    if (!empty($_GET['_uid'])) {
        $OUTPUT->set_env('list_uid', $_GET['_uid']);
    }

    // set configuration
    $RCMAIL->set_env_config(array('delete_junk', 'flag_for_deletion', 'read_when_deleted',
        'skip_deleted', 'display_next', 'message_extwin', 'forward_attachment'));

    if (!$OUTPUT->ajax_call) {
        $OUTPUT->add_label('checkingmail', 'deletemessage', 'movemessagetotrash',
            'movingmessage', 'copyingmessage', 'deletingmessage', 'markingmessage',
            'copy', 'move', 'quota', 'replyall', 'replylist', 'stillsearching',
            'flagged', 'unflagged', 'unread', 'deleted', 'replied', 'forwarded',
            'priority', 'withattachment', 'fileuploaderror', 'mark', 'markallread',
            'folders-cur', 'folders-sub', 'folders-all', 'cancel');
    }
}

// register UI objects
$OUTPUT->add_handlers(array(
    'mailboxlist'         => array($RCMAIL, 'folder_list'),
    'quotadisplay'        => array($RCMAIL, 'quota_display'),
    'messages'            => 'rcmail_message_list',
    'messagecountdisplay' => 'rcmail_messagecount_display',
    'listmenulink'        => 'rcmail_options_menu_link',
    'mailboxname'         => 'rcmail_mailbox_name_display',
    'messageheaders'      => 'rcmail_message_headers',
    'messagefullheaders'  => 'rcmail_message_full_headers',
    'messagebody'         => 'rcmail_message_body',
    'messagecontentframe' => 'rcmail_messagecontent_frame',
    'messageimportform'   => 'rcmail_message_import_form',
    'searchfilter'        => 'rcmail_search_filter',
    'searchinterval'      => 'rcmail_search_interval',
    'searchform'          => array($OUTPUT, 'search_form'),
));

// register action aliases
$RCMAIL->register_action_map(array(
    'refresh' => 'check_recent.inc',
    'preview' => 'show.inc',
    'print'   => 'show.inc',
    'move'    => 'move_del.inc',
    'delete'  => 'move_del.inc',
    'send'    => 'sendmail.inc',
    'expunge' => 'folders.inc',
    'purge'   => 'folders.inc',
    'remove-attachment'  => 'attachments.inc',
    'rename-attachment'  => 'attachments.inc',
    'display-attachment' => 'attachments.inc',
    'upload'             => 'attachments.inc',
    'group-expand'       => 'autocomplete.inc',
));


/**
 * Sets storage properties and session
 */
function rcmail_init_env()
{
    global $RCMAIL;

    $default_threading  = $RCMAIL->config->get('default_list_mode', 'list') == 'threads';
    $a_threading        = $RCMAIL->config->get('message_threading', array());
    $message_sort_col   = $RCMAIL->config->get('message_sort_col');
    $message_sort_order = $RCMAIL->config->get('message_sort_order');

    // set imap properties and session vars
    if (!strlen($mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_GPC, true))) {
        $mbox = strlen($_SESSION['mbox']) ? $_SESSION['mbox'] : 'INBOX';
    }

    // we handle 'page' argument on 'list' and 'getunread' to prevent from
    // race condition and unintentional page overwrite in session
    if ($RCMAIL->action == 'list' || $RCMAIL->action == 'getunread') {
        if (!($page = intval($_GET['_page']))) {
            $page = $_SESSION['page'] ?: 1;
        }

        $_SESSION['page'] = $page;
    }

    $RCMAIL->storage->set_folder($_SESSION['mbox'] = $mbox);
    $RCMAIL->storage->set_page($_SESSION['page']);

    // set default sort col/order to session
    if (!isset($_SESSION['sort_col'])) {
        $_SESSION['sort_col'] = $message_sort_col ?: '';
    }
    if (!isset($_SESSION['sort_order'])) {
        $_SESSION['sort_order'] = strtoupper($message_sort_order) == 'ASC' ? 'ASC' : 'DESC';
    }

    // set threads mode
    if (isset($_GET['_threads'])) {
        if ($_GET['_threads']) {
            // re-set current page number when listing mode changes
            if (!$a_threading[$_SESSION['mbox']]) {
                $RCMAIL->storage->set_page($_SESSION['page'] = 1);
            }

            $a_threading[$_SESSION['mbox']] = true;
        }
        else {
            // re-set current page number when listing mode changes
            if ($a_threading[$_SESSION['mbox']]) {
                $RCMAIL->storage->set_page($_SESSION['page'] = 1);
            }

            $a_threading[$_SESSION['mbox']] = false;
        }

        $RCMAIL->user->save_prefs(array('message_threading' => $a_threading));
    }

    $threading = isset($a_threading[$_SESSION['mbox']]) ? $a_threading[$_SESSION['mbox']] : $default_threading;

    $RCMAIL->storage->set_threading($threading);
}

/**
 * Sets page title
 */
function rcmail_list_pagetitle()
{
    global $RCMAIL;

    if ($RCMAIL->output->get_env('search_request')) {
        $pagetitle = $RCMAIL->gettext('searchresult');
    }
    else {
        $mbox_name = $RCMAIL->output->get_env('mailbox') ?: $RCMAIL->storage->get_folder();
        $delimiter = $RCMAIL->storage->get_hierarchy_delimiter();
        $pagetitle = $RCMAIL->localize_foldername($mbox_name, true);
        $pagetitle = str_replace($delimiter, " \xC2\xBB ", $pagetitle);
    }

    $RCMAIL->output->set_pagetitle($pagetitle);
}

/**
 * Returns default search mods
 */
function rcmail_search_mods()
{
    global $RCMAIL;

    $mods = $RCMAIL->config->get('search_mods');

    if (empty($mods)) {
        $mods = array('*' => array('subject' => 1, 'from' => 1));

        foreach (array('sent', 'drafts') as $mbox) {
            if ($mbox = $RCMAIL->config->get($mbox . '_mbox')) {
                $mods[$mbox] = array('subject' => 1, 'to' => 1);
            }
        }
    }

    return $mods;
}

/**
 * Returns 'to' if current folder is configured Sent or Drafts
 * or their subfolders, otherwise returns 'from'.
 *
 * @return string Column name
 */
function rcmail_message_list_smart_column_name()
{
    global $RCMAIL;

    $delim       = $RCMAIL->storage->get_hierarchy_delimiter();
    $mbox        = $RCMAIL->output->get_env('mailbox') ?: $RCMAIL->storage->get_folder();
    $sent_mbox   = $RCMAIL->config->get('sent_mbox');
    $drafts_mbox = $RCMAIL->config->get('drafts_mbox');

    if ((strpos($mbox.$delim, $sent_mbox.$delim) === 0 || strpos($mbox.$delim, $drafts_mbox.$delim) === 0)
        && strtoupper($mbox) != 'INBOX'
    ) {
        return 'to';
    }

    return 'from';
}

/**
 * Returns configured messages list sorting column name
 * The name is context-sensitive, which means if sorting is set to 'fromto'
 * it will return 'from' or 'to' according to current folder type.
 *
 * @return string Column name
 */
function rcmail_sort_column()
{
    global $RCMAIL;

    if (isset($_SESSION['sort_col'])) {
        $column = $_SESSION['sort_col'];
    }
    else {
        $column = $RCMAIL->config->get('message_sort_col');
    }

    // get name of smart From/To column in folder context
    if ($column == 'fromto') {
        $column = rcmail_message_list_smart_column_name();
    }

    return $column;
}

/**
 * Returns configured message list sorting order
 *
 * @return string Sorting order (ASC|DESC)
 */
function rcmail_sort_order()
{
    global $RCMAIL;

    if (isset($_SESSION['sort_order'])) {
        return $_SESSION['sort_order'];
    }

    return $RCMAIL->config->get('message_sort_order');
}

/**
 * return the message list as HTML table
 */
function rcmail_message_list($attrib)
{
    global $RCMAIL, $OUTPUT;

    // add some labels to client
    $OUTPUT->add_label('from', 'to');

    // add id to message list table if not specified
    if (!strlen($attrib['id'])) {
        $attrib['id'] = 'rcubemessagelist';
    }

    // define list of cols to be displayed based on parameter or config
    if (empty($attrib['columns'])) {
        $list_cols   = $RCMAIL->config->get('list_cols');
        $a_show_cols = !empty($list_cols) && is_array($list_cols) ? $list_cols : array('subject');

        $OUTPUT->set_env('col_movable', !in_array('list_cols', (array)$RCMAIL->config->get('dont_override')));
    }
    else {
        $a_show_cols = preg_split('/[\s,;]+/', str_replace(array("'", '"'), '', $attrib['columns']));
        $attrib['columns'] = $a_show_cols;
    }

    // save some variables for use in ajax list
    $_SESSION['list_attrib'] = $attrib;

    // make sure 'threads' and 'subject' columns are present
    if (!in_array('subject', $a_show_cols))
        array_unshift($a_show_cols, 'subject');
    if (!in_array('threads', $a_show_cols))
        array_unshift($a_show_cols, 'threads');

    $listcols = $a_show_cols;

    // Widescreen layout uses hardcoded list of columns
    if ($RCMAIL->config->get('layout', 'widescreen') == 'widescreen') {
        $a_show_cols = array('threads', 'subject', 'fromto', 'date', 'flag', 'attachment');
        $listcols    = $a_show_cols;
        array_shift($listcols);
    }

    // set client env
    $OUTPUT->add_gui_object('messagelist', $attrib['id']);
    $OUTPUT->set_env('autoexpand_threads', intval($RCMAIL->config->get('autoexpand_threads')));
    $OUTPUT->set_env('sort_col', $_SESSION['sort_col']);
    $OUTPUT->set_env('sort_order', $_SESSION['sort_order']);
    $OUTPUT->set_env('messages', array());
    $OUTPUT->set_env('listcols', $listcols);

    $OUTPUT->include_script('list.js');

    $table = new html_table($attrib);

    if (!$attrib['noheader']) {
        foreach (rcmail_message_list_head($attrib, $a_show_cols) as $cell)
            $table->add_header(array('class' => $cell['className'], 'id' => $cell['id']), $cell['html']);
    }

    return $table->show();
}

/**
 * return javascript commands to add rows to the message list
 */
function rcmail_js_message_list($a_headers, $insert_top=false, $a_show_cols=null)
{
    global $RCMAIL, $OUTPUT;

    if (empty($a_show_cols)) {
        if (!empty($_SESSION['list_attrib']['columns']))
            $a_show_cols = $_SESSION['list_attrib']['columns'];
        else {
            $list_cols   = $RCMAIL->config->get('list_cols');
            $a_show_cols = !empty($list_cols) && is_array($list_cols) ? $list_cols : array('subject');
        }
    }
    else {
        if (!is_array($a_show_cols)) {
            $a_show_cols = preg_split('/[\s,;]+/', str_replace(array("'", '"'), '', $a_show_cols));
        }
        $head_replace = true;
    }

    $delimiter   = $RCMAIL->storage->get_hierarchy_delimiter();
    $search_set  = $RCMAIL->storage->get_search_set();
    $multifolder = $search_set && $search_set[1]->multi;

    // add/remove 'folder' column to the list on multi-folder searches
    if ($multifolder && !in_array('folder', $a_show_cols)) {
        $a_show_cols[] = 'folder';
        $head_replace = true;
    }
    else if (!$multifolder && ($found = array_search('folder', $a_show_cols)) !== false) {
        unset($a_show_cols[$found]);
        $head_replace = true;
    }

    $mbox = $RCMAIL->output->get_env('mailbox') ?: $RCMAIL->storage->get_folder();

    // make sure 'threads' and 'subject' columns are present
    if (!in_array('subject', $a_show_cols))
        array_unshift($a_show_cols, 'subject');
    if (!in_array('threads', $a_show_cols))
        array_unshift($a_show_cols, 'threads');

    // Make sure there are no duplicated columns (#1486999)
    $a_show_cols = array_unique($a_show_cols);
    $_SESSION['list_attrib']['columns'] = $a_show_cols;

    // Widescreen layout uses hardcoded list of columns
    if ($RCMAIL->config->get('layout', 'widescreen') == 'widescreen') {
        $a_show_cols = array('threads', 'subject', 'fromto', 'date', 'flag', 'attachment');
    }

    // Plugins may set header's list_cols/list_flags and other rcube_message_header variables
    // and list columns
    $plugin = $RCMAIL->plugins->exec_hook('messages_list',
        array('messages' => $a_headers, 'cols' => $a_show_cols));

    $a_show_cols = $plugin['cols'];
    $a_headers   = $plugin['messages'];

    if ($RCMAIL->config->get('layout', 'widescreen') == 'widescreen') {
        if (!$OUTPUT->get_env('threading')) {
            if (($idx = array_search('threads', $a_show_cols)) !== false) {
                unset($a_show_cols[$idx]);
            }
        }
    }

    $thead = $head_replace ? rcmail_message_list_head($_SESSION['list_attrib'], $a_show_cols) : NULL;

    // get name of smart From/To column in folder context
    if (array_search('fromto', $a_show_cols) !== false) {
        $smart_col = rcmail_message_list_smart_column_name();
    }

    $OUTPUT->command('set_message_coltypes', array_values($a_show_cols), $thead, $smart_col);

    if ($multifolder && $_SESSION['search_scope'] == 'all') {
        $OUTPUT->command('select_folder', '');
    }

    $OUTPUT->set_env('multifolder_listing', $multifolder);

    if (empty($a_headers)) {
        return;
    }

    // remove 'threads', 'attachment', 'flag', 'status' columns, we don't need them here
    foreach (array('threads', 'attachment', 'flag', 'status', 'priority') as $col) {
        if (($key = array_search($col, $a_show_cols)) !== FALSE) {
            unset($a_show_cols[$key]);
        }
    }

    $sort_col = $_SESSION['sort_col'];

    // loop through message headers
    foreach ($a_headers as $header) {
        if (empty($header))
            continue;

        // make message UIDs unique by appending the folder name
        if ($multifolder) {
            $header->uid .= '-'.$header->folder;
            $header->flags['skip_mbox_check'] = true;
            if ($header->parent_uid)
                $header->parent_uid .= '-'.$header->folder;
        }

        $a_msg_cols  = array();
        $a_msg_flags = array();

        // format each col; similar as in rcmail_message_list()
        foreach ($a_show_cols as $col) {
            $col_name = $col == 'fromto' ? $smart_col : $col;

            if (in_array($col_name, array('from', 'to', 'cc', 'replyto'))) {
                $cont = rcmail_address_string($header->$col_name, 3, false, null, $header->charset);
                if (empty($cont)) $cont = '&nbsp;'; // for widescreen mode
            }
            else if ($col == 'subject') {
                $cont = trim(rcube_mime::decode_header($header->$col, $header->charset));
                if (!$cont) $cont = $RCMAIL->gettext('nosubject');
                $cont = rcube::Q($cont);
            }
            else if ($col == 'size')
                $cont = $RCMAIL->show_bytes($header->$col);
            else if ($col == 'date')
                $cont = $RCMAIL->format_date($sort_col == 'arrival' ? $header->internaldate : $header->date);
            else if ($col == 'folder') {
                if ($last_folder !== $header->folder) {
                    $last_folder      = $header->folder;
                    $last_folder_name = rcube_charset::convert($last_folder, 'UTF7-IMAP');
                    $last_folder_name = $RCMAIL->localize_foldername($last_folder_name, true);
                    $last_folder_name = str_replace($delimiter, " \xC2\xBB ", $last_folder_name);
                }

                $cont = rcube::Q($last_folder_name);
            }
            else
                $cont = rcube::Q($header->$col);

            $a_msg_cols[$col] = $cont;
        }

        $a_msg_flags = array_change_key_case(array_map('intval', (array) $header->flags));
        if ($header->depth)
            $a_msg_flags['depth'] = $header->depth;
        else if ($header->has_children)
            $roots[] = $header->uid;
        if ($header->parent_uid)
            $a_msg_flags['parent_uid'] = $header->parent_uid;
        if ($header->has_children)
            $a_msg_flags['has_children'] = $header->has_children;
        if ($header->unread_children)
            $a_msg_flags['unread_children'] = $header->unread_children;
        if ($header->flagged_children)
            $a_msg_flags['flagged_children'] = $header->flagged_children;
        if ($header->others['list-post'])
            $a_msg_flags['ml'] = 1;
        if ($header->priority)
            $a_msg_flags['prio'] = (int) $header->priority;

        $a_msg_flags['ctype'] = rcube::Q($header->ctype);
        $a_msg_flags['mbox']  = $header->folder;

        // merge with plugin result (Deprecated, use $header->flags)
        if (!empty($header->list_flags) && is_array($header->list_flags))
            $a_msg_flags = array_merge($a_msg_flags, $header->list_flags);
        if (!empty($header->list_cols) && is_array($header->list_cols))
            $a_msg_cols = array_merge($a_msg_cols, $header->list_cols);

        $OUTPUT->command('add_message_row', $header->uid, $a_msg_cols, $a_msg_flags, $insert_top);
    }

    if ($RCMAIL->storage->get_threading()) {
        $OUTPUT->command('init_threads', (array) $roots, $mbox);
    }
}

/*
 * Creates <THEAD> for message list table
 */
function rcmail_message_list_head($attrib, $a_show_cols)
{
    global $RCMAIL;

    // check to see if we have some settings for sorting
    $sort_col   = $_SESSION['sort_col'];
    $sort_order = $_SESSION['sort_order'];

    $dont_override  = (array) $RCMAIL->config->get('dont_override');
    $disabled_sort  = in_array('message_sort_col', $dont_override);
    $disabled_order = in_array('message_sort_order', $dont_override);

    $RCMAIL->output->set_env('disabled_sort_col', $disabled_sort);
    $RCMAIL->output->set_env('disabled_sort_order', $disabled_order);

    // define sortable columns
    if ($disabled_sort)
        $a_sort_cols = $sort_col && !$disabled_order ? array($sort_col) : array();
    else
        $a_sort_cols = array('subject', 'date', 'from', 'to', 'fromto', 'size', 'cc');

    if (!empty($attrib['optionsmenuicon'])) {
        $list_menu = rcmail_options_menu_link($attrib);
    }

    $cells = $coltypes = array();

    // get name of smart From/To column in folder context
    if (array_search('fromto', $a_show_cols) !== false) {
        $smart_col = rcmail_message_list_smart_column_name();
    }

    foreach ($a_show_cols as $col) {
        $label    = '';
        $sortable = false;
        $rel_col  = $col == 'date' && $sort_col == 'arrival' ? 'arrival' : $col;

        // get column name
        switch ($col) {
        case 'flag':
            $col_name = html::span('flagged', $RCMAIL->gettext('flagged'));
            break;
        case 'attachment':
        case 'priority':
            $col_name = html::span($col, $RCMAIL->gettext($col));
            break;
        case 'status':
            $col_name = html::span($col, $RCMAIL->gettext('readstatus'));
            break;
        case 'threads':
            $col_name = (string) $list_menu;
            break;
        case 'fromto':
            $label    = $RCMAIL->gettext($smart_col);
            $col_name = rcube::Q($label);
            break;
        default:
            $label    = $RCMAIL->gettext($col);
            $col_name = rcube::Q($label);
        }

        // make sort links
        if (in_array($col, $a_sort_cols)) {
            $sortable = true;
            $col_name = html::a(array(
                    'href'  => "./#sort",
                    'class' => 'sortcol',
                    'rel'   => $rel_col,
                    'title' => $RCMAIL->gettext('sortby')
                ), $col_name);
        }
        else if ($col_name[0] != '<') {
            $col_name = '<span class="' . $col .'">' . $col_name . '</span>';
        }

        $sort_class = $rel_col == $sort_col && !$disabled_order ? " sorted$sort_order" : '';
        $class_name = $col.$sort_class;

        // put it all together
        $cells[] = array('className' => $class_name, 'id' => "rcm$col", 'html' => $col_name);
        $coltypes[$col] = array('className' => $class_name, 'id' => "rcm$col", 'label' => $label, 'sortable' => $sortable);
    }

    $RCMAIL->output->set_env('coltypes', $coltypes);
    return $cells;
}

function rcmail_options_menu_link($attrib)
{
    global $RCMAIL;

    $onclick = 'return ' . rcmail_output::JS_OBJECT_NAME . ".command('menu-open', 'messagelistmenu', this, event)";
    $inner   = $title = $RCMAIL->gettext('listoptions');

    if (is_string($attrib['optionsmenuicon']) && $attrib['optionsmenuicon'] != 'true') {
        $inner = html::img(array('src' => $RCMAIL->output->abs_url($attrib['optionsmenuicon'], true), 'alt' => $title));
    }

    return html::a(array(
            'href'     => '#list-options',
            'onclick'  => $onclick,
            'class'    => 'listmenu',
            'id'       => 'listmenulink',
            'title'    => $title,
            'tabindex' => '0',
    ), $inner);
}

/**
 * return an HTML iframe for loading mail content
 */
function rcmail_messagecontent_frame($attrib)
{
    global $OUTPUT;

    if (empty($attrib['id'])) {
        $attrib['id'] = 'rcmailcontentwindow';
    }

    return $OUTPUT->frame($attrib, true);
}

function rcmail_messagecount_display($attrib)
{
    global $RCMAIL;

    if (!$attrib['id']) {
        $attrib['id'] = 'rcmcountdisplay';
    }

    $RCMAIL->output->add_gui_object('countdisplay', $attrib['id']);

    $content =  $RCMAIL->action != 'show' ? rcmail_get_messagecount_text() : $RCMAIL->gettext('loading');

    return html::span($attrib, $content);
}

function rcmail_get_messagecount_text($count = null, $page = null)
{
    global $RCMAIL;

    if ($page === null) {
        $page = $RCMAIL->storage->get_page();
    }

    $page_size = $RCMAIL->storage->get_pagesize();
    $start_msg = ($page-1) * $page_size + 1;
    $max       = $count;

    if ($max === null && $RCMAIL->action) {
        $max = $RCMAIL->storage->count(null, $RCMAIL->storage->get_threading() ? 'THREADS' : 'ALL');
    }

    if (!$max) {
        $out = $RCMAIL->storage->get_search_set() ? $RCMAIL->gettext('nomessages') : $RCMAIL->gettext('mailboxempty');
    }
    else {
        $out = $RCMAIL->gettext(array('name' => $RCMAIL->storage->get_threading() ? 'threadsfromto' : 'messagesfromto',
            'vars' => array('from'  => $start_msg,
            'to'    => min($max, $start_msg + $page_size - 1),
            'count' => $max)));
    }

    return rcube::Q($out);
}

function rcmail_mailbox_name_display($attrib)
{
    global $RCMAIL;

    if (!$attrib['id']) {
        $attrib['id'] = 'rcmmailboxname';
    }

    $RCMAIL->output->add_gui_object('mailboxname', $attrib['id']);

    return html::span($attrib, rcmail_get_mailbox_name_text());
}

function rcmail_get_mailbox_name_text()
{
    global $RCMAIL;
    return $RCMAIL->localize_foldername($RCMAIL->output->get_env('mailbox') ?: $RCMAIL->storage->get_folder());
}

function rcmail_send_unread_count($mbox_name, $force=false, $count=null, $mark='')
{
    global $RCMAIL;

    $old_unseen = rcmail_get_unseen_count($mbox_name);
    $unseen     = $count;

    if ($unseen === null) {
        $unseen = $RCMAIL->storage->count($mbox_name, 'UNSEEN', $force);
    }

    if ($unseen != $old_unseen || ($mbox_name == 'INBOX')) {
        $RCMAIL->output->command('set_unread_count', $mbox_name, $unseen,
            ($mbox_name == 'INBOX'), $unseen && $mark ? $mark : '');
    }

    rcmail_set_unseen_count($mbox_name, $unseen);

    return $unseen;
}

function rcmail_set_unseen_count($mbox_name, $count)
{
    // @TODO: this data is doubled (session and cache tables) if caching is enabled

    // Make sure we have an array here (#1487066)
    if (!is_array($_SESSION['unseen_count'])) {
        $_SESSION['unseen_count'] = array();
    }

    $_SESSION['unseen_count'][$mbox_name] = $count;
}

function rcmail_get_unseen_count($mbox_name)
{
    if (is_array($_SESSION['unseen_count']) && array_key_exists($mbox_name, $_SESSION['unseen_count'])) {
        return $_SESSION['unseen_count'][$mbox_name];
    }
}

/**
 * Sets message is_safe flag according to 'show_images' option value
 *
 * @param object rcube_message Message
 */
function rcmail_check_safe($message)
{
    global $RCMAIL;

    if (!$message->is_safe
        && ($show_images = $RCMAIL->config->get('show_images'))
        && $message->has_html_part()
    ) {
        switch ($show_images) {
        case 1: // known senders only
            // get default addressbook, like in addcontact.inc
            $CONTACTS = $RCMAIL->get_address_book(-1, true);

            if ($CONTACTS && $message->sender['mailto']) {
                $result = $CONTACTS->search('email', $message->sender['mailto'], 1, false);
                if ($result->count) {
                    $message->set_safe(true);
                }
            }

            $RCMAIL->plugins->exec_hook('message_check_safe', array('message' => $message));
            break;

        case 2: // always
            $message->set_safe(true);
            break;
        }
    }

    return !empty($message->is_safe);
}

/**
 * Cleans up the given message HTML Body (for displaying)
 *
 * @param string HTML
 * @param array  Display parameters 
 * @param array  CID map replaces (inline images)
 * @return string Clean HTML
 */
function rcmail_wash_html($html, $p, $cid_replaces = array())
{
    global $REMOTE_OBJECTS;

    $p += array('safe' => false, 'inline_html' => true);

    // charset was converted to UTF-8 in rcube_storage::get_message_part(),
    // change/add charset specification in HTML accordingly,
    // washtml cannot work without that
    $meta = '<meta http-equiv="Content-Type" content="text/html; charset='.RCUBE_CHARSET.'" />';

    // remove old meta tag and add the new one, making sure
    // that it is placed in the head (#1488093)
    $html = preg_replace('/<meta[^>]+charset=[a-z0-9-_]+[^>]*>/Ui', '', $html);
    $html = preg_replace('/(<head[^>]*>)/Ui', '\\1'.$meta, $html, -1, $rcount);
    if (!$rcount) {
        $html = '<head>' . $meta . '</head>' . $html;
    }

    // clean HTML with washhtml by Frederic Motte
    $wash_opts = array(
        'show_washed'   => false,
        'allow_remote'  => $p['safe'],
        'blocked_src'   => 'program/resources/blocked.gif',
        'charset'       => RCUBE_CHARSET,
        'cid_map'       => $cid_replaces,
        'html_elements' => array('body'),
    );

    if (!$p['inline_html']) {
        $wash_opts['html_elements'] = array('html','head','title','body');
    }
    if ($p['safe']) {
        $wash_opts['html_elements'][] = 'link';
        $wash_opts['html_attribs'] = array('rel','type');
    }

    // overwrite washer options with options from plugins
    if (isset($p['html_elements']))
        $wash_opts['html_elements'] = $p['html_elements'];
    if (isset($p['html_attribs']))
        $wash_opts['html_attribs'] = $p['html_attribs'];

    // initialize HTML washer
    $washer = new rcube_washtml($wash_opts);

    if (!$p['skip_washer_form_callback']) {
        $washer->add_callback('form', 'rcmail_washtml_callback');
    }

    // allow CSS styles, will be sanitized by rcmail_washtml_callback()
    if (!$p['skip_washer_style_callback']) {
        $washer->add_callback('style', 'rcmail_washtml_callback');
    }

    // Remove non-UTF8 characters (#1487813)
    $html = rcube_charset::clean($html);

    $html = $washer->wash($html);
    $REMOTE_OBJECTS = $washer->extlinks;

    return $html;
}

/**
 * Convert the given message part to proper HTML
 * which can be displayed the message view
 *
 * @param string             Message part body
 * @param rcube_message_part Message part
 * @param array              Display parameters array
 *
 * @return string Formatted HTML string
 */
function rcmail_print_body($body, $part, $p = array())
{
    global $RCMAIL;

    // trigger plugin hook
    $data = $RCMAIL->plugins->exec_hook('message_part_before',
        array('type' => $part->ctype_secondary, 'body' => $body, 'id' => $part->mime_id)
            + $p + array('safe' => false, 'plain' => false, 'inline_html' => true));

    // convert html to text/plain
    if ($data['plain'] && ($data['type'] == 'html' || $data['type'] == 'enriched')) {
        if ($data['type'] == 'enriched') {
            $data['body'] = rcube_enriched::to_html($data['body']);
        }

        $body = $RCMAIL->html2text($data['body']);
        $part->ctype_secondary = 'plain';
    }
    // text/html
    else if ($data['type'] == 'html') {
        $body = rcmail_wash_html($data['body'], $data, $part->replaces);
        $part->ctype_secondary = $data['type'];
    }
    // text/enriched
    else if ($data['type'] == 'enriched') {
        $body = rcube_enriched::to_html($data['body']);
        $body = rcmail_wash_html($body, $data, $part->replaces);
        $part->ctype_secondary = 'html';
    }
    else {
        // assert plaintext
        $body = $data['body'];
        $part->ctype_secondary = $data['type'] = 'plain';
    }

    // free some memory (hopefully)
    unset($data['body']);

    // plaintext postprocessing
    if ($part->ctype_secondary == 'plain') {
        $body = rcmail_plain_body($body, $part->ctype_parameters['format'] == 'flowed');
    }

    // allow post-processing of the message body
    $data = $RCMAIL->plugins->exec_hook('message_part_after',
        array('type' => $part->ctype_secondary, 'body' => $body, 'id' => $part->mime_id) + $data);

    return $data['body'];
}

/**
 * Handle links and citation marks in plain text message
 *
 * @param string  Plain text string
 * @param boolean Set to True if the source text is in format=flowed
 *
 * @return string Formatted HTML string
 */
function rcmail_plain_body($body, $flowed = false)
{
    $options   = array('flowed' => $flowed, 'wrap' => !$flowed, 'replacer' => 'rcmail_string_replacer');
    $text2html = new rcube_text2html($body, false, $options);
    $body      = $text2html->get_html();

    return $body;
}

/**
 * Callback function for washtml cleaning class
 */
function rcmail_washtml_callback($tagname, $attrib, $content, $washtml)
{
    switch ($tagname) {
    case 'form':
        $out = html::div('form', $content);
        break;

    case 'style':
        // Crazy big styles may freeze the browser (#1490539)
        // remove content with more than 5k lines
        if (substr_count($content, "\n") > 5000) {
            $out = '';
            break;
        }

        // decode all escaped entities and reduce to ascii strings
        $stripped = preg_replace('/[^a-zA-Z\(:;]/', '', rcube_utils::xss_entity_decode($content));

        // now check for evil strings like expression, behavior or url()
        if (!preg_match('/expression|behavior|javascript:|import[^a]/i', $stripped)) {
            if (!$washtml->get_config('allow_remote') && stripos($stripped, 'url(')) {
                $washtml->extlinks = true;
            }
            else {
                $out = html::tag('style', array('type' => 'text/css'), $content);
            }
            break;
        }

    default:
        $out = '';
    }

    return $out;
}

/**
 * return table with message headers
 */
function rcmail_message_headers($attrib, $headers=null)
{
    global $MESSAGE, $PRINT_MODE, $RCMAIL;
    static $sa_attrib;

    // keep header table attrib
    if (is_array($attrib) && !$sa_attrib && !$attrib['valueof']) {
        $sa_attrib = $attrib;
    }
    else if (!is_array($attrib) && is_array($sa_attrib)) {
        $attrib = $sa_attrib;
    }

    if (!isset($MESSAGE)) {
        return false;
    }

    // get associative array of headers object
    if (!$headers) {
        $headers_obj = $MESSAGE->headers;
        $headers     = get_object_vars($MESSAGE->headers);
    }
    else if (is_object($headers)) {
        $headers_obj = $headers;
        $headers     = get_object_vars($headers_obj);
    }
    else {
        $headers_obj = rcube_message_header::from_array($headers);
    }

    // show these headers
    $standard_headers = array('subject', 'from', 'sender', 'to', 'cc', 'bcc', 'replyto',
        'mail-reply-to', 'mail-followup-to', 'date', 'priority');
    $exclude_headers = $attrib['exclude'] ? explode(',', $attrib['exclude']) : array();
    $output_headers  = array();

    foreach ($standard_headers as $hkey) {
        if ($headers[$hkey])
            $value = $headers[$hkey];
        else if ($headers['others'][$hkey])
            $value = $headers['others'][$hkey];
        else if (!$attrib['valueof'])
            continue;

        if (in_array($hkey, $exclude_headers))
            continue;

        $ishtml       = false;
        $header_title = $RCMAIL->gettext(preg_replace('/(^mail-|-)/', '', $hkey));

        if ($hkey == 'date') {
            if ($PRINT_MODE)
                $header_value = $RCMAIL->format_date($value, $RCMAIL->config->get('date_long', 'x'));
            else
                $header_value = $RCMAIL->format_date($value);
        }
        else if ($hkey == 'priority') {
            if ($value) {
                $header_value = html::span('prio' . $value, rcube::Q(rcmail_localized_priority($value)));
                $ishtml       = true;
            }
            else {
                continue;
            }
        }
        else if ($hkey == 'replyto') {
            if ($headers['replyto'] != $headers['from']) {
                $header_value = rcmail_address_string($value, $attrib['max'], true,
                    $attrib['addicon'], $headers['charset'], $header_title);
                $ishtml = true;
            }
            else {
                continue;
            }
        }
        else if ($hkey == 'mail-reply-to') {
            if ($headers['mail-replyto'] != $headers['replyto']
                && $headers['replyto'] != $headers['from']
            ) {
                $header_value = rcmail_address_string($value, $attrib['max'], true,
                    $attrib['addicon'], $headers['charset'], $header_title);
                $ishtml = true;
            }
            else {
                continue;
            }
        }
        else if ($hkey == 'sender') {
            if ($headers['sender'] != $headers['from']) {
                $header_value = rcmail_address_string($value, $attrib['max'], true,
                    $attrib['addicon'], $headers['charset'], $header_title);
                $ishtml = true;
            }
            else {
                continue;
            }
        }
        else if ($hkey == 'mail-followup-to') {
            $header_value = rcmail_address_string($value, $attrib['max'], true,
                $attrib['addicon'], $headers['charset'], $header_title);
            $ishtml = true;
        }
        else if (in_array($hkey, array('from', 'to', 'cc', 'bcc'))) {
            $header_value = rcmail_address_string($value, $attrib['max'], true,
                $attrib['addicon'], $headers['charset'], $header_title);
            $ishtml = true;
        }
        else if ($hkey == 'subject' && empty($value)) {
            $header_value = $RCMAIL->gettext('nosubject');
        }
        else {
            $value        = is_array($value) ? implode(' ', $value) : $value;
            $header_value = trim(rcube_mime::decode_header($value, $headers['charset']));
        }

        $output_headers[$hkey] = array(
            'title' => $header_title,
            'value' => $header_value,
            'raw'   => $value,
            'html'  => $ishtml,
        );
    }

    $plugin = $RCMAIL->plugins->exec_hook('message_headers_output', array(
        'output'  => $output_headers,
        'headers' => $headers_obj,
        'exclude' => $exclude_headers, // readonly
        'folder'  => $MESSAGE->folder, // readonly
        'uid'     => $MESSAGE->uid,    // readonly
    ));

    // single header value is requested
    if (!empty($attrib['valueof'])) {
        $row = $plugin['output'][$attrib['valueof']];
        return $row['html'] ? $row['value'] : rcube::Q($row['value']);
    }

    // compose html table
    $table = new html_table(array('cols' => 2));

    foreach ($plugin['output'] as $hkey => $row) {
        $val = $row['html'] ? $row['value'] : rcube::Q($row['value']);

        $table->add(array('class' => 'header-title'), rcube::Q($row['title']));
        $table->add(array('class' => 'header '.$hkey), $val);
    }

    return $table->show($attrib);
}

/**
 * Convert Priority header value into a localized string
 */
function rcmail_localized_priority($value)
{
    global $RCMAIL;

    $labels_map = array(
        '1' => 'highest',
        '2' => 'high',
        '3' => 'normal',
        '4' => 'low',
        '5' => 'lowest',
    );

    if ($value && $labels_map[$value]) {
        return $RCMAIL->gettext($labels_map[$value]);
    }

    return '';
}

/**
 * return block to show full message headers
 */
function rcmail_message_full_headers($attrib)
{
    global $OUTPUT, $RCMAIL;

    $html = html::div(array('id' => "all-headers", 'class' => "all", 'style' => 'display:none'), html::div(array('id' => 'headers-source'), ''));
    $html .= html::div(array(
            'class'   => "more-headers show-headers",
            'onclick' => "return ".rcmail_output::JS_OBJECT_NAME.".command('show-headers','',this)",
            'title'   => $RCMAIL->gettext('togglefullheaders')
        ), '');

    $OUTPUT->add_gui_object('all_headers_row', 'all-headers');
    $OUTPUT->add_gui_object('all_headers_box', 'headers-source');

    return html::div($attrib, $html);
}

/**
 * Handler for the 'messagebody' GUI object
 *
 * @param array Named parameters
 * @return string HTML content showing the message body
 */
function rcmail_message_body($attrib)
{
    global $OUTPUT, $MESSAGE, $RCMAIL, $REMOTE_OBJECTS;

    if (!is_array($MESSAGE->parts) && empty($MESSAGE->body)) {
        return '';
    }

    if (!$attrib['id'])
        $attrib['id'] = 'rcmailMsgBody';

    $safe_mode = $MESSAGE->is_safe || intval($_GET['_safe']);
    $out       = '';
    $part_no   = 0;

    $header_attrib = array();
    foreach ($attrib as $attr => $value) {
        if (preg_match('/^headertable([a-z]+)$/i', $attr, $regs)) {
            $header_attrib[$regs[1]] = $value;
        }
    }

    if (!empty($MESSAGE->parts)) {
        foreach ($MESSAGE->parts as $part) {
            if ($part->type == 'headers') {
                $out .= html::div('message-partheaders', rcmail_message_headers(sizeof($header_attrib) ? $header_attrib : null, $part->headers));
            }
            else if ($part->type == 'content') {
                // unsupported (e.g. encrypted)
                if ($part->realtype) {
                    if ($part->realtype == 'multipart/encrypted' || $part->realtype == 'application/pkcs7-mime') {
                        if (!empty($_SESSION['browser_caps']['pgpmime']) && ($pgp_mime_part = $MESSAGE->get_multipart_encrypted_part())) {
                            $out .= html::span('part-notice', $RCMAIL->gettext('externalmessagedecryption'));
                            $OUTPUT->set_env('pgp_mime_part', $pgp_mime_part->mime_id);
                            $OUTPUT->set_env('pgp_mime_container', '#' . $attrib['id']);
                            $OUTPUT->add_label('loadingdata');
                        }

                        if (!$MESSAGE->encrypted_part) {
                            $out .= html::span('part-notice', $RCMAIL->gettext('encryptedmessage'));
                        }
                    }
                    continue;
                }
                else if (!$part->size) {
                    continue;
                }

                // Check if we have enough memory to handle the message in it
                // #1487424: we need up to 10x more memory than the body
                else if (!rcube_utils::mem_check($part->size * 10)) {
                    $out .= html::span('part-notice', $RCMAIL->gettext('messagetoobig'). ' '
                        . html::a('?_task=mail&_action=get&_download=1&_uid='.$MESSAGE->uid.'&_part='.$part->mime_id
                            .'&_mbox='. urlencode($MESSAGE->folder), $RCMAIL->gettext('download')));
                    continue;
                }

                // fetch part body
                $body = $MESSAGE->get_part_body($part->mime_id, true);

                // message is cached but not exists (#1485443), or other error
                if ($body === false) {
                    rcmail_message_error($MESSAGE->uid);
                }

                // check if the message body is PGP encrypted
                if (strpos($body, '-----BEGIN PGP MESSAGE-----') !== false) {
                    $OUTPUT->set_env('is_pgp_content', '#message-part' . ($part_no + 1));
                }

                $plugin = $RCMAIL->plugins->exec_hook('message_body_prefix',
                    array('part' => $part, 'prefix' => ''));

                $body = rcmail_print_body($body, $part, array('safe' => $safe_mode, 'plain' => !$RCMAIL->config->get('prefer_html')));

                if ($part->ctype_secondary == 'html') {
                    $container_id = 'message-htmlpart' . (++$part_no);
                    $body         = rcmail_html4inline($body, $container_id, 'rcmBody', $attrs, $safe_mode);
                    $div_attr     = array('class' => 'message-htmlpart', 'id' => $container_id);
                    $style        = array();

                    if (!empty($attrs)) {
                        foreach ($attrs as $a_idx => $a_val)
                            $style[] = $a_idx . ': ' . $a_val;
                        if (!empty($style))
                            $div_attr['style'] = implode('; ', $style);
                    }

                    $out .= html::div($div_attr, $plugin['prefix'] . $body);
                }
                else {
                    $container_id = 'message-part' . (++$part_no);
                    $div_attr     = array('class' => 'message-part', 'id' => $container_id);
                    $out .= html::div($div_attr, $plugin['prefix'] . $body);
                }
            }
        }
    }
    else {
        // Check if we have enough memory to handle the message in it
        // #1487424: we need up to 10x more memory than the body
        if (!rcube_utils::mem_check(strlen($MESSAGE->body) * 10)) {
            $out .= html::span('part-notice', $RCMAIL->gettext('messagetoobig'). ' '
                . html::a('?_task=mail&_action=get&_download=1&_uid='.$MESSAGE->uid.'&_part=0'
                    .'&_mbox='. urlencode($MESSAGE->folder), $RCMAIL->gettext('download')));
        }
        else {
            $plugin = $RCMAIL->plugins->exec_hook('message_body_prefix',
                array('part' => $MESSAGE, 'prefix' => ''));

            $out .= html::div('message-part',
                $plugin['prefix'] . rcmail_plain_body($MESSAGE->body));
        }
    }

    // list images after mail body
    if ($RCMAIL->config->get('inline_images', true) && !empty($MESSAGE->attachments)) {
        $thumbnail_size   = $RCMAIL->config->get('image_thumbnail_size', 240);
        $client_mimetypes = (array)$RCMAIL->config->get('client_mimetypes');

        foreach ($MESSAGE->attachments as $attach_prop) {
            // skip inline images
            if ($attach_prop->content_id && $attach_prop->disposition == 'inline') {
                continue;
            }

            // Content-Type: image/*...
            if ($mimetype = rcmail_part_image_type($attach_prop)) {
                // display thumbnails
                if ($thumbnail_size) {
                    $supported = in_array($mimetype, $client_mimetypes);
                    $show_link = array(
                        'href'    => $MESSAGE->get_part_url($attach_prop->mime_id, false),
                        'onclick' => sprintf(
                            'return %s.command(\'load-attachment\',\'%s\',this)',
                            rcmail_output::JS_OBJECT_NAME,
                            $attach_prop->mime_id)
                    );

                    $out .= html::p(array('class' => 'image-attachment', 'style' => $supported ? '' : 'display:none'),
                        html::a($show_link + array('class' => 'image-link', 'style' => sprintf('width:%dpx', $thumbnail_size)),
                            html::img(array(
                                'class' => 'image-thumbnail',
                                'src'   => $MESSAGE->get_part_url($attach_prop->mime_id, 'image') . '&_thumb=1',
                                'title' => $attach_prop->filename,
                                'alt'   => $attach_prop->filename,
                                'style' => sprintf('max-width:%dpx; max-height:%dpx', $thumbnail_size, $thumbnail_size),
                                'onload' => $supported ? '' : '$(this).parents(\'p.image-attachment\').show()',
                            ))
                        ) .
                        html::span('image-filename', rcube::Q($attach_prop->filename)) .
                        html::span('image-filesize', rcube::Q($RCMAIL->message_part_size($attach_prop))) .
                        html::span('attachment-links',
                            ($supported ? html::a($show_link, $RCMAIL->gettext('showattachment')) . '&nbsp;' : '') .
                                html::a($show_link['href'] . '&_download=1', $RCMAIL->gettext('download'))
                        ) .
                        html::br(array('style' => 'clear:both'))
                    );
                }
                else {
                    $out .= html::tag('fieldset', 'image-attachment',
                        html::tag('legend', 'image-filename', rcube::Q($attach_prop->filename)) .
                        html::p(array('align' => 'center'),
                            html::img(array(
                                'src'   => $MESSAGE->get_part_url($attach_prop->mime_id, 'image'),
                                'title' => $attach_prop->filename,
                                'alt'   => $attach_prop->filename,
                        )))
                    );
                }
            }
        }
    }

    // tell client that there are blocked remote objects
    if ($REMOTE_OBJECTS && !$safe_mode) {
        $OUTPUT->set_env('blockedobjects', true);
    }

    return html::div($attrib, $out);
}

function rcmail_part_image_type($part)
{
    $mimetype = strtolower($part->mimetype);

    // Skip TIFF/WEBP images if browser doesn't support this format
    // ...until we can convert them to JPEG
    $tiff_support = !empty($_SESSION['browser_caps']) && !empty($_SESSION['browser_caps']['tiff']);
    $tiff_support = $tiff_support || rcube_image::is_convertable('image/tiff');
    $webp_support = !empty($_SESSION['browser_caps']) && !empty($_SESSION['browser_caps']['webp']);
    $webp_support = $webp_support || rcube_image::is_convertable('image/webp');

    if ((!$tiff_support && $mimetype == 'image/tiff') || (!$webp_support && $mimetype == 'image/webp')) {
        return;
    }

    // Content-Type: image/*...
    if (strpos($mimetype, 'image/') === 0) {
        return rcmail_fix_mimetype($mimetype);
    }

    // Many clients use application/octet-stream, we'll detect mimetype
    // by checking filename extension

    // Supported image filename extensions to image type map
    $types = array(
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'bmp'  => 'image/bmp',
    );
    if ($tiff_support) {
        $types['tif']  = 'image/tiff';
        $types['tiff'] = 'image/tiff';
    }
    if ($webp_support) {
        $types['webp'] = 'image/webp';
    }

    if ($part->filename
        && $mimetype == 'application/octet-stream'
        && preg_match('/\.([^.]+)$/i', $part->filename, $m)
        && ($extension = strtolower($m[1]))
        && isset($types[$extension])
    ) {
        return $types[$extension];
    }
}

/**
 * modify a HTML message that it can be displayed inside a HTML page
 */
function rcmail_html4inline($body, $container_id, $body_class='', &$attributes=null, $allow_remote=false)
{
    $last_style_pos = 0;
    $cont_id        = $container_id . ($body_class ? ' div.'.$body_class : '');

    // find STYLE tags
    while (($pos = stripos($body, '<style', $last_style_pos)) && ($pos2 = stripos($body, '</style>', $pos))) {
        $pos = strpos($body, '>', $pos) + 1;
        $len = $pos2 - $pos;

        // replace all css definitions with #container [def]
        $styles = substr($body, $pos, $len);
        $styles = rcube_utils::mod_css_styles($styles, $cont_id, $allow_remote);

        $body = substr_replace($body, $styles, $pos, $len);
        $last_style_pos = $pos2 + strlen($styles) - $len;
    }

    // modify HTML links to open a new window if clicked
    $GLOBALS['rcmail_html_container_id'] = $container_id;
    $body = preg_replace_callback('/<(a|link|area)\s+([^>]+)>/Ui', 'rcmail_alter_html_link', $body);
    unset($GLOBALS['rcmail_html_container_id']);

    $body = preg_replace(array(
            // add comments arround html and other tags
            '/(<!DOCTYPE[^>]*>)/i',
            '/(<\?xml[^>]*>)/i',
            '/(<\/?html[^>]*>)/i',
            '/(<\/?head[^>]*>)/i',
            '/(<title[^>]*>.*<\/title>)/Ui',
            '/(<\/?meta[^>]*>)/i',
            // quote <? of php and xml files that are specified as text/html
            '/<\?/',
            '/\?>/',
            // replace <body> with <div>
            '/<body([^>]*)>/i',
            '/<\/body>/i',
        ),
        array(
            '<!--\\1-->',
            '<!--\\1-->',
            '<!--\\1-->',
            '<!--\\1-->',
            '<!--\\1-->',
            '<!--\\1-->',
            '&lt;?',
            '?&gt;',
            '<div class="' . $body_class . '"\\1>',
            '</div>',
        ),
        $body);

    $attributes = array();

    // Handle body attributes that doesn't play nicely with div elements
    $regexp = '/<div class="' . preg_quote($body_class, '/') . '"([^>]*)/';
    if (preg_match($regexp, $body, $m)) {
        $attrs = $m[0];

        // Get bgcolor, we'll set it as background-color of the message container
        if ($m[1] && preg_match('/bgcolor=["\']*([a-z0-9#]+)["\']*/i', $attrs, $mb)) {
            $attributes['background-color'] = $mb[1];
            $attrs = preg_replace('/bgcolor=["\']*[a-z0-9#]+["\']*/i', '', $attrs);
        }

        // Get background, we'll set it as background-image of the message container
        if ($m[1] && preg_match('/background=["\']*([^"\'>\s]+)["\']*/', $attrs, $mb)) {
            $attributes['background-image'] = 'url('.$mb[1].')';
            $attrs = preg_replace('/background=["\']*([^"\'>\s]+)["\']*/', '', $attrs);
        }

        if (!empty($attributes)) {
            $body = preg_replace($regexp, rtrim($attrs), $body, 1);
        }

        // handle body styles related to background image
        if ($attributes['background-image']) {
            // get body style
            if (preg_match('/#'.preg_quote($cont_id, '/').'\s+\{([^}]+)}/i', $body, $m)) {
                // get background related style
                $regexp = '/(background-position|background-repeat)\s*:\s*([^;]+);/i';
                if (preg_match_all($regexp, $m[1], $ma, PREG_SET_ORDER)) {
                    foreach ($ma as $style) {
                        $attributes[$style[1]] = $style[2];
                    }
                }
            }
        }
    }
    // make sure there's 'rcmBody' div, we need it for proper css modification
    // its name is hardcoded in rcmail_message_body() also
    else {
        $body = '<div class="' . $body_class . '">' . $body . '</div>';
    }

    return $body;
}

/**
 * parse link (a, link, area) attributes and set correct target
 */
function rcmail_alter_html_link($matches)
{
    global $RCMAIL;

    $tag    = strtolower($matches[1]);
    $attrib = html::parse_attrib_string($matches[2]);
    $end    = '>';

    // Remove non-printable characters in URL (#1487805)
    if ($attrib['href'])
        $attrib['href'] = preg_replace('/[\x00-\x1F]/', '', $attrib['href']);

    if ($tag == 'link' && preg_match('/^https?:\/\//i', $attrib['href'])) {
        $tempurl = 'tmp-' . md5($attrib['href']) . '.css';
        $_SESSION['modcssurls'][$tempurl] = $attrib['href'];
        $attrib['href'] = $RCMAIL->url(array('task' => 'utils', 'action' => 'modcss', 'u' => $tempurl, 'c' => $GLOBALS['rcmail_html_container_id']));
        $end = ' />';
    }
    else if (preg_match('/^mailto:(.+)/i', $attrib['href'], $mailto)) {
        list($mailto, $url) = explode('?', html_entity_decode($mailto[1], ENT_QUOTES, 'UTF-8'), 2);

        $url       = urldecode($url);
        $mailto    = urldecode($mailto);
        $addresses = rcube_mime::decode_address_list($mailto, null, true);
        $mailto    = array();

        // do sanity checks on recipients
        foreach ($addresses as $idx => $addr) {
            if (rcube_utils::check_email($addr['mailto'], false)) {
                $addresses[$idx] = $addr['mailto'];
                $mailto[]        = $addr['string'];
            }
            else {
                unset($addresses[$idx]);
            }
        }

        if (!empty($addresses)) {
            $attrib['href']    = 'mailto:' . implode(',', $addresses);
            $attrib['onclick'] = sprintf(
                "return %s.command('compose','%s',this)",
                rcmail_output::JS_OBJECT_NAME,
                rcube::JQ(implode(',', $mailto) . ($url ? "?$url" : '')));
        }
        else {
            $attrib['href']    = '#NOP';
            $attrib['onclick'] = '';
        }
    }
    else if (empty($attrib['href']) && !$attrib['name']) {
        $attrib['href']    = './#NOP';
        $attrib['onclick'] = 'return false';
    }
    else if (!empty($attrib['href']) && $attrib['href'][0] != '#') {
        $attrib['target'] = '_blank';
    }

    // Better security by adding rel="noreferrer" (#1484686)
    if (($tag == 'a' || $tag == 'area') && $attrib['href'] && $attrib['href'][0] != '#') {
        $attrib['rel'] = 'noreferrer';
    }

    // allowed attributes for a|link|area tags
    $allow = array('href','name','target','onclick','id','class','style','title',
        'rel','type','media','alt','coords','nohref','hreflang','shape');

    return "<$tag" . html::attrib_string($attrib, $allow) . $end;
}

/**
 * decode address string and re-format it as HTML links
 */
function rcmail_address_string($input, $max=null, $linked=false, $addicon=null, $default_charset=null, $title=null)
{
    global $RCMAIL, $PRINT_MODE;

    $a_parts = rcube_mime::decode_address_list($input, null, true, $default_charset);

    if (!sizeof($a_parts)) {
        return $input;
    }

    $c   = count($a_parts);
    $j   = 0;
    $out = '';
    $allvalues  = array();
    $show_email = $RCMAIL->config->get('message_show_email');

    if ($addicon && !isset($_SESSION['writeable_abook'])) {
        $_SESSION['writeable_abook'] = $RCMAIL->get_address_sources(true) ? true : false;
    }

    foreach ($a_parts as $part) {
        $j++;

        $name   = $part['name'];
        $mailto = $part['mailto'];
        $string = $part['string'];
        $valid  = rcube_utils::check_email($mailto, false);

        // phishing email prevention (#1488981), e.g. "valid@email.addr <phishing@email.addr>"
        if (!$show_email && $valid && $name && $name != $mailto && strpos($name, '@')) {
            $name = '';
        }

        // IDNA ASCII to Unicode
        if ($name == $mailto)
            $name = rcube_utils::idn_to_utf8($name);
        if ($string == $mailto)
            $string = rcube_utils::idn_to_utf8($string);
        $mailto = rcube_utils::idn_to_utf8($mailto);

        if ($PRINT_MODE) {
            $address = sprintf('%s &lt;%s&gt;', rcube::Q($name), rcube::Q($mailto));
        }
        else if ($valid) {
            if ($linked) {
                $attrs = array(
                    'href'    => 'mailto:' . $mailto,
                    'class'   => 'rcmContactAddress',
                    'onclick' => sprintf("return %s.command('compose','%s',this)",
                        rcmail_output::JS_OBJECT_NAME, rcube::JQ(format_email_recipient($mailto, $name))),
                );

                if ($show_email && $name && $mailto) {
                    $content = rcube::Q($name ? sprintf('%s <%s>', $name, $mailto) : $mailto);
                }
                else {
                    $content = rcube::Q($name ?: $mailto);
                    $attrs['title'] = $mailto;
                }

                $address = html::a($attrs, $content);
            }
            else {
                $address = html::span(array('title' => $mailto, 'class' => "rcmContactAddress"),
                    rcube::Q($name ?: $mailto));
            }

            if ($addicon && $_SESSION['writeable_abook']) {
                $address .= html::a(array(
                        'href'    => "#add",
                        'title'   => $RCMAIL->gettext('addtoaddressbook'),
                        'class'   => 'rcmaddcontact',
                        'onclick' => sprintf("return %s.command('add-contact','%s',this)",
                            rcmail_output::JS_OBJECT_NAME, rcube::JQ($string)),
                    ),
                    html::img(array(
                        'src'   => $RCMAIL->output->abs_url($addicon, true),
                        'alt'   => "Add contact",
                        'class' => 'noselect',
                )));
            }
        }
        else {
            $address = '';
            if ($name)
                $address .= rcube::Q($name);
            if ($mailto)
                $address = trim($address . ' ' . rcube::Q($name ? sprintf('<%s>', $mailto) : $mailto));
        }

        $address = html::span('adr', $address);
        $allvalues[] = $address;

        if (!$moreadrs)
            $out .= ($out ? ', ' : '') . $address;

        if ($max && $j == $max && $c > $j) {
            if ($linked) {
                $moreadrs = $c - $j;
            }
            else {
                $out .= '...';
                break;
            }
        }
    }

    if ($moreadrs) {
        if ($PRINT_MODE) {
            $out .= ' ' . html::a(array(
                    'href'    => '#more',
                    'class'   => 'morelink',
                    'onclick' => '$(this).hide().next().show()',
                ),
                rcube::Q($RCMAIL->gettext(array('name' => 'andnmore', 'vars' => array('nr' => $moreadrs)))))
                . html::span(array('style' => 'display:none'), join(', ', $allvalues));
        }
        else {
            $out .= ' ' . html::a(array(
                    'href'    => '#more',
                    'class'   => 'morelink',
                    'onclick' => sprintf("return %s.show_popup_dialog('%s','%s')",
                        rcmail_output::JS_OBJECT_NAME,
                        rcube::JQ(join(', ', $allvalues)),
                        rcube::JQ($title))
                ),
                rcube::Q($RCMAIL->gettext(array('name' => 'andnmore', 'vars' => array('nr' => $moreadrs)))));
        }
    }

    return $out;
}

/**
 * Wrap text to a given number of characters per line
 * but respect the mail quotation of replies messages (>).
 * Finally add another quotation level by prepending the lines
 * with >
 *
 * @param string Text to wrap
 * @param int    The line width
 * @return string The wrapped text
 */
function rcmail_wrap_and_quote($text, $length = 72)
{
    // Rebuild the message body with a maximum of $max chars, while keeping quoted message.
    $max   = max(75, $length + 8);
    $lines = preg_split('/\r?\n/', trim($text));
    $out   = '';

    foreach ($lines as $line) {
        // don't wrap already quoted lines
        if ($line[0] == '>') {
            $line = '>' . rtrim($line);
        }
        else if (mb_strlen($line) > $max) {
            $newline = '';

            foreach (explode("\n", rcube_mime::wordwrap($line, $length - 2)) as $l) {
                $newline .= strlen($l) ? "> $l\n" : ">\n";
            }

            $line = rtrim($newline);
        }
        else {
            $line = '> ' . $line;
        }

        // Append the line
        $out .= $line . "\n";
    }

    return rtrim($out, "\n");
}

function rcmail_draftinfo_encode($p)
{
    $parts = array();
    foreach ($p as $key => $val) {
        $encode  = $key == 'folder' || strpos($val, ';') !== false;
        $parts[] = $key . '=' . ($encode ? 'B::' . base64_encode($val) : $val);
    }

    return join('; ', $parts);
}

function rcmail_draftinfo_decode($str)
{
    $info = array();

    foreach (preg_split('/;\s+/', $str) as $part) {
        list($key, $val) = explode('=', $part, 2);
        if (strpos($val, 'B::') === 0) {
            $val = base64_decode(substr($val, 3));
        }
        else if ($key == 'folder') {
            $val = base64_decode($val);
        }

        $info[$key] = $val;
    }

    return $info;
}

/**
 * Send the MDN response
 *
 * @param mixed $message    Original message object (rcube_message) or UID
 * @param array $smtp_error SMTP error array (reference)
 *
 * @return boolean Send status
 */
function rcmail_send_mdn($message, &$smtp_error)
{
    global $RCMAIL;

    if (!is_object($message) || !is_a($message, 'rcube_message')) {
        $message = new rcube_message($message);
    }

    if ($message->headers->mdn_to && empty($message->headers->flags['MDNSENT']) &&
        ($RCMAIL->storage->check_permflag('MDNSENT') || $RCMAIL->storage->check_permflag('*'))
    ) {
        $identity  = rcmail_identity_select($message);
        $sender    = format_email_recipient($identity['email'], $identity['name']);
        $recipient = array_shift(rcube_mime::decode_address_list(
            $message->headers->mdn_to, 1, true, $message->headers->charset));
        $mailto    = $recipient['mailto'];

        $compose = new Mail_mime("\r\n");

        $compose->setParam('text_encoding', 'quoted-printable');
        $compose->setParam('html_encoding', 'quoted-printable');
        $compose->setParam('head_encoding', 'quoted-printable');
        $compose->setParam('head_charset', RCUBE_CHARSET);
        $compose->setParam('html_charset', RCUBE_CHARSET);
        $compose->setParam('text_charset', RCUBE_CHARSET);

        // compose headers array
        $headers = array(
            'Date'       => $RCMAIL->user_date(),
            'From'       => $sender,
            'To'         => $message->headers->mdn_to,
            'Subject'    => $RCMAIL->gettext('receiptread') . ': ' . $message->subject,
            'Message-ID' => $RCMAIL->gen_message_id($identity['email']),
            'X-Sender'   => $identity['email'],
            'References' => trim($message->headers->references . ' ' . $message->headers->messageID),
            'In-Reply-To' => $message->headers->messageID,
        );

        $report = "Final-Recipient: rfc822; {$identity['email']}\r\n"
            . "Original-Message-ID: {$message->headers->messageID}\r\n"
            . "Disposition: manual-action/MDN-sent-manually; displayed\r\n";

        if ($message->headers->to) {
            $report .= "Original-Recipient: {$message->headers->to}\r\n";
        }

        if ($agent = $RCMAIL->config->get('useragent')) {
            $headers['User-Agent'] = $agent;
            $report .= "Reporting-UA: $agent\r\n";
        }

        $to   = rcube_mime::decode_mime_string($message->headers->to, $message->headers->charset);
        $date = $RCMAIL->format_date($message->headers->date, $RCMAIL->config->get('date_long'));
        $body = $RCMAIL->gettext("yourmessage") . "\r\n\r\n" .
            "\t" . $RCMAIL->gettext("to") . ": {$to}\r\n" .
            "\t" . $RCMAIL->gettext("subject") . ": {$message->subject}\r\n" .
            "\t" . $RCMAIL->gettext("date") . ": {$date}\r\n" .
            "\r\n" . $RCMAIL->gettext("receiptnote");

        $compose->headers(array_filter($headers));
        $compose->setContentType('multipart/report', array('report-type'=> 'disposition-notification'));
        $compose->setTXTBody(rcube_mime::wordwrap($body, 75, "\r\n"));
        $compose->addAttachment($report, 'message/disposition-notification', 'MDNPart2.txt', false, '7bit', 'inline');

        // SMTP options
        $options = array('mdn_use_from' => (bool) $RCMAIL->config->get('mdn_use_from'));

        $sent = $RCMAIL->deliver_message($compose, $identity['email'], $mailto, $smtp_error, $body_file, $options, true);

        if ($sent) {
            $RCMAIL->storage->set_flag($message->uid, 'MDNSENT');
            return true;
        }
    }

    return false;
}

/**
 * Detect recipient identity from specified message
 */
function rcmail_identity_select($MESSAGE, $identities = null, $compose_mode = 'reply')
{
    $a_recipients = array();
    $a_names      = array();

    if ($identities === null) {
        $identities = rcmail::get_instance()->user->list_identities(null, true);
    }

    // extract all recipients of the reply-message
    if (is_object($MESSAGE->headers) && in_array($compose_mode, array('reply', 'forward'))) {
        $a_to = rcube_mime::decode_address_list($MESSAGE->headers->to, null, true, $MESSAGE->headers->charset);
        foreach ($a_to as $addr) {
            if (!empty($addr['mailto'])) {
                $a_recipients[] = strtolower($addr['mailto']);
                $a_names[]      = $addr['name'];
            }
        }

        if (!empty($MESSAGE->headers->cc)) {
            $a_cc = rcube_mime::decode_address_list($MESSAGE->headers->cc, null, true, $MESSAGE->headers->charset);
            foreach ($a_cc as $addr) {
                if (!empty($addr['mailto'])) {
                    $a_recipients[] = strtolower($addr['mailto']);
                    $a_names[]      = $addr['name'];
                }
            }
        }
    }

    // decode From: address
    $from = rcube_mime::decode_address_list($MESSAGE->headers->from, null, true, $MESSAGE->headers->charset);
    $from = array_shift($from);
    $from['mailto'] = strtolower($from['mailto']);

    $from_idx   = null;
    $found_idx  = array('to' => null, 'from' => null);
    $check_from = in_array($compose_mode, array('draft', 'edit', 'reply'));

    // Select identity
    foreach ($identities as $idx => $ident) {
        // use From: header when in edit/draft or reply-to-self
        if ($check_from && $from['mailto'] == strtolower($ident['email_ascii'])) {
            // remember first matching identity address
            if ($found_idx['from'] === null) {
                $found_idx['from'] = $idx;
            }
            // match identity name
            if ($from['name'] && $ident['name'] && $from['name'] == $ident['name']) {
                $from_idx = $idx;
                break;
            }
        }
        // use replied/forwarded message recipients
        else if (($found = array_search(strtolower($ident['email_ascii']), $a_recipients)) !== false) {
            // remember first matching identity address
            if ($found_idx['to'] === null) {
                $found_idx['to'] = $idx;
            }
            // match identity name
            if ($a_names[$found] && $ident['name'] && $a_names[$found] == $ident['name']) {
                $from_idx = $idx;
                break;
            }
        }
    }

    // If matching by name+address didn't find any matches,
    // get first found identity (address) if any
    if ($from_idx === null) {
        $from_idx = $found_idx['from'] !== null ? $found_idx['from'] : $found_idx['to'];
    }

    // Try Return-Path
    if ($from_idx === null && ($return_path = $MESSAGE->headers->others['return-path'])) {
        $return_path = array_map('strtolower', (array) $return_path);

        foreach ($identities as $idx => $ident) {
            // Return-Path header contains an email address, but on some mailing list
            // it can be e.g. <pear-dev-return-55250-local=domain.tld@lists.php.net>
            // where local@domain.tld is the address we're looking for (#1489241)
            $ident1 = strtolower($ident['email_ascii']);
            $ident2 = str_replace('@', '=', $ident1);
            $ident1 = '<' . $ident1 . '>';
            $ident2 = '-' . $ident2 . '@';

            foreach ($return_path as $path) {
                if ($path == $ident1 || stripos($path, $ident2)) {
                    $from_idx = $idx;
                    break 2;
                }
            }
        }
    }

    // See identity_select plugin for example usage of this hook
    $plugin = rcmail::get_instance()->plugins->exec_hook('identity_select',
        array('message' => $MESSAGE, 'identities' => $identities, 'selected' => $from_idx));

    $selected = $plugin['selected'];

    // default identity is always first on the list
    return $identities[$selected !== null ? $selected : 0];
}

// Fixes some content-type names
function rcmail_fix_mimetype($name)
{
    $map = array(
        'image/x-ms-bmp' => 'image/bmp', // #1490282
    );

    $name = strtolower($name);

    if ($alias = $map[$name]) {
        $name = $alias;
    }
    // Some versions of Outlook create garbage Content-Type:
    // application/pdf.A520491B_3BF7_494D_8855_7FAC2C6C0608
    else if (preg_match('/^application\/pdf.+/', $name)) {
        $name = 'application/pdf';
    }
    // treat image/pjpeg (image/pjpg, image/jpg) as image/jpeg (#1489097)
    else if (preg_match('/^image\/p?jpe?g$/', $name)) {
        $name = 'image/jpeg';
    }

    return $name;
}

// return attachment filename, handle empty filename case
function rcmail_attachment_name($attachment, $display = false)
{
    global $RCMAIL;

    $filename = $attachment->filename;

    if ($filename === null || $filename === '') {
        if ($attachment->mimetype == 'text/html') {
            $filename = $RCMAIL->gettext('htmlmessage');
        }
        else {
            $ext      = (array) rcube_mime::get_mime_extensions($attachment->mimetype);
            $ext      = array_shift($ext);
            $filename = $RCMAIL->gettext('messagepart') . ' ' . $attachment->mime_id;
            if ($ext) {
                $filename .= '.' . $ext;
            }
        }
    }

    $filename = preg_replace('[\r\n]', '', $filename);

    // Display smart names for some known mimetypes
    if ($display) {
        if (preg_match('/application\/(pgp|pkcs7)-signature/i', $attachment->mimetype)) {
            $filename = $RCMAIL->gettext('digitalsig');
        }
    }

    return $filename;
}

function rcmail_search_filter($attrib)
{
    global $RCMAIL;

    if (!strlen($attrib['id'])) {
        $attrib['id'] = 'rcmlistfilter';
    }

    $attrib['onchange'] = rcmail_output::JS_OBJECT_NAME.'.filter_mailbox(this.value)';

    // Content-Type values of messages with attachments
    // the same as in app.js:add_message_row()
    $ctypes = array('application/', 'multipart/m', 'multipart/signed', 'multipart/report');

    // Build search string of "with attachment" filter
    $attachment = trim(str_repeat(' OR', count($ctypes)-1));
    foreach ($ctypes as $type) {
        $attachment .= ' HEADER Content-Type ' . rcube_imap_generic::escape($type);
    }

    $select = new html_select($attrib);
    $select->add($RCMAIL->gettext('all'), 'ALL');
    $select->add($RCMAIL->gettext('unread'), 'UNSEEN');
    $select->add($RCMAIL->gettext('flagged'), 'FLAGGED');
    $select->add($RCMAIL->gettext('unanswered'), 'UNANSWERED');
    if (!$RCMAIL->config->get('skip_deleted')) {
        $select->add($RCMAIL->gettext('deleted'), 'DELETED');
        $select->add($RCMAIL->gettext('undeleted'), 'UNDELETED');
    }
    $select->add($RCMAIL->gettext('withattachment'), $attachment);
    $select->add($RCMAIL->gettext('priority').': '.$RCMAIL->gettext('highest'), 'HEADER X-PRIORITY 1');
    $select->add($RCMAIL->gettext('priority').': '.$RCMAIL->gettext('high'), 'HEADER X-PRIORITY 2');
    $select->add($RCMAIL->gettext('priority').': '.$RCMAIL->gettext('normal'), 'NOT HEADER X-PRIORITY 1 NOT HEADER X-PRIORITY 2 NOT HEADER X-PRIORITY 4 NOT HEADER X-PRIORITY 5');
    $select->add($RCMAIL->gettext('priority').': '.$RCMAIL->gettext('low'), 'HEADER X-PRIORITY 4');
    $select->add($RCMAIL->gettext('priority').': '.$RCMAIL->gettext('lowest'), 'HEADER X-PRIORITY 5');

    $RCMAIL->output->add_gui_object('search_filter', $attrib['id']);

    return $select->show($_REQUEST['_search'] ? $_SESSION['search_filter'] : 'ALL');
}

function rcmail_search_interval($attrib)
{
    global $RCMAIL;

    if (!strlen($attrib['id'])) {
        $attrib['id'] = 'rcmsearchinterval';
    }

    $select = new html_select($attrib);
    $select->add('', '');

    foreach (array('1W', '1M', '1Y', '-1W', '-1M', '-1Y') as $value) {
        $select->add($RCMAIL->gettext('searchinterval' . $value), $value);
    }

    $RCMAIL->output->add_gui_object('search_interval', $attrib['id']);

    return $select->show($_REQUEST['_search'] ? $_SESSION['search_interval'] : '');
}

function rcmail_message_error()
{
    global $RCMAIL;

    // Set env variables for messageerror.html template
    if ($RCMAIL->action == 'show') {
        $mbox_name = $RCMAIL->storage->get_folder();

        $RCMAIL->output->set_env('mailbox', $mbox_name);
        $RCMAIL->output->set_env('uid', null);
    }

    // display error message
    $RCMAIL->output->show_message('messageopenerror', 'error');
    // ... display message error page
    $RCMAIL->output->send('messageerror');
}

function rcmail_message_import_form($attrib = array())
{
    global $RCMAIL;

    $RCMAIL->output->add_label('selectimportfile','importwait');

    $input_attr = array(
        'multiple' => true,
        'name'     => '_file[]',
        'accept'   => ".eml, .mbox, message/rfc822, text/*",
    );

    $attrib['prefix'] = html::tag('input', array('type' => 'hidden', 'name' => '_unlock', 'value' => ''))
        . html::tag('input', array('type' => 'hidden', 'name' => '_framed', 'value' => '1'));

    return $RCMAIL->upload_form($attrib, 'importform', 'import-messages', $input_attr);
}

/**
 * Add groups from the given address source to the address book widget
 */
function rcmail_compose_contact_groups($abook, $source_id, $search = null, $search_mode = 0)
{
    global $RCMAIL, $OUTPUT;

    $jsresult = array();
    foreach ($abook->list_groups($search, $search_mode) as $group) {
        $abook->reset();
        $abook->set_group($group['ID']);

        // group (distribution list) with email address(es)
        if ($group['email']) {
            foreach ((array)$group['email'] as $email) {
                $row_id = 'G'.$group['ID'];
                $jsresult[$row_id] = format_email_recipient($email, $group['name']);
                $OUTPUT->command('add_contact_row', $row_id, array(
                    'contactgroup' => html::span(array('title' => $email), rcube::Q($group['name']))), 'group');
            }
        }
        // make virtual groups clickable to list their members
        else if ($group['virtual']) {
            $row_id = 'G'.$group['ID'];
            $OUTPUT->command('add_contact_row', $row_id, array(
                'contactgroup' => html::a(array(
                    'href' => '#list',
                    'rel' => $group['ID'],
                    'title' => $RCMAIL->gettext('listgroup'),
                    'onclick' => sprintf("return %s.command('pushgroup',{'source':'%s','id':'%s'},this,event)",
                                    rcmail_output::JS_OBJECT_NAME, $source_id, $group['ID']),
                ), rcube::Q($group['name']) . '&nbsp;' . html::span('action', '&raquo;'))),
                'group',
                array('ID' => $group['ID'], 'name' => $group['name'], 'virtual' => true));
        }
        // show group with count
        else if (($result = $abook->count()) && $result->count) {
            $row_id = 'E'.$group['ID'];
            $jsresult[$row_id] = $group['name'];
            $OUTPUT->command('add_contact_row', $row_id, array(
                'contactgroup' => rcube::Q($group['name'] . ' (' . intval($result->count) . ')')), 'group');
        }
    }

    $abook->reset();
    $abook->set_group(0);

    return $jsresult;
}

function rcmail_save_attachment($message, $pid, $compose_id, $params = array())
{
    global $COMPOSE;

    $rcmail  = rcmail::get_instance();
    $storage = $rcmail->get_storage();

    if ($pid) {
        // attachment requested
        $part     = $message->mime_parts[$pid];
        $size     = $part->size;
        $mimetype = $part->ctype_primary . '/' . $part->ctype_secondary;
        $filename = $params['filename'] ?: rcmail_attachment_name($part);
    }
    else if (is_object($message)) {
        // the whole message requested
        $size     = $message->size;
        $mimetype = 'message/rfc822';
        $filename = $params['filename'] ?: 'message_rfc822.eml';
    }
    else if (is_string($message)) {
        // the whole message requested
        $size     = strlen($message);
        $data     = $message;
        $mimetype = $params['mimetype'];
        $filename = $params['filename'];
    }

    if (!isset($data)) {
        // don't load too big attachments into memory
        if (!rcube_utils::mem_check($size)) {
            $temp_dir = unslashify($rcmail->config->get('temp_dir'));
            $path     = tempnam($temp_dir, 'rcmAttmnt');

            if ($fp = fopen($path, 'w')) {
                if ($pid) {
                    // part body
                    $message->get_part_body($pid, false, 0, $fp);
                }
                else {
                    // complete message
                    $storage->get_raw_body($message->uid, $fp);
                }

                fclose($fp);
            }
            else {
                return false;
            }
        }
        else if ($pid) {
            // part body
            $data = $message->get_part_body($pid);
        }
        else {
            // complete message
            $data = $storage->get_raw_body($message->uid);
        }
    }

    $attachment = array(
        'group'      => $compose_id,
        'name'       => $filename,
        'mimetype'   => $mimetype,
        'content_id' => $part ? $part->content_id : null,
        'data'       => $data,
        'path'       => $path,
        'size'       => $path ? filesize($path) : strlen($data),
        'charset'    => $part ? $part->charset : $params['charset'],
    );

    $attachment = $rcmail->plugins->exec_hook('attachment_save', $attachment);

    if ($attachment['status']) {
        unset($attachment['data'], $attachment['status'], $attachment['content_id'], $attachment['abort']);

        // rcube_session::append() replaces current session data with the old values
        // (in rcube_session::reload()). This is a problem in 'compose' action, because before
        // the first append() use we set some important data in the session.
        // It also overwrites attachments list. Fixing reload() is not so simple if possible
        // as we don't really know what has been added and what removed in meantime.
        // So, for now we'll do not use append() on 'compose' action (#1490608).

        if ($rcmail->action == 'compose') {
            $COMPOSE['attachments'][$attachment['id']] = $attachment;
        }
        else {
            $rcmail->session->append('compose_data_' . $compose_id . '.attachments', $attachment['id'], $attachment);
        }

        return $attachment;
    }
    else if ($path) {
        @unlink($path);
    }

    return false;
}

// Return mimetypes supported by the browser
function rcmail_supported_mimetypes()
{
    $rcmail = rcube::get_instance();

    // mimetypes supported by the browser (default settings)
    $mimetypes = (array) $rcmail->config->get('client_mimetypes');

    // Remove unsupported types, which makes that attachment which cannot be
    // displayed in a browser will be downloaded directly without displaying an overlay page
    if (empty($_SESSION['browser_caps']['pdf']) && ($key = array_search('application/pdf', $mimetypes)) !== false) {
        unset($mimetypes[$key]);
    }

    if (empty($_SESSION['browser_caps']['flash']) && ($key = array_search('application/x-shockwave-flash', $mimetypes)) !== false) {
        unset($mimetypes[$key]);
    }

    foreach (array('tiff', 'webp') as $type) {
        if (empty($_SESSION['browser_caps'][$type]) && ($key = array_search('image/' . $type, $mimetypes)) !== false) {
            // can we convert it to jpeg?
            if (!rcube_image::is_convertable('image/' . $type)) {
                unset($mimetypes[$key]);
            }
        }
    }

    // @TODO: support mail preview for compose attachments
    if ($rcmail->action != 'compose' && !in_array('message/rfc822', $mimetypes)) {
        $mimetypes[] = 'message/rfc822';
    }

    return array_values($mimetypes);
}
