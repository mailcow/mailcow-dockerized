<?php

namespace LdapRecord\Support;

use Closure;

class Helpers
{
    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @param  mixed  $args
     * @return mixed
     */
    public static function value($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}
