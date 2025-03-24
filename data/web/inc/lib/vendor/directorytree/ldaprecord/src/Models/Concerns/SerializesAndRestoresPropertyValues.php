<?php

namespace LdapRecord\Models\Concerns;

/** @mixin HasAttributes */
trait SerializesAndRestoresPropertyValues
{
    /**
     * Get the property value prepared for serialization.
     *
     * @param  string  $property
     * @param  mixed  $value
     * @return mixed
     */
    protected function getSerializedPropertyValue($property, $value)
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
     *
     * @param  string  $property
     * @param  mixed  $value
     * @return mixed
     */
    protected function getUnserializedPropertyValue($property, $value)
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
