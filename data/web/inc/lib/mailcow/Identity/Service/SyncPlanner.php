<?php

declare(strict_types=1);

namespace Mailcow\Identity\Service;

use Mailcow\Identity\Model\Account;
use Mailcow\Identity\Model\DirectoryGroup;
use Mailcow\Identity\Model\DirectoryUser;
use Mailcow\Identity\Model\Listing;
use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Model\State;
use Mailcow\Identity\Model\SyncPlan;

/**
 * Decides what a sync run changes, without side effects.
 *
 * Users: linked mailboxes are found by IdP user id, unlinked ones are adopted by email. A mailbox linked to
 *        another IdP user, or an IdP user whose email moved away from its mailbox, is a conflict; nothing is
 *        relinked or created for it.
 * Offboarding: a user disabled in the IdP, or not listed by two consecutive complete runs, gets login disabled
 *        (active = 2), once per IdP disable. Only those mailboxes are activated again when the IdP enables
 *        the user; manual changes of active always win.
 * Groups: IdP groups map to mailbox groups by idp_ref; unknown ones are created in the domain of their mail,
 *        else in the default group domain. Linked users that are not listed keep their IdP memberships.
 * Guard: more deactivations or membership removals than max(5, limit % of active IdP mailboxes resp. IdP
 *        memberships) block the run unless forced.
 */
class SyncPlanner
{
    private const GUARD_MIN = 5;

    public function plan(Settings $settings, Listing $listing, State $state, bool $force = false): SyncPlan
    {
        $plan = new SyncPlan();
        $owners = [];
        foreach ($state->accounts as $username => $account) {
            if ($account->externalId !== null) {
                $owners[$account->externalId] = (string)$username;
            }
        }
        /** @var array<string, DirectoryUser> $matched username => listed user */
        $matched = [];
        /** @var array<string, DirectoryUser> $candidates email => user to create, template still open */
        $candidates = [];
        $duplicates = [];
        foreach ($listing->users as $user) {
            if (isset($owners[$user->id])) {
                $username = $owners[$user->id];
                if ($user->email !== null && $user->email !== $username) {
                    self::conflict($plan, $username . ' is linked to an IdP user whose email is now ' . $user->email);
                }
                $matched[$username] = $user;
            } elseif ($user->email === null) {
                $plan->skipped++;
            } elseif (isset($state->accounts[$user->email])) {
                $account = $state->accounts[$user->email];
                if ($account->externalId !== null || isset($plan->links[$user->email])) {
                    self::conflict($plan, $user->email . ' is linked to another IdP user');
                    continue;
                }
                $plan->links[$user->email] = $user->id;
                $matched[$user->email] = $user;
            } elseif (isset($state->mailboxes[$user->email]) || !$settings->importUsers || !$user->enabled
                || !in_array(substr((string)strrchr($user->email, '@'), 1), $state->domains, true)) {
                // other authsource, import off, disabled or no mailcow domain
                $plan->skipped++;
            } elseif (isset($candidates[$user->email]) || isset($duplicates[$user->email])) {
                self::conflict($plan, 'Several IdP users have the email ' . $user->email);
                unset($candidates[$user->email]);
                $duplicates[$user->email] = true;
            } else {
                $candidates[$user->email] = $user;
            }
        }
        $groups = ($settings->groupSync && $listing->groups !== null) ? $listing->groups : null;
        $this->planCreates($settings, $candidates, $groups ?? [], $state, $plan);
        $usernames = [];
        foreach ($matched as $username => $user) {
            $usernames[$user->id] = (string)$username;
        }
        foreach ($plan->creates as $create) {
            $usernames[$create['id']] = $create['email'];
        }
        if ($groups !== null) {
            $this->planGroups($settings, $groups, $state, $usernames, $matched, $plan);
        }
        $this->planStates($settings, $state, $matched, $plan);
        if ($settings->periodicSync) {
            $this->planUpdates($settings, $state, $matched, $plan);
        }
        $active = count(array_filter($state->accounts, fn(Account $a): bool => $a->active === 1));
        $memberships = array_sum(array_map(fn(array $g): int => count(self::idpMembers($g)), $state->groups));
        if (!$force && (count($plan->deactivate) > self::limit($active, $settings->offboardingLimit)
            || $plan->membershipRemovals > self::limit($memberships, $settings->offboardingLimit))) {
            $plan->blocked = true;
        }
        return $plan;
    }

