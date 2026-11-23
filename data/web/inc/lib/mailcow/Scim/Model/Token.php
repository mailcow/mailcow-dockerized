<?php

declare(strict_types=1);

namespace Mailcow\Scim\Model;

/**
 * A SCIM Bearer token of an IdP (table `scim_tokens`). Only the SHA-256 of the token is stored, the token itself
 * is shown once after creation.
 */
class Token
{
    public ?int $id = null;
    public string $description = '';
    public string $hash = '';
    /** set = the token only sees users and groups of this domain, and creates groups there */
    public ?string $domainRestriction = null;
    /** domain of the groups an unrestricted token creates, null = no groups */
    public ?string $groupDomain = null;
    /** @var string[] IPs and CIDR networks the token may be used from, [] = any */
    public array $allowFrom = [];
    public ?string $defaultTemplate = null;
    /** @var string[] values of the extension attribute `template`, paired by index with $templates */
    public array $mappers = [];
    /** @var string[] mailbox templates */
    public array $templates = [];
    /** 1 = a changed userName renames the mailbox (within its domain), 0 = userName is immutable */
    public int $allowRename = 0;
    /** 1 = a rename keeps the old address as alias */
    public int $renameAlias = 1;
    public int $active = 1;
    public ?string $created = null;
    public ?string $modified = null;

    public static function fromRow(array $row): self
    {
        $token = new self();
        $token->id = isset($row['id']) ? (int)$row['id'] : null;
        $token->description = (string)($row['description'] ?? '');
        $token->hash = (string)($row['token_hash'] ?? '');
        $token->domainRestriction = ($row['domain_restriction'] ?? '') !== '' ? (string)$row['domain_restriction'] : null;
        $token->groupDomain = ($row['group_domain'] ?? '') !== '' ? (string)$row['group_domain'] : null;
        $token->allowFrom = array_values(array_filter(array_map('trim', explode(',', (string)($row['allow_from'] ?? ''))), 'strlen'));
        $token->defaultTemplate = ($row['default_template'] ?? '') !== '' ? (string)$row['default_template'] : null;
        $token->mappers = json_decode((string)($row['mappers'] ?? ''), true) ?: [];
        $token->templates = json_decode((string)($row['templates'] ?? ''), true) ?: [];
        $token->allowRename = (int)($row['allow_rename'] ?? 0);
        $token->renameAlias = (int)($row['rename_alias'] ?? 1);
        $token->active = (int)($row['active'] ?? 1);
        $token->created = isset($row['created']) ? (string)$row['created'] : null;
        $token->modified = isset($row['modified']) ? (string)$row['modified'] : null;
        return $token;
    }

    /** API representation (GET scim-token), without the hash */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'domain_restriction' => $this->domainRestriction,
            'group_domain' => $this->groupDomain,
            'allow_from' => $this->allowFrom,
            'default_template' => $this->defaultTemplate,
            'mappers' => $this->mappers,
            'templates' => $this->templates,
            'allow_rename' => $this->allowRename,
            'rename_alias' => $this->renameAlias,
            'active' => $this->active,
            'created' => $this->created,
            'modified' => $this->modified,
        ];
    }

    /** The token sees resources of this domain */
    public function covers(string $domain): bool
    {
        return $this->domainRestriction === null || strcasecmp($this->domainRestriction, $domain) === 0;
    }

    /** Domain of the groups this token creates */
    public function groupDomain(): ?string
    {
        return $this->domainRestriction ?? $this->groupDomain;
    }

    /** Mapped template of a user: the mapping of the extension attribute `template`, else the default template */
    public function templateFor(?string $attribute): ?string
    {
        $i = ($attribute === null) ? false : array_search($attribute, $this->mappers, true);
        return ($i !== false && ($this->templates[$i] ?? '') !== '') ? $this->templates[$i] : $this->defaultTemplate;
    }
}
