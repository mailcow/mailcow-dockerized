<?php

declare(strict_types=1);

namespace Mailcow\Scim\Model;

/**
 * A SCIM User: a mailbox with authsource 'scim' and a `scim_maps` row. Without the row the mailbox is no SCIM
 * resource (not provisioned yet, or deleted through SCIM).
 */
class User
{
    public string $scimId = '';
    public string $username = '';
    public string $domain = '';
    public string $name = '';
    /** mailbox.active: 0 inactive, 1 active, 2 login disabled (mail is still delivered) */
    public int $active = 1;
    /** set by token $tokenId; other tokens do not see it (RFC 7643 3.1: scoped to the provisioning client) */
    public ?string $externalId = null;
    /** the token that set externalId (or created the user) */
    public ?int $tokenId = null;
    /** template mapped from the IdP's template attribute */
    public ?string $template = null;
    /** 1 = SCIM disabled the login (active 2); only then active=true allows it again */
    public int $disabled = 0;
    /** name parts as the IdP sent them, null: derived from $name */
    public ?string $givenName = null;
    public ?string $familyName = null;
    /** @var array<string, string> SCIM groups whose members include the user: SCIM id => displayName */
    public array $groups = [];
    public ?string $created = null;
    public ?string $modified = null;

    /** Builds a user from a `mailbox` row joined with its `scim_maps` columns (scim_id, external_id, template, disabled) */
    public static function fromRow(array $row): self
    {
        $user = new self();
        $user->scimId = (string)$row['scim_id'];
        $user->username = (string)$row['username'];
        $user->domain = (string)$row['domain'];
        $user->name = (string)($row['name'] ?? '');
        $user->active = (int)$row['active'];
        $user->externalId = ($row['external_id'] ?? '') !== '' ? (string)$row['external_id'] : null;
        $user->tokenId = isset($row['token_id']) ? (int)$row['token_id'] : null;
        $user->template = ($row['template'] ?? '') !== '' ? (string)$row['template'] : null;
        $user->disabled = (int)($row['disabled'] ?? 0);
        $user->givenName = isset($row['given_name']) ? (string)$row['given_name'] : null;
        $user->familyName = isset($row['family_name']) ? (string)$row['family_name'] : null;
        $user->created = isset($row['created']) ? (string)$row['created'] : null;
        $user->modified = isset($row['modified']) ? (string)$row['modified'] : null;
        return $user;
    }

    /** @return array{0: string, 1: string} givenName and familyName: as the IdP sent them, else split from the name */
    public function nameParts(): array
    {
        if ($this->givenName !== null || $this->familyName !== null) {
            return [(string)$this->givenName, (string)$this->familyName];
        }
        return array_pad(explode(' ', trim($this->name), 2), 2, '');
    }

    /** SCIM representation (RFC 7643 4.1) */
    public function toResource(string $base): array
    {
        [$given, $family] = $this->nameParts();
        $resource = ['schemas' => [Schema::USER], 'id' => $this->scimId, 'userName' => $this->username];
        if ($this->externalId !== null) {
            $resource['externalId'] = $this->externalId;
        }
        $resource += [
            'displayName' => $this->name,
            'name' => ['formatted' => $this->name, 'givenName' => $given, 'familyName' => $family],
            'active' => $this->active === 1,
            'emails' => [['value' => $this->username, 'type' => 'work', 'primary' => true]],
        ];
        foreach ($this->groups as $id => $name) {
            $resource['groups'][] = ['value' => (string)$id, '$ref' => $base . '/Groups/' . rawurlencode((string)$id),
                'display' => $name, 'type' => 'direct'];
        }
        if ($this->template !== null) {
            $resource['schemas'][] = Schema::EXT;
            $resource[Schema::EXT] = ['template' => $this->template];
        }
        $resource['meta'] = array_filter([
            'resourceType' => 'User',
            'created' => Schema::time($this->created),
            // never modified: the creation time (RFC 7643 3.1)
            'lastModified' => Schema::time($this->modified ?? $this->created),
            'location' => $base . '/Users/' . rawurlencode($this->scimId),
        ], fn(?string $value): bool => $value !== null);
        return $resource;
    }
}
