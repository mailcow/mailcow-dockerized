<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Psr7\Exception\TimeoutException;
use Psr\Http\Message\StreamInterface;

/**
 * PHP stream implementation.
 */
class Stream implements StreamInterface
{
    use NonSerializableStreamTrait;

    /** @var resource */
    private $stream;
    private ?int $size = null;
    private bool $seekable;
    private bool $readable;
    private bool $writable;
    private ?string $uri = null;
    /** @var mixed[] */
    private array $customMetadata;

    /**
     * This constructor accepts an associative array of options.
     *
     * - size: (int) If a read stream would otherwise have an indeterminate
     *   size, but the size is known due to foreknowledge, then you can
     *   provide that size, in bytes.
     * - metadata: (array) Any additional metadata to return when the metadata
     *   of the stream is accessed.
     *
     * @param resource                            $stream  Stream resource to wrap.
     * @param array{size?: int, metadata?: array} $options Associative array of options.
     *
     * @throws \InvalidArgumentException if the stream is not a stream resource
     */
    public function __construct($stream, array $options = [])
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        $this->size = Integers::assertOptionalNonNegativeSize($options['size'] ?? null, 'Stream size');

        $this->customMetadata = $options['metadata'] ?? [];
        $this->stream = $stream;
        $meta = stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = self::isReadableMode($meta['mode']);
        $this->writable = self::isWritableMode($meta['mode']);
        $this->uri = $meta['uri'] ?? null;
    }

    /**
     * Closes the stream when the destructed
     */
    public function __destruct()
    {
        $this->close();
    }

    public function __toString(): string
    {
        if ($this->isSeekable()) {
            $this->seek(0);
        }

        return $this->getContents();
    }

    public function getContents(): string
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->readable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }

        return Utils::tryGetContents($this->stream);
    }

    public function close(): void
    {
        if (isset($this->stream)) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    public function detach()
    {
        if (!isset($this->stream)) {
            return null;
        }

        $result = $this->stream;
        unset($this->stream);
        $this->size = $this->uri = null;
        $this->readable = $this->writable = $this->seekable = false;

        return $result;
    }

    public function getSize(): ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if (!isset($this->stream)) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            clearstatcache(true, $this->uri);
        }

        $stats = fstat($this->stream);
        if ($stats === false) {
            return null;
        }

        $this->size = Integers::assertEngineInteger($stats['size'], 'Stream size');

        return $this->size;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function eof(): bool
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        return feof($this->stream);
    }

    public function tell(): int
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        $result = ftell($this->stream);
        if ($result === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        $position = Integers::assertEngineInteger($result, 'Stream position');
        if ($position === null) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        return $position;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->seekable) {
            throw new \RuntimeException('Stream is not seekable');
        }
        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position '
                .$offset.' with whence '.var_export($whence, true));
        }
    }

    public function read(int $length): string
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->readable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }
        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }

        if (0 === $length) {
            return '';
        }

        try {
            $string = fread($this->stream, $length);
        } catch (TimeoutException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->timedOut()) {
                throw new TimeoutException('Unable to read from stream: timed out', 0, $e);
            }

            throw new \RuntimeException('Unable to read from stream', 0, $e);
        }

        if (false === $string) {
            if ($this->timedOut()) {
                throw new TimeoutException('Unable to read from stream: timed out');
            }

            throw new \RuntimeException('Unable to read from stream');
        }

        if ($string === '' && $this->timedOut()) {
            throw new TimeoutException('Unable to read from stream: timed out');
        }

        return $string;
    }

    public function write(string $string): int
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->writable) {
            throw new \RuntimeException('Cannot write to a non-writable stream');
        }

        if ($string === '') {
            return 0;
        }

        // We can't know the size after writing anything
        $this->size = null;

        try {
            $result = fwrite($this->stream, $string);
        } catch (TimeoutException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->writeTimedOut()) {
                throw new TimeoutException('Unable to write to stream: timed out', 0, $e);
            }

            throw new \RuntimeException('Unable to write to stream', 0, $e);
        }

        if ($result === false) {
            if ($this->writeTimedOut()) {
                throw new TimeoutException('Unable to write to stream: timed out');
            }

            throw new \RuntimeException('Unable to write to stream');
        }

        if ($result === 0 && $this->writeTimedOut()) {
            throw new TimeoutException('Unable to write to stream: timed out');
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getMetadata(?string $key = null)
    {
        if (!isset($this->stream)) {
            return $key === null ? [] : null;
        } elseif ($key === null) {
            return $this->customMetadata + stream_get_meta_data($this->stream);
        } elseif (isset($this->customMetadata[$key])) {
            return $this->customMetadata[$key];
        }

        $meta = stream_get_meta_data($this->stream);

        return $meta[$key] ?? null;
    }

    /**
     * @see https://www.php.net/manual/en/function.fopen.php
     * @see https://www.php.net/manual/en/function.gzopen.php
     */
    private static function isReadableMode(string $mode): bool
    {
        return str_starts_with($mode, 'r') || str_contains($mode, '+');
    }

    /**
     * @see https://www.php.net/manual/en/function.fopen.php
     * @see https://www.php.net/manual/en/function.gzopen.php
     */
    private static function isWritableMode(string $mode): bool
    {
        return str_starts_with($mode, 'a')
            || str_starts_with($mode, 'w')
            || str_starts_with($mode, 'x')
            || str_starts_with($mode, 'c')
            || str_contains($mode, '+');
    }

    private function timedOut(): bool
    {
        return StreamTimeout::isResourceReadTimedOut($this->stream);
    }

    private function writeTimedOut(): bool
    {
        return StreamTimeout::isResourceWriteTimedOut($this->stream);
    }
}
