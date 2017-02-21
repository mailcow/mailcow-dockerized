<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/addressbook/func.inc                                    |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide addressbook functionality and GUI objects                   |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

$SEARCH_MODS_DEFAULT = array('name'=>1, 'firstname'=>1, 'surname'=>1, 'email'=>1, '*'=>1);

// general definition of contact coltypes
$CONTACT_COLTYPES = array(
    'name'         => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('name'), 'category' => 'main'),
    'firstname'    => array('type' => 'text', 'size' => 19, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('firstname'), 'category' => 'main'),
    'surname'      => array('type' => 'text', 'size' => 19, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('surname'), 'category' => 'main'),
    'email'        => array('type' => 'text', 'size' => 40, 'maxlength' => 254, 'label' => $RCMAIL->gettext('email'), 'subtypes' => array('home','work','other'), 'category' => 'main'),
    'middlename'   => array('type' => 'text', 'size' => 19, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('middlename'), 'category' => 'main'),
    'prefix'       => array('type' => 'text', 'size' => 8,  'maxlength' => 20, 'limit' => 1, 'label' => $RCMAIL->gettext('nameprefix'), 'category' => 'main'),
    'suffix'       => array('type' => 'text', 'size' => 8,  'maxlength' => 20, 'limit' => 1, 'label' => $RCMAIL->gettext('namesuffix'), 'category' => 'main'),
    'nickname'     => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('nickname'), 'category' => 'main'),
    'jobtitle'     => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('jobtitle'), 'category' => 'main'),
    'organization' => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('organization'), 'category' => 'main'),
    'department'   => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('department'), 'category' => 'main'),
    'gender'       => array('type' => 'select', 'limit' => 1, 'label' => $RCMAIL->gettext('gender'), 'options' => array('male' => $RCMAIL->gettext('male'), 'female' => $RCMAIL->gettext('female')), 'category' => 'personal'),
    'maidenname'   => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('maidenname'), 'category' => 'personal'),
    'phone'        => array('type' => 'text', 'size' => 40, 'maxlength' => 20, 'label' => $RCMAIL->gettext('phone'), 'subtypes' => array('home','home2','work','work2','mobile','main','homefax','workfax','car','pager','video','assistant','other'), 'category' => 'main'),
    'address'      => array('type' => 'composite', 'label' => $RCMAIL->gettext('address'), 'subtypes' => array('home','work','other'), 'childs' => array(
        'street'     => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'label' => $RCMAIL->gettext('street'), 'category' => 'main'),
        'locality'   => array('type' => 'text', 'size' => 28, 'maxlength' => 50, 'label' => $RCMAIL->gettext('locality'), 'category' => 'main'),
        'zipcode'    => array('type' => 'text', 'size' => 8,  'maxlength' => 15, 'label' => $RCMAIL->gettext('zipcode'), 'category' => 'main'),
        'region'     => array('type' => 'text', 'size' => 12, 'maxlength' => 50, 'label' => $RCMAIL->gettext('region'), 'category' => 'main'),
        'country'    => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'label' => $RCMAIL->gettext('country'), 'category' => 'main'),
        ), 'category' => 'main'),
    'birthday'     => array('type' => 'date', 'size' => 12, 'maxlength' => 16, 'label' => $RCMAIL->gettext('birthday'), 'limit' => 1, 'render_func' => 'rcmail_format_date_col', 'category' => 'personal'),
    'anniversary'  => array('type' => 'date', 'size' => 12, 'maxlength' => 16, 'label' => $RCMAIL->gettext('anniversary'), 'limit' => 1, 'render_func' => 'rcmail_format_date_col', 'category' => 'personal'),
    'website'      => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'label' => $RCMAIL->gettext('website'), 'subtypes' => array('homepage','work','blog','profile','other'), 'category' => 'main'),
    'im'           => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'label' => $RCMAIL->gettext('instantmessenger'), 'subtypes' => array('aim','icq','msn','yahoo','jabber','skype','other'), 'category' => 'main'),
    'notes'        => array('type' => 'textarea', 'size' => 40, 'rows' => 15, 'maxlength' => 500, 'label' => $RCMAIL->gettext('notes'), 'limit' => 1),
    'photo'        => array('type' => 'image', 'limit' => 1, 'category' => 'main'),
    'assistant'    => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('assistant'), 'category' => 'personal'),
    'manager'      => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('manager'), 'category' => 'personal'),
    'spouse'       => array('type' => 'text', 'size' => 40, 'maxlength' => 50, 'limit' => 1, 'label' => $RCMAIL->gettext('spouse'), 'category' => 'personal'),
    // TODO: define fields for vcards like GEO, KEY
);

