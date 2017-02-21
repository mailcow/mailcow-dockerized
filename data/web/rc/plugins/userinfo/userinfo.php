<?php

/**
 * Sample plugin that adds a new tab to the settings section
 * to display some information about the current user
 */
class userinfo extends rcube_plugin
{
    public $task    = 'settings';
    public $noajax  = true;
    public $noframe = true;

    function init()
    {
        $this->add_texts('localization/', array('userinfo'));
        $this->register_action('plugin.userinfo', array($this, 'infostep'));
        $this->include_script('userinfo.js');
    }

    function infostep()
    {
        $this->register_handler('plugin.body', array($this, 'infohtml'));
        rcmail::get_instance()->output->send('plugin');
    }

    function infohtml()
    {
        $rcmail   = rcmail::get_instance();
        $user     = $rcmail->user;
        $identity = $user->get_identity();

        $table = new html_table(array('cols' => 2, 'cellpadding' => 3));

        $table->add('title', 'ID');
        $table->add('', rcube::Q($user->ID));

        $table->add('title', rcube::Q($this->gettext('username')));
        $table->add('', rcube::Q($user->data['username']));

        $table->add('title', rcube::Q($this->gettext('server')));
        $table->add('', rcube::Q($user->data['mail_host']));

        $table->add('title', rcube::Q($this->gettext('created')));
        $table->add('', rcube::Q($user->data['created']));

        $table->add('title', rcube::Q($this->gettext('lastlogin')));
        $table->add('', rcube::Q($user->data['last_login']));

        $table->add('title', rcube::Q($this->gettext('defaultidentity')));
        $table->add('', rcube::Q($identity['name'] . ' <' . $identity['email'] . '>'));

        return html::tag('h4', null, rcube::Q('Infos for ' . $user->get_username())) . $table->show();
    }
}
