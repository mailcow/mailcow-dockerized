<?php

declare(strict_types=1);

namespace Mailcow\Scim\Legacy;

use Mailcow\MailboxGroup\Legacy\LegacyMailbox;

/**
 * Bridge to the procedural mailbox code for SCIM: creating, editing and renaming mailboxes, applying templates.
 * Each method returns null on success, else the message key of the legacy error.
 *
 * mailbox('edit') reports errors per mailbox and still returns true, so a call counts as failed as soon as it
 * left a danger message (asSystem() starts every call with empty messages).
 */
class LegacyMailboxes extends LegacyMailbox
{
    public function create(string $localPart, string $domain, string $name, int $active, ?string $template): ?string
    {
        return $this->asSystem(fn(): bool => $this->callCreate($localPart, $domain, $name, $active, $template) !== false && !self::failed());
    }

    /** @param array $fields mailbox attributes to change, e.g. ['name' => ..., 'active' => 2] */
    public function edit(string $username, array $fields): ?string
    {
        return $this->asSystem(fn(): bool => $this->callEdit($username, $fields) !== false && !self::failed());
    }

    /** Renames a mailbox within its domain (mailbox('edit', 'mailbox_rename')), without alias for the old address */
    public function rename(string $username, string $newUsername): ?string
    {
        return $this->asSystem(fn(): bool => $this->callRename($username, $newUsername) !== false && !self::failed());
    }

    public function addAlias(string $address, string $goto, string $comment): ?string
    {
        return $this->asSystem(fn(): bool => $this->callAddAlias($address, $goto, $comment) !== false && !self::failed());
    }

    public function deleteAlias(int $id): ?string
    {
        return $this->asSystem(fn(): bool => $this->callDeleteAlias($id) !== false && !self::failed());
    }

    public function applyTemplate(string $username, string $name, string $template): ?string
    {
        return $this->asSystem(fn(): bool => $this->callTemplate($username, $name, $template) !== false && !self::failed());
    }

    protected function callCreate(string $localPart, string $domain, string $name, int $active, ?string $template): mixed
    {
        $data = ['domain' => $domain, 'local_part' => $localPart, 'name' => $name, 'authsource' => 'scim', 'active' => $active];
        if ($template !== null) {
            return mailbox('add', 'mailbox_from_template', $data + ['template' => $template]);
        }
        return mailbox('add', 'mailbox', $data + ['password' => '', 'password2' => '']);
    }

    protected function callRename(string $username, string $newUsername): mixed
    {
        $at = strrpos($username, '@');
        return mailbox('edit', 'mailbox_rename', ['domain' => substr($username, $at + 1), 'old_local_part' => substr($username, 0, $at),
            'new_local_part' => substr($newUsername, 0, strrpos($newUsername, '@')), 'create_alias' => 0]);
    }

    protected function callAddAlias(string $address, string $goto, string $comment): mixed
    {
        return mailbox('add', 'alias', ['address' => $address, 'goto' => $goto, 'active' => 1, 'sogo_visible' => 1, 'private_comment' => $comment]);
    }

    protected function callDeleteAlias(int $id): mixed
    {
        return mailbox('delete', 'alias', ['id' => [$id]]);
    }

    protected function callEdit(string $username, array $fields): mixed
    {
        return mailbox('edit', 'mailbox', ['username' => [$username]] + $fields);
    }

    private static function failed(): bool
    {
        foreach ($_SESSION['return'] ?? [] as $message) {
            if (($message['type'] ?? '') === 'danger') {
                return true;
            }
        }
        return false;
    }
}
