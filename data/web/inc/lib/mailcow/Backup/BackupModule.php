<?php

declare(strict_types=1);

namespace Mailcow\Backup;

use Mailcow\Backup\Controller\BackupApiController;
use Mailcow\Backup\Legacy\LegacyDomain;
use Mailcow\Backup\Legacy\NoTemplates;
use Mailcow\Backup\Model\Settings;
use Mailcow\Backup\Repository\ExportRepository;
use Mailcow\Backup\Repository\JobRepository;
use Mailcow\Backup\Repository\RestoreRepository;
use Mailcow\Backup\Repository\SettingsRepository;
use Mailcow\Backup\Repository\SnapshotRepository;
use Mailcow\Backup\Service\DomainRestoreService;
use Mailcow\Backup\Service\ExportService;
use Mailcow\Backup\Service\GroupMembers;
use Mailcow\Backup\Service\JobService;
use Mailcow\Backup\Service\RestoreService;
use Mailcow\Backup\Service\SettingsService;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\Context;
use Mailcow\Dovecot\DovecotModule;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\MailboxGroup\Repository\LookupRepository;
use Mailcow\MailboxGroup\Service\ChangeEffects;
use Mailcow\MailboxGroup\Service\SharingService;
use Mailcow\MailboxGroup\Service\TemplateService;
use Mailcow\Sogo\SogoModule;
use PDO;
use Redis;

/**
 * Composition root of the backup with restic: settings, jobs of the runners, snapshots, the export of a domain and the
 * restore of mailboxes and domains. The runner in the restic container reaches the export and the restore actions
 * through data/conf/rspamd/dynmaps/backup.php.
 *
 *   process_get_return(BackupModule::fromGlobals()->api->getSettings());
 *   echo json_encode(BackupModule::create($pdo, Context::system(), $config, $redis)->api->export($domain, $page));  // backup.php
 *
 * $config: timezone (TZ of mailcow, for the schedule), alias_disable_external_domains ($ALIAS_DISABLE_EXTERNAL_DOMAINS).
 * $redis: for DKIM keys, rate limits and the domain map (export and restore).
 */
class BackupModule
{
    public function __construct(
        public readonly SettingsService $settings,
        public readonly JobService $jobs,
        public readonly ExportService $export,
        public readonly RestoreService $restore,
        public readonly DomainRestoreService $domainRestore,
        public readonly BackupApiController $api
    ) {
    }

    public static function create(PDO $pdo, Context $context, array $config = [], ?Redis $redis = null): self
    {
        $jobRepo = new JobRepository($pdo);
        $restoreRepo = new RestoreRepository($pdo);
        $legacy = new LegacyDomain($redis);
        $settings = new SettingsService(new SettingsRepository($pdo), $context);
        $jobs = new JobService($jobRepo, new SnapshotRepository($pdo), $settings, $context, (string)($config['timezone'] ?? 'UTC'));
        $export = new ExportService(new ExportRepository($pdo), $legacy);
        $sogo = SogoModule::create()->sogo;
        $dovecot = DovecotModule::create()->dovecot;
        // the group module without group templates: a restored mailbox keeps the settings of the backup
        $groupRepo = new GroupRepository($pdo);
        $lookup = new LookupRepository($pdo);
        $templates = new TemplateService($lookup, new NoTemplates());
        $effects = new ChangeEffects($templates, $sogo, $groupRepo, $dovecot);
        $groups = new GroupMembers($groupRepo, $lookup, $templates, $effects, new SharingService($groupRepo, $dovecot, $sogo, $effects));
        $restore = new RestoreService($restoreRepo, $jobRepo, $groups, $sogo, $dovecot, $legacy, (bool)($config['alias_disable_external_domains'] ?? false));
        $domainRestore = new DomainRestoreService($restoreRepo, $jobRepo, $groups, $legacy);
        return new self($settings, $jobs, $export, $restore, $domainRestore,
            new BackupApiController($settings, $jobs, $export, $restore, $domainRestore, new ApiResponse(Settings::SECRETS)));
    }

    /** Legacy entry point for procedural callers: reads $pdo, $redis, $_SESSION, TZ and $ALIAS_DISABLE_EXTERNAL_DOMAINS */
    public static function fromGlobals(?Context $context = null): self
    {
        return self::create($GLOBALS['pdo'], $context ?? Context::fromSession($_SESSION ?? []), [
            'timezone' => getenv('TZ') ?: 'UTC',
            'alias_disable_external_domains' => ($GLOBALS['ALIAS_DISABLE_EXTERNAL_DOMAINS'] ?? false) === true,
        ], ($GLOBALS['redis'] ?? null) instanceof Redis ? $GLOBALS['redis'] : null);
    }
}