$PAGE_SIZE = $RCMAIL->config->get('addressbook_pagesize', $RCMAIL->config->get('pagesize', 50));

// Addressbook UI
if (!$RCMAIL->action && !$OUTPUT->ajax_call) {
    // add list of address sources to client env
    $js_list = $RCMAIL->get_address_sources();

    // count all/writeable sources
    $writeable = 0;
    $count = 0;
    foreach ($js_list as $sid => $s) {
        $count++;
        if (!$s['readonly']) {
            $writeable++;
        }
        // unset hidden sources
        if ($s['hidden']) {
            unset($js_list[$sid]);
        }
    }

    $search_mods = $RCMAIL->config->get('addressbook_search_mods', $SEARCH_MODS_DEFAULT);
    $OUTPUT->set_env('search_mods', $search_mods);
    $OUTPUT->set_env('address_sources', $js_list);
    $OUTPUT->set_env('writable_source', $writeable);

    $OUTPUT->set_pagetitle($RCMAIL->gettext('contacts'));
    $_SESSION['addressbooks_count'] = $count;
    $_SESSION['addressbooks_count_writeable'] = $writeable;

    // select address book
    $source = rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC);

    // use first directory by default
    if (!strlen($source) || !isset($js_list[$source])) {
        $source = $RCMAIL->config->get('default_addressbook');
        if (!strlen($source) || !isset($js_list[$source])) {
            $source = strval(key($js_list));
        }
    }

    $CONTACTS = rcmail_contact_source($source, true);
}

// remove undo information...
if ($undo = $_SESSION['contact_undo']) {
    // ...after timeout
    $undo_time = $RCMAIL->config->get('undo_timeout', 0);
    if ($undo['ts'] < time() - $undo_time)
        $RCMAIL->session->remove('contact_undo');
}

// register UI objects
$OUTPUT->add_handlers(array(
    'directorylist'       => 'rcmail_directory_list',
    'savedsearchlist'     => 'rcmail_savedsearch_list',
    'addresslist'         => 'rcmail_contacts_list',
    'addresslisttitle'    => 'rcmail_contacts_list_title',
    'addressframe'        => 'rcmail_contact_frame',
    'recordscountdisplay' => 'rcmail_rowcount_display',
    'searchform'          => array($OUTPUT, 'search_form')
));

// register action aliases
$RCMAIL->register_action_map(array(
    'add'              => 'edit.inc',
    'group-create'     => 'groups.inc',
    'group-rename'     => 'groups.inc',
    'group-delete'     => 'groups.inc',
    'group-addmembers' => 'groups.inc',
    'group-delmembers' => 'groups.inc',
    'search-create'    => 'search.inc',
    'search-delete'    => 'search.inc',
));



// instantiate a contacts object according to the given source
function rcmail_contact_source($source=null, $init_env=false, $writable=false)
{
    global $RCMAIL, $OUTPUT, $CONTACT_COLTYPES, $PAGE_SIZE;

    if (!strlen($source)) {
        $source = rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC);
    }

    // Get object
    $CONTACTS = $RCMAIL->get_address_book($source, $writable);
    $CONTACTS->set_pagesize($PAGE_SIZE);

    // set list properties and session vars
    if (!empty($_GET['_page']))
        $CONTACTS->set_page(($_SESSION['page'] = intval($_GET['_page'])));
    else
        $CONTACTS->set_page(isset($_SESSION['page']) ? $_SESSION['page'] : 1);

    if (!empty($_REQUEST['_gid'])) {
        $group = rcube_utils::get_input_value('_gid', rcube_utils::INPUT_GPC);
        $CONTACTS->set_group($group);
    }

    if (!$init_env) {
        return $CONTACTS;
    }

    $OUTPUT->set_env('readonly', $CONTACTS->readonly);
    $OUTPUT->set_env('source', (string) $source);
    $OUTPUT->set_env('group', $group);

    // reduce/extend $CONTACT_COLTYPES with specification from the current $CONTACT object
    if (is_array($CONTACTS->coltypes)) {
        // remove cols not listed by the backend class
        $contact_cols = $CONTACTS->coltypes[0] ? array_flip($CONTACTS->coltypes) : $CONTACTS->coltypes;
        $CONTACT_COLTYPES = array_intersect_key($CONTACT_COLTYPES, $contact_cols);
        // add associative coltypes definition
        if (!$CONTACTS->coltypes[0]) {
            foreach ($CONTACTS->coltypes as $col => $colprop) {
                if (is_array($colprop['childs'])) {
                    foreach ($colprop['childs'] as $childcol => $childprop)
                        $colprop['childs'][$childcol] = array_merge((array)$CONTACT_COLTYPES[$col]['childs'][$childcol], $childprop);
                }
                $CONTACT_COLTYPES[$col] = $CONTACT_COLTYPES[$col] ? array_merge($CONTACT_COLTYPES[$col], $colprop) : $colprop;
            }
        }
    }

    $OUTPUT->set_env('photocol', is_array($CONTACT_COLTYPES['photo']));

    return $CONTACTS;
}


