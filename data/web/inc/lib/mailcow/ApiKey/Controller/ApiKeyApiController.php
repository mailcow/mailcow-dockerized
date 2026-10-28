<?php

declare(strict_types=1);

namespace Mailcow\ApiKey\Controller;

use Mailcow\ApiKey\Model\ApiKey;
use Mailcow\ApiKey\Service\ApiKeyService;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use PDOException;

/**
 * HTTP API adapter for json_api.php and edit.php (routes `api-key`).
 *
 * Write methods return what json_api passes to process_*_return() and write their messages via ApiResponse.
 * Messages are logged as ['api', action, 'key', data].
 */
class ApiKeyApiController
{
    public function __construct(private ApiKeyService $keys, private ApiResponse $response)
    {
    }

    /** POST add/api-key */
    public function addKey(array $attr): bool
    {
        return $this->handle('add', $attr,
            fn(array $log): bool => $this->response->success(['api_key_added', self::label($this->keys->create($attr))], $log));
    }

    /** POST edit/api-key: one message per id */
    public function editKeys(?array $items, array $attr): bool
    {
        $data = array_merge(['id' => $items], $attr);
        return $this->handle('edit', $data, function (array $log) use ($data, $attr): bool {
            $this->keys->requireAdmin();
            return $this->batch((array)($data['id'] ?? []), $log,
                fn(int $id): array => ['api_key_modified', self::label($this->keys->update($id, $attr))]);
        });
    }

    /** POST delete/api-key: one message per id */
    public function deleteKeys(array $items): bool
    {
        return $this->handle('delete', ['id' => $items], function (array $log) use ($items): bool {
            $this->keys->requireAdmin();
            return $this->batch($items, $log, fn(int $id): array => ['api_key_deleted', self::label($this->keys->delete($id))]);
        });
    }

    /** GET api-key/all */
    public function getKeys(): array
    {
        return array_map(fn(ApiKey $key): array => $key->toArray(), $this->keys->all());
    }

    /** GET api-key/<id>, also edit.php */
    public function getKey(mixed $id): array
    {
        $id = $this->toId($id);
        $key = ($id === null) ? null : $this->keys->get($id);
        return ($key === null) ? [] : $key->toArray();
    }

    /** Name of a key in messages: its description, else its id */
    private static function label(ApiKey $key): string
    {
        return htmlspecialchars($key->description !== '' ? $key->description : (string)$key->id);
    }

    /**
     * Runs one write call: $work gets the log entry and returns the result for process_*_return().
     * A UserError becomes a danger message, a database error stops the call with one `mysql_error` message.
     */
    private function handle(string $action, mixed $data, callable $work): bool
    {
        $log = ['api', $action, 'key', $data];
        try {
            $result = $work($log);
        } catch (UserError $e) {
            $result = $this->response->error($e, $log);
        } catch (PDOException $e) {
            $result = $this->response->danger(['mysql_error', $e->getMessage()], $log);
        }
        $this->response->flush();
        return $result;
    }

    /** One message per id: the success message returned by $work(id), or the UserError */
    private function batch(array $ids, array $log, callable $work): bool
    {
        foreach ($ids as $id) {
            try {
                $this->response->success($work($this->requireId($id)), $log);
            } catch (UserError $e) {
                $this->response->error($e, $log);
            }
        }
        return true;
    }

    /** Integer id from request input ("12", 12); null for anything else */
    private function toId(mixed $id): ?int
    {
        return (is_numeric($id) && (float)$id == (int)(float)$id) ? (int)(float)$id : null;
    }

    /** @throws AccessDenied for ids that cannot exist */
    private function requireId(mixed $id): int
    {
        return $this->toId($id) ?? throw new AccessDenied();
    }
}
