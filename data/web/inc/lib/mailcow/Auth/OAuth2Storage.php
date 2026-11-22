<?php

declare(strict_types=1);

namespace Mailcow\Auth;

use OAuth2\Storage\Pdo;

/**
 * OAuth2 server storage of mailcow: clients and tokens in the bshaffer tables, users are mailboxes.
 *
 * The password grant asks checkUserCredentials() and then getUserDetails() with the username of the
 * request. check_login() may change that name (ALLOW_BARE_LOCALPART_LOGIN qualifies "alice" as
 * "alice@example.org"), so the name it checked is kept and the token is issued for exactly that mailbox.
 */
class OAuth2Storage extends Pdo
{
    /** @var array<string, string> requested username => mailbox whose password was checked */
    private array $checkedUsers = [];

    public function __construct($connection, $config = array())
    {
        parent::__construct($connection, $config);
        $this->config['user_table'] = 'mailbox';
    }

    public function checkUserCredentials($username, $password)
    {
        $login = $username;
        if (\check_login($login, $password, array('role' => 'user', 'service' => 'NONE', 'is_internal' => false)) == 'user') {
            $this->checkedUsers[$username] = $login;
            return true;
        }
        return false;
    }

    public function getUserDetails($username)
    {
        return $this->getUser($this->checkedUsers[$username] ?? $username);
    }
}
