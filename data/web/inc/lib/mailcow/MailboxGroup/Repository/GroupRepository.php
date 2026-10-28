<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Repository;

use Mailcow\Core\Context;
use Mailcow\Core\Repository;
use Mailcow\MailboxGroup\Model\Group;
use PDO;

/**
 * SQL for `mailbox_group`, its members and send-as grants, and the rows derived from them (rebuild()).
 * Every write rebuilds the derived rows in the same transaction, so readers never see a half-applied group.
 */
class GroupRepository extends Repository
{
    /** SOGo roles per folder type and access of a SOGo grant */
    public const SOGO_ROLES = [
        'Calendar' => [
            'read' => ['PublicViewer', 'ConfidentialViewer', 'PrivateDAndTViewer'],
            'write' => ['PublicModifier', 'ConfidentialModifier', 'PrivateDAndTViewer', 'ObjectCreator', 'ObjectEraser'],
        ],
        'Contacts' => [
            'read' => ['ObjectViewer'],
            'write' => ['ObjectViewer', 'ObjectCreator', 'ObjectEditor', 'ObjectEraser'],
        ],
    ];

    /** Grant table, folder and extra "access gone" condition of the subscriptions per kind */
    private const SUBSCRIPTION_GRANTS = [
        'imap' => ['table' => 'mailbox_group_imap_grant', 'folder' => "''", 'gone' => " OR g.`state` = 'removing'", 'valid' => " AND g2.`state` != 'removing'"],
        'sogo' => ['table' => 'mailbox_group_sogo_grant', 'folder' => "CONCAT(g.`folder_type`, '/', g.`folder`)", 'gone' => '', 'valid' => ''],
    ];

    /** Side effects of the writes since the last takeEffects(): members whose acl_groups changed, sogo_acl changed */
    private array $effects = ['members' => [], 'sogo_acl' => false];
    /** Members removed by writeMembers(), rebuild() decides whether Dovecot needs to know */
    private array $removedMembers = [];

    public function find(int $id): ?Group
    {
        return $this->fetch('1=1', ['id' => $id]);
    }

    /** Like find(), but only when the group is visible to the caller */
    public function findVisible(int $id, Context $context): ?Group
    {
        [$visible, $params] = $this->visibility($context);
        return $this->fetch($visible, $params + ['id' => $id]);
    }

    public function findByIdpRef(string $ref): ?Group
    {
        $id = $this->query("SELECT `id` FROM `mailbox_group` WHERE `idp_ref` = :ref", ['ref' => $ref])->fetchColumn();
        return ($id === false) ? null : $this->find((int)$id);
    }

    /**
     * @param string[] $domains limit to these domains, [] = all visible
     * @return int[] ids of the groups visible to the caller, ordered by domain and name
     */
    public function visibleIds(Context $context, array $domains = []): array
    {
        [$visible, $params] = $this->visibility($context);
        $sql = "SELECT g.`id` FROM `mailbox_group` g WHERE $visible";
        if (!empty($domains)) {
            $names = [];
            foreach (array_values($domains) as $i => $domain) {
                $names[] = ':dom' . $i;
                $params['dom' . $i] = $domain;
            }
            $sql .= " AND g.`domain` IN (" . implode(', ', $names) . ")";
        }
        return array_map('intval', $this->query($sql . " ORDER BY g.`domain`, g.`name`", $params)->fetchAll(PDO::FETCH_COLUMN));
    }

    public function nameExists(string $domain, string $name, ?int $exceptId = null): bool
    {
        return (bool)$this->query("SELECT 1 FROM `mailbox_group` WHERE `domain` = :domain AND `name` = :name AND `id` != :id",
            ['domain' => $domain, 'name' => $name, 'id' => $exceptId ?? 0])->fetchColumn();
    }

    /** @return string[] usernames whose SOGo identities changed */
    public function insert(Group $group): array
    {
        return $this->transaction(function () use ($group): array {
            $group->id = $this->insertRow('mailbox_group', $this->values($group));
            $this->writeMembers($group);
            $this->writeSendAs($group);
            $this->writeGrants($group);
            return $this->rebuild((int)$group->id);
        });
    }

