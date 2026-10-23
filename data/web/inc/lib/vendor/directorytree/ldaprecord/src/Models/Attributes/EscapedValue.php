<?php

namespace LdapRecord\Models\Attributes;

use Stringable;

class EscapedValue implements Stringable
{
    /**
     * The value to be escaped.
     */
    protected mixed $value;

    /**
     * The characters to ignore when escaping.
     */
    protected string $ignore;

    /**
     * The escape flags.
     */
    protected int $flags;

    /**
     * Constructor.
     */
    public function __construct(mixed $value, string $ignore = '', int $flags = 0)
    {
        $this->value = $value;
        $this->ignore = $ignore;
        $this->flags = $flags;
    }

    /**
     * Un-escapes a hexadecimal string into its original string representation.
     */
    public static function unescape(string $value): string
    {
        return preg_replace_callback(
            '/\\\([0-9A-Fa-f]{2})/',
            fn ($matches) => chr(hexdec($matches[1])),
            $value
        );
    }

    /**
     * Get the escaped value.
     */
    public function __toString(): string
    {
        return $this->get();
    }

    /**
     * Get the escaped value.
     */
    public function get(): string
    {
        return ldap_escape((string) $this->value, $this->ignore, $this->flags);
    }

    /**
     * Get the raw (unescaped) value.
     */
    public function getRaw(): mixed
    {
        return $this->value;
    }

    /**
     * Set the characters to exclude from being escaped.
     */
    public function ignore(string $characters): static
    {
        $this->ignore = $characters;

        return $this;
    }

    /**
     * Prepare the value to be escaped for use in a distinguished name.
     */
    public function forDn(): static
    {
        $this->flags = LDAP_ESCAPE_DN;

        return $this;
    }

    /**
     * Prepare the value to be escaped for use in a filter.
     */
    public function forFilter(): static
    {
        $this->flags = LDAP_ESCAPE_FILTER;

        return $this;
    }

    /**
     * Prepare the value to be escaped for use in a distinguished name and filter.
     */
    public function forDnAndFilter(): static
    {
        $this->flags = LDAP_ESCAPE_FILTER + LDAP_ESCAPE_DN;

        return $this;
    }
}
