<?php

declare(strict_types=1);

namespace Mailcow\Backup\Repository;

use Mailcow\Core\Repository;
use PDO;

/**
 * Table `backup_setting` (name/value pairs; the runner writes its state_* values into it as well).
 */
class SettingsRepository extends Repository
{
    /** @return array<string, string> name => value of the stored settings */
    public function load(): array
    {
        return $this->query("SELECT `name`, `value` FROM `backup_setting`")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Stores the given values (name => value) */
    public function save(array $values): void
    {
        $this->transaction(function () use ($values): void {
            foreach ($values as $name => $value) {
                $this->query("INSERT INTO `backup_setting` (`name`, `value`) VALUES (:name, :value)
                  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", ['name' => (string)$name, 'value' => (string)$value]);
            }
        });
    }
}
