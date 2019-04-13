<?php

declare(strict_types=1);

namespace Ddeboer\Imap\Message;

use Ddeboer\Imap\Exception\NotEmbeddedMessageException;

/**
 * An e-mail attachment.
 */
final class Attachment extends AbstractPart implements AttachmentInterface
{
    /**
     * Get attachment filename.
     *
     * @return null|string
     */
    public function getFilename(): ?string
    {
        return $this->getParameters()->get('filename')
            ?: $this->getParameters()->get('name');
    }

    /**
     * Get attachment file size.
     *
     * @return int Number of bytes
     */
    public function getSize()
    {
        return $this->getParameters()->get('size');
    }

    /**
     * Is this attachment also an Embedded Message?
     *
     * @return bool
     */
    public function isEmbeddedMessage(): bool
    {
        return self::TYPE_MESSAGE === $this->getType();
    }

    /**
     * Return embedded message.
     *
     * @throws NotEmbeddedMessageException
     *
     * @return EmbeddedMessageInterface
     */
    public function getEmbeddedMessage(): EmbeddedMessageInterface
    {
        if (!$this->isEmbeddedMessage()) {
            throw new NotEmbeddedMessageException(\sprintf(
                'Attachment "%s" in message "%s" is not embedded message',
                $this->getPartNumber(),
                $this->getNumber()
            ));
        }

        return new EmbeddedMessage($this->resource, $this->getNumber(), $this->getPartNumber(), $this->getStructure()->parts[0]);
    }
}
