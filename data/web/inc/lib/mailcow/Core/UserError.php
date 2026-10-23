<?php

declare(strict_types=1);

namespace Mailcow\Core;

use RuntimeException;

/**
 * An expected error that is shown to the user (validation failed, not found, not allowed).
 *
 * Carries a lang key of the `danger` section plus optional message arguments, e.g.
 * `throw new UserError('object_exists', [$name]);`
 * Controllers turn it into an ApiResponse message; anything else that is thrown is a real failure.
 */
class UserError extends RuntimeException
{
    public function __construct(public readonly string $key, public readonly array $args = [])
    {
        parent::__construct($key);
    }

    /** The `msg` value of a mailcow message: the lang key alone, or [key, arg1, ...] when there are arguments */
    public function msg(): string|array
    {
        return empty($this->args) ? $this->key : array_merge([$this->key], array_values($this->args));
    }
}
