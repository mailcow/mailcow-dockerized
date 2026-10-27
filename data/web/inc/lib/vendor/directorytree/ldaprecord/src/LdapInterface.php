<?php

namespace LdapRecord;

use LDAP\Connection;

/**
 * @see https://ldap.com/ldap-oid-reference-guide
 * @see http://msdn.microsoft.com/en-us/library/cc223359.aspx
 * @see https://help.univention.com/t/openldap-debug-level/19301
 */
interface LdapInterface
{
    /**
     * The standard LDAP protocol string.
     *
     * @var string
     */
    public const PROTOCOL = 'ldap://';

    /**
     * The SSL LDAP protocol string.
     *
     * @var string
     */
    public const PROTOCOL_SSL = 'ldaps://';

    /**
     * The standard LDAP port number.
     *
     * @var int
     */
    public const PORT = 389;

    /**
     * The LDAP SSL port number.
     *
     * @var int
     */
    public const PORT_SSL = 636;

    /**
     * Print entry and exit from routines.
     *
     * @var int
     */
    public const DEBUG_TRACE = 1;

    /**
     * Print packet activity.
     *
     * @var int
     */
    public const DEBUG_PACKETS = 2;

    /**
     * Print data arguments from requests.
     *
     * @var int
     */
    public const DEBUG_ARGS = 4;

    /**
     * Print connection activity.
     *
     * @var int
     */
    public const DEBUG_CONNS = 8;

    /**
     * Print encoding and decoding of data.
     *
     * @var int
     */
    public const DEBUG_BER = 16;

    /**
     * Print search filters.
     *
     * @var int
     */
    public const DEBUG_FILTER = 32;

    /**
     * Print configuration file processing.
     *
     * @var int
     */
    public const DEBUG_CONFIG = 64;

    /**
     * Print Access Control List activities.
     *
     * @var int
     */
    public const DEBUG_ACL = 128;

    /**
     * Print operational statistics.
     *
     * @var int
     */
    public const DEBUG_STATS = 256;

    /**
     * Print more detailed statistics.
     *
     * @var int
     */
    public const DEBUG_STATS2 = 512;

    /**
     * Print communication with shell backends.
     *
     * @var int
     */
    public const DEBUG_SHELL = 1024;

    /**
     * Print entry parsing.
     *
     * @var int
     */
    public const DEBUG_PARSE = 2048;

    /**
     * Print LDAPSync replication.
     *
     * @var int
     */
    public const DEBUG_SYNC = 16384;

    /**
     * Print referral activities.
     *
     * @var int
     */
    public const DEBUG_REFERRAL = 32768;

    /**
     * Print error conditions.
     *
     * @var int
     */
    public const DEBUG_ERROR = 32768;

    /**
     * Print all levels of debug.
     *
     * @var int
     */
    public const DEBUG_ANY = 65535;

    /**
     * OID for StartTLS extended operation. Signals the server to initiate a TLS connection.
     *
     * @var string
     */
    public const OID_SERVER_START_TLS = '1.3.6.1.4.1.1466.20037';

    /**
     * OID for Paged Results Control. Used to retrieve search results in pages.
     *
     * @var string
     */
    public const OID_SERVER_PAGED_RESULTS = '1.2.840.113556.1.4.319';

    /**
     * OID for Show Deleted Control. Includes deleted entries in the search results.
     *
     * @var string
     */
    public const OID_SERVER_SHOW_DELETED = '1.2.840.113556.1.4.417';

    /**
     * OID for Server Side Sort Control. Requests the server to sort the search results.
     *
     * @var string
     */
    public const OID_SERVER_SORT = '1.2.840.113556.1.4.473';

    /**
     * OID for Cross-Domain Move Target Control. Used in cross-domain move operations.
     *
     * @var string
     */
    public const OID_SERVER_CROSSDOM_MOVE_TARGET = '1.2.840.113556.1.4.521';

