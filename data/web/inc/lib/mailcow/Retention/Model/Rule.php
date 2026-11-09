<?php

declare(strict_types=1);

namespace Mailcow\Retention\Model;

/**
 * A retention rule (table `retention_rule`): mail is deleted from its folders once it was saved there `days` days
 * ago. Folders are special folders with all their names (trash: Trash, Papierkorb, Deleted Items, ...), the INBOX,
 * all folders, or own folder names (`folder:<name>`, wildcard *). A rule applies to all mailboxes of its domain or
 * to mailboxes and groups of it. In mode `simulate` the nightly run only counts what it would delete.
 */
class Rule
{
    /** Special folders, the INBOX and all folders; own folders are folder:<name> */
    public const FOLDERS = ['trash', 'junk', 'sent', 'drafts', 'archive', 'inbox', 'all'];
    public const MODES = ['simulate', 'delete'];
    public const OWN_FOLDER = 'folder:';

    public ?int $id = null;
    public string $domain = '';
    public string $name = '';
    public string $description = '';
    /** @var string[] keys of FOLDERS and folder:<name> */
    public array $folders = [];
    public int $days = 30;
    /** Flagged mail stays */
    public int $keepFlagged = 0;
    public string $mode = 'simulate';
    /** Applies to all mailboxes of the domain */
    public int $allMailboxes = 0;
    public int $active = 1;
    /** @var string[] usernames the rule is assigned to */
    public array $mailboxes = [];
    /** @var int[] groups the rule is assigned to (their members of the rule's domain) */
    public array $groups = [];
    public ?string $created = null;
    public ?string $modified = null;

    /** Row of `retention_rule`; assignments are set by the repository */
    public static function fromRow(array $row): self
    {
        $rule = new self();
        $rule->id = (int)$row['id'];
        $rule->domain = (string)$row['domain'];
        $rule->name = (string)$row['name'];
        $rule->description = (string)($row['description'] ?? '');
        $folders = is_string($row['folders'] ?? null) ? json_decode($row['folders'], true) : null;
        $rule->folders = is_array($folders) ? array_values(array_filter($folders, 'is_string')) : [];
        $rule->days = (int)$row['days'];
        $rule->keepFlagged = (int)$row['keep_flagged'];
        $rule->mode = (string)$row['mode'];
        $rule->allMailboxes = (int)$row['all_mailboxes'];
        $rule->active = (int)$row['active'];
        $rule->created = $row['created'] ?? null;
        $rule->modified = $row['modified'] ?? null;
        return $rule;
    }

    /** Column values for `retention_rule` */
    public function toRow(): array
    {
        return [
            'domain' => $this->domain,
            'name' => $this->name,
            'description' => $this->description,
            'folders' => json_encode($this->folders),
            'days' => $this->days,
            'keep_flagged' => $this->keepFlagged,
            'mode' => $this->mode,
            'all_mailboxes' => $this->allMailboxes,
            'active' => $this->active,
        ];
    }

    /** Entry of the API and the edit page */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'name' => $this->name,
            'description' => $this->description,
            'folders' => $this->folders,
            'days' => $this->days,
            'keep_flagged' => $this->keepFlagged,
            'mode' => $this->mode,
            'all_mailboxes' => $this->allMailboxes,
            'active' => $this->active,
            'mailboxes' => $this->mailboxes,
            'groups' => $this->groups,
            'created' => $this->created,
            'modified' => $this->modified,
        ];
    }
}
