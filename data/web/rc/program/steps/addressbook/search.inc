<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/search.inc                                  |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2011, The Roundcube Dev Team                       |
 | Copyright (C) 2011, Kolab Systems AG                                  |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Search action (and form) for address book contacts                  |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <machniak@kolabsys.com>                   |
 +-----------------------------------------------------------------------+
*/

if ($RCMAIL->action == 'search-create') {
    $id   = rcube_utils::get_input_value('_search', rcube_utils::INPUT_POST);
    $name = rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST, true);

    if (($params = $_SESSION['search_params']) && $params['id'] == $id) {

        $data = array(
            'type' => rcube_user::SEARCH_ADDRESSBOOK,
            'name' => $name,
            'data' => array(
                'fields' => $params['data'][0],
                'search' => $params['data'][1],
            ),
        );

        $plugin = $RCMAIL->plugins->exec_hook('saved_search_create', array('data' => $data));

        if (!$plugin['abort'])
            $result = $RCMAIL->user->insert_search($plugin['data']);
        else
            $result = $plugin['result'];
    }

    if ($result) {
        $OUTPUT->show_message('savedsearchcreated', 'confirmation');
        $OUTPUT->command('insert_saved_search', rcube::Q($name), rcube::Q($result));
    }
    else
        $OUTPUT->show_message($plugin['message'] ?: 'savedsearchcreateerror', 'error');

    $OUTPUT->send();
}

if ($RCMAIL->action == 'search-delete') {
    $id = rcube_utils::get_input_value('_sid', rcube_utils::INPUT_POST);

    $plugin = $RCMAIL->plugins->exec_hook('saved_search_delete', array('id' => $id));

    if (!$plugin['abort'])
        $result = $RCMAIL->user->delete_search($id);
    else
        $result = $plugin['result'];

    if ($result) {
        $OUTPUT->show_message('savedsearchdeleted', 'confirmation');
        $OUTPUT->command('remove_search_item', rcube::Q($id));
        // contact list will be cleared, clear also page counter
        $OUTPUT->command('set_rowcount', $RCMAIL->gettext('nocontactsfound'));
        $OUTPUT->set_env('pagecount', 0);
    }
    else
        $OUTPUT->show_message($plugin['message'] ?: 'savedsearchdeleteerror', 'error');

    $OUTPUT->send();
}


if (!isset($_GET['_form'])) {
    rcmail_contact_search();
}

$OUTPUT->add_handler('searchform', 'rcmail_contact_search_form');
$OUTPUT->send('contactsearch');


function rcmail_contact_search()
{
    global $RCMAIL, $OUTPUT, $SEARCH_MODS_DEFAULT, $PAGE_SIZE;

    $adv = isset($_POST['_adv']);
    $sid = rcube_utils::get_input_value('_sid', rcube_utils::INPUT_GET);

    // get search criteria from saved search
    if ($sid && ($search = $RCMAIL->user->get_search($sid))) {
        $fields = $search['data']['fields'];
        $search = $search['data']['search'];
    }
    // get fields/values from advanced search form
    else if ($adv) {
        foreach (array_keys($_POST) as $key) {
            $s = trim(rcube_utils::get_input_value($key, rcube_utils::INPUT_POST, true));
            if (strlen($s) && preg_match('/^_search_([a-zA-Z0-9_-]+)$/', $key, $m)) {
                $search[] = $s;
                $fields[] = $m[1];
            }
        }

        if (empty($fields)) {
            // do nothing, show the form again
            return;
        }
    }
    // quick-search
    else {
        $search = trim(rcube_utils::get_input_value('_q', rcube_utils::INPUT_GET, true));
        $fields = explode(',', rcube_utils::get_input_value('_headers', rcube_utils::INPUT_GET));

        if (empty($fields)) {
            $fields = array_keys($SEARCH_MODS_DEFAULT);
        }
        else {
            $fields = array_filter($fields);
        }

        // update search_mods setting
        $old_mods = $RCMAIL->config->get('addressbook_search_mods');
        $search_mods = array_fill_keys($fields, 1);
        if ($old_mods != $search_mods) {
            $RCMAIL->user->save_prefs(array('addressbook_search_mods' => $search_mods));
        }

        if (in_array('*', $fields)) {
            $fields = '*';
        }
    }

    // Values matching mode
    $mode = (int) $RCMAIL->config->get('addressbook_search_mode');
    $mode |= rcube_addressbook::SEARCH_GROUPS;

    // get sources list
    $sources    = $RCMAIL->get_address_sources();
    $search_set = array();
    $records    = array();
    $sort_col   = $RCMAIL->config->get('addressbook_sort_col', 'name');
    $afields = $RCMAIL->config->get('contactlist_fields');

    foreach ($sources as $s) {
        $source = $RCMAIL->get_address_book($s['id']);

        // check if search fields are supported....
        if (is_array($fields)) {
            $cols = $source->coltypes[0] ? array_flip($source->coltypes) : $source->coltypes;
            $supported = 0;

            foreach ($fields as $f) {
                if (array_key_exists($f, $cols)) {
                    $supported ++;
                }
            }

            // in advanced search we require all fields (AND operator)
            // in quick search we require at least one field (OR operator)
            if (($adv && $supported < count($fields)) || (!$adv && !$supported)) {
                continue;
            }
        }

        // reset page
        $source->set_page(1);
        $source->set_pagesize(9999);

        // get contacts count
        $result = $source->search($fields, $search, $mode, false);

        if (!$result->count) {
            continue;
        }

        // get records
        $result = $source->list_records($afields);

        while ($row = $result->next()) {
            $row['sourceid'] = $s['id'];
            $key = rcube_addressbook::compose_contact_key($row, $sort_col);
            $records[$key] = $row;
        }

        unset($result);
        $search_set[$s['id']] = $source->get_search_set();
    }

    // sort the records
    ksort($records, SORT_LOCALE_STRING);

    // create resultset object
    $count  = count($records);
    $result = new rcube_result_set($count);

    // cut first-page records
    if ($PAGE_SIZE < $count) {
        $records = array_slice($records, 0, $PAGE_SIZE);
    }

    $result->records = array_values($records);

    // search request ID
    $search_request = md5('addr'
        .(is_array($fields) ? implode($fields, ',') : $fields)
        .(is_array($search) ? implode($search, ',') : $search));

    // save search settings in session
    $_SESSION['search'][$search_request] = $search_set;
    $_SESSION['search_params'] = array('id' => $search_request, 'data' => array($fields, $search));
    $_SESSION['page'] = 1;

    if ($adv)
        $OUTPUT->command('list_contacts_clear');

    if ($result->count > 0) {
        // create javascript list
        rcmail_js_contacts_list($result);
        $OUTPUT->show_message('contactsearchsuccessful', 'confirmation', array('nr' => $result->count));
    }
    else {
        $OUTPUT->show_message('nocontactsfound', 'notice');
    }

    // update message count display
    $OUTPUT->set_env('search_request', $search_request);
    $OUTPUT->set_env('pagecount', ceil($result->count / $PAGE_SIZE));
    $OUTPUT->command('set_rowcount', rcmail_get_rowcount_text($result));
    // Re-set current source
    $OUTPUT->set_env('search_id', $sid);
    $OUTPUT->set_env('source', '');
    $OUTPUT->set_env('group', '');
    // Re-set list header
    $OUTPUT->command('set_group_prop', null);

    if ($adv) {
        $OUTPUT->command('parent.set_env', array(
                'search_request' => $search_request,
                'pagecount'      => ceil($result->count / $PAGE_SIZE),
                'search_id'      => $sid,
                'source'         => '',
                'group'          => '',
        ));
    }

    if (!$sid) {
        // unselect currently selected directory/group
        $OUTPUT->command('unselect_directory');
        // enable "Save search" command
        $OUTPUT->command('enable_command', 'search-create', true);
    }
    $OUTPUT->command('update_group_commands');

    // send response
    $OUTPUT->send($adv ? 'iframe' : null);
}