    /**
     * OID for LDAP Notification Control. Used to register for change notifications.
     *
     * @var string
     */
    public const OID_SERVER_NOTIFICATION = '1.2.840.113556.1.4.528';

    /**
     * OID for Extended DN Control. Requests extended DN information in search results.
     *
     * @var string
     */
    public const OID_SERVER_EXTENDED_DN = '1.2.840.113556.1.4.529';

    /**
     * OID for Lazy Commit Control. Delays the actual commit of changes until requested.
     *
     * @var string
     */
    public const OID_SERVER_LAZY_COMMIT = '1.2.840.113556.1.4.619';

    /**
     * OID for Security Descriptor Flags Control. Used to manipulate security descriptor flags.
     *
     * @var string
     */
    public const OID_SERVER_SD_FLAGS = '1.2.840.113556.1.4.801';

    /**
     * OID for Tree Delete Control. Enables the deletion of an entire subtree.
     *
     * @var string
     */
    public const OID_SERVER_TREE_DELETE = '1.2.840.113556.1.4.805';

    /**
     * OID for DirSync Control. Used for directory synchronization operations.
     *
     * @var string
     */
    public const OID_SERVER_DIRSYNC = '1.2.840.113556.1.4.841';

    /**
     * OID for Verify Name Control. Allows verification of an entry without retrieving attributes.
     *
     * @var string
     */
    public const OID_SERVER_VERIFY_NAME = '1.2.840.113556.1.4.1338';

    /**
     * OID for Domain Scope Control. Limits a search to the current domain.
     *
     * @var string
     */
    public const OID_SERVER_DOMAIN_SCOPE = '1.2.840.113556.1.4.1339';

    /**
     * OID for Search Options Control. Used to set various search options.
     *
     * @var string
     */
    public const OID_SERVER_SEARCH_OPTIONS = '1.2.840.113556.1.4.1340';

    /**
     * OID for Permissive Modify Control. Allows modifications even if some attributes are missing.
     *
     * @var string
     */
    public const OID_SERVER_PERMISSIVE_MODIFY = '1.2.840.113556.1.4.1413';

    /**
     * OID for Authentication Service Queries (ASQ) Control. Used to perform ASQ operations.
     *
     * @var string
     */
    public const OID_SERVER_ASQ = '1.2.840.113556.1.4.1504';

    /**
     * OID for Fast Bind Control. Optimizes the bind process for faster authentication.
     *
     * @var string
     */
    public const OID_SERVER_FAST_BIND = '1.2.840.113556.1.4.1781';

    /**
     * OID for Virtual List View (VLV) Request Control. Used to request a specific range of entries.
     *
     * @var string
     */
    public const OID_SERVER_CONTROL_VLVREQUEST = '2.16.840.1.113730.3.4.9';

    /**
     * OID for the 'matchingRuleInChain' matching rule. Used for substring searches in multi-valued attributes.
     */
    public const OID_MATCHING_RULE_IN_CHAIN = '1.2.840.113556.1.4.1941';

    /**
     * Set the current connection to use SSL.
     */
    public function ssl(): static;

    /**
     * Determine if the current connection instance is using SSL.
     */
    public function isUsingSSL(): bool;

    /**
     * Set the current connection to use TLS.
     */
    public function tls(): static;

    /**
     * Determine if the current connection instance is using TLS.
     */
    public function isUsingTLS(): bool;

    /**
     * Determine if the connection is bound.
     */
    public function isBound(): bool;

    /**
     * Determine if the connection is secure over TLS or SSL.
     */
    public function isSecure(): bool;

    /**
     * Determine if the connection has been created.
     */
    public function isConnected(): bool;

    /**
     * Determine the connection is able to modify passwords.
     */
    public function canChangePasswords(): bool;

    /**
     * Get the full LDAP host URL.
     *
     * Ex: ldap://192.168.1.1:386
     */
    public function getHost(): ?string;

    /**
     * Get the underlying raw LDAP connection.
     */
    public function getConnection(): ?Connection;

