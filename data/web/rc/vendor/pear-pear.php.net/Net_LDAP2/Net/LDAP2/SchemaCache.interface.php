<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
* File containing the Net_LDAP2_SchemaCache interface class.
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
* Interface describing a custom schema cache object
*
* To implement a custom schema cache, one must implement this interface and
* pass the instanciated object to Net_LDAP2s registerSchemaCache() method.
*/
interface Net_LDAP2_SchemaCache
{
    /**
    * Return the schema object from the cache
    *
    * Net_LDAP2 will consider anything returned invalid, except
    * a valid Net_LDAP2_Schema object.
    * In case you return a Net_LDAP2_Error, this error will be routed
    * to the return of the $ldap->schema() call.
    * If you return something else, Net_LDAP2 will
    * fetch a fresh Schema object from the LDAP server.
    *
    * You may want to implement a cache aging mechanism here too.
    *
    * @return Net_LDAP2_Schema|Net_LDAP2_Error|false
    */
    public function loadSchema();

    /**
    * Store a schema object in the cache
    *
    * This method will be called, if Net_LDAP2 has fetched a fresh
    * schema object from LDAP and wants to init or refresh the cache.
    *
    * In case of errors you may return a Net_LDAP2_Error which will
    * be routet to the client.
    * Note that doing this prevents, that the schema object fetched from LDAP
    * will be given back to the client, so only return errors if storing
    * of the cache is something crucial (e.g. for doing something else with it).
    * Normaly you dont want to give back errors in which case Net_LDAP2 needs to
    * fetch the schema once per script run and instead use the error
    * returned from loadSchema().
    *
    * @return true|Net_LDAP2_Error
    */
    public function storeSchema($schema);
}
