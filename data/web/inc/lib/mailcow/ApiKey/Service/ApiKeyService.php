<?php

declare(strict_types=1);

namespace Mailcow\ApiKey\Service;

use Mailcow\ApiKey\Model\ApiKey;
use Mailcow\ApiKey\Repository\ApiKeyRepository;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;

/**
 * Use cases and rules for API keys.
 *
 * Admins only. A read-only API key may not even read the keys, since they include read-write keys.
 * Service keys are defined in mailcow.conf (API_KEY, API_KEY_READ_ONLY, API_ALLOW_FROM) and mirrored
 * into the table on every php-fpm start (syncServiceKeys()). UI and API show them, but cannot change them.
 */
class ApiKeyService
{
    /** mailcow.conf variable => access of the service key it defines */
    public const SERVICE_KEYS = ['API_KEY' => ApiKey::ACCESS_RW, 'API_KEY_READ_ONLY' => ApiKey::ACCESS_RO];

    public function __construct(private ApiKeyRepository $repo, private Context $context)
    {
    }

    /** @throws AccessDenied unless an admin (login or read-write API key) */
    public function requireAdmin(): void
    {
        $this->context->requireAdmin();
    }

    /** @return ApiKey[] all keys, service keys first; [] for other callers */
    public function all(): array
    {
        return $this->canRead() ? $this->repo->all() : [];
    }

    public function get(int $id): ?ApiKey
    {
        return $this->canRead() ? $this->repo->find($id) : null;
    }

    /**
     * Creates a key with a random value; `access` is required.
     *
     * @throws UserError
     */
    public function create(array $input): ApiKey
    {
        $this->requireAdmin();
        $key = new ApiKey();
        $key->key = self::generate();
        $this->apply($key, $input);
        $this->repo->insert($key);
        return $key;
    }

    /**
     * Updates a key; attributes missing in $input keep their value, `regen_key` issues a new key value.
     *
     * @throws UserError access_denied, api_key_service_locked, validation errors
     */
    public function update(int $id, array $input): ApiKey
    {
        $this->requireAdmin();
        $key = $this->editable($id);
        $this->apply($key, $input);
        if (self::flag($input['regen_key'] ?? 0) === 1) {
            $key->key = self::generate();
        }
        $this->repo->update($key);
        return $key;
    }

    /**
     * Deletes a key and returns it.
     *
     * @throws UserError access_denied, api_key_service_locked
     */
    public function delete(int $id): ApiKey
    {
        $this->requireAdmin();
        $key = $this->editable($id);
        $this->repo->delete($id);
        return $key;
    }

