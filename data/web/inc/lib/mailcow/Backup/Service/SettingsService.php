<?php

declare(strict_types=1);

namespace Mailcow\Backup\Service;

use Mailcow\Backup\Model\Settings;
use Mailcow\Backup\Repository\SettingsRepository;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;

/**
 * Backup settings: admins read them (also read-only API keys) and save them. Values are checked strictly (at least as
 * strictly as the runner), since the runner passes them to restic and ssh. Secrets are write-only and stored sealed
 * with the public key the runner published (only the runner can decrypt them); an empty secret keeps the stored one,
 * and without a published key no secret can be saved. A changed target, connection or repository password resets
 * state_initialized (the runner tests the repository again); a changed SFTP host or port drops the known host key. A
 * new repository password needs a new confirmation that it is stored outside mailcow. A change of a configured target,
 * its connection or the notify addresses leaves state_target_changed for the runner, which mails the notify addresses
 * of before the change.
 */
class SettingsService
{
    private const FLAGS = ['enabled', 's3_path_style', 'password_confirmed', 'include_index', 'include_fts'];
    /** Integer settings: name => [min, max] */
    private const RANGES = [
        'sftp_port' => [1, 65535],
        'keep_hourly' => [0, 1000],
        'keep_daily' => [0, 1000],
        'keep_weekly' => [0, 1000],
        'keep_monthly' => [0, 1000],
        'keep_yearly' => [0, 1000],
        'prune_weekday' => [0, 6],
        'check_subset' => [0, 365],
        'limit_upload' => [0, 100000000],
    ];
    private const MIN_PASSWORD = 8;
    private const MAX_LENGTH = 255;
    private const MAX_NOTIFY = 20;
    /** Entries of the schedule */
    private const MAX_SCHEDULE = 24;
    private const HOSTNAME = '/^(?=.{1,253}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i';

    public function __construct(private SettingsRepository $repo, private Context $context)
    {
    }

    /** The settings for admins, null for everybody else */
    public function get(): ?Settings
    {
        return $this->context->hasAdminRights() ? $this->load() : null;
    }

    /** The settings without permission check (status, job rules) */
    public function load(): Settings
    {
        return Settings::fromStored($this->repo->load());
    }

    /**
     * Saves the given settings; missing ones keep their value, unknown and runner-owned ones are ignored.
     *
     * @throws UserError
     */
    public function save(array $input): Settings
    {
        $this->context->requireAdmin();
        $current = $this->load();
        $values = $current->values;
        foreach ($input as $name => $value) {
            if (!is_string($name) || !array_key_exists($name, Settings::DEFAULTS) || in_array($name, Settings::RUNNER, true)) {
                continue;
            }
            $value = $this->normalize($name, $value);
            if (in_array($name, Settings::SECRETS, true)) {
                if ($value === '') {
                    continue;
                }
                $value = self::seal($value, $current->get('state_secrets_pubkey'));
            }
            $values[$name] = $value;
        }
        if ($values['password'] !== $current->get('password')
            && !(array_key_exists('password_confirmed', $input) && $values['password_confirmed'] === '1')) {
            $values['password_confirmed'] = '0';
        }
        foreach (array_merge(['target', 'password'], Settings::CONNECTION[$values['target']]) as $name) {
            if ($values[$name] !== $current->get($name)) {
                $values['state_initialized'] = '0';
                break;
            }
        }
        if ($values['sftp_host'] !== $current->get('sftp_host') || $values['sftp_port'] !== $current->get('sftp_port')) {
            $values['sftp_host_key'] = '';
        }
        $settings = new Settings($values);
        if ($values['enabled'] === '1' && !$settings->configured()) {
            throw new UserError('backup_not_configured');
        }
        // switched on without a time
        if ($values['enabled'] === '1' && empty($settings->scheduleSlots())) {
            throw self::invalid('schedule');
        }
        $values['state_target_changed'] = $this->targetChange($current, $values);
        $settings = new Settings($values);
        $changed = array_diff_assoc($values, $current->values);
        if (!empty($changed)) {
            $this->repo->save($changed);
        }
        return $settings;
    }

