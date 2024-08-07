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
use LdapRecord\Query\Model\Builder;
use LdapRecord\Support\Arr;
use UnexpectedValueException;

/** @mixin Builder */
abstract class Model implements ArrayAccess, Arrayable, JsonSerializable
{
    use EscapesValues;
    use Concerns\HasEvents;
    use Concerns\HasScopes;
    use Concerns\HasAttributes;
    use Concerns\HasGlobalScopes;
    use Concerns\HidesAttributes;
    use Concerns\HasRelationships;
    use Concerns\SerializesProperties;

    /**
     * Indicates if the model exists in the directory.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * Indicates whether the model was created during the current request lifecycle.
     *
     * @var bool
     */
    public $wasRecentlyCreated = false;

    /**
     * Indicates whether the model was renamed during the current request lifecycle.
     *
     * @var bool
     */
    public $wasRecentlyRenamed = false;

    /**
     * The models distinguished name.
     *
     * @var string|null
     */
    protected $dn;

    /**
     * The base DN of where the model should be created in.
     *
     * @var string|null
     */
    protected $in;

    /**
     * The object classes of the model.
     *
     * @var array
     */
    public static $objectClasses = [];

    /**
     * The connection container instance.
     *
     * @var Container
     */
    protected static $container;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection;

    /**
     * The attribute key that contains the models object GUID.
     *
     * @var string
     */
    protected $guidKey = 'objectguid';

    /**
     * Contains the models modifications.
     *
     * @var array
     */
    protected $modifications = [];

    /**
     * The array of global scopes on the model.
     *
     * @var array
     */
    protected static $globalScopes = [];

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = [];

