<?php

namespace LdapRecord\Models\Attributes;

use LdapRecord\EscapesValues;
use LdapRecord\Support\Arr;
use Stringable;

class DistinguishedName implements Stringable
{
    use EscapesValues;

    /**
     * The underlying raw distinguished name value.
     */
    protected string $value;

    /**
     * Constructor.
     */
    public function __construct(?string $value = null)
    {
        $this->value = trim((string) $value);
    }

    /**
     * Get the distinguished name value.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Alias of the "build" method.
     */
    public static function of(?string $value = null): DistinguishedNameBuilder
    {
        return static::build($value);
    }

    /**
     * Get a new DN builder object from the given DN.
     */
    public static function build(?string $value = null): DistinguishedNameBuilder
    {
        return new DistinguishedNameBuilder($value);
    }

    /**
     * Make a new distinguished name instance.
     */
    public static function make(?string $value = null): static
    {
        return new static($value);
    }

    /**
     * Determine if the given value is a valid distinguished name.
     */
    public static function isValid(?string $value = null): bool
    {
        return ! static::make($value)->isEmpty();
    }

    /**
     * Explode a distinguished name into relative distinguished names.
     */
    public static function explode(string $dn): array
    {
        $components = ldap_explode_dn($dn, (int) $withoutAttributes = false);

        if (! is_array($components)) {
            return [];
        }

        if (! array_key_exists('count', $components)) {
            return [];
        }

        unset($components['count']);

        return $components;
    }

    /**
     * Explode the RDN into an attribute and value.
     */
    public static function explodeRdn(string $rdn): array
    {
        return explode('=', $rdn, $limit = 2);
    }

    /**
     * Implode the component attribute and value into an RDN.
     */
    public static function makeRdn(array $component): string
    {
        return implode('=', $component);
    }

    /**
     * Get the underlying value.
     */
    public function get(): ?string
    {
        return $this->value;
    }

    /**
     * Set the underlying value.
     */
    public function set(?string $value = null): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the distinguished name values without attributes.
     */
    public function values(): array
    {
        $values = [];

        foreach ($this->multi() as [, $value]) {
            $values[] = EscapedValue::unescape($value);
        }

        return $values;
    }

    /**
     * Get the distinguished name attributes without values.
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->multi() as [$attribute]) {
            $attributes[] = $attribute;
        }

        return $attributes;
    }

    /**
     * Get the distinguished name components with attributes.
     */
    public function components(): array
    {
        $components = [];

        foreach ($this->multi() as [$attribute, $value]) {
            // When a distinguished name is exploded, the values are automatically
            // escaped. This cannot be opted out of. Here we will unescape
            // the attribute value, then re-escape it to its original
            // representation from the server using the "dn" flag.
            $value = $this->escape(EscapedValue::unescape($value))->forDn();

            $components[] = static::makeRdn([$attribute, $value]);
        }

        return $components;
    }

    /**
     * Convert the distinguished name into an associative array.
     */
    public function assoc(): array
    {
        $map = [];

        foreach ($this->multi() as [$attribute, $value]) {
            $attribute = $this->normalize($attribute);

            array_key_exists($attribute, $map)
                ? $map[$attribute][] = $value
                : $map[$attribute] = [$value];
        }

        return $map;
    }

    /**
     * Split the RDNs into a multi-dimensional array.
     */
    public function multi(): array
    {
        return array_map(fn ($rdn) => static::explodeRdn($rdn), $this->rdns());
    }

    /**
     * Split the distinguished name into an array of unescaped RDN's.
     */
    public function rdns(): array
    {
        return static::explode($this->value);
    }

    /**
     * Get the first RDNs value.
     */
    public function name(): ?string
    {
        return Arr::first($this->values());
    }

    /**
     * Get the first RDNs attribute.
     */
    public function head(): ?string
    {
        return Arr::first($this->attributes());
    }

    /**
     * Get the relative distinguished name.
     */
    public function relative(): ?string
    {
        return Arr::first($this->components());
    }

    /**
     * Alias of relative().
     *
     * Get the first RDN from the distinguished name.
     */
    public function first(): ?string
    {
        return $this->relative();
    }

    /**
     * Get the parent distinguished name.
     */
    public function parent(): ?string
    {
        $components = $this->components();

        array_shift($components);

        return implode(',', $components) ?: null;
    }

    /**
     * Determine if the distinguished name is empty.
     */
    public function isEmpty(): bool
    {
        return empty(
            array_filter(
                array_map('trim', $this->values())
            )
        );
    }

    /**
     * Determine if the distinguished name is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Determine if the current distinguished name is a parent of the given child.
     */
    public function isParentOf(self $child): bool
    {
        return $child->isChildOf($this);
    }

    /**
     * Determine if the current distinguished name is a child of the given parent.
     */
    public function isChildOf(self $parent): bool
    {
        if (! $this->isComparable($this->parent(), $parent->get())) {
            return false;
        }

        return $this->normalize($this->parent()) === $this->normalize($parent->get());
    }

    /**
     * Determine if the current distinguished name is an ancestor of the descendant.
     */
    public function isAncestorOf(self $descendant): bool
    {
        return $descendant->isDescendantOf($this);
    }

    /**
     * Determine if the current distinguished name is a descendant of the ancestor.
     */
    public function isDescendantOf(self $ancestor): bool
    {
        if (! $this->isComparable($this->parent(), $ancestor->get())) {
            return false;
        }

        return str_ends_with(
            $this->normalize($this->parent()),
            $this->normalize($ancestor->get())
        );
    }

    /**
     * Determine if the current distinguished name is a sibling of the given distinguished name.
     */
    public function isSiblingOf(self $sibling): bool
    {
        if (! $this->isComparable($this->parent(), $sibling->parent())) {
            return false;
        }

        return $this->normalize($this->parent()) === $this->normalize($sibling->parent());
    }

    /**
     * Determine if the distinguished names are comparable.
     */
    protected function isComparable(?string $first, ?string $second): bool
    {
        return static::make($first)->isNotEmpty() && static::make($second)->isNotEmpty();
    }

    /**
     * Normalize the string value.
     */
    protected function normalize(string $value): string
    {
        return strtolower($value);
    }
}
