<?php

namespace LdapRecord\Models\Relations;

use Closure;
use LdapRecord\DetectsErrors;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelNotFoundException;

class HasMany extends OneToMany
{
    use DetectsErrors;

    /**
     * The model to use for attaching / detaching.
     *
     * @var Model
     */
    protected $using;

    /**
     * The attribute key to use for attaching / detaching.
     *
     * @var string
     */
    protected $usingKey;

    /**
     * The pagination page size.
     *
     * @var int
     */
    protected $pageSize = 1000;

    /**
     * The exceptions to bypass for each relation operation.
     *
     * @var array
     */
    protected $bypass = [
        'attach' => [
            'Already exists', 'Type or value exists',
        ],
        'detach' => [
            'No such attribute', 'Server is unwilling to perform',
        ],
    ];

    /**
     * Set the model and attribute to use for attaching / detaching.
     *
     * @param  Model  $using
     * @param  string  $usingKey
     * @return $this
     */
    public function using(Model $using, $usingKey)
    {
        $this->using = $using;
        $this->usingKey = $usingKey;

        return $this;
    }

    /**
     * Set the pagination page size of the relation query.
     *
     * @param  int  $pageSize
     * @return $this
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = $pageSize;

        return $this;
    }

    /**
     * Paginate the relation using the given page size.
     *
     * @param  int  $pageSize
     * @return Collection
     */
    public function paginate($pageSize = 1000)
    {
        return $this->paginateOnceUsing($pageSize);
    }

    /**
     * Paginate the relation using the page size once.
     *
     * @param  int  $pageSize
     * @return Collection
     */
    protected function paginateOnceUsing($pageSize)
    {
        $size = $this->pageSize;

        $result = $this->setPageSize($pageSize)->get();

        $this->pageSize = $size;

        return $result;
    }

    /**
     * Execute a callback over each result while chunking.
     *
     * @param  Closure  $callback
     * @param  int  $pageSize
     * @return bool
     */
    public function each(Closure $callback, $pageSize = 1000)
    {
        $this->chunk($pageSize, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Chunk the relation results using the given callback.
     *
     * @param  int  $pageSize
     * @param  Closure  $callback
     * @param  array  $loaded
     * @return bool
     */
    public function chunk($pageSize, Closure $callback)
    {
        return $this->chunkRelation($pageSize, $callback);
    }

    /**
     * Execute the callback over chunks of relation results.
     *
     * @param  int  $pageSize
     * @param  Closure  $callback
     * @param  array  $loaded
     * @return bool
     */
    protected function chunkRelation($pageSize, Closure $callback, $loaded = [])
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
        });
    }

    /**
     * Get the relationships results.
     *
     * @return Collection
     */
    public function getRelationResults()
    {
        return $this->transformResults(
            $this->getRelationQuery()->paginate($this->pageSize)
        );
    }

    /**
     * Get the prepared relationship query.
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    public function getRelationQuery()
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

    /**
     * Attach a model to the relation.
     *
     * @param  Model|string  $model
     * @return Model|string|false
     */
    public function attach($model)
    {
        return $this->attemptFailableOperation(
            $this->buildAttachCallback($model),
            $this->bypass['attach'],
            $model
        );
    }

    /**
     * Build the attach callback.
     *
     * @param  Model|string  $model
     * @return \Closure
     */
    protected function buildAttachCallback($model)
    {
        return function () use ($model) {
            $foreign = $this->getAttachableForeignValue($model);

            if ($this->using) {
                return $this->using->createAttribute($this->usingKey, $foreign);
            }

            if (! $model instanceof Model) {
                $model = $this->getForeignModelByValueOrFail($model);
            }

            return $model->createAttribute($this->relationKey, $foreign);
        };
    }

    /**
     * Attach a collection of models to the parent instance.
     *
     * @param  iterable  $models
     * @return iterable
     */
    public function attachMany($models)
    {
        foreach ($models as $model) {
            $this->attach($model);
        }

        return $models;
    }

    /**
     * Detach the model from the relation.
     *
     * @param  Model|string  $model
     * @return Model|string|false
     */
    public function detach($model)
    {
        return $this->attemptFailableOperation(
            $this->buildDetachCallback($model),
            $this->bypass['detach'],
            $model
        );
    }

    /**
     * Detach the model or delete the parent if the relation is empty.
     *
     * @param  Model|string  $model
     * @return void
     */
    public function detachOrDeleteParent($model)
    {
        $count = $this->onceWithoutMerging(function () {
            return $this->count();
        });

        if ($count <= 1) {
            return $this->getParent()->delete();
        }

        return $this->detach($model);
    }

    /**
     * Build the detach callback.
     *
     * @param  Model|string  $model
     * @return \Closure
     */
    protected function buildDetachCallback($model)
    {
        return function () use ($model) {
            $foreign = $this->getAttachableForeignValue($model);

            if ($this->using) {
                return $this->using->deleteAttribute([$this->usingKey => $foreign]);
            }

            if (! $model instanceof Model) {
                $model = $this->getForeignModelByValueOrFail($model);
            }

            return $model->deleteAttribute([$this->relationKey => $foreign]);
        };
    }

    /**
     * Get the attachable foreign value from the model.
     *
     * @param  Model|string  $model
     * @return string
     */
    protected function getAttachableForeignValue($model)
    {
        if ($model instanceof Model) {
            return $this->using
                ? $this->getForeignValueFromModel($model)
                : $this->getParentForeignValue();
        }

        return $this->using ? $model : $this->getParentForeignValue();
    }

    /**
     * Get the foreign model by the given value, or fail.
     *
     * @param  string  $model
     * @return Model
     *
     * @throws ModelNotFoundException
     */
    protected function getForeignModelByValueOrFail($model)
    {
        if (! is_null($model = $this->getForeignModelByValue($model))) {
            return $model;
        }

        throw ModelNotFoundException::forQuery(
            $this->query->getUnescapedQuery(),
            $this->query->getDn()
        );
    }

    /**
     * Attempt a failable operation and return the value if successful.
     *
     * If a bypassable exception is encountered, the value will be returned.
     *
     * @param  callable  $operation
     * @param  string|array  $bypass
     * @param  mixed  $value
     * @return mixed
     *
     * @throws LdapRecordException
     */
    protected function attemptFailableOperation($operation, $bypass, $value)
    {
        try {
            $operation();

            return $value;
        } catch (LdapRecordException $e) {
            if ($this->errorContainsMessage($e->getMessage(), $bypass)) {
                return $value;
            }

            throw $e;
        }
    }

    /**
     * Detach all relation models.
     *
     * @return Collection
     */
    public function detachAll()
    {
        return $this->onceWithoutMerging(function () {
            return $this->get()->each(function (Model $model) {
                $this->detach($model);
            });
        });
    }

    /**
     * Detach all relation models or delete the model if its relation is empty.
     *
     * @return Collection
     */
    public function detachAllOrDelete()
    {
        return $this->onceWithoutMerging(function () {
            return $this->get()->each(function (Model $model) {
                $relation = $model->getRelation($this->relationName);

                if ($relation && $relation->count() >= 1) {
                    $model->delete();
                } else {
                    $this->detach($model);
                }
            });
        });
    }
}
