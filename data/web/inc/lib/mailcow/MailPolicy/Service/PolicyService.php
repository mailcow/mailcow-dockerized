<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy\Service;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Mailcow\MailPolicy\Model\Decision;
use Mailcow\MailPolicy\Model\Policy;
use Mailcow\MailPolicy\Repository\PolicyRepository;

/**
 * Use cases of mail policies.
 *
 * Read: admins all policies (also read-only API keys); domain admins with the `mail_policies` ACL the policies
 *       of their domains.
 * Edit: admins; domain admins with the ACL for policies of their domains. A policy is assigned to mailboxes and
 *       groups of its own domain; `group:<id>` entries name groups of that domain. Groups need the `mailbox_groups`
 *       ACL as well: without it a domain admin sees no groups and keeps the group entries and assignments.
 */
class PolicyService
{
    private const MAX_ENTRIES = 500;
    /** The reject text goes into the SMTP reply: printable ASCII */
    private const MAX_REJECT_TEXT = 200;
    private const LISTS = ['send_allow' => 'sendAllow', 'send_block' => 'sendBlock', 'receive_allow' => 'receiveAllow', 'receive_block' => 'receiveBlock'];

    public function __construct(private PolicyRepository $repo, private PolicyEngine $engine, private Context $context)
    {
    }

    public function canRead(): bool
    {
        return $this->context->hasAdminRights()
            || ($this->context->isDomainAdmin() && $this->context->hasAcl('mail_policies'));
    }

    /** @throws AccessDenied unless the caller may edit policies at all */
    public function requireEditor(): void
    {
        if (!$this->canRead() || $this->context->isReadOnlyApi()) {
            throw new AccessDenied();
        }
    }

    public function get(int $id): ?Policy
    {
        $policy = $this->canRead() ? $this->repo->find($id) : null;
        return ($policy !== null && $this->visible($policy)) ? $policy : null;
    }

    /** @return int[] */
    public function visibleIds(array $domains = []): array
    {
        return $this->canRead() ? $this->repo->visibleIds($this->context, $domains) : [];
    }

    public function canEdit(Policy $policy): bool
    {
        return $this->canRead() && !$this->context->isReadOnlyApi() && $this->visible($policy);
    }

    public function toArray(Policy $policy): array
    {
        return $policy->toArray() + ['can_edit' => (int)$this->canEdit($policy)];
    }

    /** @throws UserError */
    public function create(array $input): Policy
    {
        $this->requireEditor();
        $policy = new Policy();
        $policy->domain = $this->domainFor($input['domain'] ?? null);
        $this->apply($policy, $input);
        $this->repo->save($policy);
        return $policy;
    }

    /** Attributes missing in $input keep their value @throws UserError */
    public function update(int $id, array $input): Policy
    {
        $policy = $this->editable($id);
        $this->apply($policy, $input);
        $this->repo->save($policy);
        return $policy;
    }

    /** @throws UserError */
    public function delete(int $id): Policy
    {
        $policy = $this->editable($id);
        $this->repo->delete($id);
        return $policy;
    }

    /** Address entries of the lists follow a renamed mailbox or alias; called after the rename, without permission check */
    public function addressRenamed(string $old, string $new): void
    {
        $this->repo->renameAddress(strtolower($old), strtolower($new));
    }

    /** @return array[] id, name, domain, address of the groups in these domains (choices of the forms) */
    public function groupChoices(array $domains): array
    {
        return ($this->canRead() && $this->canUseGroups()) ? $this->repo->groupsIn($domains) : [];
    }

    private function canUseGroups(): bool
    {
        return $this->context->hasAdminRights() || $this->context->hasAcl('mailbox_groups');
    }

