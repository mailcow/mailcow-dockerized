<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Service;

use Mailcow\Dovecot\Service\DovecotService;
use Mailcow\MailboxGroup\Model\Group;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\Sogo\Service\SogoService;

/**
 * Mailbox access of group members (shared mailboxes, calendars, address books).
 *
 * IMAP: a grant is the Dovecot ACL group=mcg_<id> on every folder of the owner; members get the group through
 * the userdb field acl_groups, so membership changes need no ACL change. Dovecot is reached through dockerapi
 * after the commit; subfolders created later inherit the ACL, new top-level folders and failed grants are
 * handled by reconcile() (cron).
 * SOGo: sogo_acl rows per member, maintained by GroupRepository::rebuild(); missing personal folders of owners that
 * never logged in to SOGo (shared mailboxes) are created with sogo-tool init-user.
 * Grants with `subscribe` are subscribed for each member once by syncSubscriptions(); a member that removes the
 * subscription keeps it removed, members that lose the access are unsubscribed.
 */
class SharingService
{
    /** Dovecot rights per grant level */
    public const IMAP_RIGHTS = [
        'read' => ['lookup', 'read', 'write-seen'],
        'write' => ['lookup', 'read', 'write-seen', 'write', 'write-deleted', 'insert', 'post', 'expunge', 'create'],
        'full' => ['lookup', 'read', 'write-seen', 'write', 'write-deleted', 'insert', 'post', 'expunge', 'create', 'delete'],
    ];

    public function __construct(private GroupRepository $repo, private DovecotService $dovecot, private SogoService $sogo,
        private ChangeEffects $effects)
    {
    }

    /** Group name in the userdb field acl_groups (dovecot docker-entrypoint.sh) */
    public static function aclGroup(int $groupId): string
    {
        return 'mcg_' . $groupId;
    }

    /**
     * Applies the IMAP grants Dovecot does not have yet (pending, failed, removing); $all also applies the applied
     * ones again. Of one group or all.
     *
     * @return array[] warnings (msg values)
     */
    public function applyImap(?int $groupId, bool $all = false): array
    {
        $warnings = [];
        foreach ($this->repo->imapGrantsToApply($groupId, $all) as $grant) {
            $removing = ($grant['state'] === 'removing');
            $error = $this->dovecot->setAclAll((string)$grant['owner'], 'group=' . self::aclGroup((int)$grant['group_id']),
                $removing ? [] : self::IMAP_RIGHTS[$grant['rights']]);
            if ($error === null && $removing) {
                $this->repo->deleteImapGrant((int)$grant['id']);
                continue;
            }
            if ($error === null) {
                if ($grant['state'] !== 'applied' || $grant['last_error'] !== null) {
                    $this->repo->setImapState((int)$grant['id'], 'applied', null);
                }
                continue;
            }
            $this->repo->setImapState((int)$grant['id'], $removing ? 'removing' : 'failed', $error);
            $warnings[] = ['group_imap_grant_failed', htmlspecialchars((string)$grant['owner'])];
        }
        return $warnings;
    }

    /** After a group write: creates missing SOGo folders of its grant owners and applies the pending grants to them */
    public function applySogo(int $groupId): array
    {
        $warnings = $this->initSogoFolders($groupId);
        // null: no grant waits for a folder, warnings: the folders could not be created
        if ($warnings === null || $warnings !== []) {
            return $warnings ?? [];
        }
        return $this->effects->apply($this->repo->rebuild($groupId), []);
    }

    /** Before a group is deleted: its Dovecot ACLs go (best effort, the ACL of a deleted group grants nothing) */
    public function removeImap(Group $group): void
    {
        foreach ($group->imapGrants as $grant) {
            $this->dovecot->setAclAll((string)$grant['owner'], 'group=' . self::aclGroup((int)$group->id), []);
        }
    }

    /**
     * Cron: applies all IMAP grants again (new top-level folders, failed grants), rebuilds the SOGo grants
     * (owners that opened SOGo meanwhile, rows of removed grants) and syncs the SOGo subscriptions.
     *
     * @return array[] warnings (msg values)
     */
    public function reconcile(): array
    {
        $warnings = array_merge($this->applyImap(null, true), $this->initSogoFolders(null) ?? []);
        $users = [];
        foreach ($this->repo->groupIdsWithSogoGrants() as $id) {
            $users = array_merge($users, $this->repo->rebuild($id));
        }
        $this->repo->cleanupSogoAcl();
        $warnings = array_merge($warnings, $this->effects->apply(array_values(array_unique($users)), []));
        return array_merge($warnings, $this->syncSubscriptions());
    }

    /**
     * Unsubscribes members that lost the access to a folder mailcow subscribed them to, then subscribes the members
     * of grants with `subscribe` that have no subscription by mailcow yet. Cheap without pending changes, the cron
     * runs it every minute.
     *
     * @return array[] warnings (msg values)
     */
    public function syncSubscriptions(): array
    {
        $warnings = [];
        $sogoChanged = false;
        foreach (['imap', 'sogo'] as $kind) {
            foreach ($this->repo->staleSubscriptions($kind) as $row) {
                // A deleted member has no subscriptions left, a deleted owner no folders; another group may still grant it
                if ($row['member_exists'] && $row['owner_exists'] && !$row['covered']) {
                    if ($this->subscribe($kind, (string)$row['owner'], (string)$row['folder'], [(string)$row['username']], true) !== null) {
                        $warnings[] = ['group_subscription_failed', htmlspecialchars((string)$row['username'])];
                        continue;
                    }
                    $sogoChanged = $sogoChanged || ($kind === 'sogo');
                }
                $this->repo->deleteSubscription((int)$row['id']);
            }
            foreach ($this->repo->missingSubscriptions($kind) as $grant) {
                if ($this->subscribe($kind, $grant['owner'], $grant['folder'], $grant['usernames']) !== null) {
                    $warnings[] = ['group_subscription_failed', htmlspecialchars(implode(', ', $grant['usernames']))];
                    continue;
                }
                $this->repo->addSubscriptions($kind, $grant['grant_id'], $grant['owner'], $grant['folder'], $grant['usernames']);
                $sogoChanged = $sogoChanged || ($kind === 'sogo');
            }
        }
        if ($sogoChanged) {
            $this->sogo->flushCache();
        }
        return $warnings;
    }

    /** @return ?string null on success, else the error */
    private function subscribe(string $kind, string $owner, string $folder, array $usernames, bool $unsubscribe = false): ?string
    {
        return ($kind === 'imap') ? $this->dovecot->subscribeShared($owner, $usernames, $unsubscribe)
            : $this->sogo->subscribe($owner, $folder, $usernames, $unsubscribe);
    }

    /**
     * Creates the personal SOGo folders of grant owners that never logged in to SOGo, so their pending grants can
     * be applied.
     *
     * @return ?array[] warnings, null if no grant was waiting for a folder
     */
    private function initSogoFolders(?int $groupId): ?array
    {
        $owners = $this->repo->pendingSogoOwners($groupId);
        if (empty($owners)) {
            return null;
        }
        return ($this->sogo->initUsers($owners) === null) ? [] : [['group_sogo_init_failed', htmlspecialchars(implode(', ', $owners))]];
    }
}
