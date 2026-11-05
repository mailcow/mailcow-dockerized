<?php

declare(strict_types=1);

namespace Ddeboer\Imap;

use IMAP\Connection;

interface ImapResourceInterface
{
    /**
     * Get IMAP resource stream.
     */
    public function getStream(): Connection;

    /**
     * Clear last mailbox used cache.
     */
    public function clearLastMailboxUsedCache(): void;
}
