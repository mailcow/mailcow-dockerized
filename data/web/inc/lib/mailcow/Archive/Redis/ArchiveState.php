<?php

declare(strict_types=1);

namespace Mailcow\Archive\Redis;

use Mailcow\Archive\Model\Settings;

/**
 * Redis side of the archive:
 * - ARCHIVE_ENABLED: the switch rspamd reads before it asks for owners, mirrored from the settings.
 * - ARCHIVE_DROPPED / ARCHIVE_DROPPED_LOG: copies that could not be handed to Postfix (rspamd.local.lua, release
 *   hooks), counted and the last 100 listed.
 * - POSTFIX_MAILLOG: the Postfix log, read for the archive log: deliveries of the archive_smtp service and expired
 *   copies, with the Message-ID of the mail (a copy keeps the Message-ID of the original).
 *
 * The connection is a plain object, so the class loads without the redis extension; without a connection
 * nothing is read or written.
 */
class ArchiveState
{
    public const ENABLED = 'ARCHIVE_ENABLED';
    public const DROPPED = 'ARCHIVE_DROPPED';
    public const DROPPED_LOG = 'ARCHIVE_DROPPED_LOG';
    private const MAILLOG = 'POSTFIX_MAILLOG';

    public function __construct(private ?object $redis)
    {
    }

    public function setEnabled(bool $enabled): void
    {
        $this->redis?->set(self::ENABLED, $enabled ? '1' : '0');
    }

    /** A mail was not archived: its copy could not be handed to Postfix */
    public function dropped(string $qid, string $reason, string $messageId = ''): void
    {
        if ($this->redis === null) {
            return;
        }
        $this->redis->incr(self::DROPPED);
        $this->redis->lPush(self::DROPPED_LOG, json_encode(['time' => time(), 'qid' => $qid, 'reason' => $reason, 'message_id' => $messageId]));
        $this->redis->lTrim(self::DROPPED_LOG, 0, 99);
    }

    /** @return array{count: int, recent: array[]} count since ever; recent: time, qid, reason, message_id; newest first */
    public function droppedStats(int $limit = 100): array
    {
        if ($this->redis === null) {
            return ['count' => 0, 'recent' => []];
        }
        $recent = [];
        foreach ((array)$this->redis->lRange(self::DROPPED_LOG, 0, $limit - 1) as $line) {
            $entry = is_string($line) ? json_decode($line, true) : null;
            if (is_array($entry)) {
                $recent[] = ['time' => (int)($entry['time'] ?? 0), 'qid' => (string)($entry['qid'] ?? ''),
                    'reason' => (string)($entry['reason'] ?? ''), 'message_id' => (string)($entry['message_id'] ?? '')];
            }
        }
        return ['count' => (int)$this->redis->get(self::DROPPED), 'recent' => $recent];
    }

    /**
     * Archive log, newest first: deliveries of copies (status sent|deferred|softbounce|bounced, text = answer of the
     * archive), expired copies (expired) and mails not archived (dropped, text = reason).
     *
     * @return array[] time, status, qid (of the copy, of the mail for dropped), message_id, text
     */
    public function events(int $limit = 1000): array
    {
        if ($this->redis === null) {
            return [];
        }
        $events = [];
        $messageIds = [];
        foreach ((array)$this->redis->lRange(self::MAILLOG, 0, -1) as $line) {
            $entry = is_string($line) ? json_decode($line, true) : null;
            if (!is_array($entry)) {
                continue;
            }
            $program = (string)($entry['program'] ?? '');
            $message = (string)($entry['message'] ?? '');
            $time = (int)($entry['time'] ?? 0);
            if ($program === 'postfix/cleanup' && preg_match('/^([0-9A-Za-z]+): message-id=(\S*)/', $message, $m)) {
                $messageIds[$m[1]] = trim($m[2], '<>');
            }
            // the archive_discard lines are the dropped notices, not deliveries
            elseif ($program === 'postfix/archive/smtp' && preg_match('/^([0-9A-Za-z]+): to=<[^>]*>.*\bstatus=(\w+) \((.*)\)$/', $message, $m)) {
                $events[] = ['time' => $time, 'status' => strtolower($m[2]), 'qid' => $m[1], 'message_id' => '', 'text' => $m[3]];
            }
            elseif ($program === 'postfix/qmgr' && preg_match('/^([0-9A-Za-z]+): from=<' . preg_quote(Settings::SENDER, '/') . '>, status=expired/', $message, $m)) {
                $events[] = ['time' => $time, 'status' => 'expired', 'qid' => $m[1], 'message_id' => '', 'text' => ''];
            }
        }
        foreach ($events as &$event) {
            $event['message_id'] = $messageIds[$event['qid']] ?? '';
        }
        unset($event);
        foreach ($this->droppedStats()['recent'] as $dropped) {
            $events[] = ['time' => $dropped['time'], 'status' => 'dropped', 'qid' => $dropped['qid'],
                'message_id' => $dropped['message_id'], 'text' => $dropped['reason']];
        }
        usort($events, fn(array $a, array $b): int => $b['time'] <=> $a['time']);
        return array_slice($events, 0, $limit);
    }
}
