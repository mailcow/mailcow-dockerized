<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup;

use Mailcow\Core\ApiResponse;
use Mailcow\Core\Context;
use Mailcow\Dovecot\DovecotModule;
use Mailcow\MailboxGroup\Controller\MailboxGroupApiController;
use Mailcow\MailboxGroup\Legacy\LegacyMailbox;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\MailboxGroup\Repository\LookupRepository;
use Mailcow\MailboxGroup\Service\AccountHooks;
use Mailcow\MailboxGroup\Service\ChangeEffects;
use Mailcow\MailboxGroup\Service\GroupService;
use Mailcow\MailboxGroup\Service\IdpSyncService;
use Mailcow\MailboxGroup\Service\SharingService;
use Mailcow\MailboxGroup\Service\TemplateService;
use Mailcow\Sogo\SogoModule;
use PDO;

/**
 * Composition root of the mailbox group module.
 *
 *   process_add_return(MailboxGroupModule::fromGlobals()->api->addGroup($attr));
 *   MailboxGroupModule::fromGlobals(Context::system())->templates->templateForLogin($username, $legacyTemplate);
 *   MailboxGroupModule::create($pdo, Context::system())->sharing->reconcile();  // cron
 */
class MailboxGroupModule
{
    public function __construct(
        public readonly GroupService $groups,
        public readonly TemplateService $templates,
        public readonly AccountHooks $hooks,
        public readonly IdpSyncService $idp,
        public readonly SharingService $sharing,
        public readonly MailboxGroupApiController $api
    ) {
    }

    public static function create(PDO $pdo, Context $context): self
    {
        $repo = new GroupRepository($pdo);
        $lookup = new LookupRepository($pdo);
        $dovecot = DovecotModule::create()->dovecot;
        $sogo = SogoModule::create()->sogo;
        $templates = new TemplateService($lookup, new LegacyMailbox());
        $effects = new ChangeEffects($templates, $sogo, $repo, $dovecot);
        $sharing = new SharingService($repo, $dovecot, $sogo, $effects);
        $groups = new GroupService($repo, $lookup, $templates, $effects, $sharing, $context);
        $hooks = new AccountHooks($repo, $effects);
        return new self($groups, $templates, $hooks, new IdpSyncService($repo, $lookup, $effects), $sharing,
            new MailboxGroupApiController($groups, $hooks, $templates, new ApiResponse()));
    }

    /** Legacy entry point for procedural callers: reads $pdo and $_SESSION; $context overrides the session caller */
    public static function fromGlobals(?Context $context = null): self
    {
        return self::create($GLOBALS['pdo'], $context ?? Context::fromSession($_SESSION ?? []));
    }
}
