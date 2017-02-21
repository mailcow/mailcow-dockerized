<?php

/**
 * Archive
 *
 * Plugin that adds a new button to the mailbox toolbar
 * to move messages to a (user selectable) archive folder.
 *
 * @version 3.0
 * @license GNU GPLv3+
 * @author Andre Rodier, Thomas Bruederli, Aleksander Machniak
 */
class archive extends rcube_plugin
{
    public $task = 'settings|mail';


    function init()
    {
        $rcmail = rcmail::get_instance();

        // register special folder type
        rcube_storage::$folder_types[] = 'archive';

        if ($rcmail->task == 'mail' && ($rcmail->action == '' || $rcmail->action == 'show')
            && ($archive_folder = $rcmail->config->get('archive_mbox'))
        ) {
            $skin_path = $this->local_skin_path();
            if (is_file($this->home . "/$skin_path/archive.css")) {
                $this->include_stylesheet("$skin_path/archive.css");
            }

            $this->include_script('archive.js');
            $this->add_texts('localization', true);
            $this->add_button(
                array(
                    'type'     => 'link',
                    'label'    => 'buttontext',
                    'command'  => 'plugin.archive',
                    'class'    => 'button buttonPas archive disabled',
                    'classact' => 'button archive',
                    'width'    => 32,
                    'height'   => 32,
                    'title'    => 'buttontitle',
                    'domain'   => $this->ID,
                ),
                'toolbar');

            // register hook to localize the archive folder
            $this->add_hook('render_mailboxlist', array($this, 'render_mailboxlist'));

            // set env variables for client
            $rcmail->output->set_env('archive_folder', $archive_folder);
            $rcmail->output->set_env('archive_type', $rcmail->config->get('archive_type',''));
        }
        else if ($rcmail->task == 'mail') {
            // handler for ajax request
            $this->register_action('plugin.move2archive', array($this, 'move_messages'));
        }
        else if ($rcmail->task == 'settings') {
            $this->add_hook('preferences_list', array($this, 'prefs_table'));
            $this->add_hook('preferences_save', array($this, 'save_prefs'));
        }
    }

    /**
     * Hook to give the archive folder a localized name in the mailbox list
     */
    function render_mailboxlist($p)
    {
        $rcmail         = rcmail::get_instance();
        $archive_folder = $rcmail->config->get('archive_mbox');
        $show_real_name = $rcmail->config->get('show_real_foldernames');

        // set localized name for the configured archive folder
        if ($archive_folder && !$show_real_name) {
            if (isset($p['list'][$archive_folder])) {
                $p['list'][$archive_folder]['name'] = $this->gettext('archivefolder');
            }
            else {
                // search in subfolders
                $this->_mod_folder_name($p['list'], $archive_folder, $this->gettext('archivefolder'));
            }
        }

        return $p;
    }

