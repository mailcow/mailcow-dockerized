<?php

namespace LdapRecord\Models\Attributes;

use LdapRecord\EscapesValues;
use LdapRecord\Support\Arr;

class DistinguishedName
{
    use EscapesValues;

    /**
     * The underlying raw value.
     *
     * @var string
     */
    protected $value;

    /**
     * Constructor.
     *
     * @param string|null $value
     */
    public function __construct($value = null)
    {
        $this->value = trim((string) $value);
    }

    /**
     * Get the distinguished name value.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * Alias of the "build" method.
     *
     * @param string|null $value
     *
     * @return DistinguishedNameBuilder
     */
    public static function of($value = null)
    {
        return static::build($value);
    }

    /**
     * Get a new DN builder object from the given DN.
     *
     * @param string|null $value
     *
     * @return DistinguishedNameBuilder
     */
    public static function build($value = null)
    {
        return new DistinguishedNameBuilder($value);
    }

    /**
     * Make a new distinguished name instance.
     *
     * @param string|null $value
     *
     * @return static
     */
    public static function make($value = null)
    {
        return new static($value);
    }

    /**
     * Determine if the given value is a valid distinguished name.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function isValid($value)
    {
        return ! static::make($value)->isEmpty();
    }

    /**
     * Explode a distinguished name into relative distinguished names.
     *
     * @param string $dn
     *
     * @return array
     */
    public static function explode($dn)
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
     * Un-escapes a hexadecimal string into its original string representation.
     *
     * @param string $value
     *
     * @return string
     */
    public static function unescape($value)
    {
        return preg_replace_callback('/\\\([0-9A-Fa-f]{2})/', function ($matches) {
            return chr(hexdec($matches[1]));
        }, $value);
    }

    /**
     * Explode the RDN into an attribute and value.
     *
     * @param string $rdn
     *
     * @return array
     */
    public static function explodeRdn($rdn)
    {
        return explode('=', $rdn, $limit = 2);
    }

    /**
     * Implode the component attribute and value into an RDN.
     *
     * @param string $rdn
     *
     * @return string
     */
    public static function makeRdn(array $component)
    {
        return implode('=', $component);
    }

    /**
     * Get the underlying value.
     *
     * @return string|null
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * Set the underlying value.
     *
     * @param string|null $value
     *
     * @return $this
     */
    public function set($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the distinguished name values without attributes.
     *
     * @return array
     */
    public function values()
    {
        $values = [];

        foreach ($this->multi() as [, $value]) {
            $values[] = static::unescape($value);
        }

        return $values;
    }

    /**
     * Get the distinguished name attributes without values.
     *
     * @return array
     */
    public function attributes()
    {
        $attributes = [];

        foreach ($this->multi() as [$attribute]) {
            $attributes[] = $attribute;
        }

        return $attributes;
    }

    /**
     * Get the distinguished name components with attributes.
     *
     * @return array
     */
    public function components()
    {
        $components = [];

        foreach ($this->multi() as [$attribute, $value]) {
            // When a distinguished name is exploded, the values are automatically
            // escaped. This cannot be opted out of. Here we will unescape
            // the attribute value, then re-escape it to its original
            // representation from the server using the "dn" flag.
            $value = $this->escape(static::unescape($value))->dn();

            $components[] = static::makeRdn([$attribute, $value]);
        }

        return $components;
    }

    /**
     * Convert the distinguished name into an associative array.
     *
     * @return array
     */
    public function assoc()
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
     *
     * @return array
     */
    public function multi()
    {
        return array_map(function ($rdn) {
            return static::explodeRdn($rdn);
        }, $this->rdns());
    }

    /**
     * Split the distinguished name into an array of unescaped RDN's.
     *
     * @return array
     */
    public function rdns()
    {
        return static::explode($this->value);
    }

    /**
     * Get the first RDNs value.
     *
     * @return string|null
     */
    public function name()
    {
        return Arr::first($this->values());
    }

    /**
     * Get the first RDNs attribute.
     *
     * @return string|null
     */
    public function head()
    {
        return Arr::first($this->attributes());
    }

    /**
     * Get the relative distinguished name.
     *
     * @return string|null
     */
    public function relative()
    {
        return Arr::first($this->components());
    }

    /**
     * Alias of relative().
     *
     * Get the first RDN from the distinguished name.
     *
     * @return string|null
     */
    public function first()
    {
        return $this->relative();
    }

    /**
     * Get the parent distinguished name.
     *
     * @return string|null
     */
    public function parent()
    {
        $components = $this->components();

        array_shift($components);

        return implode(',', $components) ?: null;
    }

    /**
     * Determine if the distinguished name is empty.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty(
            array_filter($this->values())
        );
    }

    /**
     * Determine if the current distinguished name is a parent of the given child.
     *
     * @param DistinguishedName $child
     *
     * @return bool
     */
    public function isParentOf(self $child)
    {
        return $child->isChildOf($this);
    }

    /**
     * Determine if the current distinguished name is a child of the given parent.
     *
     * @param DistinguishedName $parent
     *
     * @return bool
     */
    public function isChildOf(self $parent)
    {
        if (
            empty($components = $this->components()) ||
            empty($parentComponents = $parent->components())
        ) {
            return false;
        }

        array_shift($components);

        return $this->compare($components, $parentComponents);
    }

    /**
     * Determine if the current distinguished name is an ancestor of the descendant.
     *
     * @param DistinguishedName $descendant
     *
     * @return bool
     */
    public function isAncestorOf(self $descendant)
    {
        return $descendant->isDescendantOf($this);
    }

    /**
     * Determine if the current distinguished name is a descendant of the ancestor.
     *
     * @param DistinguishedName $ancestor
     *
     * @return bool
     */
    public function isDescendantOf(self $ancestor)
    {
        if (
            empty($components = $this->components()) ||
            empty($ancestorComponents = $ancestor->components())
        ) {
            return false;
        }

        if (! $length = count($components) - count($ancestorComponents)) {
            return false;
        }

        array_splice($components, $offset = 0, $length);

        return $this->compare($components, $ancestorComponents);
    }

    /**
     * Compare whether the two distinguished name values are equal.
     *
     * @param array $values
     * @param array $other
     *
     * @return bool
     */
    protected function compare(array $values, array $other)
    {
        return $this->recase($values) == $this->recase($other);
    }

    /**
     * Recase the array values.
     *
     * @param array $values
     *
     * @return array
     */
    protected function recase(array $values)
    {
        return array_map([$this, 'normalize'], $values);
    }

    /**
     * Normalize the string value.
     *
     * @param string $value
     *
     * @return string
     */
    protected function normalize($value)
    {
        return strtolower($value);
    }
}