    /**
     * @param array<string, DirectoryGroup> $groups
     * @param array<string, string> $usernames user id => username of matched and new users
     */
    private function planGroups(Settings $settings, array $groups, State $state, array $usernames, array $matched, SyncPlan $plan): void
    {
        $linked = [];
        $names = [];
        foreach ($state->groups as $group) {
            if ($group['idp_ref'] !== null) {
                $linked[(string)$group['idp_ref']] = $group;
            }
            $names[$group['domain']][mb_strtolower($group['name'])] = (int)$group['id'];
        }
        foreach ($groups as $ref => $directoryGroup) {
            $ref = (string)$ref;
            $members = [];
            foreach ($directoryGroup->memberIds as $id) {
                if (isset($usernames[$id])) {
                    $members[] = $usernames[$id];
                }
            }
            $current = $linked[$ref] ?? null;
            if ($current === null) {
                if (!$settings->groupAutocreate) {
                    continue;
                }
                $domain = self::groupDomain($settings, $directoryGroup, $state->domains);
                if ($domain === null) {
                    self::conflict($plan, 'IdP group ' . $directoryGroup->name . ' has no mailcow domain');
                    continue;
                }
                if (isset($names[$domain][mb_strtolower($directoryGroup->name)])) {
                    self::conflict($plan, 'A group ' . $directoryGroup->name . ' exists in ' . $domain . ' and is not linked to the IdP group');
                    continue;
                }
                $names[$domain][mb_strtolower($directoryGroup->name)] = 0;
                $plan->groupCreates[] = ['ref' => $ref, 'name' => $directoryGroup->name, 'domain' => $domain, 'mail' => $directoryGroup->mail];
                $plan->memberships[$ref] = self::sorted($members);
                continue;
            }
            $idpMembers = self::idpMembers($current);
            // Linked users missing in the listing keep their memberships until they are listed again
            foreach ($idpMembers as $username) {
                if (isset($state->accounts[$username]) && !isset($matched[$username])) {
                    $members[] = $username;
                }
            }
            $members = self::sorted($members);
            $removed = array_diff($idpMembers, $members);
            if (!empty($removed) || !empty(array_diff($members, array_map('strval', array_keys($current['members']))))) {
                $plan->memberships[$ref] = $members;
                $plan->membershipRemovals += count($removed);
            }
            if ($directoryGroup->name !== $current['name']) {
                $taken = $names[$current['domain']][mb_strtolower($directoryGroup->name)] ?? null;
                if ($taken !== null && $taken !== (int)$current['id']) {
                    self::conflict($plan, 'IdP group ' . $current['name'] . ' was renamed to ' . $directoryGroup->name . ', which exists in ' . $current['domain']);
                } else {
                    $plan->groupRenames[(int)$current['id']] = $directoryGroup->name;
                    $names[$current['domain']][mb_strtolower($directoryGroup->name)] = (int)$current['id'];
                }
            }
        }
        foreach ($linked as $ref => $group) {
            if (!isset($groups[$ref])) {
                $plan->orphanedGroups[] = (int)$group['id'];
                $plan->membershipRemovals += count(self::idpMembers($group));
            }
        }
    }

    /**
     * Template of a new mailbox: the template of its highest-priority linked group (as in TemplateService), else
     * the attribute mapping, else the default template.
     *
     * @param array<string, DirectoryUser> $candidates
     * @param array<string, DirectoryGroup> $groups listed groups, [] without group sync
     */
    private function planCreates(Settings $settings, array $candidates, array $groups, State $state, SyncPlan $plan): void
    {
        $linked = [];
        foreach ($state->groups as $group) {
            if ($group['idp_ref'] !== null && $group['template'] !== null && $group['active'] === 1) {
                $linked[(string)$group['idp_ref']] = $group;
            }
        }
        $groupsOf = [];
        foreach ($groups as $ref => $directoryGroup) {
            if (isset($linked[(string)$ref])) {
                foreach ($directoryGroup->memberIds as $id) {
                    $groupsOf[$id][] = $linked[(string)$ref];
                }
            }
        }
        foreach ($candidates as $email => $user) {
            $best = null;
            foreach ($groupsOf[$user->id] ?? [] as $group) {
                if ($best === null || $group['priority'] > $best['priority'] || ($group['priority'] === $best['priority'] && $group['id'] < $best['id'])) {
                    $best = $group;
                }
            }
            $template = $best['template'] ?? $settings->mappedTemplate($user->templateValues)
                ?? (($settings->defaultTemplate !== '') ? $settings->defaultTemplate : null);
            if ($template === null) {
                self::conflict($plan, 'No template matches ' . $email);
                continue;
            }
            $plan->creates[] = ['email' => (string)$email, 'name' => self::name($user->name, ''), 'template' => $template, 'id' => $user->id];
        }
    }