function rcmail_set_sourcename($abook)
{
    global $OUTPUT, $RCMAIL;

    // get address book name (for display)
    if ($abook && $_SESSION['addressbooks_count'] > 1) {
        $name = $abook->get_name();
        if (!$name) {
            $name = $RCMAIL->gettext('personaladrbook');
        }
        $OUTPUT->set_env('sourcename', html_entity_decode($name, ENT_COMPAT, 'UTF-8'));
    }
}


function rcmail_directory_list($attrib)
{
    global $RCMAIL, $OUTPUT;

    if (!$attrib['id'])
        $attrib['id'] = 'rcmdirectorylist';

    $out = '';
    $jsdata = array();

    $line_templ = html::tag('li', array(
        'id' => 'rcmli%s', 'class' => '%s', 'noclose' => true),
        html::a(array('href' => '%s',
            'rel' => '%s',
            'onclick' => "return ".rcmail_output::JS_OBJECT_NAME.".command('list','%s',this)"), '%s'));

    $sources = (array) $OUTPUT->get_env('address_sources');
    reset($sources);

    // currently selected source
    $current = rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC);

    foreach ($sources as $j => $source) {
        $id = strval(strlen($source['id']) ? $source['id'] : $j);
        $js_id = rcube::JQ($id);

        // set class name(s)
        $class_name = 'addressbook';
        if ($current === $id)
            $class_name .= ' selected';
        if ($source['readonly'])
            $class_name .= ' readonly';
        if ($source['class_name'])
            $class_name .= ' ' . $source['class_name'];

        $name = $source['name'] ?: $id;
        $out .= sprintf($line_templ,
            rcube_utils::html_identifier($id, true),
            $class_name,
            rcube::Q($RCMAIL->url(array('_source' => $id))),
            $source['id'],
            $js_id, $name);

        $groupdata = array('out' => $out, 'jsdata' => $jsdata, 'source' => $id);
        if ($source['groups'])
            $groupdata = rcmail_contact_groups($groupdata);
        $jsdata = $groupdata['jsdata'];
        $out = $groupdata['out'];
        $out .= '</li>';
    }

    $OUTPUT->set_env('contactgroups', $jsdata);
    $OUTPUT->set_env('collapsed_abooks', (string)$RCMAIL->config->get('collapsed_abooks',''));
    $OUTPUT->add_gui_object('folderlist', $attrib['id']);
    $OUTPUT->include_script('treelist.js');

    // add some labels to client
    $OUTPUT->add_label('deletegroupconfirm', 'groupdeleting', 'addingmember', 'removingmember',
        'newgroup', 'grouprename', 'searchsave', 'namex', 'save'
    );

    return html::tag('ul', $attrib, $out, html::$common_attrib);
}


function rcmail_savedsearch_list($attrib)
{
    global $RCMAIL, $OUTPUT;

    if (!$attrib['id'])
        $attrib['id'] = 'rcmsavedsearchlist';

    $out = '';
    $line_templ = html::tag('li', array(
        'id' => 'rcmli%s', 'class' => '%s'),
        html::a(array('href' => '#', 'rel' => 'S%s',
            'onclick' => "return ".rcmail_output::JS_OBJECT_NAME.".command('listsearch', '%s', this)"), '%s'));

    // Saved searches
    $sources = $RCMAIL->user->list_searches(rcube_user::SEARCH_ADDRESSBOOK);
    foreach ($sources as $source) {
        $id    = $source['id'];
        $js_id = rcube::JQ($id);

        // set class name(s)
        $classes = array('contactsearch');
        if (!empty($source['class_name']))
            $classes[] = $source['class_name'];

        $out .= sprintf($line_templ,
            rcube_utils::html_identifier('S'.$id, true),
            join(' ', $classes),
            $id,
            $js_id, rcube::Q($source['name'] ?: $id)
        );
    }

    $OUTPUT->add_gui_object('savedsearchlist', $attrib['id']);

    return html::tag('ul', $attrib, $out, html::$common_attrib);
}


