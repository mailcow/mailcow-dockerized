<?php

declare(strict_types=1);

namespace Mailcow\Archive;

use Mailcow\Archive\Controller\ArchiveApiController;
use Mailcow\Archive\Legacy\LegacyQueue;
use Mailcow\Archive\Redis\ArchiveState;
use Mailcow\Archive\Repository\LookupRepository;
use Mailcow\Archive\Repository\SettingsRepository;
use Mailcow\Archive\Service\HeaderBuilder;
use Mailcow\Archive\Service\JournalService;
use Mailcow\Archive\Service\MailboxListService;
use Mailcow\Archive\Service\OwnerResolver;
use Mailcow\Archive\Service\SettingsService;
use Mailcow\Archive\Service\StatusService;
use Mailcow\Archive\Smtp\SmtpClient;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\Context;
use PDO;

/**
 * Composition root of the archive module (archive copies by SMTP through Postfix).
 *
 *   process_get_return(ArchiveModule::fromGlobals()->api->getMailboxes());
 *   ArchiveModule::create($pdo, Context::system(), $redis)->journal->prepare($request);  // rspamd dynmap
 *   ArchiveModule::fromGlobals()->journal->submitDelivered($message, $from, $rcpts, 'quarantine');
 */
class ArchiveModule
{
    /** Request keys whose values never reach a log */
    private const SECRET_KEYS = ['password', 'signing_secret'];

    public function __construct(
        public readonly SettingsService $settings,
        public readonly JournalService $journal,
        public readonly StatusService $status,
        public readonly MailboxListService $mailboxes,
        public readonly ArchiveApiController $api
    ) {
    }

    /** @param ?object $redis Redis connection; without it nothing is read from or written to Redis */
    public static function create(PDO $pdo, Context $context, ?object $redis = null): self
    {
        $settingsRepo = new SettingsRepository($pdo);
        $lookup = new LookupRepository($pdo);
        $state = new ArchiveState($redis);
        $smtp = new SmtpClient();
        $settings = new SettingsService($settingsRepo, $lookup, $state, $smtp, $context);
        $journal = new JournalService($settingsRepo, new OwnerResolver($lookup), new HeaderBuilder(), $smtp, $state, $context);
        $status = new StatusService($settingsRepo, $state, new LegacyQueue(), $context);
        $mailboxes = new MailboxListService($lookup, $settingsRepo, $context);
        $api = new ArchiveApiController($settings, $status, $journal, $mailboxes, new ApiResponse(self::SECRET_KEYS));
        return new self($settings, $journal, $status, $mailboxes, $api);
    }

    /** Legacy entry point for procedural callers: reads $pdo, $redis and $_SESSION; $context overrides the session caller */
    public static function fromGlobals(?Context $context = null): self
    {
        $redis = $GLOBALS['redis'] ?? null;
        return self::create($GLOBALS['pdo'], $context ?? Context::fromSession($_SESSION ?? []), is_object($redis) ? $redis : null);
    }
}
