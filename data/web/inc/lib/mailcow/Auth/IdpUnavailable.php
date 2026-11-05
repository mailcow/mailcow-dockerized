<?php

declare(strict_types=1);

namespace Mailcow\Auth;

use RuntimeException;

/**
 * The identity provider could not answer a login (unreachable, timeout, server error). No failed login:
 * callers report a temporary failure and must not count it as an attempt for netfilter.
 */
class IdpUnavailable extends RuntimeException
{
}
