<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api;

use Symfony\Component\Yaml\Yaml;

/** data/web/api/openapi.yaml against json_api.php: every route documented, every documented route real, the spec consistent */
class OpenApiSpecTest extends ApiTestCase
{
    private const SPEC = __DIR__ . '/../../../data/web/api/openapi.yaml';
    private const JSON_API = __DIR__ . '/../../../data/web/json_api.php';

    /** Routes of the mailcow UI that an API client cannot use, with the reason */
    private const UNDOCUMENTED = [
        // WebAuthn ceremonies of the browser: the challenge lives in the UI session
        'add/fido2-registration' => 'WebAuthn',
        'add/webauthn-tfa-registration' => 'WebAuthn',
        'get/fido2-registration' => 'WebAuthn',
        'get/fido2-get-args' => 'WebAuthn',
        'get/webauthn-tfa-registration' => 'WebAuthn',
        'get/webauthn-tfa-get-args' => 'WebAuthn',
        // the own account of a UI session (domain admin, mailbox user): an API key gets an empty answer
        'edit/self' => 'own account',
        // renames a FIDO2 key of the logged-in account, the API account has none
        'edit/fido2-fn' => 'own account',
    ];

    private static ?array $spec = null;

    private static function spec(): array
    {
        return self::$spec ??= Yaml::parseFile(self::SPEC);
    }

    /** @return list<array{0: string, 1: string, 2: array}> path, method, operation */
    private static function operations(): array
    {
        $operations = [];
        foreach (self::spec()['paths'] as $path => $item) {
            foreach ($item as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $operations[] = [$path, $method, $operation];
                }
            }
        }
        return $operations;
    }

    public function testEveryRouteIsDocumented(): void
    {
        $paths = array_keys(self::spec()['paths']);
        $missing = [];
        foreach (RouteMap::fromFile(self::JSON_API) as $route) {
            $covered = isset(self::UNDOCUMENTED[$route]) || !empty(array_filter($paths, fn(string $path): bool => RouteMap::covers($path, $route)));
            if (!$covered) {
                $missing[] = $route;
            }
        }
        $this->assertSame([], $missing, 'routes of json_api.php missing in openapi.yaml');
    }

    public function testTheUndocumentedRoutesExist(): void
    {
        $this->assertSame([], array_values(array_diff(array_keys(self::UNDOCUMENTED), RouteMap::fromFile(self::JSON_API))));
    }

    public function testEveryDocumentedRouteExists(): void
    {
        $unknown = [];
        foreach (self::operations() as [$path, $method, $operation]) {
            $route = preg_replace_callback('/\{([^}]+)\}/', fn(array $m): string => self::exampleOf($operation, $m[1]), $path);
            $action = explode('/', trim(preg_replace('#^/api/v1/#', '', $path), '/'))[0];
            $body = match ($action) {
                'edit' => ['items' => [], 'attr' => (object)[]],
                'delete' => [],
                default => (object)[],
            };
            $result = ($method === 'get') ? $this->get($route, ['allow_error' => true]) : $this->post($route, $body, ['allow_error' => true]);
            $answer = $result->json();
            if ($result->status === 404 && is_array($answer) && ($answer['type'] ?? '') === 'error') {
                $unknown[] = strtoupper($method) . ' ' . $path . ': ' . ($answer['msg'] ?? '');
            }
        }
        $this->assertSame([], $unknown, 'documented routes that json_api.php does not know');
    }

    public function testMethodsFollowTheAction(): void
    {
        foreach (self::operations() as [$path, $method]) {
            $action = explode('/', trim(preg_replace('#^/api/v1/#', '', $path), '/'))[0];
            $this->assertSame(($action === 'get') ? 'get' : 'post', $method, $path);
        }
    }

    public function testOperationsAreComplete(): void
    {
        $tags = array_column(self::spec()['tags'], 'name');
        $ids = [];
        foreach (self::operations() as [$path, $method, $operation]) {
            $where = strtoupper($method) . ' ' . $path;
            $this->assertNotEmpty($operation['summary'] ?? null, "$where: summary");
            $this->assertNotEmpty($operation['operationId'] ?? null, "$where: operationId");
            $ids[] = $operation['operationId'];
            $this->assertNotEmpty($operation['tags'] ?? [], "$where: tags");
            $this->assertSame([], array_values(array_diff($operation['tags'] ?? [], $tags)), "$where: tags not in the tag list");
            $this->assertArrayHasKey('200', $operation['responses'] ?? [], "$where: 200 response");
            $this->assertArrayHasKey('401', $operation['responses'] ?? [], "$where: 401 response");
            preg_match_all('/\{([^}]+)\}/', $path, $placeholders);
            $declared = array_column(array_filter($operation['parameters'] ?? [], fn(array $p): bool => ($p['in'] ?? '') === 'path'), 'name');
            sort($declared);
            $expected = $placeholders[1];
            sort($expected);
            $this->assertSame($expected, $declared, "$where: path parameters");
        }
        $this->assertSame(array_values(array_unique($ids)), $ids, 'operationIds must be unique');
    }

    public function testEveryTagIsUsed(): void
    {
        $used = array_merge(...array_map(fn(array $op): array => $op[2]['tags'] ?? [], self::operations()));
        $this->assertSame([], array_values(array_diff(array_column(self::spec()['tags'], 'name'), $used)));
    }

    public function testReferencesResolve(): void
    {
        $refs = [];
        $spec = self::spec();
        array_walk_recursive($spec, function (mixed $value, mixed $key) use (&$refs): void {
            if ($key === '$ref') {
                $refs[] = $value;
            }
        });
        foreach (array_unique($refs) as $ref) {
            $node = self::spec();
            foreach (explode('/', substr($ref, 2)) as $part) {
                $this->assertIsArray($node, $ref);
                $this->assertArrayHasKey(str_replace(['~1', '~0'], ['/', '~'], $part), $node, "unresolved $ref");
                $node = $node[str_replace(['~1', '~0'], ['/', '~'], $part)];
            }
        }
    }

    private static function exampleOf(array $operation, string $name): string
    {
        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (($parameter['name'] ?? null) === $name) {
                $example = $parameter['example'] ?? $parameter['schema']['example'] ?? $parameter['schema']['enum'][0] ?? 'test';
                return rawurlencode((string)$example);
            }
        }
        return 'test';
    }
}
