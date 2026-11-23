<?php

declare(strict_types=1);

namespace Mailcow\Scim\Controller;

use Mailcow\Scim\Model\Filter;
use Mailcow\Scim\Model\Group;
use Mailcow\Scim\Model\Response;
use Mailcow\Scim\Model\Schema;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Model\User;
use Mailcow\Scim\ScimError;
use Mailcow\Scim\Service\GroupService;
use Mailcow\Scim\Service\TokenService;
use Mailcow\Scim\Service\UserService;
use Redis;
use Throwable;

/**
 * The SCIM 2.0 HTTP API below /scim/v2 (RFC 7644), called by scim.php: authentication, routing, list responses
 * with sorting, bulk requests, versions (weak ETags over the resource, RFC 7644 3.14) and errors. Requests and
 * errors are logged to the Redis list SCIM_LOG; failed authentications go to fail2ban.
 */
class ScimController
{
    private const ALLOW = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';

    /**
     * @param int $logLines length of SCIM_LOG, trimmed on every write ($LOG_LINES, like trim_logs.sh)
     * @param int $batch resources read per query when a list is filtered or sorted in memory; at most 1000, the
     *     group memberships of a batch are read with one IN list
     */
    public function __construct(private TokenService $tokens, private UserService $users, private GroupService $groups,
        public readonly string $base, private ?Redis $redis, private int $logLines = 1000, private int $batch = 1000)
    {
    }

