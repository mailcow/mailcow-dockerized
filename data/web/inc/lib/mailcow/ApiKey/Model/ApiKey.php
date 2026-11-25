<?php

declare(strict_types=1);

namespace Mailcow\ApiKey\Model;

/**
 * An API key (table `api`), sent by clients in the X-API-Key header (see sessions.inc.php).
 *
 * Unlike other secrets the key is part of toArray(): admins copy it from the UI. ApiKeyService limits
 * read access to admins (not with a read-only API key).
 */
class ApiKey
{
    public const ACCESS_RO = 'ro';
    public const ACCESS_RW = 'rw';

    public ?int $id = null;
    public string $key = '';
    public string $description = '';
    public string $access = self::ACCESS_RO;
    /** @var string[] IPs and CIDR networks the key may be used from */
    public array $allowFrom = [];
    public int $skipIpCheck = 0;
    /** 1 = service key from mailcow.conf: shown, but neither editable nor deletable in UI and API */
    public int $service = 0;
    public int $active = 1;
    public ?string $created = null;
    public ?string $modified = null;

    /** Builds a key from an `api` row (column names as in the table) */
    public static function fromRow(array $row): self
    {
        $key = new self();
        $key->id = isset($row['id']) ? (int)$row['id'] : null;
        $key->key = (string)($row['api_key'] ?? '');
        $key->description = (string)($row['description'] ?? '');
        $key->access = (string)($row['access'] ?? self::ACCESS_RO);
        $key->allowFrom = array_values(array_filter(array_map('trim', explode(',', (string)($row['allow_from'] ?? ''))), 'strlen'));
        $key->skipIpCheck = (int)($row['skip_ip_check'] ?? 0);
        $key->service = (int)($row['service'] ?? 0);
        $key->active = (int)($row['active'] ?? 1);
        $key->created = isset($row['created']) ? (string)$row['created'] : null;
        $key->modified = isset($row['modified']) ? (string)$row['modified'] : null;
        return $key;
    }

    /** API representation (GET api-key) */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'access' => $this->access,
            'api_key' => $this->key,
            'allow_from' => $this->allowFrom,
            'skip_ip_check' => $this->skipIpCheck,
            'service' => $this->service,
            'active' => $this->active,
            'created' => $this->created,
            'modified' => $this->modified,
        ];
    }
}
