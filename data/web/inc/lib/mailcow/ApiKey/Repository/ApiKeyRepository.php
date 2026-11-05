<?php

declare(strict_types=1);

namespace Mailcow\ApiKey\Repository;

use Mailcow\ApiKey\Model\ApiKey;
use Mailcow\Core\Repository;
use PDO;

/**
 * SQL for the API keys (table `api`).
 */
class ApiKeyRepository extends Repository
{
    /** @return ApiKey[] service keys first, then by id */
    public function all(): array
    {
        return array_map([ApiKey::class, 'fromRow'],
            $this->query("SELECT * FROM `api` ORDER BY `service` DESC, `id`")->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?ApiKey
    {
        $row = $this->query("SELECT * FROM `api` WHERE `id` = :id", ['id' => $id])->fetch(PDO::FETCH_ASSOC);
        return $row ? ApiKey::fromRow($row) : null;
    }

    /** Inserts the key and returns the new id */
    public function insert(ApiKey $key): int
    {
        $key->id = $this->insertRow('api', $this->values($key) + ['service' => $key->service]);
        return $key->id;
    }

    public function update(ApiKey $key): void
    {
        $this->updateRow('api', (int)$key->id, $this->values($key));
    }

    public function delete(int $id): void
    {
        $this->query("DELETE FROM `api` WHERE `id` = :id", ['id' => $id]);
    }

    /**
     * Makes the service keys match $keys in one transaction: other service keys are deleted,
     * an existing row with the same key (also a UI key) becomes the service key.
     *
     * @param ApiKey[] $keys
     */
    public function syncService(array $keys): void
    {
        $this->transaction(function () use ($keys): void {
            $params = [];
            foreach (array_values($keys) as $i => $key) {
                $params['k' . $i] = $key->key;
            }
            $keep = empty($params) ? '' : " AND `api_key` NOT IN (:" . implode(', :', array_keys($params)) . ")";
            $this->query("DELETE FROM `api` WHERE `service` = '1'" . $keep, $params);
            // No INSERT ... ON DUPLICATE KEY UPDATE: it uses up an id on every php-fpm start
            foreach ($keys as $key) {
                $id = $this->query("SELECT `id` FROM `api` WHERE `api_key` = :api_key", ['api_key' => $key->key])->fetchColumn();
                if ($id === false) {
                    $this->insertRow('api', $this->values($key) + ['service' => 1]);
                } else {
                    $this->updateRow('api', (int)$id, $this->values($key) + ['service' => 1]);
                }
            }
        });
    }

    private function values(ApiKey $key): array
    {
        return [
            'api_key' => $key->key,
            'description' => $key->description,
            'allow_from' => implode(',', $key->allowFrom),
            'skip_ip_check' => $key->skipIpCheck,
            'access' => $key->access,
            'active' => $key->active,
        ];
    }
}
