<?php

declare(strict_types=1);

namespace Mailcow\Retention\Service;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Mailcow\Retention\Model\Rule;
use Mailcow\Retention\Repository\RuleRepository;

/**
 * Use cases of retention rules.
 *
 * Read: admins all rules (also read-only API keys); domain admins with the `retention` ACL the rules of their
 *       domains. Mailbox users see the rules that apply to their mailbox (effective()).
 * Edit: admins; domain admins with the ACL for rules of their domains. A rule is assigned to all mailboxes of its
 *       domain or to mailboxes and groups of it. Groups need the `mailbox_groups` ACL as well: without it a domain
 *       admin sees no groups and keeps the group assignments.
 */
class RuleService
{
    private const MAX_FOLDERS = 50;
    private const MAX_MEMBERS = 500;
    private const MAX_DAYS = 36500;
    private const LOG_LIMIT = 1000;
    /** Mailboxes per page of the plan, and results per report of the runner */
    private const PLAN_PAGE = 500;
    private const MAX_RESULTS = 1000;

    public function __construct(private RuleRepository $repo, private Context $context)
    {
    }

    public function canRead(): bool
    {
        return $this->context->hasAdminRights()
            || ($this->context->isDomainAdmin() && $this->context->hasAcl('retention'));
    }

    /** @throws AccessDenied unless the caller may edit rules at all */
    public function requireEditor(): void
    {
        if (!$this->canRead() || $this->context->isReadOnlyApi()) {
            throw new AccessDenied();
        }
    }

    public function get(int $id): ?Rule
    {
        $rule = $this->canRead() ? $this->repo->find($id) : null;
        return ($rule !== null && $this->visible($rule)) ? $rule : null;
    }

    /** @return int[] */
    public function visibleIds(array $domains = []): array
    {
        return $this->canRead() ? $this->repo->visibleIds($this->context, $domains) : [];
    }

    public function canEdit(Rule $rule): bool
    {
        return $this->canRead() && !$this->context->isReadOnlyApi() && $this->visible($rule);
    }

    /** $lastRun: summary of the last nightly run, null = not run yet */
    public function toArray(Rule $rule, ?array $lastRun = null): array
    {
        return $rule->toArray() + ['can_edit' => (int)$this->canEdit($rule), 'last_run' => $lastRun];
    }

    /** @return array<int, array> rule id => summary of its last nightly run */
    public function lastRuns(array $ruleIds): array
    {
        return $this->canRead() ? $this->repo->lastRuns($ruleIds) : [];
    }

    /** @throws UserError */
    public function create(array $input): Rule
    {
        $this->requireEditor();
        $rule = new Rule();
        $rule->domain = $this->domainFor($input['domain'] ?? null);
        $this->apply($rule, $input);
        $this->repo->save($rule);
        return $rule;
    }

    /** Attributes missing in $input keep their value @throws UserError */
    public function update(int $id, array $input): Rule
    {
        $rule = $this->editable($id);
        $this->apply($rule, $input);
        $this->repo->save($rule);
        return $rule;
    }

    /** @throws UserError */
    public function delete(int $id): Rule
    {
        $rule = $this->editable($id);
        $this->repo->delete($id);
        return $rule;
    }

    /** @return array[] id, name, domain, address of the groups in these domains (choices of the forms) */
    public function groupChoices(array $domains): array
    {
        return ($this->canRead() && $this->canUseGroups()) ? $this->repo->groupsIn($domains) : [];
    }

    /**
     * What the rules delete in a mailbox, per folder (see Planner): for the mailbox user, or for those who may read
     * the rules and access the mailbox.
     *
     * @return array[] actions of Planner::actions()
     * @throws AccessDenied
     */
    public function effective(mixed $username): array
    {
        $username = strtolower((string)Input::text($username));
        $allowed = $this->context->isUser() ? $username === strtolower((string)$this->context->username)
            : ($this->canRead() && ($this->context->hasAdminRights() || $this->context->canAccessMailbox($username)));
        if ($username === '' || !$allowed) {
            throw new AccessDenied();
        }
        return Planner::actions($this->repo->candidates([$username])[$username] ?? []);
    }

    /** @return array[] results of the nightly runs in the caller's domains, newest first; $limit 0 = LOG_LIMIT */
    public function log(int $limit = 0): array
    {
        return $this->canRead() ? $this->repo->log($this->context, [], ($limit > 0) ? min($limit, self::LOG_LIMIT * 10) : self::LOG_LIMIT) : [];
    }

