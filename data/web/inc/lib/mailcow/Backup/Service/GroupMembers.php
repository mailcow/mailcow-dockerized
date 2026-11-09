<?php

declare(strict_types=1);

namespace Mailcow\Backup\Service;

use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\MailboxGroup\Repository\LookupRepository;
use Mailcow\MailboxGroup\Service\ChangeEffects;
use Mailcow\MailboxGroup\Service\GroupService;
use Mailcow\MailboxGroup\Service\SharingService;
use Mailcow\MailboxGroup\Service\TemplateService;

/**
 * Group writes of a restore through the group module (addresses, send-as rights, shares and caches follow), as the
 * requester of the restore: an admin, or the domain admin with his rights now (GroupService::canEdit). The module is
 * wired without group templates (Legacy\NoTemplates).
 */
class GroupMembers
{
    public function __construct(private GroupRepository $repo, private LookupRepository $lookup, private TemplateService $templates,
        private ChangeEffects $effects, private SharingService $sharing)
    {
    }

    /** @throws UserError e.g. access_denied when the group is read-only for the requester */
    public function add(Context $context, int $groupId, string $username): void
    {
        $this->service($context)->update($groupId, ['add_members' => [$username]]);
    }

    /**
     * Creates a group (restore of a deleted domain); members follow with the mailboxes.
     *
     * @throws UserError
     */
    public function create(Context $context, array $input): int
    {
        return (int)$this->service($context)->create($input)->id;
    }

    protected function service(Context $context): GroupService
    {
        return new GroupService($this->repo, $this->lookup, $this->templates, $this->effects, $this->sharing, $context);
    }
}
