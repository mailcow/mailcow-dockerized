<?php

namespace LdapRecord\Models;

use Closure;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Query\Collection as QueryCollection;
use LdapRecord\Support\Arr;

class Collection extends QueryCollection
{
    /**
     * Get a collection of the model's distinguished names.
     */
    public function modelDns(): static
    {
        return $this->map(function (Model $model) {
            return $model->getDn();
        });
    }

    /**
     * Determine if the collection contains all the given models, or any models.
     *
     * @param  QueryCollection|Model|array|string|null  $models
     */
    public function exists(mixed $models = null): bool
    {
        $models = $this->getArrayableModels($models);

        // If any arguments were given and the result set is
        // empty, we can simply return false here. We can't
        // verify the existence of models without results.
        if (func_num_args() > 0 && empty(array_filter($models))) {
            return false;
        }

        if (! $models) {
            return parent::isNotEmpty();
        }

        foreach ($models as $model) {
            $exists = parent::contains(function (Model $related) use ($model) {
                return $this->compareModelWithRelated($model, $related);
            });

            if (! $exists) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if any of the given models are contained in the collection.
     */
    public function contains($key, $operator = null, $value = null): bool
    {
        if (func_num_args() > 1 || $key instanceof Closure) {
            return parent::contains(...func_get_args());
        }

        foreach ($this->getArrayableModels($key) as $model) {
            $exists = parent::contains(function (Model $related) use ($model) {
                return $this->compareModelWithRelated($model, $related);
            });

            if ($exists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the provided models as an array.
     */
    protected function getArrayableModels(mixed $models = null): array
    {
        if ($models instanceof QueryCollection) {
            return $models->all();
        }

        return Arr::wrap($models);
    }

    /**
     * Compare the related model with the given.
     */
    protected function compareModelWithRelated(Model|string $model, Model $related): bool
    {
        if (is_string($model)) {
            return $this->isValidDn($model)
                ? strcasecmp($related->getDn(), $model) === 0
                : strcasecmp($related->getName(), $model) === 0;
        }

        return $related->is($model);
    }

    /**
     * Determine if the given string is a valid distinguished name.
     */
    protected function isValidDn(string $dn): bool
    {
        return ! empty((new DistinguishedName($dn))->components());
    }
}