function rcmail_contact_search_form($attrib)
{
    global $RCMAIL, $CONTACT_COLTYPES;

    $i_size = $attrib['size'] ?: 30;

    $form = array(
        'main' => array(
            'name'    => $RCMAIL->gettext('properties'),
            'content' => array(
            ),
        ),
        'personal' => array(
            'name'    => $RCMAIL->gettext('personalinfo'),
            'content' => array(
            ),
        ),
        'other' => array(
            'name'    => $RCMAIL->gettext('other'),
            'content' => array(
            ),
        ),
    );

    // get supported coltypes from all address sources
    $sources  = $RCMAIL->get_address_sources();
    $coltypes = array();

    foreach ($sources as $s) {
        $CONTACTS = $RCMAIL->get_address_book($s['id']);

        if (is_array($CONTACTS->coltypes)) {
            $contact_cols = $CONTACTS->coltypes[0] ? array_flip($CONTACTS->coltypes) : $CONTACTS->coltypes;
            $coltypes = array_merge($coltypes, $contact_cols);
        }
    }

    // merge supported coltypes with $CONTACT_COLTYPES
    foreach ($coltypes as $col => $colprop) {
        $coltypes[$col] = $CONTACT_COLTYPES[$col] ? array_merge($CONTACT_COLTYPES[$col], (array)$colprop) : (array)$colprop;
    }

    // build form fields list
    foreach ($coltypes as $col => $colprop)
    {
        if ($colprop['type'] != 'image' && !$colprop['nosearch'])
        {
            $ftype    = $colprop['type'] == 'select' ? 'select' : 'text';
            $label    = isset($colprop['label']) ? $colprop['label'] : $RCMAIL->gettext($col);
            $category = $colprop['category'] ?: 'other';

            // load jquery UI datepicker for date fields 
            if ($colprop['type'] == 'date')
                $colprop['class'] .= ($colprop['class'] ? ' ' : '') . 'datepicker';
            else if ($ftype == 'text')
                $colprop['size'] = $i_size;


            $content  = html::div('row', html::div('contactfieldlabel label', rcube::Q($label))
                . html::div('contactfieldcontent', rcube_output::get_edit_field('search_'.$col, '', $colprop, $ftype)));

            $form[$category]['content'][] = $content;
        }
    }

    $hiddenfields = new html_hiddenfield();
    $hiddenfields->add(array('name' => '_adv', 'value' => 1));

    $out = $RCMAIL->output->request_form(array(
        'name' => 'form', 'method' => 'post',
        'task' => $RCMAIL->task, 'action' => 'search',
        'noclose' => true) + $attrib, $hiddenfields->show());

    $RCMAIL->output->add_gui_object('editform', $attrib['id']);

    unset($attrib['name']);
    unset($attrib['id']);

    foreach ($form as $f) {
        if (!empty($f['content'])) {
            $content = html::div('contactfieldgroup', join("\n", $f['content']));

            $out .= html::tag('fieldset', $attrib,
                html::tag('legend', null, rcube::Q($f['name']))
                . $content) . "\n";
        }
    }

    return $out . '</form>';
}
