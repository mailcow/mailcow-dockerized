<?php

namespace LdapRecord\Models\Relations;

use Closure;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;

/**
 * @method bool exists($models = null) Determine if the relation contains all the given models, or any models
 * @method bool contains($models) Determine if any of the given models are contained in the relation
 * @method bool count() Retrieve the "count" result of the query.
 */
abstract class Relation
{
    /**
     * The underlying LDAP query.
     */
    protected Builder $query;

    /**
     * The parent model instance.
     */
    protected Model $parent;

    /**
     * The related model class names.
     */
    protected array $related;

    /**
     * The relation key.
     */
    protected string $relationKey;

    /**
     * The foreign key.
     */
    protected string $foreignKey;

    /**
     * The default relation model.
     */
    protected string $default = Entry::class;

    /**
     * The callback to use for resolving relation models.
     */
    protected static ?Closure $modelResolver = null;

    /**
     * The methods that should be passed along to a relation collection.
     */
    protected array $passthru = ['count', 'exists', 'contains'];

    /**
     * Constructor.
     */
    public function __construct(Builder $query, Model $parent, array|string $related, string $relationKey, string $foreignKey)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = (array) $related;
        $this->relationKey = $relationKey;
        $this->foreignKey = $foreignKey;

        $this->initRelation();
    }

    /**
     * Handle dynamic method calls to the relationship.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (in_array($method, $this->passthru)) {
            return $this->get('objectclass')->$method(...$parameters);
        }

        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    /**
     * Set the callback to use for resolving models from relation results.
     */
    public static function resolveModelsUsing(?Closure $callback = null): void
    {
        static::$modelResolver = $callback;
    }

    /**
     * Only return objects matching the related model's object classes.
     */
    public function onlyRelated(): static
    {
        $relations = [];

        foreach ($this->related as $related) {
            $relations[$related] = $related::$objectClasses;
        }

        $relations = array_filter($relations);

        if (empty($relations)) {
            return $this;
        }

        $this->query->andFilter(function (Builder $query) use ($relations) {
            foreach ($relations as $relation => $objectClasses) {
                $query->whereIn('objectclass', $objectClasses);
            }
        });

        return $this;
    }

    /**
     * Get the results of the relationship.
     */
    abstract public function getResults(): Collection;

    /**
     * Execute the relationship query.
     */
    public function get(array|string $columns = ['*']): Collection
    {
        return $this->getResultsWithColumns($columns);
    }

    /**
     * Get the results of the relationship while selecting the given columns.
     *
     * If the query columns are empty, the given columns are applied.
     */
    protected function getResultsWithColumns(array|string $columns): Collection
    {
        if (is_null($this->query->columns)) {
            $this->query->select($columns);
        }

        return $this->getResults();
    }

    /**
     * Get the first result of the relationship.
     */
    public function first(array|string $columns = ['*']): ?Model
    {
        return $this->get($columns)->first();
    }

    /**
     * Prepare the relation query.
     */
    public function initRelation(): static
    {
        $this->query
            ->clearFilters()
            ->withoutGlobalScopes()
            ->setModel($this->getNewDefaultModel());

        return $this;
    }

    /**
     * Set the underlying query for the relation.
     */
    public function setQuery(Builder $query): static
    {
        $this->query = $query;

        $this->initRelation();

        return $this;
    }

    /**
     * Get the underlying query for the relation.
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get the parent model of the relation.
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Get the relation attribute key.
     */
    public function getRelationKey(): string
    {
        return $this->relationKey;
    }

    /**
     * Get the related model classes for the relation.
     */
    public function getRelated(): array
    {
        return $this->related;
    }

    /**
     * Get the relation foreign attribute key.
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the class name of the default model.
     */
    public function getDefaultModel(): string
    {
        return $this->default;
    }

    /**
     * Get a new instance of the default model on the relation.
     */
    public function getNewDefaultModel(): Model
    {
        $model = new $this->default;

        $model->setConnection($this->parent->getConnectionName());

        return $model;
    }

    /**
     * Get the foreign model by the given value.
     */
    protected function getForeignModelByValue(string $value): ?Model
    {
        return $this->foreignKeyIsDistinguishedName()
            ? $this->query->clearFilters()->find($value)
            : $this->query->clearFilters()->findBy($this->foreignKey, $value);
    }

    /**
     * Get the escaped foreign key value for use in an LDAP filter from the model.
     */
    protected function getEscapedForeignValueFromModel(Model $model): string
    {
        return $this->query->escape(
            $this->getForeignValueFromModel($model)
        )->forDnAndFilter();
    }

    /**
     * Get the relation parents foreign value.
     */
    protected function getParentForeignValue(): ?string
    {
        return $this->getForeignValueFromModel($this->parent);
    }

    /**
     * Get the foreign key value from the model.
     */
    protected function getForeignValueFromModel(Model $model): ?string
    {
        return $this->foreignKeyIsDistinguishedName()
            ? $model->getDn()
            : $this->getFirstAttributeValue($model, $this->foreignKey);
    }

    /**
     * Get the first attribute value from the model.
     */
    protected function getFirstAttributeValue(Model $model, string $attribute): mixed
    {
        return $model->getFirstAttribute($attribute);
    }

    /**
     * Transforms the results by converting the models into their related.
     */
    protected function transformResults(Collection $results): Collection
    {
        return $results->transform(
            fn (Model $entry) => $entry->morphInto($this->related, static::$modelResolver)
        );
    }

    /**
     * Determine if the foreign key is a distinguished name.
     */
    protected function foreignKeyIsDistinguishedName(): bool
    {
        return in_array($this->foreignKey, ['dn', 'distinguishedname']);
    }
}
