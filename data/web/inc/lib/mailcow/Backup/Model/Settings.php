<?php

declare(strict_types=1);

namespace Mailcow\Backup\Model;

/**
 * Backup settings (table `backup_setting`, name/value pairs) over their defaults. Secrets are stored sealed for the
 * runner (`sealed:v1:<base64>`) and never leave toArray(): the API gets `<key>_set` instead. The state_* values, the
 * SSH public key and the SFTP host key are written by the runner in the restic container and cannot be set through
 * the API (PHP writes state_target_changed when the target changes).
 */
class Settings
{
    public const DEFAULTS = [
        'enabled' => '0',
        'target' => 's3',
        's3_endpoint' => 'https://s3.amazonaws.com',
        's3_bucket' => '',
        's3_path' => '',
        's3_region' => '',
        's3_access_key' => '',
        's3_secret_key' => '',
        's3_path_style' => '0',
        'sftp_host' => '',
        'sftp_port' => '22',
        'sftp_user' => '',
        'sftp_path' => '',
        'sftp_public_key' => '',
        'sftp_host_key' => '',
        'rest_url' => '',
        'rest_user' => '',
        'rest_password' => '',
        'local_path' => 'mailcow',
        'password' => '',
        'password_confirmed' => '0',
        // JSON list of the scheduled backups: {"days": [weekdays, 0 = Sunday], "time": "HH:MM"}
        'schedule' => '[{"days":[0,1,2,3,4,5,6],"time":"01:00"}]',
        'keep_hourly' => '0',
        'keep_daily' => '7',
        'keep_weekly' => '4',
        'keep_monthly' => '12',
        'keep_yearly' => '0',
        'prune_weekday' => '0',
        'max_unused' => '10%',
        'check_subset' => '30',
        'include_index' => '1',
        'include_fts' => '1',
        'limit_upload' => '0',
        'notify_email' => '',
        'state_initialized' => '0',
        'state_runner_seen' => '',
        'state_last_backup' => '',
        'state_last_success' => '',
        'state_repo_size' => '',
        'state_repo_stats' => '',
        'state_schedule_since' => '',
        'state_schedule' => '',
        // public key (base64, X25519) the secrets are sealed with; the private key stays in the restic container
        'state_secrets_pubkey' => '',
        'state_secrets_error' => '',
        // JSON {old_target, old_addresses, changed, changed_by, time}: the runner mails the old addresses and clears it
        'state_target_changed' => '',
        // RESTIC_BACKUP_MODE of mailcow.conf as the runner sees it
        'state_backup_mode' => '',
    ];
    public const SECRETS = ['password', 's3_secret_key', 'rest_password'];
    /** Written by the runner only */
    public const RUNNER = ['sftp_public_key', 'sftp_host_key', 'state_initialized', 'state_runner_seen', 'state_last_backup',
        'state_last_success', 'state_repo_size', 'state_repo_stats', 'state_schedule_since', 'state_schedule', 'state_secrets_pubkey',
        'state_secrets_error', 'state_target_changed', 'state_backup_mode'];
    /** Prefix of a sealed secret (sodium_crypto_box_seal with state_secrets_pubkey) */
    public const SEALED = 'sealed:v1:';
    public const TARGETS = ['s3', 'sftp', 'rest', 'local'];
    /** The connection of each target: a change (or another target or repository password) resets state_initialized */
    public const CONNECTION = [
        's3' => ['s3_endpoint', 's3_bucket', 's3_path', 's3_region', 's3_access_key', 's3_secret_key', 's3_path_style'],
        'sftp' => ['sftp_host', 'sftp_port', 'sftp_user', 'sftp_path'],
        'rest' => ['rest_url', 'rest_user', 'rest_password'],
        'local' => ['local_path'],
    ];
    /** Values a target cannot work without (besides the repository password) */
    public const REQUIRED = [
        's3' => ['s3_endpoint', 's3_bucket', 's3_access_key', 's3_secret_key'],
        'sftp' => ['sftp_host', 'sftp_port', 'sftp_user', 'sftp_path'],
        'rest' => ['rest_url'],
        'local' => ['local_path'],
    ];
    /** Returned as numbers by the API ('' = not known yet: null; prune_weekday '' = never stays '') */
    private const NUMBERS = ['enabled', 's3_path_style', 'sftp_port', 'password_confirmed', 'keep_hourly', 'keep_daily', 'keep_weekly', 'keep_monthly',
        'keep_yearly', 'prune_weekday', 'check_subset', 'include_index', 'include_fts', 'limit_upload', 'state_initialized',
        'state_runner_seen', 'state_last_backup', 'state_last_success', 'state_repo_size', 'state_schedule_since'];
    private const JSON = ['schedule', 'state_repo_stats', 'state_target_changed'];

