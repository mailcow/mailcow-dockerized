<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Collection;

class HasManyIn extends OneToMany
{
    /**
     * Get the relationships results.
     */
    public function getRelationResults(): Collection
    {
        $results = $this->parent->newCollection();

        foreach ((array) $this->parent->getAttribute($this->relationKey) as $value) {
            if ($value && $foreign = $this->getForeignModelByValue($value)) {
                $results->push($foreign);
            }
        }

        return $this->transformResults($results);
    }
}
