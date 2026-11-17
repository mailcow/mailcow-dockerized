<?php

declare(strict_types=1);

namespace Mailcow\Scim\Model;

use Mailcow\Scim\ScimError;

/**
 * The SCIM data model of mailcow: schema URNs, the attributes of the User and Group resources, attribute
 * projection (RFC 7644 3.9), the discovery documents (RFC 7643 5-7) and the JSON helpers all of them share.
 *
 * Attribute names are case-insensitive (RFC 7643 2.1): request bodies are lowercased on read, lookups in
 * resources use key().
 */
class Schema
{
    public const USER = 'urn:ietf:params:scim:schemas:core:2.0:User';
    public const GROUP = 'urn:ietf:params:scim:schemas:core:2.0:Group';
    public const EXT = 'urn:mailcow:params:scim:schemas:extension:mailcow:2.0:User';
    /** RFC 7643 4.3, not stored: its attributes (department, manager of Entra ID) are ignored like IGNORED */
    public const ENTERPRISE = 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User';
    public const ERROR = 'urn:ietf:params:scim:api:messages:2.0:Error';
    public const LIST = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';
    public const PATCH = 'urn:ietf:params:scim:api:messages:2.0:PatchOp';
    public const SEARCH = 'urn:ietf:params:scim:api:messages:2.0:SearchRequest';
    public const BULK_REQUEST = 'urn:ietf:params:scim:api:messages:2.0:BulkRequest';
    public const BULK_RESPONSE = 'urn:ietf:params:scim:api:messages:2.0:BulkResponse';
    public const SPC = 'urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig';
    public const RESOURCE_TYPE = 'urn:ietf:params:scim:schemas:core:2.0:ResourceType';
    public const SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:Schema';

    /** Core schema of each resource type */
    public const TYPES = ['User' => self::USER, 'Group' => self::GROUP];

    /** Multi-valued complex attributes, the only ones with value filters (emails[type eq "work"]) */
    public const MULTI_VALUED = ['emails', 'members', 'groups'];

    /** Limits of POST /Bulk (RFC 7644 3.7.4): each operation can run several mailbox writes */
    public const BULK_MAX_OPERATIONS = 100;
    public const BULK_MAX_PAYLOAD = 1048576;

    /** Limit of any request body (a full PUT of a large group is longer than a bulk request) */
    public const MAX_PAYLOAD = 8388608;

    /**
     * Core User attributes mailcow does not store: accepted and ignored by PATCH like by POST/PUT, so default IdP
     * mappings (jobTitle => title, phoneNumbers) do not fail every update. password too: SCIM users sign in through
     * the IdP. Not 'groups': it is readOnly, membership changes go through the Group (RFC 7643 4.1.2).
     */
    /** Core sub-attributes mailcow does not store, ignored like IGNORED */
    public const IGNORED_SUB = ['name.middlename', 'name.honorificprefix', 'name.honorificsuffix', 'emails.display'];

    public const IGNORED = ['password', 'title', 'nickname', 'profileurl', 'usertype', 'preferredlanguage', 'locale', 'timezone',
        'phonenumbers', 'ims', 'photos', 'addresses', 'entitlements', 'roles', 'x509certificates'];

    private const META = ['resourcetype', 'created', 'lastmodified', 'location', 'version'];

    /** Attributes of each resource type (lowercase) => their sub-attributes */
    private const ATTRIBUTES = [
        'User' => [
            'schemas' => [], 'id' => [], 'externalid' => [], 'username' => [], 'displayname' => [], 'active' => [],
            'name' => ['formatted', 'givenname', 'familyname'],
            'emails' => ['value', 'type', 'primary'],
            'groups' => ['value', '$ref', 'display', 'type'],
            'meta' => self::META,
        ],
        'Group' => [
            'schemas' => [], 'id' => [], 'externalid' => [], 'displayname' => [],
            'members' => ['value', '$ref', 'type', 'display'],
            'meta' => self::META,
        ],
    ];

