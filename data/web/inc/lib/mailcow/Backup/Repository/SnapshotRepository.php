<?php

declare(strict_types=1);

namespace Mailcow\Backup\Repository;

use Mailcow\Backup\Model\Snapshot;
use Mailcow\Core\Repository;

/**
 * Table `backup_snapshot`: the runner's cache of the repository's snapshots (read only here).
 */
class SnapshotRepository extends Repository
{
    private const COLUMNS = "`id`, `short_id`, UNIX_TIMESTAMP(`time`) AS `time`, `hostname`, `tags`, `summary`, `domains`";

    /** By id or short id, with its content (the mailboxes in it) */
    public function find(string $id): ?Snapshot
    {
        $row = $this->query("SELECT " . self::COLUMNS . ", `content` FROM `backup_snapshot` WHERE `id` = :id OR `short_id` = :short_id ORDER BY `id` = :exact DESC LIMIT 1",
            ['id' => $id, 'short_id' => $id, 'exact' => $id])->fetch();
        return is_array($row) ? Snapshot::fromRow($row) : null;
    }

    /** @return Snapshot[] newest first; the content (the mailboxes in it) only with $withContent */
    public function all(bool $withContent = false): array
    {
        return array_map(fn(array $row): Snapshot => Snapshot::fromRow($row),
            $this->query("SELECT " . self::COLUMNS . ($withContent ? ", `content`" : "") . " FROM `backup_snapshot` ORDER BY `time` DESC, `id`")->fetchAll());
    }
}
