<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Psr7\Exception\TimeoutException;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class StreamTimeout
{
    private function __construct()
    {
    }

    public static function read(StreamInterface $stream, int $length, string $timeoutMessage): string
    {
        try {
            $buffer = $stream->read($length);
        } catch (TimeoutException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            self::throwIfReadTimedOut($stream, $timeoutMessage, $e);

            throw $e;
        }

        if ($buffer === '') {
            self::throwIfReadTimedOut($stream, $timeoutMessage);
        }

        return $buffer;
    }

    public static function throwIfReadTimedOut(
        StreamInterface $stream,
        string $message,
        ?\Throwable $previous = null
    ): void {
        if (self::isReadTimedOut($stream)) {
            throw new TimeoutException($message, 0, $previous);
        }
    }

    public static function throwIfWriteTimedOut(StreamInterface $stream, ?\Throwable $previous = null): void
    {
        if (self::isWriteTimedOut($stream)) {
            throw new TimeoutException('Unable to write to stream: timed out', 0, $previous);
        }
    }

    public static function isReadTimedOut(StreamInterface $stream): bool
    {
        try {
            if ($stream->getMetadata('timed_out') !== true) {
                return false;
            }

            return !$stream->eof();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isWriteTimedOut(StreamInterface $stream): bool
    {
        try {
            return $stream->getMetadata('timed_out') === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param resource $resource
     */
    public static function isResourceReadTimedOut($resource): bool
    {
        try {
            /** @var array<string, mixed> $metadata */
            $metadata = stream_get_meta_data($resource);

            return ($metadata['timed_out'] ?? false) === true && !feof($resource);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param resource $resource
     */
    public static function isResourceWriteTimedOut($resource): bool
    {
        try {
            /** @var array<string, mixed> $metadata */
            $metadata = stream_get_meta_data($resource);

            return ($metadata['timed_out'] ?? false) === true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
