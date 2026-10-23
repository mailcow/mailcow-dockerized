<?php
declare(strict_types = 1);

namespace DASPRiD\Enum;

use DASPRiD\Enum\Exception\ExpectationException;
use DASPRiD\Enum\Exception\IllegalArgumentException;
use IteratorAggregate;
use Serializable;
use Traversable;

/**
 * A specialized map implementation for use with enum type keys.
 *
 * All of the keys in an enum map must come from a single enum type that is specified, when the map is created. Enum
 * maps are represented internally as arrays. This representation is extremely compact and efficient.
 *
 * Enum maps are maintained in the natural order of their keys (the order in which the enum constants are declared).
 * This is reflected in the iterators returned by the collection views {@see self::getIterator()} and
 * {@see self::values()}.
 *
 * Iterators returned by the collection views are not consistent: They may or may not show the effects of modifications
 * to the map that occur while the iteration is in progress.
 */
final class EnumMap implements Serializable, IteratorAggregate
{
    /**
     * The class name of the key.
     *
     * @var string
     */
    private $keyType;

    /**
     * The type of the value.
     *
     * @var string
     */
    private $valueType;

    /**
     * @var bool
     */
    private $allowNullValues;

    /**
     * All of the constants comprising the enum, cached for performance.
     *
     * @var array<int, AbstractEnum>
     */
    private $keyUniverse;

    /**
     * Array representation of this map. The ith element is the value to which universe[i] is currently mapped, or null
     * if it isn't mapped to anything, or NullValue if it's mapped to null.
     *
     * @var array<int, mixed>
     */
    private $values;

    /**
     * @var int
     */
    private $size = 0;

    /**
     * Creates a new enum map.
     *
     * @param string $keyType the type of the keys, must extend AbstractEnum
     * @param string $valueType the type of the values
     * @param bool $allowNullValues whether to allow null values
     * @throws IllegalArgumentException when key type does not extend AbstractEnum
     */
    public function __construct(string $keyType, string $valueType, bool $allowNullValues)
    {
        if (! is_subclass_of($keyType, AbstractEnum::class)) {
            throw new IllegalArgumentException(sprintf(
                'Class %s does not extend %s',
                $keyType,
                AbstractEnum::class
            ));
        }

        $this->keyType = $keyType;
        $this->valueType = $valueType;
        $this->allowNullValues = $allowNullValues;
        $this->keyUniverse = $keyType::values();
        $this->values = array_fill(0, count($this->keyUniverse), null);
    }

