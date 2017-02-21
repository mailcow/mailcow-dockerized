<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
* File containing the example simple file based Schema Caching class.
*
* PHP version 5
*
* @category  Net
* @package   Net_LDAP2
* @author    Benedikt Hallinger <beni@php.net>
* @copyright 2009 Benedikt Hallinger
* @license   http://www.gnu.org/licenses/lgpl-3.0.txt LGPLv3
* @version   SVN: $Id$
* @link      http://pear.php.net/package/Net_LDAP2/
*/

/**
* A simple file based schema cacher with cache aging.
*
* Once the cache is too old, the loadSchema() method will return false, so
* Net_LDAP2 will fetch a fresh object from the LDAP server that will
* overwrite the current (outdated) old cache.
*/
class Net_LDAP2_SimpleFileSchemaCache implements Net_LDAP2_SchemaCache
{
    /**
    * Internal config of this cache
    *
    * @see Net_LDAP2_SimpleFileSchemaCache()
    * @var array
    */
    protected $config = array(
        'path'    => '/tmp/Net_LDAP_Schema.cache',
        'max_age' => 1200
    );

    /**
    * Initialize the simple cache
    *
    * Config is as following:
    *  path     Complete path to the cache file.
    *  max_age  Maximum age of cache in seconds, 0 means "endlessly".
    *
    * @param array $cfg Config array
    */
    public function __construct($cfg)
    {
    	foreach ($cfg as $key => $value) {
			if (array_key_exists($key, $this->config)) {
				if (gettype($this->config[$key]) != gettype($value)) {
					$this->getCore()->dropFatalError(__CLASS__.": Could not set config! Key $key does not match type ".gettype($this->config[$key])."!");
				}
				$this->config[$key] = $value;
			} else {
				$this->getCore()->dropFatalError(__CLASS__.": Could not set config! Key $key is not defined!");
			}
		}
    }

    /**
    * Return the schema object from the cache
    *
    * If file is existent and cache has not expired yet,
    * then the cache is deserialized and returned.
    *
    * @return Net_LDAP2_Schema|Net_LDAP2_Error|false
    */
    public function loadSchema()
    {
         $return = false; // Net_LDAP2 will load schema from LDAP
         if (file_exists($this->config['path'])) {
             $cache_maxage = filemtime($this->config['path']) + $this->config['max_age'];
             if (time() <= $cache_maxage || $this->config['max_age'] == 0) {
                 $return = unserialize(file_get_contents($this->config['path']));
             }
         }
         return $return;
    }

    /**
    * Store a schema object in the cache
    *
    * This method will be called, if Net_LDAP2 has fetched a fresh
    * schema object from LDAP and wants to init or refresh the cache.
    *
    * To invalidate the cache and cause Net_LDAP2 to refresh the cache,
    * you can call this method with null or false as value.
    * The next call to $ldap->schema() will then refresh the caches object.
    *
    * @param mixed $schema The object that should be cached
    * @return true|Net_LDAP2_Error|false
    */
    public function storeSchema($schema) {
        file_put_contents($this->config['path'], serialize($schema));
        return true;
    }
}
