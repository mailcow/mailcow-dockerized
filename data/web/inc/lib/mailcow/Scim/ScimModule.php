<?php

declare(strict_types=1);

namespace Mailcow\Scim;

use Mailcow\Core\ApiResponse;
use Mailcow\Core\Context;
use Mailcow\MailboxGroup\MailboxGroupModule;
use Mailcow\Scim\Controller\ScimController;
use Mailcow\Scim\Controller\TokenApiController;
use Mailcow\Scim\Legacy\LegacyMailboxes;
use Mailcow\Scim\Repository\GroupRepository;
use Mailcow\Scim\Repository\TokenRepository;
use Mailcow\Scim\Repository\UserRepository;
use Mailcow\Scim\Service\GroupService;
use Mailcow\Scim\Service\TokenService;
use Mailcow\Scim\Service\UserService;
use PDO;

/**
 * Composition root of the SCIM 2.0 provider: IdPs provision users and groups through /scim/v2 (scim.php),
 * admins manage the tokens (json_api route `scim-token`).
 *
 *   $response = ScimModule::fromGlobals()->http->handle($method, $path, $_GET, $body, $contentType, $authorization, $ip);
 *   process_add_return(ScimModule::fromGlobals()->api->addToken($attr));
 */
class ScimModule
{
    public function __construct(public readonly TokenService $tokens, public readonly UserService $users,
        public readonly GroupService $groups, public readonly ScimController $http, public readonly TokenApiController $api)
    {
    }

    /** $config: base_url (absolute URL of /scim/v2), redis (SCIM_LOG and fail2ban, optional), log_lines (SCIM_LOG length) */
    public static function create(PDO $pdo, Context $context, array $config = []): self
    {
        $tokens = new TokenService(new TokenRepository($pdo), $context);
        $users = new UserRepository($pdo);
        $mailboxGroups = MailboxGroupModule::create($pdo, $context);
        $legacy = new LegacyMailboxes();
        $groups = new GroupService(new GroupRepository($pdo), $users, $mailboxGroups->groups, $mailboxGroups->idp, $legacy, $mailboxGroups->templates);
        $userService = new UserService($users, $legacy, $groups, $mailboxGroups->templates);
        return new self($tokens, $userService, $groups,
            new ScimController($tokens, $userService, $groups, (string)($config['base_url'] ?? ''), $config['redis'] ?? null,
                (int)($config['log_lines'] ?? 1000)),
            new TokenApiController($tokens, new ApiResponse()));
    }

    /** Legacy entry point for procedural callers: reads $pdo, $redis, $_SESSION and MAILCOW_HOSTNAME */
    public static function fromGlobals(?Context $context = null): self
    {
        $host = getenv('MAILCOW_HOSTNAME') ?: ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return self::create($GLOBALS['pdo'], $context ?? Context::fromSession($_SESSION ?? []),
            ['base_url' => $scheme . '://' . $host . '/scim/v2', 'redis' => $GLOBALS['redis'] ?? null, 'log_lines' => $GLOBALS['LOG_LINES'] ?? 1000]);
    }
}
