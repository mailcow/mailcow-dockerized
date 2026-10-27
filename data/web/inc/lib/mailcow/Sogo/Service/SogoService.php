<?php

declare(strict_types=1);

namespace Mailcow\Sogo\Service;

/**
 * SOGo: its user view, its cache and sogo-tool in the sogo container through dockerapi. The legacy calls sit in
 * protected methods so tests can replace them.
 */
class SogoService
{
    /** More changed mailboxes than this: one full view rebuild instead of single updates */
    private const SINGLE_UPDATES = 25;

    /** Refreshes the SOGo identities (visible aliases, send-as addresses) of the given mailboxes */
    public function refreshStaticView(array $usernames): void
    {
        $usernames = array_values(array_unique($usernames));
        if (empty($usernames)) {
            return;
        }
        if (count($usernames) > self::SINGLE_UPDATES) {
            $this->updateStaticView(null);
            return;
        }
        foreach ($usernames as $username) {
            $this->updateStaticView($username);
        }
    }

    /** SOGo caches ACLs and user settings in memcached */
    public function flushCache(): void
    {
        $this->flushMemcached();
    }

    /**
     * Subscribes the users to a folder of the owner (sogo-tool manage-acl), or unsubscribes them.
     *
     * @param string $folder Calendar/<id> or Contacts/<id>
     * @return ?string null on success, else the error of dockerapi
     */
    public function subscribe(string $owner, string $folder, array $usernames, bool $unsubscribe = false): ?string
    {
        return self::error($this->exec(['cmd' => 'sogo', 'task' => 'subscribe', 'owner' => $owner, 'folder' => $folder,
            'users' => array_values($usernames), 'unsubscribe' => $unsubscribe]));
    }

    /**
     * Creates the personal folders of users that never logged in to SOGo (sogo-tool init-user).
     *
     * @return ?string null on success, else the error of dockerapi
     */
    public function initUsers(array $usernames): ?string
    {
        return self::error($this->exec(['cmd' => 'sogo', 'task' => 'init_user', 'users' => array_values($usernames)]));
    }

    protected function updateStaticView(?string $username): void
    {
        update_sogo_static_view($username);
    }

    protected function flushMemcached(): void
    {
        flush_memcached();
    }

    protected function exec(array $fields): mixed
    {
        return docker('post', 'sogo-mailcow', 'exec', $fields);
    }

    private static function error(mixed $response): ?string
    {
        $result = json_decode((string)$response, true);
        return (($result['type'] ?? '') === 'success') ? null : (string)($result['msg'] ?? 'dockerapi did not answer');
    }
}