function rcmail_contact_groups($args)
{
    global $RCMAIL;

    $groups_html = '';
    $groups = $RCMAIL->get_address_book($args['source'])->list_groups();

    if (!empty($groups)) {
        $line_templ = html::tag('li', array(
            'id' => 'rcmli%s', 'class' => 'contactgroup'),
            html::a(array('href' => '#',
                'rel' => '%s:%s',
                'onclick' => "return ".rcmail_output::JS_OBJECT_NAME.".command('listgroup',{'source':'%s','id':'%s'},this)"), '%s'));

        // append collapse/expand toggle and open a new <ul>
        $is_collapsed = strpos($RCMAIL->config->get('collapsed_abooks',''), '&'.rawurlencode($args['source']).'&') !== false;
        $args['out'] .= html::div('treetoggle ' . ($is_collapsed ? 'collapsed' : 'expanded'), '&nbsp;');

        foreach ($groups as $group) {
            $groups_html .= sprintf($line_templ,
                rcube_utils::html_identifier('G' . $args['source'] . $group['ID'], true),
                $args['source'], $group['ID'],
                $args['source'], $group['ID'], rcube::Q($group['name'])
            );
            $args['jsdata']['G'.$args['source'].$group['ID']] = array(
                'source' => $args['source'], 'id' => $group['ID'],
                'name' => $group['name'], 'type' => 'group');
        }
    }

    $args['out'] .= html::tag('ul',
      array('class' => 'groups', 'style' => ($is_collapsed || empty($groups) ? "display:none;" : null)),
      $groups_html);

    return $args;
}


// return the contacts list as HTML table
function rcmail_contacts_list($attrib)
{
    global $RCMAIL, $CONTACTS, $OUTPUT;

    // define list of cols to be displayed
    $a_show_cols = array('name','action');

    // add id to message list table if not specified
    if (!strlen($attrib['id']))
        $attrib['id'] = 'rcmAddressList';

    // create XHTML table
    $out = $RCMAIL->table_output($attrib, array(), $a_show_cols, $CONTACTS->primary_key);

    // set client env
    $OUTPUT->add_gui_object('contactslist', $attrib['id']);
    $OUTPUT->set_env('current_page', (int)$CONTACTS->list_page);
    $OUTPUT->include_script('list.js');

    // add some labels to client
    $OUTPUT->add_label('deletecontactconfirm', 'copyingcontact', 'movingcontact', 'contactdeleting');

    return $out;
}


function rcmail_js_contacts_list($result, $prefix='')
{
    global $OUTPUT, $RCMAIL;

    if (empty($result) || $result->count == 0) {
        return;
    }

    // define list of cols to be displayed
    $a_show_cols = array('name','action');

    while ($row = $result->next()) {
        $emails       = rcube_addressbook::get_col_values('email', $row, true);
        $row['CID']   = $row['ID'];
        $row['email'] = reset($emails);

        $source_id  = $OUTPUT->get_env('source');
        $a_row_cols = array();
        $classes    = array($row['_type'] ?: 'person');

        // build contact ID with source ID
        if (isset($row['sourceid'])) {
            $row['ID'] = $row['ID'].'-'.$row['sourceid'];
            $source_id = $row['sourceid'];
        }

        // format each col
        foreach ($a_show_cols as $col) {
            $val = '';
            switch ($col) {
                case 'name':
                    $val = rcube::Q(rcube_addressbook::compose_list_name($row));
                    break;

                case 'action':
                    if ($row['_type'] == 'group') {
                        $val = html::a(array(
                            'href' => '#list',
                            'rel' => $row['ID'],
                            'title' => $RCMAIL->gettext('listgroup'),
                            'onclick' => sprintf("return %s.command('pushgroup',{'source':'%s','id':'%s'},this,event)", rcmail_output::JS_OBJECT_NAME, $source_id, $row['CID']),
                        ), '&raquo;');
                    }
                    else
                        $val = '';
                    break;

                default:
                    $val = rcube::Q($row[$col]);
                    break;
            }

            $a_row_cols[$col] = $val;
        }

        if ($row['readonly'])
            $classes[] = 'readonly';

        $OUTPUT->command($prefix.'add_contact_row', $row['ID'], $a_row_cols, join(' ', $classes), array_intersect_key($row, array('ID'=>1,'readonly'=>1,'_type'=>1,'email'=>1,'name'=>1)));
    }
}


function rcmail_contacts_list_title($attrib)
{
    global $OUTPUT, $RCMAIL;

    $attrib += array('label' => 'contacts', 'id' => 'rcmabooklisttitle', 'tag' => 'span');
    unset($attrib['name']);

    $OUTPUT->add_gui_object('addresslist_title', $attrib['id']);
    $OUTPUT->add_label('contacts','uponelevel');

    return html::tag($attrib['tag'], $attrib, $RCMAIL->gettext($attrib['label']), html::$common_attrib);
}


