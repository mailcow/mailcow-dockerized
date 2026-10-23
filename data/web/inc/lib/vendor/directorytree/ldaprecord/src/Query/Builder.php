<?php

namespace LdapRecord\Query;

use BadMethodCallException;
use Closure;
use DateTimeInterface;
use Generator;
use InvalidArgumentException;
use LDAP\Result;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\EscapesValues;
use LdapRecord\LdapInterface;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\EscapedValue;
use LdapRecord\Models\Model;
use LdapRecord\Query\Events\QueryExecuted;
use LdapRecord\Query\Model\Builder as ModelBuilder;
use LdapRecord\Query\Pagination\LazyPaginator;
use LdapRecord\Query\Pagination\Paginator;
use LdapRecord\Support\Arr;

class Builder
{
    use EscapesValues;

    public const TYPE_SEARCH = 'search';

    public const TYPE_READ = 'read';

    public const TYPE_CHUNK = 'chunk';

    public const TYPE_LIST = 'list';

    public const TYPE_PAGINATE = 'paginate';

    /**
     * The base distinguished name placeholder.
     */
    public const BASE_DN_PLACEHOLDER = '{base}';

    /**
     * The selected columns to retrieve on the query.
     */
    public ?array $columns = null;

    /**
     * The query filters.
     */
    public array $filters = [
        'and' => [],
        'or' => [],
        'raw' => [],
    ];

    /**
     * The LDAP server controls to be sent.
     */
    public array $controls = [];

    /**
     * The LDAP server controls that were processed.
     */
    public array $controlsResponse = [];

    /**
     * The size limit of the query.
     */
    public int $limit = 0;

    /**
     * Determine whether the current query is paginated.
     */
    public bool $paginated = false;

    /**
     * The distinguished name to perform searches upon.
     */
    protected ?string $dn = null;

    /**
     * The base distinguished name to perform searches inside.
     */
    protected ?string $baseDn = null;

    /**
     * The default query type.
     */
    protected string $type = self::TYPE_SEARCH;

    /**
     * Determine whether the query is nested.
     */
    protected bool $nested = false;

    /**
     * Determine whether the query should be cached.
     */
    protected bool $caching = false;

    /**
     * The custom cache key to use when caching results.
     */
    protected ?string $cacheKey = null;

    /**
     * How long the query should be cached until.
     */
    protected ?DateTimeInterface $cacheUntil = null;

    /**
     * Determine whether the query cache must be flushed.
     */
    protected bool $flushCache = false;

    /**
     * The current connection instance.
     */
    protected Connection $connection;

    /**
     * The current grammar instance.
     */
    protected Grammar $grammar;

    /**
     * The current cache instance.
     */
    protected ?Cache $cache = null;

    /**
     * Constructor.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->grammar = new Grammar;
    }

    /**
     * Set the current connection.
     */
    public function setConnection(Connection $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Set the current filter grammar.
     */
    public function setGrammar(Grammar $grammar): static
    {
        $this->grammar = $grammar;

        return $this;
    }

    /**
     * Set the cache to store query results.
     */
    public function setCache(?Cache $cache = null): static
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Returns a new Query Builder instance.
     */
    public function newInstance(?string $baseDn = null): Builder
    {
        return (new static($this->connection))->setDn(
            is_null($baseDn) ? $this->getDn() : $baseDn
        );
    }

    /**
     * Returns a new nested Query Builder instance.
     */
    public function newNestedInstance(?Closure $closure = null): Builder
    {
        $query = $this->newInstance()->nested();

        if ($closure) {
            $closure($query);
        }

        return $query;
    }

    /**
     * Executes the LDAP query.
     */
    public function get(array|string $columns = ['*']): Collection|array
    {
        return $this->onceWithColumns(
            Arr::wrap($columns), fn () => $this->query($this->getQuery())
        );
    }

    /**
     * Execute the given callback while selecting the given columns.
     *
     * After running the callback, the columns are reset to the original value.
     */
    protected function onceWithColumns(array $columns, Closure $callback): mixed
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $result = $callback();

        $this->columns = $original;

        return $result;
    }

    /**
     * Compile the query into an LDAP filter string.
     */
    public function getQuery(): string
    {
        // We need to ensure we have at least one filter, as
        // no query results will be returned otherwise.
        if (count(array_filter($this->filters)) === 0) {
            $this->whereHas('objectclass');
        }

        return $this->grammar->compile($this);
    }

    /**
     * Get the unescaped query.
     */
    public function getUnescapedQuery(): string
    {
        return EscapedValue::unescape($this->getQuery());
    }

    /**
     * Get the current Grammar instance.
     */
    public function getGrammar(): Grammar
    {
        return $this->grammar;
    }

