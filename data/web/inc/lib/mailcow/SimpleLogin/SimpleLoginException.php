<?php

declare(strict_types=1);

namespace Mailcow\SimpleLogin;

use RuntimeException;

/**
 * A request the SimpleLogin-compatible endpoint cannot fulfil.
 *
 * The HTTP status and error code are part of the API contract: clients such as Bitwarden
 * show the message next to the API key field, so failures must stay descriptive without
 * revealing whether a mailbox exists.
 */
class SimpleLoginException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $error,
        string $message
    ) {
        parent::__construct($message);
    }
}
