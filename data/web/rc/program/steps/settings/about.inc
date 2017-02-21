<?php

/**
 +-----------------------------------------------------------------------+
 | program/steps/settings/about.inc                                      |
 |                                                                       |
 | This file is part of the Roundcube Webmail client                     |
 | Copyright (C) 2005-2013, The Roundcube Dev Team                       |
 | Copyright (C) 2011-2013, Kolab Systems AG                             |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Display license information about program and enabled plugins       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/


$OUTPUT->set_pagetitle($RCMAIL->gettext('about'));

$OUTPUT->add_handler('supportlink', 'rcmail_supportlink');
$OUTPUT->add_handler('pluginlist', 'rcmail_plugins_list');

$OUTPUT->send('about');



function rcmail_supportlink($attrib)
{
    global $RCMAIL;

    if ($url = $RCMAIL->config->get('support_url')) {
        $label = $attrib['label'] ?: 'support';
        $attrib['href'] = $url;

        return html::a($attrib, $RCMAIL->gettext($label));
    }
}

function rcmail_plugins_list($attrib)
{
    global $RCMAIL;

    if (!$attrib['id']) {
        $attrib['id'] = 'rcmpluginlist';
    }

    $plugins     = array_filter($RCMAIL->plugins->active_plugins);
    $plugin_info = array();

    foreach ($plugins as $name) {
        if ($info = $RCMAIL->plugins->get_info($name)) {
            $plugin_info[$name] = $info;
        }
    }

    // load info from required plugins, too
    foreach ($plugin_info as $name => $info) {
        if (is_array($info['require']) && !empty($info['require'])) {
            foreach ($info['require'] as $req_name) {
                if (!isset($plugin_info[$req_name]) && ($req_info = $RCMAIL->plugins->get_info($req_name))) {
                    $plugin_info[$req_name] = $req_info;
                }
            }
        }
    }

    if (empty($plugin_info)) {
        return '';
    }

    ksort($plugin_info, SORT_LOCALE_STRING);

    $table = new html_table($attrib);

    // add table header
    $table->add_header('name', $RCMAIL->gettext('plugin'));
    $table->add_header('version', $RCMAIL->gettext('version'));
    $table->add_header('license', $RCMAIL->gettext('license'));
    $table->add_header('source', $RCMAIL->gettext('source'));

    foreach ($plugin_info as $name => $data) {
        $uri = $data['src_uri'] ?: $data['uri'];
        if ($uri && stripos($uri, 'http') !== 0) {
            $uri = 'http://' . $uri;
        }

        $table->add_row();
        $table->add('name', rcube::Q($data['name'] ?: $name));
        $table->add('version', rcube::Q($data['version']));
        $table->add('license', $data['license_uri'] ? html::a(array('target' => '_blank', href=> rcube::Q($data['license_uri'])),
            rcube::Q($data['license'])) : $data['license']);
        $table->add('source', $uri ? html::a(array('target' => '_blank', href=> rcube::Q($uri)),
            rcube::Q($RCMAIL->gettext('download'))) : '');
    }

    return $table->show();
}
