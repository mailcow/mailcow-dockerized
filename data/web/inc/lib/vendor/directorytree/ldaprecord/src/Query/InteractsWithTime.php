<?php

namespace LdapRecord\Query;

use Carbon\Carbon;
use DateInterval;
use DateTimeInterface;

/**
 * @author Taylor Otwell
 *
 * @see https://laravel.com
 */
trait InteractsWithTime
{
    /**
     * Get the number of seconds until the given DateTime.
     */
    protected function secondsUntil(DateTimeInterface|DateInterval|int|null $delay = null): int
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface
            ? max(0, $delay->getTimestamp() - $this->currentTime())
            : (int) $delay;
    }

    /**
     * Get the "available at" UNIX timestamp.
     */
    protected function availableAt(DateTimeInterface|DateInterval|int $delay = 0): int
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface
            ? $delay->getTimestamp()
            : Carbon::now()->addRealSeconds($delay)->getTimestamp();
    }

    /**
     * If the given value is an interval, convert it to a DateTime instance.
     */
    protected function parseDateInterval(DateTimeInterface|DateInterval|int|null $delay = null): DateTimeInterface|int|null
    {
        if ($delay instanceof DateInterval) {
            $delay = Carbon::now()->add($delay);
        }

        return $delay;
    }

    /**
     * Get the current system time as a UNIX timestamp.
     */
    protected function currentTime(): int
    {
        return Carbon::now()->getTimestamp();
    }
}
