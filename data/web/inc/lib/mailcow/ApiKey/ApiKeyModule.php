<?php

declare(strict_types=1);

namespace Mailcow\ApiKey;

use Mailcow\ApiKey\Controller\ApiKeyApiController;
use Mailcow\ApiKey\Repository\ApiKeyRepository;
use Mailcow\ApiKey\Service\ApiKeyService;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\Context;
use PDO;

/**
 * Composition root of the API key module.
 *
 *   process_add_return(ApiKeyModule::fromGlobals()->api->addKey($attr));
 *   ApiKeyModule::create($pdo, Context::system())->keys->syncServiceKeys(getenv());  // php-fpm start
 */
class ApiKeyModule
{
    /** Request keys whose values never reach a log */
    private const SECRET_KEYS = ['api_key'];

    public function __construct(public readonly ApiKeyService $keys, public readonly ApiKeyApiController $api)
    {
    }

    public static function create(PDO $pdo, Context $context): self
    {
        $keys = new ApiKeyService(new ApiKeyRepository($pdo), $context);
        return new self($keys, new ApiKeyApiController($keys, new ApiResponse(self::SECRET_KEYS)));
    }

    /** Legacy entry point for procedural callers: reads $pdo and $_SESSION; $context overrides the session caller */
    public static function fromGlobals(?Context $context = null): self
    {
        return self::create($GLOBALS['pdo'], $context ?? Context::fromSession($_SESSION ?? []));
    }
}
