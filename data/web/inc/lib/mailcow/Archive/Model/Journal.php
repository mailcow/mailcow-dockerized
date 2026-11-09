<?php

declare(strict_types=1);

namespace Mailcow\Archive\Model;

/**
 * What the archive learns about one mail besides its content: owners, SMTP envelope, origin and content hash.
 */
class Journal
{
    public const DIRECTIONS = ['in', 'out', 'internal'];
    public const SOURCES = ['smtp', 'quarantine', 'massmail', 'test'];

    /**
     * @param string[] $owners mailbox usernames the mail belongs to
     * @param string[] $rcpts SMTP envelope recipients as received (incl. BCC)
     * @param float $received time mailcow accepted the mail (unix time with milliseconds)
     * @param string $sha256 hash of the content as sent to the archive (CRLF line ends)
     */
    public function __construct(
        public readonly array $owners,
        public readonly array $rcpts,
        public readonly string $from,
        public readonly string $qid,
        public readonly string $direction,
        public readonly string $source,
        public readonly float $received,
        public readonly string $sha256
    ) {
    }
}
