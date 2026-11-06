<?php

declare(strict_types=1);

namespace Mailcow\Sogo;

use Mailcow\Sogo\Service\SogoService;

/**
 * SOGo (user view, cache, sogo-tool through dockerapi). Modules use it instead of the legacy SOGo functions.
 */
class SogoModule
{
    public function __construct(public readonly SogoService $sogo)
    {
    }

    public static function create(): self
    {
        return new self(new SogoService());
    }
}