    /**
     * Helper method to find the archive folder in the mailbox tree
     */
    private function _mod_folder_name(&$list, $folder, $new_name)
    {
        foreach ($list as $idx => $item) {
            if ($item['id'] == $folder) {
                $list[$idx]['name'] = $new_name;
                return true;
            }
            else if (!empty($item['folders'])) {
                if ($this->_mod_folder_name($list[$idx]['folders'], $folder, $new_name)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Plugin action to move the submitted list of messages to the archive subfolders
     * according to the user settings and their headers.
     */
    function move_messages()
    {
        $rcmail = rcmail::get_instance();

        // only process ajax requests
        if (!$rcmail->output->ajax_call) {
            return;
        }

        $this->add_texts('localization');

        $storage        = $rcmail->get_storage();
        $delimiter      = $storage->get_hierarchy_delimiter();
        $read_on_move   = (bool) $rcmail->config->get('read_on_archive');
        $archive_type   = $rcmail->config->get('archive_type', '');
        $archive_folder = $rcmail->config->get('archive_mbox');
        $archive_prefix = $archive_folder . $delimiter;
        $current_mbox   = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $search_request = rcube_utils::get_input_value('_search', rcube_utils::INPUT_GPC);
        $uids           = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);

        // count messages before changing anything
        if ($_POST['_from'] != 'show') {
            $threading = (bool) $storage->get_threading();
            $old_count = $storage->count(null, $threading ? 'THREADS' : 'ALL');
            $old_pages = ceil($old_count / $storage->get_pagesize());
        }

        $count = 0;

        // this way response handler for 'move' action will be executed
        $rcmail->action = 'move';
        $this->result   = array(
            'reload'       => false,
            'error'        => false,
            'sources'      => array(),
            'destinations' => array(),
        );

        foreach (rcmail::get_uids(null, null, $multifolder) as $mbox => $uids) {
            if (!$archive_folder  || strpos($mbox, $archive_prefix) === 0) {
                $count = count($uids);
                continue;
            }
            else if (!$archive_type || $archive_type == 'folder') {
                $folder = $archive_folder;

                if ($archive_type == 'folder') {
                    // compose full folder path
                    $folder .= $delimiter . $mbox;

                    // create archive subfolder if it doesn't yet exist
                    $this->subfolder_worker($folder);
                }

                $count += $this->move_messages_worker($uids, $mbox, $folder, $read_on_move);
            }
            else {
                if ($uids == '*') {
                    $index = $storage->index(null, rcmail_sort_column(), rcmail_sort_order());
                    $uids  = $index->get();
                }

                $messages = $storage->fetch_headers($mbox, $uids);
                $execute  = array();

                foreach ($messages as $message) {
                    $subfolder = null;
                    switch ($archive_type) {
                    case 'year':
                        $subfolder = $rcmail->format_date($message->timestamp, 'Y');
                        break;

                    case 'month':
                        $subfolder = $rcmail->format_date($message->timestamp, 'Y')
                            . $delimiter . $rcmail->format_date($message->timestamp, 'm');
                        break;

                    case 'sender':
                        $from = $message->get('from');
                        preg_match('/[\b<](.+@.+)[\b>]/i', $from, $m);
                        $subfolder = $m[1] ?: $this->gettext('unkownsender');

                        // replace reserved characters in folder name
                        $repl = $delimiter == '-' ? '_' : '-';
                        $replacements[$delimiter] = $repl;
                        $replacements['.'] = $repl;  // some IMAP server do not allow . characters
                        $subfolder = strtr($subfolder, $replacements);
                        break;
                    }

                    // compose full folder path
                    $folder = $archive_folder . ($subfolder ? $delimiter . $subfolder : '');

                    $execute[$folder][] = $message->uid;
                }

                foreach ($execute as $folder => $uids) {
                    // create archive subfolder if it doesn't yet exist
                    $this->subfolder_worker($folder);

                    $count += $this->move_messages_worker($uids, $mbox, $folder, $read_on_move);
                }
            }
        }

        if ($this->result['error']) {
            if ($_POST['_from'] != 'show') {
                $rcmail->output->command('list_mailbox');
            }

            $rcmail->output->show_message($this->gettext('archiveerror'), 'warning');
            $rcmail->output->send();
        }

        if (!empty($_POST['_refresh'])) {
            // FIXME: send updated message rows instead of reloading the entire list
            $rcmail->output->command('refresh_list');
        }
        else {
            $addrows = true;
        }

        // refresh saved search set after moving some messages
        if ($search_request && $rcmail->storage->get_search_set()) {
            $_SESSION['search'] = $rcmail->storage->refresh_search();
        }

        if ($_POST['_from'] == 'show') {
            if ($next = rcube_utils::get_input_value('_next_uid', rcube_utils::INPUT_GPC)) {
                $rcmail->output->command('show_message', $next);
            }
            else {
                $rcmail->output->command('command', 'list');
            }

            $rcmail->output->send();
        }

        $mbox           = $storage->get_folder();
        $msg_count      = $storage->count(null, $threading ? 'THREADS' : 'ALL');
        $exists         = $storage->count($mbox, 'EXISTS', true);
        $page_size      = $storage->get_pagesize();
        $page           = $storage->get_page();
        $pages          = ceil($msg_count / $page_size);
        $nextpage_count = $old_count - $page_size * $page;
        $remaining      = $msg_count - $page_size * ($page - 1);

        // jump back one page (user removed the whole last page)
        if ($page > 1 && $remaining == 0) {
            $page -= 1;
            $storage->set_page($page);
            $_SESSION['page'] = $page;
            $jump_back = true;
        }

        // update message count display
        $rcmail->output->set_env('messagecount', $msg_count);
        $rcmail->output->set_env('current_page', $page);
        $rcmail->output->set_env('pagecount', $pages);
        $rcmail->output->set_env('exists', $exists);

        // update mailboxlist
        $unseen_count = $msg_count ? $storage->count($mbox, 'UNSEEN') : 0;
        $old_unseen   = rcmail_get_unseen_count($mbox);
        $quota_root   = $multifolder ? $this->result['sources'][0] : 'INBOX';

        if ($old_unseen != $unseen_count) {
            $rcmail->output->command('set_unread_count', $mbox, $unseen_count, ($mbox == 'INBOX'));
            rcmail_set_unseen_count($mbox, $unseen_count);
        }

        $rcmail->output->command('set_quota', $rcmail->quota_content(null, $quota_root));
        $rcmail->output->command('set_rowcount', rcmail_get_messagecount_text($msg_count), $mbox);

        if ($threading) {
            $count = rcube_utils::get_input_value('_count', rcube_utils::INPUT_POST);
        }

        // add new rows from next page (if any)
        if ($addrows && $count && $uids != '*' && ($jump_back || $nextpage_count > 0)) {
            $a_headers = $storage->list_messages($mbox, null,
                rcmail_sort_column(), rcmail_sort_order(), $jump_back ? null : $count);

            rcmail_js_message_list($a_headers, false);
        }

        if ($this->result['reload']) {
            $rcmail->output->show_message($this->gettext('archivedreload'), 'confirmation');
        }
        else {
            $rcmail->output->show_message($this->gettext('archived'), 'confirmation');

            if (!$read_on_move) {
                foreach ($this->result['destinations'] as $folder) {
                    rcmail_send_unread_count($folder, true);
                }
            }
        }

        // send response
        $rcmail->output->send();
    }

    /**
     * Move messages from one folder to another and mark as read if needed
     */
    private function move_messages_worker($uids, $from_mbox, $to_mbox, $read_on_move)
    {
        $storage = rcmail::get_instance()->get_storage();

        if ($read_on_move) {
            // don't flush cache (4th argument)
            $storage->set_flag($uids, 'SEEN', $from_mbox, true);
        }

        // move message to target folder
        if ($storage->move_message($uids, $to_mbox, $from_mbox)) {
            if (!in_array($from_mbox, $this->result['sources'])) {
                $this->result['sources'][] = $from_mbox;
            }
            if (!in_array($to_mbox, $this->result['destinations'])) {
                $this->result['destinations'][] = $to_mbox;
            }

            return count($uids);
        }

        $this->result['error'] = true;
    }

    /**
     * Create archive subfolder if it doesn't yet exist
     */
    private function subfolder_worker($folder)
    {
        $storage   = rcmail::get_instance()->get_storage();
        $delimiter = $storage->get_hierarchy_delimiter();

        if ($this->folders === null) {
            $this->folders = $storage->list_folders('', $archive_folder . '*', 'mail', null, true);
        }

        if (!in_array($folder, $this->folders)) {
            $path = explode($delimiter, $folder);

            // we'll create all folders in the path
            for ($i=0; $i<count($path); $i++) {
                $_folder = implode($delimiter, array_slice($path, 0, $i+1));
                if (!in_array($_folder, $this->folders)) {
                    if ($storage->create_folder($_folder, true)) {
                        $this->result['reload'] = true;
                        $this->folders[] = $_folder;
                    }
                }
            }
        }
    }

    /**
     * Hook to inject plugin-specific user settings
     */
    function prefs_table($args)
    {
        global $CURR_SECTION;

        $this->add_texts('localization');

        $rcmail        = rcmail::get_instance();
        $dont_override = $rcmail->config->get('dont_override', array());

        if ($args['section'] == 'folders' && !in_array('archive_mbox', $dont_override)) {
            $mbox = $rcmail->config->get('archive_mbox');
            $type = $rcmail->config->get('archive_type');

            // load folders list when needed
            if ($CURR_SECTION) {
                $select = $rcmail->folder_selector(array(
                        'noselection'   => '---',
                        'realnames'     => true,
                        'maxlength'     => 30,
                        'folder_filter' => 'mail',
                        'folder_rights' => 'w',
                        'onchange'      => "if ($(this).val() == 'INBOX') $(this).val('')",
                ));
            }
            else {
                $select = new html_select();
            }

            $args['blocks']['main']['options']['archive_mbox'] = array(
                'title'   => $this->gettext('archivefolder'),
                'content' => $select->show($mbox, array('name' => "_archive_mbox"))
            );

            // add option for structuring the archive folder
            $archive_type = new html_select(array('name' => '_archive_type', 'id' => 'ff_archive_type'));
            $archive_type->add($this->gettext('none'), '');
            $archive_type->add($this->gettext('archivetypeyear'), 'year');
            $archive_type->add($this->gettext('archivetypemonth'), 'month');
            $archive_type->add($this->gettext('archivetypesender'), 'sender');
            $archive_type->add($this->gettext('archivetypefolder'), 'folder');

            $args['blocks']['archive'] = array(
                'name' => rcube::Q($this->gettext('settingstitle')),
                'options' => array('archive_type' => array(
                        'title'   => $this->gettext('archivetype'),
                        'content' => $archive_type->show($type)
                    )
                )
            );
        }
        else if ($args['section'] == 'server' && !in_array('read_on_archive', $dont_override)) {
            $chbox = new html_checkbox(array('name' => '_read_on_archive', 'id' => 'ff_read_on_archive', 'value' => 1));
            $args['blocks']['main']['options']['read_on_archive'] = array(
                'title'   => $this->gettext('readonarchive'),
                'content' => $chbox->show($rcmail->config->get('read_on_archive') ? 1 : 0)
            );
        }

        return $args;
    }

    /**
     * Hook to save plugin-specific user settings
     */
    function save_prefs($args)
    {
        $rcmail        = rcmail::get_instance();
        $dont_override = $rcmail->config->get('dont_override', array());

        if ($args['section'] == 'folders' && !in_array('archive_mbox', $dont_override)) {
            $args['prefs']['archive_type'] = rcube_utils::get_input_value('_archive_type', rcube_utils::INPUT_POST);
        }
        else if ($args['section'] == 'server' && !in_array('read_on_archive', $dont_override)) {
            $args['prefs']['read_on_archive'] = (bool) rcube_utils::get_input_value('_read_on_archive', rcube_utils::INPUT_POST);
        }

        return $args;
    }
}