// similar function as /steps/settings/identities.inc::rcmail_identity_frame()
function rcmail_contact_frame($attrib)
{
    global $OUTPUT;

    if (!$attrib['id'])
        $attrib['id'] = 'rcmcontactframe';

    return $OUTPUT->frame($attrib, true);
}


function rcmail_rowcount_display($attrib)
{
    global $RCMAIL;

    if (!$attrib['id'])
        $attrib['id'] = 'rcmcountdisplay';

    $RCMAIL->output->add_gui_object('countdisplay', $attrib['id']);

    if ($attrib['label'])
      $_SESSION['contactcountdisplay'] = $attrib['label'];

    return html::span($attrib, $RCMAIL->gettext('loading'));
}


function rcmail_get_rowcount_text($result=null)
{
    global $RCMAIL, $CONTACTS, $PAGE_SIZE;

    // read nr of contacts
    if (!$result) {
        $result = $CONTACTS->get_result();
    }

    if ($result->count == 0)
        $out = $RCMAIL->gettext('nocontactsfound');
    else
        $out = $RCMAIL->gettext(array(
            'name'  => $_SESSION['contactcountdisplay'] ?: 'contactsfromto',
            'vars'  => array(
            'from'  => $result->first + 1,
            'to'    => min($result->count, $result->first + $PAGE_SIZE),
            'count' => $result->count)
        ));

    return $out;
}


function rcmail_get_type_label($type)
{
    global $RCMAIL;

    $label = 'type'.$type;
    if ($RCMAIL->text_exists($label, '*', $domain))
        return $RCMAIL->gettext($label, $domain);
    else if (preg_match('/\w+(\d+)$/', $label, $m)
            && ($label = preg_replace('/(\d+)$/', '', $label))
            && $RCMAIL->text_exists($label, '*', $domain))
        return $RCMAIL->gettext($label, $domain) . ' ' . $m[1];

    return ucfirst($type);
}


