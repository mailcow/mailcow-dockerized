<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Service;

use Mailcow\Dovecot\Service\DovecotService;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\Sogo\Service\SogoService;

/**
 * What follows a committed group write: SOGo identities of the affected members, group templates and the caches
 * of SOGo (ACLs) and Dovecot (acl_groups). Runs after the commit; failures become warnings and never roll the
 * write back.
 */
class ChangeEffects
{
    public function __construct(private TemplateService $templates, private SogoService $sogo, private GroupRepository $repo,
        private DovecotService $dovecot)
    {
    }

    /** Effective templates of the mailboxes a write may touch, taken before the write */
    public function snapshot(array $usernames): array
    {
        return $this->templates->effective($usernames);
    }

    /** @return array[] warnings (msg values) */
    public function apply(array $sogoUsers, array $snapshot): array
    {
        $this->sogo->refreshStaticView($sogoUsers);
        $effects = $this->repo->takeEffects();
        if ($effects['sogo_acl']) {
            $this->sogo->flushCache();
        }
        $this->dovecot->flushAuthCache($effects['members']);
        return $this->templates->applyChanged($snapshot);
    }
}
