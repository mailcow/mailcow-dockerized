<?php

namespace LdapRecord\Models\Concerns;

use Closure;
use InvalidArgumentException;
use LdapRecord\Models\Scope;

/** @mixin \LdapRecord\Models\Model */
trait HasGlobalScopes
{
    /**
     * Register a new global scope on the model.
     *
     * @throws InvalidArgumentException
     */
    public static function addGlobalScope(Scope|Closure|string $scope, ?Closure $implementation = null): void
    {
        if (is_string($scope) && ! is_null($implementation)) {
            static::$globalScopes[static::class][$scope] = $implementation;
        } elseif ($scope instanceof Closure) {
            static::$globalScopes[static::class][spl_object_hash($scope)] = $scope;
        } elseif ($scope instanceof Scope) {
            static::$globalScopes[static::class][get_class($scope)] = $scope;
        } else {
            throw new InvalidArgumentException('Global scope must be an instance of Closure or Scope.');
        }
    }

    /**
     * Determine if a model has a global scope.
     */
    public static function hasGlobalScope(Scope|string $scope): bool
    {
        return ! is_null(static::getGlobalScope($scope));
    }

    /**
     * Get a global scope registered with the model.
     */
    public static function getGlobalScope(Scope|string $scope): Scope|Closure|null
    {
        if (array_key_exists(static::class, static::$globalScopes)) {
            $scopeName = is_string($scope) ? $scope : get_class($scope);

            return array_key_exists($scopeName, static::$globalScopes[static::class])
                ? static::$globalScopes[static::class][$scopeName]
                : null;
        }

        return null;
    }

    /**
     * Get the global scopes for this class instance.
     */
    public function getGlobalScopes(): array
    {
        return array_key_exists(static::class, static::$globalScopes)
            ? static::$globalScopes[static::class]
            : [];
    }
}