function rcmail_contact_form($form, $record, $attrib = null)
{
    global $RCMAIL;

    // group fields
    $head_fields = array(
        'names'        => array('prefix','firstname','middlename','surname','suffix'),
        'displayname'  => array('name'),
        'nickname'     => array('nickname'),
        'organization' => array('organization'),
        'department'   => array('department'),
        'jobtitle'     => array('jobtitle'),
    );

    // Allow plugins to modify contact form content
    $plugin = $RCMAIL->plugins->exec_hook('contact_form', array(
        'form' => $form, 'record' => $record, 'head_fields' => $head_fields));

    $form        = $plugin['form'];
    $record      = $plugin['record'];
    $head_fields = $plugin['head_fields'];
    $edit_mode   = $RCMAIL->action != 'show' && $RCMAIL->action != 'print';
    $del_button  = $attrib['deleteicon'] ? html::img(array('src' => $RCMAIL->output->get_skin_file($attrib['deleteicon']), 'alt' => $RCMAIL->gettext('delete'))) : $RCMAIL->gettext('delete');
    $out         = '';

    unset($attrib['deleteicon']);

    // get default coltypes
    $coltypes = $GLOBALS['CONTACT_COLTYPES'];
    $coltype_labels = array();

    foreach ($coltypes as $col => $prop) {
        if ($prop['subtypes']) {
            $subtype_names = array_map('rcmail_get_type_label', $prop['subtypes']);
            $select_subtype = new html_select(array('name' => '_subtype_'.$col.'[]', 'class' => 'contactselectsubtype', 'title' => $prop['label'] . ' ' . $RCMAIL->gettext('type')));
            $select_subtype->add($subtype_names, $prop['subtypes']);
            $coltypes[$col]['subtypes_select'] = $select_subtype->show();
        }
        if ($prop['childs']) {
            foreach ($prop['childs'] as $childcol => $cp)
                $coltype_labels[$childcol] = array('label' => $cp['label']);
        }
    }

    foreach ($form as $section => $fieldset) {
        // skip empty sections
        if (empty($fieldset['content'])) {
            continue;
        }

        $select_add = new html_select(array('class' => 'addfieldmenu', 'rel' => $section));
        $select_add->add($RCMAIL->gettext('addfield'), '');

        // render head section with name fields (not a regular list of rows)
        if ($section == 'head') {
            $content = '';

            // unset display name if it is composed from name parts
            if ($record['name'] == rcube_addressbook::compose_display_name(array('name' => '') + (array)$record)) {
                unset($record['name']);
            }

            foreach ($head_fields as $blockname => $colnames) {
                $fields = '';
                foreach ($colnames as $col) {
                    // skip cols unknown to the backend
                    if (!$coltypes[$col])
                        continue;

                    // skip cols not listed in the form definition
                    if (is_array($fieldset['content']) && !in_array($col, array_keys($fieldset['content']))) {
                        continue;
                    }

                    // only string values are expected here
                    if (is_array($record[$col]))
                        $record[$col] = join(' ', $record[$col]);

                    if (!$edit_mode) {
                        if (!empty($record[$col]))
                            $fields .= html::span('namefield ' . $col, rcube::Q($record[$col])) . " ";
                    }
                    else {
                        $colprop = (array)$fieldset['content'][$col] + (array)$coltypes[$col];
                        $colprop['id'] = 'ff_'.$col;
                        if (empty($record[$col]) && !$colprop['visible']) {
                            $colprop['style'] = 'display:none';
                            $select_add->add($colprop['label'], $col);
                        }
                        $fields .= rcube_output::get_edit_field($col, $record[$col], $colprop, $colprop['type']);
                    }
                }
                $content .= html::div($blockname, $fields);
            }

            if ($edit_mode)
                $content .= html::p('addfield', $select_add->show(null));

            $out .= html::tag('fieldset', $attrib, (!empty($fieldset['name']) ? html::tag('legend', null, rcube::Q($fieldset['name'])) : '') . $content) ."\n";
            continue;
        }

        $content = '';
        if (is_array($fieldset['content'])) {
            foreach ($fieldset['content'] as $col => $colprop) {
                // remove subtype part of col name
                list($field, $subtype) = explode(':', $col);
                if (!$subtype) $subtype = 'home';
                $fullkey = $col.':'.$subtype;

                // skip cols unknown to the backend
                if (!$coltypes[$field] && empty($colprop['value'])) {
                    continue;
                }

                // merge colprop with global coltype configuration
                if ($coltypes[$field]) {
                    $colprop += $coltypes[$field];
                }

                $label = isset($colprop['label']) ? $colprop['label'] : $RCMAIL->gettext($col);

                // prepare subtype selector in edit mode
                if ($edit_mode && is_array($colprop['subtypes'])) {
                    $subtype_names = array_map('rcmail_get_type_label', $colprop['subtypes']);
                    $select_subtype = new html_select(array('name' => '_subtype_'.$col.'[]', 'class' => 'contactselectsubtype', 'title' => $colprop['label'] . ' ' . $RCMAIL->gettext('type')));
                    $select_subtype->add($subtype_names, $colprop['subtypes']);
                }
                else {
                    $select_subtype = null;
                }

                if (!empty($colprop['value'])) {
                    $values = (array)$colprop['value'];
                }
                else {
                    // iterate over possible subtypes and collect values with their subtype
                    if (is_array($colprop['subtypes'])) {
                        $values = $subtypes = array();
                        foreach (rcube_addressbook::get_col_values($field, $record) as $st => $vals) {
                            foreach((array)$vals as $value) {
                                $i = count($values);
                                $subtypes[$i] = $st;
                                $values[$i] = $value;
                            }
                            // TODO: add $st to $select_subtype if missing ?
                        }
                    }
                    else {
                        $values   = $record[$fullkey] ?: $record[$field];
                        $subtypes = null;
                    }
                }

                // hack: create empty values array to force this field to be displayed
                if (empty($values) && $colprop['visible'])
                    $values[] = '';

                if (!is_array($values)) {
                    // $values can be an object, don't use (array)$values syntax
                    $values = !empty($values) ? array($values) : array();
                }

                $rows = '';
                foreach ($values as $i => $val) {
                    if ($subtypes[$i])
                        $subtype = $subtypes[$i];

                    $colprop['id'] = 'ff_' . $col . intval($coltypes[$field]['count']);

                    // render composite field
                    if ($colprop['type'] == 'composite') {
                        $composite = array(); $j = 0;
                        $template = $RCMAIL->config->get($col . '_template', '{'.join('} {', array_keys($colprop['childs'])).'}');
                        foreach ($colprop['childs'] as $childcol => $cp) {
                            if (!empty($val) && is_array($val)) {
                                $childvalue = $val[$childcol] ?: $val[$j];
                            }
                            else {
                                $childvalue = '';
                            }

                            if ($edit_mode) {
                                if ($colprop['subtypes'] || $colprop['limit'] != 1) $cp['array'] = true;
                                $composite['{'.$childcol.'}'] = rcube_output::get_edit_field($childcol, $childvalue, $cp, $cp['type']) . " ";
                            }
                            else {
                                $childval = $cp['render_func'] ? call_user_func($cp['render_func'], $childvalue, $childcol) : rcube::Q($childvalue);
                                $composite['{'.$childcol.'}'] = html::span('data ' . $childcol, $childval) . " ";
                            }
                            $j++;
                        }

                        $coltypes[$field] += (array)$colprop;
                        $coltypes[$field]['count']++;
                        $val = preg_replace('/\{\w+\}/', '', strtr($template, $composite));
                    }
                    else if ($edit_mode) {
                        // call callback to render/format value
                        if ($colprop['render_func'])
                            $val = call_user_func($colprop['render_func'], $val, $col);

                        $coltypes[$field] = (array)$colprop + $coltypes[$field];

                        if ($colprop['subtypes'] || $colprop['limit'] != 1)
                            $colprop['array'] = true;

                        // load jquery UI datepicker for date fields
                        if ($colprop['type'] == 'date') {
                            $colprop['class'] .= ($colprop['class'] ? ' ' : '') . 'datepicker';
                            if (!$colprop['render_func'])
                                $val = rcmail_format_date_col($val);
                        }

                        $val = rcube_output::get_edit_field($col, $val, $colprop, $colprop['type']);
                        $coltypes[$field]['count']++;
                    }
                    else if ($colprop['render_func'])
                        $val = call_user_func($colprop['render_func'], $val, $col);
                    else if (is_array($colprop['options']) && isset($colprop['options'][$val]))
                        $val = $colprop['options'][$val];
                    else
                        $val = rcube::Q($val);

                    // use subtype as label
                    if ($colprop['subtypes'])
                        $label = rcmail_get_type_label($subtype);

                    // add delete button/link
                    if ($edit_mode && !($colprop['visible'] && $colprop['limit'] == 1))
                        $val .= html::a(array('href' => '#del', 'class' => 'contactfieldbutton deletebutton', 'title' => $RCMAIL->gettext('delete'), 'rel' => $col), $del_button);

                    // display row with label
                    if ($label) {
                        if ($RCMAIL->action == 'print') {
                            $_label = rcube::Q($colprop['label'] . ($label != $colprop['label'] ? ' (' . $label . ')' : ''));
                        }
                        else {
                            $_label = $select_subtype ? $select_subtype->show($subtype) : html::label($colprop['id'], rcube::Q($label));
                        }

                        $rows .= html::div('row',
                            html::div('contactfieldlabel label', $_label) .
                            html::div('contactfieldcontent '.$colprop['type'], $val));
                    }
                    // row without label
                    else {
                        $rows .= html::div('row', html::div('contactfield', $val));
                    }
                }

                // add option to the add-field menu
                if (!$colprop['limit'] || $coltypes[$field]['count'] < $colprop['limit']) {
                    $select_add->add($colprop['label'], $col);
                    $select_add->_count++;
                }

                // wrap rows in fieldgroup container
                if ($rows) {
                    $c_class    = 'contactfieldgroup ' . ($colprop['subtypes'] ? 'contactfieldgroupmulti ' : '') . 'contactcontroller' . $col;
                    $with_label = $colprop['subtypes'] && $RCMAIL->action != 'print';
                    $content   .= html::tag(
                        'fieldset',
                        array('class' => $c_class, 'style' => ($rows ? null : 'display:none')),
                        ($with_label ? html::tag('legend', null, rcube::Q($colprop['label'])) : ' ') . $rows
                    );
                }
            }

            if (!$content && (!$edit_mode || !$select_add->_count))
                continue;

            // also render add-field selector
            if ($edit_mode)
                $content .= html::p('addfield', $select_add->show(null, array('style' => $select_add->_count ? null : 'display:none')));

            $content = html::div(array('id' => 'contactsection' . $section), $content);
        }
        else {
            $content = $fieldset['content'];
        }

        if ($content)
            $out .= html::tag('fieldset', null, html::tag('legend', null, rcube::Q($fieldset['name'])) . $content) ."\n";
    }

    if ($edit_mode) {
        $RCMAIL->output->set_env('coltypes', $coltypes + $coltype_labels);
        $RCMAIL->output->set_env('delbutton', $del_button);
        $RCMAIL->output->add_label('delete');
    }

    return $out;
}