    /**
     * Get the current Cache instance.
     */
    public function getCache(): ?Cache
    {
        return $this->cache;
    }

    /**
     * Get the current Connection instance.
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Get the query type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the base distinguished name of the query.
     */
    public function setBaseDn(Model|string|null $dn = null): static
    {
        $this->baseDn = $this->substituteBaseDn($dn);

        return $this;
    }

    /**
     * Get the base distinguished name of the query.
     */
    public function getBaseDn(): ?string
    {
        return $this->baseDn;
    }

    /**
     * Get the distinguished name of the query.
     */
    public function getDn(): ?string
    {
        return $this->dn;
    }

    /**
     * Set the distinguished name for the query.
     */
    public function setDn(Model|string|null $dn = null): static
    {
        $this->dn = $this->substituteBaseDn($dn);

        return $this;
    }

    /**
     * Substitute the base DN string template for the current base.
     */
    public function substituteBaseDn(Model|string|null $dn = null): string
    {
        return str_replace(static::BASE_DN_PLACEHOLDER, $this->baseDn ?? '', (string) $dn);
    }

    /**
     * Alias for setting the distinguished name for the query.
     */
    public function in(Model|string|null $dn = null): static
    {
        return $this->setDn($dn);
    }

    /**
     * Set the size limit of the current query.
     */
    public function limit(int $limit = 0): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Returns a new query for the given model.
     */
    public function model(Model $model): ModelBuilder
    {
        return $model->newQueryBuilder($this->connection)
            ->setCache($this->connection->getCache())
            ->setBaseDn($this->baseDn)
            ->setModel($model);
    }

    /**
     * Performs the specified query on the current LDAP connection.
     */
    public function query(string $query): Collection|array
    {
        $start = microtime(true);

        // Here we will create the execution callback. This allows us
        // to only execute an LDAP request if caching is disabled
        // or if no cache of the given query exists yet.
        $callback = fn () => $this->parse($this->run($query));

        $results = $this->getCachedResponse($query, $callback);

        $this->logQuery($this, $this->type, $this->getElapsedTime($start));

        return $this->process($results);
    }

    /**
     * Paginates the current LDAP query.
     */
    public function paginate(int $pageSize = 1000, bool $isCritical = false): Collection|array
    {
        $this->paginated = true;

        $start = microtime(true);

        $query = $this->getQuery();

        // Here we will create the pagination callback. This allows us
        // to only execute an LDAP request if caching is disabled
        // or if no cache of the given query exists yet.
        $callback = fn () => $this->runPaginate($query, $pageSize, $isCritical);

        $pages = $this->getCachedResponse($query, $callback);

        $this->logQuery($this, self::TYPE_PAGINATE, $this->getElapsedTime($start));

        return $this->process($pages);
    }

