<?php

namespace LdapRecord\Models\Concerns;

use ReflectionClass;
use ReflectionProperty;

trait SerializesProperties
{
    use SerializesAndRestoresPropertyValues;

    /**
     * Prepare the attributes for serialization.
     */
    public function __sleep(): array
    {
        $properties = (new ReflectionClass($this))->getProperties();

        foreach ($properties as $property) {
            $property->setValue($this, $this->getSerializedPropertyValue(
                $property->getName(),
                $this->getPropertyValue($property)
            ));
        }

        return array_values(array_filter(
            array_map(fn ($p) => $p->isStatic() ? null : $p->getName(), $properties)
        ));
    }

    /**
     * Restore the attributes after serialization.
     */
    public function __wakeup(): void
    {
        foreach ((new ReflectionClass($this))->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setValue($this, $this->getUnserializedPropertyValue(
                $property->getName(),
                $this->getPropertyValue($property)
            ));
        }
    }

    /**
     * Prepare the model for serialization.
     */
    public function __serialize(): array
    {
        $values = [];

        $properties = (new ReflectionClass($this))->getProperties();

        $class = get_class($this);

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);

            if (! $property->isInitialized($this)) {
                continue;
            }

            $name = $property->getName();

            if ($property->isPrivate()) {
                $name = "\0{$class}\0{$name}";
            } elseif ($property->isProtected()) {
                $name = "\0*\0{$name}";
            }

            $values[$name] = $this->getSerializedPropertyValue(
                $property->getName(),
                $this->getPropertyValue($property)
            );
        }

        return $values;
    }

    /**
     * Restore the model after serialization.
     */
    public function __unserialize(array $values): void
    {
        $properties = (new ReflectionClass($this))->getProperties();

        $class = get_class($this);

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();

            if ($property->isPrivate()) {
                $name = "\0{$class}\0{$name}";
            } elseif ($property->isProtected()) {
                $name = "\0*\0{$name}";
            }

            if (! array_key_exists($name, $values)) {
                continue;
            }

            $property->setAccessible(true);

            $property->setValue(
                $this,
                $this->getUnserializedPropertyValue($property->getName(), $values[$name])
            );
        }
    }

    /**
     * Get the property value for the given property.
     */
    protected function getPropertyValue(ReflectionProperty $property): mixed
    {
        $property->setAccessible(true);

        return $property->getValue($this);
    }
}
