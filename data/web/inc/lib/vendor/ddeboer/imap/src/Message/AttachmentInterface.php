<?php

declare(strict_types=1);

namespace Ddeboer\Imap\Message;

/**
 * An e-mail attachment.
 */
interface AttachmentInterface extends PartInterface
{
    /**
     * Get attachment filename.
     *
     * @return null|string
     */
    public function getFilename(): ?string;

    /**
     * Get attachment file size.
     *
     * @return int Number of bytes
     */
    public function getSize();

    /**
     * Is this attachment also an Embedded Message?
     *
     * @return bool
     */
    public function isEmbeddedMessage(): bool;

    /**
     * Return embedded message.
     *
     * @return EmbeddedMessageInterface
     */
    public function getEmbeddedMessage(): EmbeddedMessageInterface;
}
