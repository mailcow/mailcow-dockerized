<?php

namespace LdapRecord\Models\Concerns;

/** @mixin HasAttributes */
trait SerializesAndRestoresPropertyValues
{
    /**
     * Get the property value prepared for serialization.
     */
    protected function getSerializedPropertyValue(string $property, mixed $value): mixed
    {
        if ($property === 'original') {
            return $this->originalToArray();
        }

        if ($property === 'attributes') {
            return $this->attributesToArray();
        }

        return $value;
    }

    /**
     * Get the unserialized property value after deserialization.
     */
    protected function getUnserializedPropertyValue(string $property, mixed $value): mixed
    {
        if ($property === 'original') {
            return $this->arrayToOriginal($value);
        }

        if ($property === 'attributes') {
            return $this->arrayToAttributes($value);
        }

        return $value;
    }
}
