<?php

namespace LdapRecord;

use LdapRecord\Models\Attributes\EscapedValue;

trait EscapesValues
{
    /**
     * Prepare a value to be escaped.
     */
    public function escape(mixed $value = null, string $ignore = '', int $flags = 0): EscapedValue
    {
        return new EscapedValue($value, $ignore, $flags);
    }
}
