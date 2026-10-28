<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * Provides a read only stream that pumps data from a PHP callable.
 *
 * When invoking the provided callable, the PumpStream will pass the suggested
 * number of bytes to read to the callable. The callable can choose to ignore
 * this value and return fewer or more bytes than requested. Any extra data
 * returned by the callable is buffered internally until drained using the
 * read() function of the PumpStream. The callable MUST return a non-empty
 * string when data is available, or false or null when there is no more data
 * to read.
 *
 * Userland callables that declare no parameters are tolerated by PHP, but
 * length-aware callables remain the recommended formal shape.
 */
final class PumpStream implements StreamInterface
{
    use NonSerializableStreamTrait;

    /** @var callable|null */
    private $source;

    private ?int $size;

    private int $tellPos = 0;

    private array $metadata;

    private BufferStream $buffer;

    /**
     * @param (callable(): (string|false|null))|(callable(int): (string|false|null)) $source  Source of the stream data. The callable receives
     *                                                                                        the suggested number of bytes to read, may ignore
     *                                                                                        that value, and may return fewer or more bytes.
     *                                                                                        Extra bytes are buffered. The callable MUST return
     *                                                                                        a non-empty string when producing data, or false|null
     *                                                                                        on error or EOF. Userland callables that declare no
     *                                                                                        parameters are tolerated by PHP, but length-aware
     *                                                                                        callables remain the recommended formal shape.
     * @param array{size?: int, metadata?: array}                                    $options Stream options:
     *                                                                                        - metadata: Hash of metadata to use with stream.
     *                                                                                        - size: Size of the stream, if known.
     */
    public function __construct(callable $source, array $options = [])
    {
        $this->source = $source;
        $this->size = Integers::assertOptionalNonNegativeSize($options['size'] ?? null, 'Stream size');
        $this->metadata = $options['metadata'] ?? [];
        $this->buffer = new BufferStream();
    }

    public function __unserialize(array $data): void
    {
        $this->source = null;
        $this->size = null;
        $this->tellPos = 0;
        $this->metadata = [];
        $this->buffer = new BufferStream();

        throw new \LogicException(static::class.' should never be unserialized');
    }

    public function __toString(): string
    {
        return Utils::copyToString($this);
    }

    public function close(): void
    {
        $this->detach();
    }

    public function detach()
    {
        $this->tellPos = 0;
        $this->source = null;
        $this->buffer->close();

        return null;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function tell(): int
    {
        return $this->tellPos;
    }

    public function eof(): bool
    {
        return $this->source === null;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new \RuntimeException('Cannot seek a PumpStream');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new \RuntimeException('Cannot write to a PumpStream');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }

        $bufferLength = $this->buffer->getSize() ?? 0;

        if ($length > $bufferLength) {
            $this->pump($length - $bufferLength);
        }

        $data = $this->buffer->read($length);
        $this->tellPos = Integers::add($this->tellPos, strlen($data));

        return $data;
    }

    public function getContents(): string
    {
        return Utils::copyToString($this);
    }

    /**
     * @return mixed
     */
    public function getMetadata(?string $key = null)
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    private function pump(int $length): void
    {
        if ($this->source !== null) {
            do {
                /** @var string|false|null $data */
                $data = ($this->source)($length);
                if ($data === false || $data === null) {
                    $this->source = null;

                    return;
                }

                if ($data === '') {
                    throw new \RuntimeException('PumpStream source returned an empty string');
                }

                $this->buffer->write($data);
                $length -= strlen($data);
            } while ($length > 0);
        }
    }
}
