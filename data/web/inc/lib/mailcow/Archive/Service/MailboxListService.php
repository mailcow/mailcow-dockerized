<?php

declare(strict_types=1);

namespace Mailcow\Archive\Service;

use Mailcow\Archive\Model\Mailbox;
use Mailcow\Archive\Repository\LookupRepository;
use Mailcow\Archive\Repository\SettingsRepository;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;

/**
 * The mailboxes archived now (GET archive/mailboxes), the list archivecow keeps its mailboxes in line with.
 * Same rule as the owners of a mail: the global switch, then the mailbox setting, 'inherit' follows the domain.
 */
class MailboxListService
{
    public function __construct(private LookupRepository $lookup, private SettingsRepository $settings, private Context $context)
    {
    }

    /**
     * Admins, read-only API keys included.
     *
     * @return array{enabled: int, mailboxes: array[]}
     * @throws AccessDenied
     */
    public function archived(): array
    {
        $this->context->requireAdmin(false);
        $enabled = $this->settings->load()->enabled;
        return [
            'enabled' => (int)$enabled,
            'mailboxes' => $enabled ? array_map(fn(Mailbox $mailbox): array => $mailbox->toArray(), $this->lookup->archivedMailboxes()) : [],
        ];
    }
}
