<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * Stream decorator that records read failures and returns an empty string.
 *
 * InflateStream reads through it so that a failure part way through a filtered
 * fread() cannot discard bytes that were already decoded.
 *
 * @internal
 */
final class ReadFailureCapturingStream implements StreamInterface
{
    use StreamDecoratorTrait;
    use NonSerializableStreamTrait;

    private StreamInterface $stream;

    private ?\Exception $readFailure = null;

    private bool $capturing = true;

    public function read(int $length): string
    {
        if (!$this->capturing) {
            return $this->stream->read($length);
        }

        try {
            return $this->stream->read($length);
        } catch (\Exception $e) {
            $this->readFailure = $e;

            return '';
        }
    }

    public function stopCapturing(): void
    {
        $this->capturing = false;
        $this->readFailure = null;
    }

    public function hasReadFailure(): bool
    {
        return $this->readFailure !== null;
    }

    public function takeReadFailure(): ?\Exception
    {
        $failure = $this->readFailure;
        $this->readFailure = null;

        return $failure;
    }
}
