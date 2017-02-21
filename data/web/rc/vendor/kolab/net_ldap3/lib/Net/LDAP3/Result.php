<?php

/*
 +-----------------------------------------------------------------------+
 | Net/LDAP3/Result.php                                                  |
 |                                                                       |
 | Based on code created by the Roundcube Webmail team.                  |
 |                                                                       |
 | Copyright (C) 2006-2014, The Roundcube Dev Team                       |
 | Copyright (C) 2012-2014, Kolab Systems AG                             |
 |                                                                       |
 | This program is free software: you can redistribute it and/or modify  |
 | it under the terms of the GNU General Public License as published by  |
 | the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                   |
 |                                                                       |
 | This program is distributed in the hope that it will be useful,       |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of        |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the          |
 | GNU General Public License for more details.                          |
 |                                                                       |
 | You should have received a copy of the GNU General Public License     |
 | along with this program.  If not, see <http://www.gnu.org/licenses/>. |
 |                                                                       |
 | PURPOSE:                                                              |
 |   Provide advanced functionality for accessing LDAP directories       |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Authors: Thomas Bruederli <roundcube@gmail.com>                       |
 |          Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                 |
 +-----------------------------------------------------------------------+
*/

/**
 * Model class representing an LDAP search result
 *
 * @package LDAP
 */
class Net_LDAP3_Result implements Iterator
{
    protected $conn;
    protected $base_dn;
    protected $filter;
    protected $scope;

    private $count;
    private $current;
    private $iteratorkey = 0;

    /**
     * Default constructor
     *
     * @param resource $conn      LDAP link identifier
     * @param string   $base_dn   Base DN used to get this result
     * @param string   $filter    Filter query used to get this result
     * @param string   $scope     Scope of the result
     * @param resource $result    LDAP result entry identifier
     */
    function __construct($conn, $base_dn, $filter, $scope, $result)
    {
        $this->conn    = $conn;
        $this->base_dn = $base_dn;
        $this->filter  = $filter;
        $this->scope   = $scope;
        $this->result  = $result;
    }

    /**
     * Property value getter
     *
     * @param string $property Property name
     * @param mixed  $default  Return value if proprty is not set
     *
     * @return mixed Property value
     */
    public function get($property, $default = null)
    {
        return isset($this->$property) ? $this->$property : $default;
    }

    /**
     * Property value setter
     *
     * @param string $property Property name
     * @param mixed  $value    Property value
     */
    public function set($property, $value)
    {
        $this->$property = $value;
    }

    /**
     * Wrapper for ldap_sort()
     *
     * @param string $attr The attribute to use as a key in the sort
     *
     * @return bool True on success, False on failure
     */
    public function sort($attr)
    {
        // @TODO: Don't use ldap_sort() it's deprecated since PHP7
        // and will be removed in future
        return @ldap_sort($this->conn, $this->result, $attr);
    }

    /**
     * Get entries count
     *
     * @return int Number of entries in the result
     */
    public function count()
    {
        if (!isset($this->count)) {
            $this->count = ldap_count_entries($this->conn, $this->result);
        }

        return $this->count;
    }

    /**
     * Wrapper for ldap_get_entries()
     *
     * @param bool $normalize Optionally normalize the entries to a list of hash arrays
     *
     * @return array List of LDAP entries
     */
    public function entries($normalize = false)
    {
        $entries = ldap_get_entries($this->conn, $this->result);

        if ($normalize) {
            return Net_LDAP3::normalize_result($entries);
        }

        return $entries;
    }

    /**
     * Wrapper for ldap_get_dn() using the current entry pointer
     */
    public function get_dn()
    {
        return $this->current ? ldap_get_dn($this->conn, $this->current) : null;
    }


    /***  Implement PHP 5 Iterator interface to make foreach work  ***/

    function current()
    {
        $attrib       = ldap_get_attributes($this->conn, $this->current);
        $attrib['dn'] = ldap_get_dn($this->conn, $this->current);

        return $attrib;
    }

    function key()
    {
        return $this->iteratorkey;
    }

    function rewind()
    {
        $this->iteratorkey = 0;
        $this->current = ldap_first_entry($this->conn, $this->result);
    }

    function next()
    {
        $this->iteratorkey++;
        $this->current = ldap_next_entry($this->conn, $this->current);
    }

    function valid()
    {
        return (bool)$this->current;
    }
}
