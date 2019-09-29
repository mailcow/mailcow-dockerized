<?php

namespace OAuth2\Storage;

use phpcassa\ColumnFamily;
use phpcassa\ColumnSlice;
use phpcassa\Connection\ConnectionPool;
use OAuth2\OpenID\Storage\UserClaimsInterface;
use OAuth2\OpenID\Storage\AuthorizationCodeInterface as OpenIDAuthorizationCodeInterface;
use InvalidArgumentException;

/**
 * Cassandra storage for all storage types
 *
 * To use, install "thobbs/phpcassa" via composer:
 * <code>
 *     composer require thobbs/phpcassa:dev-master
 * </code>
 *
 * Once this is done, instantiate the connection:
 * <code>
 *     $cassandra = new \phpcassa\Connection\ConnectionPool('oauth2_server', array('127.0.0.1:9160'));
 * </code>
 *
 * Then, register the storage client:
 * <code>
 *     $storage = new OAuth2\Storage\Cassandra($cassandra);
 *     $storage->setClientDetails($client_id, $client_secret, $redirect_uri);
 * </code>
 *
 * @see test/lib/OAuth2/Storage/Bootstrap::getCassandraStorage
 */
class Cassandra implements AuthorizationCodeInterface,
    AccessTokenInterface,
    ClientCredentialsInterface,
    UserCredentialsInterface,
    RefreshTokenInterface,
    JwtBearerInterface,
    ScopeInterface,
    PublicKeyInterface,
    UserClaimsInterface,
    OpenIDAuthorizationCodeInterface
{

    private $cache;

    /**
     * @var ConnectionPool
     */
    protected $cassandra;

    /**
     * @var array
     */
    protected $config;

    /**
     * Cassandra Storage! uses phpCassa
     *
     * @param ConnectionPool|array $connection
     * @param array                $config
     *
     * @throws InvalidArgumentException
     */
    public function __construct($connection = array(), array $config = array())
    {
        if ($connection instanceof ConnectionPool) {
            $this->cassandra = $connection;
        } else {
            if (!is_array($connection)) {
                throw new InvalidArgumentException('First argument to OAuth2\Storage\Cassandra must be an instance of phpcassa\Connection\ConnectionPool or a configuration array');
            }
            $connection = array_merge(array(
                'keyspace' => 'oauth2',
                'servers'  => null,
            ), $connection);

            $this->cassandra = new ConnectionPool($connection['keyspace'], $connection['servers']);
        }

        $this->config = array_merge(array(
            // cassandra config
            'column_family' => 'auth',

            // key names
            'client_key' => 'oauth_clients:',
            'access_token_key' => 'oauth_access_tokens:',
            'refresh_token_key' => 'oauth_refresh_tokens:',
            'code_key' => 'oauth_authorization_codes:',
            'user_key' => 'oauth_users:',
            'jwt_key' => 'oauth_jwt:',
            'scope_key' => 'oauth_scopes:',
            'public_key_key'  => 'oauth_public_keys:',
        ), $config);
    }

    /**
     * @param $key
     * @return bool|mixed
     */
    protected function getValue($key)
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $cf = new ColumnFamily($this->cassandra, $this->config['column_family']);

        try {
            $value = $cf->get($key, new ColumnSlice("", ""));
            $value = array_shift($value);
        } catch (\cassandra\NotFoundException $e) {
            return false;
        }

        return json_decode($value, true);
    }

    /**
     * @param $key
     * @param $value
     * @param int $expire
     * @return bool
     */
    protected function setValue($key, $value, $expire = 0)
    {
        $this->cache[$key] = $value;

        $cf = new ColumnFamily($this->cassandra, $this->config['column_family']);

        $str = json_encode($value);
        if ($expire > 0) {
            try {
                $seconds = $expire - time();
                // __data key set as C* requires a field, note: max TTL can only be 630720000 seconds
                $cf->insert($key, array('__data' => $str), null, $seconds);
            } catch (\Exception $e) {
                return false;
            }
        } else {
            try {
                // __data key set as C* requires a field
                $cf->insert($key, array('__data' => $str));
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $key
     * @return bool
     */
    protected function expireValue($key)
    {
        unset($this->cache[$key]);

        $cf = new ColumnFamily($this->cassandra, $this->config['column_family']);

        if ($cf->get_count($key) > 0) {
            try {
                // __data key set as C* requires a field
                $cf->remove($key, array('__data'));
            } catch (\Exception $e) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $code
     * @return bool|mixed
     */
    public function getAuthorizationCode($code)
    {
        return $this->getValue($this->config['code_key'] . $code);
    }

    /**
     * @param string $authorization_code
     * @param mixed  $client_id
     * @param mixed  $user_id
     * @param string $redirect_uri
     * @param int    $expires
     * @param string $scope
     * @param string $id_token
     * @return bool
     */
    public function setAuthorizationCode($authorization_code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null)
    {
        return $this->setValue(
            $this->config['code_key'] . $authorization_code,
            compact('authorization_code', 'client_id', 'user_id', 'redirect_uri', 'expires', 'scope', 'id_token'),
            $expires
        );
    }

    /**
     * @param string $code
     * @return bool
     */
    public function expireAuthorizationCode($code)
    {
        $key = $this->config['code_key'] . $code;
        unset($this->cache[$key]);

        return $this->expireValue($key);
    }

    /**
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function checkUserCredentials($username, $password)
    {
        if ($user = $this->getUser($username)) {
            return $this->checkPassword($user, $password);
        }

        return false;
    }

    /**
     * plaintext passwords are bad!  Override this for your application
     *
     * @param array  $user
     * @param string $password
     * @return bool
     */
    protected function checkPassword($user, $password)
    {
        return $user['password'] == $this->hashPassword($password);
    }

    // use a secure hashing algorithm when storing passwords. Override this for your application
    protected function hashPassword($password)
    {
        return sha1($password);
    }

    /**
     * @param string $username
     * @return array|bool|false
     */
    public function getUserDetails($username)
    {
        return $this->getUser($username);
    }

    /**
     * @param string $username
     * @return array|bool
     */
    public function getUser($username)
    {
        if (!$userInfo = $this->getValue($this->config['user_key'] . $username)) {
            return false;
        }

        // the default behavior is to use "username" as the user_id
        return array_merge(array(
            'user_id' => $username,
        ), $userInfo);
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $first_name
     * @param string $last_name
     * @return bool
     */
    public function setUser($username, $password, $first_name = null, $last_name = null)
    {
        $password = $this->hashPassword($password);

        return $this->setValue(
            $this->config['user_key'] . $username,
            compact('username', 'password', 'first_name', 'last_name')
        );
    }

    /**
     * @param mixed  $client_id
     * @param string $client_secret
     * @return bool
     */
    public function checkClientCredentials($client_id, $client_secret = null)
    {
        if (!$client = $this->getClientDetails($client_id)) {
            return false;
        }

        return isset($client['client_secret'])
            && $client['client_secret'] == $client_secret;
    }

    /**
     * @param $client_id
     * @return bool
     */
    public function isPublicClient($client_id)
    {
        if (!$client = $this->getClientDetails($client_id)) {
            return false;
        }

        return empty($client['client_secret']);
    }

    /**
     * @param $client_id
     * @return array|bool|mixed
     */
    public function getClientDetails($client_id)
    {
        return $this->getValue($this->config['client_key'] . $client_id);
    }

    /**
     * @param $client_id
     * @param null $client_secret
     * @param null $redirect_uri
     * @param null $grant_types
     * @param null $scope
     * @param null $user_id
     * @return bool
     */
    public function setClientDetails($client_id, $client_secret = null, $redirect_uri = null, $grant_types = null, $scope = null, $user_id = null)
    {
        return $this->setValue(
            $this->config['client_key'] . $client_id,
            compact('client_id', 'client_secret', 'redirect_uri', 'grant_types', 'scope', 'user_id')
        );
    }

    /**
     * @param $client_id
     * @param $grant_type
     * @return bool
     */
    public function checkRestrictedGrantType($client_id, $grant_type)
    {
        $details = $this->getClientDetails($client_id);
        if (isset($details['grant_types'])) {
            $grant_types = explode(' ', $details['grant_types']);

            return in_array($grant_type, (array) $grant_types);
        }

        // if grant_types are not defined, then none are restricted
        return true;
    }

    /**
     * @param $refresh_token
     * @return bool|mixed
     */
    public function getRefreshToken($refresh_token)
    {
        return $this->getValue($this->config['refresh_token_key'] . $refresh_token);
    }

    /**
     * @param $refresh_token
     * @param $client_id
     * @param $user_id
     * @param $expires
     * @param null $scope
     * @return bool
     */
    public function setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope = null)
    {
        return $this->setValue(
            $this->config['refresh_token_key'] . $refresh_token,
            compact('refresh_token', 'client_id', 'user_id', 'expires', 'scope'),
            $expires
        );
    }

    /**
     * @param $refresh_token
     * @return bool
     */
    public function unsetRefreshToken($refresh_token)
    {
        return $this->expireValue($this->config['refresh_token_key'] . $refresh_token);
    }

    /**
     * @param string $access_token
     * @return array|bool|mixed|null
     */
    public function getAccessToken($access_token)
    {
        return $this->getValue($this->config['access_token_key'].$access_token);
    }

    /**
     * @param string $access_token
     * @param mixed $client_id
     * @param mixed $user_id
     * @param int $expires
     * @param null $scope
     * @return bool
     */
    public function setAccessToken($access_token, $client_id, $user_id, $expires, $scope = null)
    {
        return $this->setValue(
            $this->config['access_token_key'].$access_token,
            compact('access_token', 'client_id', 'user_id', 'expires', 'scope'),
            $expires
        );
    }

    /**
     * @param $access_token
     * @return bool
     */
    public function unsetAccessToken($access_token)
    {
        return $this->expireValue($this->config['access_token_key'] . $access_token);
    }

    /**
     * @param $scope
     * @return bool
     */
    public function scopeExists($scope)
    {
        $scope = explode(' ', $scope);

        $result = $this->getValue($this->config['scope_key'].'supported:global');

        $supportedScope = explode(' ', (string) $result);

        return (count(array_diff($scope, $supportedScope)) == 0);
    }

    /**
     * @param null $client_id
     * @return bool|mixed
     */
    public function getDefaultScope($client_id = null)
    {
        if (is_null($client_id) || !$result = $this->getValue($this->config['scope_key'].'default:'.$client_id)) {
            $result = $this->getValue($this->config['scope_key'].'default:global');
        }

        return $result;
    }

    /**
     * @param $scope
     * @param null $client_id
     * @param string $type
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function setScope($scope, $client_id = null, $type = 'supported')
    {
        if (!in_array($type, array('default', 'supported'))) {
            throw new \InvalidArgumentException('"$type" must be one of "default", "supported"');
        }

        if (is_null($client_id)) {
            $key = $this->config['scope_key'].$type.':global';
        } else {
            $key = $this->config['scope_key'].$type.':'.$client_id;
        }

        return $this->setValue($key, $scope);
    }

    /**
     * @param $client_id
     * @param $subject
     * @return bool|null
     */
    public function getClientKey($client_id, $subject)
    {
        if (!$jwt = $this->getValue($this->config['jwt_key'] . $client_id)) {
            return false;
        }

        if (isset($jwt['subject']) && $jwt['subject'] == $subject ) {
            return $jwt['key'];
        }

        return null;
    }

    /**
     * @param $client_id
     * @param $key
     * @param null $subject
     * @return bool
     */
    public function setClientKey($client_id, $key, $subject = null)
    {
        return $this->setValue($this->config['jwt_key'] . $client_id, array(
            'key' => $key,
            'subject' => $subject
        ));
    }

    /**
     * @param $client_id
     * @return bool|null
     */
    public function getClientScope($client_id)
    {
        if (!$clientDetails = $this->getClientDetails($client_id)) {
            return false;
        }

        if (isset($clientDetails['scope'])) {
            return $clientDetails['scope'];
        }

        return null;
    }

    /**
     * @param $client_id
     * @param $subject
     * @param $audience
     * @param $expiration
     * @param $jti
     * @throws \Exception
     */
    public function getJti($client_id, $subject, $audience, $expiration, $jti)
    {
        //TODO: Needs cassandra implementation.
        throw new \Exception('getJti() for the Cassandra driver is currently unimplemented.');
    }

    /**
     * @param $client_id
     * @param $subject
     * @param $audience
     * @param $expiration
     * @param $jti
     * @throws \Exception
     */
    public function setJti($client_id, $subject, $audience, $expiration, $jti)
    {
        //TODO: Needs cassandra implementation.
        throw new \Exception('setJti() for the Cassandra driver is currently unimplemented.');
    }

    /**
     * @param string $client_id
     * @return mixed
     */
    public function getPublicKey($client_id = '')
    {
        $public_key = $this->getValue($this->config['public_key_key'] . $client_id);
        if (is_array($public_key)) {
            return $public_key['public_key'];
        }
        $public_key = $this->getValue($this->config['public_key_key']);
        if (is_array($public_key)) {
            return $public_key['public_key'];
        }
    }

    /**
     * @param string $client_id
     * @return mixed
     */
    public function getPrivateKey($client_id = '')
    {
        $public_key = $this->getValue($this->config['public_key_key'] . $client_id);
        if (is_array($public_key)) {
            return $public_key['private_key'];
        }
        $public_key = $this->getValue($this->config['public_key_key']);
        if (is_array($public_key)) {
            return $public_key['private_key'];
        }
    }

    /**
     * @param null $client_id
     * @return mixed|string
     */
    public function getEncryptionAlgorithm($client_id = null)
    {
        $public_key = $this->getValue($this->config['public_key_key'] . $client_id);
        if (is_array($public_key)) {
            return $public_key['encryption_algorithm'];
        }
        $public_key = $this->getValue($this->config['public_key_key']);
        if (is_array($public_key)) {
            return $public_key['encryption_algorithm'];
        }

        return 'RS256';
    }

    /**
     * @param mixed $user_id
     * @param string $claims
     * @return array|bool
     */
    public function getUserClaims($user_id, $claims)
    {
        $userDetails = $this->getUserDetails($user_id);
        if (!is_array($userDetails)) {
            return false;
        }

        $claims = explode(' ', trim($claims));
        $userClaims = array();

        // for each requested claim, if the user has the claim, set it in the response
        $validClaims = explode(' ', self::VALID_CLAIMS);
        foreach ($validClaims as $validClaim) {
            if (in_array($validClaim, $claims)) {
                if ($validClaim == 'address') {
                    // address is an object with subfields
                    $userClaims['address'] = $this->getUserClaim($validClaim, $userDetails['address'] ?: $userDetails);
                } else {
                    $userClaims = array_merge($userClaims, $this->getUserClaim($validClaim, $userDetails));
                }
            }
        }

        return $userClaims;
    }

    /**
     * @param $claim
     * @param $userDetails
     * @return array
     */
    protected function getUserClaim($claim, $userDetails)
    {
        $userClaims = array();
        $claimValuesString = constant(sprintf('self::%s_CLAIM_VALUES', strtoupper($claim)));
        $claimValues = explode(' ', $claimValuesString);

        foreach ($claimValues as $value) {
            if ($value == 'email_verified') {
                $userClaims[$value] = $userDetails[$value]=='true' ? true : false;
            } else {
                $userClaims[$value] = isset($userDetails[$value]) ? $userDetails[$value] : null;
            }
        }

        return $userClaims;
    }
}