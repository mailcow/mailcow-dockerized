<?php

namespace LdapRecord\Models\Attributes;

use LdapRecord\EscapesValues;
use LdapRecord\Support\Arr;
use Stringable;

class DistinguishedNameBuilder implements Stringable
{
    use EscapesValues;

    /**
     * The components of the DN.
     */
    protected array $components = [];

    /**
     * Whether to output the DN in reverse.
     */
    protected bool $reverse = false;

    /**
     * Constructor.
     */
    public function __construct($dn = null)
    {
        $this->components = array_map(
            fn ($rdn) => DistinguishedName::explodeRdn($rdn),
            DistinguishedName::make($dn)->components()
        );
    }

    /**
     * Forward missing method calls onto the Distinguished Name object.
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->get()->{$method}(...$args);
    }

    /**
     * Get the distinguished name value.
     */
    public function __toString(): string
    {
        return (string) $this->get();
    }

    /**
     * Prepend an RDN onto the DN.
     */
    public function prepend(array|string $attribute, ?string $value = null): static
    {
        array_unshift(
            $this->components,
            ...$this->componentize($attribute, $value)
        );

        return $this;
    }

    /**
     * Append an RDN onto the DN.
     */
    public function append(array|string $attribute, ?string $value = null): static
    {
        array_push(
            $this->components,
            ...$this->componentize($attribute, $value)
        );

        return $this;
    }

    /**
     * Componentize the attribute and value.
     */
    protected function componentize(array|string $attribute, ?string $value = null): array
    {
        // Here we will make the assumption that an array of
        // RDN's have been given if the value is null, and
        // attempt to break them into their components.
        if (is_null($value)) {
            $attributes = Arr::wrap($attribute);

            $components = array_map([$this, 'makeComponentizedArray'], $attributes);
        } else {
            $components = [[$attribute, $value]];
        }

        return array_map(function ($component) {
            [$attribute, $value] = $component;

            return $this->makeAppendableComponent($attribute, $value);
        }, $components);
    }

    /**
     * Make a componentized array by exploding the value if it's a string.
     */
    protected function makeComponentizedArray(array|string $value): array
    {
        return is_array($value) ? $value : DistinguishedName::explodeRdn($value);
    }

    /**
     * Make an appendable component array from the attribute and value.
     */
    protected function makeAppendableComponent(string|array $attribute, ?string $value = null): array
    {
        return [trim($attribute), $this->escape(trim($value))->forDn()];
    }

    /**
     * Pop an RDN off of the end of the DN.
     */
    public function pop(int $amount = 1, ?array &$removed = null): static
    {
        $removed = array_map(
            fn ($component) => DistinguishedName::makeRdn($component),
            array_splice($this->components, -$amount, $amount)
        );

        return $this;
    }

    /**
     * Shift an RDN off of the beginning of the DN.
     */
    public function shift(int $amount = 1, ?array &$removed = null): static
    {
        $removed = array_map(
            fn ($component) => DistinguishedName::makeRdn($component),
            array_splice($this->components, 0, $amount)
        );

        return $this;
    }

    /**
     * Whether to output the DN in reverse.
     */
    public function reverse(): static
    {
        $this->reverse = true;

        return $this;
    }

    /**
     * Get the components of the DN.
     */
    public function components(?string $type = null): array
    {
        return is_null($type)
            ? $this->components
            : $this->componentsOfType($type);
    }

    /**
     * Get the components of a particular type.
     */
    protected function componentsOfType(string $type): array
    {
        $components = array_filter($this->components, fn ($component) => (
            ([$name] = $component) && strtolower($name) === strtolower($type)
        ));

        return array_values($components);
    }

    /**
     * Get the fully qualified DN.
     */
    public function get(): DistinguishedName
    {
        return new DistinguishedName($this->build());
    }

    /**
     * Build the distinguished name from the components.
     */
    protected function build(): string
    {
        $components = $this->reverse
            ? array_reverse($this->components)
            : $this->components;

        return implode(',', array_map(
            fn ($component) => DistinguishedName::makeRdn($component),
            $components
        ));
    }
}
