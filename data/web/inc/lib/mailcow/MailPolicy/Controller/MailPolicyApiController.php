<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy\Controller;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use Mailcow\MailPolicy\Service\PolicyEngine;
use Mailcow\MailPolicy\Service\PolicyService;
use PDOException;

/**
 * HTTP API adapter for json_api.php and edit.php (routes `mail-policy`), and the answer to the Postfix policy
 * service (data/conf/rspamd/dynmaps/mailpolicy.php). Messages are logged as ['mail_policy', action, 'policy', data].
 */
class MailPolicyApiController
{
    public function __construct(private PolicyService $policies, private PolicyEngine $engine, private ApiResponse $response)
    {
    }

    /** POST add/mail-policy */
    public function addPolicy(array $attr): bool
    {
        return $this->handle('add', $attr, function (array $log) use ($attr): bool {
            $policy = $this->policies->create($attr);
            return $this->response->success(['mail_policy_added', htmlspecialchars($policy->name)], $log);
        });
    }

    /** POST edit/mail-policy: one message per id */
    public function editPolicies(?array $items, array $attr): bool
    {
        $data = array_merge(['id' => $items], $attr);
        return $this->handle('edit', $data, function (array $log) use ($data, $attr): bool {
            $this->policies->requireEditor();
            return $this->batch((array)($data['id'] ?? []), $log,
                fn(int $id): array => ['mail_policy_modified', htmlspecialchars($this->policies->update($id, $attr)->name)]);
        });
    }

    /** POST delete/mail-policy: one message per id */
    public function deletePolicies(array $items): bool
    {
        return $this->handle('delete', ['id' => $items], function (array $log) use ($items): bool {
            $this->policies->requireEditor();
            return $this->batch($items, $log, fn(int $id): array => ['mail_policy_removed', htmlspecialchars($this->policies->delete($id)->name)]);
        });
    }

    /** GET mail-policy/all[/<domain,domain>] */
    public function getPolicies(?string $domains = null): array
    {
        $list = array_values(array_filter(array_map('trim', explode(',', (string)$domains)), 'strlen'));
        return array_values(array_filter(array_map(fn(int $id): array => $this->getPolicy($id), $this->policies->visibleIds($list))));
    }

    /** GET mail-policy/<id>, also edit.php */
    public function getPolicy(mixed $id): array
    {
        $id = $this->toId($id);
        $policy = ($id === null) ? null : $this->policies->get($id);
        return ($policy === null) ? [] : $this->policies->toArray($policy);
    }

    /** GET mail-policy/test/<sender>/<recipient>[/login]: would the mail be accepted? Sender "-" = null sender; [] when not allowed to ask */
    public function test(string $sender, string $recipient, bool $loggedIn): array
    {
        try {
            return $this->policies->test(($sender === '-') ? '' : $sender, $recipient, $loggedIn);
        } catch (UserError) {
            return [];
        }
    }

    /** Answer for the Postfix policy service: DUNNO or the rejection */
    public function check(string $sender, string $recipient, string $login, int $port): string
    {
        return $this->engine->check($sender, $recipient, $login, $port)->action();
    }

    /**
     * Runs one write call: $work gets the log entry and returns the result for process_*_return().
     * A UserError becomes a danger message, a database error stops the call with one `mysql_error` message.
     */
    private function handle(string $action, mixed $data, callable $work): bool
    {
        $log = ['mail_policy', $action, 'policy', $data];
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
