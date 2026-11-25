<?php

declare(strict_types=1);

namespace Mailcow\Core;

/**
 * The caller may not do this (or may not see the object). Always shown as `access_denied`.
 */
class AccessDenied extends UserError
{
    public function __construct()
    {
        parent::__construct('access_denied');
    }
}
