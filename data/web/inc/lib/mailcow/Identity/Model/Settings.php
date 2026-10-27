<?php

declare(strict_types=1);

namespace Mailcow\Identity\Model;

/**
 * Identity provider settings (table `identity_provider`, key/value, see identity_provider('get')) with defaults.
 */
class Settings
{
    public const KEYCLOAK = 'keycloak';
    public const LDAP = 'ldap';
    public const GENERIC_OIDC = 'generic-oidc';
    public const MEMBER_MODES = ['memberof', 'member', 'ad_chain'];

    public string $provider = '';
    public bool $periodicSync = false;
    public bool $importUsers = false;
    /** Minutes between scheduled runs */
    public int $syncInterval = 15;
    /** Users disabled or removed in the IdP get login disabled (mailbox.active = 2) */
    public bool $offboarding = false;
    /** Max. share (percent) of active IdP mailboxes one run may deactivate, at least 5 mailboxes */
    public int $offboardingLimit = 10;
    public bool $groupSync = false;
    public bool $groupAutocreate = true;
    public string $groupDefaultDomain = '';
    public string $defaultTemplate = '';
    /** @var string[] attribute value per mapping, keys aligned with $templates */
    public array $mappers = [];
    /** @var string[] */
    public array $templates = [];
    public string $serverUrl = '';
    public string $realm = '';
    public string $clientId = '';
    public string $clientSecret = '';
    public bool $ignoreSslError = false;
    /** @var string[] Keycloak group paths: '/path', '/path/*' (subtree) or '/*' (all) */
    public array $groupPaths = [];
    public string $basedn = '';
    public string $filter = '';
    public string $usernameField = 'mail';
    public string $attributeField = '';
    /** LDAP attribute with the stable user id, '' = objectGUID, entryUUID, nsUniqueId, ipaUniqueID, else the DN */
    public string $idAttribute = '';
    /** LDAP filter matching disabled users (besides AD userAccountControl) */
    public string $disabledFilter = '';
    public string $groupBasedn = '';
    public string $groupFilter = '';
    public string $groupNameAttribute = 'cn';
    public string $groupMemberMode = 'memberof';
    public string $groupMemberAttribute = 'member';

    public static function fromArray(array $s): self
    {
        $settings = new self();
        $settings->provider = (string)($s['authsource'] ?? '');
        $settings->periodicSync = self::flag($s['periodic_sync'] ?? 0);
        $settings->importUsers = self::flag($s['import_users'] ?? 0);
        $settings->syncInterval = max(1, (int)($s['sync_interval'] ?? 15));
        $settings->offboarding = self::flag($s['offboarding'] ?? 0);
        $settings->offboardingLimit = min(100, max(0, (int)($s['offboarding_limit'] ?? 10)));
        $settings->groupSync = self::flag($s['group_sync'] ?? 0);
        $settings->groupAutocreate = self::flag($s['group_autocreate'] ?? 1);
        $settings->groupDefaultDomain = strtolower(trim((string)($s['group_default_domain'] ?? '')));
        $settings->defaultTemplate = (string)($s['default_template'] ?? '');
        $settings->mappers = array_map('strval', (array)($s['mappers'] ?? []));
        $settings->templates = array_map('strval', (array)($s['templates'] ?? []));
        $settings->serverUrl = rtrim((string)($s['server_url'] ?? ''), '/');
        $settings->realm = (string)($s['realm'] ?? '');
        $settings->clientId = (string)($s['client_id'] ?? '');
        $settings->clientSecret = (string)($s['client_secret'] ?? '');
        $settings->ignoreSslError = self::flag($s['ignore_ssl_error'] ?? 0);
        $settings->groupPaths = array_values(array_filter(array_map('trim', explode(',', (string)($s['group_paths'] ?? ''))), 'strlen'));
        $settings->basedn = (string)($s['basedn'] ?? '');
        $settings->filter = trim((string)($s['filter'] ?? ''));
        $settings->usernameField = strtolower((string)(($s['username_field'] ?? '') ?: 'mail'));
        $settings->attributeField = strtolower(trim((string)($s['attribute_field'] ?? '')));
        $settings->idAttribute = strtolower(trim((string)($s['id_attribute'] ?? '')));
        $settings->disabledFilter = trim((string)($s['disabled_filter'] ?? ''));
        $settings->groupBasedn = trim((string)($s['group_basedn'] ?? ''));
        $settings->groupFilter = trim((string)($s['group_filter'] ?? ''));
        $settings->groupNameAttribute = strtolower((string)(($s['group_name_attribute'] ?? '') ?: 'cn'));
        $mode = (string)($s['group_member_mode'] ?? 'memberof');
        $settings->groupMemberMode = in_array($mode, self::MEMBER_MODES, true) ? $mode : 'memberof';
        $settings->groupMemberAttribute = strtolower((string)(($s['group_member_attribute'] ?? '') ?: 'member'));
        return $settings;
    }

    /** Only Keycloak and LDAP can be listed by the sync */
    public function canSync(): bool
    {
        return in_array($this->provider, [self::KEYCLOAK, self::LDAP], true);
    }

    public function syncEnabled(): bool
    {
        return $this->canSync() && ($this->periodicSync || $this->importUsers || $this->offboarding || $this->groupSync);
    }

    /** Template of the first mapping (in configured order) matching one of the values, null if none */
    public function mappedTemplate(array $values): ?string
    {
        $values = array_map('strval', array_filter($values, 'is_scalar'));
        foreach ($this->mappers as $key => $mapper) {
            if (in_array($mapper, $values, true) && ($this->templates[$key] ?? '') !== '') {
                return $this->templates[$key];
            }
        }
        return null;
    }

    private static function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
