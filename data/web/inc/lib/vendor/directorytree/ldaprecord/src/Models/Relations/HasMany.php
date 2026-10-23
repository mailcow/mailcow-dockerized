<?php

namespace LdapRecord\Models\Relations;

use Closure;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;

class HasMany extends OneToMany
{
    /**
     * The pagination page size.
     */
    protected int $pageSize = 1000;

    /**
     * Set the pagination page size of the relation query.
     */
    public function setPageSize(int $pageSize): static
    {
        $this->pageSize = $pageSize;

        return $this;
    }

    /**
     * Paginate the relation using the given page size.
     */
    public function paginate(int $pageSize = 1000): Collection
    {
        return $this->paginateOnceUsing($pageSize);
    }

    /**
     * Paginate the relation using the page size once.
     */
    protected function paginateOnceUsing(int $pageSize): Collection
    {
        $size = $this->pageSize;

        $result = $this->setPageSize($pageSize)->get();

        $this->pageSize = $size;

        return $result;
    }

    /**
     * Execute a callback over each result while chunking.
     */
    public function each(Closure $callback, int $pageSize = 1000): bool
    {
        return $this->chunk($pageSize, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Chunk the relation results using the given callback.
     */
    public function chunk(int $pageSize, Closure $callback): bool
    {
        return $this->chunkRelation($pageSize, $callback);
    }

    /**
     * Execute the callback over chunks of relation results.
     */
    protected function chunkRelation(int $pageSize, Closure $callback, array $loaded = []): bool
    {
        return $this->getRelationQuery()->chunk($pageSize, function (Collection $results) use ($pageSize, $callback, $loaded) {
            $models = $this->transformResults($results)->when($this->recursive, function (Collection $models) use ($loaded) {
                return $models->reject(function (Model $model) use ($loaded) {
                    return in_array($model->getDn(), $loaded);
                });
            });

            if ($callback($models) === false) {
                return false;
            }

            $models->when($this->recursive, function (Collection $models) use ($pageSize, $callback, $loaded) {
                $models->each(function (Model $model) use ($pageSize, $callback, $loaded) {
                    if ($relation = $model->getRelation($this->relationName)) {
                        $loaded[] = $model->getDn();

                        return $relation->recursive()->chunkRelation($pageSize, $callback, $loaded);
                    }
                });
            });

            return true;
        });
    }

    /**
     * Get the relationships results.
     */
    public function getRelationResults(): Collection
    {
        return $this->transformResults(
            $this->getRelationQuery()->paginate($this->pageSize)
        );
    }

    /**
     * Get the prepared relationship query.
     */
    public function getRelationQuery(): Builder
    {
        $columns = $this->query->getSelects();

        // We need to select the proper key to be able to retrieve its
        // value from LDAP results. If we don't, we won't be able
        // to properly attach / detach models from relation
        // query results as the attribute will not exist.
        $key = $this->using ? $this->usingKey : $this->relationKey;

        // If the * character is missing from the attributes to select,
        // we will add the key to the attributes to select and also
        // validate that the key isn't already being selected
        // to prevent stacking on multiple relation calls.
        if (! in_array('*', $columns) && ! in_array($key, $columns)) {
            $this->query->addSelect($key);
        }

        return $this->query->whereRaw(
            $this->relationKey,
            '=',
            $this->getEscapedForeignValueFromModel($this->parent)
        );
    }
}
