<?php

declare(strict_types=1);

namespace Mailcow\Archive\Legacy;

/**
 * Bridge to the legacy mailq() (Postfix queue through dockerapi) until it is converted.
 */
class LegacyQueue
{
    /**
     * @return array[] queue entries of $sender: queue_id, queue_name, arrival_time, message_size,
     *                 recipients ("address (delay reason)")
     */
    public function entries(string $sender): array
    {
        if (!function_exists('mailq')) {
            return [];
        }
        $list = json_decode((string)mailq('get'), true);
        if (!is_array($list)) {
            return [];
        }
        return array_values(array_filter($list, fn(mixed $entry): bool => is_array($entry) && ($entry['sender'] ?? '') === $sender));
    }
}
