<?php

declare(strict_types=1);

namespace Mailcow\Scim\Model;

/**
 * A SCIM Group: a mailbox group with a `scim_group_maps` row. Its members are the members the IdP set that are
 * still group members and SCIM users visible to the token: manual members of the mailbox group are not shown, so
 * a SCIM remove always takes effect for the IdP. Address, send-as, shares and template are maintained in mailcow.
 */
class Group
{
    public string $scimId = '';
    public int $groupId = 0;
    public string $domain = '';
    public string $name = '';
    /** set by token $tokenId; other tokens do not see it (RFC 7643 3.1: scoped to the provisioning client) */
    public ?string $externalId = null;
    /** the token that set externalId (or created the group) */
    public ?int $tokenId = null;
    /** @var array<string, string> SCIM id => username */
    public array $members = [];
    /** @var string[] usernames the IdP set as members, of all domains */
    public array $assigned = [];
    public ?string $created = null;
    public ?string $modified = null;

    /** Builds a group from a `mailbox_group` row joined with its `scim_group_maps` columns (scim_id, external_id, token_id) */
    public static function fromRow(array $row): self
    {
        $group = new self();
        $group->scimId = (string)$row['scim_id'];
        $group->groupId = (int)$row['id'];
        $group->domain = (string)$row['domain'];
        $group->name = (string)$row['name'];
        $group->externalId = ($row['external_id'] ?? '') !== '' ? (string)$row['external_id'] : null;
        $group->tokenId = isset($row['token_id']) ? (int)$row['token_id'] : null;
        $group->assigned = json_decode((string)($row['scim_members'] ?? '[]'), true) ?: [];
        $group->created = isset($row['created']) ? (string)$row['created'] : null;
        $group->modified = isset($row['modified']) ? (string)$row['modified'] : null;
        return $group;
    }

    /** SCIM representation (RFC 7643 4.2) */
    public function toResource(string $base): array
    {
        $resource = ['schemas' => [Schema::GROUP], 'id' => $this->scimId];
        if ($this->externalId !== null) {
            $resource['externalId'] = $this->externalId;
        }
        $resource['displayName'] = $this->name;
        $resource['members'] = [];
        foreach ($this->members as $id => $username) {
            $resource['members'][] = ['value' => (string)$id, '$ref' => $base . '/Users/' . rawurlencode((string)$id),
                'type' => 'User', 'display' => $username];
        }
        $resource['meta'] = array_filter([
            'resourceType' => 'Group',
            'created' => Schema::time($this->created),
            // never modified: the creation time (RFC 7643 3.1)
            'lastModified' => Schema::time($this->modified ?? $this->created),
            'location' => $base . '/Groups/' . rawurlencode($this->scimId),
        ], fn(?string $value): bool => $value !== null);
        return $resource;
    }
}