function rcmail_contact_photo($attrib)
{
    global $SOURCE_ID, $CONTACTS, $CONTACT_COLTYPES, $RCMAIL;

    if ($result = $CONTACTS->get_result())
        $record = $result->first();

    $photo_img = $attrib['placeholder'] ? $RCMAIL->output->abs_url($attrib['placeholder'], true) : 'program/resources/blank.gif';
    if ($record['_type'] == 'group' && $attrib['placeholdergroup'])
        $photo_img = $RCMAIL->output->abs_url($attrib['placeholdergroup'], true);

    $RCMAIL->output->set_env('photo_placeholder', $RCMAIL->output->asset_url($photo_img));

    unset($attrib['placeholder']);

    $plugin = $RCMAIL->plugins->exec_hook('contact_photo', array('record' => $record, 'data' => $record['photo']));

    // check if we have photo data from contact form
    if ($GLOBALS['EDIT_RECORD']) {
        $rec = $GLOBALS['EDIT_RECORD'];
        if ($rec['photo'] == '-del-') {
            $record['photo'] = '';
        }
        else if ($_SESSION['contacts']['files'][$rec['photo']]) {
            $record['photo'] = $file_id = $rec['photo'];
        }
    }

    if ($plugin['url'])
        $photo_img = $plugin['url'];
    else if (preg_match('!^https?://!i', $record['photo']))
        $photo_img = $record['photo'];
    else if ($record['photo']) {
        $url = array('_action' => 'photo', '_cid' => $record['ID'], '_source' => $SOURCE_ID);
        if ($file_id) {
            $url['_photo'] = $ff_value = $file_id;
        }
        $photo_img = $RCMAIL->url($url);
    }
    else {
        $ff_value = '-del-'; // will disable delete-photo action
    }

    $content = html::div($attrib, html::img(array(
            'src'     => $photo_img,
            'alt'     => $RCMAIL->gettext('contactphoto'),
            'onerror' => 'this.src = rcmail.env.photo_placeholder',
    )));

    if ($CONTACT_COLTYPES['photo'] && ($RCMAIL->action == 'edit' || $RCMAIL->action == 'add')) {
        $RCMAIL->output->add_gui_object('contactphoto', $attrib['id']);
        $hidden = new html_hiddenfield(array('name' => '_photo', 'id' => 'ff_photo', 'value' => $ff_value));
        $content .= $hidden->show();
    }

    return $content;
}


