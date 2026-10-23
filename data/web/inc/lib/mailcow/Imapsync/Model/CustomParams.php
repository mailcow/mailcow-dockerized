<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Model;

use Mailcow\Core\UserError;

/**
 * Rules for the extra imapsync options of a sync job: a list of {o: option, v: value} pairs, stored as JSON
 * (`imapsync`.`custom_params`, read by the runner). Stored values are passed through unchanged.
 *
 * Allowlist rules (option name lowercase, without dashes):
 *   true   option requires a non-empty value
 *   false  flag, must not have a value
 *   string PCRE (no delimiters) the whole non-empty value must match
 */
class CustomParams
{
    private const MAX_PAIRS = 100;
    private const MAX_VALUE_LENGTH = 1000;

    private static bool $warnedOldFormat = false;

    /**
     * Validates request input (list of {o, v}) against the allowlist and returns the JSON to store.
     * Empty input means no options.
     *
     * @throws UserError imapsync_custom_params_format, imapsync_custom_param_invalid, imapsync_custom_param_value
     */
    public static function toJson(mixed $input, array $allowlist): string
    {
        $input = ($input === null || $input === '') ? [] : $input;
        if (!is_array($input)) {
            throw new UserError('imapsync_custom_params_format');
        }
        $pairs = [];
        foreach ($input as $pair) {
            $option = is_array($pair) ? ($pair['o'] ?? '') : null;
            $value = is_array($pair) ? ($pair['v'] ?? '') : null;
            if (!is_scalar($option) || !is_scalar($value)) {
                throw new UserError('imapsync_custom_params_format');
            }
            $option = ltrim(strtolower(trim((string)$option)), '-');
            $value = str_replace("\0", '', (string)$value);
            if ($option === '' && trim($value) === '') {
                continue; // empty UI row
            }
            $value = self::checkValue($option, $value, $allowlist);
            if (strlen($value) > self::MAX_VALUE_LENGTH || count($pairs) >= self::MAX_PAIRS) {
                throw new UserError('imapsync_custom_params_format');
            }
            $pairs[] = ['o' => $option, 'v' => $value];
        }
        return json_encode($pairs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The effective allowlist: $IMAPSYNC_OPTIONS (after vars.local.inc.php) without the old
     * 'whitelist' key and malformed entries; the defaults when nothing valid is left.
     */
    public static function allowlist(array $configured, array $defaults): array
    {
        $allowlist = [];
        foreach ($configured as $option => $rule) {
            $option = strtolower(ltrim(trim((string)$option), '-'));
            if ($option !== 'whitelist' && preg_match('/^[a-z][a-z0-9]*$/', $option)
                && (is_bool($rule) || (is_string($rule) && $rule !== ''))) {
                $allowlist[$option] = $rule;
            }
        }
        if (!self::$warnedOldFormat && isset($configured['whitelist'])) {
            self::$warnedOldFormat = true;
            error_log("mailcow: \$IMAPSYNC_OPTIONS['whitelist'] (old format) is ignored, use 'option' => true|false|'regex' (see data/web/inc/vars.inc.php)");
        }
        return empty($allowlist) ? $defaults : $allowlist;
    }

    /** The value to store for one option; throws when the allowlist rule is violated */
    private static function checkValue(string $option, string $value, array $allowlist): string
    {
        $rule = $allowlist[$option] ?? null;
        if ($option === '' || !(is_bool($rule) || is_string($rule))) {
            throw new UserError('imapsync_custom_param_invalid', [htmlspecialchars($option)]);
        }
        $empty = (trim($value) === '');
        $valid = match (true) {
            $rule === false => $empty,
            $rule === true => !$empty,
            default => !$empty && preg_match('~^(?:' . str_replace('~', '\~', $rule) . ')$~D', $value) === 1,
        };
        if (!$valid) {
            throw new UserError('imapsync_custom_param_value', [$option]);
        }
        return ($rule === false) ? '' : $value;
    }
}
