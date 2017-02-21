<?php

/**
 * Kerberos Authentication
 *
 * Make use of an existing Kerberos authentication and perform login
 * with the existing user credentials
 *
 * For other configuration options, see config.inc.php.dist!
 *
 * @license GNU GPLv3+
 * @author Jeroen van Meeuwen
 */
class krb_authentication extends rcube_plugin
{
    private $redirect_query;

    /**
     * Plugin initialization
     */
    function init()
    {
        $this->add_hook('startup', array($this, 'startup'));
        $this->add_hook('authenticate', array($this, 'authenticate'));
        $this->add_hook('login_after', array($this, 'login'));
        $this->add_hook('storage_connect', array($this, 'storage_connect'));
    }

    /**
     * Startup hook handler
     */
    function startup($args)
    {
        if (!empty($_SERVER['REMOTE_USER']) && !empty($_SERVER['KRB5CCNAME'])) {
            // handle login action
            if (empty($_SESSION['user_id'])) {
                $args['action']       = 'login';
                $this->redirect_query = $_SERVER['QUERY_STRING'];
            }
            else {
                $_SESSION['password'] = null;
            }
        }

        return $args;
    }

    /**
     * Authenticate hook handler
     */
    function authenticate($args)
    {
        if (!empty($_SERVER['REMOTE_USER']) && !empty($_SERVER['KRB5CCNAME'])) {
            // Load plugin's config file
            $this->load_config();

            $rcmail = rcmail::get_instance();
            $host   = $rcmail->config->get('krb_authentication_host');

            if (is_string($host) && trim($host) !== '' && empty($args['host'])) {
                $args['host'] = rcube_utils::idn_to_ascii(rcube_utils::parse_host($host));
            }

            if (!empty($_SERVER['REMOTE_USER'])) {
                $args['user'] = $_SERVER['REMOTE_USER'];
                $args['pass'] = null;
            }

            $args['cookiecheck'] = false;
            $args['valid']       = true;
        }

        return $args;
    }

    /**
     * Storage_connect hook handler
     */
    function storage_connect($args)
    {
        if (!empty($_SERVER['REMOTE_USER']) && !empty($_SERVER['KRB5CCNAME'])) {
            // Load plugin's config file
            $this->load_config();

            $rcmail  = rcmail::get_instance();
            $context = $rcmail->config->get('krb_authentication_context');

            $args['gssapi_context'] = $context ?: 'imap/kolab.example.org@EXAMPLE.ORG';
            $args['gssapi_cn']      = $_SERVER['KRB5CCNAME'];
            $args['auth_type']      = 'GSSAPI';
        }

        return $args;
    }

    /**
     * login_after hook handler
     */
    function login($args)
    {
        // Redirect to the previous QUERY_STRING
        if ($this->redirect_query) {
            header('Location: ./?' . $this->redirect_query);
            exit;
        }

        return $args;
    }
}