function rcmail_format_date_col($val)
{
    global $RCMAIL;
    return $RCMAIL->format_date($val, $RCMAIL->config->get('date_format', 'Y-m-d'), false);
}

/**
 * Updates saved search after data changed
 */
function rcmail_search_update($return = false)
{
    global $RCMAIL;

    if (($search_request = $_REQUEST['_search']) && isset($_SESSION['search'][$search_request])) {
        $search   = (array)$_SESSION['search'][$search_request];
        $sort_col = $RCMAIL->config->get('addressbook_sort_col', 'name');
        $afields  = $return ? $RCMAIL->config->get('contactlist_fields') : array('name', 'email');
        $records  = array();

        foreach ($search as $s => $set) {
            $source = $RCMAIL->get_address_book($s);

            // reset page
            $source->set_page(1);
            $source->set_pagesize(9999);
            $source->set_search_set($set);

            // get records
            $result = $source->list_records($afields);

            if (!$result->count) {
                unset($search[$s]);
                continue;
            }

            if ($return) {
                while ($row = $result->next()) {
                    $row['sourceid'] = $s;
                    $key = rcube_addressbook::compose_contact_key($row, $sort_col);
                    $records[$key] = $row;
                }
                unset($result);
            }

            $search[$s] = $source->get_search_set();
        }

        $_SESSION['search'][$search_request] = $search;

        return $records;
    }

    return false;
}

/**
 * Returns contact ID(s) and source(s) from GET/POST data
 *
 * @return array List of contact IDs per-source
 */
function rcmail_get_cids($filter = null, $request_type = rcube_utils::INPUT_GPC)
{
    // contact ID (or comma-separated list of IDs) is provided in two
    // forms. If _source is an empty string then the ID is a string
    // containing contact ID and source name in form: <ID>-<SOURCE>

    $cid    = rcube_utils::get_input_value('_cid', $request_type);
    $source = (string) rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC);

    if (is_array($cid)) {
        return $cid;
    }

    if (!preg_match('/^[a-zA-Z0-9\+\/=_-]+(,[a-zA-Z0-9\+\/=_-]+)*$/', $cid)) {
        return array();
    }

    $cid        = explode(',', $cid);
    $got_source = strlen($source);
    $result     = array();

    // create per-source contact IDs array
    foreach ($cid as $id) {
        // extract source ID from contact ID (it's there in search mode)
        // see #1488959 and #1488862 for reference
        if (!$got_source) {
            if ($sep = strrpos($id, '-')) {
                $contact_id = substr($id, 0, $sep);
                $source_id  = (string) substr($id, $sep+1);
                if (strlen($source_id)) {
                    $result[$source_id][] = $contact_id;
                }
            }
        }
        else {
            if (substr($id, -($got_source+1)) === "-$source") {
                $id = substr($id, 0, -($got_source+1));
            }
            $result[$source][] = $id;
        }
    }

    return $filter !== null ? $result[$filter] : $result;
}
