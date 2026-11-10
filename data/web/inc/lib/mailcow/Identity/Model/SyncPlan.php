<?php

declare(strict_types=1);

namespace Mailcow\Identity\Model;

/**
 * What one sync run changes. Built by SyncPlanner from a listing and the current state, applied by SyncService.
 */
class SyncPlan
{
    /** Details per kind kept in the stored summary */
    private const DETAILS = 50;

    /** @var array<string, string> username => external id (adopted by email or relinked) */
    public array $links = [];
    /** @var array[] {email, name, template, id}: mailboxes to create */
    public array $creates = [];
    /** @var string[] usernames set to login disabled (active = 2) */
    public array $deactivate = [];
    /** @var string[] usernames the sync had disabled and the IdP enabled again (active = 1) */
    public array $restore = [];
    /** @var string[] usernames whose idp_disabled marker is dropped, active stays (an admin changed it) */
    public array $release = [];
    /** @var string[] usernames not listed for the first time (missing_since is set) */
    public array $missing = [];
    /** @var string[] usernames listed again (missing_since is cleared) */
    public array $found = [];
    /** @var array[] {username, name, template}: existing mailboxes whose name/template is synced; template = legacy mapping */
    public array $updates = [];
    /** @var array<string, array> username => {name, template}: IdP name and legacy mapping of every listed mailbox */
    public array $profiles = [];
    /** @var array[] {ref, name, domain, mail}: groups to create for IdP groups */
    public array $groupCreates = [];
    /** @var array<int, string> group id => new name */
    public array $groupRenames = [];
    /** @var array<string, string[]> group ref => usernames of its IdP members */
    public array $memberships = [];
    /** @var int[] linked groups no longer listed; their IdP members are removed */
    public array $orphanedGroups = [];
    /** Number of IdP memberships the plan removes (for the guard) */
    public int $membershipRemovals = 0;
    /** @var string[] situations an admin has to resolve */
    public array $conflicts = [];
    /** Users without email, not in a mailcow domain or without template (not created) */
    public int $skipped = 0;
    /** The guard stopped deactivations and membership removals */
    public bool $blocked = false;

    /** Stored with the run and shown in the UI */
    public function summary(): array
    {
        $summary = ['counts' => [], 'details' => []];
        foreach (['links' => 'links', 'creates' => 'creates', 'deactivate' => 'deactivate', 'restore' => 'restore',
            'release' => 'release', 'missing' => 'missing', 'found' => 'found', 'updates' => 'updates', 'group_creates' => 'groupCreates',
            'group_renames' => 'groupRenames', 'memberships' => 'memberships', 'orphaned_groups' => 'orphanedGroups',
            'conflicts' => 'conflicts'] as $key => $kind) {
            $summary['counts'][$key] = count($this->$kind);
        }
        $summary['counts']['membership_removals'] = $this->membershipRemovals;
        $summary['counts']['skipped'] = $this->skipped;
        $summary['details'] = [
            'creates' => array_slice(array_column($this->creates, 'email'), 0, self::DETAILS),
            'deactivate' => array_slice($this->deactivate, 0, self::DETAILS),
            'restore' => array_slice($this->restore, 0, self::DETAILS),
            'missing' => array_slice($this->missing, 0, self::DETAILS),
            'group_creates' => array_slice(array_column($this->groupCreates, 'name'), 0, self::DETAILS),
            'conflicts' => array_slice($this->conflicts, 0, self::DETAILS),
        ];
        $summary['blocked'] = $this->blocked;
        return $summary;
    }
}
