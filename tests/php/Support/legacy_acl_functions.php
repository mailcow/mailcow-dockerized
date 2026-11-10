<?php

// Stand-ins for the legacy ACL functions of data/web/inc/functions.inc.php, used by ContextTest only:
// they record the call and answer from a fixed table.

use Mailcow\Tests\Support\LegacyAclCalls;

if (!function_exists('hasMailboxObjectAccess')) {
    function hasMailboxObjectAccess($username, $role, $object)
    {
        LegacyAclCalls::$calls[] = ['mailbox', $username, $role, $object];
        return $object === 'allowed@example.org';
    }
}

if (!function_exists('hasDomainAccess')) {
    function hasDomainAccess($username, $role, $domain)
    {
        LegacyAclCalls::$calls[] = ['domain', $username, $role, $domain];
        return $domain === 'example.org';
    }
}
