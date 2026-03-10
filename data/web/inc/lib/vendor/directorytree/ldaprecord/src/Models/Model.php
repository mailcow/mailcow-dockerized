<?php

namespace LdapRecord\Models;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\EscapesValues;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Query\Builder as BaseBuilder;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Support\Arr;
use RuntimeException;
use Stringable;
use UnexpectedValueException;

/** @mixin Builder */
abstract class Model implements Arrayable, ArrayAccess, JsonSerializable, Stringable
{
    use Concerns\HasAttributes;
    use Concerns\HasEvents;
    use Concerns\HasGlobalScopes;
    use Concerns\HasRelationships;
    use Concerns\HasScopes;
    use Concerns\HidesAttributes;
    use Concerns\SerializesProperties;
    use EscapesValues;

    /**
     * Indicates if the model exists in the directory.
     */
    public bool $exists = false;

    /**
     * Indicates whether the model was created during the current request lifecycle.
     */
    public bool $wasRecentlyCreated = false;

    /**
     * Indicates whether the model was renamed during the current request lifecycle.
     */
    public bool $wasRecentlyRenamed = false;

    /**
     * The models distinguished name.
     */
    protected ?string $dn = null;

    /**
     * The base DN of where the model should be created in.
     */
    protected ?string $in = null;

    /**
     * The object classes of the model.
     */
    public static array $objectClasses = [];

    /**
     * The connection container instance.
     */
    protected static ?Container $container = null;

    /**
     * The connection name for the model.
     */
    protected ?string $connection = null;

    /**
     * The attribute key containing the models object GUID.
     */
    protected string $guidKey = 'objectguid';

    /**
     * The array of the model's modifications.
     */
    protected array $modifications = [];

    /**
     * The array of booted models.
     */
    protected static array $booted = [];

    /**
     * The array of global scopes on the model.
     */
    protected static array $globalScopes = [];

    /**
     * The morph model cache containing object classes and their corresponding models.
     */
    protected static array $morphCache = [];

    /**
     * Constructor.
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();

        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     */
    protected function bootIfNotBooted(): void
    {
        if (! isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            static::boot();
        }
    }

    /**
     * The "boot" method of the model.
     */
    protected static function boot(): void
    {
        //
    }

    /**
     * Clear the list of booted models, so they will be re-booted.
     */
    public static function clearBootedModels(): void
    {
        static::$booted = [];

        static::$globalScopes = [];
    }

    /**
     * Handle dynamic method calls into the model.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }

        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Get the models distinguished name.
     */
    public function getDn(): ?string
    {
        return $this->dn;
    }

    /**
     * Set the model's distinguished name.
     */
    public function setDn(?string $dn = null): static
    {
        $this->dn = $dn;

        return $this;
    }

    /**
     * A mutator for setting the model's distinguished name.
     */
    public function setDnAttribute(string $dn): static
    {
        return $this->setRawAttribute('dn', $dn)->setDn($dn);
    }

    /**
     * A mutator for setting the model's distinguished name.
     */
    public function setDistinguishedNameAttribute(string $dn): static
    {
        return $this->setRawAttribute('distinguishedname', $dn)->setDn($dn);
    }

    /**
     * Get the connection for the model.
     */
    public function getConnection(): Connection
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     */
    public function setConnection(?string $name = null): static
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Make a new model instance.
     */
    public static function make(array $attributes = []): static
    {
        return new static($attributes);
    }

