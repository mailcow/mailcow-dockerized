<?php

declare(strict_types=1);

namespace Mailcow\Scim\Model;

use Mailcow\Scim\ScimError;

/**
 * One operation of a PATCH request (RFC 7644 3.5.2), validated but not yet applied. The services apply all
 * operations to a copy of the resource first and write once, so a PATCH is atomic.
 */
class PatchOp
{
    /** @param ?array $path parsed path (Filter::path()), null for a path-less operation */
    private function __construct(public readonly string $op, public readonly ?array $path, public readonly mixed $value,
        public readonly bool $hasValue, public readonly bool $ignored)
    {
    }

    /**
     * Operations of a (lowercased) PatchOp body. `op` is case-insensitive (Entra ID sends "Replace").
     *
     * @return self[]
     * @throws ScimError
     */
    public static function parse(array $body, string $type): array
    {
        Schema::requireSchema($body, Schema::PATCH);
        $operations = $body['operations'] ?? null;
        if (!is_array($operations) || !array_is_list($operations) || $operations === []) {
            throw new ScimError(400, "'Operations' must be a non-empty array", 'invalidSyntax');
        }
        $ops = [];
        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                throw new ScimError(400, 'Each operation must be an object', 'invalidSyntax');
            }
            $op = strtolower(is_string($operation['op'] ?? null) ? $operation['op'] : '');
            if (!in_array($op, ['add', 'replace', 'remove'], true)) {
                throw new ScimError(400, "Unsupported operation '$op'", 'invalidSyntax');
            }
            $hasValue = array_key_exists('value', $operation);
            if ($op !== 'remove' && !$hasValue) {
                throw ScimError::invalidValue("'value' is required for $op operations");
            }
            $path = $operation['path'] ?? null;
            if ($path === null) {
                if ($op === 'remove') {
                    throw new ScimError(400, "'path' is required for remove operations", 'noTarget');
                }
                if (!is_array($operation['value']) || array_is_list($operation['value'])) {
                    throw ScimError::invalidValue("'value' must be an object when 'path' is omitted");
                }
                $ops[] = new self($op, null, $operation['value'], true, false);
                continue;
            }
            if (!is_string($path) || trim($path) === '') {
                throw new ScimError(400, "'path' must be a non-empty string", 'invalidPath');
            }
            $parsed = Filter::path($path, $type);
            $ops[] = new self($op, $parsed, $operation['value'] ?? null, $hasValue, $parsed === null);
        }
        return $ops;
    }
}
