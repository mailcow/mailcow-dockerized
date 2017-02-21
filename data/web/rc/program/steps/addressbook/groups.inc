<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/groups.inc                                  |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2010-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Create/delete/rename contact groups and assign/remove contacts      |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

$source   = rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC);
$CONTACTS = rcmail_contact_source($source);

if ($CONTACTS->readonly || !$CONTACTS->groups) {
    $OUTPUT->show_message('sourceisreadonly', 'warning');
    $OUTPUT->send();
}

if ($RCMAIL->action == 'group-addmembers') {
    if (($gid = rcube_utils::get_input_value('_gid', rcube_utils::INPUT_POST)) && ($ids = rcmail_get_cids($source))) {
        $plugin = $RCMAIL->plugins->exec_hook('group_addmembers', array(
            'group_id' => $gid,
            'ids'      => $ids,
            'source'   => $source,
        ));

        $CONTACTS->set_group($gid);
        $num2add = count($plugin['ids']);

        if (!$plugin['abort']) {
            if (($maxnum = $RCMAIL->config->get('max_group_members', 0)) && ($CONTACTS->count()->count + $num2add > $maxnum)) {
                $OUTPUT->show_message('maxgroupmembersreached', 'warning', array('max' => $maxnum));
                $OUTPUT->send();
            }

            $result = $CONTACTS->add_to_group($gid, $plugin['ids']);
        }
        else {
            $result = $plugin['result'];
        }

        if ($result)
            $OUTPUT->show_message('contactaddedtogroup');
        else if ($plugin['abort'] || $CONTACTS->get_error())
            $OUTPUT->show_message($plugin['message'] ?: 'errorsaving', 'error');
        else
            $OUTPUT->show_message($plugin['message'] ?: 'nogroupassignmentschanged');
    }
}
else if ($RCMAIL->action == 'group-delmembers') {
    if (($gid = rcube_utils::get_input_value('_gid', rcube_utils::INPUT_POST)) && ($ids = rcmail_get_cids($source))) {
        $plugin = $RCMAIL->plugins->exec_hook('group_delmembers', array(
            'group_id' => $gid,
            'ids'      => $ids,
            'source'   => $source,
        ));

        if (!$plugin['abort'])
            $result = $CONTACTS->remove_from_group($gid, $plugin['ids']);
        else
            $result = $plugin['result'];

        if ($result) {
            $OUTPUT->show_message('contactremovedfromgroup');
            $OUTPUT->command('remove_group_contacts',array('source' => $source, 'gid' => $gid));
        }
        else {
            $OUTPUT->show_message($plugin['message'] ?: 'errorsaving', 'error');
        }
    }
}
else if ($RCMAIL->action == 'group-create') {
    if ($name = trim(rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST, true))) {
        $plugin = $RCMAIL->plugins->exec_hook('group_create', array(
            'name'   => $name,
            'source' => $source,
        ));

        if (!$plugin['abort'])
            $created = $CONTACTS->create_group($plugin['name']);
        else
            $created = $plugin['result'];
    }

    if ($created && $OUTPUT->ajax_call) {
        $created['name'] = rcube::Q($created['name']);

        $OUTPUT->show_message('groupcreated', 'confirmation');
        $OUTPUT->command('insert_contact_group', array('source' => $source) + $created);
    }
    else if (!$created) {
        $OUTPUT->show_message($plugin['message'] ?: 'errorsaving', 'error');
    }
}
else if ($RCMAIL->action == 'group-rename') {
    if (($gid = rcube_utils::get_input_value('_gid', rcube_utils::INPUT_POST))
        && ($name = trim(rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST, true)))
    ) {
        $plugin = $RCMAIL->plugins->exec_hook('group_rename', array(
            'group_id' => $gid,
            'name'     => $name,
            'source'   => $source,
        ));

        if (!$plugin['abort'])
            $newname = $CONTACTS->rename_group($gid, $plugin['name'], $newgid);
        else
            $newname = $plugin['result'];
    }

    if ($newname && $OUTPUT->ajax_call) {
        $OUTPUT->show_message('grouprenamed', 'confirmation');
        $OUTPUT->command('update_contact_group', array(
            'source' => $source, 'id' => $gid, 'name' => rcube::Q($newname), 'newid' => $newgid));
    }
    else if (!$newname) {
        $OUTPUT->show_message($plugin['message'] ?: 'errorsaving', 'error');
    }
}
else if ($RCMAIL->action == 'group-delete') {
    if ($gid = rcube_utils::get_input_value('_gid', rcube_utils::INPUT_POST)) {
        $plugin = $RCMAIL->plugins->exec_hook('group_delete', array(
            'group_id' => $gid,
            'source'   => $source,
        ));

        if (!$plugin['abort'])
            $deleted = $CONTACTS->delete_group($gid);
        else
            $deleted = $plugin['result'];
    }

    if ($deleted) {
        $OUTPUT->show_message('groupdeleted', 'confirmation');
        $OUTPUT->command('remove_group_item', array('source' => $source, 'id' => $gid));
    }
    else {
        $OUTPUT->show_message($plugin['message'] ?: 'errorsaving', 'error');
    }
}

// send response
$OUTPUT->send();
