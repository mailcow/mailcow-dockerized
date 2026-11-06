<?php

declare(strict_types=1);

namespace Mailcow\Auth;

use Mailcow\Auth\Service\IdpLoginService;
use Mailcow\Core\Context;
use Mailcow\Identity\IdentityModule;
use Mailcow\MailboxGroup\MailboxGroupModule;
use PDO;

/**
 * Logins. For now the IdP login steps of the legacy flows (keycloak_mbox_login_rest(), ldap_mbox_login(),
 * identity_provider('verify-sso')); the auth logic of the function files moves here step by step.
 */
class AuthModule
{
    public function __construct(public readonly IdpLoginService $idp)
    {
    }

    /** @param array $iamSettings identity provider settings as identity_provider('get') returns them */
    public static function create(PDO $pdo, Context $context, array $iamSettings): self
    {
        $identity = IdentityModule::create($pdo, $context, $iamSettings);
        return new self(new IdpLoginService($identity->settings, $identity->links, MailboxGroupModule::create($pdo, $context)->templates));
    }

    /** Logins run before a session exists, so the default context is the system */
    public static function fromGlobals(?Context $context = null): self
    {
        $settings = $GLOBALS['iam_settings'] ?? null;
        return self::create($GLOBALS['pdo'], $context ?? Context::system(), is_array($settings) ? $settings : identity_provider('get'));
    }
}
