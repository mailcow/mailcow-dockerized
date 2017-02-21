<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/edit_prefs.inc                                 |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide functionality for user's settings & preferences             |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/


if (!$OUTPUT->ajax_call) {
    $OUTPUT->set_pagetitle($RCMAIL->gettext('preferences'));
}

$CURR_SECTION    = rcube_utils::get_input_value('_section', rcube_utils::INPUT_GPC);
list($SECTIONS,) = rcmail_user_prefs($CURR_SECTION);

// register UI objects
$OUTPUT->add_handlers(array(
    'userprefs'   => 'rcmail_user_prefs_form',
    'sectionname' => 'rcmail_prefs_section_name',
));

$OUTPUT->send('settingsedit');



function rcmail_user_prefs_form($attrib)
{
    global $RCMAIL, $CURR_SECTION, $SECTIONS;

    // add some labels to client
    $RCMAIL->output->add_label('nopagesizewarning');

    unset($attrib['form']);

    list($form_start, $form_end) = get_form_tags($attrib, 'save-prefs', null,
        array('name' => '_section', 'value' => $CURR_SECTION));

    $out = $form_start;

    if(!empty($SECTIONS[$CURR_SECTION]['header'])) {
        $out .= html::div(array('id' => 'preferences-header', 'class' =>'boxcontent'), $SECTIONS[$CURR_SECTION]['header']);
    }

    foreach ($SECTIONS[$CURR_SECTION]['blocks'] as $class => $block) {
        if (!empty($block['options'])) {
            $table = new html_table(array('cols' => 2));

            foreach ($block['options'] as $option) {
                if (isset($option['title'])) {
                    $table->add('title', $option['title']);
                    $table->add(null, $option['content']);
                }
                else {
                    $table->add(array('colspan' => 2), $option['content']);
                }
            }

            $out .= html::tag('fieldset', $class, html::tag('legend', null, $block['name']) . $table->show($attrib));
        }
        else if (!empty($block['content'])) {
            $out .= html::tag('fieldset', null, html::tag('legend', null, $block['name']) . $block['content']);
        }
    }

    return $out . $form_end;
}

function rcmail_prefs_section_name()
{
    global $SECTIONS, $CURR_SECTION;

    return $SECTIONS[$CURR_SECTION]['section'];
}
