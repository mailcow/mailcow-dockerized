<?php

declare(strict_types=1);

namespace Mailcow\Identity;

use Closure;
use LdapRecord\Connection;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\Context;
use Mailcow\Identity\Controller\IdentityApiController;
use Mailcow\Identity\Directory\Directory;
use Mailcow\Identity\Directory\KeycloakDirectory;
use Mailcow\Identity\Directory\LdapDirectory;
use Mailcow\Identity\Http\KeycloakClient;
use Mailcow\Identity\Legacy\LegacyAccounts;
use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Repository\IdentityRepository;
use Mailcow\Identity\Repository\RunRepository;
use Mailcow\Identity\Service\LinkService;
use Mailcow\Identity\Service\StatusService;
use Mailcow\Identity\Service\SyncPlanner;
use Mailcow\Identity\Service\SyncService;
use Mailcow\MailboxGroup\MailboxGroupModule;
use Mailcow\Sogo\SogoModule;
use PDO;

/**
 * Composition root of the identity module (IdP links, sync, offboarding).
 *
 *   IdentityModule::create($pdo, Context::system(), identity_provider('get'), fn() => identity_provider('init'))->sync->runDue();  // cron
 *   IdentityModule::fromGlobals()->api->getLink($username);  // edit page
 *
 * Logins use the links through Mailcow\Auth (AuthModule::fromGlobals()->idp).
 */
class IdentityModule
{
    public function __construct(
        public readonly Settings $settings,
        public readonly SyncService $sync,
        public readonly LinkService $links,
        public readonly StatusService $status,
        public readonly IdentityApiController $api
    ) {
    }

    /**
     * @param array $settings identity_provider('get')
     * @param ?Closure $ldap returns the bound LDAP connection (identity_provider('init')), called when a run starts
     */
    public static function create(PDO $pdo, Context $context, array $settings, ?Closure $ldap = null): self
    {
        $config = Settings::fromArray($settings);
        $repo = new IdentityRepository($pdo);
        $runs = new RunRepository($pdo);
        $groups = MailboxGroupModule::create($pdo, Context::system());
        $status = new StatusService($config, $repo, $runs, $context);
        $directory = fn(): ?Directory => self::directory($config, $ldap);
        return new self($config,
            new SyncService($config, $directory, $repo, $runs, new SyncPlanner(), $groups->idp, $groups->templates, new LegacyAccounts(),
                SogoModule::create()->sogo),
            new LinkService($repo), $status, new IdentityApiController($status, new ApiResponse()));
    }

    /** Legacy entry point for procedural callers: reads $pdo, $iam_settings and $_SESSION; $context overrides the session caller */
    public static function fromGlobals(?Context $context = null): self
    {
        $settings = $GLOBALS['iam_settings'] ?? null;
        return self::create($GLOBALS['pdo'], $context ?? Context::fromSession($_SESSION ?? []),
            is_array($settings) ? $settings : identity_provider('get'), fn(): mixed => $GLOBALS['iam_provider'] ?? null);
    }

    private static function directory(Settings $settings, ?Closure $ldap): ?Directory
    {
        if ($settings->provider === Settings::KEYCLOAK) {
            return new KeycloakDirectory(new KeycloakClient($settings->serverUrl, $settings->realm, $settings->clientId,
                $settings->clientSecret, $settings->ignoreSslError), $settings);
        }
        if ($settings->provider === Settings::LDAP) {
            $connection = ($ldap === null) ? null : $ldap();
            return new LdapDirectory(($connection instanceof Connection) ? $connection : null, $settings);
        }
        return null;
    }
}
