<?php

namespace LdapRecord\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\MbString;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Models\DetectsResetIntegers;
use LdapRecord\Support\Arr;
use RuntimeException;

trait HasAttributes
{
    use DetectsResetIntegers;

    /**
     * The models original attributes.
     */
    protected array $original = [];

    /**
     * The models changed attributes.
     */
    protected array $changes = [];

    /**
     * The models attributes.
     */
    protected array $attributes = [];

    /**
     * The attributes that should be mutated to dates.
     */
    protected array $dates = [];

    /**
     * The attributes that should be cast to their native types.
     */
    protected array $casts = [];

    /**
     * The accessors to append to the model's array form.
     */
    protected array $appends = [];

    /**
     * The format that dates must be output to for serialization.
     */
    protected ?string $dateFormat = null;

    /**
     * The default attributes that should be mutated to dates.
     */
    protected array $defaultDates = [
        'createtimestamp' => 'ldap',
        'modifytimestamp' => 'ldap',
    ];

    /**
     * The cache of the mutated attributes for each class.
     */
    protected static array $mutatorCache = [];

    /**
     * Convert the model's original attributes to an array.
     */
    public function originalToArray(): array
    {
        return $this->encodeAttributes(
            $this->convertAttributesForJson($this->original)
        );
    }

    /**
     * Convert the model's attributes to an array.
     */
    public function attributesToArray(): array
    {
        // Here we will replace our LDAP formatted dates with
        // properly formatted ones, so dates do not need to
        // be converted manually after being returned.
        $attributes = $this->addDateAttributesToArray(
            $this->getArrayableAttributes()
        );

        $attributes = $this->addMutatedAttributesToArray(
            $attributes,
            $this->getMutatedAttributes()
        );

        // Before we go ahead and encode each value, we'll attempt
        // converting any necessary attribute values to ensure
        // they can be encoded, such as GUIDs and SIDs.
        $attributes = $this->convertAttributesForJson($attributes);

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        // Now we will go through each attribute to make sure it is
        // properly encoded. If attributes aren't in UTF-8, we will
        // encounter JSON encoding errors upon model serialization.
        return $this->encodeAttributes($attributes);
    }

    /**
     * Convert the model's serialized original attributes to their original form.
     */
    public function arrayToOriginal(array $attributes): array
    {
        $attributes = $this->decodeAttributes($attributes);

        return $this->convertAttributesFromJson($attributes);
    }

    /**
     * Convert the model's serialized attributes to their original form.
     */
    public function arrayToAttributes(array $attributes): array
    {
        $attributes = $this->restoreDateAttributesFromArray($attributes);

        $attributes = $this->decodeAttributes($attributes);

        return $this->convertAttributesFromJson($attributes);
    }

    /**
     * Add the date attributes to the attributes array.
     */
    protected function addDateAttributesToArray(array $attributes): array
    {
        foreach ($this->getDates() as $attribute => $type) {
            if (! isset($attributes[$attribute])) {
                continue;
            }

            $date = $this->asDateTime($attributes[$attribute], $type);

            $attributes[$attribute] = $date instanceof Carbon
                ? Arr::wrap($this->serializeDate($date))
                : $attributes[$attribute];
        }

        return $attributes;
    }