    /**
     * Constructor.
     *
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();

        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if (! isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            static::boot();
        }
    }

    /**
     * The "boot" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        //
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     *
     * @return void
     */
    public static function clearBootedModels()
    {
        static::$booted = [];

        static::$globalScopes = [];
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }

        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static())->$method(...$parameters);
    }

    /**
     * Returns the models distinguished name.
     *
     * @return string|null
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * Set the models distinguished name.
     *
     * @param  string  $dn
     * @return $this
     */
    public function setDn($dn)
    {
        $this->dn = (string) $dn;

        return $this;
    }

    /**
     * A mutator for setting the models distinguished name.
     *
     * @param  string  $dn
     * @return $this
     */
    public function setDnAttribute($dn)
    {
        return $this->setRawAttribute('dn', $dn)->setDn($dn);
    }

    /**
     * A mutator for setting the models distinguished name.
     *
     * @param  string  $dn
     * @return $this
     */
    public function setDistinguishedNameAttribute($dn)
    {
        return $this->setRawAttribute('distinguishedname', $dn)->setDn($dn);
    }

    /**
     * Get the connection for the model.
     *
     * @return Connection
     */
    public function getConnection()
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     *
     * @param  string  $name
     * @return $this
     */
    public function setConnection($name)
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Make a new model instance.
     *
     * @param  array  $attributes
     * @return static
     */
    public static function make($attributes = [])
    {
        return new static($attributes);
    }

    /**
     * Begin querying the model on a given connection.
     *
     * @param  string|null  $connection
     * @return Builder
     */
    public static function on($connection = null)
    {
        $instance = new static();

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Get all the models from the directory.
     *
     * @param  array|mixed  $attributes
     * @return Collection|static[]
     */
    public static function all($attributes = ['*'])
    {
        return static::query()->select($attributes)->paginate();
    }

    /**
     * Get the RootDSE (AD schema) record from the directory.
     *
     * @param  string|null  $connection
     * @return Model
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     */
    public static function getRootDse($connection = null)
    {
        $model = static::getRootDseModel();

        return $model::on($connection ?? (new $model)->getConnectionName())
            ->in(null)
            ->read()
            ->whereHas('objectclass')
            ->firstOrFail();
    }

    /**
     * Get the root DSE model.
     *
     * @return class-string<Model>
     */
    protected static function getRootDseModel()
    {
        $instance = (new static);

        switch (true) {
            case $instance instanceof Types\ActiveDirectory:
                return ActiveDirectory\Entry::class;
            case $instance instanceof Types\DirectoryServer:
                return OpenLDAP\Entry::class;
            case $instance instanceof Types\OpenLDAP:
                return OpenLDAP\Entry::class;
            case $instance instanceof Types\FreeIPA:
                return FreeIPA\Entry::class;
            default:
                return Entry::class;
        }
    }

    /**
     * Begin querying the model.
     *
     * @return Builder
     */
    public static function query()
    {
        return (new static())->newQuery();
    }

    /**
     * Get a new query for builder filtered by the current models object classes.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return $this->registerModelScopes(
            $this->newQueryWithoutScopes()
        );
    }

    /**
     * Get a new query builder that doesn't have any global scopes.
     *
     * @return Builder
     */
    public function newQueryWithoutScopes()
    {
        return static::resolveConnection(
            $this->getConnectionName()
        )->query()->model($this);
    }

    /**
     * Create a new query builder.
     *
     * @param  Connection  $connection
     * @return Builder
     */
    public function newQueryBuilder(Connection $connection)
    {
        return new Builder($connection);
    }

    /**
     * Create a new model instance.
     *
     * @param  array  $attributes
     * @return static
     */
    public function newInstance(array $attributes = [])
    {
        return (new static($attributes))->setConnection($this->getConnectionName());
    }

    /**
     * Resolve a connection instance.
     *
     * @param  string|null  $connection
     * @return Connection
     */
    public static function resolveConnection($connection = null)
    {
        return static::getConnectionContainer()->get($connection);
    }

    /**
     * Get the connection container.
     *
     * @return Container
     */
    public static function getConnectionContainer()
    {
        return static::$container ?? static::getDefaultConnectionContainer();
    }

    /**
     * Get the default singleton container instance.
     *
     * @return Container
     */
    public static function getDefaultConnectionContainer()
    {
        return Container::getInstance();
    }

    /**
     * Set the connection container.
     *
     * @param  Container  $container
     * @return void
     */
    public static function setConnectionContainer(Container $container)
    {
        static::$container = $container;
    }

    /**
     * Unset the connection container.
     *
     * @return void
     */
    public static function unsetConnectionContainer()
    {
        static::$container = null;
    }

    /**
     * Register the query scopes for this builder instance.
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function registerModelScopes($builder)
    {
        $this->applyObjectClassScopes($builder);

        $this->registerGlobalScopes($builder);

        return $builder;
    }

    /**
     * Register the global model scopes.
     *
     * @param  Builder  $builder
     * @return Builder
     */
    public function registerGlobalScopes($builder)
    {
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }

        return $builder;
    }

    /**
     * Apply the model object class scopes to the given builder instance.
     *
     * @param  Builder  $query
     * @return void
     */
    public function applyObjectClassScopes(Builder $query)
    {
        foreach (static::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }
    }

    /**
     * Returns the models distinguished name when the model is converted to a string.
     *
     * @return null|string
     */
    public function __toString()
    {
        return $this->getDn();
    }

    /**
     * Returns a new batch modification.
     *
     * @param  string|null  $attribute
     * @param  string|int|null  $type
     * @param  array  $values
     * @return BatchModification
     */
    public function newBatchModification($attribute = null, $type = null, $values = [])
    {
        return new BatchModification($attribute, $type, $values);
    }

    /**
     * Returns a new collection with the specified items.
     *
     * @param  mixed  $items
     * @return Collection
     */
    public function newCollection($items = [])
    {
        return new Collection($items);
    }

    /**
     * Dynamically retrieve attributes on the object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the object.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function __set($key, $value)
    {
        return $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given offset exists.
     *
     * @param  string  $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param  string  $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value at the given offset.
     *
     * @param  string  $offset
     * @param  mixed  $value
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value at the given offset.
     *
     * @param  string  $offset
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * Convert the model to its JSON encodeable array form.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model's attributes into JSON encodeable values.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the attributes for JSON serialization.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function convertAttributesForJson(array $attributes = [])
    {
        // If the model has a GUID set, we need to convert it to its
        // string format, due to it being in binary. Otherwise
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
     *
     * @param  array  $attributes
     * @return array
     */
    protected function convertAttributesFromJson(array $attributes = [])
    {
        return $attributes;
    }

    /**
     * Reload a fresh model instance from the directory.
     *
     * @return static|false
     */
    public function fresh()
    {
        if (! $this->exists) {
            return false;
        }

        return $this->newQuery()->find($this->dn);
    }

    /**
     * Determine if two models have the same distinguished name and belong to the same connection.
     *
     * @param  Model|null  $model
     * @return bool
     */
    public function is($model)
    {
        return ! is_null($model)
           && $this->dn == $model->getDn()
           && $this->getConnectionName() == $model->getConnectionName();
    }

    /**
     * Determine if two models are not the same.
     *
     * @param  Model|null  $model
     * @return bool
     */
    public function isNot($model)
    {
        return ! $this->is($model);
    }

    /**
     * Hydrate a new collection of models from search results.
     *
     * @param  array  $records
     * @return Collection
     */
    public function hydrate($records)
    {
        return $this->newCollection($records)->transform(function ($attributes) {
            return $attributes instanceof static
                ? $attributes
                : static::newInstance()->setRawAttributes($attributes);
        });
    }

    /**
     * Converts the current model into the given model.
     *
     * @param  Model  $into
     * @return Model
     */
    public function convert(self $into)
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
     *
     * @return bool
     */
    public function refresh()
    {
        if ($model = $this->fresh()) {
            $this->setRawAttributes($model->getAttributes());

            return true;
        }

        return false;
    }

    /**
     * Get the model's batch modifications to be processed.
     *
     * @return array
     */
    public function getModifications()
    {
        $builtModifications = [];

        foreach ($this->buildModificationsFromDirty() as $modification) {
            $builtModifications[] = $modification->get();
        }

        return array_merge($this->modifications, $builtModifications);
    }

    /**
     * Set the models batch modifications.
     *
     * @param  array  $modifications
     * @return $this
     */
    public function setModifications(array $modifications = [])
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
     * @param  array|BatchModification  $mod
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function addModification($mod = [])
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
     *
     * @return string
     */
    public function getGuidKey()
    {
        return $this->guidKey;
    }

    /**
     * Get the model's ANR attributes for querying when incompatible with ANR.
     *
     * @return array
     */
    public function getAnrAttributes()
    {
        return ['cn', 'sn', 'uid', 'name', 'mail', 'givenname', 'displayname'];
    }

    /**
     * Get the name of the model, or the given DN.
     *
     * @param  string|null  $dn
     * @return string|null
     */
    public function getName($dn = null)
    {
        return $this->newDn($dn ?? $this->dn)->name();
    }

    /**
     * Get the head attribute of the model, or the given DN.
     *
     * @param  string|null  $dn
     * @return string|null
     */
    public function getHead($dn = null)
    {
        return $this->newDn($dn ?? $this->dn)->head();
    }

    /**
     * Get the RDN of the model, of the given DN.
     *
     * @param string|null
     * @return string|null
     */
    public function getRdn($dn = null)
    {
        return $this->newDn($dn ?? $this->dn)->relative();
    }

    /**
     * Get the parent distinguished name of the model, or the given DN.
     *
     * @param string|null
     * @return string|null
     */
    public function getParentDn($dn = null)
    {
        return $this->newDn($dn ?? $this->dn)->parent();
    }

    /**
     * Create a new Distinguished Name object.
     *
     * @param  string|null  $dn
     * @return DistinguishedName
     */
    public function newDn($dn = null)
    {
        return new DistinguishedName($dn);
    }

    /**
     * Get the model's object GUID key.
     *
     * @return string
     */
    public function getObjectGuidKey()
    {
        return $this->guidKey;
    }

    /**
     * Get the model's binary object GUID.
     *
     * @see https://msdn.microsoft.com/en-us/library/ms679021(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getObjectGuid()
    {
        return $this->getFirstAttribute($this->guidKey);
    }

    /**
     * Get the model's object classes.
     *
     * @return array
     */
    public function getObjectClasses()
    {
        return $this->getAttribute('objectclass', static::$objectClasses);
    }

    /**
     * Get the model's string GUID.
     *
     * @param  string|null  $guid
     * @return string|null
     */
    public function getConvertedGuid($guid = null)
    {
        try {
            return (string) $this->newObjectGuid(
                $guid ?? $this->getObjectGuid()
            );
        } catch (InvalidArgumentException $e) {
            return;
        }
    }

    /**
     * Get the model's binary GUID.
     *
     * @param  string|null  $guid
     * @return string|null
     */
    public function getBinaryGuid($guid = null)
    {
        try {
            return $this->newObjectGuid(
                $guid ?? $this->getObjectGuid()
            )->getBinary();
        } catch (InvalidArgumentException $e) {
            return;
        }
    }

    /**
     * Make a new object Guid instance.
     *
     * @param  string  $value
     * @return Guid
     */
    protected function newObjectGuid($value)
    {
        return new Guid($value);
    }

    /**
     * Determine if the current model is a direct descendant of the given.
     *
     * @param  static|string  $parent
     * @return bool
     */
    public function isChildOf($parent)
    {
        return $this->newDn($this->getDn())->isChildOf(
            $this->newDn((string) $parent)
        );
    }

    /**
     * Determine if the current model is a direct ascendant of the given.
     *
     * @param  static|string  $child
     * @return bool
     */
    public function isParentOf($child)
    {
        return $this->newDn($this->getDn())->isParentOf(
            $this->newDn((string) $child)
        );
    }

    /**
     * Determine if the current model is a descendant of the given.
     *
     * @param  static|string  $model
     * @return bool
     */
    public function isDescendantOf($model)
    {
        return $this->dnIsInside($this->getDn(), $model);
    }

    /**
     * Determine if the current model is a ancestor of the given.
     *
     * @param  static|string  $model
     * @return bool
     */
    public function isAncestorOf($model)
    {
        return $this->dnIsInside($model, $this->getDn());
    }

    /**
     * Determines if the DN is inside of the parent DN.
     *
     * @param  static|string  $dn
     * @param  static|string  $parentDn
     * @return bool
     */
    protected function dnIsInside($dn, $parentDn)
    {
        return $this->newDn((string) $dn)->isDescendantOf(
            $this->newDn($parentDn)
        );
    }

    /**
     * Set the base DN of where the model should be created in.
     *
     * @param  static|string  $dn
     * @return $this
     */
    public function inside($dn)
    {
        $this->in = $dn instanceof self ? $dn->getDn() : $dn;

        return $this;
    }

    /**
     * Save the model to the directory without raising any events.
     *
     * @param  array  $attributes
     * @return void
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public function saveQuietly(array $attributes = [])
    {
        static::withoutEvents(function () use ($attributes) {
            $this->save($attributes);
        });
    }

    /**
     * Save the model to the directory.
     *
     * @param  array  $attributes  The attributes to update or create for the current entry.
     * @return void
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public function save(array $attributes = [])
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
     * @return void
     *
     * @throws \LdapRecord\LdapRecordException
     */
    protected function performInsert()
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

        // Here we perform the insert of new object in the directory,
        // but filter out any empty attributes before sending them
        // to the server. LDAP servers will throw an exception if
        // attributes have been given empty or null values.
        $query->insert($this->getDn(), array_filter($this->getAttributes()));

        $this->dispatch('created');

        $this->syncOriginal();

        $this->exists = true;

        $this->wasRecentlyCreated = true;
    }

    /**
     * Updates the model in the directory.
     *
     * @return void
     *
     * @throws \LdapRecord\LdapRecordException
     */
    protected function performUpdate()
    {
        if (! count($modifications = $this->getModifications())) {
            return;
        }

        $this->dispatch('updating');

        $this->newQuery()->update($this->dn, $modifications);

        $this->dispatch('updated');

        $this->syncOriginal();
    }

    /**
     * Create the model in the directory.
     *
     * @param  array  $attributes  The attributes for the new entry.
     * @return Model
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public static function create(array $attributes = [])
    {
        $instance = new static($attributes);

        $instance->save();

        return $instance;
    }

    /**
     * Create an attribute on the model.
     *
     * @param  string  $attribute  The attribute to create
     * @param  mixed  $value  The value of the new attribute
     * @return void
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function createAttribute($attribute, $value)
    {
        $this->assertExists();

        $this->dispatch(['saving', 'updating']);

        $this->newQuery()->insertAttributes($this->dn, [$attribute => (array) $value]);

        $this->addAttributeValue($attribute, $value);

        $this->dispatch(['updated', 'saved']);
    }

    /**
     * Update the model.
     *
     * @param  array  $attributes  The attributes to update for the current entry.
     * @return void
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function update(array $attributes = [])
    {
        $this->assertExists();

        $this->save($attributes);
    }

    /**
     * Update the model attribute with the specified value.
     *
     * @param  string  $attribute  The attribute to modify
     * @param  mixed  $value  The new value for the attribute
     * @return void
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function updateAttribute($attribute, $value)
    {
        $this->assertExists();

        $this->dispatch(['saving', 'updating']);

        $this->newQuery()->updateAttributes($this->dn, [$attribute => (array) $value]);

        $this->addAttributeValue($attribute, $value);

        $this->dispatch(['updated', 'saved']);
    }

    /**
     * Destroy the models for the given distinguished names.
     *
     * @param  Collection|array|string  $dns
     * @param  bool  $recursive
     * @return int
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public static function destroy($dns, $recursive = false)
    {
        $count = 0;

        $instance = new static();

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
     * @param  bool  $recursive  Whether to recursively delete leaf nodes (models that are children).
     * @return void
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function delete($recursive = false)
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
     * @return void
     *
     * @throws \LdapRecord\LdapRecordException
     */
    protected function deleteLeafNodes()
    {
        $this->newQueryWithoutScopes()
            ->in($this->dn)
            ->listing()
            ->each(function (Model $model) {
                $model->delete($recursive = true);
            });
    }

    /**
     * Delete an attribute on the model.
     *
     * @param  string|array  $attributes  The attribute(s) to delete
     *
     * Delete specific values in attributes:
     *
     *     ["memberuid" => "jdoe"]
     *
     * Delete an entire attribute:
     *
     *     ["memberuid" => []]
     * @return void
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function deleteAttribute($attributes)
    {
        $this->assertExists();

        $attributes = $this->makeDeletableAttributes($attributes);

        $this->dispatch(['saving', 'updating']);

        $this->newQuery()->deleteAttributes($this->dn, $attributes);

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
     *
     * @param  string|array  $attributes
     * @return array
     */
    protected function makeDeletableAttributes($attributes)
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
     * @param  static|string  $newParentDn  The new parent of the current model.
     * @param  bool  $deleteOldRdn  Whether to delete the old models relative distinguished name once renamed / moved.
     * @return void
     *
     * @throws UnexpectedValueException
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function move($newParentDn, $deleteOldRdn = true)
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
     * @param  string  $rdn  The models new relative distinguished name. Example: "cn=JohnDoe"
     * @param  static|string|null  $newParentDn  The models new parent distinguished name (if moving). Leave this null if you are only renaming. Example: "ou=MovedUsers,dc=acme,dc=org"
     * @param  bool|true  $deleteOldRdn  Whether to delete the old models relative distinguished name once renamed / moved.
     * @return void
     *
     * @throws ModelDoesNotExistException
     * @throws \LdapRecord\LdapRecordException
     */
    public function rename($rdn, $newParentDn = null, $deleteOldRdn = true)
    {
        $this->assertExists();

        if ($newParentDn instanceof self) {
            $newParentDn = $newParentDn->getDn();
        }

        if (is_null($newParentDn)) {
            $newParentDn = $this->getParentDn($this->dn);
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

        // If the RDN we have been given is empty when parsed, we must
        // have been given a string, with no attribute. In this case,
        // we will create a new RDN using the current DN's head.
        if ($this->newDn($rdn)->isEmpty()) {
            $rdn = $this->getUpdateableRdn($rdn);
        }

        $this->dispatch('renaming', [$rdn, $newParentDn]);

        $this->newQuery()->rename($this->dn, $rdn, $newParentDn, $deleteOldRdn);

        // If the model was successfully renamed, we will set
        // its new DN so any further updates to the model
        // can be performed without any issues.
        $this->dn = implode(',', [$rdn, $newParentDn]);

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
     * Get an updateable RDN for the model.
     *
     * @param  string  $name
     * @return string
     */
    public function getUpdateableRdn($name)
    {
        return $this->getCreatableRdn($name, $this->newDn($this->dn)->head());
    }

    /**
     * Get a distinguished name that is creatable for the model.
     *
     * @param  string|null  $name
     * @param  string|null  $attribute
     * @return string
     */
    public function getCreatableDn($name = null, $attribute = null)
    {
        return implode(',', [
            $this->getCreatableRdn($name, $attribute),
            $this->in ?? $this->newQuery()->getbaseDn(),
        ]);
    }

    /**
     * Get a creatable (escaped) RDN for the model.
     *
     * @param  string|null  $name
     * @param  string|null  $attribute
     * @return string
     */
    public function getCreatableRdn($name = null, $attribute = null)
    {
        $attribute = $attribute ?? $this->getCreatableRdnAttribute();

        $name = $this->escape(
            $name ?? $this->getFirstAttribute($attribute)
        )->dn();

        return "$attribute=$name";
    }

    /**
     * Get the creatable RDN attribute name.
     *
     * @return string
     */
    protected function getCreatableRdnAttribute()
    {
        return 'cn';
    }

    /**
     * Determines if the given modification is valid.
     *
     * @param  mixed  $mod
     * @return bool
     */
    protected function isValidModification($mod)
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
    protected function buildModificationsFromDirty()
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
     * @deprecated
     *
     * @return void
     *
     * @throws ModelDoesNotExistException
     */
    protected function requireExistence()
    {
        return $this->assertExists();
    }

    /**
     * Throw an exception if the model does not exist.
     *
     * @return void
     *
     * @throws ModelDoesNotExistException
     */
    protected function assertExists()
    {
        if (! $this->exists || is_null($this->dn)) {
            throw ModelDoesNotExistException::forModel($this);
        }
    }
}
