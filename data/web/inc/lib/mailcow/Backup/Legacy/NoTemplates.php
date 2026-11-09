<?php

declare(strict_types=1);

namespace Mailcow\Backup\Legacy;

use Mailcow\MailboxGroup\Legacy\LegacyMailbox;

/**
 * The group module's bridge to the mailbox code for restores: a membership restored from a backup never applies the
 * group's mailbox template (the mailbox gets its settings from the backup).
 */
class NoTemplates extends LegacyMailbox
{
    public function applyTemplate(string $username, string $name, string $template): ?string
    {
        return null;
    }
}