    /**
     * A page of the plan of the nightly run (system context), mailboxes after $after in order
     *
     * @return array{mailboxes: array<string, array[]>, next: ?string} username => actions of Planner::actions() for
     *         the mailboxes with rules; next: the $after of the next page, null after the last
     */
    public function plan(string $after = '', int $limit = 0): array
    {
        $limit = ($limit > 0) ? min($limit, self::PLAN_PAGE) : self::PLAN_PAGE;
        $usernames = $this->repo->mailboxesWithRules($after, $limit);
        $plan = array_filter(array_map(fn(array $candidates): array => Planner::actions($candidates), $this->repo->candidates($usernames)));
        return ['mailboxes' => $plan, 'next' => (count($usernames) < $limit) ? null : (string)end($usernames)];
    }

    /**
     * Results of the nightly run (system context): mailboxes with found mail or errors, a summary per rule and at
     * the end the whole run
     *
     * @throws UserError for more than MAX_RESULTS results
     */
    public function report(array $results, array $summary, ?array $run = null): int
    {
        if (count($results) > self::MAX_RESULTS || count($summary) > self::MAX_RESULTS * 10) {
            throw new UserError('retention_entries_exceeded', [self::MAX_RESULTS]);
        }
        $results = array_filter($results, fn(mixed $result): bool => is_array($result) && is_string($result['username'] ?? null)
            && is_numeric($result['rule_id'] ?? null) && is_string($result['folder'] ?? null));
        $summary = array_filter($summary, fn(mixed $runOfRule): bool => is_array($runOfRule) && is_numeric($runOfRule['rule_id'] ?? null));
        return $this->repo->addLog(array_values($results), array_values($summary), $run);
    }

    /** The domain was deleted: its rules went with it (foreign key), the results of its runs go now */
    public function domainDeleted(string $domain): void
    {
        $this->repo->deleteLog($domain);
    }

    private function visible(Rule $rule): bool
    {
        return $this->context->hasAdminRights() || $this->context->canAccessDomain($rule->domain);
    }

    private function canUseGroups(): bool
    {
        return $this->context->hasAdminRights() || $this->context->hasAcl('mailbox_groups');
    }

    /** @throws UserError */
    private function editable(int $id): Rule
    {
        $this->requireEditor();
        $rule = $this->get($id);
        if ($rule === null || !$this->canEdit($rule)) {
            throw new AccessDenied();
        }
        return $rule;
    }

    /** @throws UserError */
    private function domainFor(mixed $value): string
    {
        $domain = Input::text($value);
        $domain = ($domain === null || $domain === '') ? '' : (string)idn_to_ascii(strtolower($domain), 0, INTL_IDNA_VARIANT_UTS46);
        if ($domain !== '' && !$this->context->hasAdminRights() && !$this->context->canAccessDomain($domain)) {
            throw new AccessDenied();
        }
        if ($domain === '' || !$this->repo->isDomain($domain)) {
            throw new UserError('domain_not_found', [htmlspecialchars((string)Input::text($value))]);
        }
        return $domain;
    }