    /**
     * Retrieve the entries from a search result.
     *
     * @see http://php.net/manual/en/function.ldap-get-entries.php
     *
     * @param  \LDAP\Result  $result
     */
    public function getEntries(mixed $result): array;

    /**
     * Get the entry identifier for first entry in the result.
     *
     * @see https://www.php.net/manual/en/function.ldap-first-entry.php
     *
     * @param  \LDAP\Result  $result
     */
    public function getFirstEntry(mixed $result): mixed;

    /**
     * Retrieve the next result entry.
     *
     * @see https://www.php.net/manual/en/function.ldap-next-entry.php
     *
     * @param  \LDAP\Result  $entry
     */
    public function getNextEntry(mixed $entry): mixed;

    /**
     * Reads attributes and values from an entry in the search result.
     *
     * @see https://www.php.net/manual/en/function.ldap-get-attributes.php
     *
     * @param  \LDAP\Result  $entry
     */
    public function getAttributes(mixed $entry): array|false;

    /**
     * Reads all the values of the attribute in the entry in the result.
     *
     * @param  \LDAP\Result  $entry
     */
    public function getValuesLen(mixed $entry, string $attribute): array|false;

    /**
     * Retrieve the last error on the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-error.php
     */
    public function getLastError(): ?string;

    /**
     * Return detailed information about an error.
     *
     * Returns null when there was a successful last request.
     *
     * Returns DetailedError when there was an error.
     */
    public function getDetailedError(): ?DetailedError;

    /**
     * Count the number of entries in a search.
     *
     * @see https://www.php.net/manual/en/function.ldap-count-entries.php
     *
     * @param  \LDAP\Result  $result
     */
    public function countEntries(mixed $result): int;

    /**
     * Compare value of attribute found in entry specified with DN.
     */
    public function compare(string $dn, string $attribute, string $value, ?array $controls = null): bool|int;

    /**
     * Set an option on the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-set-option.php
     */
    public function setOption(int $option, mixed $value): bool;

    /**
     * Set multiple options on the current connection.
     */
    public function setOptions(array $options = []): void;

    /**
     * Set a callback function to do re-binds on referral chasing.
     *
     * @see https://www.php.net/manual/en/function.ldap-set-rebind-proc.php
     */
    public function setRebindCallback(callable $callback): bool;

    /**
     * Get the value for the LDAP option.
     *
     * @see https://www.php.net/manual/en/function.ldap-get-option.php
     */
    public function getOption(int $option, mixed &$value = null): mixed;

    /**
     * Starts a connection using TLS.
     *
     * @see http://php.net/manual/en/function.ldap-start-tls.php
     *
     * @throws LdapRecordException
     */
    public function startTLS(): bool;

    /**
     * Connects to the specified hostname using the specified port.
     *
     * @see http://php.net/manual/en/function.ldap-start-tls.php
     */
    public function connect(string|array $hosts = [], int $port = 389, ?string $protocol = null): bool;

    /**
     * Closes the current connection.
     *
     * Returns false if no connection is present.
     *
     * @see http://php.net/manual/en/function.ldap-close.php
     */
    public function close(): bool;

    /**
     * Performs a search on the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-search.php
     *
     * @return \LDAP\Result
     */
    public function search(string $dn, string $filter, array $fields, bool $onlyAttributes = false, int $size = 0, int $time = 0, int $deref = LDAP_DEREF_NEVER, ?array $controls = null): mixed;

    /**
     * Performs a single level search on the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-list.php
     *
     * @return \LDAP\Result
     */
    public function list(string $dn, string $filter, array $fields, bool $onlyAttributes = false, int $size = 0, int $time = 0, int $deref = LDAP_DEREF_NEVER, ?array $controls = null): mixed;

    /**
     * Reads an entry on the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-read.php
     *
     * @return \LDAP\Result
     */
    public function read(string $dn, string $filter, array $fields, bool $onlyAttributes = false, int $size = 0, int $time = 0, int $deref = LDAP_DEREF_NEVER, ?array $controls = null): mixed;