    /**
     * "Test a rule": would the mail be accepted? A sender that is a mailbox counts as logged in when $loggedIn.
     *
     * @return array{allowed: int, side: string, policy: ?string, internal_alias: int, text: string}
     * @throws UserError
     */
    public function test(mixed $sender, mixed $recipient, bool $loggedIn): array
    {
        if (!$this->canRead()) {
            throw new AccessDenied();
        }
        $sender = strtolower((string)Input::text($sender));
        $recipient = strtolower((string)Input::text($recipient));
        if (($sender !== '' && !filter_var($sender, FILTER_VALIDATE_EMAIL)) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new UserError('mail_policy_test_invalid');
        }
        // domain admins test mail of their domains; a sender of another domain counts as not logged in
        $ownSender = $this->context->hasAdminRights() || $this->context->canAccessDomain(self::domainOf($sender));
        if (!$ownSender && !$this->context->canAccessDomain(self::domainOf($recipient))) {
            throw new AccessDenied();
        }
        $login = ($loggedIn && $ownSender && $this->repo->mailboxDomain($sender) !== null) ? $sender : '';
        $decision = $this->engine->check($sender, $recipient, $login);
        $policy = $decision->policy;
        $hidden = $policy !== null && $policy->id !== null && !$this->visible($policy);
        return [
            'allowed' => (int)$decision->allowed,
            'side' => $decision->side,
            'policy' => ($policy !== null && $policy->id !== null && !$hidden) ? $policy->name : null,
            'internal_alias' => (int)($policy !== null && $policy->id === null),
            'logged_in' => (int)($login !== ''),
            // the text of a policy of another domain stays hidden
            'text' => $decision->allowed ? '' : ($hidden ? Decision::defaultText($decision->side) : $decision->text()),
        ];
    }

    private function visible(Policy $policy): bool
    {
        return $this->context->hasAdminRights() || $this->context->canAccessDomain($policy->domain);
    }

    /** @throws UserError */
    private function editable(int $id): Policy
    {
        $this->requireEditor();
        $policy = $this->get($id);
        if ($policy === null || !$this->canEdit($policy)) {
            throw new AccessDenied();
        }
        return $policy;
    }

    /** @throws UserError */
    private function domainFor(mixed $value): string
    {
        $domain = strtolower((string)Input::text($value));
        if ($domain === '' || !$this->repo->isDomain($domain)) {
            throw new UserError('domain_not_found', [htmlspecialchars((string)Input::text($value))]);
        }
        if (!$this->context->hasAdminRights() && !$this->context->canAccessDomain($domain)) {
            throw new AccessDenied();
        }
        return $domain;
    }

    /** @throws UserError */
    private function apply(Policy $policy, array $input): void
    {
        if (array_key_exists('name', $input) || $policy->id === null) {
            $name = Input::text($input['name'] ?? null);
            if ($name === null || $name === '' || mb_strlen($name) > 100 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
                throw new UserError('mail_policy_name_invalid');
            }
            if ($this->repo->nameExists($policy->domain, $name, $policy->id)) {
                throw new UserError('object_exists', [htmlspecialchars($name)]);
            }
            $policy->name = $name;
        }
        if (array_key_exists('description', $input)) {
            $description = Input::text($input['description']);
            if ($description === null || mb_strlen($description) > 255 || preg_match('/[\x00-\x1f\x7f]/', $description)) {
                throw new UserError('mail_policy_description_invalid');
            }
            $policy->description = $description;
        }
        foreach (['send_mode' => 'sendMode', 'receive_mode' => 'receiveMode'] as $key => $property) {
            if (array_key_exists($key, $input)) {
                $mode = Input::text($input[$key]);
                $policy->$property = in_array($mode, Policy::MODES, true) ? $mode : throw new UserError('mail_policy_mode_invalid');
            }
        }
        $groupAccess = $this->canUseGroups();
        foreach (self::LISTS as $key => $property) {
            // the UI sends the groups of a list separately (<list>_groups); the API may name them as group:<id>
            if (!array_key_exists($key, $input) && !array_key_exists($key . '_groups', $input)) {
                continue;
            }
            $entries = array_key_exists($key, $input) ? self::values($input[$key]) : self::withoutGroups($policy->$property);
            $kept = [];
            if (!$groupAccess) {
                // without access to groups the group entries stay (also when sent back unchanged); the form shows no group choices
                $kept = array_values(array_diff($policy->$property, self::withoutGroups($policy->$property)));
                $entries = array_values(array_diff(array_map('strtolower', $entries), $kept));
            } elseif (array_key_exists($key . '_groups', $input)) {
                $entries = array_merge(self::withoutGroups($entries), array_map(fn(string $id): string => 'group:' . $id, self::values($input[$key . '_groups'])));
            }
            $entries = array_values(array_unique(array_merge($this->entries($entries, $policy->domain, $groupAccess), $kept)));
            if (count($entries) > self::MAX_ENTRIES) {
                throw new UserError('mail_policy_entries_exceeded', [self::MAX_ENTRIES]);
            }
            $policy->$property = $entries;
        }
        if (array_key_exists('reject_text', $input)) {
            $text = Input::text($input['reject_text']);
            if ($text === null || strlen($text) > self::MAX_REJECT_TEXT || preg_match('/[^\x20-\x7e]/', $text)) {
                throw new UserError('mail_policy_reject_text_invalid');
            }
            $policy->rejectText = $text;
        }
        if (array_key_exists('active', $input)) {
            $policy->active = (int)self::flag($input['active']);
        }
        if (array_key_exists('mailboxes', $input)) {
            $policy->mailboxes = [];
            foreach (self::limited($input['mailboxes']) as $username) {
                $username = strtolower($username);
                if ($this->repo->mailboxDomain($username) !== $policy->domain) {
                    throw new UserError('mail_policy_member_invalid', [htmlspecialchars($username)]);
                }
                $policy->mailboxes[$username] = $username;
            }
            $policy->mailboxes = array_values($policy->mailboxes);
        }
        // without access to groups the assignments stay; only sending them back unchanged is accepted
        if (array_key_exists('groups', $input) && !$groupAccess && self::values($input['groups']) !== []) {
            $sent = array_map('intval', self::values($input['groups']));
            $stored = $policy->groups;
            sort($sent);
            sort($stored);
            if (array_values(array_unique($sent)) !== $stored) {
                throw new AccessDenied();
            }
        }
        if (array_key_exists('groups', $input) && $groupAccess) {
            $policy->groups = [];
            foreach (self::limited($input['groups']) as $value) {
                $id = Input::intInRange($value, 1, PHP_INT_MAX);
                if ($id === null || $this->repo->groupDomain($id) !== $policy->domain) {
                    throw new UserError('mail_policy_member_invalid', [htmlspecialchars($value)]);
                }
                $policy->groups[$id] = $id;
            }
            $policy->groups = array_values($policy->groups);
        }
    }