    /** @param array<string, DirectoryUser> $matched */
    private function planStates(Settings $settings, State $state, array $matched, SyncPlan $plan): void
    {
        foreach ($state->accounts as $username => $account) {
            $username = (string)$username;
            $user = $matched[$username] ?? null;
            if ($user === null && $account->missingSince === null) {
                $plan->missing[] = $username;
                continue;
            }
            if ($user !== null && $account->missingSince !== null) {
                $plan->found[] = $username;
            }
            if (!$settings->offboarding) {
                continue;
            }
            // Second complete run without the user counts as removed from the IdP
            $disabled = ($user === null) || !$user->enabled;
            if ($disabled && !$account->idpDisabled && $account->active === 1) {
                $plan->deactivate[] = $username;
            } elseif (!$disabled && $account->idpDisabled) {
                if ($account->active === 2) {
                    $plan->restore[] = $username;
                } else {
                    $plan->release[] = $username;
                }
            }
        }
    }

    /**
     * Name and template of linked mailboxes, like the IdP login does. The planned template is the legacy mapping;
     * SyncService applies the effective template again after the group changes of the run.
     *
     * @param array<string, DirectoryUser> $matched
     */
    private function planUpdates(Settings $settings, State $state, array $matched, SyncPlan $plan): void
    {
        foreach ($matched as $username => $user) {
            $account = $state->accounts[$username];
            $legacy = $settings->mappedTemplate($user->templateValues);
            $plan->profiles[$username] = ['name' => self::name($user->name, $account->name), 'template' => $legacy];
            $template = $state->effectiveTemplates[$username] ?? $legacy;
            if ($template === null) {
                continue;
            }
            if (!isset($state->templateHashes[$template])) {
                self::conflict($plan, 'Template ' . $template . ' does not exist');
                continue;
            }
            $name = self::name($user->name, $account->name);
            if ($name !== $account->name || $state->templateHashes[$template] !== $account->attributeHash) {
                $plan->updates[] = ['username' => (string)$username, 'name' => $name, 'template' => $legacy];
            }
        }
    }

    /** Domain for a new group: the domain of its mail if it is a mailcow domain, else the default group domain */
    private static function groupDomain(Settings $settings, DirectoryGroup $group, array $domains): ?string
    {
        $domain = ($group->mail !== null) ? substr((string)strrchr($group->mail, '@'), 1) : '';
        if (in_array($domain, $domains, true)) {
            return $domain;
        }
        return in_array($settings->groupDefaultDomain, $domains, true) ? $settings->groupDefaultDomain : null;
    }

    /** Mailbox name as mailbox_from_template stores it; an empty IdP name keeps the current one */
    private static function name(string $name, string $current): string
    {
        $name = ltrim(rtrim(trim($name), '>'), '<');
        return ($name === '') ? $current : $name;
    }

    private static function limit(int $base, int $percent): int
    {
        return max(self::GUARD_MIN, (int)ceil($base * $percent / 100));
    }

    /** @return string[] members of a state group with source idp */
    private static function idpMembers(array $group): array
    {
        return array_map('strval', array_keys(array_filter($group['members'], fn(string $source): bool => $source === 'idp')));
    }

    private static function sorted(array $usernames): array
    {
        $usernames = array_values(array_unique(array_map('strval', $usernames)));
        sort($usernames);
        return $usernames;
    }

    private static function conflict(SyncPlan $plan, string $message): void
    {
        if (!in_array($message, $plan->conflicts, true)) {
            $plan->conflicts[] = $message;
        }
    }
}