    /**
     * Parses an attribute name ("userName", "name.givenName", "<core URN>:members", "<extension URN>:template").
     *
     * @return ?array{ext: bool, parts: string[]} lowercase path, null when the resource type has no such attribute
     */
    public static function attribute(string $type, string $name): ?array
    {
        $lower = strtolower($name);
        $ext = false;
        foreach ([strtolower(self::TYPES[$type]) => false, strtolower(self::EXT) => true] as $urn => $isExt) {
            if ($lower === $urn || str_starts_with($lower, $urn . ':')) {
                $lower = substr($lower, strlen($urn) + 1);
                $ext = $isExt;
                break;
            }
        }
        if (str_contains($lower, ':')) {
            return null;
        }
        $parts = ($lower === '') ? [] : explode('.', $lower);
        if ($ext) {
            return ($type === 'User' && ($parts === [] || $parts === ['template'])) ? ['ext' => true, 'parts' => $parts] : null;
        }
        $known = self::ATTRIBUTES[$type];
        if ($parts === [] || count($parts) > 2 || !isset($known[$parts[0]])
            || (count($parts) === 2 && !in_array($parts[1], $known[$parts[0]], true))) {
            return null;
        }
        return ['ext' => false, 'parts' => $parts];
    }

    /**
     * Applies the `attributes` / `excludedAttributes` parameters (comma separated string or list). Unknown
     * attributes are ignored; `schemas` and `id` are always returned.
     *
     * @throws ScimError when both parameters are given
     */
    public static function project(array $resource, string $type, mixed $attributes, mixed $excluded): array
    {
        $include = self::attributeList($type, $attributes);
        $exclude = self::attributeList($type, $excluded);
        if ($include !== null && $exclude !== null) {
            throw ScimError::invalidValue("'attributes' and 'excludedAttributes' are mutually exclusive");
        }
        if ($include !== null) {
            $out = ['schemas' => $resource['schemas'], 'id' => $resource['id']];
            foreach ($include as $spec) {
                self::copy($resource, $out, $spec);
            }
            return $out;
        }
        foreach ($exclude ?? [] as $spec) {
            self::remove($resource, $spec);
        }
        return $resource;
    }

    /** id and externalId are caseExact (RFC 7643 3.1), all other strings are not */
    public static function caseExact(array $spec): bool
    {
        return !$spec['ext'] && in_array($spec['parts'], [['id'], ['externalid']], true);
    }

    /** @throws ScimError when both `attributes` and `excludedAttributes` are set; checked before a request writes */
    public static function checkProjection(array $query): void
    {
        // the same rule as project(): a parameter counts when it is not null, '' or []
        $set = fn(mixed $param): bool => !in_array($param, [null, '', []], true);
        if ($set($query['attributes'] ?? null) && $set($query['excludedattributes'] ?? null)) {
            throw ScimError::invalidValue("'attributes' and 'excludedAttributes' are mutually exclusive");
        }
    }

    /** True when `attributes` or `excludedAttributes` is set (PATCH answers 200 with the resource then) */
    public static function projects(array $query): bool
    {
        return ($query['attributes'] ?? '') !== '' || ($query['excludedattributes'] ?? '') !== '';
    }

    public static function serviceProviderConfig(string $base): array
    {
        return [
            'schemas' => [self::SPC],
            'patch' => ['supported' => true],
            'bulk' => ['supported' => true, 'maxOperations' => self::BULK_MAX_OPERATIONS, 'maxPayloadSize' => self::BULK_MAX_PAYLOAD],
            'filter' => ['supported' => true, 'maxResults' => 500],
            'changePassword' => ['supported' => false],
            'sort' => ['supported' => true],
            'etag' => ['supported' => true],
            'authenticationSchemes' => [[
                'name' => 'OAuth Bearer Token',
                'description' => 'Authentication with a SCIM token of the mailcow admin UI',
                'specUri' => 'https://www.rfc-editor.org/info/rfc6750',
                'type' => 'oauthbearertoken',
                'primary' => true,
            ]],
            'meta' => ['resourceType' => 'ServiceProviderConfig', 'location' => $base . '/ServiceProviderConfig'],
        ];
    }