    /**
     * @param string $path below /scim/v2, without leading slash, still percent-encoded
     * @param array $query query parameters
     * @param array{if-match?: ?string, if-none-match?: ?string} $conditions request headers of conditional requests
     */
    public function handle(string $method, string $path, array $query, string $body, string $contentType, string $authorization,
        string $ip, array $conditions = []): Response
    {
        $request = $method . ' /scim/v2/' . $path;
        try {
            // CORS preflights carry no Authorization
            if ($method === 'OPTIONS') {
                return new Response(204, null, ['Allow: ' . self::ALLOW]);
            }
            try {
                $token = $this->tokens->authenticate($authorization, $ip);
            } catch (ScimError $e) {
                if ($authorization !== '') {
                    $this->redis?->publish('F2B_CHANNEL', 'mailcow UI: Invalid password for SCIM token by ' . $ip);
                }
                throw $e;
            }
            $this->log('info', $request . ' from ' . $ip . ' (token ID ' . $token->id . ')');
            // a bulk request reports its own, lower limit (RFC 7644 3.7.4)
            if (strlen($body) > Schema::MAX_PAYLOAD && !str_starts_with(trim($path, '/'), 'Bulk')) {
                throw new ScimError(413, 'The request body exceeds ' . Schema::MAX_PAYLOAD . ' bytes');
            }
            $segments = ($path === '') ? [] : array_map('rawurldecode', explode('/', trim($path, '/')));
            return $this->route($token, $method, $segments, array_change_key_case($query, CASE_LOWER),
                fn(): array => self::body($body, $contentType), $conditions, strlen($body));
        } catch (ScimError $e) {
            // requests without any credentials are no IdP: not logged, so they cannot flood the log
            if ($e->status !== 401 || $authorization !== '') {
                $this->log('err', $request . ' -> ' . $e->status . ($e->scimType ? ' ' . $e->scimType : '') . ': ' . $e->getMessage());
            }
            return self::error($e);
        } catch (Throwable $e) {
            $this->log('err', $request . ' -> 500: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return self::error(new ScimError(500, 'Internal server error'));
        } finally {
            foreach ($this->users->notices() as $notice) {
                $this->log('warning', $notice);
            }
        }
    }

    /**
     * @param callable(): array $body reads the request body when the route needs one
     * @param int $size of the raw request body
     */
    private function route(Token $token, string $method, array $segments, array $query, callable $body, array $conditions, int $size): Response
    {
        [$resource, $id] = [$segments[0] ?? '', $segments[1] ?? null];
        if ($resource === '' || count($segments) > 2) {
            throw ScimError::notFound('Not found');
        }
        // before anything is written (RFC 7644 3.5.2: a failing PATCH changes nothing)
        Schema::checkProjection($query);
        switch ($resource) {
            case 'ServiceProviderConfig':
            case 'Schemas':
            case 'ResourceTypes':
                self::allow($method, 'GET');
                // RFC 7644 4: a filter SHOULD be answered with 403
                if (($query['filter'] ?? '') !== '') {
                    throw new ScimError(403, 'Filtering is not supported on configuration endpoints');
                }
                return new Response(200, $this->configuration($resource, $id, $token->allowRename === 1));
            case 'Users':
            case 'Groups':
                $type = substr($resource, 0, -1);
                if ($id === '.search') {
                    self::allow($method, 'POST');
                    return $this->search([$type], $token, $body());
                }
                if ($id === null) {
                    self::allow($method, 'GET, POST');
                    return ($method === 'GET') ? $this->list([$type], $token, $query) : $this->create($type, $token, $body(), $query);
                }
                self::allow($method, 'GET, PUT, PATCH, DELETE');
                return $this->resource($type, $token, $method, $id, $query, $body, $conditions);
            case '.search':
                if ($id !== null) {
                    throw ScimError::notFound('Not found');
                }
                self::allow($method, 'POST');
                return $this->search(['User', 'Group'], $token, $body());
            case 'Bulk':
                if ($id !== null) {
                    throw ScimError::notFound('Not found');
                }
                self::allow($method, 'POST');
                if ($size > Schema::BULK_MAX_PAYLOAD) {
                    throw new ScimError(413, 'The size of the bulk operation exceeds the maxPayloadSize (' . Schema::BULK_MAX_PAYLOAD . ')');
                }
                return $this->bulk($token, $body());
            case 'Me':
                // RFC 7644 3.11: optional
                throw new ScimError(501, 'The /Me endpoint is not implemented');
            default:
                throw ScimError::notFound("Resource type '$resource' not found");
        }
    }

    private function configuration(string $resource, ?string $id, bool $rename): array
    {
        if ($resource === 'ServiceProviderConfig') {
            return ($id === null) ? Schema::serviceProviderConfig($this->base) : throw ScimError::notFound('Not found');
        }
        $items = ($resource === 'Schemas') ? Schema::schemas($this->base, $rename) : Schema::resourceTypes($this->base);
        if ($id === null) {
            return self::listResponse($items, count($items), 1);
        }
        foreach ($items as $item) {
            if (strcasecmp($item['id'], $id) === 0) {
                return $item;
            }
        }
        throw ScimError::notFound("$resource '$id' not found");
    }

    private function create(string $type, Token $token, array $body, array $query): Response
    {
        $resource = $this->represent($this->service($type)->create($token, $body));
        $this->log('info', "Created $type " . ($resource['userName'] ?? $resource['displayName']) . ' (token ID ' . $token->id . ')');
        return new Response(201, Schema::project($resource, $type, $query['attributes'] ?? null, $query['excludedattributes'] ?? null),
            ['Location: ' . $resource['meta']['location'], 'ETag: ' . $resource['meta']['version']]);
    }

    /**
     * GET, PUT, PATCH and DELETE of one resource. If-None-Match makes a GET conditional (304), If-Match a change (412).
     * The services check the version on the state they read under the lock of the resource, so a concurrent SCIM
     * write between check and change is detected. Changes made in the mailcow UI do not take these locks.
     */
    private function resource(string $type, Token $token, string $method, string $id, array $query, callable $body, array $conditions): Response
    {
        $service = $this->service($type);
        if ($method === 'GET') {
            $current = $this->represent($service->get($token, $id));
            if (self::versionMatches($conditions['if-none-match'] ?? null, $current['meta']['version'])) {
                return new Response(304, null, ['ETag: ' . $current['meta']['version']]);
            }
            return new Response(200, Schema::project($current, $type, $query['attributes'] ?? null, $query['excludedattributes'] ?? null),
                ['ETag: ' . $current['meta']['version']]);
        }
        $current = null;
        $precondition = function (User|Group $item) use ($conditions, &$current): void {
            $current = $item;
            if (($conditions['if-match'] ?? null) === null && ($conditions['if-none-match'] ?? null) === null) {
                return;
            }
            $version = $this->represent($item)['meta']['version'];
            if (($conditions['if-match'] ?? null) !== null && !self::versionMatches($conditions['if-match'], $version)) {
                throw new ScimError(412, 'The resource was changed: If-Match does not match its version ' . $version);
            }
            // RFC 7232 3.2: a write with a matching If-None-Match fails
            if (self::versionMatches($conditions['if-none-match'] ?? null, $version)) {
                throw new ScimError(412, 'If-None-Match matches the version of the resource');
            }
        };
        if ($method === 'DELETE') {
            $service->delete($token, $id, $precondition);
            $this->log('info', "Deleted $type " . (($current instanceof User) ? $current->username : ($current?->name ?? $id)) . ': removed from SCIM'
                . (($type === 'User') ? ', login disabled, mailbox kept' : ', mailbox group kept') . ' (token ID ' . $token->id . ')');
            return new Response(204);
        }
        $resource = $this->represent(($method === 'PUT') ? $service->replace($token, $id, $body(), $precondition)
            : $service->patch($token, $id, $body(), $precondition));
        $headers = ['ETag: ' . $resource['meta']['version']];
        // RFC 7644 3.5.2: a PATCH may answer 204; groups do unless attributes are requested (their member lists can be long)
        if ($method === 'PATCH' && $type === 'Group' && !Schema::projects($query)) {
            return new Response(204, null, $headers);
        }
        return new Response(200, Schema::project($resource, $type, $query['attributes'] ?? null, $query['excludedattributes'] ?? null), $headers);
    }

    /**
     * POST /Bulk (RFC 7644 3.7): the operations run in order, an operation that references the bulkId of a later
     * POST runs after it. References that cannot be resolved (unknown, failed or circular) fail the operation with 409.
     */
    private function bulk(Token $token, array $body): Response
    {
        Schema::requireSchema($body, Schema::BULK_REQUEST);
        $operations = $body['operations'] ?? null;
        if (!is_array($operations) || !array_is_list($operations)) {
            throw new ScimError(400, "'Operations' must be an array", 'invalidSyntax');
        }
        if (count($operations) > Schema::BULK_MAX_OPERATIONS) {
            throw new ScimError(413, 'The number of operations exceeds the maxOperations (' . Schema::BULK_MAX_OPERATIONS . ')');
        }
        $failOnErrors = $body['failonerrors'] ?? null;
        if ($failOnErrors !== null && (!is_int($failOnErrors) || $failOnErrors < 1)) {
            throw ScimError::invalidValue("'failOnErrors' must be a positive integer");
        }
        $declared = [];
        foreach ($operations as $operation) {
            if (is_array($operation) && is_string($operation['bulkid'] ?? null) && strtoupper((string)($operation['method'] ?? '')) === 'POST') {
                $declared[$operation['bulkid']] = true;
            }
        }
        $ids = [];
        $results = [];
        $errors = 0;
        $queue = array_keys($operations);
        $waited = [];
        while ($queue !== [] && ($failOnErrors === null || $errors < $failOnErrors)) {
            $i = array_shift($queue);
            $operation = is_array($operations[$i]) ? $operations[$i] : [];
            $pending = array_filter(self::bulkReferences($operation), fn(string $ref): bool => !isset($ids[$ref]));
            // wait for a POST later in the request, once per queue round: a second wait without progress is a cycle
            if ($pending !== [] && array_diff($pending, array_keys($declared)) === [] && ($waited[$i] ?? -1) !== count($ids)) {
                $waited[$i] = count($ids);
                $queue[] = $i;
                continue;
            }
            $result = $this->bulkOperation($token, $operation, $ids, $pending);
            if ((int)$result['status'] >= 400) {
                $errors++;
            }
            $results[] = $result;
        }
        return new Response(200, ['schemas' => [Schema::BULK_RESPONSE], 'Operations' => $results]);
    }

    /**
     * One bulk operation, as result of the BulkResponse
     *
     * @param array<string, string> $ids bulkId => id of the resources created so far, extended by a POST
     * @param string[] $pending bulkIds the operation references that are not created
     */
    private function bulkOperation(Token $token, array $operation, array &$ids, array $pending): array
    {
        $method = strtoupper(is_string($operation['method'] ?? null) ? $operation['method'] : '');
        $bulkId = is_string($operation['bulkid'] ?? null) ? $operation['bulkid'] : null;
        $path = is_string($operation['path'] ?? null) ? self::resolve($operation['path'], $ids) : '';
        $result = array_filter(['method' => $method, 'bulkId' => $bulkId], fn(?string $value): bool => $value !== null);
        try {
            $segments = explode('/', trim($path, '/'));
            $collection = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && in_array($segments[0], ['Users', 'Groups'], true)
                && count($segments) === (($method === 'POST') ? 1 : 2) && ($segments[1] ?? null) !== '.search';
            if (!$collection) {
                throw ScimError::invalidValue("Invalid bulk operation: '$method $path'");
            }
            if ($method === 'POST' && $bulkId === null) {
                throw ScimError::invalidValue("'bulkId' is required for POST operations");
            }
            if ($pending !== []) {
                throw new ScimError(409, "Unresolved bulkId reference: '" . implode("', '", $pending) . "'");
            }
            $data = self::resolve($operation['data'] ?? null, $ids);
            if ($method !== 'DELETE' && !is_array($data)) {
                throw ScimError::invalidValue("'data' is required for $method operations");
            }
            $version = is_string($operation['version'] ?? null) ? $operation['version'] : null;
            $response = $this->route($token, $method, array_map('rawurldecode', $segments), [], fn(): array => $data,
                ['if-match' => $version], 0);
        } catch (ScimError $e) {
            $response = self::error($e);
        } catch (Throwable $e) {
            $this->log('err', "Bulk $method $path -> 500: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $response = self::error(new ScimError(500, 'Internal server error'));
        }
        foreach ($response->headers as $header) {
            [$name, $value] = array_map('trim', explode(':', $header, 2));
            match (strtolower($name)) {
                'location' => $result['location'] = $value,
                'etag' => $result['version'] = $value,
                default => null,
            };
        }
        if ($response->status < 400 && $method === 'POST' && isset($response->body['id'])) {
            $ids[$bulkId] = $response->body['id'];
        }
        if ($method !== 'POST' && !isset($result['location']) && $path !== '') {
            // RFC 7644 3.7.3: a location for every operation except failed POSTs
            $result['location'] = $this->base . '/' . ltrim($path, '/');
        }
        $result['status'] = (string)$response->status;
        if ($response->status >= 400) {
            $result['response'] = $response->body;
        }
        return $result;
    }

    /** @return string[] bulkIds an operation references ("bulkId:<id>" in path or data, RFC 7644 3.7.2) */
    private static function bulkReferences(mixed $value): array
    {
        if (is_string($value)) {
            return preg_match_all('~bulkId:([^/\s"]+)~i', $value, $m) ? $m[1] : [];
        }
        return is_array($value) ? array_merge([], ...array_map([self::class, 'bulkReferences'], array_values($value))) : [];
    }

    /** Replaces "bulkId:<id>" references by the ids of the created resources */
    private static function resolve(mixed $value, array $ids): mixed
    {
        if (is_string($value)) {
            return preg_replace_callback('~bulkId:([^/\s"]+)~i', fn(array $m): string => $ids[$m[1]] ?? $m[0], $value);
        }
        return is_array($value) ? array_map(fn(mixed $item): mixed => self::resolve($item, $ids), $value) : $value;
    }

    /** SCIM representation with its version (RFC 7644 3.14): a weak ETag of the content */
    private function represent(User|Group $item): array
    {
        $resource = $item->toResource($this->base);
        $resource['meta']['version'] = 'W/"' . substr(hash('sha256', json_encode($resource)), 0, 32) . '"';
        return $resource;
    }

    /** If-Match / If-None-Match: a list of entity tags or "*", compared weakly like the examples of RFC 7644 3.14 */
    private static function versionMatches(?string $header, string $version): bool
    {
        $opaque = fn(string $tag): string => preg_replace('~^W/~i', '', trim($tag));
        foreach (($header === null) ? [] : explode(',', $header) as $tag) {
            if (trim($tag) === '*' || ($opaque($tag) !== '' && $opaque($tag) === $opaque($version))) {
                return true;
            }
        }
        return false;
    }

    /** POST .search (RFC 7644 3.4.3): the query as body */
    private function search(array $types, Token $token, array $body): Response
    {
        Schema::requireSchema($body, Schema::SEARCH);
        return $this->list($types, $token, $body);
    }

    /**
     * GET of a collection: filter, sorting (RFC 7644 3.4.2.3), pagination (3.4.2.4) and attribute projection.
     * Several types (root .search): a filter or sortBy attribute that one type does not know matches none of its
     * resources, or has no value for them.
     */
    private function list(array $types, Token $token, array $query): Response
    {
        $filter = $query['filter'] ?? '';
        if (!is_string($filter)) {
            throw new ScimError(400, "'filter' must be a string", 'invalidFilter');
        }
        $start = max(1, (int)(is_numeric($query['startindex'] ?? null) ? $query['startindex'] : 1));
        $count = is_numeric($query['count'] ?? null) ? min(500, max(0, (int)$query['count'])) : 100;
        // without filter and sortBy, one type is paged by SQL: the full syncs of IdPs page through all users
        if ($filter === '' && ($query['sortby'] ?? '') === '' && count($types) === 1) {
            [$items, $total] = $this->service($types[0])->page($token, $start - 1, $count);
            $page = array_map(fn(User|Group $item): array => Schema::project($this->represent($item), $types[0],
                $query['attributes'] ?? null, $query['excludedattributes'] ?? null), $items);
            return new Response(200, self::listResponse($page, $total, $start));
        }
        $parsedByType = array_fill_keys($types, null);
        if ($filter !== '' && count($types) === 1) {
            $parsedByType[$types[0]] = Filter::parse($filter, $types[0]);
        } elseif ($filter !== '') {
            // several types: an attribute a type lacks is unassigned for it (RFC 7644 3.4.2.1), e.g. not (userName pr);
            // only an attribute no type knows is an error
            $unknown = null;
            foreach ($types as $type) {
                $parsedByType[$type] = Filter::parse($filter, $type, true);
                $unknown = ($unknown === null) ? $parsedByType[$type]->unknown : array_intersect($unknown, $parsedByType[$type]->unknown);
            }
            if ($unknown !== []) {
                throw new ScimError(400, "Invalid filter: Unknown attribute '" . reset($unknown) . "'", 'invalidFilter');
            }
        }
        // The resources are read in batches (FilterSql narrows them where it can) and checked against the filter;
        // only the requested page is kept, or with sortBy the sort keys of all matches, so memory does not grow
        // with the number of resources. The time still does for filters SQL cannot narrow.
        $sort = self::sortSpec($types, $query);
        $total = 0;
        $window = [];
        $keys = [];
        foreach ($types as $type) {
            $parsed = $parsedByType[$type];
            for ($offset = 0; ; $offset += $this->batch) {
                $items = $this->service($type)->list($token, $parsed, $offset, $this->batch);
                foreach ($items as $item) {
                    $resource = $this->represent($item);
                    if ($parsed !== null && !$parsed->matches($resource)) {
                        continue;
                    }
                    if ($sort !== null) {
                        $keys[] = [isset($sort['specs'][$type]) ? Filter::sortValue($sort['specs'][$type], $resource) : null, $type, $resource['id']];
                    } elseif ($total >= $start - 1 && count($window) < $count) {
                        $window[] = [$type, $resource];
                    }
                    $total++;
                }
                if (count($items) < $this->batch) {
                    break;
                }
            }
        }
        if ($sort !== null && $count > 0) {
            $direction = ($sort['order'] === 'descending') ? -1 : 1;
            usort($keys, fn(array $a, array $b): int => $direction * Filter::sortCompare($a[0], $b[0], $sort['exact']));
            foreach (array_slice($keys, $start - 1, $count) as [, $type, $id]) {
                try {
                    $window[] = [$type, $this->represent($this->service($type)->get($token, $id))];
                } catch (ScimError $e) {
                    // deleted since the scan
                    if ($e->status !== 404) {
                        throw $e;
                    }
                }
            }
        }
        $page = array_map(fn(array $match): array => Schema::project($match[1], $match[0], $query['attributes'] ?? null,
            $query['excludedattributes'] ?? null), $window);
        return new Response(200, self::listResponse($page, $total, $start));
    }

    /**
     * sortBy and sortOrder of a list request, null without sortBy
     *
     * @return ?array{specs: array<string, array>, order: string, exact: bool} the sort attribute of each type that has it
     * @throws ScimError invalidValue for an unknown sortBy attribute or sortOrder
     */
    private static function sortSpec(array $types, array $query): ?array
    {
        $sortBy = $query['sortby'] ?? '';
        if (!is_string($sortBy) || $sortBy === '') {
            return null;
        }
        $order = strtolower(is_string($query['sortorder'] ?? null) ? $query['sortorder'] : 'ascending');
        if (!in_array($order, ['ascending', 'descending'], true)) {
            throw ScimError::invalidValue("'sortOrder' must be ascending or descending");
        }
        $specs = array_filter(array_combine($types, array_map(fn(string $type): ?array => Schema::attribute($type, $sortBy), $types)));
        if ($specs === []) {
            throw ScimError::invalidValue("Unknown sortBy attribute '$sortBy'");
        }
        return ['specs' => $specs, 'order' => $order, 'exact' => Schema::caseExact(reset($specs))];
    }

    private function service(string $type): UserService|GroupService
    {
        return ($type === 'User') ? $this->users : $this->groups;
    }

    private function log(string $priority, string $message): void
    {
        try {
            // messages can quote request values: capped, so an entry stays small
            $message = mb_strimwidth($message, 0, 2000, '…');
            $this->redis?->lPush('SCIM_LOG', json_encode(['time' => time(), 'priority' => $priority, 'task' => 'SCIM', 'message' => $message]));
            // the length of the other logs ($LOG_LINES), trimmed here so no cron job is needed for it
            $this->redis?->lTrim('SCIM_LOG', 0, max(1, $this->logLines) - 1);
        } catch (Throwable) {
            // logging must not break the response
        }
    }

    private static function listResponse(array $resources, int $total, int $start): array
    {
        return ['schemas' => [Schema::LIST], 'totalResults' => $total, 'startIndex' => $start,
            'itemsPerPage' => count($resources), 'Resources' => $resources];
    }

    /** @throws ScimError 405 with Allow header */
    private static function allow(string $method, string $allowed): void
    {
        if (!in_array($method, explode(', ', $allowed), true)) {
            throw new ScimError(405, 'Method not allowed', null, ['Allow: ' . $allowed . ', OPTIONS']);
        }
    }

    /** JSON body (RFC 7644 3.1) with lowercased keys */
    private static function body(string $body, string $contentType): array
    {
        $mime = strtolower(trim(explode(';', $contentType)[0]));
        if ($mime !== '' && !in_array($mime, ['application/scim+json', 'application/json'], true)) {
            throw new ScimError(415, "Unsupported media type '$mime', use application/scim+json");
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ScimError(400, (trim($body) === '') ? 'Request body is required' : 'Request body is not valid JSON', 'invalidSyntax');
        }
        return Schema::lowerKeys($decoded);
    }

    private static function error(ScimError $e): Response
    {
        $body = ['schemas' => [Schema::ERROR], 'status' => (string)$e->status, 'detail' => $e->getMessage()];
        if ($e->scimType !== null) {
            $body['scimType'] = $e->scimType;
        }
        $headers = $e->headers;
        if ($e->status === 401 && !preg_grep('/^WWW-Authenticate:/i', $headers)) {
            $headers[] = 'WWW-Authenticate: Bearer realm="mailcow SCIM"';
        }
        return new Response($e->status, $body, $headers);
    }
}
