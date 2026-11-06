<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Http;

use RuntimeException;

/**
 * A failed OAuth request. The message is safe to store and show: "HTTP <code>" plus the provider's
 * error fields, or a fixed text; never a raw response body or curl details.
 */
class OAuthException extends RuntimeException
{
}