    /**
     * @param bool $rename the token renames mailboxes on a changed userName (readWrite), else userName is immutable
     * @return array[] the RFC 7643 7 definitions of the served schemas
     */
    public static function schemas(string $base, bool $rename = false): array
    {
        $schemas = [
            [
                'id' => self::USER,
                'name' => 'User',
                'description' => 'User account, a mailcow mailbox',
                'attributes' => [
                    self::string('userName', 'Email address of the mailbox. A change renames the mailbox within its domain if the SCIM token allows it, else it is rejected.',
                        ['required' => true, 'uniqueness' => 'server', 'mutability' => $rename ? 'readWrite' : 'immutable']),
                    self::string('displayName', 'Name of the mailbox, the local part of userName when empty.'),
                    self::complex('name', false, 'Name of the mailbox (one string in mailcow); givenName and familyName are kept as the IdP sent them.', [
                        self::string('formatted', 'Full name.'),
                        self::string('givenName', 'Given name as the IdP sent it, else the first word of the name.'),
                        self::string('familyName', 'Family name as the IdP sent it, else the rest of the name.'),
                    ]),
                    self::boolean('active', 'false disallows the login, mail is still delivered. Only logins SCIM disallowed are allowed again by true.'),
                    self::complex('emails', true, 'Read-only, derived from userName.', [
                        self::string('value', 'Email address.', ['mutability' => 'readOnly']),
                        self::string('type', 'Always work.', ['mutability' => 'readOnly', 'canonicalValues' => ['work']]),
                        self::boolean('primary', 'Always true.', ['mutability' => 'readOnly']),
                    ], ['mutability' => 'readOnly']),
                    self::complex('groups', true, 'Groups of this provider the user is a member of. Read-only: membership changes through the Group.', [
                        self::string('value', 'id of the group.', ['mutability' => 'readOnly']),
                        self::string('$ref', 'URI of the group.', ['type' => 'reference', 'referenceTypes' => ['Group'], 'mutability' => 'readOnly']),
                        self::string('display', 'displayName of the group.', ['mutability' => 'readOnly']),
                        self::string('type', 'Always direct: groups cannot be nested.', ['mutability' => 'readOnly', 'canonicalValues' => ['direct']]),
                    ], ['mutability' => 'readOnly']),
                ],
            ],
            [
                'id' => self::GROUP,
                'name' => 'Group',
                'description' => 'Group, a mailcow mailbox group; its members are synced, everything else is maintained in mailcow',
                'attributes' => [
                    self::string('displayName', 'Name of the group, unique within its domain.', ['required' => true, 'uniqueness' => 'server']),
                    self::complex('members', true, 'Members of the group: users of this provider, groups cannot be nested.', [
                        self::string('value', 'id of the member.', ['mutability' => 'immutable']),
                        self::string('$ref', 'URI of the member.', ['type' => 'reference', 'referenceTypes' => ['User'], 'mutability' => 'immutable']),
                        self::string('type', 'Always User.', ['mutability' => 'immutable', 'canonicalValues' => ['User']]),
                        self::string('display', 'userName of the member.', ['mutability' => 'readOnly']),
                    ]),
                ],
            ],
            [
                'id' => self::EXT,
                'name' => 'MailcowUser',
                'description' => 'mailcow extension of the User',
                'attributes' => [
                    self::string('template', 'Selects the mailbox template through the attribute mapping of the SCIM token; a change applies the newly mapped template (a group template takes priority), like the identity provider sync. Responses return the mapped template.'),
                ],
            ],
        ];
        foreach ($schemas as $i => $schema) {
            $schemas[$i] = ['schemas' => [self::SCHEMA]] + $schema
                + ['meta' => ['resourceType' => 'Schema', 'location' => $base . '/Schemas/' . $schema['id']]];
        }
        return $schemas;
    }

    /** @return array[] */
    public static function resourceTypes(string $base): array
    {
        $types = [];
        foreach (['User' => 'Users', 'Group' => 'Groups'] as $type => $endpoint) {
            $types[] = [
                'schemas' => [self::RESOURCE_TYPE],
                'id' => $type,
                'name' => $type,
                'endpoint' => '/' . $endpoint,
                'description' => $type,
                'schema' => self::TYPES[$type],
                'schemaExtensions' => ($type === 'User') ? [['schema' => self::EXT, 'required' => false]] : [],
                'meta' => ['resourceType' => 'ResourceType', 'location' => $base . '/ResourceTypes/' . $type],
            ];
        }
        return $types;
    }

    /** Canonical key of $key in $array, compared case-insensitively */
    public static function key(array $array, string $key): ?string
    {
        if (array_key_exists($key, $array)) {
            return $key;
        }
        foreach ($array as $k => $_) {
            if (is_string($k) && strcasecmp($k, $key) === 0) {
                return $k;
            }
        }
        return null;
    }

    /** Value of $key in $array, compared case-insensitively */
    public static function get(array $array, string $key): mixed
    {
        $k = self::key($array, $key);
        return ($k === null) ? null : $array[$k];
    }

    /** Lowercases all object keys of a decoded request body */
    public static function lowerKeys(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        $out = [];
        foreach ($data as $k => $v) {
            $out[is_string($k) ? strtolower($k) : $k] = self::lowerKeys($v);
        }
        return $out;
    }

