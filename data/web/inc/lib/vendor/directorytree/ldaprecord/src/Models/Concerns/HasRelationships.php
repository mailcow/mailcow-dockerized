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
     */
    public function hasOne(array|string $related, string $relationKey, string $foreignKey = 'dn'): HasOne
    {
        return new HasOne($this->newQuery(), $this, $related, $relationKey, $foreignKey);
    }

    /**
     * Returns a new has many relationship.
     */
    public function hasMany(array|string $related, string $relationKey, string $foreignKey = 'dn'): HasMany
    {
        return new HasMany($this->newQuery(), $this, $related, $relationKey, $foreignKey, $this->guessRelationshipName());
    }

    /**
     * Returns a new has many in relationship.
     */
    public function hasManyIn(array|string $related, string $relationKey, string $foreignKey = 'dn'): HasManyIn
    {
        return new HasManyIn($this->newQuery(), $this, $related, $relationKey, $foreignKey, $this->guessRelationshipName());
    }

    /**
     * Get a relationship by its name.
     */
    public function getRelation(?string $relationName = null): ?Relation
    {
        if (is_null($relationName)) {
            return null;
        }

        if (! method_exists($this, $relationName)) {
            return null;
        }

        if (! $relation = $this->{$relationName}()) {
            return null;
        }

        if (! $relation instanceof Relation) {
            return null;
        }

        return $relation;
    }

    /**
     * Get the relationships name.
     */
    protected function guessRelationshipName(): ?string
    {
        return Arr::last(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3))['function'];
    }
}
