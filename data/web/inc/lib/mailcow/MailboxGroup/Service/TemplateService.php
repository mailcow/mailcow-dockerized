<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Service;

use Mailcow\MailboxGroup\Legacy\LegacyMailbox;
use Mailcow\MailboxGroup\Repository\LookupRepository;

/**
 * Group templates: the effective template of a mailbox is the one of its active group with the highest
 * priority. It is applied only when it changes, so manual settings of a mailbox stay until then.
 * A mailbox that leaves all groups keeps its settings.
 */
class TemplateService
{
    public function __construct(private LookupRepository $lookup, private LegacyMailbox $legacy)
    {
    }

    /** @return array<string, ?string> username => effective group template */
    public function effective(array $usernames): array
    {
        return $this->lookup->effectiveTemplates(array_values(array_unique($usernames)));
    }

    /**
     * Applies the effective template to the mailboxes whose effective template differs from $before
     * (a snapshot of effective() taken before the change).
     *
     * @return array[] warnings (msg values)
     */
    public function applyChanged(array $before): array
    {
        $changed = [];
        foreach ($this->effective(array_keys($before)) as $username => $template) {
            if ($template !== null && $template !== $before[$username]) {
                $changed[$username] = $template;
            }
        }
        return $this->apply($changed);
    }

    /** Applies the effective templates again, e.g. after manual changes (admin action) */
    public function applyTo(array $usernames): array
    {
        return $this->apply(array_filter($this->effective($usernames), fn(?string $template): bool => $template !== null));
    }

    /** A template was edited: apply it to the mailboxes it is the effective template of */
    public function onTemplateChanged(int $templateId): array
    {
        $name = $this->lookup->templateName($templateId);
        if ($name === null) {
            return [];
        }
        return $this->apply(array_filter($this->effective($this->lookup->membersWithTemplate($templateId)),
            fn(?string $template): bool => $template === $name));
    }

    /** Template for IdP logins and syncs: the effective group template, else the legacy attribute mapping */
    public function templateForLogin(string $username, ?string $legacy): ?string
    {
        return $this->effective([$username])[$username] ?? $legacy;
    }

    /**
     * @param array<string, string> $templates username => template
     * @return array[] warnings (msg values)
     */
    private function apply(array $templates): array
    {
        $warnings = [];
        $names = $this->lookup->mailboxNames(array_keys($templates));
        foreach ($templates as $username => $template) {
            $error = $this->legacy->applyTemplate((string)$username, (string)($names[$username] ?? ''), $template);
            if ($error !== null) {
                $warnings[] = ['group_template_failed', htmlspecialchars($template), htmlspecialchars((string)$username)];
            }
        }
        return $warnings;
    }
}
