<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Model;

/**
 * A mailbox group (table `mailbox_group`) with its members and send-as grants.
 *
 * The group tables are the source of truth. GroupRepository::rebuild() derives the rows other services read:
 * the alias row of the distribution address and the sender_acl rows of the grants, both tagged with `group_id`.
 * Not to be confused with SOGo resources of kind 'group' (`mailbox.kind`).
 */
class Group
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_IDP = 'idp';

    public ?int $id = null;
    public string $domain = '';
    public string $name = '';
    public string $description = '';
    /** Distribution address, null = group only used for grants and templates */
    public ?string $address = null;
    /** Members may send as the address */
    public int $senderAllowed = 0;
    public int $sogoVisible = 0;
    /** Only senders of the domain may write to the address */
    public int $internal = 0;
    public ?int $templateId = null;
    /** Template name, read only */
    public ?string $template = null;
    public int $priority = 0;
    /** IdP group id, set = IdP memberships are synced */
    public ?string $idpRef = null;
    public int $active = 1;
    public ?string $created = null;
    public ?string $modified = null;
    /** @var array<string, string> username => source */
    public array $members = [];
    /** @var string[] addresses or @domain */
    public array $sendAs = [];
    /** @var array[] {owner, rights, subscribe, state, last_error}: members may open these mailboxes (ACL group=mcg_<id>) */
    public array $imapGrants = [];
    /** @var array[] {owner, type, folder, access, subscribe, state, last_error}: SOGo folders shared with the members */
    public array $sogoGrants = [];
    /** @var array[] warnings of the last write (msg values), not stored */
    public array $warnings = [];

    /** Builds a group from a `mailbox_group` row (`template_name` optional) */
    public static function fromRow(array $row): self
    {
        $group = new self();
        $group->id = isset($row['id']) ? (int)$row['id'] : null;
        $group->domain = (string)($row['domain'] ?? '');
        $group->name = (string)($row['name'] ?? '');
        $group->description = (string)($row['description'] ?? '');
        $group->address = isset($row['address']) ? (string)$row['address'] : null;
        $group->senderAllowed = (int)($row['sender_allowed'] ?? 0);
        $group->sogoVisible = (int)($row['sogo_visible'] ?? 0);
        $group->internal = (int)($row['internal'] ?? 0);
        $group->templateId = isset($row['template_id']) ? (int)$row['template_id'] : null;
        $group->template = isset($row['template_name']) ? (string)$row['template_name'] : null;
        $group->priority = (int)($row['priority'] ?? 0);
        $group->idpRef = isset($row['idp_ref']) ? (string)$row['idp_ref'] : null;
        $group->active = (int)($row['active'] ?? 1);
        $group->created = isset($row['created']) ? (string)$row['created'] : null;
        $group->modified = isset($row['modified']) ? (string)$row['modified'] : null;
        return $group;
    }

    /** @return string[] */
    public function usernames(): array
    {
        return array_keys($this->members);
    }

    /** API representation (GET mailbox-group) */
    public function toArray(bool $canEdit): array
    {
        $members = [];
        foreach ($this->members as $username => $source) {
            $members[] = ['username' => (string)$username, 'source' => $source];
        }
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'sender_allowed' => $this->senderAllowed,
            'sogo_visible' => $this->sogoVisible,
            'internal' => $this->internal,
            'template_id' => $this->templateId,
            'template' => $this->template,
            'priority' => $this->priority,
            'idp_ref' => $this->idpRef,
            'members' => $members,
            'send_as' => $this->sendAs,
            'imap_grants' => $this->imapGrants,
            'sogo_grants' => $this->sogoGrants,
            'active' => $this->active,
            'created' => $this->created,
            'modified' => $this->modified,
            'can_edit' => $canEdit,
        ];
    }
}
