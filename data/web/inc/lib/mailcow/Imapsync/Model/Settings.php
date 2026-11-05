<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Model;

/**
 * Global sync job settings (table `imapsync_settings`), read by the runner.
 */
class Settings
{
    public int $maxParallel = 1;
    /** Global bandwidth limit in bytes/s, 0 = unlimited */
    public int $maxBytesPerSecond = 0;

    /**
     * API representation (GET imapsync_settings); the UI edits the limit in KB/s.
     */
    public function toArray(): array
    {
        return [
            'max_parallel' => $this->maxParallel,
            'max_bytes_per_second' => $this->maxBytesPerSecond,
            'max_kb_per_second' => intdiv($this->maxBytesPerSecond, 1024),
        ];
    }
}
