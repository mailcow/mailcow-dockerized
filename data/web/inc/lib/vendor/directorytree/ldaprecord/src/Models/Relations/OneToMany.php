<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Model;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;

abstract class OneToMany extends Relation
{
    /**
     * The relation to merge results with.
     *
     * @var OneToMany|null
     */
    protected $with;

    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected $relationName;

    /**
     * Whether to include recursive results.
     *
     * @var bool
     */
    protected $recursive = false;

    /**
     * Constructor.
     *
     * @param Builder $query
     * @param Model   $parent
     * @param string  $related
     * @param string  $relationKey
     * @param string  $foreignKey
     * @param string  $relationName
     */
    public function __construct(Builder $query, Model $parent, $related, $relationKey, $foreignKey, $relationName)
    {
        $this->relationName = $relationName;

        parent::__construct($query, $parent, $related, $relationKey, $foreignKey);
    }

    /**
     * Set the relation to load with its parent.
     *
     * @param Relation $relation
     *
     * @return $this
     */
    public function with(Relation $relation)
    {
        $this->with = $relation;

        return $this;
    }

    /**
     * Whether to include recursive results.
     *
     * @param bool $enable
     *
     * @return $this
     */
    public function recursive($enable = true)
    {
        $this->recursive = $enable;

        return $this;
    }

    /**
     * Get the immediate relationships results.
     *
     * @return Collection
     */
    abstract public function getRelationResults();

    /**
     * Get the results of the relationship.
     *
     * @return Collection
     */
    public function getResults()
    {
        $results = $this->recursive
            ? $this->getRecursiveResults()
            : $this->getRelationResults();

        return $results->merge(
            $this->getMergingRelationResults()
        );
    }

    /**
     * Execute the callback excluding the merged query result.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    protected function onceWithoutMerging($callback)
    {
        $merging = $this->with;

        $this->with = null;

        $result = $callback();

        $this->with = $merging;

        return $result;
    }

    /**
     * Get the relation name.
     *
     * @return string
     */
    public function getRelationName()
    {
        return $this->relationName;
    }

    /**
     * Get the results of the merging 'with' relation.
     *
     * @return Collection
     */
    protected function getMergingRelationResults()
    {
        return $this->with
            ? $this->with->recursive($this->recursive)->get()
            : $this->parent->newCollection();
    }

    /**
     * Get the results for the models relation recursively.
     *
     * @param string[] $loaded The distinguished names of models already loaded
     *
     * @return Collection
     */
    protected function getRecursiveResults(array $loaded = [])
    {
        $results = $this->getRelationResults()->reject(function (Model $model) use ($loaded) {
            // Here we will exclude the models that we have already
            // loaded the recursive results for so we don't run
            // into issues with circular relations in LDAP.
            return in_array($model->getDn(), $loaded);
        });

        foreach ($results as $model) {
            $loaded[] = $model->getDn();

            // Finally, we will fetch the related models relations,
            // passing along our loaded models, to ensure we do
            // not attempt fetching already loaded relations.
            $results = $results->merge(
                $this->getRecursiveRelationResults($model, $loaded)
            );
        }

        return $results;
    }

    /**
     * Get the recursive relation results for given model.
     *
     * @param Model $model
     * @param array $loaded
     *
     * @return Collection
     */
    protected function getRecursiveRelationResults(Model $model, array $loaded)
    {
        return method_exists($model, $this->relationName)
            ? $model->{$this->relationName}()->getRecursiveResults($loaded)
            : $model->newCollection();
    }
}
