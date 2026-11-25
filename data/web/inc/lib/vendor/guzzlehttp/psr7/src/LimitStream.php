<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * Decorator used to return only a subset of a stream.
 */
final class LimitStream implements StreamInterface
{
    use StreamDecoratorTrait;
    use NonSerializableStreamTrait;

    /** @var int Offset to start reading from */
    private int $offset;

    /** @var int Limit the number of bytes that can be read */
    private int $limit;

    private StreamInterface $stream;

    /**
     * @param StreamInterface $stream Stream to wrap
     * @param int             $limit  Total number of bytes to allow to be read
     *                                from the stream. Pass -1 for no limit.
     * @param int             $offset Position to seek to before reading (only
     *                                works on seekable streams).
     */
    public function __construct(
        StreamInterface $stream,
        int $limit = -1,
        int $offset = 0
    ) {
        $this->stream = $stream;
        $this->setLimit($limit);
        $this->setOffset($offset);
    }

    public function eof(): bool
    {
        // Always return true if the underlying stream is EOF
        if ($this->stream->eof()) {
            return true;
        }

        // No limit and the underlying stream is not at EOF
        if ($this->limit === -1) {
            return false;
        }

        return $this->stream->tell() >= Integers::add($this->offset, $this->limit);
    }

    /**
     * Returns the size of the limited subset of data
     */
    public function getSize(): ?int
    {
        if (null === ($length = $this->stream->getSize())) {
            return null;
        }

        $size = $length - $this->offset;

        if ($this->limit !== -1) {
            $size = min($this->limit, $size);
        }

        return max(0, $size);
    }

    /**
     * Allow for a bounded seek on the read limited stream
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($whence !== SEEK_SET || $offset < 0) {
            throw new \RuntimeException(sprintf(
                'Cannot seek to offset %s with whence %s',
                $offset,
                $whence
            ));
        }

        $offset = Integers::add($this->offset, $offset);

        if ($this->limit !== -1) {
            $upperBound = Integers::add($this->offset, $this->limit);
            if ($offset > $upperBound) {
                $offset = $upperBound;
            }
        }

        $this->stream->seek($offset);
    }

    /**
     * Give a relative tell()
     */
    public function tell(): int
    {
        return $this->stream->tell() - $this->offset;
    }

    /**
     * Set the offset to start limiting from
     *
     * @param int $offset Offset to seek to and begin byte limiting from
     *
     * @throws \RuntimeException if the stream cannot be seeked.
     */
    public function setOffset(int $offset): void
    {
        $offset = Integers::assertNonNegativeInteger($offset, 'Offset');

        $current = $this->stream->tell();

        if ($current === $offset) {
            $this->offset = $offset;

            return;
        }

        // If the stream cannot seek to the offset position, then read to it.
        if ($this->stream->isSeekable()) {
            $this->stream->seek($offset);
            $this->offset = $offset;

            return;
        }

        if ($current > $offset) {
            throw new \RuntimeException("Could not seek to stream offset $offset");
        }

        while ($current < $offset) {
            if ($this->stream->eof()) {
                $this->offset = $current;

                return;
            }

            $result = $this->stream->read($offset - $current);

            if ($result === '') {
                if ($this->stream->eof()) {
                    $this->offset = $current;

                    return;
                }

                throw new \RuntimeException("Could not seek to stream offset $offset");
            }

            $current = Integers::add($current, strlen($result));
        }

        $this->offset = $offset;
    }

    /**
     * Set the limit of bytes that the decorator allows to be read from the
     * stream.
     *
     * @param int $limit Number of bytes to allow to be read from the stream.
     *                   Use -1 for no limit.
     */
    public function setLimit(int $limit): void
    {
        $this->limit = Integers::assertLimitInteger($limit, 'Limit');
    }

    public function read(int $length): string
    {
        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }

        if ($this->limit === -1) {
            return $this->stream->read($length);
        }

        // Check if the current position is less than the total allowed
        // bytes + original offset
        $remaining = Integers::add($this->offset, $this->limit) - $this->stream->tell();
        if ($remaining > 0) {
            // Only return the amount of requested data, ensuring that the byte
            // limit is not exceeded
            return $this->stream->read(min($remaining, $length));
        }

        return '';
    }
}