    /**
     * Entries of a list: addresses, domains (a leading @ is dropped) and group:<id> of a group of the policy's
     * domain, lower case, without repeats.
     *
     * @param string[] $values
     * @return string[]
     * @throws UserError
     */
    private function entries(array $values, string $domain, bool $groupAccess): array
    {
        $entries = [];
        foreach (self::limited(array_map('strtolower', $values)) as $entry) {
            if (str_starts_with($entry, 'group:')) {
                $id = Input::intInRange(substr($entry, 6), 1, PHP_INT_MAX);
                if (!$groupAccess || $id === null || $this->repo->groupDomain($id) !== $domain) {
                    throw new UserError('mail_policy_entry_invalid', [htmlspecialchars($entry)]);
                }
                $entry = 'group:' . $id;
            } elseif (str_contains(ltrim($entry, '@'), '@')) {
                // in the form the engine compares: domain without the final dot, never a +tag or an IP literal
                $at = strrpos($entry, '@');
                $local = substr($entry, 0, $at);
                $entry = $local . '@' . rtrim(substr($entry, $at + 1), '.');
                if (!filter_var($entry, FILTER_VALIDATE_EMAIL) || str_contains(substr($local, 1), '+') || str_contains($entry, '@[')) {
                    throw new UserError('mail_policy_entry_invalid', [htmlspecialchars($entry)]);
                }
            } else {
                $entry = rtrim(ltrim($entry, '@'), '.');
                if (!str_contains($entry, '.') || !filter_var($entry, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    throw new UserError('mail_policy_entry_invalid', [htmlspecialchars($entry)]);
                }
            }
            $entries[$entry] = $entry;
        }
        return array_values($entries);
    }

    /**
     * Values of a list field without repeats, at most MAX_ENTRIES (checked before anything is looked up)
     *
     * @return string[]
     * @throws UserError
     */
    private static function limited(mixed $value): array
    {
        $values = array_values(array_unique(self::values($value)));
        if (count($values) > self::MAX_ENTRIES) {
            throw new UserError('mail_policy_entries_exceeded', [self::MAX_ENTRIES]);
        }
        return $values;
    }

    /** @return string[] entries without group:<id> */
    private static function withoutGroups(array $entries): array
    {
        return array_values(array_filter($entries, fn(string $entry): bool => !str_starts_with(strtolower($entry), 'group:')));
    }

    /**
     * Values of a list field: an array (multi-select, JSON) or a string separated by line breaks, commas or spaces.
     *
     * @return string[]
     */
    private static function values(mixed $value): array
    {
        $values = is_array($value) ? $value : preg_split('/[\s,;]+/', (string)Input::text($value));
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

    private static function domainOf(string $address): string
    {
        $at = strrpos($address, '@');
        return ($at === false) ? '' : substr($address, $at + 1);
    }
}
