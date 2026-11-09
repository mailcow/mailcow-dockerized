<?php

declare(strict_types=1);

namespace Mailcow\Retention\Repository;

use Mailcow\Core\Context;
use Mailcow\Core\Repository;
use Mailcow\Retention\Model\Rule;
use Mailcow\Retention\Service\Planner;
use PDO;

/**
 * Tables `retention_rule`, `retention_rule_member` (the mailboxes and groups a rule is assigned to) and
 * `retention_log` (results of the nightly run), plus the lookups the editing needs. Assignments go with their
 * mailbox, group or domain (foreign keys, ON DELETE CASCADE).
 */
class RuleRepository extends Repository
{
    /** Results of the nightly run are kept this long */
    private const LOG_DAYS = 90;

    public function find(int $id): ?Rule
    {
        $row = $this->query("SELECT * FROM `retention_rule` WHERE `id` = :id", ['id' => $id])->fetch();
        return is_array($row) ? $this->withMembers(Rule::fromRow($row)) : null;
    }

    /**
     * @param string[] $domains limit to these domains, [] = all visible
     * @return int[] ids of the rules visible to the caller, ordered by domain and name
     */
    public function visibleIds(Context $context, array $domains = []): array
    {
        [$where, $params] = $this->visibleDomains($context, $domains);
        if ($where === null) {
            return [];
        }
        return array_map('intval', $this->query("SELECT `id` FROM `retention_rule` WHERE $where ORDER BY `domain`, `name`", $params)
            ->fetchAll(PDO::FETCH_COLUMN));
    }

    public function nameExists(string $domain, string $name, ?int $exceptId = null): bool
    {
        return (bool)$this->query("SELECT 1 FROM `retention_rule` WHERE `domain` = :domain AND `name` = :name AND `id` != :id",
            ['domain' => $domain, 'name' => $name, 'id' => $exceptId ?? 0])->fetchColumn();
    }

    /** Inserts or updates the rule with its assignments; returns its id */
    public function save(Rule $rule): int
    {
        return $this->transaction(function () use ($rule): int {
            if ($rule->id === null) {
                $rule->id = $this->insertRow('retention_rule', $rule->toRow());
            } else {
                $this->updateRow('retention_rule', $rule->id, $rule->toRow());
                $this->query("DELETE FROM `retention_rule_member` WHERE `rule_id` = :id", ['id' => $rule->id]);
            }
            foreach ($rule->mailboxes as $username) {
                $this->insertRow('retention_rule_member', ['rule_id' => $rule->id, 'username' => $username]);
            }
            foreach ($rule->groups as $groupId) {
                $this->insertRow('retention_rule_member', ['rule_id' => $rule->id, 'group_id' => $groupId]);
            }
            return $rule->id;
        });
    }

    public function delete(int $id): void
    {
        $this->query("DELETE FROM `retention_rule` WHERE `id` = :id", ['id' => $id]);
    }

