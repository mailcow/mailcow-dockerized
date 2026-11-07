<?php

declare(strict_types=1);

namespace Mailcow\MailPolicy;

use Mailcow\Core\ApiResponse;
use Mailcow\Core\Context;
use Mailcow\MailPolicy\Controller\MailPolicyApiController;
use Mailcow\MailPolicy\Repository\LookupRepository;
use Mailcow\MailPolicy\Repository\PolicyRepository;
use Mailcow\MailPolicy\Service\PolicyEngine;
use Mailcow\MailPolicy\Service\PolicyService;
use PDO;

/**
 * Composition root of the mail policies: whom mailboxes and groups may send to and receive from, checked by
 * Postfix at RCPT TO through the policy service (mailcow-policy in master.cf, data/conf/rspamd/dynmaps/mailpolicy.php).
 *
 *   process_get_return(MailPolicyModule::fromGlobals()->api->getPolicies());
 *   echo MailPolicyModule::create($pdo, Context::system())->api->check($sender, $recipient, $login, $port);  // policy service
 */
class MailPolicyModule
{
    public function __construct(
        public readonly PolicyService $policies,
        public readonly PolicyEngine $engine,
        public readonly MailPolicyApiController $api
    ) {
    }

    public static function create(PDO $pdo, Context $context): self
    {
        $engine = new PolicyEngine(new LookupRepository($pdo));
        $policies = new PolicyService(new PolicyRepository($pdo), $engine, $context);
        return new self($policies, $engine, new MailPolicyApiController($policies, $engine, new ApiResponse()));
    }

    /** Legacy entry point for procedural callers: reads $pdo and $_SESSION */
    public static function fromGlobals(?Context $context = null): self
    {
        return self::create($GLOBALS['pdo'], $context ?? Context::fromSession($_SESSION ?? []));
    }
}
