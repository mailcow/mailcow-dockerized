<?php

declare(strict_types=1);

namespace Mailcow\Dovecot;

use Mailcow\Dovecot\Service\DovecotService;

/**
 * Dovecot through dockerapi (ACLs, subscriptions, auth cache). Modules use it instead of calling docker().
 */
class DovecotModule
{
    public function __construct(public readonly DovecotService $dovecot)
    {
    }

    public static function create(): self
    {
        return new self(new DovecotService());
    }
}
