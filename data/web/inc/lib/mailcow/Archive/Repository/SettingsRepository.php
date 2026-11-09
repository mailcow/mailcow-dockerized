<?php

declare(strict_types=1);

namespace Mailcow\Archive\Repository;

use Mailcow\Archive\Model\Settings;
use Mailcow\Core\Repository;
use PDO;

/**
 * SQL for the archive target: the settings (table `archive_settings`, name => value) and the row the Postfix maps
 * read (table `archive_transport`: transport, SASL, TLS policy of the archive_smtp service).
 */
class SettingsRepository extends Repository
{
    /** Stored settings; keys that are not stored yet keep the defaults of the model */
    public function load(): Settings
    {
        $rows = $this->query("SELECT `name`, `value` FROM `archive_settings`")->fetchAll(PDO::FETCH_KEY_PAIR);
        $settings = new Settings();
        $settings->address = (string)($rows['address'] ?? '');
        $settings->host = (string)($rows['host'] ?? '');
        $settings->port = (int)($rows['port'] ?? $settings->port);
        $settings->tls = (string)($rows['tls'] ?? $settings->tls);
        $settings->username = (string)($rows['username'] ?? '');
        $settings->password = (string)($rows['password'] ?? '');
        $settings->profile = (string)($rows['profile'] ?? $settings->profile);
        $settings->signingKey = (string)($rows['signing_secret'] ?? '');
        $settings->headerFrom = (string)($rows['header_from'] ?? $settings->headerFrom);
        $settings->headerRcpt = (string)($rows['header_rcpt'] ?? $settings->headerRcpt);
        return $settings;
    }

    /**
     * Saves the settings and the transport row. The row stays while an address is set, also with archiving off,
     * so copies still queued in Postfix are delivered.
     */
    public function save(Settings $settings): void
    {
        $values = [
            'address' => $settings->address,
            'host' => $settings->host,
            'port' => (string)$settings->port,
            'tls' => $settings->tls,
            'username' => $settings->username,
            'password' => $settings->password,
            'profile' => $settings->profile,
            'signing_secret' => $settings->signingKey,
            'header_from' => $settings->headerFrom,
            'header_rcpt' => $settings->headerRcpt,
        ];
        $sql = "INSERT INTO `archive_settings` (`name`, `value`) VALUES (:name, :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $this->transaction(function () use ($sql, $values, $settings): void {
            foreach ($values as $name => $value) {
                $this->query($sql, ['name' => $name, 'value' => $value]);
            }
            $this->query("DELETE FROM `archive_transport`");
            if ($settings->address !== '') {
                $this->insertRow('archive_transport', [
                    'address' => $settings->address,
                    'nexthop' => $settings->nexthop(),
                    'username' => $settings->username,
                    'password' => $settings->password,
                    'tls_policy' => $settings->tls,
                    'active' => 1,
                ]);
            }
        });
    }
}
