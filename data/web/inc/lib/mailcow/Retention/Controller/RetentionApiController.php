<?php

declare(strict_types=1);

namespace Mailcow\Retention\Controller;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use Mailcow\Retention\Service\Planner;
use Mailcow\Retention\Service\RuleService;
use PDOException;

/**
 * HTTP API adapter for json_api.php, edit.php and user.php (routes `retention-rule`, `logs/retention`), and the
 * plan and results of the nightly run (data/conf/rspamd/dynmaps/retention.php). Messages are logged as
 * ['retention', action, 'rule', data].
 */
class RetentionApiController
{
    public function __construct(private RuleService $rules, private ApiResponse $response)
    {
    }

    /** POST add/retention-rule */
    public function addRule(array $attr): bool
    {
        return $this->handle('add', $attr, function (array $log) use ($attr): bool {
            $rule = $this->rules->create($attr);
            return $this->response->success(['retention_rule_added', htmlspecialchars($rule->name)], $log);
        });
    }

    /** POST edit/retention-rule: one message per id */
    public function editRules(?array $items, array $attr): bool
    {
        $data = array_merge(['id' => $items], $attr);
        return $this->handle('edit', $data, function (array $log) use ($data, $attr): bool {
            $this->rules->requireEditor();
            return $this->batch((array)($data['id'] ?? []), $log,
                fn(int $id): array => ['retention_rule_modified', htmlspecialchars($this->rules->update($id, $attr)->name)]);
        });
    }

    /** POST delete/retention-rule: one message per id */
    public function deleteRules(array $items): bool
    {
        return $this->handle('delete', ['id' => $items], function (array $log) use ($items): bool {
            $this->rules->requireEditor();
            return $this->batch($items, $log, fn(int $id): array => ['retention_rule_removed', htmlspecialchars($this->rules->delete($id)->name)]);
        });
    }

    /** GET retention-rule/all[/<domain,domain>]: with the summary of the last nightly run */
    public function getRules(?string $domains = null): array
    {
        $ids = $this->rules->visibleIds(self::domains($domains));
        $runs = $this->rules->lastRuns($ids);
        $rules = [];
        foreach ($ids as $id) {
            $rule = $this->rules->get($id);
            if ($rule !== null) {
                $rules[] = $this->rules->toArray($rule, $runs[$id] ?? null);
            }
        }
        return $rules;
    }

    /** GET retention-rule/<id>, also edit.php */
    public function getRule(mixed $id): array
    {
        $id = $this->toId($id);
        $rule = ($id === null) ? null : $this->rules->get($id);
        return ($rule === null) ? [] : $this->rules->toArray($rule, $this->rules->lastRuns([$id])[$id] ?? null);
    }

    /**
     * GET retention-rule/effective/<username>: what the rules delete in the mailbox; [] when not allowed to ask.
     * $describe: for people (user.php), see Planner::describe()
     */
    public function getEffective(mixed $username, bool $describe = false): array
    {
        try {
            $actions = $this->rules->effective($username);
            return $describe ? Planner::describe($actions) : $actions;
        } catch (UserError) {
            return [];
        }
    }

    /** GET logs/retention[/<count>]: mailboxes with found mail or errors in the nightly runs, newest first */
    public function getLog(int $limit = 0): array
    {
        return $this->rules->log($limit);
    }

    /** A page of the plan of the nightly run (retention.php): mailboxes with their actions, next: after for the next page */
    public function plan(string $after = '', int $limit = 0): array
    {
        $page = $this->rules->plan($after, $limit);
        $plan = [];
        foreach ($page['mailboxes'] as $username => $actions) {
            $plan[] = ['username' => (string)$username, 'actions' => $actions];
        }
        return ['mailboxes' => $plan, 'next' => $page['next']];
    }

    /** Results of the nightly run (retention.php): mailboxes with found mail or errors, a summary per rule, the run */
    public function report(array $results, array $summary, ?array $run = null): int
    {
        return $this->rules->report($results, $summary, $run);
    }

    /** The domain was deleted (functions.mailbox.inc.php) */
    public function domainDeleted(string $domain): void
    {
        $this->rules->domainDeleted($domain);
    }

    /**
     * Runs one write call: $work gets the log entry and returns the result for process_*_return().
     * A UserError becomes a danger message, a database error stops the call with one `mysql_error` message.
     */
    private function handle(string $action, mixed $data, callable $work): bool
    {
        $log = ['retention', $action, 'rule', $data];
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

    /** @return string[] domains of a comma separated filter */
    private static function domains(?string $domains): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string)$domains)), 'strlen'));
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