    /** A primary domain (rules never belong to alias domains) */
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
        [$in, $params] = self::in('dom', $domains);
        return $this->query("SELECT `id`, `name`, `domain`, `address` FROM `mailbox_group` WHERE `domain` IN ($in) ORDER BY `domain`, `name`", $params)
            ->fetchAll();
    }

    /**
     * A page of the mailboxes the nightly run looks at: Dovecot knows them (regular, not disabled), their domain is
     * active and has active rules.
     *
     * @return string[] usernames after $after, in order
     */
    public function mailboxesWithRules(string $after, int $limit): array
    {
        return $this->query("SELECT `m`.`username` FROM `mailbox` `m` JOIN `domain` `d` ON `d`.`domain` = `m`.`domain` AND `d`.`active` = '1'
          WHERE `m`.`kind` = '' AND `m`.`active` IN ('1', '2') AND `m`.`username` > :after
            AND EXISTS (SELECT 1 FROM `retention_rule` `r` WHERE `r`.`domain` = `m`.`domain` AND `r`.`active` = '1')
          ORDER BY `m`.`username` LIMIT " . max(1, $limit), ['after' => $after])->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Active rules of these mailboxes with the level of their assignment: the whole domain, a group of the mailbox
     * (members of the rule's domain only) or the mailbox itself. Only mailboxes Dovecot knows (regular, not
     * disabled) in active domains.
     *
     * @param string[] $usernames
     * @return array<string, array<array{rule: Rule, level: int}>> username => candidates
     */
    public function candidates(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }
        $params = [];
        $mailbox = [];
        foreach ([1, 2, 3] as $part) {
            [$in, $inParams] = self::in('u' . $part . '_', $usernames);
            $params += $inParams;
            $mailbox[$part] = "`m`.`kind` = '' AND `m`.`active` IN ('1', '2') AND `m`.`username` IN ($in)
              AND `m`.`domain` IN (SELECT `domain` FROM `domain` WHERE `active` = '1')";
        }
        $rows = $this->query("SELECT `r`.*, `m`.`username` AS `member`, " . Planner::LEVEL_DOMAIN . " AS `level` FROM `retention_rule` `r`
            JOIN `mailbox` `m` ON `m`.`domain` = `r`.`domain` AND {$mailbox[1]}
            WHERE `r`.`active` = '1' AND `r`.`all_mailboxes` = '1'
          UNION ALL SELECT `r`.*, `m`.`username`, " . Planner::LEVEL_GROUP . " FROM `retention_rule` `r`
            JOIN `retention_rule_member` `rm` ON `rm`.`rule_id` = `r`.`id`
            JOIN `mailbox_group` `g` ON `g`.`id` = `rm`.`group_id` AND `g`.`active` = '1'
            JOIN `mailbox_group_member` `gm` ON `gm`.`group_id` = `g`.`id`
            JOIN `mailbox` `m` ON `m`.`username` = `gm`.`username` AND `m`.`domain` = `r`.`domain` AND {$mailbox[2]}
            WHERE `r`.`active` = '1'
          UNION ALL SELECT `r`.*, `m`.`username`, " . Planner::LEVEL_MAILBOX . " FROM `retention_rule` `r`
            JOIN `retention_rule_member` `rm` ON `rm`.`rule_id` = `r`.`id`
            JOIN `mailbox` `m` ON `m`.`username` = `rm`.`username` AND `m`.`domain` = `r`.`domain` AND {$mailbox[3]}
            WHERE `r`.`active` = '1'
          ORDER BY `member`, `id`", $params)->fetchAll();
        $candidates = [];
        foreach ($rows as $row) {
            $candidates[strtolower((string)$row['member'])][] = ['rule' => Rule::fromRow($row), 'level' => (int)$row['level']];
        }
        return $candidates;
    }

    /**
     * Stores results of the nightly run. Domain and rule name come from the database, not from the runner; results
     * of a rule from another domain than the mailbox are dropped. With the summary of a rule, the results its
     * earlier runs found while simulating are dropped: only the last simulation is kept. After a run without error,
     * the active rules that decided about no folder get an empty summary.
     *
     * @param array[] $results username, rule_id, folder (names), days, matched, deleted, simulated, error: mailboxes
     *                         with found mail or an error
     * @param array[] $summary rule_id, mailboxes, matched, deleted, errors, simulated: one per rule and run
     * @param array|null $run  started (unix time), mailboxes, matched, deleted, errors, error: the whole run, sent at its end
     */
    public function addLog(array $results, array $summary, ?array $run = null): int
    {
        $stored = $this->transaction(function () use ($results, $summary, $run): int {
            $stored = 0;
            foreach ($results as $result) {
                $domain = $this->mailboxDomain((string)$result['username']);
                $rule = $this->ruleName((int)$result['rule_id']);
                if ($domain === null || $rule === null || $rule['domain'] !== $domain) {
                    continue;
                }
                $this->insertRow('retention_log', [
                    'domain' => $domain,
                    'username' => (string)$result['username'],
                    'rule_id' => (int)$result['rule_id'],
                    'rule_name' => $rule['name'],
                    'folder' => mb_substr((string)$result['folder'], 0, 10000),
                    'days' => (int)($result['days'] ?? 0),
                    'matched' => (int)($result['matched'] ?? 0),
                    'deleted' => (int)($result['deleted'] ?? 0),
                    'errors' => (int)(($result['error'] ?? '') !== ''),
                    'simulated' => (int)(bool)($result['simulated'] ?? false),
                    'error' => ($result['error'] ?? '') === '' ? null : mb_substr((string)$result['error'], 0, 1000),
                ]);
                $stored++;
            }
            $summarized = [];
            foreach ($summary as $runOfRule) {
                $rule = $this->ruleName((int)$runOfRule['rule_id']);
                if ($rule === null || isset($summarized[(int)$runOfRule['rule_id']])) {
                    continue;
                }
                $summarized[(int)$runOfRule['rule_id']] = true;
                $this->addSummary((int)$runOfRule['rule_id'], $rule, $runOfRule);
                $stored++;
            }
            if ($run !== null && ($run['error'] ?? '') === '') {
                // rules created during the run may have missed its first pages
                foreach ($this->query("SELECT `r`.`id`, `r`.`domain`, `r`.`name`, `r`.`mode` FROM `retention_rule` `r`
                  JOIN `domain` `d` ON `d`.`domain` = `r`.`domain` AND `d`.`active` = '1'
                  WHERE `r`.`active` = '1' AND `r`.`created` < FROM_UNIXTIME(:started)", ['started' => (int)($run['started'] ?? 0)])->fetchAll() as $row) {
                    if (!isset($summarized[(int)$row['id']])) {
                        $this->addSummary((int)$row['id'], ['domain' => (string)$row['domain'], 'name' => (string)$row['name']],
                            ['simulated' => $row['mode'] !== 'delete']);
                        $stored++;
                    }
                }
            }
            if ($run !== null) {
                $this->insertRow('retention_log', [
                    'domain' => '',
                    'mailboxes' => (int)($run['mailboxes'] ?? 0),
                    'matched' => (int)($run['matched'] ?? 0),
                    'deleted' => (int)($run['deleted'] ?? 0),
                    'errors' => (int)($run['errors'] ?? 0),
                    'error' => ($run['error'] ?? '') === '' ? null : mb_substr((string)$run['error'], 0, 1000),
                ]);
                $stored++;
            }
            return $stored;
        });
        if ($run !== null) {
            $this->prune();
        }
        return $stored;
    }

    /** Summary of a rule's run; drops what its earlier runs found while simulating */
    private function addSummary(int $ruleId, array $rule, array $values): void
    {
        $previous = $this->query("SELECT MAX(`id`) FROM `retention_log` WHERE `rule_id` = :id AND `username` = ''", ['id' => $ruleId])->fetchColumn();
        if ($previous !== null && $previous !== false) {
            $this->query("DELETE FROM `retention_log` WHERE `rule_id` = :id AND `username` != '' AND `simulated` = '1' AND `id` < :previous",
                ['id' => $ruleId, 'previous' => (int)$previous]);
        }
        $this->insertRow('retention_log', [
            'domain' => $rule['domain'],
            'rule_id' => $ruleId,
            'rule_name' => $rule['name'],
            'mailboxes' => (int)($values['mailboxes'] ?? 0),
            'matched' => (int)($values['matched'] ?? 0),
            'deleted' => (int)($values['deleted'] ?? 0),
            'errors' => (int)($values['errors'] ?? 0),
            'simulated' => (int)(bool)($values['simulated'] ?? false),
        ]);
    }

    /** Drops the results of the runs in a domain (the domain was deleted) */
    public function deleteLog(string $domain): void
    {
        $this->query("DELETE FROM `retention_log` WHERE `domain` = :domain", ['domain' => $domain]);
    }

    /** @return array<int, array> rule id => the summary of its last run (created, mailboxes, matched, deleted, errors, simulated) */
    public function lastRuns(array $ruleIds): array
    {
        if (empty($ruleIds)) {
            return [];
        }
        [$in, $params] = self::in('rule', array_map('intval', $ruleIds));
        $runs = [];
        foreach ($this->query("SELECT `l`.`rule_id`, `l`.`created`, `l`.`mailboxes`, `l`.`matched`, `l`.`deleted`, `l`.`errors`, `l`.`simulated`
          FROM `retention_log` `l` WHERE `l`.`username` = '' AND `l`.`rule_id` IN ($in) AND `l`.`id` = (SELECT MAX(`id`) FROM `retention_log`
            WHERE `rule_id` = `l`.`rule_id` AND `username` = '')", $params)->fetchAll() as $row) {
            $runs[(int)$row['rule_id']] = $row;
        }
        return $runs;
    }

    /**
     * @return array[] mailboxes with found mail or errors in the nightly runs visible to the caller, and for admins the
     *                 whole runs (username '', rule_id null), newest first
     */
    public function log(Context $context, array $domains, int $limit): array
    {
        [$where, $params] = $this->visibleDomains($context, $domains);
        if ($where === null) {
            return [];
        }
        return $this->query("SELECT UNIX_TIMESTAMP(`created`) AS `time`, `domain`, `username`, `rule_id`, `rule_name`, `folder`, `days`, `mailboxes`, `matched`, `deleted`, `errors`, `simulated`, `error`
          FROM `retention_log` WHERE (`username` != '' OR `rule_id` IS NULL) AND $where ORDER BY `created` DESC, `id` DESC LIMIT " . max(1, $limit), $params)->fetchAll();
    }

    /** Drops results older than LOG_DAYS, in parts so a large log does not hold the table */
    private function prune(): void
    {
        do {
            $deleted = $this->query("DELETE FROM `retention_log` WHERE `created` < NOW() - INTERVAL " . self::LOG_DAYS . " DAY LIMIT 10000")->rowCount();
        } while ($deleted >= 10000);
    }

    /** @return array{domain: string, name: string}|null */
    private function ruleName(int $id): ?array
    {
        $row = $this->query("SELECT `domain`, `name` FROM `retention_rule` WHERE `id` = :id", ['id' => $id])->fetch();
        return is_array($row) ? ['domain' => (string)$row['domain'], 'name' => (string)$row['name']] : null;
    }

    /** @return array{0: ?string, 1: array} condition on `domain` for the caller and the domain filter, null = nothing visible */
    private function visibleDomains(Context $context, array $domains): array
    {
        if ($context->hasAdminRights()) {
            [$where, $params] = ['1=1', []];
        } elseif ($context->isDomainAdmin()) {
            [$where, $params] = ["`domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :vis_da)",
                ['vis_da' => $context->username]];
        } else {
            return [null, []];
        }
        if (!empty($domains)) {
            [$in, $inParams] = self::in('dom', $domains);
            $where .= " AND `domain` IN ($in)";
            $params += $inParams;
        }
        return [$where, $params];
    }

    /** @return array{0: string, 1: array} placeholders and parameters of an IN list */
    private static function in(string $prefix, array $values): array
    {
        $names = [];
        $params = [];
        foreach (array_values($values) as $i => $value) {
            $names[] = ':' . $prefix . $i;
            $params[$prefix . $i] = $value;
        }
        return [implode(', ', $names), $params];
    }

    private function withMembers(Rule $rule): Rule
    {
        foreach ($this->query("SELECT `username`, `group_id` FROM `retention_rule_member` WHERE `rule_id` = :id ORDER BY `username`, `group_id`",
            ['id' => $rule->id])->fetchAll() as $row) {
            if ($row['username'] !== null) {
                $rule->mailboxes[] = strtolower((string)$row['username']);
            } elseif ($row['group_id'] !== null) {
                $rule->groups[] = (int)$row['group_id'];
            }
        }
        return $rule;
    }
}
