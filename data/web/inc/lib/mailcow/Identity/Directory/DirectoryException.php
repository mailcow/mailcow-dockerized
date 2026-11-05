<?php

declare(strict_types=1);

namespace Mailcow\Identity\Directory;

use RuntimeException;

/**
 * The IdP could not be listed completely; the sync run stops without writing anything.
 */
class DirectoryException extends RuntimeException
{
}