    /**
     * Begin querying the model on a given connection.
     */
    public static function on(?string $connection = null): Builder
    {
        $instance = new static;

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Get all the models from the directory.
     */
    public static function all(array|string $attributes = ['*']): array|Collection
    {
        return static::query()->select($attributes)->paginate();
    }

    /**
     * Get the RootDSE (AD schema) record from the directory.
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     */
    public static function getRootDse(?string $connection = null): Model
    {
        /** @var Model $model */
        $model = static::getRootDseModel();

        return $model::on($connection ?? (new $model)->getConnectionName())
            ->in()
            ->read()
            ->whereHas('objectclass')
            ->firstOrFail();
    }

    /**
     * Get the root DSE model.
     *
     * @return class-string<Model>
     */
    protected static function getRootDseModel(): string
    {
        $instance = new static;

        return match (true) {
            $instance instanceof Types\ActiveDirectory => ActiveDirectory\Entry::class,
            $instance instanceof Types\DirectoryServer => DirectoryServer\Entry::class,
            $instance instanceof Types\OpenLDAP => OpenLDAP\Entry::class,
            $instance instanceof Types\FreeIPA => FreeIPA\Entry::class,
            default => Entry::class,
        };
    }

    /**
     * Begin querying the model.
     */
    public static function query(): Builder
    {
        return (new static)->newQuery();
    }

    /**
     * Get a new query for builder filtered by the current models object classes.
     */
    public function newQuery(): Builder
    {
        return $this->registerModelScopes(
            $this->newQueryWithoutScopes()
        );
    }

    /**
     * Get a new query builder that doesn't have any global scopes.
     */
    public function newQueryWithoutScopes(): Builder
    {
        return static::resolveConnection(
            $this->getConnectionName()
        )->query()->model($this);
    }

    /**
     * Create a new query builder.
     */
    public function newQueryBuilder(Connection $connection): Builder
    {
        return new Builder($connection);
    }

    /**
     * Create a new model instance.
     */
    public function newInstance(array $attributes = []): static
    {
        return (new static($attributes))->setConnection($this->getConnectionName());
    }

    /**
     * Resolve a connection instance.
     */
    public static function resolveConnection(?string $connection = null): Connection
    {
        return static::getConnectionContainer()->getConnection($connection);
    }

    /**
     * Get the connection container.
     */
    public static function getConnectionContainer(): Container
    {
        return static::$container ?? static::getDefaultConnectionContainer();
    }

    /**
     * Get the default singleton container instance.
     */
    public static function getDefaultConnectionContainer(): Container
    {
        return Container::getInstance();
    }

    /**
     * Set the connection container.
     */
    public static function setConnectionContainer(Container $container): void
    {
        static::$container = $container;
    }

    /**
     * Unset the connection container.
     */
    public static function unsetConnectionContainer(): void
    {
        static::$container = null;
    }

    /**
     * Register the query scopes for this builder instance.
     */
    public function registerModelScopes(Builder $builder): Builder
    {
        $this->applyObjectClassScopes($builder);

        $this->registerGlobalScopes($builder);

        return $builder;
    }

    /**
     * Register the global model scopes.
     */
    public function registerGlobalScopes(Builder $builder): Builder
    {
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }

        return $builder;
    }

    /**
     * Apply the model object class scopes to the given builder instance.
     */
    public function applyObjectClassScopes(Builder $query): void
    {
        foreach (static::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }
    }

    /**
     * Get the models distinguished name when the model is converted to a string.
     */
    public function __toString(): string
    {
        return (string) $this->getDn();
    }

    /**
     * Returns a new batch modification.
     */
    public function newBatchModification(?string $attribute = null, ?int $type = null, array $values = []): BatchModification
    {
        return new BatchModification($attribute, $type, $values);
    }

    /**
     * Returns a new collection with the specified items.
     */
    public function newCollection(mixed $items = []): Collection
    {
        return new Collection($items);
    }

    /**
     * Dynamically retrieve attributes on the object.
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the object.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given offset exists.
     */
    #[\ReturnTypeWillChange]
    public function offsetExists(mixed $offset): bool
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     */
    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value at the given offset.
     */
    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value at the given offset.
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Determine if an attribute exists on the model.
     */
    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     */
    public function __unset(string $key): void
    {
        $this->offsetUnset($key);
    }

    /**
     * Convert the model to its JSON encodeable array form.
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model's attributes into JSON encodeable values.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the attributes for JSON serialization.
     */
    protected function convertAttributesForJson(array $attributes = []): array
    {
        // If the model has a GUID set, we need to convert it to its
        // string format, due to it being in binary. Otherwise,
        // we will receive a JSON serialization exception.
        if (isset($attributes[$this->guidKey])) {
            $attributes[$this->guidKey] = [$this->getConvertedGuid(
                Arr::first($attributes[$this->guidKey])
            )];
        }

        return $attributes;
    }