    /**
     * Mirrors the service keys of mailcow.conf into the table. Runs on php-fpm start (init_db CLI, master only),
     * so there is no permission check. $env holds the container environment (getenv()).
     * Without a valid API_ALLOW_FROM no service key is active, like before. Service keys that were removed from
     * mailcow.conf are deleted; a UI key with the same value becomes the service key.
     *
     * @return string[] warnings for the container log
     */
    public function syncServiceKeys(array $env): array
    {
        $warnings = [];
        $allowFrom = [];
        foreach (self::split($env['API_ALLOW_FROM'] ?? '') as $network) {
            // 'invalid' is the docker-compose default for an unset variable
            if ($network === 'invalid') {
                continue;
            }
            if (self::isNetwork($network)) {
                $allowFrom[] = $network;
            } else {
                $warnings[] = 'API_ALLOW_FROM: skipped invalid IP/network ' . $network;
            }
        }
        $keys = [];
        foreach (self::SERVICE_KEYS as $var => $access) {
            $value = trim((string)($env[$var] ?? ''));
            if ($value === '' || $value === 'invalid') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $value)) {
                $warnings[] = $var . ': invalid characters (allowed: a-z, A-Z, 0-9, -), key ignored';
                continue;
            }
            if (empty($allowFrom)) {
                $warnings[] = $var . ': API_ALLOW_FROM contains no valid IP/network, key ignored';
                continue;
            }
            if (isset($keys[$value])) {
                $warnings[] = $var . ': same value as ' . $keys[$value]->description . ', key ignored';
                continue;
            }
            $key = new ApiKey();
            $key->key = $value;
            $key->description = $var;
            $key->access = $access;
            $key->allowFrom = $allowFrom;
            $key->service = 1;
            $keys[$value] = $key;
        }
        $this->repo->syncService(array_values($keys));
        return $warnings;
    }

    private function canRead(): bool
    {
        return $this->context->hasAdminRights() && !$this->context->isReadOnlyApi();
    }

    /** @throws AccessDenied when missing, UserError api_key_service_locked for service keys */
    private function editable(int $id): ApiKey
    {
        $key = $this->repo->find($id) ?? throw new AccessDenied();
        if ($key->service === 1) {
            throw new UserError('api_key_service_locked', [$key->description]);
        }
        return $key;
    }

    /**
     * Add/edit rules: attributes missing in $input keep the current value, a new key starts with the
     * model defaults. Without skip_ip_check the key needs at least one IP/network.
     *
     * @throws UserError
     */
    private function apply(ApiKey $key, array $input): void
    {
        if (isset($input['description'])) {
            $description = Input::text($input['description']);
            if ($description === null || mb_strlen($description) > 255) {
                throw new UserError('api_key_description_invalid');
            }
            $key->description = $description;
        }
        if (isset($input['access']) || $key->id === null) {
            $access = $input['access'] ?? null;
            if (!in_array($access, [ApiKey::ACCESS_RO, ApiKey::ACCESS_RW], true)) {
                throw new UserError('api_key_access_invalid');
            }
            $key->access = $access;
        }
        if (isset($input['allow_from'])) {
            $key->allowFrom = self::networks($input['allow_from']);
        }
        if (isset($input['skip_ip_check'])) {
            $key->skipIpCheck = self::flag($input['skip_ip_check']);
        }
        if (isset($input['active'])) {
            $key->active = self::flag($input['active']);
        }
        if ($key->skipIpCheck === 0 && empty($key->allowFrom)) {
            throw new UserError('ip_list_empty');
        }
    }

    /** New random key in the format of the legacy UI: five groups of six hex digits */
    private static function generate(): string
    {
        return implode('-', array_map(fn(): string => strtoupper(bin2hex(random_bytes(3))), range(1, 5)));
    }

    /** Checkbox or API flag; a hidden "0" + checkbox pair arrives as ["0", "1"] when checked */
    private static function flag(mixed $value): int
    {
        if (is_array($value)) {
            $value = end($value);
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    /**
     * @return string[] validated IPs/networks
     * @throws UserError api_key_network_invalid for the first invalid entry
     */
    private static function networks(mixed $value): array
    {
        $networks = self::split($value);
        foreach ($networks as $network) {
            if (!self::isNetwork($network)) {
                throw new UserError('api_key_network_invalid', [htmlspecialchars($network)]);
            }
        }
        return $networks;
    }

    /** @return string[] unique entries of a list: array or string separated by spaces, commas, semicolons or newlines */
    private static function split(mixed $value): array
    {
        $values = is_array($value) ? $value : preg_split('/[\s,;]+/', is_scalar($value) ? (string)$value : '');
        $values = array_map(fn(mixed $v): string => is_scalar($v) ? trim((string)$v) : '', $values);
        return array_values(array_unique(array_filter($values, 'strlen')));
    }

    /** IPv4/IPv6 address or CIDR network */
    private static function isNetwork(string $value): bool
    {
        [$ip, $prefix] = array_pad(explode('/', $value, 2), 2, null);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($prefix === null) {
            return true;
        }
        $max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
        return ctype_digit($prefix) && (int)$prefix <= $max;
    }
}