    /** @param array<string, string> $values every key of DEFAULTS */
    public function __construct(public array $values = self::DEFAULTS)
    {
    }

    /** Stored values (name => value) over the defaults; unknown names are dropped */
    public static function fromStored(array $stored): self
    {
        $values = self::DEFAULTS;
        foreach ($stored as $name => $value) {
            if (array_key_exists($name, $values)) {
                $values[$name] = (string)$value;
            }
        }
        return new self($values);
    }

    public function get(string $name): string
    {
        return $this->values[$name] ?? '';
    }

    /** The target has its required values and the repository password is set */
    public function configured(): bool
    {
        $required = self::REQUIRED[$this->get('target')] ?? null;
        if ($required === null || $this->get('password') === '') {
            return false;
        }
        foreach ($required as $name) {
            if ($this->get($name) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * The schedule as [weekday (0 = Sunday), hour, minute] slots, sorted and unique
     *
     * @return array<int, array{int, int, int}>
     */
    public function scheduleSlots(): array
    {
        $entries = json_decode($this->get('schedule'), true);
        $slots = [];
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (!is_array($entry) || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', (string)($entry['time'] ?? ''), $time) || !is_array($entry['days'] ?? null)) {
                continue;
            }
            foreach ($entry['days'] as $day) {
                if (is_int($day) && $day >= 0 && $day <= 6) {
                    $slots[] = $day * 1440 + (int)$time[1] * 60 + (int)$time[2];
                }
            }
        }
        $slots = array_unique($slots);
        sort($slots);
        return array_map(fn(int $slot): array => [intdiv($slot, 1440), intdiv($slot % 1440, 60), $slot % 60], $slots);
    }

    /** The longest time between two scheduled backups of a week in seconds, null without a schedule */
    public function scheduleGap(): ?int
    {
        $slots = array_map(fn(array $slot): int => $slot[0] * 86400 + $slot[1] * 3600 + $slot[2] * 60, $this->scheduleSlots());
        if (empty($slots)) {
            return null;
        }
        $gaps = [$slots[0] + 7 * 86400 - end($slots)];
        for ($i = 1; $i < count($slots); $i++) {
            $gaps[] = $slots[$i] - $slots[$i - 1];
        }
        return max($gaps);
    }

    /** Entry of get/backup-settings: every key, secrets as <key>_set, plus `configured` */
    public function toArray(): array
    {
        $settings = [];
        foreach ($this->values as $name => $value) {
            if (in_array($name, self::SECRETS, true)) {
                $settings[$name . '_set'] = (int)($value !== '');
            } elseif (in_array($name, self::JSON, true)) {
                $decoded = json_decode($value, true);
                $settings[$name] = is_array($decoded) ? $decoded : null;
            } elseif (in_array($name, self::NUMBERS, true)) {
                $settings[$name] = ($value === '') ? (($name === 'prune_weekday') ? '' : null) : (int)$value;
            } else {
                $settings[$name] = $value;
            }
        }
        $settings['configured'] = (int)$this->configured();
        return $settings;
    }
}
