<?php

namespace LdapRecord;

use LDAP\Connection as RawLdapConnection;

class Ldap implements LdapInterface
{
    use DetectsErrors;
    use HandlesConnection;

    /**
     * {@inheritdoc}
     */
    public function getEntries(mixed $result): array
    {
        return $this->executeFailableOperation(function () use ($result) {
            return ldap_get_entries($this->connection, $result);
        });
    }

    /**
     * Retrieves the first entry from a search result.
     *
     * @see http://php.net/manual/en/function.ldap-first-entry.php
     *
     * @param  \Ldap\Result  $result
     */
    public function getFirstEntry(mixed $result): mixed
    {
        return $this->executeFailableOperation(function () use ($result) {
            return ldap_first_entry($this->connection, $result);
        });
    }

    /**
     * Retrieves the next entry from a search result.
     *
     * @see http://php.net/manual/en/function.ldap-next-entry.php
     *
     * @param  \Ldap\ResultEntry  $entry
     */
    public function getNextEntry(mixed $entry): mixed
    {
        return $this->executeFailableOperation(function () use ($entry) {
            return ldap_next_entry($this->connection, $entry);
        });
    }

    /**
     * Retrieves the ldap entry's attributes.
     *
     * @see http://php.net/manual/en/function.ldap-get-attributes.php
     *
     * @param  \Ldap\ResultEntry  $entry
     */
    public function getAttributes(mixed $entry): array|false
    {
        return $this->executeFailableOperation(function () use ($entry) {
            return ldap_get_attributes($this->connection, $entry);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function countEntries(mixed $result): int
    {
        return $this->executeFailableOperation(function () use ($result) {
            return ldap_count_entries($this->connection, $result);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function compare(string $dn, string $attribute, string $value, ?array $controls = null): bool|int
    {
        return $this->executeFailableOperation(function () use ($dn, $attribute, $value, $controls) {
            return ldap_compare($this->connection, $dn, $attribute, $value, $controls);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getLastError(): ?string
    {
        if (! $this->connection) {
            return null;
        }

        return ldap_error($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function getDetailedError(): ?DetailedError
    {
        if (! $number = $this->errNo()) {
            return null;
        }

        $this->getOption(LDAP_OPT_DIAGNOSTIC_MESSAGE, $message);

        return new DetailedError($number, $this->err2Str($number), $message);
    }

    /**
     * {@inheritDoc}
     */
    public function getValuesLen(mixed $entry, string $attribute): array|false
    {
        return $this->executeFailableOperation(function () use ($entry, $attribute) {
            return ldap_get_values_len($this->connection, $entry, $attribute);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function setOption(int $option, mixed $value): bool
    {
        return ldap_set_option($this->connection, $option, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption(int $option, mixed &$value = null): mixed
    {
        ldap_get_option($this->connection, $option, $value);

        return $value;
    }

    /**
     * Set a callback function to do re-binds on referral chasing.
     *
     * @see http://php.net/manual/en/function.ldap-set-rebind-proc.php
     */
    public function setRebindCallback(callable $callback): bool
    {
        return ldap_set_rebind_proc($this->connection, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function startTLS(): bool
    {
        return $this->secure = $this->executeFailableOperation(function () {
            return ldap_start_tls($this->connection);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function connect(string|array $hosts = [], int $port = 389, ?string $protocol = null): bool
    {
        $this->bound = false;
        $this->protocol = $protocol;
        $this->host = $this->makeConnectionUris($hosts, $port);

        $this->connection = $this->executeFailableOperation(function () {
            return ldap_connect($this->host);
        });

        return $this->connection instanceof RawLdapConnection;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        $result = false;

        if ($this->connection instanceof RawLdapConnection) {
            $result = @ldap_close($this->connection);
        }

        $this->bound = false;
        $this->secure = false;

        $this->host = null;
        $this->protocol = null;
        $this->connection = null;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $dn, string $filter, array $fields, bool $onlyAttributes = false, int $size = 0, int $time = 0, int $deref = LDAP_DEREF_NEVER, ?array $controls = null): mixed
    {
        return $this->executeFailableOperation(function () use (
            $dn,
            $filter,
            $fields,
            $onlyAttributes,
            $size,
            $time,
            $deref,
            $controls
        ) {
            return ldap_search($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $controls);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function list(string $dn, string $filter, array $fields, bool $onlyAttributes = false, int $size = 0, int $time = 0, int $deref = LDAP_DEREF_NEVER, ?array $controls = null): mixed
    {
        return $this->executeFailableOperation(function () use (
            $dn,
            $filter,
            $fields,
            $onlyAttributes,
            $size,
            $time,
            $deref,
            $controls
        ) {
            return ldap_list($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $controls);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $dn, string $filter, array $fields, bool $onlyAttributes = false, int $size = 0, int $time = 0, int $deref = LDAP_DEREF_NEVER, ?array $controls = null): mixed
    {
        return $this->executeFailableOperation(function () use (
            $dn,
            $filter,
            $fields,
            $onlyAttributes,
            $size,
            $time,
            $deref,
            $controls
        ) {
            return ldap_read($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $controls);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function parseResult(mixed $result, int &$errorCode = 0, ?string &$dn = null, ?string &$errorMessage = null, ?array &$referrals = null, ?array &$controls = null): LdapResultResponse|false
    {
        if (ldap_parse_result($this->connection, $result, $errorCode, $dn, $errorMessage, $referrals, $controls)) {
            return new LdapResultResponse(
                $errorCode,
                $dn,
                $errorMessage,
                $referrals,
                $controls
            );
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function bind(?string $dn = null, ?string $password = null, ?array $controls = null): LdapResultResponse
    {
        /** @var \LDAP\Result $result */
        $result = $this->executeFailableOperation(function () use ($dn, $password, $controls) {
            return ldap_bind_ext($this->connection, $dn, $password ? html_entity_decode($password) : null, $controls);
        });

        $response = $this->parseResult($result);

        $this->handleBindResponse($response);

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function saslBind(?string $dn = null, ?string $password = null, array $options = []): bool
    {
        return $this->executeFailableOperation(function () use ($dn, $password, $options) {
            $options = array_merge([
                'mech' => null,
                'realm' => null,
                'authc_id' => null,
                'authz_id' => null,
                'props' => null,
            ], $options);

            return $this->bound = ldap_sasl_bind(
                $this->connection,
                $dn,
                $password ? html_entity_decode($password) : null,
                $options['mech'],
                $options['realm'],
                $options['authc_id'],
                $options['authz_id'],
                $options['props'],
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $dn, array $entry): bool
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_add($this->connection, $dn, $entry);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $dn): bool
    {
        return $this->executeFailableOperation(function () use ($dn) {
            return ldap_delete($this->connection, $dn);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $dn, string $newRdn, string $newParent, bool $deleteOldRdn = false): bool
    {
        return $this->executeFailableOperation(function () use (
            $dn,
            $newRdn,
            $newParent,
            $deleteOldRdn
        ) {
            return ldap_rename($this->connection, $dn, $newRdn, $newParent, $deleteOldRdn);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function modify(string $dn, array $entry): bool
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_modify($this->connection, $dn, $entry);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function modifyBatch(string $dn, array $values): bool
    {
        return $this->executeFailableOperation(function () use ($dn, $values) {
            return ldap_modify_batch($this->connection, $dn, $values);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function modAdd(string $dn, array $entry): bool
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_mod_add($this->connection, $dn, $entry);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function modReplace(string $dn, array $entry): bool
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_mod_replace($this->connection, $dn, $entry);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function modDelete(string $dn, array $entry): bool
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_mod_del($this->connection, $dn, $entry);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function freeResult(mixed $result): bool
    {
        return ldap_free_result($result);
    }

    /**
     * {@inheritdoc}
     */
    public function errNo(): ?int
    {
        return $this->connection ? ldap_errno($this->connection) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function err2Str(int $number): string
    {
        return ldap_err2str($number);
    }

    /**
     * Get the extended error hex code of the last command.
     */
    public function getExtendedErrorHex(): ?string
    {
        if (preg_match("/(?<=data\s).*?(?=,)/", $this->getExtendedError(), $code)) {
            return $code[0];
        }

        return null;
    }

    /**
     * Get the extended error code of the last command.
     */
    public function getExtendedErrorCode(): string|false
    {
        return $this->extractDiagnosticCode($this->getExtendedError());
    }

    /**
     * Extract the diagnostic code from the message.
     */
    public function extractDiagnosticCode(string $message): string|false
    {
        preg_match('/^([\da-fA-F]+):/', $message, $matches);

        return $matches[1] ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function getDiagnosticMessage(): ?string
    {
        $this->getOption(LDAP_OPT_ERROR_STRING, $message);

        return $message;
    }
}