    /**
     * Boolean of a request: true/false, 0/1 and their strings in any case (Entra ID sends "False").
     *
     * @throws ScimError for anything else, a cast would turn "False" into true
     */
    public static function bool(mixed $value, string $attribute = 'active'): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = is_scalar($value) ? strtolower(trim((string)$value)) : null;
        return match ($value) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw ScimError::invalidValue("'$attribute' must be a boolean"),
        };
    }

    /** @throws ScimError unless the (lowercased) body lists the schema URN */
    public static function requireSchema(array $body, string $urn): void
    {
        if (!is_array($body['schemas'] ?? null)) {
            throw new ScimError(400, "'schemas' is required", 'invalidSyntax');
        }
        foreach ($body['schemas'] as $schema) {
            if (is_string($schema) && strcasecmp($schema, $urn) === 0) {
                return;
            }
        }
        throw ScimError::invalidValue("'schemas' must include '$urn'");
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /** RFC 3339 time of a DB value */
    public static function time(?string $value): ?string
    {
        return ($value === null || $value === '') ? null : (new \DateTime($value))->format(\DateTime::RFC3339);
    }

    /** @return ?array[] specs of a known attribute list, null when the parameter is not set */
    private static function attributeList(string $type, mixed $param): ?array
    {
        if ($param === null || $param === '' || $param === []) {
            return null;
        }
        $specs = [];
        foreach (is_array($param) ? $param : explode(',', (string)$param) as $name) {
            $spec = is_string($name) ? self::attribute($type, trim($name)) : null;
            if ($spec !== null) {
                $specs[] = $spec;
            }
        }
        return $specs;
    }

    private static function copy(array $resource, array &$out, array $spec): void
    {
        if ($spec['ext']) {
            $k = self::key($resource, self::EXT);
            if ($k !== null) {
                $out[$k] = $resource[$k];
            }
            return;
        }
        $k = self::key($resource, $spec['parts'][0]);
        if ($k === null) {
            return;
        }
        if (count($spec['parts']) === 1) {
            $out[$k] = $resource[$k];
            return;
        }
        $value = $resource[$k];
        if (!is_array($value)) {
            return;
        }
        if (!array_is_list($value)) {
            $sub = self::key($value, $spec['parts'][1]);
            if ($sub !== null) {
                $out[$k] = array_merge($out[$k] ?? [], [$sub => $value[$sub]]);
            }
            return;
        }
        foreach ($value as $i => $item) {
            $sub = is_array($item) ? self::key($item, $spec['parts'][1]) : null;
            if ($sub !== null) {
                $out[$k][$i][$sub] = $item[$sub];
            }
        }
    }

    private static function remove(array &$resource, array $spec): void
    {
        $k = self::key($resource, $spec['ext'] ? self::EXT : $spec['parts'][0]);
        // schemas and id are returned=always
        if ($k === null || $k === 'schemas' || $k === 'id') {
            return;
        }
        if ($spec['ext'] || count($spec['parts']) === 1) {
            unset($resource[$k]);
            return;
        }
        if (!is_array($resource[$k])) {
            return;
        }
        $items = array_is_list($resource[$k]) ? $resource[$k] : [$resource[$k]];
        foreach ($items as $i => $item) {
            $sub = is_array($item) ? self::key($item, $spec['parts'][1]) : null;
            if ($sub !== null) {
                unset($items[$i][$sub]);
            }
        }
        $resource[$k] = array_is_list($resource[$k]) ? $items : $items[0];
    }

    private static function string(string $name, string $description, array $overrides = []): array
    {
        return array_merge(['name' => $name, 'type' => 'string', 'multiValued' => false, 'description' => $description,
            'required' => false, 'caseExact' => false, 'mutability' => 'readWrite', 'returned' => 'default', 'uniqueness' => 'none'], $overrides);
    }

    private static function boolean(string $name, string $description, array $overrides = []): array
    {
        return array_merge(['name' => $name, 'type' => 'boolean', 'multiValued' => false, 'description' => $description,
            'required' => false, 'mutability' => 'readWrite', 'returned' => 'default'], $overrides);
    }

    private static function complex(string $name, bool $multi, string $description, array $sub, array $overrides = []): array
    {
        return array_merge(['name' => $name, 'type' => 'complex', 'multiValued' => $multi, 'description' => $description,
            'required' => false, 'mutability' => 'readWrite', 'returned' => 'default', 'subAttributes' => $sub], $overrides);
    }
}
