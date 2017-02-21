<?php

/**
 * Mark as Junk
 *
 * Sample plugin that adds a new button to the mailbox toolbar
 * to mark the selected messages as Junk and move them to the Junk folder
 *
 * @license GNU GPLv3+
 * @author Thomas Bruederli
 */
class markasjunk extends rcube_plugin
{
    public $task = 'mail';

    function init()
    {
        $rcmail = rcmail::get_instance();

        $this->register_action('plugin.markasjunk', array($this, 'request_action'));
        $this->add_hook('storage_init', array($this, 'storage_init'));

        if ($rcmail->action == '' || $rcmail->action == 'show') {
            $skin_path = $this->local_skin_path();

            $this->add_texts('localization', true);
            $this->include_script('markasjunk.js');

            if (is_file($this->home . "/$skin_path/markasjunk.css")) {
                $this->include_stylesheet("$skin_path/markasjunk.css");
            }

            $this->add_button(array(
                    'type'     => 'link',
                    'label'    => 'buttontext',
                    'command'  => 'plugin.markasjunk',
                    'class'    => 'button buttonPas junk disabled',
                    'classact' => 'button junk',
                    'title'    => 'buttontitle',
                    'domain'   => 'markasjunk'
                ),'toolbar');
        }
    }

    function storage_init($args)
    {
        $flags = array(
            'JUNK'    => 'Junk',
            'NONJUNK' => 'NonJunk',
        );

        // register message flags
        $args['message_flags'] = array_merge((array)$args['message_flags'], $flags);

        return $args;
    }

    function request_action()
    {
        $this->add_texts('localization');

        $rcmail  = rcmail::get_instance();
        $storage = $rcmail->get_storage();

        foreach (rcmail::get_uids() as $mbox => $uids) {
            $storage->unset_flag($uids, 'NONJUNK', $mbox);
            $storage->set_flag($uids, 'JUNK', $mbox);
        }

        if (($junk_mbox = $rcmail->config->get('junk_mbox'))) {
            $rcmail->output->command('move_messages', $junk_mbox);
        }

        $rcmail->output->command('display_message', $this->gettext('reportedasjunk'), 'confirmation');
        $rcmail->output->send();
    }
}
