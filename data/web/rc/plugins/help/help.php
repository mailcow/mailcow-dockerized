<?php

/**
 * Roundcube Help Plugin
 *
 * @author Aleksander 'A.L.E.C' Machniak
 * @author Thomas Bruederli <thomas@roundcube.net>
 * @license GNU GPLv3+
 *
 * Configuration (see config.inc.php.dist)
 * 
 **/

class help extends rcube_plugin
{
    // all task excluding 'login' and 'logout'
    public $task = '?(?!login|logout).*';
    // we've got no ajax handlers
    public $noajax = true;
    // skip frames
    public $noframe = true;

    function init()
    {
        $this->load_config();
        $this->add_texts('localization/', false);

        // register task
        $this->register_task('help');

        // register actions
        $this->register_action('index', array($this, 'action'));
        $this->register_action('about', array($this, 'action'));
        $this->register_action('license', array($this, 'action'));

        $this->add_hook('startup', array($this, 'startup'));
        $this->add_hook('error_page', array($this, 'error_page'));
    }

    function startup($args)
    {
        $rcmail = rcmail::get_instance();

        // add taskbar button
        $this->add_button(array(
            'command'    => 'help',
            'class'      => 'button-help',
            'classsel'   => 'button-help button-selected',
            'innerclass' => 'button-inner',
            'label'      => 'help.help',
        ), 'taskbar');

        $this->include_script('help.js');
        $rcmail->output->set_env('help_open_extwin', $rcmail->config->get('help_open_extwin', false), true);

        // add style for taskbar button (must be here) and Help UI
        $skin_path = $this->local_skin_path();
        if (is_file($this->home . "/$skin_path/help.css")) {
            $this->include_stylesheet("$skin_path/help.css");
        }
    }

    function action()
    {
        $rcmail = rcmail::get_instance();

        // register UI objects
        $rcmail->output->add_handlers(array(
            'helpcontent' => array($this, 'content'),
            'tablink' => array($this, 'tablink'),
        ));

        if ($rcmail->action == 'about')
            $rcmail->output->set_pagetitle($this->gettext('about'));
        else if ($rcmail->action == 'license')
            $rcmail->output->set_pagetitle($this->gettext('license'));
        else
            $rcmail->output->set_pagetitle($this->gettext('help'));

        $rcmail->output->send('help.help');
    }

    function tablink($attrib)
    {
        $rcmail = rcmail::get_instance();

        $attrib['name'] = 'helplink' . $attrib['action'];
        $attrib['href'] = $rcmail->url(array('_action' => $attrib['action'], '_extwin' => !empty($_REQUEST['_extwin']) ? 1 : null));

        // title might be already translated here, so revert to it's initial value
        // so button() will translate it correctly
        $attrib['title'] = $attrib['label'];

        return $rcmail->output->button($attrib);
    }

    function content($attrib)
    {
        $rcmail = rcmail::get_instance();

        switch ($rcmail->action) {
            case 'about':
                if (is_readable($this->home . '/content/about.html')) {
                    return @file_get_contents($this->home . '/content/about.html');
                }
                $default = $rcmail->url(array('_task' => 'settings', '_action' => 'about', '_framed' => 1));
                $src     = $rcmail->config->get('help_about_url', $default);
                break;

            case 'license':
                if (is_readable($this->home . '/content/license.html')) {
                    return @file_get_contents($this->home . '/content/license.html');
                }
                $src = $rcmail->config->get('help_license_url', 'http://www.gnu.org/licenses/gpl-3.0-standalone.html');
                break;

            default:
                $src = $rcmail->config->get('help_source');

                // resolve task/action for depp linking
                $index_map = $rcmail->config->get('help_index_map', array());
                $rel = $_REQUEST['_rel'];
                list($task,$action) = explode('/', $rel);
                if ($add = $index_map[$rel])
                    $src .= $add;
                else if ($add = $index_map[$task])
                    $src .= $add;
                break;
        }

        // default content: iframe
        if (!empty($src)) {
            $attrib['src'] = $this->resolve_language($src);
        }

        if (empty($attrib['id']))
            $attrib['id'] = 'rcmailhelpcontent';

        $attrib['name'] = $attrib['id'];

        return $rcmail->output->frame($attrib);
    }

    function error_page($args)
    {
        $rcmail = rcmail::get_instance();

        if ($args['code'] == 403 && $rcmail->request_status == rcube::REQUEST_ERROR_URL && ($url = $rcmail->config->get('help_csrf_info'))) {
            $args['text'] .= '<p>' . html::a(array('href' => $url, 'target' => '_blank'), $this->gettext('csrfinfo')) . '</p>';
        }

        return $args;
    }

    private function resolve_language($path)
    {
        // resolve language placeholder
        $rcmail  = rcmail::get_instance();
        $langmap = $rcmail->config->get('help_language_map', array('*' => 'en_US'));
        $lang    = $langmap[$_SESSION['language']] ?: $langmap['*'];

        return str_replace('%l', $lang, $path);
    }
}
