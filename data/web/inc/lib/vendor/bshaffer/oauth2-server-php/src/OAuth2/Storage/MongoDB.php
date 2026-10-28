<?php

namespace OAuth2\Storage;

use MongoDB\Client;
use MongoDB\Database;
use OAuth2\OpenID\Storage\AuthorizationCodeInterface as OpenIDAuthorizationCodeInterface;

/**
 * Simple MongoDB storage for all storage types
 *
 * NOTE: This class is meant to get users started
 * quickly. If your application requires further
 * customization, extend this class or create your own.
 *
 * NOTE: Passwords are stored in plaintext, which is never
 * a good idea.  Be sure to override this for your application
 *
 * @author Julien Chaumond <chaumond@gmail.com>
 */
class MongoDB implements AuthorizationCodeInterface,
    UserCredentialsInterface,
    AccessTokenInterface,
    ClientCredentialsInterface,
    RefreshTokenInterface,
    JwtBearerInterface,
    PublicKeyInterface,
    OpenIDAuthorizationCodeInterface
{
    protected $db;
    protected $config;

    public function __construct($connection, $config = array())
    {
        if (!class_exists(Database::class) || !class_exists(Client::class)) {
            throw new \LogicException('Missing MongoDB php extension. Please install mongodb.so');
        }
        if ($connection instanceof Database) {
            $this->db = $connection;
        } else {
            if (!is_array($connection)) {
                throw new \InvalidArgumentException('First argument to OAuth2\Storage\Mongo must be an instance of MongoDB\Database or a configuration array');
            }
            $server = sprintf('mongodb://%s:%d', $connection['host'], $connection['port']);
            $m = new Client($server);
            $this->db = $m->selectDatabase($connection['database']);
        }
        $this->config = array_merge(array(
            'client_table' => 'oauth_clients',
            'access_token_table' => 'oauth_access_tokens',
            'refresh_token_table' => 'oauth_refresh_tokens',
            'code_table' => 'oauth_authorization_codes',
            'user_table' => 'oauth_users',
            'jwt_table' => 'oauth_jwt',
            'jti_table' => 'oauth_jti',
            'scope_table'  => 'oauth_scopes',
            'key_table'  => 'oauth_keys',
        ), $config);
    }

    /* ClientCredentialsInterface */
    public function checkClientCredentials($client_id, $client_secret = null)
    {
        if ($result = $this->collection('client_table')->findOne(array('client_id' => $client_id))) {
            return $result['client_secret'] == $client_secret;
        }
        return false;
    }

    public function isPublicClient($client_id)
    {
        if (!$result = $this->collection('client_table')->findOne(array('client_id' => $client_id))) {
            return false;
        }
        return empty($result['client_secret']);
    }

    /* ClientInterface */
    public function getClientDetails($client_id)
    {
        $result = $this->collection('client_table')->findOne(array('client_id' => $client_id));
        return is_null($result) ? false : $result;
    }

    public function setClientDetails($client_id, $client_secret = null, $redirect_uri = null, $grant_types = null, $scope = null, $user_id = null)
    {
        if ($this->getClientDetails($client_id)) {
            $result = $this->collection('client_table')->updateOne(
                array('client_id' => $client_id),
                array('$set' => array(
                    'client_secret' => $client_secret,
                    'redirect_uri'  => $redirect_uri,
                    'grant_types'   => $grant_types,
                    'scope'         => $scope,
                    'user_id'       => $user_id,
                ))
            );
            return $result->getMatchedCount() > 0;
        }
        $client = array(
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_types'   => $grant_types,
            'scope'         => $scope,
            'user_id'       => $user_id,
        );
        $result = $this->collection('client_table')->insertOne($client);
        return $result->getInsertedCount() > 0;
    }

    public function checkRestrictedGrantType($client_id, $grant_type)
    {
        $details = $this->getClientDetails($client_id);
        if (isset($details['grant_types'])) {
            $grant_types = explode(' ', $details['grant_types']);
            return in_array($grant_type, $grant_types);
        }
        // if grant_types are not defined, then none are restricted
        return true;
    }

    /* AccessTokenInterface */
    public function getAccessToken($access_token)
    {
        $token = $this->collection('access_token_table')->findOne(array('access_token' => $access_token));
        return is_null($token) ? false : $token;
    }

    public function setAccessToken($access_token, $client_id, $user_id, $expires, $scope = null)
    {
        // if it exists, update it.
        if ($this->getAccessToken($access_token)) {
            $result = $this->collection('access_token_table')->updateOne(
                array('access_token' => $access_token),
                array('$set' => array(
                    'client_id' => $client_id,
                    'expires' => $expires,
                    'user_id' => $user_id,
                    'scope' => $scope
                ))
            );
            return $result->getMatchedCount() > 0;
        }
        $token = array(
            'access_token' => $access_token,
            'client_id' => $client_id,
            'expires' => $expires,
            'user_id' => $user_id,
            'scope' => $scope
        );
        $result = $this->collection('access_token_table')->insertOne($token);
        return $result->getInsertedCount() > 0;
    }

    public function unsetAccessToken($access_token)
    {
        $result = $this->collection('access_token_table')->deleteOne(array(
            'access_token' => $access_token
        ));
        return $result->getDeletedCount() > 0;
    }

    /* AuthorizationCodeInterface */
    public function getAuthorizationCode($code)
    {
        $code = $this->collection('code_table')->findOne(array(
            'authorization_code' => $code
        ));
        return is_null($code) ? false : $code;
    }

    public function setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null, $code_challenge = null, $code_challenge_method = null)
    {
        // if it exists, update it.
        if ($this->getAuthorizationCode($code)) {
            $result = $this->collection('code_table')->updateOne(
                array('authorization_code' => $code),
                array('$set' => array(
                    'client_id' => $client_id,
                    'user_id' => $user_id,
                    'redirect_uri' => $redirect_uri,
                    'expires' => $expires,
                    'scope' => $scope,
                    'id_token' => $id_token,
                    'code_challenge' => $code_challenge,
                    'code_challenge_method' => $code_challenge_method,
                ))
            );
            return $result->getMatchedCount() > 0;
        }
        $token = array(
            'authorization_code' => $code,
            'client_id' => $client_id,
            'user_id' => $user_id,
            'redirect_uri' => $redirect_uri,
            'expires' => $expires,
            'scope' => $scope,
            'id_token' => $id_token,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => $code_challenge_method,
        );
        $result = $this->collection('code_table')->insertOne($token);
        return $result->getInsertedCount() > 0;
    }

    public function expireAuthorizationCode($code)
    {
        $result = $this->collection('code_table')->deleteOne(array(
            'authorization_code' => $code
        ));
        return $result->getDeletedCount() > 0;
    }

    /* UserCredentialsInterface */
    public function checkUserCredentials($username, $password)
    {
        if ($user = $this->getUser($username)) {
            return $this->checkPassword($user, $password);
        }
        return false;
    }

    public function getUserDetails($username)
    {
        if ($user = $this->getUser($username)) {
            $user['user_id'] = $user['username'];
        }
        return $user;
    }

    /* RefreshTokenInterface */
    public function getRefreshToken($refresh_token)
    {
        $token = $this->collection('refresh_token_table')->findOne(array(
            'refresh_token' => $refresh_token
        ));
        return is_null($token) ? false : $token;
    }

    public function setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope = null)
    {
        $token = array(
            'refresh_token' => $refresh_token,
            'client_id' => $client_id,
            'user_id' => $user_id,
            'expires' => $expires,
            'scope' => $scope
        );
        $result = $this->collection('refresh_token_table')->insertOne($token);
        return $result->getInsertedCount() > 0;
    }

    public function unsetRefreshToken($refresh_token)
    {
        $result = $this->collection('refresh_token_table')->deleteOne(array(
            'refresh_token' => $refresh_token
        ));
        return $result->getDeletedCount() > 0;
    }

    // plaintext passwords are bad!  Override this for your application
    protected function checkPassword($user, $password)
    {
        return $user['password'] == $password;
    }

    public function getUser($username)
    {
        $result = $this->collection('user_table')->findOne(array('username' => $username));
        return is_null($result) ? false : $result;
    }

    public function setUser($username, $password, $firstName = null, $lastName = null)
    {
        if ($this->getUser($username)) {
            $result = $this->collection('user_table')->updateOne(
                array('username' => $username),
                array('$set' => array(
                    'password' => $password,
                    'first_name' => $firstName,
                    'last_name' => $lastName
                ))
            );

            return $result->getMatchedCount() > 0;
        }

        $user = array(
            'username' => $username,
            'password' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName
        );
        $result = $this->collection('user_table')->insertOne($user);
        return $result->getInsertedCount() > 0;
    }

    public function getClientKey($client_id, $subject)
    {
        $result = $this->collection('jwt_table')->findOne(array(
            'client_id' => $client_id,
            'subject' => $subject
        ));
        return is_null($result) ? false : $result['key'];
    }

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

    public function getJti($client_id, $subject, $audience, $expires, $jti)
    {
        //TODO: Needs mongodb implementation.
        throw new \Exception('getJti() for the MongoDB driver is currently unimplemented.');
    }

    public function setJti($client_id, $subject, $audience, $expires, $jti)
    {
        //TODO: Needs mongodb implementation.
        throw new \Exception('setJti() for the MongoDB driver is currently unimplemented.');
    }

    public function getPublicKey($client_id = null)
    {
        if ($client_id) {
            $result = $this->collection('key_table')->findOne(array(
                'client_id' => $client_id
            ));
            if ($result) {
                return $result['public_key'];
            }
        }

        $result = $this->collection('key_table')->findOne(array(
            'client_id' => null
        ));
        return is_null($result) ? false : $result['public_key'];
    }

    public function getPrivateKey($client_id = null)
    {
        if ($client_id) {
            $result = $this->collection('key_table')->findOne(array(
                'client_id' => $client_id
            ));
            if ($result) {
                return $result['private_key'];
            }
        }

        $result = $this->collection('key_table')->findOne(array(
            'client_id' => null
        ));
        return is_null($result) ? false : $result['private_key'];
    }

    public function getEncryptionAlgorithm($client_id = null)
    {
        if ($client_id) {
            $result = $this->collection('key_table')->findOne(array(
                'client_id' => $client_id
            ));
            if ($result) {
                return $result['encryption_algorithm'];
            }
        }

        $result = $this->collection('key_table')->findOne(array(
            'client_id' => null
        ));
        return is_null($result) ? 'RS256' : $result['encryption_algorithm'];
    }

    // Helper function to access a MongoDB collection by `type`:
    protected function collection($name)
    {
        return $this->db->{$this->config[$name]};
    }
}