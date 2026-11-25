<?php

declare(strict_types=1);

namespace Mailcow\Identity\Directory;

use Mailcow\Identity\Http\KeycloakClient;
use Mailcow\Identity\Model\DirectoryGroup;
use Mailcow\Identity\Model\DirectoryUser;
use Mailcow\Identity\Model\Listing;
use Mailcow\Identity\Model\Settings;

/**
 * Keycloak realm users and the groups selected by their path. Only direct group members count.
 */
class KeycloakDirectory implements Directory
{
    private const PAGE = 100;

    public function __construct(private KeycloakClient $client, private Settings $settings)
    {
    }

    public function listAll(bool $withGroups): Listing
    {
        $users = [];
        foreach ($this->paged('/users', ['briefRepresentation' => 'false']) as $row) {
            if (isset($row['id'])) {
                $user = self::user($row);
                $users[$user->id] = $user;
            }
        }
        return new Listing($users, $withGroups ? $this->groups($users) : null);
    }

    /** A user of the admin REST API */
    public static function user(array $row): DirectoryUser
    {
        $email = (isset($row['email']) && $row['email'] !== '') ? strtolower((string)$row['email']) : null;
        return new DirectoryUser((string)$row['id'], $email, trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? '')),
            !empty($row['enabled']), array_map('strval', (array)($row['attributes']['mailcow_template'] ?? [])));
    }

    /**
     * @param array<string, DirectoryUser> $users
     * @return array<string, DirectoryGroup> selected groups, members limited to listed users
     */
    private function groups(array $users): array
    {
        $groups = [];
        foreach ($this->paged('/groups', ['briefRepresentation' => 'false']) as $row) {
            $this->collect($row, $groups);
        }
        foreach ($groups as $id => $group) {
            foreach ($this->paged('/groups/' . rawurlencode((string)$id) . '/members', ['briefRepresentation' => 'true']) as $member) {
                if (isset($member['id'], $users[$member['id']])) {
                    $group->memberIds[] = (string)$member['id'];
                }
            }
        }
        return $groups;
    }

    /** Adds the group and its subgroups that match the configured paths (Keycloak >= 23 lists children separately) */
    private function collect(array $row, array &$groups): void
    {
        $path = (string)($row['path'] ?? '/' . ($row['name'] ?? ''));
        if ($this->selected($path)) {
            $mail = $row['attributes']['mail'][0] ?? null;
            $groups[(string)$row['id']] = new DirectoryGroup((string)$row['id'], (string)($row['name'] ?? $path),
                is_string($mail) && $mail !== '' ? strtolower($mail) : null);
        }
        $children = $row['subGroups'] ?? [];
        if (empty($children) && (int)($row['subGroupCount'] ?? 0) > 0) {
            $children = $this->paged('/groups/' . rawurlencode((string)$row['id']) . '/children', ['briefRepresentation' => 'false']);
        }
        foreach ($children as $child) {
            $this->collect($child, $groups);
        }
    }

    /** '/a' exactly, '/a/*' every group below /a, '/*' every group */
    private function selected(string $path): bool
    {
        foreach ($this->settings->groupPaths as $pattern) {
            if ($pattern === $path || (str_ends_with($pattern, '/*') && str_starts_with($path, substr($pattern, 0, -1)))) {
                return true;
            }
        }
        return false;
    }

    /** @return array[] all rows of a paged list endpoint */
    private function paged(string $path, array $query): array
    {
        $rows = [];
        for ($first = 0; ; $first += self::PAGE) {
            $page = array_values($this->client->get($path, $query + ['first' => $first, 'max' => self::PAGE]));
            $rows = array_merge($rows, $page);
            if (count($page) < self::PAGE) {
                return $rows;
            }
        }
    }
}
