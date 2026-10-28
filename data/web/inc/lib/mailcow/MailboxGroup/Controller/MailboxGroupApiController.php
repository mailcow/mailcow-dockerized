<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Controller;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use Mailcow\MailboxGroup\Model\Group;
use Mailcow\MailboxGroup\Service\AccountHooks;
use Mailcow\MailboxGroup\Service\GroupService;
use Mailcow\MailboxGroup\Service\TemplateService;
use PDOException;

/**
 * HTTP API adapter for json_api.php and edit.php (routes `mailbox-group`), plus the hook adapters the legacy
 * code calls outside a transaction (a database error becomes a `mysql_error` message).
 * Messages are logged as ['mailbox_group', action, 'group', data].
 */
class MailboxGroupApiController
{
    public function __construct(private GroupService $groups, private AccountHooks $hooks, private TemplateService $templates,
        private ApiResponse $response)
    {
    }

    /** POST add/mailbox-group */
    public function addGroup(array $attr): bool
    {
        return $this->handle('add', $attr, function (array $log) use ($attr): bool {
            $group = $this->groups->create($attr);
            $this->warnings($group->warnings, $log);
            return $this->response->success(['group_added', htmlspecialchars($group->name)], $log);
        });
    }

    /** POST edit/mailbox-group: one message per id */
    public function editGroups(?array $items, array $attr): bool
    {
        $data = array_merge(['id' => $items], $attr);
        return $this->handle('edit', $data, function (array $log) use ($data, $attr): bool {
            $this->groups->requireEditor();
            return $this->batch((array)($data['id'] ?? []), $log, function (int $id) use ($attr, $log): array {
                $group = $this->groups->update($id, $attr);
                $this->warnings($group->warnings, $log);
                return ['group_modified', htmlspecialchars($group->name)];
            });
        });
    }

    /** POST delete/mailbox-group: one message per id */
    public function deleteGroups(array $items): bool
    {
        return $this->handle('delete', ['id' => $items], function (array $log) use ($items): bool {
            $this->groups->requireEditor();
            return $this->batch($items, $log, function (int $id) use ($log): array {
                $group = $this->groups->delete($id);
                $this->warnings($group->warnings, $log);
                return ['group_removed', htmlspecialchars($group->name)];
            });
        });
    }

    /** GET mailbox-group/all[/<domain,domain>] */
    public function getGroups(?string $domains = null): array
    {
        $list = array_values(array_filter(array_map('trim', explode(',', (string)$domains)), 'strlen'));
        return array_values(array_filter(array_map(fn(int $id): array => $this->getGroup($id), $this->groups->visibleIds($list))));
    }

    /** GET mailbox-group/<id>, also edit.php */
    public function getGroup(mixed $id): array
    {
        $id = $this->toId($id);
        $group = ($id === null) ? null : $this->groups->get($id);
        return ($group === null) ? [] : $this->groups->toArray($group);
    }

    /** GET mailbox-group/member/<username>: groups of a mailbox (mailbox edit page, user page) */
    public function getMemberships(string $username): array
    {
        return $this->groups->membershipsOf($username);
    }

    public function mailboxDeleted(string $username): void
    {
        $this->hook('mailbox_deleted', $username, fn() => $this->hooks->onMailboxDeleted($username));
    }

    public function domainDeleted(string $domain): void
    {
        $this->hook('domain_deleted', $domain, fn() => $this->hooks->onDomainDeleted($domain));
    }

    public function addressDeleted(string $address): void
    {
        $this->hook('address_deleted', $address, fn() => $this->hooks->onAddressDeleted($address));
    }

    public function addressRenamed(string $old, string $new): void
    {
        $this->hook('address_renamed', [$old, $new], fn() => $this->hooks->onAddressRenamed($old, $new));
    }

    /** A mailbox template was edited: apply it where it is the effective group template */
    public function templateChanged(int $templateId): void
    {
        $this->hook('template_changed', $templateId, function () use ($templateId): void {
            $this->warnings($this->templates->onTemplateChanged($templateId), ['mailbox_group', 'edit', 'template', $templateId]);
        });
    }

    private function warnings(array $warnings, array $log): void
    {
        foreach ($warnings as $msg) {
            $this->response->warning($msg, $log);
        }
    }

    /**
     * Runs one write call: $work gets the log entry and returns the result for process_*_return().
     * A UserError becomes a danger message, a database error stops the call with one `mysql_error` message.
     */
    private function handle(string $action, mixed $data, callable $work): bool
    {
        $log = ['mailbox_group', $action, 'group', $data];
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

    private function hook(string $action, mixed $data, callable $work): void
    {
        try {
            $work();
        } catch (PDOException $e) {
            $this->response->danger(['mysql_error', $e->getMessage()], ['mailbox_group', $action, 'hook', $data]);
        }
        $this->response->flush();
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
