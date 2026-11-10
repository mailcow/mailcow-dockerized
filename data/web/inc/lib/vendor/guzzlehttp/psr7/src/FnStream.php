<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * Compose stream implementations based on a hash of callables.
 *
 * Allows for easy testing and extension of a provided stream without needing
 * to create a concrete class for a simple extension point.
 */
#[\AllowDynamicProperties]
final class FnStream implements StreamInterface
{
    use NonSerializableStreamTrait;

    private const SLOTS = [
        '__toString', 'close', 'detach', 'rewind',
        'getSize', 'tell', 'eof', 'isSeekable', 'seek', 'isWritable', 'write',
        'isReadable', 'read', 'getContents', 'getMetadata',
    ];

    /** @var array<string, callable> */
    private array $methods;

    private bool $detached = false;

    /**
     * @param array<string, callable> $methods Hash of method name to a callable.
     */
    public function __construct(array $methods)
    {
        $this->methods = $methods;

        // Create the callables on the class
        foreach ($methods as $name => $fn) {
            $this->{'_fn_'.$name} = $fn;
        }
    }

    /**
     * Lazily determine which methods are not implemented.
     *
     * @throws \BadMethodCallException
     */
    public function __get(string $name): void
    {
        throw new \BadMethodCallException(\sprintf('%s() is not implemented in the FnStream', DiagnosticValue::escape(str_replace('_fn_', '', $name))));
    }

    /**
     * The close method is called on the underlying stream only if possible.
     */
    public function __destruct()
    {
        if ($this->detached || !isset($this->_fn_close)) {
            return;
        }

        try {
            $this->close();
        } catch (\Throwable $e) {
            // Destructors must not surface cleanup failures.
        }
    }

    /**
     * An unserialize would allow the __destruct to run when the unserialized value goes out of scope.
     *
     * @throws \LogicException
     */
    public function __wakeup(): void
    {
        $this->methods = [];
        $this->detached = true;

        throw new \LogicException(static::class.' should never be unserialized');
    }

    public function __unserialize(array $data): void
    {
        $this->methods = [];
        $this->detached = true;

        throw new \LogicException(static::class.' should never be unserialized');
    }

    /**
     * Adds custom functionality to an underlying stream by intercepting
     * specific method calls.
     *
     * @param StreamInterface         $stream  Stream to decorate
     * @param array<string, callable> $methods Hash of method name to a callable
     */
    public static function decorate(StreamInterface $stream, array $methods): self
    {
        // If any of the required methods were not provided, then simply
        // proxy to the decorated stream.
        foreach (array_diff(self::SLOTS, array_keys($methods)) as $diff) {
            /** @var callable $callable */
            $callable = [$stream, $diff];
            $methods[$diff] = $callable;
        }

        return new self($methods);
    }

    public function __toString(): string
    {
        $this->assertAttached();

        /** @var string */
        return ($this->_fn___toString)();
    }

    public function close(): void
    {
        if ($this->detached) {
            return;
        }

        $close = $this->_fn_close;
        $this->detached = true;
        $close();
    }

    public function detach()
    {
        if ($this->detached) {
            return null;
        }

        $detach = $this->_fn_detach;
        $result = $detach();
        $this->detached = true;

        return $result;
    }

    public function getSize(): ?int
    {
        if ($this->detached) {
            return null;
        }

        return ($this->_fn_getSize)();
    }

    public function tell(): int
    {
        $this->assertAttached();

        return ($this->_fn_tell)();
    }

    public function eof(): bool
    {
        $this->assertAttached();

        return ($this->_fn_eof)();
    }

    public function isSeekable(): bool
    {
        if ($this->detached) {
            return false;
        }

        return ($this->_fn_isSeekable)();
    }

    public function rewind(): void
    {
        $this->assertAttached();

        ($this->_fn_rewind)();
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->assertAttached();

        ($this->_fn_seek)($offset, $whence);
    }

    public function isWritable(): bool
    {
        if ($this->detached) {
            return false;
        }

        return ($this->_fn_isWritable)();
    }

    public function write(string $string): int
    {
        $this->assertAttached();

        return ($this->_fn_write)($string);
    }

    public function isReadable(): bool
    {
        if ($this->detached) {
            return false;
        }

        return ($this->_fn_isReadable)();
    }

    public function read(int $length): string
    {
        $this->assertAttached();

        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }

        return ($this->_fn_read)($length);
    }

    public function getContents(): string
    {
        $this->assertAttached();

        return ($this->_fn_getContents)();
    }

    /**
     * @return mixed
     */
    public function getMetadata(?string $key = null)
    {
        if ($this->detached) {
            return $key === null ? [] : null;
        }

        return ($this->_fn_getMetadata)($key);
    }

    private function assertAttached(): void
    {
        if ($this->detached) {
            throw new \RuntimeException('Stream is detached');
        }
    }
}