    /**
     * state_target_changed after a save: when the target, its connection or the notify addresses change, what the target
     * was, whom to tell (the notify addresses before the change) and what changed ("target", "notify_email"). An earlier
     * change the runner has not mailed yet keeps its state and addresses and only gets the new kinds of change, so new
     * addresses followed by a new target still go to the original addresses. Nothing is recorded for a target that was
     * not configured or without notify addresses.
     */
    private function targetChange(Settings $current, array $values): string
    {
        $pending = json_decode($current->get('state_target_changed'), true);
        if (!$current->configured() && !is_array($pending)) {
            return $current->get('state_target_changed');
        }
        $old = $current->get('target');
        $watched = array_unique(array_merge(['target'], Settings::CONNECTION[$old] ?? [], Settings::CONNECTION[$values['target']] ?? []));
        $changed = [];
        if (!empty(array_filter($watched, fn(string $name): bool => $values[$name] !== $current->get($name)))) {
            $changed[] = 'target';
        }
        if ($values['notify_email'] !== $current->get('notify_email')) {
            $changed[] = 'notify_email';
        }
        if (is_array($pending)) {
            $pending['changed'] = array_values(array_unique(array_merge((array)($pending['changed'] ?? ['target']), $changed)));
            return (string)json_encode($pending, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $addresses = array_values(array_filter(explode(',', $current->get('notify_email')), 'strlen'));
        if (empty($changed) || empty($addresses)) {
            return '';
        }
        $summary = ['target' => $old];
        foreach (array_diff(Settings::CONNECTION[$old] ?? [], Settings::SECRETS) as $name) {
            $summary[$name] = $current->get($name);
        }
        return (string)json_encode(['old_target' => $summary, 'old_addresses' => $addresses, 'changed' => $changed,
            'changed_by' => (string)$this->context->username, 'time' => time()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * A secret sealed for the runner: sealed:v1:<base64 of sodium_crypto_box_seal>
     *
     * @throws UserError backup_secrets_key_missing while the runner has not published its key
     */
    private static function seal(string $secret, string $publicKey): string
    {
        $key = base64_decode($publicKey, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new UserError('backup_secrets_key_missing');
        }
        return Settings::SEALED . base64_encode(sodium_crypto_box_seal($secret, $key));
    }

    /**
     * The value to store, '' for an empty optional value.
     *
     * @throws UserError backup_setting_invalid, backup_password_too_short
     */
    private function normalize(string $name, mixed $value): string
    {
        if (in_array($name, self::FLAGS, true)) {
            return self::flag($value) ?? throw self::invalid($name);
        }
        if ($name === 'notify_email' && is_string($value)) {
            // one address per line is fine as well
            $value = preg_replace('/[\r\n\t]+/', ',', $value);
        }
        if ($name === 'schedule') {
            return self::schedule($value) ?? throw self::invalid($name);
        }
        // secrets as typed: spaces at the ends are part of a password
        $text = (in_array($name, Settings::SECRETS, true) && is_string($value)) ? $value : Input::text($value);
        if ($text === null || mb_strlen($text) > (($name === 'notify_email') ? self::MAX_LENGTH * self::MAX_NOTIFY : self::MAX_LENGTH) || preg_match('/[\x00-\x1f\x7f]/', $text) || !mb_check_encoding($text, 'UTF-8')) {
            throw self::invalid($name);
        }
        // no scheduled prune (append-only or object-lock targets); a prune job still works
        if ($name === 'prune_weekday' && $text === '') {
            return '';
        }
        if (isset(self::RANGES[$name])) {
            $int = Input::intInRange($text, ...self::RANGES[$name]);
            return ($int === null) ? throw self::invalid($name) : (string)$int;
        }
        $valid = match ($name) {
            'target' => in_array($text, Settings::TARGETS, true),
            // like the runner: scheme, host and port only
            's3_endpoint' => self::url($text) && in_array(parse_url($text, PHP_URL_PATH) ?? '', ['', '/'], true),
            'rest_url' => $text === '' || self::url($text),
            's3_bucket' => $text === '' || preg_match('/^[a-z0-9][a-z0-9._-]{1,62}$/i', $text),
            's3_path' => $text === '' || self::path(trim($text, '/'), false),
            's3_region' => $text === '' || preg_match('/^[a-z0-9-]{1,64}$/i', $text),
            's3_access_key' => preg_match('/^[\x21-\x7e]*$/', $text),
            's3_secret_key', 'rest_password' => true,
            'sftp_host' => $text === '' || preg_match(self::HOSTNAME, $text) || filter_var($text, FILTER_VALIDATE_IP),
            // the runner's character set (ssh -l)
            'sftp_user' => $text === '' || preg_match('/^[a-z0-9_][a-z0-9._-]{0,63}$/i', $text),
            'sftp_path' => $text === '' || self::path($text, true),
            'rest_user' => $text === '' || preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/i', $text),
            // the runner's character set, below the repository folder
            'local_path' => preg_match('~^[a-z0-9._-]+(/[a-z0-9._-]+)*$~i', $text) && self::path($text, false),
            'password' => true,
            'max_unused' => preg_match('/^(\d{1,3})\s*%?$/', $text, $percent) && (int)$percent[1] <= 100,
            'notify_email' => true,
            default => false,
        };
        if (!$valid) {
            throw self::invalid($name);
        }
        return match ($name) {
            's3_path' => trim($text, '/'),
            's3_endpoint' => rtrim($text, '/'),
            'sftp_path' => rtrim($text, '/'),
            'password' => self::password($text),
            'max_unused' => (int)$text . '%',
            'notify_email' => self::emails($text),
            default => $text,
        };
    }

    /**
     * The schedule as stored: a list (or its JSON) of {days: weekdays 0 (Sunday) to 6, time: "H:MM"}, up to MAX_SCHEDULE
     * entries, each with at least one weekday; days sorted, times as HH:MM, repeated entries dropped. Null when invalid.
     */
    private static function schedule(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAX_SCHEDULE) {
            return null;
        }
        $entries = [];
        foreach ($value as $entry) {
            $days = $entry['days'] ?? null;
            $time = is_array($entry) && is_string($entry['time'] ?? null) ? $entry['time'] : '';
            if (!is_array($days) || empty($days) || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $match)) {
                return null;
            }
            foreach ($days as $day) {
                if (Input::intInRange($day, 0, 6) === null) {
                    return null;
                }
            }
            $days = array_values(array_unique(array_map('intval', $days)));
            sort($days);
            $entries[] = ['days' => $days, 'time' => sprintf('%02d:%02d', $match[1], $match[2])];
        }
        return (string)json_encode(array_values(array_unique($entries, SORT_REGULAR)));
    }

    /** http(s) URL without credentials, query or fragment (rest_user/rest_password hold the credentials) */
    private static function url(string $url): bool
    {
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) !== false && is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
            && (preg_match(self::HOSTNAME, (string)($parts['host'] ?? '')) || filter_var(trim((string)($parts['host'] ?? ''), '[]'), FILTER_VALIDATE_IP));
    }

    /** A path of plain names separated by /, without . and .. parts; a leading / only where $absolute is allowed */
    private static function path(string $path, bool $absolute): bool
    {
        if ($path === '' || (str_starts_with($path, '/') && !$absolute)) {
            return false;
        }
        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === '.' || $part === '..' || !preg_match('/^[a-z0-9_~+@][a-z0-9._~+@-]*$/i', $part)) {
                return false;
            }
        }
        return true;
    }

    /** @throws UserError backup_password_too_short ('' keeps the stored password) */
    private static function password(string $password): string
    {
        if ($password !== '' && mb_strlen($password) < self::MIN_PASSWORD) {
            throw new UserError('backup_password_too_short', [self::MIN_PASSWORD]);
        }
        return $password;
    }

    /** @throws UserError for an invalid address or too many */
    private static function emails(string $text): string
    {
        $emails = array_values(array_unique(array_filter(preg_split('/[\s,;]+/', $text), 'strlen')));
        if (count($emails) > self::MAX_NOTIFY) {
            throw self::invalid('notify_email');
        }
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw self::invalid('notify_email');
            }
        }
        return implode(',', $emails);
    }

    /** Checkbox or API flag as '0'/'1'; a hidden "0" + checkbox pair arrives as ["0", "1"] when checked; null = invalid */
    private static function flag(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = end($value);
        }
        if (!is_scalar($value) && $value !== null) {
            return null;
        }
        $flag = filter_var($value ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return ($flag === null) ? null : (string)(int)$flag;
    }

    private static function invalid(string $name): UserError
    {
        return new UserError('backup_setting_invalid', [$name]);
    }
}
