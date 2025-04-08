<?php

namespace OAuth2;

use InvalidArgumentException;
use OAuth2\Storage\Memory;
use OAuth2\Storage\ScopeInterface as ScopeStorageInterface;

/**
* @see ScopeInterface
*/
class Scope implements ScopeInterface
{
    protected $storage;

    /**
     * Constructor
     *
     * @param mixed $storage - Either an array of supported scopes, or an instance of OAuth2\Storage\ScopeInterface
     *
     * @throws InvalidArgumentException
     */
    public function __construct($storage = null)
    {
        if (is_null($storage) || is_array($storage)) {
            $storage = new Memory((array) $storage);
        }

        if (!$storage instanceof ScopeStorageInterface) {
            throw new InvalidArgumentException("Argument 1 to OAuth2\Scope must be null, an array, or instance of OAuth2\Storage\ScopeInterface");
        }

        $this->storage = $storage;
    }

    /**
     * Check if everything in required scope is contained in available scope.
     *
     * @param string $required_scope  - A space-separated string of scopes.
     * @param string $available_scope - A space-separated string of scopes.
     * @return bool                   - TRUE if everything in required scope is contained in available scope and FALSE
     *                                  if it isn't.
     *
     * @see http://tools.ietf.org/html/rfc6749#section-7
     *
     * @ingroup oauth2_section_7
     */
    public function checkScope($required_scope, $available_scope)
    {
        $required_scope = explode(' ', trim($required_scope));
        $available_scope = explode(' ', trim($available_scope));

        return (count(array_diff($required_scope, $available_scope)) == 0);
    }

    /**
     * Check if the provided scope exists in storage.
     *
     * @param string $scope - A space-separated string of scopes.
     * @return bool         - TRUE if it exists, FALSE otherwise.
     */
    public function scopeExists($scope)
    {
        // Check reserved scopes first.
        $scope = explode(' ', trim($scope));
        $reservedScope = $this->getReservedScopes();
        $nonReservedScopes = array_diff($scope, $reservedScope);
        if (count($nonReservedScopes) == 0) {
            return true;
        } else {
            // Check the storage for non-reserved scopes.
            $nonReservedScopes = implode(' ', $nonReservedScopes);

            return $this->storage->scopeExists($nonReservedScopes);
        }
    }

    /**
     * @param RequestInterface $request
     * @return string
     */
    public function getScopeFromRequest(RequestInterface $request)
    {
        // "scope" is valid if passed in either POST or QUERY
        return $request->request('scope', $request->query('scope'));
    }

    /**
     * @param null $client_id
     * @return mixed
     */
    public function getDefaultScope($client_id = null)
    {
        return $this->storage->getDefaultScope($client_id);
    }

    /**
     * Get reserved scopes needed by the server.
     *
     * In case OpenID Connect is used, these scopes must include:
     * 'openid', offline_access'.
     *
     * @return array - An array of reserved scopes.
     */
    public function getReservedScopes()
    {
        return array('openid', 'offline_access');
    }
}
