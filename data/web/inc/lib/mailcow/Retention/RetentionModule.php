<?php

declare(strict_types=1);

namespace Mailcow\Retention;

use Mailcow\Core\ApiResponse;
use Mailcow\Core\Context;
use Mailcow\Retention\Controller\RetentionApiController;
use Mailcow\Retention\Repository\RuleRepository;
use Mailcow\Retention\Service\RuleService;
use PDO;

/**
 * Composition root of the retention rules: which mail is deleted from which folders after how many days. The
 * nightly run in the dovecot container (retention_runner.py) gets its plan from data/conf/rspamd/dynmaps/retention.php.
 *
 *   process_get_return(RetentionModule::fromGlobals()->api->getRules());
 *   echo json_encode(RetentionModule::create($pdo, Context::system())->api->plan());  // nightly run
 */
class RetentionModule
{
    public function __construct(
        public readonly RuleService $rules,
        public readonly RetentionApiController $api
    ) {
    }

    public static function create(PDO $pdo, Context $context): self
    {
        $rules = new RuleService(new RuleRepository($pdo), $context);
        return new self($rules, new RetentionApiController($rules, new ApiResponse()));
    }

    /** Legacy entry point for procedural callers: reads $pdo and $_SESSION */
    public static function fromGlobals(?Context $context = null): self
    {
        return self::create($GLOBALS['pdo'], $context ?? Context::fromSession($_SESSION ?? []));
    }
}
