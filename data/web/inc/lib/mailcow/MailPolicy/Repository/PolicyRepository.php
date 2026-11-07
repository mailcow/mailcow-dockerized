<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy\Repository;

use Mailcow\Core\Context;
use Mailcow\Core\Repository;
use Mailcow\MailPolicy\Model\Policy;
use PDO;

/**
 * Tables `mail_policy` and `mail_policy_member` (the mailboxes and groups a policy is assigned to), plus the
 * lookups the editing needs. Assignments go with their mailbox, group or domain (foreign keys, ON DELETE CASCADE).
 */
class PolicyRepository extends Repository
{
    public function find(int $id): ?Policy
    {
        $row = $this->query("SELECT * FROM `mail_policy` WHERE `id` = :id", ['id' => $id])->fetch();
        return is_array($row) ? $this->withMembers(Policy::fromRow($row)) : null;
    }

    /**
     * @param string[] $domains limit to these domains, [] = all visible
     * @return int[] ids of the policies visible to the caller, ordered by domain and name
     */
    public function visibleIds(Context $context, array $domains = []): array
    {
        if ($context->hasAdminRights()) {
            [$visible, $params] = ['1=1', []];
        } elseif ($context->isDomainAdmin()) {
            [$visible, $params] = ["`domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :vis_da)",
                ['vis_da' => $context->username]];
        } else {
            return [];
        }
        $sql = "SELECT `id` FROM `mail_policy` WHERE $visible";
        if (!empty($domains)) {
            $names = [];
            foreach (array_values($domains) as $i => $domain) {
                $names[] = ':dom' . $i;
                $params['dom' . $i] = $domain;
            }
            $sql .= " AND `domain` IN (" . implode(', ', $names) . ")";
        }
        return array_map('intval', $this->query($sql . " ORDER BY `domain`, `name`", $params)->fetchAll(PDO::FETCH_COLUMN));
    }

    public function nameExists(string $domain, string $name, ?int $exceptId = null): bool
    {
        return (bool)$this->query("SELECT 1 FROM `mail_policy` WHERE `domain` = :domain AND `name` = :name AND `id` != :id",
            ['domain' => $domain, 'name' => $name, 'id' => $exceptId ?? 0])->fetchColumn();
    }

    /** Inserts or updates the policy with its assignments; returns its id */
    public function save(Policy $policy): int
    {
        return $this->transaction(function () use ($policy): int {
            if ($policy->id === null) {
                $policy->id = $this->insertRow('mail_policy', $policy->toRow());
            } else {
                $this->updateRow('mail_policy', $policy->id, $policy->toRow());
                $this->query("DELETE FROM `mail_policy_member` WHERE `policy_id` = :id", ['id' => $policy->id]);
            }
            foreach ($policy->mailboxes as $username) {
                $this->insertRow('mail_policy_member', ['policy_id' => $policy->id, 'username' => $username]);
            }
            foreach ($policy->groups as $groupId) {
                $this->insertRow('mail_policy_member', ['policy_id' => $policy->id, 'group_id' => $groupId]);
            }
            return $policy->id;
        });
    }

    public function delete(int $id): void
    {
        $this->query("DELETE FROM `mail_policy` WHERE `id` = :id", ['id' => $id]);
    }

    /** A primary domain (policies never belong to alias domains) */
    public function isDomain(string $domain): bool
    {
        return (bool)$this->query("SELECT 1 FROM `domain` WHERE `domain` = :domain", ['domain' => $domain])->fetchColumn();
    }

    /** Domain of a regular mailbox, null if there is none */
    public function mailboxDomain(string $username): ?string
    {
        $domain = $this->query("SELECT `domain` FROM `mailbox` WHERE `username` = :username AND `kind` = ''", ['username' => $username])->fetchColumn();
        return is_string($domain) ? $domain : null;
    }

    public function groupDomain(int $id): ?string
    {
        $domain = $this->query("SELECT `domain` FROM `mailbox_group` WHERE `id` = :id", ['id' => $id])->fetchColumn();
        return is_string($domain) ? $domain : null;
    }

    /** @return array[] id, name, domain, address of the groups in these domains (choices of the forms) */
    public function groupsIn(array $domains): array
    {
        if (empty($domains)) {
            return [];
        }
        $names = [];
        $params = [];
        foreach (array_values($domains) as $i => $domain) {
            $names[] = ':dom' . $i;
            $params['dom' . $i] = $domain;
        }
        return $this->query("SELECT `id`, `name`, `domain`, `address` FROM `mailbox_group` WHERE `domain` IN (" . implode(', ', $names) . ")
          ORDER BY `domain`, `name`", $params)->fetchAll();
    }

    private function withMembers(Policy $policy): Policy
    {
        foreach ($this->query("SELECT `username`, `group_id` FROM `mail_policy_member` WHERE `policy_id` = :id ORDER BY `username`, `group_id`",
            ['id' => $policy->id])->fetchAll() as $row) {
            if ($row['username'] !== null) {
                $policy->mailboxes[] = (string)$row['username'];
            } elseif ($row['group_id'] !== null) {
                $policy->groups[] = (int)$row['group_id'];
            }
        }
        return $policy;
    }
}