    /** @return string[] usernames whose SOGo identities changed */
    public function update(Group $group): array
    {
        return $this->transaction(function () use ($group): array {
            $this->updateRow('mailbox_group', (int)$group->id, $this->values($group));
            $this->writeMembers($group);
            $this->writeSendAs($group);
            $this->writeGrants($group);
            return $this->rebuild((int)$group->id);
        });
    }

    /**
     * Deletes the group; members, grants and the derived alias/sender_acl rows go via FK cascade.
     *
     * @return string[] usernames whose SOGo identities changed
     */
    public function delete(int $id): array
    {
        return $this->transaction(function () use ($id): array {
            $before = $this->aliasState($id);
            $this->query("DELETE FROM `mailbox_group` WHERE `id` = :id", ['id' => $id]);
            $this->cleanupSogoAcl();
            return $before['members'];
        });
    }

    /**
     * Rewrites the rows derived from the group: the alias row of its address (goto = members, inactive while the
     * group is inactive or empty, so the address bounces but stays reserved) and one sender_acl row per member
     * and send-as grant, and the sogo_acl rows of its SOGo grants. Idempotent, joins the caller's transaction.
     * $sogo = false keeps the sogo_acl rows (mailbox rename: sogo-tool rename-user updates them afterwards).
     *
     * @return string[] usernames whose SOGo identities changed
     */
    public function rebuild(int $id, bool $sogo = true): array
    {
        return $this->transaction(function () use ($id, $sogo): array {
            $group = $this->query("SELECT * FROM `mailbox_group` WHERE `id` = :id FOR UPDATE", ['id' => $id])->fetch(PDO::FETCH_ASSOC);
            if (!$group) {
                return [];
            }
            $before = $this->aliasState($id);
            $members = $this->query("SELECT `username` FROM `mailbox_group_member` WHERE `group_id` = :id ORDER BY `username`",
                ['id' => $id])->fetchAll(PDO::FETCH_COLUMN);
            $visible = false;
            if ($group['address'] === null) {
                $this->query("DELETE FROM `alias` WHERE `group_id` = :id", ['id' => $id]);
            } else {
                $values = [
                    'address' => $group['address'],
                    'goto' => empty($members) ? 'null@localhost' : implode(',', $members),
                    'domain' => $group['domain'],
                    'public_comment' => $group['description'],
                    'sender_allowed' => (int)$group['sender_allowed'],
                    // SOGo identities only make sense when the members may send as the address
                    'sogo_visible' => ($group['sogo_visible'] == 1 && $group['sender_allowed'] == 1) ? 1 : 0,
                    'internal' => (int)$group['internal'],
                    'active' => ($group['active'] == 1 && !empty($members)) ? 1 : 0,
                ];
                // Never insert by address: an existing foreign row must not be taken over
                $aliasId = $this->query("SELECT `id` FROM `alias` WHERE `group_id` = :id", ['id' => $id])->fetchColumn();
                if ($aliasId === false) {
                    $this->insertRow('alias', $values + ['group_id' => $id]);
                } else {
                    $this->updateRow('alias', (int)$aliasId, $values);
                }
                $visible = ($values['active'] === 1 && $values['sogo_visible'] === 1);
            }
            $this->query("DELETE FROM `sender_acl` WHERE `group_id` = :id", ['id' => $id]);
            if ($group['active'] == 1) {
                $this->query("INSERT INTO `sender_acl` (`logged_in_as`, `send_as`, `external`, `group_id`)
                  SELECT m.`username`, s.`address`, 0, m.`group_id` FROM `mailbox_group_member` m
                    JOIN `mailbox_group_send_as` s ON s.`group_id` = m.`group_id`
                  WHERE m.`group_id` = :id AND m.`username` != s.`address`", ['id' => $id]);
            }
            if ($sogo) {
                $this->rebuildSogoAcl($id, $group['active'] == 1, $members);
            }
            // Dovecot caches acl_groups (userdb) of the members, incl. the ones that just left
            if ($this->query("SELECT 1 FROM `mailbox_group_imap_grant` WHERE `group_id` = :id LIMIT 1", ['id' => $id])->fetchColumn()) {
                $this->effects['members'] = array_merge($this->effects['members'], $members, $this->removedMembers);
            }
            $this->removedMembers = [];
            $after = $visible ? $members : [];
            // Same visible address: only joined and left members are affected
            if ($before['members'] !== [] && $visible && $before['address'] === $group['address']) {
                return array_values(array_merge(array_diff($before['members'], $after), array_diff($after, $before['members'])));
            }
            return array_values(array_unique(array_merge($before['members'], $after)));
        });
    }

    /**
     * Every group as the IdP sync plans with it: {id, domain, name, idp_ref, template, priority, active,
     * members: username => source}
     *
     * @return array[]
     */
    public function syncState(): array
    {
        $members = [];
        foreach ($this->query("SELECT `group_id`, `username`, `source` FROM `mailbox_group_member`")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $members[(int)$row['group_id']][(string)$row['username']] = (string)$row['source'];
        }
        $groups = [];
        foreach ($this->query("SELECT g.`id`, g.`domain`, g.`name`, g.`idp_ref`, t.`template`, g.`priority`, g.`active`
          FROM `mailbox_group` g LEFT JOIN `templates` t ON t.`id` = g.`template_id` ORDER BY g.`id`")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $groups[] = [
                'id' => (int)$row['id'],
                'domain' => (string)$row['domain'],
                'name' => (string)$row['name'],
                'idp_ref' => isset($row['idp_ref']) ? (string)$row['idp_ref'] : null,
                'template' => isset($row['template']) ? (string)$row['template'] : null,
                'priority' => (int)$row['priority'],
                'active' => (int)$row['active'],
                'members' => $members[(int)$row['id']] ?? [],
            ];
        }
        return $groups;
    }

    /** @return int[] groups with this member, a send-as grant on this address or a SOGo grant on this mailbox */
    public function groupIdsOfAccount(string $address): array
    {
        return array_map('intval', $this->query("SELECT `group_id` FROM `mailbox_group_member` WHERE `username` = :a1
          UNION SELECT `group_id` FROM `mailbox_group_send_as` WHERE `address` = :a2
          UNION SELECT `group_id` FROM `mailbox_group_sogo_grant` WHERE `owner` = :a3",
            ['a1' => $address, 'a2' => $address, 'a3' => $address])->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return int[] groups with send-as grants on addresses of the domain (incl. @domain) */
    public function groupIdsWithSendAsIn(string $domain): array
    {
        return array_map('intval', $this->query("SELECT DISTINCT `group_id` FROM `mailbox_group_send_as`
          WHERE SUBSTRING_INDEX(`address`, '@', -1) = :domain", ['domain' => $domain])->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * A mailbox is deleted: its memberships and the grants on its address go, the groups are rebuilt.
     *
     * @param int[] $ids groups of the account (groupIdsOfAccount())
     * @return string[] usernames whose SOGo identities changed
     */
    public function removeAccount(string $username, array $ids): array
    {
        return $this->transaction(function () use ($username, $ids): array {
            $this->query("DELETE FROM `mailbox_group_member` WHERE `username` = :username", ['username' => $username]);
            $this->query("DELETE FROM `mailbox_group_send_as` WHERE `address` = :username", ['username' => $username]);
            return $this->rebuildAll($ids);
        });
    }

    /** An alias was deleted: grants on its address go */
    public function removeSendAs(string $address, array $ids): array
    {
        return $this->transaction(function () use ($address, $ids): array {
            $this->query("DELETE FROM `mailbox_group_send_as` WHERE `address` = :address", ['address' => $address]);
            return $this->rebuildAll($ids);
        });
    }

    /** A domain or alias domain is deleted: grants on its addresses go */
    public function removeSendAsIn(string $domain, array $ids): array
    {
        return $this->transaction(function () use ($domain, $ids): array {
            $this->query("DELETE FROM `mailbox_group_send_as` WHERE SUBSTRING_INDEX(`address`, '@', -1) = :domain", ['domain' => $domain]);
            return $this->rebuildAll($ids);
        });
    }

    /**
     * Account or alias address renamed: members and grants follow. Explicit updates, because the mailbox rename
     * runs with FOREIGN_KEY_CHECKS = 0; joins the caller's transaction.
     */
    public function renameAccount(string $old, string $new, array $ids): array
    {
        return $this->transaction(function () use ($old, $new, $ids): array {
            $this->query("UPDATE `mailbox_group_member` SET `username` = :new WHERE `username` = :old", ['new' => $new, 'old' => $old]);
            $this->query("UPDATE `mailbox_group_send_as` SET `address` = :new WHERE `address` = :old", ['new' => $new, 'old' => $old]);
            return $this->rebuildAll($ids, false);
        });
    }

    /** @return int[] groups with SOGo grants */
    public function groupIdsWithSogoGrants(): array
    {
        return array_map('intval', $this->query("SELECT DISTINCT `group_id` FROM `mailbox_group_sogo_grant`")->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * IMAP grants Dovecot has to apply: pending, failed and removing ones, with $all also applied ones (new
     * top-level folders); of one group or all.
     *
     * @return array[] rows of mailbox_group_imap_grant
     */
    public function imapGrantsToApply(?int $groupId, bool $all): array
    {
        $where = $all ? '1=1' : "`state` != 'applied'";
        $params = [];
        if ($groupId !== null) {
            $where .= " AND `group_id` = :id";
            $params['id'] = $groupId;
        }
        return $this->query("SELECT * FROM `mailbox_group_imap_grant` WHERE $where ORDER BY `id`", $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setImapState(int $grantId, string $state, ?string $error): void
    {
        $this->query("UPDATE `mailbox_group_imap_grant` SET `state` = :state, `last_error` = :error WHERE `id` = :id",
            ['state' => $state, 'error' => $error, 'id' => $grantId]);
    }

    public function deleteImapGrant(int $grantId): void
    {
        $this->query("DELETE FROM `mailbox_group_imap_grant` WHERE `id` = :id", ['id' => $grantId]);
    }

    /**
     * Deletes the sogo_acl rows of grants that are gone, e.g. by FK cascades of groups, owners and domains.
     * Joins the caller's transaction.
     */
    public function cleanupSogoAcl(): void
    {
        $this->transaction(function (): void {
            $orphans = $this->query("SELECT l.`acl_id` FROM `mailbox_group_sogo_acl` l
                LEFT JOIN `mailbox_group_sogo_grant` g ON g.`id` = l.`grant_id`
              WHERE g.`id` IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($orphans as $aclId) {
                $this->deleteSogoAcl((int)$aclId);
            }
        });
    }

    /**
     * Members to subscribe to grants with `subscribe` (kind imap or sogo): applied grants of active groups, members
     * without a subscription by mailcow yet (a member that removes it keeps it removed).
     *
     * @return array[] {grant_id, owner, folder, usernames}
     */
    public function missingSubscriptions(string $kind): array
    {
        $config = self::SUBSCRIPTION_GRANTS[$kind];
        $grants = [];
        foreach ($this->query("SELECT g.`id`, g.`owner`, {$config['folder']} AS `folder`, m.`username`
            FROM `{$config['table']}` g
              JOIN `mailbox_group` mg ON mg.`id` = g.`group_id` AND mg.`active` = 1
              JOIN `mailbox_group_member` m ON m.`group_id` = g.`group_id` AND m.`username` != g.`owner`
              LEFT JOIN `mailbox_group_subscription` s ON s.`kind` = :kind AND s.`grant_id` = g.`id` AND s.`username` = m.`username`
            WHERE g.`subscribe` = 1 AND g.`state` = 'applied' AND s.`id` IS NULL
            ORDER BY g.`id`, m.`username`", ['kind' => $kind])->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grants[$row['id']] ??= ['grant_id' => (int)$row['id'], 'owner' => (string)$row['owner'], 'folder' => (string)$row['folder'], 'usernames' => []];
            $grants[$row['id']]['usernames'][] = (string)$row['username'];
        }
        return array_values($grants);
    }

    /**
     * Subscriptions by mailcow whose member lost the access: grant, membership or group gone or inactive.
     * `covered`: another group still grants the member the same folder, so the subscription stays.
     *
     * @return array[] rows of mailbox_group_subscription with member_exists, owner_exists, covered
     */
    public function staleSubscriptions(string $kind): array
    {
        $config = self::SUBSCRIPTION_GRANTS[$kind];
        return $this->query("SELECT s.`id`, s.`username`, s.`owner`, s.`folder`,
            EXISTS(SELECT 1 FROM `mailbox` WHERE `username` = s.`username`) AS `member_exists`,
            EXISTS(SELECT 1 FROM `mailbox` WHERE `username` = s.`owner`) AS `owner_exists`,
            EXISTS(SELECT 1 FROM `mailbox_group_subscription` s2
                JOIN `{$config['table']}` g2 ON g2.`id` = s2.`grant_id`{$config['valid']}
                JOIN `mailbox_group` mg2 ON mg2.`id` = g2.`group_id` AND mg2.`active` = 1
                JOIN `mailbox_group_member` m2 ON m2.`group_id` = g2.`group_id` AND m2.`username` = s2.`username`
              WHERE s2.`kind` = s.`kind` AND s2.`id` != s.`id` AND s2.`username` = s.`username` AND s2.`owner` = s.`owner`
                AND s2.`folder` = s.`folder`) AS `covered`
          FROM `mailbox_group_subscription` s
            LEFT JOIN `{$config['table']}` g ON g.`id` = s.`grant_id`
            LEFT JOIN `mailbox_group` mg ON mg.`id` = g.`group_id` AND mg.`active` = 1
            LEFT JOIN `mailbox_group_member` m ON m.`group_id` = g.`group_id` AND m.`username` = s.`username`
          WHERE s.`kind` = :kind AND (g.`id` IS NULL OR mg.`id` IS NULL OR m.`username` IS NULL{$config['gone']})
          ORDER BY s.`id`", ['kind' => $kind])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addSubscriptions(string $kind, int $grantId, string $owner, string $folder, array $usernames): void
    {
        foreach ($usernames as $username) {
            $this->query("INSERT IGNORE INTO `mailbox_group_subscription` (`kind`, `grant_id`, `username`, `owner`, `folder`)
              VALUES (:kind, :grant, :username, :owner, :folder)",
                ['kind' => $kind, 'grant' => $grantId, 'username' => $username, 'owner' => $owner, 'folder' => $folder]);
        }
    }

    public function deleteSubscription(int $id): void
    {
        $this->query("DELETE FROM `mailbox_group_subscription` WHERE `id` = :id", ['id' => $id]);
    }

    /**
     * Owners of pending SOGo grants on their personal folder that SOGo knows (active mailboxes), of one group or all.
     * Their folders do not exist because they never logged in to SOGo, e.g. shared mailboxes.
     *
     * @return string[]
     */
    public function pendingSogoOwners(?int $groupId): array
    {
        $params = [];
        $where = '';
        if ($groupId !== null) {
            $where = " AND g.`group_id` = :id";
            $params['id'] = $groupId;
        }
        return $this->query("SELECT DISTINCT g.`owner` FROM `mailbox_group_sogo_grant` g
            JOIN `mailbox` mb ON mb.`username` = g.`owner` AND mb.`active` = '1' AND mb.`kind` = ''
          WHERE g.`state` = 'pending' AND g.`folder` = 'personal'$where ORDER BY g.`owner`", $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Side effects of the writes since the last call, for ChangeEffects after the commit.
     *
     * @return array{members: string[], sogo_acl: bool}
     */
    public function takeEffects(): array
    {
        $effects = $this->effects;
        $effects['members'] = array_values(array_unique($effects['members']));
        $this->effects = ['members' => [], 'sogo_acl' => false];
        return $effects;
    }

    /** @return string[] */
    private function rebuildAll(array $ids, bool $sogo = true): array
    {
        $users = [];
        foreach (array_unique($ids) as $id) {
            $users = array_merge($users, $this->rebuild((int)$id, $sogo));
        }
        return array_values(array_unique($users));
    }

    /**
     * sogo_acl rows of the group's SOGo grants: one row per member (except the owner) and role while the group is
     * active. Only rows of the ledger are ever deleted, so shares made in SOGo stay. A grant on a folder that does
     * not exist yet (the owner never opened SOGo) stays pending.
     */
    private function rebuildSogoAcl(int $id, bool $active, array $members): void
    {
        $this->cleanupSogoAcl();
        foreach ($this->query("SELECT * FROM `mailbox_group_sogo_grant` WHERE `group_id` = :id", ['id' => $id])->fetchAll(PDO::FETCH_ASSOC) as $grant) {
            $object = '/' . $grant['owner'] . '/' . $grant['folder_type'] . '/' . $grant['folder'];
            $folderId = $this->query("SELECT `c_folder_id` FROM `sogo_folder_info` WHERE `c_path` = :path",
                ['path' => '/Users' . $object])->fetchColumn();
            $wanted = [];
            if ($folderId !== false && $active) {
                foreach ($members as $username) {
                    foreach (($username === $grant['owner']) ? [] : self::SOGO_ROLES[$grant['folder_type']][$grant['access']] as $role) {
                        $wanted[$username . '|' . $role] = [$username, $role];
                    }
                }
            }
            $rows = $this->query("SELECT l.`acl_id`, l.`c_uid`, l.`c_role`, a.`c_folder_id`, a.`c_object` FROM `mailbox_group_sogo_acl` l
                LEFT JOIN `sogo_acl` a ON a.`id` = l.`acl_id`
              WHERE l.`grant_id` = :id", ['id' => $grant['id']])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $key = $row['c_uid'] . '|' . $row['c_role'];
                if (isset($wanted[$key]) && (string)$row['c_folder_id'] === (string)$folderId && $row['c_object'] === $object) {
                    unset($wanted[$key]);
                    continue;
                }
                $this->deleteSogoAcl((int)$row['acl_id']);
            }
            foreach ($wanted as [$username, $role]) {
                $aclId = $this->insertRow('sogo_acl', ['c_folder_id' => $folderId, 'c_object' => $object, 'c_uid' => $username, 'c_role' => $role]);
                $this->query("INSERT INTO `mailbox_group_sogo_acl` (`acl_id`, `grant_id`, `c_uid`, `c_role`) VALUES (:acl, :grant, :uid, :role)",
                    ['acl' => $aclId, 'grant' => $grant['id'], 'uid' => $username, 'role' => $role]);
                $this->effects['sogo_acl'] = true;
            }
            $state = ($folderId === false) ? 'pending' : 'applied';
            if ($grant['state'] !== $state) {
                $this->query("UPDATE `mailbox_group_sogo_grant` SET `state` = :state, `last_error` = :error WHERE `id` = :id",
                    ['state' => $state, 'error' => ($folderId === false) ? 'folder_missing' : null, 'id' => $grant['id']]);
            }
        }
    }

    private function deleteSogoAcl(int $aclId): void
    {
        $this->query("DELETE FROM `sogo_acl` WHERE `id` = :id", ['id' => $aclId]);
        $this->query("DELETE FROM `mailbox_group_sogo_acl` WHERE `acl_id` = :id", ['id' => $aclId]);
        $this->effects['sogo_acl'] = true;
    }

    private function fetch(string $where, array $params): ?Group
    {
        $row = $this->query("SELECT g.*, t.`template` AS `template_name` FROM `mailbox_group` g
          LEFT JOIN `templates` t ON t.`id` = g.`template_id`
          WHERE g.`id` = :id AND $where", $params)->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $group = Group::fromRow($row);
        $id = ['id' => $group->id];
        $group->members = $this->query("SELECT `username`, `source` FROM `mailbox_group_member` WHERE `group_id` = :id ORDER BY `username`",
            $id)->fetchAll(PDO::FETCH_KEY_PAIR);
        $group->sendAs = $this->query("SELECT `address` FROM `mailbox_group_send_as` WHERE `group_id` = :id ORDER BY `address`",
            $id)->fetchAll(PDO::FETCH_COLUMN);
        $group->imapGrants = array_map(fn(array $grant): array => array_merge($grant, ['subscribe' => (int)$grant['subscribe']]),
            $this->query("SELECT `owner`, `rights`, `subscribe`, `state`, `last_error` FROM `mailbox_group_imap_grant`
              WHERE `group_id` = :id AND `state` != 'removing' ORDER BY `owner`", $id)->fetchAll(PDO::FETCH_ASSOC));
        $group->sogoGrants = array_map(fn(array $grant): array => array_merge($grant, ['subscribe' => (int)$grant['subscribe']]),
            $this->query("SELECT `owner`, `folder_type` AS `type`, `folder`, `access`, `subscribe`, `state`, `last_error`
              FROM `mailbox_group_sogo_grant` WHERE `group_id` = :id ORDER BY `owner`, `folder_type`, `folder`", $id)->fetchAll(PDO::FETCH_ASSOC));
        return $group;
    }

    /** Members (username => source) as in the model; changed sources are updated, created stays */
    private function writeMembers(Group $group): void
    {
        $id = (int)$group->id;
        $stored = $this->query("SELECT `username`, `source` FROM `mailbox_group_member` WHERE `group_id` = :id", ['id' => $id])
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach (array_diff_key($stored, $group->members) as $username => $source) {
            $this->query("DELETE FROM `mailbox_group_member` WHERE `group_id` = :id AND `username` = :username",
                ['id' => $id, 'username' => $username]);
            $this->removedMembers[] = (string)$username;
        }
        foreach ($group->members as $username => $source) {
            if (!isset($stored[$username])) {
                $this->query("INSERT INTO `mailbox_group_member` (`group_id`, `username`, `source`) VALUES (:id, :username, :source)",
                    ['id' => $id, 'username' => $username, 'source' => $source]);
            } elseif ($stored[$username] !== $source) {
                $this->query("UPDATE `mailbox_group_member` SET `source` = :source WHERE `group_id` = :id AND `username` = :username",
                    ['source' => $source, 'id' => $id, 'username' => $username]);
            }
        }
    }

    /**
     * IMAP grants: new ones are pending, removed ones are set to removing until Dovecot dropped the ACL
     * (SharingService). SOGo grants are replaced; rebuild() maintains their sogo_acl rows.
     */
    private function writeGrants(Group $group): void
    {
        $id = (int)$group->id;
        $stored = [];
        foreach ($this->query("SELECT `id`, `owner`, `rights`, `subscribe`, `state` FROM `mailbox_group_imap_grant` WHERE `group_id` = :id",
            ['id' => $id])->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stored[(string)$row['owner']] = $row;
        }
        $wanted = [];
        foreach ($group->imapGrants as $grant) {
            $wanted[(string)$grant['owner']] = $grant;
        }
        foreach ($stored as $owner => $row) {
            if (!isset($wanted[$owner]) && $row['state'] !== 'removing') {
                $this->setImapState((int)$row['id'], 'removing', null);
            }
        }
        foreach ($wanted as $owner => $grant) {
            $row = $stored[$owner] ?? null;
            $subscribe = (int)($grant['subscribe'] ?? 0);
            if ($row === null) {
                $this->insertRow('mailbox_group_imap_grant', ['group_id' => $id, 'owner' => $owner, 'rights' => $grant['rights'],
                    'subscribe' => $subscribe, 'state' => 'pending']);
                continue;
            }
            if ($row['rights'] !== $grant['rights'] || $row['state'] === 'removing') {
                $this->query("UPDATE `mailbox_group_imap_grant` SET `rights` = :rights, `state` = 'pending', `last_error` = NULL WHERE `id` = :id",
                    ['rights' => $grant['rights'], 'id' => $row['id']]);
            }
            if ((int)$row['subscribe'] !== $subscribe) {
                $this->query("UPDATE `mailbox_group_imap_grant` SET `subscribe` = :subscribe WHERE `id` = :id",
                    ['subscribe' => $subscribe, 'id' => $row['id']]);
            }
        }
        $stored = [];
        foreach ($this->query("SELECT `id`, `owner`, `folder_type`, `folder`, `access`, `subscribe` FROM `mailbox_group_sogo_grant` WHERE `group_id` = :id",
            ['id' => $id])->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stored[$row['owner'] . '|' . $row['folder_type'] . '|' . $row['folder']] = $row;
        }
        $wanted = [];
        foreach ($group->sogoGrants as $grant) {
            $wanted[$grant['owner'] . '|' . $grant['type'] . '|' . $grant['folder']] = $grant;
        }
        foreach (array_diff_key($stored, $wanted) as $row) {
            $this->query("DELETE FROM `mailbox_group_sogo_grant` WHERE `id` = :id", ['id' => $row['id']]);
        }
        foreach ($wanted as $key => $grant) {
            $subscribe = (int)($grant['subscribe'] ?? 0);
            if (!isset($stored[$key])) {
                $this->insertRow('mailbox_group_sogo_grant', ['group_id' => $id, 'owner' => $grant['owner'], 'folder_type' => $grant['type'],
                    'folder' => $grant['folder'], 'access' => $grant['access'], 'subscribe' => $subscribe, 'state' => 'pending']);
            } elseif ($stored[$key]['access'] !== $grant['access'] || (int)$stored[$key]['subscribe'] !== $subscribe) {
                $this->query("UPDATE `mailbox_group_sogo_grant` SET `access` = :access, `subscribe` = :subscribe WHERE `id` = :id",
                    ['access' => $grant['access'], 'subscribe' => $subscribe, 'id' => $stored[$key]['id']]);
            }
        }
    }

    private function writeSendAs(Group $group): void
    {
        $id = ['id' => $group->id];
        $this->query("DELETE FROM `mailbox_group_send_as` WHERE `group_id` = :id", $id);
        foreach ($group->sendAs as $address) {
            $this->query("INSERT INTO `mailbox_group_send_as` (`group_id`, `address`) VALUES (:id, :address)", $id + ['address' => $address]);
        }
    }

    /**
     * The group's alias row as SOGo sees it: visible = active and sogo_visible (see the grouped_mail_aliases view).
     *
     * @return array{address: ?string, members: string[]} members only when visible
     */
    private function aliasState(int $id): array
    {
        $row = $this->query("SELECT `address`, `goto`, `active`, `sogo_visible` FROM `alias` WHERE `group_id` = :id",
            ['id' => $id])->fetch(PDO::FETCH_ASSOC);
        $visible = $row && $row['active'] == 1 && $row['sogo_visible'] == 1;
        return ['address' => $row['address'] ?? null, 'members' => $visible ? explode(',', (string)$row['goto']) : []];
    }

    /**
     * WHERE fragment over `mailbox_group g` that limits rows to what the caller may see, and its parameters.
     *
     * @return array{0: string, 1: array}
     */
    private function visibility(Context $context): array
    {
        if ($context->hasAdminRights()) {
            return ['1=1', []];
        }
        if ($context->isDomainAdmin()) {
            return ["g.`domain` IN (SELECT `domain` FROM `domain_admins` WHERE `active` = '1' AND `username` = :vis_da)",
                ['vis_da' => $context->username]];
        }
        return ['0=1', []];
    }

    private function values(Group $group): array
    {
        return [
            'domain' => $group->domain,
            'name' => $group->name,
            'description' => $group->description,
            'address' => $group->address,
            'sender_allowed' => $group->senderAllowed,
            'sogo_visible' => $group->sogoVisible,
            'internal' => $group->internal,
            'template_id' => $group->templateId,
            'priority' => $group->priority,
            'idp_ref' => $group->idpRef,
            'active' => $group->active,
        ];
    }
}
