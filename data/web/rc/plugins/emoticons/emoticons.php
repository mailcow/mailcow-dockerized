<?php

/**
 * Emoticons
 *
 * Plugin to replace emoticons in plain text message body with real icons.
 * Also it enables emoticons in HTML compose editor. Both features are optional.
 *
 * @license GNU GPLv3+
 * @author Thomas Bruederli
 * @author Aleksander Machniak
 * @website http://roundcube.net
 */
class emoticons extends rcube_plugin
{
    public $task = 'mail|settings|utils';


    /**
     * Plugin initilization.
     */
    function init()
    {
        $rcube = rcube::get_instance();

        $this->add_hook('message_part_after', array($this, 'message_part_after'));
        $this->add_hook('message_outgoing_body', array($this, 'message_outgoing_body'));
        $this->add_hook('html2text', array($this, 'html2text'));
        $this->add_hook('html_editor', array($this, 'html_editor'));

        if ($rcube->task == 'settings') {
            $this->add_hook('preferences_list', array($this, 'preferences_list'));
            $this->add_hook('preferences_save', array($this, 'preferences_save'));
        }
    }

    /**
     * 'message_part_after' hook handler to replace common plain text emoticons
     * with emoticon images (<img>)
     */
    function message_part_after($args)
    {
        if ($args['type'] == 'plain') {
            $this->load_config();

            $rcube = rcube::get_instance();
            if (!$rcube->config->get('emoticons_display', false)) {
                return $args;
            }

            require_once __DIR__ . '/emoticons_engine.php';

            $args['body'] = emoticons_engine::text2icons($args['body']);
        }

        return $args;
    }

    /**
     * 'message_outgoing_body' hook handler to replace image emoticons from TinyMCE
     * editor with image attachments.
     */
    function message_outgoing_body($args)
    {
        if ($args['type'] == 'html') {
            $this->load_config();

            $rcube = rcube::get_instance();
            if (!$rcube->config->get('emoticons_compose', true)) {
                return $args;
            }

            require_once __DIR__ . '/emoticons_engine.php';

            // look for "emoticon" images from TinyMCE and change their src paths to
            // be file paths on the server instead of URL paths.
            $images = emoticons_engine::replace($args['body']);

            // add these images as attachments to the MIME message
            foreach ($images as $img_name => $img_file) {
                $args['message']->addHTMLImage($img_file, 'image/gif', '', true, $img_name);
            }
        }

        return $args;
    }

    /**
     * 'html2text' hook handler to replace image emoticons from TinyMCE
     * editor with plain text emoticons.
     *
     * This is executed on html2text action, i.e. when switching from HTML to text
     * in compose window (or similiar place). Also when generating alternative
     * text/plain part.
     */
    function html2text($args)
    {
        $rcube = rcube::get_instance();

        if ($rcube->action == 'html2text' || $rcube->action == 'send') {
            $this->load_config();

            if (!$rcube->config->get('emoticons_compose', true)) {
                return $args;
            }

            require_once __DIR__ . '/emoticons_engine.php';

            $args['body'] = emoticons_engine::icons2text($args['body']);
        }

        return $args;
    }

    /**
     * 'html_editor' hook handler, where we enable emoticons in TinyMCE
     */
    function html_editor($args)
    {
        $rcube = rcube::get_instance();

        $this->load_config();

        if ($rcube->config->get('emoticons_compose', true)) {
            $args['extra_plugins'][] = 'emoticons';
            $args['extra_buttons'][] = 'emoticons';
        }

        return $args;
    }

    /**
     * 'preferences_list' hook handler
     */
    function preferences_list($args)
    {
        $rcube         = rcube::get_instance();
        $dont_override = $rcube->config->get('dont_override', array());

        if ($args['section'] == 'mailview' && !in_array('emoticons_display', $dont_override)) {
            $this->load_config();
            $this->add_texts('localization');

            $field_id = 'emoticons_display';
            $checkbox = new html_checkbox(array('name' => '_' . $field_id, 'id' => $field_id, 'value' => 1));

            $args['blocks']['main']['options']['emoticons_display'] = array(
                    'title'   => $this->gettext('emoticonsdisplay'),
                    'content' => $checkbox->show(intval($rcube->config->get('emoticons_display', false)))
            );
        }
        else if ($args['section'] == 'compose' && !in_array('emoticons_compose', $dont_override)) {
            $this->load_config();
            $this->add_texts('localization');

            $field_id = 'emoticons_compose';
            $checkbox = new html_checkbox(array('name' => '_' . $field_id, 'id' => $field_id, 'value' => 1));

            $args['blocks']['main']['options']['emoticons_compose'] = array(
                    'title'   => $this->gettext('emoticonscompose'),
                    'content' => $checkbox->show(intval($rcube->config->get('emoticons_compose', true)))
            );
        }

        return $args;
    }

    /**
     * 'preferences_save' hook handler
     */
    function preferences_save($args)
    {
        $rcube         = rcube::get_instance();
        $dont_override = $rcube->config->get('dont_override', array());

        if ($args['section'] == 'mailview' && !in_array('emoticons_display', $dont_override)) {
            $args['prefs']['emoticons_display'] = rcube_utils::get_input_value('_emoticons_display', rcube_utils::INPUT_POST) ? true : false;
        }
        else if ($args['section'] == 'compose' && !in_array('emoticons_compose', $dont_override)) {
            $args['prefs']['emoticons_compose'] = rcube_utils::get_input_value('_emoticons_compose', rcube_utils::INPUT_POST) ? true : false;
        }

        return $args;
    }
}