    public function __serialize(): array
    {
        $values = [];

        foreach ($this->values as $ordinal => $value) {
            if (null === $value) {
                continue;
            }

            $values[$ordinal] = $this->unmaskNull($value);
        }

        return [
            'keyType' => $this->keyType,
            'valueType' => $this->valueType,
            'allowNullValues' => $this->allowNullValues,
            'values' => $values,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->unserialize(serialize($data));
    }

    /**
     * Checks whether the map types match the supplied ones.
     *
     * You should call this method when an EnumMap is passed to you and you want to ensure that it's made up of the
     * correct types.
     *
     * @throws ExpectationException when supplied key type mismatches local key type
     * @throws ExpectationException when supplied value type mismatches local value type
     * @throws ExpectationException when the supplied map allows null values, abut should not
     */
    public function expect(string $keyType, string $valueType, bool $allowNullValues) : void
    {
        if ($keyType !== $this->keyType) {
            throw new ExpectationException(sprintf(
                'Callee expected an EnumMap with key type %s, but got %s',
                $keyType,
                $this->keyType
            ));
        }

        if ($valueType !== $this->valueType) {
            throw new ExpectationException(sprintf(
                'Callee expected an EnumMap with value type %s, but got %s',
                $keyType,
                $this->keyType
            ));
        }

        if ($allowNullValues !== $this->allowNullValues) {
            throw new ExpectationException(sprintf(
                'Callee expected an EnumMap with nullable flag %s, but got %s',
                ($allowNullValues ? 'true' : 'false'),
                ($this->allowNullValues ? 'true' : 'false')
            ));
        }
    }

    /**
     * Returns the number of key-value mappings in this map.
     */
    public function size() : int
    {
        return $this->size;
    }

    /**
     * Returns true if this map maps one or more keys to the specified value.
     */
    public function containsValue($value) : bool
    {
        return in_array($this->maskNull($value), $this->values, true);
    }

    /**
     * Returns true if this map contains a mapping for the specified key.
     */
    public function containsKey(AbstractEnum $key) : bool
    {
        $this->checkKeyType($key);
        return null !== $this->values[$key->ordinal()];
    }

    /**
     * Returns the value to which the specified key is mapped, or null if this map contains no mapping for the key.
     *
     * More formally, if this map contains a mapping from a key to a value, then this method returns the value;
     * otherwise it returns null (there can be at most one such mapping).
     *
     * A return value of null does not necessarily indicate that the map contains no mapping for the key; it's also
     * possible that hte map explicitly maps the key to null. The {@see self::containsKey()} operation may be used to
     * distinguish these two cases.
     *
     * @return mixed
     */
    public function get(AbstractEnum $key)
    {
        $this->checkKeyType($key);
        return $this->unmaskNull($this->values[$key->ordinal()]);
    }

    /**
     * Associates the specified value with the specified key in this map.
     *
     * If the map previously contained a mapping for this key, the old value is replaced.
     *
     * @return mixed the previous value associated with the specified key, or null if there was no mapping for the key.
     *               (a null return can also indicate that the map previously associated null with the specified key.)
     * @throws IllegalArgumentException when the passed values does not match the internal value type
     */
    public function put(AbstractEnum $key, $value)
    {
        $this->checkKeyType($key);

        if (! $this->isValidValue($value)) {
            throw new IllegalArgumentException(sprintf('Value is not of type %s', $this->valueType));
        }

        $index = $key->ordinal();
        $oldValue = $this->values[$index];
        $this->values[$index] = $this->maskNull($value);

        if (null === $oldValue) {
            ++$this->size;
        }

        return $this->unmaskNull($oldValue);
    }

    /**
     * Removes the mapping for this key frm this map if present.
     *
     * @return mixed the previous value associated with the specified key, or null if there was no mapping for the key.
     *               (a null return can also indicate that the map previously associated null with the specified key.)
     */
    public function remove(AbstractEnum $key)
    {
        $this->checkKeyType($key);

        $index = $key->ordinal();
        $oldValue = $this->values[$index];
        $this->values[$index] = null;

        if (null !== $oldValue) {
            --$this->size;
        }

        return $this->unmaskNull($oldValue);
    }

    /**
     * Removes all mappings from this map.
     */
    public function clear() : void
    {
        $this->values = array_fill(0, count($this->keyUniverse), null);
        $this->size = 0;
    }

    /**
     * Compares the specified map with this map for quality.
     *
     * Returns true if the two maps represent the same mappings.
     */
    public function equals(self $other) : bool
    {
        if ($this === $other) {
            return true;
        }

        if ($this->size !== $other->size) {
            return false;
        }

        return $this->values === $other->values;
    }

    /**
     * Returns the values contained in this map.
     *
     * The array will contain the values in the order their corresponding keys appear in the map, which is their natural
     * order (the order in which the num constants are declared).
     */
    public function values() : array
    {
        return array_values(array_map(function ($value) {
            return $this->unmaskNull($value);
        }, array_filter($this->values, function ($value) : bool {
            return null !== $value;
        })));
    }

    public function serialize() : string
    {
        return serialize($this->__serialize());
    }

    public function unserialize($serialized) : void
    {
        $data = unserialize($serialized);
        $this->__construct($data['keyType'], $data['valueType'], $data['allowNullValues']);

        foreach ($this->keyUniverse as $key) {
            if (array_key_exists($key->ordinal(), $data['values'])) {
                $this->put($key, $data['values'][$key->ordinal()]);
            }
        }
    }

    public function getIterator() : Traversable
    {
        foreach ($this->keyUniverse as $key) {
            if (null === $this->values[$key->ordinal()]) {
                continue;
            }

            yield $key => $this->unmaskNull($this->values[$key->ordinal()]);
        }
    }

    private function maskNull($value)
    {
        if (null === $value) {
            return NullValue::instance();
        }

        return $value;
    }

    private function unmaskNull($value)
    {
        if ($value instanceof NullValue) {
            return null;
        }

        return $value;
    }

    /**
     * @throws IllegalArgumentException when the passed key does not match the internal key type
     */
    private function checkKeyType(AbstractEnum $key) : void
    {
        if (get_class($key) !== $this->keyType) {
            throw new IllegalArgumentException(sprintf(
                'Object of type %s is not the same type as %s',
                get_class($key),
                $this->keyType
            ));
        }
    }

    private function isValidValue($value) : bool
    {
        if (null === $value) {
            if ($this->allowNullValues) {
                return true;
            }

            return false;
        }

        switch ($this->valueType) {
            case 'mixed':
                return true;

            case 'bool':
            case 'boolean':
                return is_bool($value);

            case 'int':
            case 'integer':
                return is_int($value);

            case 'float':
            case 'double':
                return is_float($value);

            case 'string':
                return is_string($value);

            case 'object':
                return is_object($value);

            case 'array':
                return is_array($value);
        }

        return $value instanceof $this->valueType;
    }
}
