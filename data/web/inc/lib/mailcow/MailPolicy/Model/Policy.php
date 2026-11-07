<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy\Model;

/**
 * A mail policy (table `mail_policy`): whom its mailboxes and groups may send to and receive from.
 *
 * Each direction has a mode: `all` (only the blocked entries are refused), `internal` (the own domain and its
 * alias domains, plus the allowed entries) or `list` (only the allowed entries). Blocked entries are always
 * refused. Entries are addresses, domains or `group:<id>`. A sender counts as internal only when it is logged in
 * to this mailcow (SASL) with a mailbox of the domain, never because of its address.
 */
class Policy
{
    public const MODES = ['all', 'internal', 'list'];

    public ?int $id = null;
    public string $domain = '';
    public string $name = '';
    public string $description = '';
    public string $sendMode = 'all';
    /** @var string[] */
    public array $sendAllow = [];
    /** @var string[] */
    public array $sendBlock = [];
    public string $receiveMode = 'all';
    /** @var string[] */
    public array $receiveAllow = [];
    /** @var string[] */
    public array $receiveBlock = [];
    /** Text of the rejection, '' = default text */
    public string $rejectText = '';
    public int $active = 1;
    /** @var string[] usernames the policy is assigned to */
    public array $mailboxes = [];
    /** @var int[] groups the policy is assigned to: their members and their address */
    public array $groups = [];
    public ?string $created = null;
    public ?string $modified = null;

    /** Row of `mail_policy`; assignments are set by the repository */
    public static function fromRow(array $row): self
    {
        $policy = new self();
        $policy->id = (int)$row['id'];
        $policy->domain = (string)$row['domain'];
        $policy->name = (string)$row['name'];
        $policy->description = (string)($row['description'] ?? '');
        $policy->sendMode = (string)$row['send_mode'];
        $policy->sendAllow = self::list($row['send_allow'] ?? null);
        $policy->sendBlock = self::list($row['send_block'] ?? null);
        $policy->receiveMode = (string)$row['receive_mode'];
        $policy->receiveAllow = self::list($row['receive_allow'] ?? null);
        $policy->receiveBlock = self::list($row['receive_block'] ?? null);
        $policy->rejectText = (string)($row['reject_text'] ?? '');
        $policy->active = (int)$row['active'];
        $policy->created = $row['created'] ?? null;
        $policy->modified = $row['modified'] ?? null;
        return $policy;
    }

    /** Column values for `mail_policy` */
    public function toRow(): array
    {
        return [
            'domain' => $this->domain,
            'name' => $this->name,
            'description' => $this->description,
            'send_mode' => $this->sendMode,
            'send_allow' => json_encode($this->sendAllow),
            'send_block' => json_encode($this->sendBlock),
            'receive_mode' => $this->receiveMode,
            'receive_allow' => json_encode($this->receiveAllow),
            'receive_block' => json_encode($this->receiveBlock),
            'reject_text' => $this->rejectText,
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
            'send_mode' => $this->sendMode,
            'send_allow' => $this->sendAllow,
            'send_block' => $this->sendBlock,
            'receive_mode' => $this->receiveMode,
            'receive_allow' => $this->receiveAllow,
            'receive_block' => $this->receiveBlock,
            'reject_text' => $this->rejectText,
            'active' => $this->active,
            'mailboxes' => $this->mailboxes,
            'groups' => $this->groups,
            'created' => $this->created,
            'modified' => $this->modified,
        ];
    }

    /** @return string[] entries of a JSON list column */
    private static function list(mixed $json): array
    {
        $list = is_string($json) ? json_decode($json, true) : null;
        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }
}