    /**
     * Runs the paginate operation with the given filter.
     */
    protected function runPaginate(string $filter, int $perPage, bool $isCritical): array
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $this->newPaginator($filter, $perPage, $isCritical)->execute($ldap)
        );
    }

    /**
     * Make a new paginator instance.
     */
    protected function newPaginator(string $filter, int $perPage, bool $isCritical): Paginator
    {
        return new Paginator($this, $filter, $perPage, $isCritical);
    }

    /**
     * Execute a callback over each item while chunking.
     */
    public function each(Closure $callback, int $pageSize = 1000, bool $isCritical = false, bool $isolate = false): bool
    {
        return $this->chunk($pageSize, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        }, $isCritical, $isolate);
    }

    /**
     * Chunk the results of a paginated LDAP query.
     */
    public function chunk(int $pageSize, Closure $callback, bool $isCritical = false, bool $isolate = false): bool
    {
        $this->limit(0);

        $start = microtime(true);

        $chunk = function (Builder $query) use ($pageSize, $callback, $isCritical) {
            $page = 1;

            foreach ($query->runChunk($this->getQuery(), $pageSize, $isCritical) as $chunk) {
                if ($callback($this->process($chunk), $page) === false) {
                    return false;
                }

                $page++;
            }
        };

        // Connection isolation creates a new, temporary connection for the pagination
        // request to occur on. This allows connections that do not support executing
        // other queries during a pagination request, to do so without interruption.
        $isolate ? $this->connection->isolate(
            fn (Connection $replicate) => $chunk($this->clone()->setConnection($replicate))
        ) : $chunk($this);

        $this->logQuery($this, self::TYPE_CHUNK, $this->getElapsedTime($start));

        return true;
    }

    /**
     * Runs the chunk operation with the given filter.
     */
    protected function runChunk(string $filter, int $perPage, bool $isCritical): Generator
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $this->newLazyPaginator($filter, $perPage, $isCritical)->execute($ldap)
        );
    }

    /**
     * Make a new lazy paginator instance.
     */
    protected function newLazyPaginator(string $filter, int $perPage, bool $isCritical): LazyPaginator
    {
        return new LazyPaginator($this, $filter, $perPage, $isCritical);
    }

    /**
     * Create a slice of the LDAP query into a page.
     */
    public function slice(int $page = 1, int $perPage = 100, string $orderBy = 'cn', string $orderByDir = 'asc'): Slice
    {
        $results = $this->forPage($page, $perPage, $orderBy, $orderByDir);

        $total = $this->controlsResponse[LDAP_CONTROL_VLVRESPONSE]['value']['count'] ?? 0;

        // Some LDAP servers seem to have an issue where the last result in a virtual
        // list view will always be returned, regardless of the offset being larger
        // than the result itself. In this case, we will manually return an empty
        // response so that no objects are deceivingly included in the slice.
        $objects = $page > max((int) ceil($total / $perPage), 1)
            ? ($this instanceof ModelBuilder ? $this->model->newCollection() : [])
            : $results;

        return new Slice($objects, $total, $perPage, $page);
    }

    /**
     * Get the results of a query for a given page.
     */
    public function forPage(int $page = 1, int $perPage = 100, string $orderBy = 'cn', string $orderByDir = 'asc'): Collection|array
    {
        if (! $this->hasOrderBy()) {
            $this->orderBy($orderBy, $orderByDir);
        }

        $this->addControl(LDAP_CONTROL_VLVREQUEST, true, [
            'before' => 0,
            'after' => $perPage - 1,
            'offset' => ($page * $perPage) - $perPage + 1,
            'count' => 0,
        ]);

        return $this->get();
    }

    /**
     * Processes and converts the given LDAP results into models.
     */
    protected function process(array $results): mixed
    {
        unset($results['count']);

        if ($this->paginated) {
            return $this->flattenPages($results);
        }

        return $results;
    }

    /**
     * Flattens LDAP paged results into a single array.
     */
    protected function flattenPages(array $pages): array
    {
        $records = [];

        foreach ($pages as $page) {
            unset($page['count']);

            $records = array_merge($records, $page);
        }

        return $records;
    }

    /**
     * Get the cached response or execute and cache the callback value.
     */
    protected function getCachedResponse(string $query, Closure $callback): mixed
    {
        if ($this->cache && $this->caching) {
            $key = $this->cacheKey ?? $this->getCacheKey($query);

            if ($this->flushCache) {
                $this->cache->delete($key);
            }

            return $this->cache->remember($key, $this->cacheUntil, $callback);
        }

        try {
            return $callback();
        } finally {
            $this->caching = false;
            $this->cacheKey = null;
            $this->cacheUntil = null;
            $this->flushCache = false;
        }
    }

    /**
     * Runs the query operation with the given filter.
     */
    public function run(string $filter): mixed
    {
        return $this->connection->run(function (LdapInterface $ldap) use ($filter) {
            // We will avoid setting the controls during any pagination
            // requests as it will clear the cookie we need to send
            // to the server upon retrieving every page.
            if (! $this->paginated) {
                // Before running the query, we will set the LDAP server controls. This
                // allows the controls to be automatically reset upon each new query
                // that is conducted on the same connection during each request.
                $ldap->setOption(LDAP_OPT_SERVER_CONTROLS, $this->controls);
            }

            return $ldap->{$this->type}(
                (string) ($this->dn ?? $this->baseDn),
                $filter,
                $this->getSelects(),
                $onlyAttributes = false,
                $this->limit
            );
        });
    }

    /**
     * Parses the given LDAP resource by retrieving its entries.
     */
    public function parse(mixed $resource): array
    {
        if (! $resource) {
            return [];
        }

        return $this->connection->run(function (LdapInterface $ldap) use ($resource) {
            $this->controlsResponse = $this->controls;

            // Process the server controls response.
            $ldap->parseResult(
                result: $resource,
                controls: $this->controlsResponse
            );

            $entries = $ldap->getEntries($resource);

            // Free up memory.
            if (is_resource($resource) || $resource instanceof Result) {
                $ldap->freeResult($resource);
            }

            return $entries;
        });
    }

    /**
     * Get the cache key.
     */
    protected function getCacheKey(string $query): string
    {
        $host = $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->getHost()
        );

        $key = $host
            .$this->type
            .$this->getDn()
            .$query
            .implode($this->getSelects())
            .$this->limit
            .$this->paginated;

        return md5($key);
    }

    /**
     * Get the first entry in a search result.
     */
    public function first(array|string $columns = ['*']): Model|array|null
    {
        return Arr::first(
            $this->limit(1)->get($columns)
        );
    }

    /**
     * Get the first entry in a search result.
     *
     * If no entry is found, an exception is thrown.
     *
     * @throws ObjectNotFoundException
     */
    public function firstOrFail(array|string $columns = ['*']): Model|array
    {
        if (! $record = $this->first($columns)) {
            $this->throwNotFoundException($this->getUnescapedQuery(), $this->dn);
        }

        return $record;
    }

    /**
     * Get the first entry in a result, or execute the callback.
     */
    public function firstOr(Closure $callback): mixed
    {
        return $this->first() ?: $callback();
    }

    /**
     * Execute the query and get the first result if it's the sole matching record.
     *
     * @throws ObjectsNotFoundException
     * @throws MultipleObjectsFoundException
     */
    public function sole(array|string $columns = ['*']): Model|array
    {
        $result = $this->limit(2)->get($columns);

        if (empty($result)) {
            throw new ObjectsNotFoundException;
        }

        if (count($result) > 1) {
            throw new MultipleObjectsFoundException;
        }

        return reset($result);
    }

    /**
     * Determine if any results exist for the current query.
     */
    public function exists(): bool
    {
        return ! is_null($this->first());
    }

    /**
     * Determine if no results exist for the current query.
     */
    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * Execute the given callback if no rows exist for the current query.
     */
    public function existsOr(Closure $callback): mixed
    {
        return $this->exists() ? true : $callback();
    }

    /**
     * Throws a not found exception.
     *
     * @throws ObjectNotFoundException
     */
    protected function throwNotFoundException(string $query, ?string $dn = null): void
    {
        throw ObjectNotFoundException::forQuery($query, $dn);
    }

    /**
     * Finds a record by the specified attribute and value.
     *
     * @return Model|static|null
     */
    public function findBy(string $attribute, string $value, array|string $columns = ['*']): Model|array|null
    {
        try {
            return $this->findByOrFail($attribute, $value, $columns);
        } catch (ObjectNotFoundException $e) {
            return null;
        }
    }

    /**
     * Finds a record by the specified attribute and value.
     *
     * If no record is found an exception is thrown.
     *
     * @throws ObjectNotFoundException
     */
    public function findByOrFail(string $attribute, string $value, array|string $columns = ['*']): Model|array
    {
        return $this->whereEquals($attribute, $value)->firstOrFail($columns);
    }

    /**
     * Find many records by distinguished name.
     */
    public function findMany(array|string $dns, array|string $columns = ['*']): Collection|array
    {
        if (empty($dns)) {
            return $this->process([]);
        }

        $objects = [];

        foreach ((array) $dns as $dn) {
            if (! is_null($object = $this->find($dn, $columns))) {
                $objects[] = $object;
            }
        }

        return $this->process($objects);
    }

    /**
     * Finds many records by the specified attribute.
     */
    public function findManyBy(string $attribute, array $values = [], array|string $columns = ['*']): Collection|array
    {
        $query = $this->select($columns);

        foreach ($values as $value) {
            $query->orWhere([$attribute => $value]);
        }

        return $query->get();
    }

    /**
     * Finds a record by its distinguished name.
     */
    public function find(array|string $dn, array|string $columns = ['*']): Collection|Model|array|null
    {
        if (is_array($dn)) {
            return $this->findMany($dn, $columns);
        }

        try {
            return $this->findOrFail($dn, $columns);
        } catch (ObjectNotFoundException $e) {
            return null;
        }
    }

    /**
     * Finds a record by its distinguished name.
     *
     * Fails upon no records returned.
     *
     * @throws ObjectNotFoundException
     */
    public function findOrFail(string $dn, array|string $columns = ['*']): Model|array
    {
        return $this->setDn($dn)
            ->read()
            ->whereHas('objectclass')
            ->firstOrFail($columns);
    }

    /**
     * Adds the inserted fields to query on the current LDAP connection.
     */
    public function select(array|string $columns = ['*']): static
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        if (! empty($columns)) {
            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Add a new select column to the query.
     */
    public function addSelect(array|string $column): static
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->columns = array_merge((array) $this->columns, $column);

        return $this;
    }

    /**
     * Add an order by control to the query.
     */
    public function orderBy(string $attribute, string $direction = 'asc', array $options = []): static
    {
        return $this->addControl(LDAP_CONTROL_SORTREQUEST, true, [
            [
                ...$options,
                'attr' => $attribute,
                'reverse' => $direction === 'desc',
            ],
        ]);
    }

    /**
     * Add an order by descending control to the query.
     */
    public function orderByDesc(string $attribute, array $options = []): static
    {
        return $this->orderBy($attribute, 'desc', $options);
    }

    /**
     * Determine if the query has a sotr request control header.
     */
    public function hasOrderBy(): bool
    {
        return $this->hasControl(LDAP_CONTROL_SORTREQUEST);
    }

    /**
     * Adds a raw filter to the current query.
     */
    public function rawFilter(array|string $filters = []): static
    {
        $filters = is_array($filters) ? $filters : func_get_args();

        foreach ($filters as $filter) {
            $this->filters['raw'][] = $filter;
        }

        return $this;
    }

    /**
     * Adds a nested 'and' filter to the current query.
     */
    public function andFilter(Closure $closure): static
    {
        $query = $this->newNestedInstance($closure);

        return $this->rawFilter(
            $this->grammar->compileAnd($query->getQuery())
        );
    }

    /**
     * Adds a nested 'or' filter to the current query.
     */
    public function orFilter(Closure $closure): static
    {
        $query = $this->newNestedInstance($closure);

        return $this->rawFilter(
            $this->grammar->compileOr($query->getQuery())
        );
    }

    /**
     * Adds a nested 'not' filter to the current query.
     */
    public function notFilter(Closure $closure): static
    {
        $query = $this->newNestedInstance($closure);

        return $this->rawFilter(
            $this->grammar->compileNot($query->getQuery())
        );
    }

    /**
     * Adds a where clause to the current query.
     *
     * @throws InvalidArgumentException
     */
    public function where(array|string $field, mixed $operator = null, mixed $value = null, string $boolean = 'and', bool $raw = false): static
    {
        if (is_array($field)) {
            // If the field is an array, we will assume we have been
            // provided with an array of key-value pairs and can
            // add them each as their own separate where clause.
            return $this->addArrayOfWheres($field, $boolean, $raw);
        }

        // If we have been provided with two arguments not a "has" or
        // "not has" operator, we'll assume the developer is creating
        // an "equals" clause and set the proper operator in place.
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2 && ! $this->operatorRequiresValue($operator)
        );

        if (! in_array($operator, $this->grammar->getOperators())) {
            throw new InvalidArgumentException("Invalid LDAP filter operator [$operator]");
        }

        // We'll escape the value if raw isn't requested.
        $value = $this->prepareWhereValue($field, $value, $raw);

        $field = $this->escape($field)->forDnAndFilter()->get();

        $this->addFilter($boolean, compact('field', 'operator', 'value'));

        return $this;
    }

    /**
     * Prepare the value and operator for a where clause.
     */
    public function prepareValueAndOperator(mixed $value, mixed $operator, bool $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        }

        return [$value, $operator];
    }

    /**
     * Determine if the operator requires a value to be present.
     */
    protected function operatorRequiresValue(mixed $operator): bool
    {
        return in_array($operator, ['*', '!*']);
    }

    /**
     * Prepare the value for being queried.
     */
    protected function prepareWhereValue(string $field, mixed $value = null, bool $raw = false): string
    {
        return $raw ? $value : $this->escape($value)->get();
    }

    /**
     * Adds a raw where clause to the current query.
     *
     * Values given to this method are not escaped.
     */
    public function whereRaw(array|string $field, ?string $operator = null, mixed $value = null): static
    {
        return $this->where($field, $operator, $value, 'and', true);
    }

    /**
     * Adds a 'where equals' clause to the current query.
     */
    public function whereEquals(string $field, string $value): static
    {
        return $this->where($field, '=', $value);
    }

    /**
     * Adds a 'where not equals' clause to the current query.
     */
    public function whereNotEquals(string $field, string $value): static
    {
        return $this->where($field, '!', $value);
    }

    /**
     * Adds a 'where approximately equals' clause to the current query.
     */
    public function whereApproximatelyEquals(string $field, string $value): static
    {
        return $this->where($field, '~=', $value);
    }

    /**
     * Adds a 'where has' clause to the current query.
     */
    public function whereHas(string $field): static
    {
        return $this->where($field, '*');
    }

    /**
     * Adds a 'where not has' clause to the current query.
     */
    public function whereNotHas(string $field): static
    {
        return $this->where($field, '!*');
    }

    /**
     * Adds a 'where contains' clause to the current query.
     */
    public function whereContains(string $field, string $value): static
    {
        return $this->where($field, 'contains', $value);
    }

    /**
     * Adds a 'where contains' clause to the current query.
     */
    public function whereNotContains(string $field, string $value): static
    {
        return $this->where($field, 'not_contains', $value);
    }

    /**
     * Query for entries that match any of the values provided for the given field.
     */
    public function whereIn(string $field, array $values): static
    {
        if (empty($values)) {
            // If the array of values is empty, we will
            // add an empty OR filter to the query to
            // ensure that no results are returned.
            $this->rawFilter('(|)');

            return $this;
        }

        return $this->orFilter(function (Builder $query) use ($field, $values) {
            foreach ($values as $value) {
                $query->whereEquals($field, $value);
            }
        });
    }

    /**
     * Adds a 'between' clause to the current query.
     */
    public function whereBetween(string $field, array $values): static
    {
        return $this->where([
            [$field, '>=', $values[0]],
            [$field, '<=', $values[1]],
        ]);
    }

    /**
     * Adds a 'where starts with' clause to the current query.
     */
    public function whereStartsWith(string $field, string $value): static
    {
        return $this->where($field, 'starts_with', $value);
    }

    /**
     * Adds a 'where *not* starts with' clause to the current query.
     */
    public function whereNotStartsWith(string $field, string $value): static
    {
        return $this->where($field, 'not_starts_with', $value);
    }

    /**
     * Adds a 'where ends with' clause to the current query.
     */
    public function whereEndsWith(string $field, string $value): static
    {
        return $this->where($field, 'ends_with', $value);
    }

    /**
     * Adds a 'where *not* ends with' clause to the current query.
     */
    public function whereNotEndsWith(string $field, string $value): static
    {
        return $this->where($field, 'not_ends_with', $value);
    }

    /**
     * Only include deleted models in the results.
     */
    public function whereDeleted(): static
    {
        return $this->withDeleted()->whereEquals('isDeleted', 'TRUE');
    }

    /**
     * Set the LDAP control option to include deleted LDAP models.
     */
    public function withDeleted(): static
    {
        return $this->addControl(LdapInterface::OID_SERVER_SHOW_DELETED, $isCritical = true);
    }

    /**
     * Add a server control to the query.
     */
    public function addControl(string $oid, bool $isCritical = false, mixed $value = null): static
    {
        $this->controls[$oid] = compact('oid', 'isCritical', 'value');

        return $this;
    }

    /**
     * Determine if the server control exists on the query.
     */
    public function hasControl(string $oid): bool
    {
        return array_key_exists($oid, $this->controls);
    }

    /**
     * Adds an 'or where' clause to the current query.
     */
    public function orWhere(array|string $field, ?string $operator = null, ?string $value = null): static
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2 && ! $this->operatorRequiresValue($operator)
        );

        return $this->where($field, $operator, $value, 'or');
    }

    /**
     * Adds a raw or where clause to the current query.
     *
     * Values given to this method are not escaped.
     */
    public function orWhereRaw(array|string $field, ?string $operator = null, ?string $value = null): static
    {
        return $this->where($field, $operator, $value, 'or', true);
    }

    /**
     * Adds an 'or where has' clause to the current query.
     */
    public function orWhereHas(string $field): static
    {
        return $this->orWhere($field, '*');
    }

    /**
     * Adds a 'where not has' clause to the current query.
     */
    public function orWhereNotHas(string $field): static
    {
        return $this->orWhere($field, '!*');
    }

    /**
     * Adds an 'or where equals' clause to the current query.
     */
    public function orWhereEquals(string $field, string $value): static
    {
        return $this->orWhere($field, '=', $value);
    }

    /**
     * Adds an 'or where not equals' clause to the current query.
     */
    public function orWhereNotEquals(string $field, string $value): static
    {
        return $this->orWhere($field, '!', $value);
    }

    /**
     * Adds a 'or where approximately equals' clause to the current query.
     */
    public function orWhereApproximatelyEquals(string $field, string $value): static
    {
        return $this->orWhere($field, '~=', $value);
    }

    /**
     * Adds an 'or where contains' clause to the current query.
     */
    public function orWhereContains(string $field, string $value): static
    {
        return $this->orWhere($field, 'contains', $value);
    }

    /**
     * Adds an 'or where *not* contains' clause to the current query.
     */
    public function orWhereNotContains(string $field, string $value): static
    {
        return $this->orWhere($field, 'not_contains', $value);
    }

    /**
     * Adds an 'or where starts with' clause to the current query.
     */
    public function orWhereStartsWith(string $field, string $value): static
    {
        return $this->orWhere($field, 'starts_with', $value);
    }

    /**
     * Adds an 'or where *not* starts with' clause to the current query.
     */
    public function orWhereNotStartsWith(string $field, string $value): static
    {
        return $this->orWhere($field, 'not_starts_with', $value);
    }

    /**
     * Adds an 'or where ends with' clause to the current query.
     */
    public function orWhereEndsWith(string $field, string $value): static
    {
        return $this->orWhere($field, 'ends_with', $value);
    }

    /**
     * Adds an 'or where *not* ends with' clause to the current query.
     */
    public function orWhereNotEndsWith(string $field, string $value): static
    {
        return $this->orWhere($field, 'not_ends_with', $value);
    }

    /**
     * Adds a filter binding onto the current query.
     *
     * @throws InvalidArgumentException
     */
    public function addFilter(string $type, array $bindings): static
    {
        if (! array_key_exists($type, $this->filters)) {
            throw new InvalidArgumentException("Filter type: [$type] is invalid.");
        }

        // Each filter clause require key bindings to be set. We
        // will validate this here to ensure all of them have
        // been provided, or throw an exception otherwise.
        if ($missing = $this->missingBindingKeys($bindings)) {
            $keys = implode(', ', $missing);

            throw new InvalidArgumentException("Invalid filter bindings. Missing: [$keys] keys.");
        }

        $this->filters[$type][] = $bindings;

        return $this;
    }

    /**
     * Extract any missing required binding keys.
     */
    protected function missingBindingKeys(array $bindings): array
    {
        $required = array_flip(['field', 'operator', 'value']);

        $existing = array_intersect_key($required, $bindings);

        return array_keys(array_diff_key($required, $existing));
    }

    /**
     * Get all the filters on the query.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Clear the query filters.
     */
    public function clearFilters(): static
    {
        foreach (array_keys($this->filters) as $type) {
            $this->filters[$type] = [];
        }

        return $this;
    }

    /**
     * Determine if the query has attributes selected.
     */
    public function hasSelects(): bool
    {
        return count($this->columns ?? []) > 0;
    }

    /**
     * Get the attributes to select on the search.
     */
    public function getSelects(): array
    {
        $selects = $this->columns ?? ['*'];

        if (in_array('*', $selects)) {
            return $selects;
        }

        if (in_array('objectclass', $selects)) {
            return $selects;
        }

        // If the * character is not provided in the selected columns,
        // we need to ensure we always select the object class, as
        // this is used for constructing models properly.
        $selects[] = 'objectclass';

        return $selects;
    }

    /**
     * Set the query to search on the base distinguished name.
     *
     * This will result in one record being returned.
     */
    public function read(): static
    {
        $this->type = self::TYPE_READ;

        return $this;
    }

    /**
     * Set the query to search one level on the base distinguished name.
     */
    public function list(): static
    {
        $this->type = self::TYPE_LIST;

        return $this;
    }

    /**
     * Alias for the "search" method.
     */
    public function recursive(): static
    {
        return $this->search();
    }

    /**
     * Set the query to search the entire directory on the base distinguished name.
     */
    public function search(): static
    {
        $this->type = self::TYPE_SEARCH;

        return $this;
    }

    /**
     * Whether to mark the current query as nested.
     */
    public function nested(bool $nested = true): static
    {
        $this->nested = $nested;

        return $this;
    }

    /**
     * Enables caching on the current query until the given date.
     *
     * If flushing is enabled, the query cache will be flushed and then re-cached.
     */
    public function cache(?DateTimeInterface $until = null, bool $flush = false, ?string $key = null): static
    {
        $this->caching = true;
        $this->cacheKey = $key;
        $this->cacheUntil = $until;
        $this->flushCache = $flush;

        return $this;
    }

    /**
     * Determine if the query is nested.
     */
    public function isNested(): bool
    {
        return $this->nested === true;
    }

    /**
     * Determine whether the query is paginated.
     */
    public function isPaginated(): bool
    {
        return $this->paginated;
    }

    /**
     * Insert an entry into the directory.
     *
     * @throws LdapRecordException
     */
    public function insert(string $dn, array $attributes): bool
    {
        return (bool) $this->insertAndGetDn($dn, $attributes);
    }

    /**
     * Insert an entry into the directory and get the inserted distinguished name.
     *
     * @throws LdapRecordException
     */
    public function insertAndGetDn(string $dn, array $attributes): string|false
    {
        $dn = $this->substituteBaseDn($dn);

        if (empty($dn)) {
            throw new LdapRecordException('A new LDAP object must have a distinguished name (dn).');
        }

        if (! array_key_exists('objectclass', $attributes)) {
            throw new LdapRecordException(
                'A new LDAP object must contain at least one object class (objectclass) to be created.'
            );
        }

        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->add($dn, $attributes)
        ) ? $dn : false;
    }

    /**
     * Add attributes to an entry in the directory.
     */
    public function add(string $dn, array $attributes): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->modAdd($dn, $attributes)
        );
    }

    /**
     * Update the entry with the given modifications.
     */
    public function update(string $dn, array $modifications): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->modifyBatch($dn, $modifications)
        );
    }

    /**
     * Replace an entry's attributes in the directory.
     */
    public function replace(string $dn, array $attributes): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->modReplace($dn, $attributes)
        );
    }

    /**
     * Delete an entry from the directory.
     */
    public function delete(string $dn): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->delete($dn)
        );
    }

    /**
     * Remove attributes on the entry in the directory.
     */
    public function remove(string $dn, array $attributes): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->modDelete($dn, $attributes)
        );
    }

    /**
     * Rename an entry in the directory.
     */
    public function rename(string $dn, string $rdn, string $newParentDn, bool $deleteOldRdn = true): bool
    {
        return (bool) $this->renameAndGetDn($dn, $rdn, $newParentDn, $deleteOldRdn);
    }

    /**
     * Rename an entry in the directory and get the new distinguished name.
     */
    public function renameAndGetDn(string $dn, string $rdn, string $newParentDn, bool $deleteOldRdn = true): string|false
    {
        $newParentDn = $this->substituteBaseDn($newParentDn);

        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->rename($dn, $rdn, $newParentDn, $deleteOldRdn)
        ) ? implode(',', [$rdn, $newParentDn]) : false;
    }

    /**
     * Clone the query.
     */
    public function clone(): static
    {
        return clone $this;
    }

    /**
     * Handle dynamic method calls on the query builder.
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters): static
    {
        // If the beginning of the method being called contains
        // 'where', we will assume a dynamic 'where' clause is
        // being performed and pass the parameters to it.
        if (str_starts_with($method, 'where')) {
            return $this->dynamicWhere($method, $parameters);
        }

        throw new BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()',
            static::class,
            $method
        ));
    }

    /**
     * Handles dynamic "where" clauses to the query.
     *
     * @return $this
     */
    public function dynamicWhere(string $method, array $parameters): static
    {
        $finder = substr($method, 5);

        $segments = preg_split('/(And|Or)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE);

        // The connector variable will determine which connector will be used for the
        // query condition. We will change it as we come across new boolean values
        // in the dynamic method strings, which could contain a number of these.
        $connector = 'and';

        $index = 0;

        foreach ($segments as $segment) {
            // If the segment is not a boolean connector, we can assume it is a column's name
            // and we will add it to the query as a new constraint as a where clause, then
            // we can keep iterating through the dynamic method string's segments again.
            if ($segment != 'And' && $segment != 'Or') {
                $this->addDynamic($segment, $connector, $parameters, $index);

                $index++;
            }

            // Otherwise, we will store the connector so we know how the next where clause we
            // find in the query should be connected to the previous ones, meaning we will
            // have the proper boolean connector to connect the next where clause found.
            else {
                $connector = $segment;
            }
        }

        return $this;
    }

    /**
     * Adds an array of wheres to the current query.
     */
    protected function addArrayOfWheres(array $wheres, string $boolean, bool $raw): static
    {
        foreach ($wheres as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                // If the key is numeric and the value is an array, we'll
                // assume we've been given an array with conditionals.
                [$field, $condition] = $value;

                // Since a value is optional for some conditionals, we will
                // try and retrieve the third parameter from the array,
                // but is entirely optional.
                $value = Arr::get($value, 2);

                $this->where($field, $condition, $value, $boolean);
            } else {
                // If the value is not an array, we will assume an equals clause.
                $this->where($key, '=', $value, $boolean, $raw);
            }
        }

        return $this;
    }

    /**
     * Add a single dynamic where clause statement to the query.
     */
    protected function addDynamic(string $segment, string $connector, array $parameters, int $index): void
    {
        // If no parameters were given to the dynamic where clause,
        // we can assume a "has" attribute filter is being added.
        if (count($parameters) === 0) {
            $this->where(strtolower($segment), '*', null, strtolower($connector));
        } else {
            $this->where(strtolower($segment), '=', $parameters[$index], strtolower($connector));
        }
    }

    /**
     * Logs the given executed query information by firing its query event.
     */
    protected function logQuery(Builder $query, string $type, ?float $time = null): void
    {
        $args = [$query, $time];

        $this->fireQueryEvent(
            match ($type) {
                self::TYPE_READ => new Events\Read(...$args),
                self::TYPE_CHUNK => new Events\Chunk(...$args),
                self::TYPE_LIST => new Events\Listing(...$args),
                self::TYPE_PAGINATE => new Events\Paginate(...$args),
                default => new Events\Search(...$args),
            }
        );
    }

    /**
     * Fires the given query event.
     */
    protected function fireQueryEvent(QueryExecuted $event): void
    {
        Container::getInstance()->getDispatcher()->fire($event);
    }

    /**
     * Get the elapsed time since a given starting point.
     */
    protected function getElapsedTime(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }
}