    /**
     * Extract information from an LDAP result.
     *
     * @see https://www.php.net/manual/en/function.ldap-parse-result.php
     *
     * @param  \LDAP\Result  $result
     */
    public function parseResult(mixed $result, int &$errorCode = 0, ?string &$dn = null, ?string &$errorMessage = null, ?array &$referrals = null, ?array &$controls = null): LdapResultResponse|false;

    /**
     * Bind to the LDAP directory.
     *
     * @see http://php.net/manual/en/function.ldap-bind.php
     *
     * @throws LdapRecordException
     */
    public function bind(?string $dn = null, ?string $password = null, ?array $controls = null): LdapResultResponse;

    /**
     * Bind to the LDAP directory using SASL.
     *
     * SASL options:
     *  - mech: Mechanism (Defaults: null)
     *  - realm: Realm (Defaults: null)
     *  - authc_id: Verification Identity (Defaults: null)
     *  - authz_id: Authorization Identity (Defaults: null)
     *  - props: Options for Authorization Identity (Defaults: null)
     *
     * @see https://php.net/manual/en/function.ldap-sasl-bind.php
     * @see https://www.iana.org/assignments/sasl-mechanisms/sasl-mechanisms.xhtml
     */
    public function saslBind(?string $dn = null, ?string $password = null, array $options = []): bool;

    /**
     * Adds an entry to the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-add.php
     *
     * @throws LdapRecordException
     */
    public function add(string $dn, array $entry): bool;

    /**
     * Deletes an entry on the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-delete.php
     *
     * @throws LdapRecordException
     */
    public function delete(string $dn): bool;

    /**
     * Modify the name of an entry on the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-rename.php
     *
     * @throws LdapRecordException
     */
    public function rename(string $dn, string $newRdn, string $newParent, bool $deleteOldRdn = false): bool;

    /**
     * Modifies an existing entry on the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-modify.php
     *
     * @throws LdapRecordException
     */
    public function modify(string $dn, array $entry): bool;

    /**
     * Batch modifies an existing entry on the current connection.
     *
     * @see http://php.net/manual/en/function.ldap-modify-batch.php
     *
     * @throws LdapRecordException
     */
    public function modifyBatch(string $dn, array $values): bool;

    /**
     * Add attribute values to current attributes.
     *
     * @see http://php.net/manual/en/function.ldap-mod-add.php
     *
     * @throws LdapRecordException
     */
    public function modAdd(string $dn, array $entry): bool;

    /**
     * Replaces attribute values with new ones.
     *
     * @see http://php.net/manual/en/function.ldap-mod-replace.php
     *
     * @throws LdapRecordException
     */
    public function modReplace(string $dn, array $entry): bool;

    /**
     * Delete attribute values from current attributes.
     *
     * @see http://php.net/manual/en/function.ldap-mod-del.php
     *
     * @throws LdapRecordException
     */
    public function modDelete(string $dn, array $entry): bool;

    /**
     * Frees up the memory allocated internally to store the result.
     *
     * @see https://www.php.net/manual/en/function.ldap-free-result.php
     *
     * @param  \LDAP\Result  $result
     */
    public function freeResult(mixed $result): bool;

    /**
     * Get the error number of the last command executed.
     *
     * @see http://php.net/manual/en/function.ldap-errno.php
     */
    public function errNo(): ?int;

    /**
     * Get the error string of the specified error number.
     *
     * @see http://php.net/manual/en/function.ldap-err2str.php
     */
    public function err2Str(int $number): string;

    /**
     * Get the LDAP protocol to utilize for the current connection.
     */
    public function getProtocol(): string;

    /**
     * Get the extended error code of the last command.
     */
    public function getExtendedError(): ?string;

    /**
     * Get the diagnostic message.
     */
    public function getDiagnosticMessage(): ?string;
}