    /**
     * Convert the attributes from JSON serialization.
     */
    protected function convertAttributesFromJson(array $attributes = []): array
    {
        // Here we are converting the model's GUID and SID attributes
        // back to their original values from serialization, so that
        // their original value may be used and compared against.
        if (isset($attributes[$this->guidKey])) {
            $attributes[$this->guidKey] = [$this->getBinaryGuid(
                Arr::first($attributes[$this->guidKey])
            )];
        }

        return $attributes;
    }

    /**
     * Reload a fresh model instance from the directory.
     */
    public function fresh(): static|false
    {
        if (! $this->exists) {
            return false;
        }

        return $this->newQuery()->find($this->dn);
    }

    /**
     * Determine if two models have the same distinguished name and belong to the same connection.
     */
    public function is(?Model $model = null): bool
    {
        return ! is_null($model)
            && ! empty($this->dn)
            && ! empty($model->getDn())
            && $this->dn == $model->getDn()
            && $this->getConnectionName() == $model->getConnectionName();
    }

    /**
     * Determine if two models are not the same.
     */
    public function isNot(?Model $model = null): bool
    {
        return ! $this->is($model);
    }

    /**
     * Hydrate a new collection of models from search results.
     */
    public function hydrate(array $records): Collection
    {
        return $this->newCollection($records)->transform(function ($attributes) {
            if ($attributes instanceof static) {
                return $attributes;
            }

            return static::newInstance()->setRawAttributes($attributes);
        });
    }

    /**
     * Morph the model into a one of matching models using their object classes.
     */
    public function morphInto(array $models, ?callable $resolver = null): Model
    {
        if (class_exists($model = $this->determineMorphModel($this, $models, $resolver))) {
            return $this->convert(new $model);
        }

        return $this;
    }

    /**
     * Morph the model into a one of matching models or throw an exception.
     */
    public function morphIntoOrFail(array $models, ?callable $resolver = null): Model
    {
        $model = $this->morphInto($models, $resolver);

        if ($model instanceof $this) {
            throw new RuntimeException(
                'The model could not be morphed into any of the given models.'
            );
        }

        return $model;
    }

    /**
     * Determine the model to morph into from the given models.
     *
     * @return class-string|bool
     */
    protected function determineMorphModel(Model $model, array $models, ?callable $resolver = null): string|bool
    {
        $morphModelMap = [];

        foreach ($models as $modelClass) {
            $morphModelMap[$modelClass] = static::$morphCache[$modelClass] ??= $this->normalizeObjectClasses(
                $modelClass::$objectClasses
            );
        }

        $objectClasses = $this->normalizeObjectClasses(
            $model->getObjectClasses()
        );

        $resolver ??= function (array $objectClasses, array $morphModelMap) {
            return array_search($objectClasses, $morphModelMap);
        };

        return $resolver($objectClasses, $morphModelMap);
    }

    /**
     * Sort and normalize the object classes.
     */
    protected function normalizeObjectClasses(array $classes): array
    {
        sort($classes);

        return array_map('strtolower', $classes);
    }

    /**
     * Converts the current model into the given model.
     */
    public function convert(self $into): Model
    {
        $into->setDn($this->getDn());
        $into->setConnection($this->getConnectionName());

        $this->exists
            ? $into->setRawAttributes($this->getAttributes())
            : $into->fill($this->getAttributes());

        return $into;
    }

    /**
     * Refreshes the current models attributes with the directory values.
     */
    public function refresh(): bool
    {
        if ($model = $this->fresh()) {
            $this->setRawAttributes($model->getAttributes());

            return true;
        }

        return false;
    }

