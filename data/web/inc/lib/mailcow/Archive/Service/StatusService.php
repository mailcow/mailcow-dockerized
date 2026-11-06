<?php

declare(strict_types=1);

namespace Mailcow\Archive\Service;

use Mailcow\Archive\Legacy\LegacyQueue;
use Mailcow\Archive\Model\Settings;
use Mailcow\Archive\Redis\ArchiveState;
use Mailcow\Archive\Repository\SettingsRepository;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;

/**
 * State of the archive for the admin: the summary of the archive tab (copies waiting in the Postfix queue with the
 * reason Postfix gives, last delivery, mails not archived) and the archive log of the dashboard.
 */
class StatusService
{
    /** Mails not archived count as a problem for a day */
    private const DROPPED_WINDOW = 86400;

    public function __construct(private SettingsRepository $settings, private ArchiveState $state, private LegacyQueue $queue,
        private Context $context)
    {
    }

    /**
     * GET archive/status (admins, read-only keys included).
     *
     * @throws AccessDenied
     */
    public function status(): array
    {
        $this->context->requireAdmin(false);
        $settings = $this->settings->load();
        $copies = $this->queue->entries(Settings::SENDER);
        $reasons = [];
        $oldest = null;
        $bytes = 0;
        foreach ($copies as $copy) {
            $arrival = (int)($copy['arrival_time'] ?? 0);
            $oldest = ($oldest === null) ? $arrival : min($oldest, $arrival);
            $bytes += (int)($copy['message_size'] ?? 0);
            foreach ((array)($copy['recipients'] ?? []) as $rcpt) {
                if (is_string($rcpt) && preg_match('/^\S+ \((.+)\)$/', $rcpt, $m)) {
                    $reasons[$m[1]] = true;
                }
            }
        }
        $dropped = $this->state->droppedStats();
        $recent = array_values(array_filter($dropped['recent'], fn(array $d): bool => $d['time'] >= time() - self::DROPPED_WINDOW));
        $lastDelivery = null;
        foreach ($this->state->events() as $event) {
            if ($event['status'] === 'sent') {
                $lastDelivery = $event['time'];
                break;
            }
        }
        return [
            'enabled' => (int)$settings->enabled,
            'configured' => (int)$settings->canDeliver(),
            'queued' => count($copies),
            'queued_bytes' => $bytes,
            'oldest_queued' => $oldest,
            'delay_reasons' => array_slice(array_keys($reasons), 0, 5),
            'last_delivery' => $lastDelivery,
            'dropped' => $dropped['count'],
            'dropped_24h' => count($recent),
            'last_dropped' => $recent ? ['time' => $recent[0]['time'], 'reason' => $recent[0]['reason']] : null,
        ];
    }

    /**
     * GET logs/archive: deliveries, expired copies and mails not archived, newest first (admins, read-only keys included);
     * $count limits the entries, 0 keeps the default.
     *
     * @throws AccessDenied
     */
    public function log(int $count = 0): array
    {
        $this->context->requireAdmin(false);
        return ($count > 0) ? $this->state->events($count) : $this->state->events();
    }
}
