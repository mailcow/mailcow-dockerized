<?php

/**
 * HTTP Basic Authentication
 *
 * Make use of an existing HTTP authentication and perform login with the existing user credentials
 *
 * Configuration:
 * // redirect the client to this URL after logout. This page is then responsible to clear HTTP auth
 * $config['logout_url'] = 'http://server.tld/logout.html';
 *
 * See logout.html (in this directory) for an example how HTTP auth can be cleared.
 *
 * For other configuration options, see config.inc.php.dist!
 *
 * @license GNU GPLv3+
 * @author Thomas Bruederli
 */
class http_authentication extends rcube_plugin
{
    private $redirect_query;

    function init()
    {
        $this->add_hook('startup', array($this, 'startup'));
        $this->add_hook('authenticate', array($this, 'authenticate'));
        $this->add_hook('logout_after', array($this, 'logout'));
        $this->add_hook('login_after', array($this, 'login'));
    }

    function startup($args)
    {
        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $rcmail = rcmail::get_instance();
            $rcmail->add_shutdown_function(array('http_authentication', 'shutdown'));

            // handle login action
            if (empty($_SESSION['user_id'])) {
                $args['action']         = 'login';
                $this->redirect_query   = $_SERVER['QUERY_STRING'];
            }
            // Set user password in session (see shutdown() method for more info)
            else if (!empty($_SESSION['user_id']) && empty($_SESSION['password'])
                     && !empty($_SERVER['PHP_AUTH_PW'])) {
                $_SESSION['password'] = $rcmail->encrypt($_SERVER['PHP_AUTH_PW']);
            }
        }

        return $args;
    }

    function authenticate($args)
    {
        // Load plugin's config file
        $this->load_config();

        $host = rcmail::get_instance()->config->get('http_authentication_host');
        if (is_string($host) && trim($host) !== '' && empty($args['host']))
            $args['host'] = rcube_utils::idn_to_ascii(rcube_utils::parse_host($host));

        // Allow entering other user data in login form,
        // e.g. after log out (#1487953)
        if (!empty($args['user'])) {
            return $args;
        }

        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $args['user'] = $_SERVER['PHP_AUTH_USER'];
            if (!empty($_SERVER['PHP_AUTH_PW']))
                $args['pass'] = $_SERVER['PHP_AUTH_PW'];
        }

        $args['cookiecheck'] = false;
        $args['valid'] = true;

        return $args;
    }

    function logout($args)
    {
        // redirect to configured URL in order to clear HTTP auth credentials
        if (!empty($_SERVER['PHP_AUTH_USER']) && $args['user'] == $_SERVER['PHP_AUTH_USER']) {
            if ($url = rcmail::get_instance()->config->get('logout_url')) {
                header("Location: $url", true, 307);
            }
        }
    }

    function shutdown()
    {
        // There's no need to store password (even if encrypted) in session
        // We'll set it back on startup (#1486553)
        rcmail::get_instance()->session->remove('password');
    }

    function login($args)
    {
        // Redirect to the previous QUERY_STRING
        if($this->redirect_query){
            header('Location: ./?' . $this->redirect_query);
            exit;
        }
        return $args;
    }
}

