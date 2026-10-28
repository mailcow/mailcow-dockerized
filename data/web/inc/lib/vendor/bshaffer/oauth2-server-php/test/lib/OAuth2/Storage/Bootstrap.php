<?php

namespace OAuth2\Storage;

class Bootstrap
{
    const DYNAMODB_PHP_VERSION = 'none';

    protected static $instance;
    private $mysql;
    private $sqlite;
    private $postgres;
    private $mongo;
    private $mongoDb;
    private $redis;
    private $cassandra;
    private $configDir;
    private $dynamodb;
    private $couchbase;

    public function __construct()
    {
        $this->configDir = __DIR__.'/../../../config';
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getSqlitePdo()
    {
        if (!$this->sqlite) {
            $this->removeSqliteDb();
            $pdo = new \PDO(sprintf('sqlite:%s', $this->getSqliteDir()));
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->createSqliteDb($pdo);

            $this->sqlite = new Pdo($pdo);
        }

        return $this->sqlite;
    }

    public function getPostgresPdo()
    {
        if (!$this->postgres) {
            if (in_array('pgsql', \PDO::getAvailableDrivers())) {
                $this->removePostgresDb();
                $this->createPostgresDb();
                if ($pdo = $this->getPostgresDriver()) {
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $this->populatePostgresDb($pdo);
                    $this->postgres = new Pdo($pdo);
                }
            } else {
                $this->postgres = new NullStorage('Postgres', 'Missing postgres PDO extension.');
            }
        }

        return $this->postgres;
    }

    public function getPostgresDriver()
    {
        try {
            $pdo = new \PDO('pgsql:host=localhost;dbname=oauth2_server_php', 'postgres', 'postgres');

            return $pdo;
        } catch (\PDOException $e) {
            $this->postgres = new NullStorage('Postgres', $e->getMessage());
        }
    }

    public function getMemoryStorage()
    {
        return new Memory(json_decode(file_get_contents($this->configDir. '/storage.json'), true));
    }

    public function getRedisStorage()
    {
        if (!$this->redis) {
            if (class_exists('Predis\Client')) {
                $redis = new \Predis\Client();
                if ($this->testRedisConnection($redis)) {
                    $redis->flushdb();
                    $this->redis = new Redis($redis);
                    $this->createRedisDb($this->redis);
                } else {
                    $this->redis = new NullStorage('Redis', 'Unable to connect to redis server on port 6379');
                }
            } else {
                $this->redis = new NullStorage('Redis', 'Missing redis library. Please run "composer.phar require predis/predis:dev-master"');
            }
        }

        return $this->redis;
    }

    private function testRedisConnection(\Predis\Client $redis)
    {
        try {
            $redis->connect();
        } catch (\Predis\CommunicationException $exception) {
            // we were unable to connect to the redis server
            return false;
        }

        return true;
    }

    public function getMysqlPdo()
    {
        if (!$this->mysql) {
            $pdo = null;
            try {
                $pdo = new \PDO('mysql:host=localhost;', 'root', 'root');
            } catch (\PDOException $e) {
                $this->mysql = new NullStorage('MySQL', 'Unable to connect to MySQL on root@localhost');
            }

            if ($pdo) {
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->removeMysqlDb($pdo);
                $this->createMysqlDb($pdo);

                $this->mysql = new Pdo($pdo);
            }
        }

        return $this->mysql;
    }

    public function getMongo()
    {
        if (!$this->mongo) {
            if (class_exists('MongoClient')) {
                $mongo = new \MongoClient('mongodb://localhost:27017', array('connect' => false));
                if ($this->testMongoConnection($mongo)) {
                    $db = $mongo->oauth2_server_php_legacy;
                    $this->removeMongo($db);
                    $this->createMongo($db);

                    $this->mongo = new Mongo($db);
                } else {
                    $this->mongo = new NullStorage('Mongo', 'Unable to connect to mongo server on "localhost:27017"');
                }
            } else {
                $this->mongo = new NullStorage('Mongo', 'Missing mongo php extension. Please install mongo.so');
            }
        }

        return $this->mongo;
    }

    public function getMongoDb()
    {
        if (!$this->mongoDb) {
            if (class_exists('MongoDB\Client')) {
                $mongoDb = new \MongoDB\Client('mongodb://localhost:27017');
                if ($this->testMongoDBConnection($mongoDb)) {
                    $db = $mongoDb->oauth2_server_php;
                    $this->removeMongoDb($db);
                    $this->createMongoDb($db);

                    $this->mongoDb = new MongoDB($db);
                } else {
                    $this->mongoDb = new NullStorage('MongoDB', 'Unable to connect to mongo server on "localhost:27017"');
                }
            } else {
                $this->mongoDb = new NullStorage('MongoDB', 'Missing MongoDB php extension. Please install mongodb.so');
            }
        }

        return $this->mongoDb;
    }

    private function testMongoConnection(\MongoClient $mongo)
    {
        try {
            $mongo->connect();
        } catch (\MongoConnectionException $e) {
            return false;
        }

        return true;
    }

    private function testMongoDBConnection(\MongoDB\Client $mongo)
    {
        return true;
    }

    public function getCouchbase()
    {
        if (!$this->couchbase) {
            if ($this->getEnvVar('SKIP_COUCHBASE_TESTS')) {
                $this->couchbase = new NullStorage('Couchbase', 'Skipping Couchbase tests');
            } elseif (!class_exists('Couchbase')) {
                $this->couchbase = new NullStorage('Couchbase', 'Missing Couchbase php extension. Please install couchbase.so');
            } else {
                // round-about way to make sure couchbase is working
                // this is required because it throws a "floating point exception" otherwise
                $code = "new \Couchbase(array('localhost:8091'), '', '', 'auth', false);";
                $exec = sprintf('php -r "%s"', $code);
                $ret = exec($exec, $test, $var);
                if ($ret != 0) {
                    $couchbase = new \Couchbase(array('localhost:8091'), '', '', 'auth', false);
                    if ($this->testCouchbaseConnection($couchbase)) {
                        $this->clearCouchbase($couchbase);
                        $this->createCouchbaseDB($couchbase);

                        $this->couchbase = new CouchbaseDB($couchbase);
                    } else {
                        $this->couchbase = new NullStorage('Couchbase', 'Unable to connect to Couchbase server on "localhost:8091"');
                    }
                } else {
                    $this->couchbase = new NullStorage('Couchbase', 'Error while trying to connect to Couchbase');
                }
            }
        }

        return $this->couchbase;
    }

    private function testCouchbaseConnection(\Couchbase $couchbase)
    {
        try {
            if (count($couchbase->getServers()) > 0) {
                return true;
            }
        } catch (\CouchbaseException $e) {
            return false;
        }

        return true;
    }

    public function getCassandraStorage()
    {
        if (!$this->cassandra) {
            if (class_exists('phpcassa\ColumnFamily')) {
                $cassandra = new \phpcassa\Connection\ConnectionPool('oauth2_test', array('127.0.0.1:9160'));
                if ($this->testCassandraConnection($cassandra)) {
                    $this->removeCassandraDb();
                    $this->cassandra = new Cassandra($cassandra);
                    $this->createCassandraDb($this->cassandra);
                } else {
                    $this->cassandra = new NullStorage('Cassandra', 'Unable to connect to cassandra server on "127.0.0.1:9160"');
                }
            } else {
                $this->cassandra = new NullStorage('Cassandra', 'Missing cassandra library. Please run "composer.phar require thobbs/phpcassa:dev-master"');
            }
        }

        return $this->cassandra;
    }

    private function testCassandraConnection(\phpcassa\Connection\ConnectionPool $cassandra)
    {
        try {
            new \phpcassa\SystemManager('localhost:9160');
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    private function removeCassandraDb()
    {
        $sys = new \phpcassa\SystemManager('localhost:9160');

        try {
            $sys->drop_keyspace('oauth2_test');
        } catch (\cassandra\InvalidRequestException $e) {

        }
    }

    private function createCassandraDb(Cassandra $storage)
    {
        // create the cassandra keyspace and column family
        $sys = new \phpcassa\SystemManager('localhost:9160');

        $sys->create_keyspace('oauth2_test', array(
            "strategy_class" => \phpcassa\Schema\StrategyClass::SIMPLE_STRATEGY,
            "strategy_options" => array('replication_factor' => '1')
        ));

        $sys->create_column_family('oauth2_test', 'auth');
        $cassandra = new \phpcassa\Connection\ConnectionPool('oauth2_test', array('127.0.0.1:9160'));
        $cf = new \phpcassa\ColumnFamily($cassandra, 'auth');

        // populate the data
        $storage->setClientDetails("oauth_test_client", "testpass", "http://example.com", 'implicit password');
        $storage->setAccessToken("testtoken", "Some Client", '', time() + 1000);
        $storage->setAuthorizationCode("testcode", "Some Client", '', '', time() + 1000);

        $storage->setScope('supportedscope1 supportedscope2 supportedscope3 supportedscope4');
        $storage->setScope('defaultscope1 defaultscope2', null, 'default');

        $storage->setScope('clientscope1 clientscope2', 'Test Client ID');
        $storage->setScope('clientscope1 clientscope2', 'Test Client ID', 'default');

        $storage->setScope('clientscope1 clientscope2 clientscope3', 'Test Client ID 2');
        $storage->setScope('clientscope1 clientscope2', 'Test Client ID 2', 'default');

        $storage->setScope('clientscope1 clientscope2', 'Test Default Scope Client ID');
        $storage->setScope('clientscope1 clientscope2', 'Test Default Scope Client ID', 'default');

        $storage->setScope('clientscope1 clientscope2 clientscope3', 'Test Default Scope Client ID 2');
        $storage->setScope('clientscope3', 'Test Default Scope Client ID 2', 'default');

        $storage->setClientKey('oauth_test_client', $this->getTestPublicKey(), 'test_subject');

        $cf->insert("oauth_public_keys:ClientID_One", array('__data' => json_encode(array("public_key" => "client_1_public", "private_key" => "client_1_private", "encryption_algorithm" => "RS256"))));
        $cf->insert("oauth_public_keys:ClientID_Two", array('__data' => json_encode(array("public_key" => "client_2_public", "private_key" => "client_2_private", "encryption_algorithm" => "RS256"))));
        $cf->insert("oauth_public_keys:", array('__data' => json_encode(array("public_key" => $this->getTestPublicKey(), "private_key" =>  $this->getTestPrivateKey(), "encryption_algorithm" => "RS256"))));

        $cf->insert("oauth_users:testuser", array('__data' =>json_encode(array("password" => "password", "email" => "testuser@test.com", "email_verified" => true))));

    }

    private function createSqliteDb(\PDO $pdo)
    {
        $this->runPdoSql($pdo);
    }

    private function removeSqliteDb()
    {
        if (file_exists($this->getSqliteDir())) {
            unlink($this->getSqliteDir());
        }
    }

    private function createMysqlDb(\PDO $pdo)
    {
        $pdo->exec('CREATE DATABASE oauth2_server_php');
        $pdo->exec('USE oauth2_server_php');
        $this->runPdoSql($pdo);
    }

    private function removeMysqlDb(\PDO $pdo)
    {
        $pdo->exec('DROP DATABASE IF EXISTS oauth2_server_php');
    }

    private function createPostgresDb()
    {
        if (!`PGPASSWORD=postgres psql postgres -tAc "SELECT 1 FROM pg_roles WHERE rolname='postgres'" -h localhost -U postgres`) {
            `PGPASSWORD=postgres createuser -s -r postgres -h localhost -U postgres`;
        }

        `PGPASSWORD=postgres createdb -O postgres oauth2_server_php -h localhost -U postgres`;
    }

    private function populatePostgresDb(\PDO $pdo)
    {
        $this->runPdoSql($pdo);
    }

    private function removePostgresDb()
    {
        if (trim(`PGPASSWORD=postgres psql -l -h localhost -U postgres | grep oauth2_server_php | wc -l`)) {
            `PGPASSWORD=postgres dropdb oauth2_server_php -h localhost -U postgres`;
        }
    }

    public function runPdoSql(\PDO $pdo)
    {
        $storage = new Pdo($pdo);
        foreach (explode(';', $storage->getBuildSql()) as $statement) {
            $result = $pdo->exec($statement);
        }

        // set up scopes
        $sql = 'INSERT INTO oauth_scopes (scope) VALUES (?)';
        foreach (explode(' ', 'supportedscope1 supportedscope2 supportedscope3 supportedscope4 clientscope1 clientscope2 clientscope3') as $supportedScope) {
            $pdo->prepare($sql)->execute(array($supportedScope));
        }

        $sql = 'INSERT INTO oauth_scopes (scope, is_default) VALUES (?, ?)';
        foreach (array('defaultscope1', 'defaultscope2') as $defaultScope) {
            $pdo->prepare($sql)->execute(array($defaultScope, true));
        }

        // set up clients
        $sql = 'INSERT INTO oauth_clients (client_id, client_secret, scope, grant_types) VALUES (?, ?, ?, ?)';
        $pdo->prepare($sql)->execute(array('Test Client ID', 'TestSecret', 'clientscope1 clientscope2', null));
        $pdo->prepare($sql)->execute(array('Test Client ID 2', 'TestSecret', 'clientscope1 clientscope2 clientscope3', null));
        $pdo->prepare($sql)->execute(array('Test Default Scope Client ID', 'TestSecret', 'clientscope1 clientscope2', null));
        $pdo->prepare($sql)->execute(array('oauth_test_client', 'testpass', null, 'implicit password'));

        // set up misc
        $sql = 'INSERT INTO oauth_access_tokens (access_token, client_id, expires, user_id) VALUES (?, ?, ?, ?)';
        $pdo->prepare($sql)->execute(array('testtoken', 'Some Client', date('Y-m-d H:i:s', strtotime('+1 hour')), null));
        $pdo->prepare($sql)->execute(array('accesstoken-openid-connect', 'Some Client', date('Y-m-d H:i:s', strtotime('+1 hour')), 'testuser'));

        $sql = 'INSERT INTO oauth_authorization_codes (authorization_code, client_id, expires) VALUES (?, ?, ?)';
        $pdo->prepare($sql)->execute(array('testcode', 'Some Client', date('Y-m-d H:i:s', strtotime('+1 hour'))));

        $sql = 'INSERT INTO oauth_users (username, password, email, email_verified) VALUES (?, ?, ?, ?)';
        $pdo->prepare($sql)->execute(array('testuser', 'password', 'testuser@test.com', true));

        $sql = 'INSERT INTO oauth_public_keys (client_id, public_key, private_key, encryption_algorithm) VALUES (?, ?, ?, ?)';
        $pdo->prepare($sql)->execute(array('ClientID_One', 'client_1_public', 'client_1_private', 'RS256'));
        $pdo->prepare($sql)->execute(array('ClientID_Two', 'client_2_public', 'client_2_private', 'RS256'));

        $sql = 'INSERT INTO oauth_public_keys (client_id, public_key, private_key, encryption_algorithm) VALUES (?, ?, ?, ?)';
        $pdo->prepare($sql)->execute(array(null, $this->getTestPublicKey(), $this->getTestPrivateKey(), 'RS256'));

        $sql = 'INSERT INTO oauth_jwt (client_id, subject, public_key) VALUES (?, ?, ?)';
        $pdo->prepare($sql)->execute(array('oauth_test_client', 'test_subject', $this->getTestPublicKey()));
    }

    public function getSqliteDir()
    {
        return $this->configDir. '/test.sqlite';
    }

    public function getConfigDir()
    {
        return $this->configDir;
    }

    private function createCouchbaseDB(\Couchbase $db)
    {
        $db->set('oauth_clients-oauth_test_client',json_encode(array(
            'client_id' => "oauth_test_client",
            'client_secret' => "testpass",
            'redirect_uri' => "http://example.com",
            'grant_types' => 'implicit password'
        )));

        $db->set('oauth_access_tokens-testtoken',json_encode(array(
            'access_token' => "testtoken",
            'client_id' => "Some Client"
        )));

        $db->set('oauth_authorization_codes-testcode',json_encode(array(
            'access_token' => "testcode",
            'client_id' => "Some Client"
        )));

        $db->set('oauth_users-testuser',json_encode(array(
            'username' => 'testuser',
            'password' => 'password',
            'email' => 'testuser@test.com',
            'email_verified' => true,
        )));

        $db->set('oauth_jwt-oauth_test_client',json_encode(array(
            'client_id' => 'oauth_test_client',
            'key'       => $this->getTestPublicKey(),
            'subject'   => 'test_subject',
        )));
    }

    private function clearCouchbase(\Couchbase $cb)
    {
        $cb->delete('oauth_authorization_codes-new-openid-code');
        $cb->delete('oauth_access_tokens-newtoken');
        $cb->delete('oauth_authorization_codes-newcode');
        $cb->delete('oauth_refresh_tokens-refreshtoken');
    }

    private function createMongo(\MongoDB $db)
    {
        $db->oauth_clients->insert(array(
            'client_id' => "oauth_test_client",
            'client_secret' => "testpass",
            'redirect_uri' => "http://example.com",
            'grant_types' => 'implicit password'
        ));

        $db->oauth_access_tokens->insert(array(
            'access_token' => "testtoken",
            'client_id' => "Some Client"
        ));

        $db->oauth_authorization_codes->insert(array(
            'authorization_code' => "testcode",
            'client_id' => "Some Client"
        ));

        $db->oauth_users->insert(array(
            'username' => 'testuser',
            'password' => 'password',
            'email' => 'testuser@test.com',
            'email_verified' => true,
        ));

        $db->oauth_keys->insert(array(
            'client_id'   => null,
            'public_key' => $this->getTestPublicKey(),
            'private_key' => $this->getTestPrivateKey(),
            'encryption_algorithm' => 'RS256'
        ));

        $db->oauth_jwt->insert(array(
            'client_id' => 'oauth_test_client',
            'key' => $this->getTestPublicKey(),
            'subject'   => 'test_subject',
        ));
    }

    public function removeMongo(\MongoDB $db)
    {
        $db->drop();
    }

    private function createMongoDB(\MongoDB\Database $db)
    {
        $db->oauth_clients->insertOne(array(
            'client_id' => "oauth_test_client",
            'client_secret' => "testpass",
            'redirect_uri' => "http://example.com",
            'grant_types' => 'implicit password'
        ));

        $db->oauth_access_tokens->insertOne(array(
            'access_token' => "testtoken",
            'client_id' => "Some Client"
        ));

        $db->oauth_authorization_codes->insertOne(array(
            'authorization_code' => "testcode",
            'client_id' => "Some Client"
        ));

        $db->oauth_users->insertOne(array(
            'username' => 'testuser',
            'password' => 'password',
            'email' => 'testuser@test.com',
            'email_verified' => true,
        ));

        $db->oauth_keys->insertOne(array(
            'client_id'   => null,
            'public_key' => $this->getTestPublicKey(),
            'private_key' => $this->getTestPrivateKey(),
            'encryption_algorithm' => 'RS256'
        ));

        $db->oauth_jwt->insertOne(array(
            'client_id' => 'oauth_test_client',
            'key' => $this->getTestPublicKey(),
            'subject'   => 'test_subject',
        ));
    }

    public function removeMongoDB(\MongoDB\Database $db)
    {
        $db->drop();
    }

    private function createRedisDb(Redis $storage)
    {
        $storage->setClientDetails("oauth_test_client", "testpass", "http://example.com", 'implicit password');
        $storage->setAccessToken("testtoken", "Some Client", '', time() + 1000);
        $storage->setAuthorizationCode("testcode", "Some Client", '', '', time() + 1000);
        $storage->setUser("testuser", "password");

        $storage->setScope('supportedscope1 supportedscope2 supportedscope3 supportedscope4');
        $storage->setScope('defaultscope1 defaultscope2', null, 'default');

        $storage->setScope('clientscope1 clientscope2', 'Test Client ID');
        $storage->setScope('clientscope1 clientscope2', 'Test Client ID', 'default');

        $storage->setScope('clientscope1 clientscope2 clientscope3', 'Test Client ID 2');
        $storage->setScope('clientscope1 clientscope2', 'Test Client ID 2', 'default');

        $storage->setScope('clientscope1 clientscope2', 'Test Default Scope Client ID');
        $storage->setScope('clientscope1 clientscope2', 'Test Default Scope Client ID', 'default');

        $storage->setScope('clientscope1 clientscope2 clientscope3', 'Test Default Scope Client ID 2');
        $storage->setScope('clientscope3', 'Test Default Scope Client ID 2', 'default');

        $storage->setClientKey('oauth_test_client', $this->getTestPublicKey(), 'test_subject');
    }

    public function getTestPublicKey()
    {
        return file_get_contents(__DIR__.'/../../../config/keys/id_rsa.pub');
    }

    private function getTestPrivateKey()
    {
        return file_get_contents(__DIR__.'/../../../config/keys/id_rsa');
    }

    public function getDynamoDbStorage()
    {
        if (!$this->dynamodb) {
            // only run once per travis build
            if (true == $this->getEnvVar('TRAVIS')) {
                if (self::DYNAMODB_PHP_VERSION != $this->getEnvVar('TRAVIS_PHP_VERSION')) {
                    $this->dynamodb = new NullStorage('DynamoDb', 'Skipping for travis.ci - only run once per build');

                    return;
                }
            }
            if (class_exists('\Aws\DynamoDb\DynamoDbClient')) {
                if ($client = $this->getDynamoDbClient()) {
                    // travis runs a unique set of tables per build, to avoid conflict
                    $prefix = '';
                    if ($build_id = $this->getEnvVar('TRAVIS_JOB_NUMBER')) {
                        $prefix = sprintf('build_%s_', $build_id);
                    } else {
                        if (!$this->deleteDynamoDb($client, $prefix, true)) {
                            return $this->dynamodb = new NullStorage('DynamoDb', 'Timed out while waiting for DynamoDB deletion (30 seconds)');
                        }
                    }
                    $this->createDynamoDb($client, $prefix);
                    $this->populateDynamoDb($client, $prefix);
                    $config = array(
                        'client_table' => $prefix.'oauth_clients',
                        'access_token_table' => $prefix.'oauth_access_tokens',
                        'refresh_token_table' => $prefix.'oauth_refresh_tokens',
                        'code_table' => $prefix.'oauth_authorization_codes',
                        'user_table' => $prefix.'oauth_users',
                        'jwt_table'  => $prefix.'oauth_jwt',
                        'scope_table'  => $prefix.'oauth_scopes',
                        'public_key_table'  => $prefix.'oauth_public_keys',
                    );
                    $this->dynamodb = new DynamoDB($client, $config);
                } elseif (!$this->dynamodb) {
                    $this->dynamodb = new NullStorage('DynamoDb', 'unable to connect to DynamoDB');
                }
            } else {
                $this->dynamodb = new NullStorage('DynamoDb', 'Missing DynamoDB library. Please run "composer.phar require aws/aws-sdk-php:dev-master');
            }
        }

        return $this->dynamodb;
    }

    private function getDynamoDbClient()
    {
        $config = array();
        // check for environment variables
        if (($key = $this->getEnvVar('AWS_ACCESS_KEY_ID')) && ($secret = $this->getEnvVar('AWS_SECRET_KEY'))) {
            $config['key']    = $key;
            $config['secret'] = $secret;
        } else {
            // fall back on ~/.aws/credentials file
            // @see http://docs.aws.amazon.com/aws-sdk-php/guide/latest/credentials.html#credential-profiles
            if (!file_exists($this->getEnvVar('HOME') . '/.aws/credentials')) {
                $this->dynamodb = new NullStorage('DynamoDb', 'No aws credentials file found, and no AWS_ACCESS_KEY_ID or AWS_SECRET_KEY environment variable set');

                return;
            }

            // set profile in AWS_PROFILE environment variable, defaults to "default"
            $config['profile'] = $this->getEnvVar('AWS_PROFILE', 'default');
        }

        // set region in AWS_REGION environment variable, defaults to "us-east-1"
        $config['region'] = $this->getEnvVar('AWS_REGION', \Aws\Common\Enum\Region::US_EAST_1);

        return \Aws\DynamoDb\DynamoDbClient::factory($config);
    }

    private function deleteDynamoDb(\Aws\DynamoDb\DynamoDbClient $client, $prefix = null, $waitForDeletion = false)
    {
        $tablesList = explode(' ', 'oauth_access_tokens oauth_authorization_codes oauth_clients oauth_jwt oauth_public_keys oauth_refresh_tokens oauth_scopes oauth_users');
        $nbTables  = count($tablesList);

        // Delete all table.
        foreach ($tablesList as $key => $table) {
            try {
                $client->deleteTable(array('TableName' => $prefix.$table));
            } catch (\Aws\DynamoDb\Exception\DynamoDbException $e) {
                // Table does not exist : nothing to do
            }
        }

        // Wait for deleting
        if ($waitForDeletion) {
            $retries = 5;
            $nbTableDeleted = 0;
            while ($nbTableDeleted != $nbTables) {
                $nbTableDeleted = 0;
                foreach ($tablesList as $key => $table) {
                    try {
                        $result = $client->describeTable(array('TableName' => $prefix.$table));
                    } catch (\Aws\DynamoDb\Exception\DynamoDbException $e) {
                        // Table does not exist : nothing to do
                        $nbTableDeleted++;
                    }
                }
                if ($nbTableDeleted != $nbTables) {
                    if ($retries < 0) {
                        // we are tired of waiting
                        return false;
                    }
                    sleep(5);
                    echo "Sleeping 5 seconds for DynamoDB ($retries more retries)...\n";
                    $retries--;
                }
            }
        }

        return true;
    }

    private function createDynamoDb(\Aws\DynamoDb\DynamoDbClient $client, $prefix = null)
    {
        $tablesList = explode(' ', 'oauth_access_tokens oauth_authorization_codes oauth_clients oauth_jwt oauth_public_keys oauth_refresh_tokens oauth_scopes oauth_users');
        $nbTables  = count($tablesList);
        $client->createTable(array(
            'TableName' => $prefix.'oauth_access_tokens',
            'AttributeDefinitions' => array(
                array('AttributeName' => 'access_token','AttributeType' => 'S')
            ),
            'KeySchema' => array(array('AttributeName' => 'access_token','KeyType' => 'HASH')),
            'ProvisionedThroughput' => array('ReadCapacityUnits'  => 1,'WriteCapacityUnits' => 1)
        ));

        $client->createTable(array(
            'TableName' => $prefix.'oauth_authorization_codes',
            'AttributeDefinitions' => array(
                array('AttributeName' => 'authorization_code','AttributeType' => 'S')
            ),
            'KeySchema' => array(array('AttributeName' => 'authorization_code','KeyType' => 'HASH')),
            'ProvisionedThroughput' => array('ReadCapacityUnits'  => 1,'WriteCapacityUnits' => 1)
        ));

        $client->createTable(array(
            'TableName' => $prefix.'oauth_clients',
            'AttributeDefinitions' => array(
                array('AttributeName' => 'client_id','AttributeType' => 'S')
            ),
            'KeySchema' => array(array('AttributeName' => 'client_id','KeyType' => 'HASH')),
            'ProvisionedThroughput' => array('ReadCapacityUnits'  => 1,'WriteCapacityUnits' => 1)
        ));

        $client->createTable(array(
            'TableName' => $prefix.'oauth_jwt',
            'AttributeDefinitions' => array(
                array('AttributeName' => 'client_id','AttributeType' => 'S'),
                array('AttributeName' => 'subject','AttributeType' => 'S')
            ),
            'KeySchema' => array(
                array('AttributeName' => 'client_id','KeyType' => 'HASH'),
                array('AttributeName' => 'subject','KeyType' => 'RANGE')
            ),
            'ProvisionedThroughput' => array('ReadCapacityUnits'  => 1,'WriteCapacityUnits' => 1)
        ));

        $client->createTable(array(
            'TableName' => $prefix.'oauth_public_keys',
            'AttributeDefinitions' => array(
                array('AttributeName' => 'client_id','AttributeType' => 'S')
            ),
            'KeySchema' => array(array('AttributeName' => 'client_id','KeyType' => 'HASH')),
            'ProvisionedThroughput' => array('ReadCapacityUnits'  => 1,'WriteCapacityUnits' => 1)
        ));

        $client->createTable(array(
            'TableName' => $prefix.'oauth_refresh_tokens',
            'AttributeDefinitions' => array(
                array('AttributeName' => 'refresh_token','AttributeType' => 'S')
            ),
            'KeySchema' => array(array('AttributeName' => 'refresh_token','KeyType' => 'HASH')),
            'ProvisionedThroughput' => array('ReadCapacityUnits'  => 1,'WriteCapacityUnits' => 1)
        ));

        $client->createTable(array(
            'TableName' => $prefix.'oauth_scopes',
            'AttributeDefinitions' => array(
                array('AttributeName' => 'scope','AttributeType' => 'S'),
                array('AttributeName' => 'is_default','AttributeType' => 'S')
            ),
            'KeySchema' => array(array('AttributeName' => 'scope','KeyType' => 'HASH')),
            'GlobalSecondaryIndexes' => array(
                array(
                    'IndexName' => 'is_default-index',
                    'KeySchema' => array(array('AttributeName' => 'is_default', 'KeyType' => 'HASH')),
                    'Projection' => array('ProjectionType' => 'ALL'),
                    'ProvisionedThroughput' => array('ReadCapacityUnits'  => 1,'WriteCapacityUnits' => 1)
                ),
            ),
            'ProvisionedThroughput' => array('ReadCapacityUnits'  => 1,'WriteCapacityUnits' => 1)
        ));

        $client->createTable(array(
            'TableName' => $prefix.'oauth_users',
            'AttributeDefinitions' => array(array('AttributeName' => 'username','AttributeType' => 'S')),
            'KeySchema' => array(array('AttributeName' => 'username','KeyType' => 'HASH')),
            'ProvisionedThroughput' => array('ReadCapacityUnits'  => 1,'WriteCapacityUnits' => 1)
        ));

        // Wait for creation
        $nbTableCreated = 0;
        while ($nbTableCreated != $nbTables) {
            $nbTableCreated = 0;
            foreach ($tablesList as $key => $table) {
                try {
                    $result = $client->describeTable(array('TableName' => $prefix.$table));
                    if ($result['Table']['TableStatus'] == 'ACTIVE') {
                        $nbTableCreated++;
                    }
                } catch (\Aws\DynamoDb\Exception\DynamoDbException $e) {
                    // Table does not exist : nothing to do
                    $nbTableCreated++;
                }
            }
            if ($nbTableCreated != $nbTables) {
                sleep(1);
            }
        }
    }

    private function populateDynamoDb($client, $prefix = null)
    {
        // set up scopes
        foreach (explode(' ', 'supportedscope1 supportedscope2 supportedscope3 supportedscope4 clientscope1 clientscope2 clientscope3') as $supportedScope) {
            $client->putItem(array(
                'TableName' => $prefix.'oauth_scopes',
                'Item' => array('scope' => array('S' => $supportedScope))
            ));
        }

        foreach (array('defaultscope1', 'defaultscope2') as $defaultScope) {
            $client->putItem(array(
                'TableName' => $prefix.'oauth_scopes',
                'Item' => array('scope' => array('S' => $defaultScope), 'is_default' => array('S' => "true"))
            ));
        }

        $client->putItem(array(
            'TableName' => $prefix.'oauth_clients',
            'Item' => array(
                'client_id' => array('S' => 'Test Client ID'),
                'client_secret' => array('S' => 'TestSecret'),
                'scope' => array('S' => 'clientscope1 clientscope2')
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_clients',
            'Item' => array(
                'client_id' => array('S' => 'Test Client ID 2'),
                'client_secret' => array('S' => 'TestSecret'),
                'scope' => array('S' => 'clientscope1 clientscope2 clientscope3')
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_clients',
            'Item' => array(
                'client_id' => array('S' => 'Test Default Scope Client ID'),
                'client_secret' => array('S' => 'TestSecret'),
                'scope' => array('S' => 'clientscope1 clientscope2')
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_clients',
            'Item' => array(
                'client_id' => array('S' => 'oauth_test_client'),
                'client_secret' => array('S' => 'testpass'),
                'grant_types' => array('S' => 'implicit password')
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_access_tokens',
            'Item' => array(
                'access_token' => array('S' => 'testtoken'),
                'client_id' => array('S' => 'Some Client'),
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_access_tokens',
            'Item' => array(
                 'access_token' => array('S' => 'accesstoken-openid-connect'),
                 'client_id' => array('S' => 'Some Client'),
                 'user_id' => array('S' => 'testuser'),
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_authorization_codes',
            'Item' => array(
                'authorization_code' => array('S' => 'testcode'),
                'client_id' => array('S' => 'Some Client'),
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_users',
            'Item' => array(
                'username' => array('S' => 'testuser'),
                'password' => array('S' => 'password'),
                'email' => array('S' => 'testuser@test.com'),
                'email_verified' => array('S' => 'true'),
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_public_keys',
            'Item' => array(
                'client_id' => array('S' => 'ClientID_One'),
                'public_key' => array('S' => 'client_1_public'),
                'private_key' => array('S' => 'client_1_private'),
                'encryption_algorithm' => array('S' => 'RS256'),
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_public_keys',
            'Item' => array(
                'client_id' => array('S' => 'ClientID_Two'),
                'public_key' => array('S' => 'client_2_public'),
                'private_key' => array('S' => 'client_2_private'),
                'encryption_algorithm' => array('S' => 'RS256'),
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_public_keys',
            'Item' => array(
                'client_id' => array('S' => '0'),
                'public_key' => array('S' => $this->getTestPublicKey()),
                'private_key' => array('S' => $this->getTestPrivateKey()),
                'encryption_algorithm' => array('S' => 'RS256'),
            )
        ));

        $client->putItem(array(
            'TableName' => $prefix.'oauth_jwt',
            'Item' => array(
                'client_id' => array('S' => 'oauth_test_client'),
                'subject' => array('S' => 'test_subject'),
                'public_key' => array('S' => $this->getTestPublicKey()),
            )
        ));
    }

    private function getEnvVar($var, $default = null)
    {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : (getenv($var) ?: $default);
    }
}