    /**
     * Get the model's batch modifications to be processed.
     */
    public function getModifications(): array
    {
        $builtModifications = [];

        foreach ($this->buildModificationsFromDirty() as $modification) {
            $builtModifications[] = $modification->get();
        }

        return array_merge($this->modifications, $builtModifications);
    }

    /**
     * Set the models batch modifications.
     */
    public function setModifications(array $modifications = []): static
    {
        $this->modifications = [];

        foreach ($modifications as $modification) {
            $this->addModification($modification);
        }

        return $this;
    }

    /**
     * Adds a batch modification to the model.
     *
     * @throws InvalidArgumentException
     */
    public function addModification(BatchModification|array $mod = []): static
    {
        if ($mod instanceof BatchModification) {
            $mod = $mod->get();
        }

        if ($this->isValidModification($mod)) {
            $this->modifications[] = $mod;

            return $this;
        }

        throw new InvalidArgumentException(
            "The batch modification array does not include the mandatory 'attrib' or 'modtype' keys."
        );
    }

    /**
     * Get the model's guid attribute key name.
     */
    public function getGuidKey(): string
    {
        return $this->guidKey;
    }

    /**
     * Get the model's ANR attributes for querying when incompatible with ANR.
     */
    public function getAnrAttributes(): array
    {
        return ['cn', 'sn', 'uid', 'name', 'mail', 'givenname', 'displayname'];
    }

    /**
     * Get the name of the model, or the given DN.
     */
    public function getName(?string $dn = null): ?string
    {
        return $this->newDn($dn ?? $this->dn)->name();
    }

    /**
     * Get the head attribute of the model, or the given DN.
     */
    public function getHead(?string $dn = null): ?string
    {
        return $this->newDn($dn ?? $this->dn)->head();
    }

    /**
     * Get the RDN of the model, of the given DN.
     */
    public function getRdn(?string $dn = null): ?string
    {
        return $this->newDn($dn ?? $this->dn)->relative();
    }

    /**
     * Get the parent distinguished name of the model, or the given DN.
     */
    public function getParentDn(?string $dn = null): ?string
    {
        return $this->newDn($dn ?? $this->dn)->parent();
    }

    /**
     * Create a new distinguished name.
     */
    public function newDn(?string $dn = null): DistinguishedName
    {
        if (! is_null($dn) && str_contains($dn, BaseBuilder::BASE_DN_PLACEHOLDER)) {
            $dn = $this->newQuery()->substituteBaseDn($dn);
        }

        return new DistinguishedName($dn);
    }

    /**
     * Get the model's object GUID key.
     */
    public function getObjectGuidKey(): string
    {
        return $this->guidKey;
    }

    /**
     * Get the model's binary object GUID.
     *
     * @see https://msdn.microsoft.com/en-us/library/ms679021(v=vs.85).aspx
     */
    public function getObjectGuid(): ?string
    {
        return $this->getFirstAttribute($this->guidKey);
    }

    /**
     * Get the model's object classes.
     */
    public function getObjectClasses(): array
    {
        return $this->getAttribute('objectclass', static::$objectClasses);
    }

