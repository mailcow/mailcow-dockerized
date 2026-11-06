<?php

declare(strict_types=1);

namespace Mailcow\Archive\Smtp;

use RuntimeException;

/**
 * An SMTP step failed: connect, tls, auth, sender, recipient or data, with the reply of the server.
 */
class SmtpException extends RuntimeException
{
    private const MAX_TEXT_LENGTH = 200;

    public function __construct(public readonly string $step, public readonly string $reply)
    {
        parent::__construct($step . ': ' . $reply);
    }

    /** Short text for messages: "<step>: <reply>", without angle brackets (the UI escapes message arguments twice) */
    public function describe(): string
    {
        $text = trim((string)preg_replace('/\s+/', ' ', $this->step . ': ' . str_replace(['<', '>'], '', $this->reply)));
        return mb_substr($text, 0, self::MAX_TEXT_LENGTH);
    }
}
