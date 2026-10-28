<?php

declare(strict_types=1);

namespace Mailcow\Dovecot\Service;

/**
 * doveadm in the dovecot container through dockerapi. The docker() call sits in a protected method so tests can
 * replace it.
 */
class DovecotService
{
    /**
     * Sets the rights of an ACL identifier (user=<name>, group=<name>) on every folder of the owner; no rights
     * remove it.
     *
     * @return ?string null on success, else the error of dockerapi
     */
    public function setAclAll(string $owner, string $identifier, array $rights): ?string
    {
        return self::error($this->exec(['cmd' => 'doveadm', 'task' => 'set_acl_all', 'user' => $owner, 'id' => $identifier,
            'rights' => array_values($rights)]));
    }

    /**
     * Subscribes the users to every folder of the owner they see under Shared/<owner>/, or unsubscribes them.
     *
     * @return ?string null on success, else the error of dockerapi
     */
    public function subscribeShared(string $owner, array $usernames, bool $unsubscribe = false): ?string
    {
        return self::error($this->exec(['cmd' => 'doveadm', 'task' => 'subscribe_shared', 'owner' => $owner,
            'users' => array_values($usernames), 'unsubscribe' => $unsubscribe]));
    }

    /** Drops the cached userdb results, so changed userdb fields apply at the next login instead of after auth_cache_ttl */
    public function flushAuthCache(array $usernames): void
    {
        if (!empty($usernames)) {
            $this->exec(['cmd' => 'doveadm', 'task' => 'auth_cache_flush', 'users' => array_values($usernames)]);
        }
    }

    protected function exec(array $fields): mixed
    {
        return docker('post', 'dovecot-mailcow', 'exec', $fields);
    }

    private static function error(mixed $response): ?string
    {
        $result = json_decode((string)$response, true);
        return (($result['type'] ?? '') === 'success') ? null : (string)($result['msg'] ?? 'dockerapi did not answer');
    }
}
