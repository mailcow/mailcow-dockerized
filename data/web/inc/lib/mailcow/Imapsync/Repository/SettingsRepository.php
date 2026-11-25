<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Repository;

use Mailcow\Core\Repository;
use Mailcow\Imapsync\Model\Settings;
use PDO;

/**
 * SQL for the global sync job settings (table `imapsync_settings`, name => value).
 */
class SettingsRepository extends Repository
{
    /** Stored settings; keys that are not stored yet keep the defaults of the model */
    public function load(): Settings
    {
        $rows = $this->query("SELECT `name`, `value` FROM `imapsync_settings`")->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = new Settings();
        $settings->maxParallel = (int)($rows['max_parallel'] ?? $settings->maxParallel);
        $settings->maxBytesPerSecond = (int)($rows['max_bytes_per_second'] ?? $settings->maxBytesPerSecond);
        return $settings;
    }

    public function save(Settings $settings): void
    {
        $sql = "INSERT INTO `imapsync_settings` (`name`, `value`) VALUES (:name, :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $this->query($sql, ['name' => 'max_parallel', 'value' => (string)$settings->maxParallel]);
        $this->query($sql, ['name' => 'max_bytes_per_second', 'value' => (string)$settings->maxBytesPerSecond]);
    }
}