    /** @throws UserError */
    private function apply(Rule $rule, array $input): void
    {
        if (array_key_exists('name', $input) || $rule->id === null) {
            $name = Input::text($input['name'] ?? null);
            if ($name === null || $name === '' || mb_strlen($name) > 100 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
                throw new UserError('retention_name_invalid');
            }
            if ($this->repo->nameExists($rule->domain, $name, $rule->id)) {
                throw new UserError('object_exists', [htmlspecialchars($name)]);
            }
            $rule->name = $name;
        }
        if (array_key_exists('description', $input)) {
            $description = Input::text($input['description']);
            if ($description === null || mb_strlen($description) > 255 || preg_match('/[\x00-\x1f\x7f]/', $description)) {
                throw new UserError('retention_description_invalid');
            }
            $rule->description = $description;
        }
        // the UI sends the special folders and the own folder names apart (own_folders, one per line or a list)
        if (array_key_exists('folders', $input) || array_key_exists('own_folders', $input)) {
            $folders = array_key_exists('folders', $input) ? self::values($input['folders'])
                : array_filter($rule->folders, fn(string $folder): bool => in_array($folder, Rule::FOLDERS, true));
            if (array_key_exists('own_folders', $input)) {
                $folders = array_filter($folders, fn(string $folder): bool => !str_starts_with($folder, Rule::OWN_FOLDER));
                $names = is_array($input['own_folders']) ? $input['own_folders'] : preg_split('/\r?\n/', (string)Input::text($input['own_folders']));
                foreach ($names as $name) {
                    if (!is_string($name)) {
                        throw new UserError('retention_folder_invalid', ['']);
                    }
                    if (trim($name) !== '') {
                        $folders[] = Rule::OWN_FOLDER . trim($name);
                    }
                }
            }
            $rule->folders = self::folders($folders);
        }
        if ($rule->folders === []) {
            throw new UserError('retention_folders_invalid');
        }
        if (array_key_exists('days', $input) || $rule->id === null) {
            $days = Input::intInRange($input['days'] ?? null, 1, self::MAX_DAYS);
            $rule->days = $days ?? throw new UserError('retention_days_invalid', [self::MAX_DAYS]);
        }
        if (array_key_exists('mode', $input)) {
            $mode = Input::text($input['mode']);
            $rule->mode = in_array($mode, Rule::MODES, true) ? $mode : throw new UserError('retention_mode_invalid');
        }
        foreach (['keep_flagged' => 'keepFlagged', 'all_mailboxes' => 'allMailboxes', 'active' => 'active'] as $key => $property) {
            if (array_key_exists($key, $input)) {
                $rule->$property = (int)self::flag($input[$key]);
            }
        }
        if (array_key_exists('mailboxes', $input)) {
            $rule->mailboxes = [];
            foreach (self::limited($input['mailboxes'], self::MAX_MEMBERS) as $username) {
                $username = strtolower($username);
                if ($this->repo->mailboxDomain($username) !== $rule->domain) {
                    throw new UserError('retention_member_invalid', [htmlspecialchars($username)]);
                }
                $rule->mailboxes[$username] = $username;
            }
            $rule->mailboxes = array_values($rule->mailboxes);
        }
        // without access to groups the assignments stay; only sending them back unchanged is accepted
        $groupAccess = $this->canUseGroups();
        if (array_key_exists('groups', $input) && !$groupAccess && self::values($input['groups']) !== []) {
            $sent = array_values(array_unique(array_map('intval', self::values($input['groups']))));
            $stored = $rule->groups;
            sort($sent);
            sort($stored);
            if ($sent !== $stored) {
                throw new AccessDenied();
            }
        }
        if (array_key_exists('groups', $input) && $groupAccess) {
            $rule->groups = [];
            foreach (self::limited($input['groups'], self::MAX_MEMBERS) as $value) {
                $id = Input::intInRange($value, 1, PHP_INT_MAX);
                if ($id === null || $this->repo->groupDomain($id) !== $rule->domain) {
                    throw new UserError('retention_member_invalid', [htmlspecialchars($value)]);
                }
                $rule->groups[$id] = $id;
            }
            $rule->groups = array_values($rule->groups);
        }
    }

    /**
     * Folders of a rule: keys of Rule::FOLDERS and folder:<name>, without repeats. Names are Dovecot folder names
     * (separator /, wildcard *), printable and at most 255 characters; INBOX is written in capitals like Dovecot does.
     *
     * @param string[] $values
     * @return string[]
     * @throws UserError
     */
    private static function folders(array $values): array
    {
        $folders = [];
        foreach (self::limited($values, self::MAX_FOLDERS) as $folder) {
            if (str_starts_with($folder, Rule::OWN_FOLDER)) {
                $name = trim(substr($folder, strlen(Rule::OWN_FOLDER)), " \t/");
                if ($name === '' || mb_strlen($name) > 255 || preg_match('/[\x00-\x1f\x7f]/u', $name) || !mb_check_encoding($name, 'UTF-8')) {
                    throw new UserError('retention_folder_invalid', [htmlspecialchars($folder)]);
                }
                $name = preg_replace('~^inbox(?=/|$)~i', 'INBOX', $name);
                $folder = Rule::OWN_FOLDER . $name;
            } elseif (!in_array($folder, Rule::FOLDERS, true)) {
                throw new UserError('retention_folder_invalid', [htmlspecialchars($folder)]);
            }
            $folders[$folder] = $folder;
        }
        return array_values($folders);
    }

    /**
     * Values of a list field without repeats, at most $max (checked before anything is looked up)
     *
     * @return string[]
     * @throws UserError
     */
    private static function limited(mixed $value, int $max): array
    {
        $values = array_values(array_unique(self::values($value)));
        if (count($values) > $max) {
            throw new UserError('retention_entries_exceeded', [$max]);
        }
        return $values;
    }

    /**
     * Values of a list field: an array (multi-select, JSON) or a string separated by line breaks or commas.
     *
     * @return string[]
     */
    private static function values(mixed $value): array
    {
        $values = is_array($value) ? $value : preg_split('/[\r\n,;]+/', (string)Input::text($value));
        return array_values(array_filter(array_map(fn(mixed $v): string => is_scalar($v) ? trim((string)$v) : '', $values), 'strlen'));
    }

    /** Checkbox or API flag; a hidden "0" + checkbox pair arrives as ["0", "1"] when checked */
    private static function flag(mixed $value): bool
    {
        if (is_array($value)) {
            $value = end($value);
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