    /**
     * Get the model's string GUID.
     */
    public function getConvertedGuid(?string $guid = null): ?string
    {
        try {
            return $this->newObjectGuid(
                (string) ($guid ?? $this->getObjectGuid())
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Get the model's binary GUID.
     */
    public function getBinaryGuid(?string $guid = null): ?string
    {
        try {
            return $this->newObjectGuid(
                $guid ?? $this->getObjectGuid()
            )->getBinary();
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Make a new object Guid instance.
     */
    protected function newObjectGuid(string $value): Guid
    {
        return new Guid($value);
    }

    /**
     * Determine if the current model is a direct descendant of the given.
     */
    public function isChildOf(Model|string|null $parent = null): bool
    {
        return $this->newDn($this->getDn())->isChildOf(
            $this->newDn((string) $parent)
        );
    }

    /**
     * Determine if the current model is a sibling of the given.
     */
    public function isSiblingOf(Model|string|null $model = null): bool
    {
        return $this->newDn($this->getDn())->isSiblingOf(
            $this->newDn((string) $model)
        );
    }

    /**
     * Determine if the current model is a direct ascendant of the given.
     */
    public function isParentOf(Model|string|null $child = null): bool
    {
        return $this->newDn($this->getDn())->isParentOf(
            $this->newDn((string) $child)
        );
    }

    /**
     * Determine if the current model is a descendant of the given.
     */
    public function isDescendantOf(Model|string|null $model = null): bool
    {
        return $this->dnIsInside($this->getDn(), $model);
    }

    /**
     * Determine if the current model is a ancestor of the given.
     */
    public function isAncestorOf(Model|string|null $model = null): bool
    {
        return $this->dnIsInside($model, $this->getDn());
    }

    /**
     * Determine if the DN is inside the parent DN.
     */
    protected function dnIsInside(Model|string|null $dn = null, Model|string|null $parentDn = null): bool
    {
        return $this->newDn((string) $dn)->isDescendantOf(
            $this->newDn($parentDn)
        );
    }

    /**
     * Set the base DN of where the model should be created in.
     */
    public function inside(Model|string $dn): static
    {
        $this->in = $dn instanceof self ? $dn->getDn() : $dn;

        return $this;
    }

    /**
     * Save the model to the directory without raising any events.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public function saveQuietly(array $attributes = []): void
    {
        static::withoutEvents(function () use ($attributes) {
            $this->save($attributes);
        });
    }

    /**
     * Save the model to the directory.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public function save(array $attributes = []): void
    {
        $this->fill($attributes);

        $this->dispatch('saving');

        $this->exists ? $this->performUpdate() : $this->performInsert();

        $this->dispatch('saved');

        $this->modifications = [];

        $this->in = null;
    }

    /**
     * Inserts the model into the directory.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    protected function performInsert(): void
    {
        // Here we will populate the models object classes if it
        // does not already have any set. An LDAP object cannot
        // be successfully created in the server without them.
        if (! $this->hasAttribute('objectclass')) {
            $this->setAttribute('objectclass', static::$objectClasses);
        }

        $query = $this->newQuery();

        // If the model does not currently have a distinguished
        // name, we will attempt to generate one automatically
        // using the current query builder's DN as the base.
        if (empty($this->getDn())) {
            $this->setDn($this->getCreatableDn());
        }

        $this->dispatch('creating');

        // Some PHP versions prevent non-numerically indexed arrays
        // from being sent to the server. To resolve this, we will
        // convert the attributes to numerically indexed arrays.
        $attributes = array_map('array_values', array_filter($this->getAttributes()));

        // Here we perform the insert of new object in the directory,
        // but filter out any empty attributes before sending them
        // to the server. LDAP servers will throw an exception if
        // attributes have been given empty or null values.
        $this->dn = $query->insertAndGetDn($this->getDn(), $attributes);

        $this->attributes = $attributes;

        $this->dispatch('created');

        $this->syncOriginal();

        $this->exists = true;

        $this->wasRecentlyCreated = true;
    }

    /**
     * Updates the model in the directory.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    protected function performUpdate(): void
    {
        if (! count($modifications = $this->getModifications())) {
            return;
        }

        $this->dispatch('updating');

        $this->newQuery()->update($this->dn, $modifications);

        $this->dispatch('updated');

        $this->syncChanges();

        $this->syncOriginal();
    }

    /**
     * Create the model in the directory.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public static function create(array $attributes = []): static
    {
        $instance = new static($attributes);

        $instance->save();

        return $instance;
    }

    /**
     * Add an attribute on the model with the given value.
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function addAttribute(string $attribute, mixed $value): void
    {
        $this->assertExists();

        $this->dispatch(['saving', 'updating']);

        $this->newQuery()->add($this->dn, [$attribute => (array) $value]);

        $this->addAttributeValue($attribute, $value);

        $this->dispatch(['updated', 'saved']);
    }

    /**
     * Update the model.
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function update(array $attributes = []): void
    {
        $this->assertExists();

        $this->save($attributes);
    }

    /**
     * Update the model attribute with the specified value.
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function replaceAttribute(string $attribute, mixed $value): void
    {
        $this->assertExists();

        $this->dispatch(['saving', 'updating']);

        $this->newQuery()->replace($this->dn, [$attribute => (array) $value]);

        $this->addAttributeValue($attribute, $value);

        $this->dispatch(['updated', 'saved']);
    }

    /**
     * Destroy the models for the given distinguished names.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public static function destroy(mixed $dns, bool $recursive = false): int
    {
        $count = 0;

        $instance = new static;

        if ($dns instanceof Collection) {
            $dns = $dns->modelDns()->toArray();
        }

        // Here we are iterating through each distinguished name and locating
        // the associated model. While it's more resource intensive, we must
        // do this in case of leaf nodes being given alongside any parent
        // node, ensuring they can be deleted inside of the directory.
        foreach ((array) $dns as $dn) {
            if (! $model = $instance->find($dn)) {
                continue;
            }

            $model->delete($recursive);

            $count++;
        }

        return $count;
    }

    /**
     * Delete the model from the directory.
     *
     * Throws a ModelNotFoundException if the current model does
     * not exist or does not contain a distinguished name.
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function delete(bool $recursive = false): void
    {
        $this->assertExists();

        $this->dispatch('deleting');

        if ($recursive) {
            $this->deleteLeafNodes();
        }

        $this->newQuery()->delete($this->dn);

        // If the deletion is successful, we will mark the model
        // as non-existing, and then fire the deleted event so
        // developers can hook in and run further operations.
        $this->exists = false;

        $this->dispatch('deleted');
    }

    /**
     * Deletes leaf nodes that are attached to the model.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    protected function deleteLeafNodes(): void
    {
        $this->newQueryWithoutScopes()
            ->in($this->dn)
            ->list()
            ->each(function (Model $model) {
                $model->delete(recursive: true);
            });
    }

    /**
     * Remove an attribute on the model.
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function removeAttribute(string $attribute, mixed $value = null): void
    {
        $this->removeAttributes([$attribute => $value]);
    }

    /**
     * Remove an attribute on the model.
     *
     * Remove specific values in attributes:
     *
     *     ["memberuid" => "jdoe"]
     *
     * Remove an entire attribute:
     *
     *     ["memberuid" => []]
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function removeAttributes(array|string $attributes): void
    {
        $this->assertExists();

        $attributes = $this->makeDeletableAttributes($attributes);

        $this->dispatch(['saving', 'updating']);

        $this->newQuery()->remove($this->dn, $attributes);

        foreach ($attributes as $attribute => $value) {
            // If the attribute value is empty, we can assume the
            // attribute was completely deleted from the model.
            // We will pull the attribute out and continue on.
            if (empty($value)) {
                unset($this->attributes[$attribute]);
            }
            // Otherwise, only specific attribute values have been
            // removed. We will determine which ones have been
            // removed and update the attributes value.
            elseif (Arr::exists($this->attributes, $attribute)) {
                $this->attributes[$attribute] = array_values(
                    array_diff($this->attributes[$attribute], (array) $value)
                );
            }
        }

        $this->dispatch(['updated', 'saved']);

        $this->syncOriginal();
    }

    /**
     * Make a deletable attribute array.
     */
    protected function makeDeletableAttributes(string|array $attributes): array
    {
        $delete = [];

        foreach (Arr::wrap($attributes) as $key => $value) {
            is_int($key)
                ? $delete[$value] = []
                : $delete[$key] = Arr::wrap($value);
        }

        return $delete;
    }

    /**
     * Move the model into the given new parent.
     *
     * For example: $user->move($ou);
     *
     * @throws UnexpectedValueException
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function move(Model|string $newParentDn, bool $deleteOldRdn = true): void
    {
        $this->assertExists();

        if (! $rdn = $this->getRdn()) {
            throw new UnexpectedValueException('Current model does not contain an RDN to move.');
        }

        $this->rename($rdn, $newParentDn, $deleteOldRdn);
    }

    /**
     * Rename the model to a new RDN and new parent.
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function rename(string $rdn, Model|string|null $newParentDn = null, bool $deleteOldRdn = true): void
    {
        $this->assertExists();

        if ($newParentDn instanceof self) {
            $newParentDn = $newParentDn->getDn();
        }

        if (is_null($newParentDn)) {
            $newParentDn = $this->getParentDn($this->dn);
        }

        // If the RDN we have been given is empty when parsed, we must
        // have been given a string, with no attribute. In this case,
        // we will create a new RDN using the current DN's head.
        if ($this->newDn($rdn)->isEmpty()) {
            $rdn = $this->getUpdateableRdn($rdn);
        }

        // If the RDN and the new parent DN are the same as the current,
        // we will simply return here to prevent a rename operation
        // being sent, which would fail anyway in such case.
        if (
            $rdn === $this->getRdn()
         && $newParentDn === $this->getParentDn()
        ) {
            return;
        }

        $this->dispatch('renaming', [$rdn, $newParentDn]);

        // If the model was successfully renamed, we will set
        // its new DN so any further updates to the model
        // can be performed without any issues.
        $this->dn = $this->newQuery()->renameAndGetDn($this->dn, $rdn, $newParentDn, $deleteOldRdn);

        $map = $this->newDn($this->dn)->assoc();

        // Here we'll populate the models new primary
        // RDN attribute on the model so we do not
        // have to re-synchronize with the server.
        $modelNameAttribute = key($map);

        $this->attributes[$modelNameAttribute]
            = $this->original[$modelNameAttribute]
            = [reset($map[$modelNameAttribute])];

        $this->dispatch('renamed');

        $this->wasRecentlyRenamed = true;
    }

    /**
     * Get an updatable RDN for the model.
     */
    public function getUpdateableRdn(string $name): string
    {
        return $this->getCreatableRdn($name, $this->newDn($this->dn)->head());
    }

    /**
     * Get a distinguished name that is creatable for the model.
     */
    public function getCreatableDn(?string $name = null, ?string $attribute = null): string
    {
        return implode(',', [
            $this->getCreatableRdn($name, $attribute),
            $this->in ?? $this->newQuery()->getbaseDn(),
        ]);
    }

    /**
     * Get a creatable (escaped) RDN for the model.
     */
    public function getCreatableRdn(?string $name = null, ?string $attribute = null): string
    {
        $attribute = $attribute ?? $this->getCreatableRdnAttribute();

        $name = $this->escape(
            $name ?? $this->getFirstAttribute($attribute)
        )->forDn();

        return "$attribute=$name";
    }

    /**
     * Get the creatable RDN attribute name.
     */
    protected function getCreatableRdnAttribute(): string
    {
        return 'cn';
    }

    /**
     * Determine if the given modification is valid.
     */
    protected function isValidModification(mixed $mod): bool
    {
        return Arr::accessible($mod)
            && Arr::exists($mod, BatchModification::KEY_MODTYPE)
            && Arr::exists($mod, BatchModification::KEY_ATTRIB);
    }

    /**
     * Builds the models modifications from its dirty attributes.
     *
     * @return BatchModification[]
     */
    protected function buildModificationsFromDirty(): array
    {
        $modifications = [];

        foreach ($this->getDirty() as $attribute => $values) {
            $modification = $this->newBatchModification($attribute, null, (array) $values);

            if (Arr::exists($this->original, $attribute)) {
                // If the attribute we're modifying has an original value, we will
                // give the BatchModification object its values to automatically
                // determine which type of LDAP operation we need to perform.
                $modification->setOriginal($this->original[$attribute]);
            }

            if (! $modification->build()->isValid()) {
                continue;
            }

            $modifications[] = $modification;
        }

        return $modifications;
    }

    /**
     * Throw an exception if the model does not exist.
     *
     * @throws ModelDoesNotExistException
     */
    protected function assertExists(): void
    {
        if (! $this->exists || is_null($this->dn)) {
            throw ModelDoesNotExistException::forModel($this);
        }
    }
}
