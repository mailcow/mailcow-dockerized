<?php

declare(strict_types=1);

namespace Mailcow\Identity\Controller;

use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use Mailcow\Identity\Service\StatusService;
use PDOException;

/**
 * HTTP API adapter for json_api.php (routes `identity-provider/sync-status`, `identity-provider-sync`,
 * `identity-link`). Messages are logged as ['identity_provider', action, object, data].
 */
class IdentityApiController
{
    public function __construct(private StatusService $status, private ApiResponse $response)
    {
    }

    /** GET identity-provider/sync-status */
    public function getStatus(): array
    {
        try {
            return $this->status->status();
        } catch (UserError) {
            return [];
        }
    }

    /** POST edit/identity-provider-sync: attr mode = preview|run|force */
    public function requestRun(array $attr): bool
    {
        return $this->handle('edit', 'sync', $attr, function (array $log) use ($attr): bool {
            $this->status->requestRun($attr['mode'] ?? 'run');
            return $this->response->success('iam_sync_requested', $log);
        });
    }

    /** GET identity-link/<username> */
    public function getLink(string $username): array
    {
        return $this->status->link($username) ?? [];
    }

    /** POST delete/identity-link: usernames */
    public function unlink(array $items): bool
    {
        return $this->handle('delete', 'link', $items, function (array $log) use ($items): bool {
            foreach ($items as $username) {
                $this->status->unlink((string)$username);
                $this->response->success(['iam_link_removed', htmlspecialchars((string)$username)], $log);
            }
            return true;
        });
    }

    private function handle(string $action, string $object, mixed $data, callable $work): bool
    {
        $log = ['identity_provider', $action, $object, $data];
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
}
