<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * Stream decorator that can cache previously read bytes from a sequentially
 * read stream.
 */
final class CachingStream implements StreamInterface
{
    use StreamDecoratorTrait;
    use NonSerializableStreamTrait;

    /** @var StreamInterface Stream being wrapped */
    private StreamInterface $remoteStream;

    /** @var int Number of bytes to skip reading due to a write on the buffer */
    private int $skipReadBytes = 0;

    private StreamInterface $stream;

    private bool $detached = false;

    private bool $closed = false;

    /**
     * We will treat the buffer object as the body of the stream
     *
     * @param StreamInterface $stream Stream to cache. The cursor is assumed to be at the beginning of the stream.
     * @param StreamInterface $target Optionally specify where data is cached. Defaults to a "php://temp"
     *                                stream. A custom target is used as a random-access byte buffer to
     *                                replay the remote stream, so it must be readable, writable, and
     *                                seekable, report an accurate position and size, and store writes
     *                                losslessly. Lossy or non-seekable streams such as BufferStream and
     *                                DroppingStream are not valid targets.
     */
    public function __construct(
        StreamInterface $stream,
        ?StreamInterface $target = null
    ) {
        $this->remoteStream = $stream;
        $this->stream = $target ?: new Stream(Utils::tryFopen('php://temp', 'r+'));
    }

    public function getSize(): ?int
    {
        if ($this->detached) {
            return null;
        }

        $remoteSize = $this->remoteStream->getSize();

        if (null === $remoteSize) {
            return null;
        }

        return max($this->stream->getSize(), $remoteSize);
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($whence === SEEK_SET) {
            $byte = $offset;
        } elseif ($whence === SEEK_CUR) {
            $byte = Integers::addSigned($this->tell(), $offset);
        } elseif ($whence === SEEK_END) {
            $size = $this->remoteStream->getSize();
            if ($size === null) {
                // Discovering the size reads the remote stream to EOF and
                // moves the cursor, so restore the cursor if the computed
                // target is rejected to keep a failed seek side-effect free.
                $position = $this->tell();
                $size = $this->cacheEntireStream();

                try {
                    $byte = Integers::addSigned($size, $offset);
                } catch (\Throwable $e) {
                    $this->stream->seek($position);

                    throw $e;
                }
            } else {
                $byte = Integers::addSigned($size, $offset);
            }
        } else {
            throw new \InvalidArgumentException('Invalid whence');
        }

        $diff = $byte - $this->stream->getSize();

        if ($diff > 0) {
            // Read the remoteStream until we have read in at least the amount
            // of bytes requested, or we reach the end of the file.
            while ($diff > 0 && !$this->remoteStream->eof()) {
                $previousSize = $this->stream->getSize();
                $previousSkipReadBytes = $this->skipReadBytes;
                $data = $this->read($diff);
                $currentSize = $this->stream->getSize();

                if ($data === '' && $currentSize === $previousSize && $this->skipReadBytes === $previousSkipReadBytes) {
                    break;
                }

                $diff = $byte - $currentSize;
            }
        } else {
            // We can just do a normal seek since we've already seen this byte.
            $this->stream->seek($byte);
        }
    }

    public function read(int $length): string
    {
        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }

        // Perform a regular read on any previously read data from the buffer
        $data = $this->stream->read($length);
        $remaining = $length - strlen($data);

        // More data was requested so read from the remote stream
        if ($remaining) {
            // If data was written to the buffer in a position that would have
            // been filled from the remote stream, then we must skip bytes on
            // the remote stream to emulate overwriting bytes from that
            // position. This mimics the behavior of other PHP stream wrappers.
            $remoteData = StreamTimeout::read(
                $this->remoteStream,
                Integers::add($remaining, $this->skipReadBytes),
                'Unable to read from stream: timed out'
            );

            if ($this->skipReadBytes) {
                $len = strlen($remoteData);
                $remoteData = substr($remoteData, $this->skipReadBytes);
                $this->skipReadBytes = max(0, $this->skipReadBytes - $len);
            }

            $data .= $remoteData;

            // A short cache write would silently corrupt later replays, so fail loudly.
            if ($this->stream->write($remoteData) !== strlen($remoteData)) {
                throw new \RuntimeException('Unable to cache the entire read from the remote stream');
            }
        }

        return $data;
    }

    public function write(string $string): int
    {
        // When appending to the end of the currently read stream, you'll want
        // to skip bytes from being read from the remote stream to emulate
        // other stream wrappers. Basically replacing bytes of data of a fixed
        // length.
        $overflow = Integers::add(strlen($string), $this->tell()) - $this->remoteStream->tell();
        if ($overflow > 0) {
            $this->skipReadBytes = max($this->skipReadBytes, $overflow);
        }

        return $this->stream->write($string);
    }

    public function eof(): bool
    {
        return $this->stream->eof() && $this->remoteStream->eof();
    }

    public function detach()
    {
        if ($this->detached) {
            return null;
        }

        $position = $this->tell();

        $this->cacheEntireStream();
        $this->stream->seek($position);

        $resource = $this->stream->detach();
        $this->detached = true;

        return $resource;
    }

    /**
     * Close the remote stream and any attached cache stream.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $closeCache = !$this->detached;
        $this->closed = true;
        $this->detached = true;

        $exception = null;

        try {
            $this->remoteStream->close();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($closeCache) {
            try {
                $this->stream->close();
            } catch (\Throwable $e) {
                if ($exception === null) {
                    $exception = $e;
                }
            }
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    private function cacheEntireStream(): int
    {
        $target = new FnStream(['write' => 'strlen']);
        Utils::copyToStream($this, $target);

        return $this->tell();
    }
}
