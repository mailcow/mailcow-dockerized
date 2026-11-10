<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Service;

use Mailcow\MailboxGroup\Repository\GroupRepository;

/**
 * Keeps groups consistent with account changes made by the legacy code (functions.mailbox.inc.php).
 * No permission checks: the legacy caller already checked them.
 */
class AccountHooks
{
    public function __construct(private GroupRepository $repo, private ChangeEffects $effects)
    {
    }

    /** Before a mailbox is deleted: its memberships and the grants on its address go */
    public function onMailboxDeleted(string $username): void
    {
        $ids = $this->repo->groupIdsOfAccount($username);
        if (!empty($ids)) {
            // The deleted mailbox itself needs no SOGo refresh
            $this->effects->apply(array_diff($this->repo->removeAccount($username, $ids), [$username]), []);
        }
    }

    /**
     * Inside the legacy mailbox rename transaction (FOREIGN_KEY_CHECKS = 0): members and grants follow.
     * Must not catch, so the caller can roll back. The legacy rename refreshes SOGo itself.
     */
    public function onMailboxRenamed(string $old, string $new): void
    {
        $ids = $this->repo->groupIdsOfAccount($old);
        if (!empty($ids)) {
            $this->repo->renameAccount($old, $new, $ids);
        }
    }

    /**
     * After a domain or alias domain was deleted: grants on its addresses go. Its groups went via FK cascade,
     * the sogo_acl rows of their SOGo grants go here.
     */
    public function onDomainDeleted(string $domain): void
    {
        $ids = $this->repo->groupIdsWithSendAsIn($domain);
        $users = empty($ids) ? [] : $this->repo->removeSendAsIn($domain, $ids);
        $this->repo->cleanupSogoAcl();
        $this->effects->apply($users, []);
    }

    /** An alias was deleted: grants on its address go */
    public function onAddressDeleted(string $address): void
    {
        $ids = $this->repo->groupIdsOfAccount($address);
        if (!empty($ids)) {
            $this->effects->apply($this->repo->removeSendAs($address, $ids), []);
        }
    }

    /** An alias address changed: grants follow */
    public function onAddressRenamed(string $old, string $new): void
    {
        $ids = $this->repo->groupIdsOfAccount($old);
        if (!empty($ids) && $old !== $new) {
            $this->effects->apply($this->repo->renameAccount($old, $new, $ids), []);
        }
    }
}
