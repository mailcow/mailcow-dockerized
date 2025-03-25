<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Models\Relations\HasManyIn;
use LdapRecord\Models\Relations\HasOne;
use LdapRecord\Models\Relations\Relation;
use LdapRecord\Support\Arr;

trait HasRelationships
{
    /**
     * Returns a new has one relationship.
     *
     * @param  mixed  $related
     * @param  string  $relationKey
     * @param  string  $foreignKey
     * @return HasOne
     */
    public function hasOne($related, $relationKey, $foreignKey = 'dn')
    {
        return new HasOne($this->newQuery(), $this, $related, $relationKey, $foreignKey);
    }

    /**
     * Returns a new has many relationship.
     *
     * @param  mixed  $related
     * @param  string  $relationKey
     * @param  string  $foreignKey
     * @return HasMany
     */
    public function hasMany($related, $relationKey, $foreignKey = 'dn')
    {
        return new HasMany($this->newQuery(), $this, $related, $relationKey, $foreignKey, $this->guessRelationshipName());
    }

    /**
     * Returns a new has many in relationship.
     *
     * @param  mixed  $related
     * @param  string  $relationKey
     * @param  string  $foreignKey
     * @return HasManyIn
     */
    public function hasManyIn($related, $relationKey, $foreignKey = 'dn')
    {
        return new HasManyIn($this->newQuery(), $this, $related, $relationKey, $foreignKey, $this->guessRelationshipName());
    }

    /**
     * Get a relationship by its name.
     *
     * @param  string  $relationName
     * @return Relation|null
     */
    public function getRelation($relationName)
    {
        if (! method_exists($this, $relationName)) {
            return;
        }

        if (! $relation = $this->{$relationName}()) {
            return;
        }

        if (! $relation instanceof Relation) {
            return;
        }

        return $relation;
    }

    /**
     * Get the relationships name.
     *
     * @return string|null
     */
    protected function guessRelationshipName()
    {
        return Arr::last(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3))['function'];
    }
}
