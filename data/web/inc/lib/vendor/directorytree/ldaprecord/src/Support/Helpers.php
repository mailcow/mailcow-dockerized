<?php

namespace LdapRecord\Support;

use Closure;

class Helpers
{
    /**
     * Get the default value of the given value.
     */
    public static function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}
