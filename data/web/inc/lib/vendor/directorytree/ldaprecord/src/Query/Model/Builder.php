<?php

namespace LdapRecord\Query\Model;

use Closure;
use DateTime;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelNotFoundException;
use LdapRecord\Models\Scope;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Query\Builder as BaseBuilder;
use UnexpectedValueException;

class Builder extends BaseBuilder
{
    /**
     * The model being queried.
     */
    protected Model $model;

    /**
     * The global scopes to be applied.
     */
    protected array $scopes = [];

    /**
     * The removed global scopes.
     */
    protected array $removedScopes = [];

    /**
     * The applied global scopes.
     */
    protected array $appliedScopes = [];

    /**
     * Dynamically handle calls into the query instance.
     */
    public function __call(string $method, array $parameters): static
    {
        if (method_exists($this->model, $scope = 'scope'.ucfirst($method))) {
            return $this->callScope([$this->model, $scope], $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Apply the given scope on the current builder instance.
     */
    protected function callScope(callable $scope, array $parameters = []): static
    {
        array_unshift($parameters, $this);

        return $scope(...array_values($parameters)) ?? $this;
    }

    /**
     * Get the attributes to select on the search.
     */
    public function getSelects(): array
    {
        // Here we will ensure the models GUID attribute is always
        // selected. In some LDAP directories, the attribute is
        // virtual and must be requested for specifically.
        return array_values(array_unique(
            array_merge([$this->model->getGuidKey()], parent::getSelects())
        ));
    }

    /**
     * Set the model instance for the model being queried.
     */
    public function setModel(Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Get the model being queried for.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get a new model query builder instance.
     */
    public function newInstance(?string $baseDn = null): BaseBuilder
    {
        return parent::newInstance($baseDn)->model($this->model);
    }

    /**
     * {@inheritDoc}
     */
    public function first(array|string $columns = ['*']): ?Model
    {
        return parent::first($columns);
    }

    /**
     * {@inheritDoc}
     */
    public function firstOrFail(array|string $columns = ['*']): Model
    {
        return parent::firstOrFail($columns);
    }

    /**
     * {@inheritDoc}
     */
    public function sole(array|string $columns = ['*']): Model
    {
        return parent::sole($columns);
    }

    /**
     * {@inheritDoc}
     */
    public function find(array|string $dn, array|string $columns = ['*']): Model|Collection|null
    {
        return $this->afterScopes(fn () => parent::find($dn, $columns));
    }

    /**
     * {@inheritDoc}
     */
    public function findOrFail(string $dn, array|string $columns = ['*']): Model
    {
        return parent::findOrFail($dn, $columns);
    }

    /**
     * {@inheritDoc}
     */
    public function findByOrFail(string $attribute, string $value, array|string $columns = ['*']): Model
    {
        return parent::findByOrFail($attribute, $value, $columns);
    }

    /**
     * {@inheritDoc}
     */
    public function findBy(string $attribute, string $value, array|string $columns = ['*']): ?Model
    {
        return parent::findBy($attribute, $value, $columns);
    }

    /**
     * {@inheritDoc}
     */
    public function findMany(array|string $dns, array|string $columns = ['*']): Collection
    {
        return parent::findMany($dns, $columns);
    }

    /**
     * {@inheritDoc}
     */
    public function findManyBy(string $attribute, array $values = [], array|string $columns = ['*']): Collection
    {
        return parent::findManyBy($attribute, $values, $columns);
    }

    /**
     * Finds a record using ambiguous name resolution.
     */
    public function findByAnr(array|string $value, array|string $columns = ['*']): Model|Collection|null
    {
        if (is_array($value)) {
            return $this->findManyByAnr($value, $columns);
        }

        // If the model is not compatible with ANR filters,
        // we must construct an equivalent filter that
        // the current LDAP server does support.
        if (! $this->modelIsCompatibleWithAnr()) {
            return $this->prepareAnrEquivalentQuery($value)->first($columns);
        }

        return $this->findBy('anr', $value, $columns);
    }

    /**
     * Determine if the current model is compatible with ANR filters.
     */
    protected function modelIsCompatibleWithAnr(): bool
    {
        return $this->model instanceof ActiveDirectory;
    }

    /**
     * Finds a record using ambiguous name resolution.
     *
     * If a record is not found, an exception is thrown.
     *
     * @throws ModelNotFoundException
     */
    public function findByAnrOrFail(string $value, array|string $columns = ['*']): Model
    {
        if (! $entry = $this->findByAnr($value, $columns)) {
            $this->throwNotFoundException($this->getUnescapedQuery(), $this->dn);
        }

        return $entry;
    }

    /**
     * Throws a not found exception.
     *
     * @throws ModelNotFoundException
     */
    protected function throwNotFoundException(string $query, ?string $dn = null): void
    {
        throw ModelNotFoundException::forQuery($query, $dn);
    }

    /**
     * Finds multiple records using ambiguous name resolution.
     */
    public function findManyByAnr(array $values = [], array|string $columns = ['*']): Collection
    {
        $this->select($columns);

        if (! $this->modelIsCompatibleWithAnr()) {
            foreach ($values as $value) {
                $this->prepareAnrEquivalentQuery($value);
            }

            return $this->get($columns);
        }

        return $this->findManyBy('anr', $values);
    }

    /**
     * Creates an ANR equivalent query for LDAP distributions that do not support ANR.
     */
    protected function prepareAnrEquivalentQuery(string $value): static
    {
        return $this->orFilter(function (BaseBuilder $query) use ($value) {
            foreach ($this->model->getAnrAttributes() as $attribute) {
                $query->whereEquals($attribute, $value);
            }
        });
    }

    /**
     * Finds a record by its string GUID.
     */
    public function findByGuid(string $guid, array|string $columns = ['*']): ?Model
    {
        try {
            return $this->findByGuidOrFail($guid, $columns);
        } catch (ModelNotFoundException $e) {
            return null;
        }
    }

    /**
     * Finds a record by its string GUID or throw an exception.
     *
     * @throws ModelNotFoundException
     */
    public function findByGuidOrFail(string $guid, array|string $columns = ['*']): Model
    {
        if ($this->model instanceof ActiveDirectory) {
            $guid = (new Guid($guid))->getEncodedHex();
        }

        return $this->whereRaw([
            $this->model->getGuidKey() => $guid,
        ])->firstOrFail($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery(): string
    {
        return $this->afterScopes(fn () => parent::getQuery());
    }

    /**
     * Apply the query scopes and execute the callback.
     */
    protected function afterScopes(Closure $callback): mixed
    {
        $this->applyScopes();

        return $callback();
    }

    /**
     * Apply the global query scopes.
     */
    public function applyScopes(): static
    {
        if (! $this->scopes) {
            return $this;
        }

        foreach ($this->scopes as $identifier => $scope) {
            if (isset($this->appliedScopes[$identifier])) {
                continue;
            }

            $scope instanceof Scope
                ? $scope->apply($this, $this->getModel())
                : $scope($this);

            $this->appliedScopes[$identifier] = $scope;
        }

        return $this;
    }

    /**
     * Register a new global scope.
     */
    public function withGlobalScope(string $identifier, Scope|Closure $scope): static
    {
        $this->scopes[$identifier] = $scope;

        return $this;
    }

    /**
     * Remove a registered global scope.
     */
    public function withoutGlobalScope(Scope|string $scope): static
    {
        if (! is_string($scope)) {
            $scope = get_class($scope);
        }

        unset($this->scopes[$scope]);

        $this->removedScopes[] = $scope;

        return $this;
    }

    /**
     * Remove all or passed registered global scopes.
     */
    public function withoutGlobalScopes(?array $scopes = null): static
    {
        if (! is_array($scopes)) {
            $scopes = array_keys($this->scopes);
        }

        foreach ($scopes as $scope) {
            $this->withoutGlobalScope($scope);
        }

        return $this;
    }

    /**
     * Get an array of global scopes that were removed from the query.
     */
    public function removedScopes(): array
    {
        return $this->removedScopes;
    }

    /**
     * Get an array of the global scopes that were applied to the query.
     */
    public function appliedScopes(): array
    {
        return $this->appliedScopes;
    }

    /**
     * Processes and converts the given LDAP results into models.
     */
    protected function process(array $results): Collection
    {
        return $this->model->hydrate(parent::process($results));
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareWhereValue(string $field, mixed $value = null, $raw = false): string
    {
        if ($value instanceof DateTime) {
            $field = $this->model->normalizeAttributeKey($field);

            if (! $this->model->isDateAttribute($field)) {
                throw new UnexpectedValueException(
                    "Cannot convert field [$field] to an LDAP timestamp. You must add this field as a model date."
                    .' Refer to https://ldaprecord.com/docs/core/v3/model-mutators/#date-mutators'
                );
            }

            $value = $this->model->fromDateTime($value, $this->model->getDates()[$field]);
        }

        return parent::prepareWhereValue($field, $value, $raw);
    }
}
