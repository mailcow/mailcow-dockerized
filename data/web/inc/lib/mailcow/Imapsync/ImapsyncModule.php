<?php

declare(strict_types=1);

namespace Mailcow\Imapsync;

use Mailcow\Core\ApiResponse;
use Mailcow\Core\Context;
use Mailcow\Imapsync\Controller\ImapsyncApiController;
use Mailcow\Imapsync\Http\OAuthClient;
use Mailcow\Imapsync\Model\CustomParams;
use Mailcow\Imapsync\Repository\JobRepository;
use Mailcow\Imapsync\Repository\SettingsRepository;
use Mailcow\Imapsync\Repository\SourceRepository;
use Mailcow\Imapsync\Repository\TokenRepository;
use Mailcow\Imapsync\Service\JobService;
use Mailcow\Imapsync\Service\OAuthService;
use Mailcow\Imapsync\Service\SourceService;
use PDO;

/**
 * Composition root of the sync job module: wires repositories, services and the API controller.
 *
 *   $imapsync = ImapsyncModule::create($pdo, Context::fromSession($_SESSION), [
 *     'allowlist' => CustomParams::allowlist($IMAPSYNC_OPTIONS, $IMAPSYNC_OPTIONS_DEFAULT),
 *     'hostname' => (string)getenv('MAILCOW_HOSTNAME'),
 *   ]);
 *   process_add_return($imapsync->api->addJob($attr));
 *
 * Procedural legacy code (json_api.php, pages, functions.*.inc.php) uses fromGlobals() instead.
 */
class ImapsyncModule
{
    /** Request keys whose values never reach a log */
    private const SECRET_KEYS = ['password1', 'password2', 'oauth_client_secret', 'oauth_extra_params'];

    public function __construct(
        public readonly SourceService $sources,
        public readonly JobService $jobs,
        public readonly OAuthService $oauth,
        public readonly ImapsyncApiController $api
    ) {
    }

    /**
     * @param array $config 'allowlist' => effective custom param allowlist (CustomParams::allowlist()),
     *                      'hostname'  => MAILCOW_HOSTNAME (OAuth redirect URI); both optional
     */
    public static function create(PDO $pdo, Context $context, array $config = []): self
    {
        $sourceRepository = new SourceRepository($pdo);
        $tokenRepository = new TokenRepository($pdo);
        $oauth = new OAuthService($sourceRepository, $tokenRepository, new OAuthClient(), (string)($config['hostname'] ?? ''));
        $sources = new SourceService($sourceRepository, $tokenRepository, $oauth, $context);
        $jobs = new JobService(new JobRepository($pdo), $sources, $tokenRepository, new SettingsRepository($pdo),
            $context, (array)($config['allowlist'] ?? []));
        return new self($sources, $jobs, $oauth, new ImapsyncApiController($sources, $jobs, new ApiResponse(self::SECRET_KEYS)));
    }

    /**
     * Legacy entry point for procedural callers: the one place that reads the globals ($pdo, $_SESSION,
     * $IMAPSYNC_OPTIONS / $IMAPSYNC_OPTIONS_DEFAULT from vars(.local).inc.php, MAILCOW_HOSTNAME)
     * and passes them to create(). $context overrides the session caller (e.g. Context::system()).
     */
    public static function fromGlobals(?Context $context = null): self
    {
        return self::create($GLOBALS['pdo'], $context ?? Context::fromSession($_SESSION ?? []), [
            'allowlist' => CustomParams::allowlist((array)($GLOBALS['IMAPSYNC_OPTIONS'] ?? []),
                (array)($GLOBALS['IMAPSYNC_OPTIONS_DEFAULT'] ?? [])),
            'hostname' => (string)getenv('MAILCOW_HOSTNAME'),
        ]);
    }
}
