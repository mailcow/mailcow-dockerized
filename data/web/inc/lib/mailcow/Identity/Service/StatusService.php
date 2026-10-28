<?php

declare(strict_types=1);

namespace Mailcow\Identity\Service;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Mailcow\Identity\Model\Settings;
use Mailcow\Identity\Repository\IdentityRepository;
use Mailcow\Identity\Repository\RunRepository;

/**
 * Admin use cases of the sync: status, run requests (executed by the cron) and links of mailboxes.
 * Admins only, read-only API keys may read. Domain admins may read the link of mailboxes they manage.
 */
class StatusService
{
    public const MODES = ['preview', 'run', 'force'];

    public function __construct(private Settings $settings, private IdentityRepository $repo, private RunRepository $runs,
        private Context $context)
    {
    }

    /** @throws AccessDenied */
    public function status(): array
    {
        $this->context->requireAdmin(false);
        return [
            'provider' => $this->settings->provider,
            'can_sync' => $this->settings->canSync(),
            'sync_enabled' => $this->settings->syncEnabled(),
            'counts' => $this->repo->stats($this->settings->provider),
            'runs' => $this->runs->recent(),
        ];
    }

    /**
     * Queues a run for the cron: preview (dry run), run, or force (run without the change limit).
     *
     * @throws UserError
     */
    public function requestRun(mixed $mode): int
    {
        $this->context->requireAdmin();
        $mode = Input::text($mode);
        if (!in_array($mode, self::MODES, true)) {
            throw new UserError('iam_sync_mode_invalid');
        }
        if (!$this->settings->canSync()) {
            throw new UserError('iam_sync_unavailable');
        }
        return $this->runs->request($this->settings->provider, $mode, (string)$this->context->username);
    }

    /** Link of a mailbox for the mailbox edit page, null if none or not allowed */
    public function link(string $username): ?array
    {
        $username = strtolower(trim($username));
        if (!($this->context->hasAdminRights() || ($this->context->isDomainAdmin() && $this->context->canAccessMailbox($username)))) {
            return null;
        }
        return $this->repo->findLink($username);
    }

    /**
     * Drops the link, the mailbox is linked again by email (next run or login). Resolves conflicts, e.g. when an
     * address was given to another IdP user.
     *
     * @throws AccessDenied
     */
    public function unlink(string $username): void
    {
        $this->context->requireAdmin();
        $this->repo->unlink(strtolower(trim($username)));
    }
}
