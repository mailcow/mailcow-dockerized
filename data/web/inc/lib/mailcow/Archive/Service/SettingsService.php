<?php

declare(strict_types=1);

namespace Mailcow\Archive\Service;

use Mailcow\Archive\Model\Settings;
use Mailcow\Archive\Redis\ArchiveState;
use Mailcow\Archive\Repository\LookupRepository;
use Mailcow\Archive\Repository\SettingsRepository;
use Mailcow\Archive\Smtp\SmtpClient;
use Mailcow\Archive\Smtp\SmtpException;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use PDOException;

/**
 * Admin use cases of the archive target. Read-only API keys may read the settings (never password or key).
 */
class SettingsService
{
    private const MAX_SECRET_LENGTH = 255;
    /** Extensions the archive should announce: Postfix passes 8-bit mails unchanged (archive_smtp in master.cf) */
    private const WANTED_EXTENSIONS = ['8BITMIME'];

    public function __construct(private SettingsRepository $repo, private LookupRepository $lookup, private ArchiveState $state,
        private SmtpClient $smtp, private Context $context)
    {
    }

    /** @throws AccessDenied */
    public function get(): Settings
    {
        $this->context->requireAdmin(false);
        return $this->repo->load();
    }

    /** Global switch for internal callers (page templates, mailbox details), no permission check; off while the table is missing */
    public function enabled(): bool
    {
        try {
            return $this->repo->load()->enabled;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Saves the settings: attributes missing in $input keep their value, an empty password or signing key keeps
     * the stored one, `password_remove` deletes the password. Switching on needs an address (and a signing key for archivecow).
     *
     * @throws UserError
     */
    public function save(array $input): Settings
    {
        $this->context->requireAdmin();
        $settings = $this->repo->load();
        if (array_key_exists('address', $input)) {
            $address = strtolower((string)Input::text($input['address']));
            if (!is_scalar($input['address']) || ($address !== '' && !filter_var($address, FILTER_VALIDATE_EMAIL))) {
                throw new UserError('archive_address_invalid');
            }
            // Postfix would treat an own address as local (aliases, unknown users) before the copy reaches the archive
            if ($address !== '' && $this->lookup->isMailcowDomain(substr($address, strrpos($address, '@') + 1))) {
                throw new UserError('archive_address_local');
            }
            $settings->address = $address;
        }
        if (array_key_exists('host', $input)) {
            $host = strtolower(trim((string)Input::text($input['host']), " []"));
            if (!is_scalar($input['host']) || ($host !== '' && !filter_var($host, FILTER_VALIDATE_IP)
                && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
                throw new UserError('archive_host_invalid');
            }
            $settings->host = $host;
        }
        if (array_key_exists('port', $input)) {
            $settings->port = Input::intInRange($input['port'], 1, 65535) ?? throw new UserError('archive_port_invalid');
        }
        if (array_key_exists('tls', $input)) {
            $settings->tls = in_array($input['tls'], Settings::TLS_MODES, true) ? $input['tls'] : throw new UserError('archive_tls_invalid');
        }
        if (array_key_exists('username', $input)) {
            $username = Input::text($input['username']);
            if ($username === null || strlen($username) > self::MAX_SECRET_LENGTH || preg_match('/[\s:]/', $username)) {
                throw new UserError('archive_username_invalid');
            }
            $settings->username = $username;
        }
        if (array_key_exists('password', $input)) {
            $settings->password = self::secret($input['password'], $settings->password, 'archive_password_invalid');
        }
        if (self::flag($input['password_remove'] ?? '0')) {
            $settings->password = '';
        }
        if (array_key_exists('profile', $input)) {
            $settings->profile = in_array($input['profile'], Settings::PROFILES, true) ? $input['profile'] : throw new UserError('archive_profile_invalid');
        }
        if (array_key_exists('signing_secret', $input)) {
            $settings->signingKey = self::secret($input['signing_secret'], $settings->signingKey, 'archive_signing_secret_invalid');
        }
        foreach (['header_from' => 'headerFrom', 'header_rcpt' => 'headerRcpt'] as $key => $property) {
            if (array_key_exists($key, $input)) {
                $name = Input::text($input[$key]);
                if ($name === null || !preg_match('/^[A-Za-z][A-Za-z0-9-]{0,63}$/', $name)) {
                    throw new UserError('archive_header_invalid');
                }
                $settings->$property = $name;
            }
        }
        if (array_key_exists('enabled', $input)) {
            $settings->enabled = self::flag($input['enabled']);
        }
        if ($settings->enabled && !$settings->canDeliver()) {
            throw new UserError('archive_incomplete');
        }
        $this->repo->save($settings);
        $this->state->setEnabled($settings->enabled);
        return $settings;
    }

    /**
     * Tests the stored target: connection, TLS, AUTH and whether the archive accepts the address (no mail is sent).
     * Returns the success message, with the missing extension when the archive lacks 8BITMIME.
     *
     * @throws UserError
     */
    public function test(): string|array
    {
        $this->context->requireAdmin();
        $settings = $this->repo->load();
        if ($settings->address === '') {
            throw new UserError('archive_incomplete');
        }
        $host = ($settings->host !== '') ? $settings->host : $this->smtp->mailHost(substr($settings->address, strrpos($settings->address, '@') + 1));
        try {
            $extensions = $this->smtp->probe($host, $settings->port, $settings->tls, $settings->username, $settings->password,
                Settings::SENDER, $settings->address);
        } catch (SmtpException $e) {
            throw new UserError('archive_test_failed', [htmlspecialchars($host . ':' . $settings->port . ' ' . $e->describe())]);
        }
        $missing = array_values(array_diff(self::WANTED_EXTENSIONS, $extensions));
        return empty($missing) ? 'archive_test_ok' : ['archive_test_ok_missing', implode(', ', $missing)];
    }

    /** A new secret, or the stored one when $value is empty */
    private static function secret(mixed $value, string $current, string $error): string
    {
        $secret = Input::text($value);
        if ($secret === null || strlen($secret) > self::MAX_SECRET_LENGTH || preg_match('/[^\x21-\x7e]/', $secret)) {
            throw new UserError($error);
        }
        return ($secret === '') ? $current : $secret;
    }

    /** Checkbox or API flag; a hidden "0" + checkbox pair arrives as ["0", "1"] when checked */
    private static function flag(mixed $value): bool
    {
        if (is_array($value)) {
            $value = end($value);
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
