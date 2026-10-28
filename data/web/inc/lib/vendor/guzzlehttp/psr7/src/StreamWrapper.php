<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * Converts Guzzle streams into PHP stream resources.
 *
 * @see https://www.php.net/streamwrapper
 */
final class StreamWrapper
{
    /** @var resource */
    public $context;

    private StreamInterface $stream;

    /** @var string r, r+, or w */
    private string $mode;

    /**
     * Returns a resource representing the stream.
     *
     * @param StreamInterface $stream The stream to get a resource for
     *
     * @return resource
     *
     * @throws \InvalidArgumentException if stream is not readable or writable
     */
    public static function getResource(StreamInterface $stream)
    {
        self::register();

        if ($stream->isReadable()) {
            $mode = $stream->isWritable() ? 'r+' : 'r';
        } elseif ($stream->isWritable()) {
            $mode = 'w';
        } else {
            throw new \InvalidArgumentException('The stream must be readable, '
                .'writable, or both.');
        }

        $resource = @fopen('guzzle://stream', $mode, false, self::createStreamContext($stream));

        if ($resource === false) {
            throw new \RuntimeException('Unable to create stream resource');
        }

        return $resource;
    }

    /**
     * Creates a stream context that can be used to open a stream as a php stream resource.
     *
     * @return resource
     */
    public static function createStreamContext(StreamInterface $stream)
    {
        return stream_context_create([
            'guzzle' => ['stream' => $stream],
        ]);
    }

    /**
     * Registers the stream wrapper if needed
     */
    public static function register(): void
    {
        if (!in_array('guzzle', stream_get_wrappers())) {
            stream_wrapper_register('guzzle', __CLASS__);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path = null): bool
    {
        $options = stream_context_get_options($this->context);
        $stream = $options['guzzle']['stream'] ?? null;

        if (!$stream instanceof StreamInterface) {
            return false;
        }

        $this->mode = $mode;
        $this->stream = $stream;

        return true;
    }

    /**
     * @return string|false
     */
    public function stream_read(int $count)
    {
        try {
            return $this->stream->read($count);
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function stream_write(string $data): int
    {
        try {
            return $this->stream->write($data);
        } catch (\RuntimeException $e) {
            return -1;
        }
    }

    public function stream_flush(): bool
    {
        return true;
    }

    /**
     * @return int|false
     */
    public function stream_tell()
    {
        try {
            return $this->stream->tell();
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function stream_eof(): bool
    {
        try {
            return $this->stream->eof();
        } catch (\RuntimeException $e) {
            return true;
        }
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        try {
            $this->stream->seek($offset, $whence);
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * @return resource|false
     */
    public function stream_cast(int $cast_as)
    {
        try {
            $stream = clone $this->stream;
            $resource = $stream->detach();
        } catch (\RuntimeException $e) {
            return false;
        }

        return $resource ?? false;
    }

    /**
     * @return array{
     *   dev: int,
     *   ino: int,
     *   mode: int,
     *   nlink: int,
     *   uid: int,
     *   gid: int,
     *   rdev: int,
     *   size: int,
     *   atime: int,
     *   mtime: int,
     *   ctime: int,
     *   blksize: int,
     *   blocks: int
     * }|false
     */
    public function stream_stat()
    {
        try {
            $size = $this->stream->getSize();
        } catch (\RuntimeException $e) {
            return false;
        }

        if ($size === null) {
            return false;
        }

        static $modeMap = [
            'r' => 33060,
            'rb' => 33060,
            'r+' => 33206,
            'w' => 33188,
            'wb' => 33188,
        ];

        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => $modeMap[$this->mode] ?? 0,
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => $size,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => 0,
            'blocks' => 0,
        ];
    }

    /**
     * @return array{
     *   dev: int,
     *   ino: int,
     *   mode: int,
     *   nlink: int,
     *   uid: int,
     *   gid: int,
     *   rdev: int,
     *   size: int,
     *   atime: int,
     *   mtime: int,
     *   ctime: int,
     *   blksize: int,
     *   blocks: int
     * }
     */
    public function url_stat(string $path, int $flags): array
    {
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 0,
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 0,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => 0,
            'blocks' => 0,
        ];
    }
}