    /**
     * Restore the date attributes to their true value from serialized attributes.
     */
    protected function restoreDateAttributesFromArray(array $attributes): array
    {
        foreach ($this->getDates() as $attribute => $type) {
            if (! isset($attributes[$attribute])) {
                continue;
            }

            $date = $this->fromDateTime($attributes[$attribute], $type);

            $attributes[$attribute] = Arr::wrap($date);
        }

        return $attributes;
    }

    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format($this->getDateFormat());
    }

    /**
     * Recursively UTF-8 encode the given attributes.
     */
    protected function encodeAttributes($attributes): array
    {
        array_walk_recursive($attributes, function (&$value) {
            $value = $this->encodeValue($value);
        });

        return $attributes;
    }

    /**
     * Recursively UTF-8 decode the given attributes.
     */
    public function decodeAttributes(array $attributes): array
    {
        array_walk_recursive($attributes, function (&$value) {
            $value = $this->decodeValue($value);
        });

        return $attributes;
    }

    /**
     * Encode the value for serialization.
     */
    protected function encodeValue(string $value): string
    {
        // If we are able to detect the encoding, we will
        // encode only the attributes that need to be,
        // so that we do not double encode values.
        if (MbString::isLoaded() && MbString::isUtf8($value)) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * Decode the value from serialization.
     */
    protected function decodeValue(string $value): string
    {
        if (MbString::isLoaded() && ! MbString::isUtf8($value)) {
            return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }

        return $value;
    }

    /**
     * Add the mutated attributes to the attributes array.
     */
    protected function addMutatedAttributesToArray(array $attributes, array $mutatedAttributes): array
    {
        foreach ($mutatedAttributes as $key) {
            // We want to spin through all the mutated attributes for this model and call
            // the mutator for the attribute. We cache off every mutated attributes so
            // we don't have to constantly check on attributes that actually change.
            if (! Arr::exists($attributes, $key)) {
                continue;
            }

            // Next, we will call the mutator for this attribute so that we can get these
            // mutated attribute's actual values. After we finish mutating each of the
            // attributes we will return this final array of the mutated attributes.
            $attributes[$key] = $this->mutateAttributeForArray(
                $key,
                $attributes[$key]
            );
        }

        return $attributes;
    }

    /**
     * Set the model's original attributes with the model's current attributes.
     */
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Sync the changed attributes.
     */
    public function syncChanges(): static
    {
        $this->changes = $this->getDirty();

        return $this;
    }

    /**
     * Fills the entry with the supplied attributes.
     */
    public function fill(array $attributes = []): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Get the models attribute by its key.
     */
    public function getAttribute(?string $key = null, mixed $default = null): mixed
    {
        if (! $key) {
            return null;
        }

        return $this->getAttributeValue($key, $default);
    }

    /**
     * Get an attribute's value.
     */
    public function getAttributeValue(string $key, mixed $default = null): mixed
    {
        $key = $this->normalizeAttributeKey($key);
        $value = $this->getAttributeFromArray($key);

        if ($this->hasGetMutator($key)) {
            return $this->getMutatedAttributeValue($key, $value);
        }

        if ($this->isDateAttribute($key) && ! is_null($value)) {
            return $this->asDateTime(Arr::first($value), $this->getDates()[$key]);
        }

        if ($this->isCastedAttribute($key) && ! is_null($value)) {
            return $this->castAttribute($key, $value);
        }

        return is_null($value) ? $default : $value;
    }

    /**
     * Get the model's raw attribute value.
     */
    public function getRawAttribute(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->attributes, $this->normalizeAttributeKey($key), $default);
    }

    /**
     * Determine if the given attribute is a date.
     */
    public function isDateAttribute(string $key): bool
    {
        return array_key_exists($key, $this->getDates());
    }

    /**
     * Get the attributes that should be mutated to dates.
     */
    public function getDates(): array
    {
        // Since array string keys can be unique depending
        // on casing differences, we need to normalize the
        // array key case so they are merged properly.
        return array_merge(
            array_change_key_case($this->defaultDates),
            array_change_key_case($this->dates),
            array_change_key_case($this->getDateCasts()),
        );
    }

    /**
     * Get the attributes casts that should be mutated to dates.
     */
    protected function getDateCasts(): array
    {
        return array_map(function (string $cast) {
            return explode(':', $cast, 2)[1] ?? throw new RuntimeException(
                "Invalid date cast [$cast]. A date cast must be in the format 'datetime:format'."
            );
        }, array_filter($this->getCasts(), function ($cast) {
            return $this->isDateTimeCast($cast);
        }));
    }

    /**
     * Convert the given date value to an LDAP compatible value.
     *
     * @throws LdapRecordException
     */
    public function fromDateTime(mixed $value, string $type): float|int|string
    {
        return (new Timestamp($type))->fromDateTime($value);
    }

    /**
     * Convert the given LDAP date value to a Carbon instance.
     *
     * @throws LdapRecordException
     */
    public function asDateTime(mixed $value, string $type): Carbon|int|false
    {
        return (new Timestamp($type))->toDateTime($value);
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     */
    public function hasCast(string $key, array|string|null $types = null): bool
    {
        if (array_key_exists($key, $this->getCasts())) {
            return ! $types || in_array($this->getCastType($key), (array) $types, true);
        }

        return false;
    }

    /**
     * Get the attributes that should be cast to their native types.
     */
    protected function getCasts(): array
    {
        return array_change_key_case($this->casts);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     */
    protected function isJsonCastable(string $key): bool
    {
        return $this->hasCast($key, ['array', 'json', 'object', 'collection']);
    }

    /**
     * Get the type of cast for a model attribute.
     */
    protected function getCastType(string $key): string
    {
        if ($this->isDecimalCast($this->getCasts()[$key])) {
            return 'decimal';
        }

        if ($this->isDateTimeCast($this->getCasts()[$key])) {
            return 'datetime';
        }

        return trim(strtolower($this->getCasts()[$key]));
    }

    /**
     * Determine if the cast is a decimal.
     */
    protected function isDecimalCast(string $cast): bool
    {
        return strncmp($cast, 'decimal:', 8) === 0;
    }

    /**
     * Determine if the cast is a datetime.
     */
    protected function isDateTimeCast(string $cast): bool
    {
        return strncmp($cast, 'datetime:', 8) === 0;
    }

    /**
     * Determine if the given attribute must be casted.
     */
    protected function isCastedAttribute(string $key): bool
    {
        return array_key_exists($key, array_change_key_case($this->casts));
    }

    /**
     * Cast an attribute to a native PHP type.
     */
    protected function castAttribute(string $key, ?array $value): mixed
    {
        $value = $this->castRequiresArrayValue($key) ? $value : Arr::first($value);

        if (is_null($value)) {
            return $value;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $this->getCasts()[$key], 2)[1]);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return $this->asBoolean($value);
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return $this->newCollection($value);
            case 'datetime':
                return $this->asDateTime($value, explode(':', $this->getCasts()[$key], 2)[1]);
            default:
                return $value;
        }
    }

    /**
     * Determine if the cast type requires the first attribute value.
     */
    protected function castRequiresArrayValue(string $key): bool
    {
        return $this->getCastType($key) === 'collection';
    }

    /**
     * Cast the given attribute to JSON.
     */
    protected function castAttributeAsJson(string $key, mixed $value): string
    {
        $value = $this->asJson($value);

        if ($value === false) {
            $class = get_class($this);
            $message = json_last_error_msg();

            throw new Exception("Unable to encode attribute [$key] for model [$class] to JSON: $message.");
        }

        return $value;
    }

    /**
     * Cast the given attribute to an LDAP primitive type.
     */
    protected function castAttributeAsPrimitive(string $key, mixed $value): string
    {
        return match ($this->getCastType($key)) {
            'bool', 'boolean' => $this->fromBoolean($value),
            default => (string) $value,
        };
    }

    /**
     * Convert the model to its JSON representation.
     */
    public function toJson(): string
    {
        return json_encode($this);
    }

    /**
     * Encode the given value as JSON.
     */
    protected function asJson(mixed $value): string|false
    {
        return json_encode($value);
    }

    /**
     * Decode the given JSON back into an array or object.
     */
    public function fromJson(string $value, bool $asObject = false): mixed
    {
        return json_decode($value, ! $asObject);
    }

    /**
     * Decode the given float.
     */
    public function fromFloat(float $value): float
    {
        return match ((string) $value) {
            'NaN' => NAN,
            'Infinity' => INF,
            '-Infinity' => -INF,
            default => $value,
        };
    }

    /**
     * Cast the value from an LDAP boolean string to a primitive boolean.
     */
    protected function asBoolean(mixed $value): bool
    {
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => (bool) $value,
        };
    }

    /**
     * Cast the value from a primitive boolean to an LDAP boolean string.
     */
    protected function fromBoolean(mixed $value): string
    {
        if (is_string($value)) {
            $value = $this->asBoolean($value);
        }

        return $value ? 'TRUE' : 'FALSE';
    }

    /**
     * Cast a decimal value as a string.
     */
    protected function asDecimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }

    /**
     * Get an attribute array of all arrayable attributes.
     */
    protected function getArrayableAttributes(): array
    {
        return $this->getArrayableItems($this->attributes);
    }

    /**
     * Get an attribute array of all arrayable values.
     */
    protected function getArrayableItems(array $values): array
    {
        if (count($visible = $this->getVisible()) > 0) {
            $values = array_intersect_key($values, array_flip($visible));
        }

        if (count($hidden = $this->getHidden()) > 0) {
            $values = array_diff_key($values, array_flip($hidden));
        }

        return $values;
    }

    /**
     * Get all the appendable values that are arrayable.
     */
    protected function getArrayableAppends(): array
    {
        if (empty($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }

    /**
     * Get the format for date serialization.
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat ?: DateTimeInterface::ISO8601;
    }

    /**
     * Set the date format used by the model for serialization.
     */
    public function setDateFormat(string $format): static
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Get an attribute from the $attributes array.
     */
    protected function getAttributeFromArray(string $key): mixed
    {
        return $this->getNormalizedAttributes()[$key] ?? null;
    }

    /**
     * Get the attributes with their keys normalized.
     */
    protected function getNormalizedAttributes(): array
    {
        return array_change_key_case($this->attributes);
    }

    /**
     * Get the first attribute by the specified key.
     */
    public function getFirstAttribute(string $key, mixed $default = null): mixed
    {
        return Arr::first(
            Arr::wrap($this->getAttribute($key, $default)),
        );
    }

    /**
     * Returns all the model's attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set an attribute value by the specified key.
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $key = $this->normalizeAttributeKey($key);

        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        if (
            $value &&
            $this->isDateAttribute($key) &&
            ! $this->valueIsResetInteger($value)
        ) {
            $value = (string) $this->fromDateTime($value, $this->getDates()[$key]);
        }

        if ($this->isJsonCastable($key) && ! is_null($value)) {
            $value = $this->castAttributeAsJson($key, $value);
        } elseif ($this->hasCast($key) && ! is_null($value)) {
            $value = $this->castAttributeAsPrimitive($key, $value);
        }

        $this->attributes[$key] = Arr::wrap($value);

        return $this;
    }

    /**
     * Set an attribute on the model. No checking is done.
     */
    public function setRawAttribute(string $key, mixed $value): static
    {
        $key = $this->normalizeAttributeKey($key);

        $this->attributes[$key] = Arr::wrap($value);

        return $this;
    }

    /**
     * Set the models first attribute value.
     */
    public function setFirstAttribute(string $key, mixed $value): static
    {
        return $this->setAttribute($key, Arr::wrap($value));
    }

    /**
     * Add a unique value to the given attribute.
     */
    public function addAttributeValue(string $key, mixed $value): static
    {
        return $this->setRawAttribute($key, array_unique(array_merge(
            $this->getRawAttribute($key, []),
            Arr::wrap($value)
        )));
    }

    /**
     * Remove a unique value from the given attribute.
     */
    public function removeAttributeValue(string $key, mixed $value): static
    {
        $values = $this->getRawAttribute($key, []);

        foreach (Arr::wrap($value) as $value) {
            $index = array_search($value, $values);

            if ($index !== false) {
                unset($values[$index]);
            }
        }

        return $this->setRawAttribute($key, array_values($values));
    }

    /**
     * Determine if a get mutator exists for an attribute.
     */
    public function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get'.$this->getMutatorMethodName($key).'Attribute');
    }

    /**
     * Determine if a set mutator exists for an attribute.
     */
    public function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set'.$this->getMutatorMethodName($key).'Attribute');
    }

    /**
     * Set the value of an attribute using its mutator.
     */
    protected function setMutatedAttributeValue(string $key, mixed $value): static
    {
        $this->{'set'.$this->getMutatorMethodName($key).'Attribute'}($value);

        return $this;
    }

    /**
     * Get the value of an attribute using its mutator.
     */
    protected function getMutatedAttributeValue(string $key, mixed $value): mixed
    {
        return $this->{'get'.$this->getMutatorMethodName($key).'Attribute'}($value);
    }

    /**
     * Get the mutator attribute method name.
     *
     * Hyphenated attributes will use pascal cased methods.
     */
    protected function getMutatorMethodName(string $key): string
    {
        $key = ucwords(str_replace('-', ' ', $key));

        return str_replace(' ', '', $key);
    }

    /**
     * Get the value of an attribute using its mutator for array conversion.
     */
    protected function mutateAttributeForArray(string $key, mixed $value): array
    {
        return Arr::wrap(
            $this->getMutatedAttributeValue($key, $value)
        );
    }

    /**
     * Set the raw model attributes.
     *
     * Used when constructing an existing LDAP record.
     */
    public function setRawAttributes(array $attributes = []): static
    {
        // We will filter out those annoying 'count' keys
        // returned with LDAP results and lowercase all
        // root array keys to prevent any casing issues.
        $raw = array_change_key_case($this->filterRawAttributes($attributes));

        // Before setting the models attributes, we will filter
        // out the attributes that contain an integer key. LDAP
        // search results will contain integer keys that have
        // attribute names as values. We don't need these.
        $this->attributes = array_filter($raw, fn ($key) => ! is_int($key), ARRAY_FILTER_USE_KEY);

        // LDAP search results will contain the distinguished
        // name inside of the `dn` key. We will retrieve this,
        // and then set it on the model for accessibility.
        if (Arr::exists($attributes, 'dn')) {
            $this->dn = Arr::accessible($attributes['dn'])
                ? Arr::first($attributes['dn'])
                : $attributes['dn'];
        }

        $this->syncOriginal();

        // Here we will set the exists attribute to true,
        // since raw attributes are only set in the case
        // of attributes being loaded by query results.
        $this->exists = true;

        return $this;
    }

    /**
     * Filters the count key recursively from raw LDAP attributes.
     */
    public function filterRawAttributes(array $attributes = [], array $keys = ['count', 'dn']): array
    {
        foreach ($keys as $key) {
            unset($attributes[$key]);
        }

        foreach ($attributes as $key => $value) {
            $attributes[$key] = is_array($value)
                ? $this->filterRawAttributes($value, $keys)
                : $value;
        }

        return $attributes;
    }

    /**
     * Determine if the model has the given attribute.
     */
    public function hasAttribute(int|string $key): bool
    {
        return ($this->attributes[$this->normalizeAttributeKey($key)] ?? []) !== [];
    }

    /**
     * Get the number of attributes.
     */
    public function countAttributes(): int
    {
        return count($this->getAttributes());
    }

    /**
     * Get the model's original attributes.
     */
    public function getOriginal(): array
    {
        return $this->original;
    }

    /**
     * Get the model's raw original attribute values.
     */
    public function getRawOriginal(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->original, $key, $default);
    }

    /**
     * Get the attributes that have been changed since last sync.
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if ($this->isDirty($key)) {
                // We need to reset the array using array_values due to
                // LDAP requiring consecutive indices (0, 1, 2 etc.).
                // We would receive an exception otherwise.
                $dirty[$key] = array_values($value);
            }
        }

        return $dirty;
    }

    /**
     * Get the attributes that have been changed since the model was last saved.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Determine if the given attribute is dirty.
     */
    public function isDirty(string $key): bool
    {
        return ! $this->originalIsEquivalent($key);
    }

    /**
     * Determine if given attribute has remained the same.
     */
    public function isClean(string $key): bool
    {
        return ! $this->isDirty($key);
    }

    /**
     * Discard attribute changes and reset the attributes to their original state.
     */
    public function discardChanges(): static
    {
        [$this->attributes, $this->changes] = [$this->original, []];

        return $this;
    }

    /**
     * Determine if the model or any of the given attribute(s) were changed when the model was last saved.
     */
    public function wasChanged(array|string|null $attributes = null): bool
    {
        if (func_num_args() === 0) {
            return count($this->changes) > 0;
        }

        foreach ((array) $attributes as $attribute) {
            if (array_key_exists($attribute, $this->changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the accessors being appended to the models array form.
     */
    public function getAppends(): array
    {
        return $this->appends;
    }

    /**
     * Set the accessors to append to model arrays.
     */
    public function setAppends(array $appends): static
    {
        $this->appends = $appends;

        return $this;
    }

    /**
     * Return whether the accessor attribute has been appended.
     */
    public function hasAppended(string $attribute): bool
    {
        return in_array($attribute, $this->appends);
    }

    /**
     * Returns a normalized attribute key.
     */
    public function normalizeAttributeKey(string $key): string
    {
        // Since LDAP supports hyphens in attribute names,
        // we'll convert attributes being retrieved by
        // underscores into hyphens for convenience.
        return strtolower(
            str_replace('_', '-', $key)
        );
    }

    /**
     * Determine if the new and old values for a given key are equivalent.
     */
    protected function originalIsEquivalent(string $key): bool
    {
        if (! array_key_exists($key, $this->original)) {
            return false;
        }

        $current = $this->attributes[$key];
        $original = $this->original[$key];

        if ($current === $original) {
            return true;
        }

        return is_numeric($current) &&
                is_numeric($original) &&
                strcmp((string) $current, (string) $original) === 0;
    }

    /**
     * Get the mutated attributes for a given instance.
     */
    public function getMutatedAttributes(): array
    {
        $class = static::class;

        if (! isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     */
    public static function cacheMutatedAttributes(string $class): void
    {
        static::$mutatorCache[$class] = collect(static::getMutatorMethods($class))
            ->reject(fn ($match) => $match === 'First')
            ->map(fn ($match) => lcfirst($match))
            ->all();
    }

    /**
     * Get all of the attribute mutator methods.
     */
    protected static function getMutatorMethods(string $class): array
    {
        preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches);

        return $matches[1];
    }
}
