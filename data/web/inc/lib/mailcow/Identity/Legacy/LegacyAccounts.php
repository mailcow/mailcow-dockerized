<?php

declare(strict_types=1);

namespace Mailcow\Identity\Legacy;

use Mailcow\MailboxGroup\Legacy\LegacyMailbox;

/**
 * Bridge to the procedural mailbox code for the IdP sync: creating mailboxes, applying templates.
 */
class LegacyAccounts extends LegacyMailbox
{
    /**
     * Creates a mailbox of the IdP from a template, like the IdP login does.
     *
     * @return ?string null on success, else the message key of the legacy error
     */
    public function createMailbox(string $email, string $name, string $authsource, string $template): ?string
    {
        return $this->asSystem(fn(): mixed => $this->callCreate($email, $name, $authsource, $template));
    }

    protected function callCreate(string $email, string $name, string $authsource, string $template): mixed
    {
        [$local, $domain] = explode('@', $email, 2);
        return mailbox('add', 'mailbox_from_template', ['domain' => $domain, 'local_part' => $local, 'name' => $name,
            'authsource' => $authsource, 'template' => $template]);
    }
}
