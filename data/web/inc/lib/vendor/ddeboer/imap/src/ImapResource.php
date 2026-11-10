<?php

declare(strict_types=1);

namespace Ddeboer\Imap;

use Ddeboer\Imap\Exception\ReopenMailboxException;
use IMAP\Connection;

/**
 * An imap resource stream.
 */
final class ImapResource implements ImapResourceInterface
{
    private Connection $resource;
    private ?MailboxInterface $mailbox;
    private static ?string $lastMailboxUsedCache = null;

    /**
     * Constructor.
     */
    public function __construct(Connection $resource, MailboxInterface $mailbox = null)
    {
        $this->resource = $resource;
        $this->mailbox  = $mailbox;
    }

    public function getStream(): Connection
    {
        $this->initMailbox();

        return $this->resource;
    }

    public function clearLastMailboxUsedCache(): void
    {
        self::$lastMailboxUsedCache = null;
    }

    /**
     * If connection is not currently in this mailbox, switch it to this mailbox.
     */
    private function initMailbox(): void
    {
        if (null === $this->mailbox || self::isMailboxOpen($this->mailbox, $this->resource)) {
            return;
        }

        \set_error_handler(static function (): bool {
            return true;
        });

        \imap_reopen($this->resource, $this->mailbox->getFullEncodedName());

        \restore_error_handler();

        if (self::isMailboxOpen($this->mailbox, $this->resource)) {
            return;
        }

        throw new ReopenMailboxException(\sprintf('Cannot reopen mailbox "%s"', $this->mailbox->getName()));
    }

    /**
     * Check whether the current mailbox is open.
     */
    private static function isMailboxOpen(MailboxInterface $mailbox, Connection $resource): bool
    {
        $currentMailboxName = $mailbox->getFullEncodedName();
        if ($currentMailboxName === self::$lastMailboxUsedCache) {
            return true;
        }

        self::$lastMailboxUsedCache = null;
        $check                      = \imap_check($resource);
        $return                     = false !== $check && $check->Mailbox === $currentMailboxName;

        if (true === $return) {
            self::$lastMailboxUsedCache = $currentMailboxName;
        }

        return $return;
    }
}
