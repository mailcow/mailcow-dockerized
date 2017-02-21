<?php

/**
 * Present identities settings dialog to new users
 *
 * When a new user is created, this plugin checks the default identity
 * and sets a session flag in case it is incomplete. An overlay box will appear
 * on the screen until the user has reviewed/completed his identity.
 *
 * @license GNU GPLv3+
 * @author Thomas Bruederli
 * @author Aleksander Machniak
 */
class new_user_dialog extends rcube_plugin
{
    public $task    = '';
    public $noframe = true;


    function init()
    {
        $this->add_hook('identity_create', array($this, 'create_identity'));
        $this->register_action('plugin.newusersave', array($this, 'save_data'));

        // register additional hooks if session flag is set
        if ($_SESSION['plugin.newuserdialog']) {
            $this->add_hook('render_page', array($this, 'render_page'));
        }
    }

    /**
     * Check newly created identity at first login
     */
    function create_identity($p)
    {
        // set session flag when a new user was created and the default identity seems to be incomplete
        if ($p['login'] && !$p['complete']) {
            $_SESSION['plugin.newuserdialog'] = true;
        }
    }

    /**
     * Callback function when HTML page is rendered
     * We'll add an overlay box here.
     */
    function render_page($p)
    {
        if ($_SESSION['plugin.newuserdialog']) {
            $this->add_texts('localization');

            $rcmail           = rcmail::get_instance();
            $identity         = $rcmail->user->get_identity();
            $identities_level = intval($rcmail->config->get('identities_level', 0));

            // compose user-identity dialog
            $table = new html_table(array('cols' => 2));

            $table->add('title', $this->gettext('name'));
            $table->add(null, html::tag('input', array(
                    'type'     => 'text',
                    'name'     => '_name',
                    'value'    => $identity['name'],
                    'disabled' => $identities_level == 4
            )));

            $table->add('title', $this->gettext('email'));
            $table->add(null, html::tag('input', array(
                    'type'     => 'text',
                    'name'     => '_email',
                    'value'    => rcube_utils::idn_to_utf8($identity['email']),
                    'disabled' => in_array($identities_level, array(1, 3, 4))
            )));

            $table->add('title', $this->gettext('organization'));
            $table->add(null, html::tag('input', array(
                    'type'     => 'text',
                    'name'     => '_organization',
                    'value'    => $identity['organization'],
                    'disabled' => $identities_level == 4
            )));

            $table->add('title', $this->gettext('signature'));
            $table->add(null, html::tag('textarea', array(
                    'name' => '_signature',
                    'rows' => '3',
                ),
                $identity['signature']
            ));

            // add overlay input box to html page
            $rcmail->output->add_footer(html::tag('form', array(
                    'id' => 'newuserdialog',
                    'action' => $rcmail->url('plugin.newusersave'),
                    'method' => 'post'
                ),
                html::p('hint', rcube::Q($this->gettext('identitydialoghint'))) .
                    $table->show() .
                    html::p(array('class' => 'formbuttons'),
                        html::tag('input', array('type' => 'submit',
                            'class' => 'button mainaction', 'value' => $this->gettext('save'))))
            ));

            $title  = rcube::JQ($this->gettext('identitydialogtitle'));
            $script = "
$('#newuserdialog').show()
  .dialog({modal:true, resizable:false, closeOnEscape:false, width:450, title:'$title'})
  .submit(function() {
    var i, request = {}, form = $(this).serializeArray();
    for (i in form)
      request[form[i].name] = form[i].value;

      rcmail.http_post('plugin.newusersave', request, true);
      return false;
  });

$('input[name=_name]').focus();
rcube_webmail.prototype.new_user_dialog_close = function() { $('#newuserdialog').dialog('close'); }
";
            // disable keyboard events for messages list (#1486726)
            $rcmail->output->add_script($script, 'docready');

            $this->include_stylesheet('newuserdialog.css');
        }
    }

    /**
     * Handler for submitted form (ajax request)
     *
     * Check fields and save to default identity if valid.
     * Afterwards the session flag is removed and we're done.
     */
    function save_data()
    {
        $rcmail      = rcmail::get_instance();
        $identity    = $rcmail->user->get_identity();
        $ident_level = intval($rcmail->config->get('identities_level', 0));
        $disabled    = array();

        $save_data = array(
            'name'         => rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST),
            'email'        => rcube_utils::get_input_value('_email', rcube_utils::INPUT_POST),
            'organization' => rcube_utils::get_input_value('_organization', rcube_utils::INPUT_POST),
            'signature'    => rcube_utils::get_input_value('_signature', rcube_utils::INPUT_POST),
        );

        if ($ident_level == 4) {
            $disabled = array('name', 'email', 'organization');
        }
        else if (in_array($ident_level, array(1, 3))) {
            $disabled = array('email');
        }

        foreach ($disabled as $key) {
            $save_data[$key] = $identity[$key];
        }

        if (empty($save_data['name']) || empty($save_data['email'])) {
            $rcmail->output->show_message('formincomplete', 'error');
        }
        else if (!rcube_utils::check_email($save_data['email'] = rcube_utils::idn_to_ascii($save_data['email']))) {
            $rcmail->output->show_message('emailformaterror', 'error', array('email' => $save_data['email']));
        }
        else {
            // save data
            $rcmail->user->update_identity($identity['identity_id'], $save_data);
            $rcmail->session->remove('plugin.newuserdialog');
            // hide dialog
            $rcmail->output->command('new_user_dialog_close');
            $rcmail->output->show_message('successfullysaved', 'confirmation');
        }

        $rcmail->output->send();
    }
}
