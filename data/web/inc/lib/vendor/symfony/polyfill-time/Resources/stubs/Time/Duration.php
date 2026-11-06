<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Time;

if (\PHP_VERSION_ID < 80600) {
    /**
     * @see https://wiki.php.net/rfc/duration_class
     */
    final class Duration
    {
        private const NANOS_PER_SECOND = 1_000_000_000;

        /**
         * Maximum seconds accepted by Duration.
         *
         * One second is reserved to avoid overflowing when arithmetic operations
         * temporarily require carrying nanoseconds into the seconds component.
         */
        private const MAX_SECONDS = \PHP_INT_SIZE >= 8 ? 9_223_372_035 : \PHP_INT_MAX;

        private const RANGE_ERROR = \PHP_INT_SIZE >= 8
            ? 'The maximum representable range is 9_223_372_035 seconds (roughly 292 years)'
            : 'The maximum representable range is 2_147_483_647 seconds (roughly 68 years)';

        /**
         * Regular expression to parse an ISO-8601 duration string.
         *
         * Year, Month, Week and Day components are intentionally not supported.
         * The biggest supported component is Hour.
         */
        private const REGEXP_ISO8601 = '@^
            PT
            (?=(?:\d+H|\d+M|\d+S)) # look ahead to avoid PT without argument
            (?:(?<hour>\d+)H)?
            (?:(?<minute>\d+)M)?
            (?:(?<second>\d+)S)?
        $@x';

        /**
         * Regular expression matching any ISO-8601 duration, including the
         * date components that Duration does not support.
         */
        private const REGEXP_ISO8601_ANY = '@^
            P
            (?:(?<year>\d+)Y)?
            (?:(?<month>\d+)M)?
            (?:(?<week>\d+)W)?
            (?:(?<day>\d+)D)?
            (?:T(?:\d+H)?(?:\d+M)?(?:\d+S)?)?
        $@x';

        private function __construct(
            public readonly int $seconds,
            public readonly int $nanoseconds,
            public readonly bool $negative,
        ) {}

        /**
         * Create a duration representing $seconds seconds and $nanoseconds nanoseconds. Neither parameter
         * may be negative. $nanoseconds must be less than 1_000_000_000 (the number of nanoseconds in a
         * second).
         *
         * This constructor creates a Duration from its “atomic” components.
         *
         * @throws TimeException
         */
        public static function fromSeconds(int $seconds, int $nanoseconds = 0): self
        {
            if ($seconds < 0) {
                throw new \ValueError(__METHOD__.'(): Argument #1 ($seconds) must be greater than or equal to 0');
            }

            if ($nanoseconds < 0) {
                throw new \ValueError(__METHOD__.'(): Argument #2 ($nanoseconds) must be greater than or equal to 0');
            }

            if ($nanoseconds >= self::NANOS_PER_SECOND) {
                throw new \ValueError(__METHOD__.'(): Argument #2 ($nanoseconds) must be less than 1_000_000_000');
            }

            return self::create($seconds, $nanoseconds, false);
        }

        /**
         * Create a duration representing $nanoseconds nanoseconds. $nanoseconds must not be negative.
         *
         * @throws TimeException
         */
        public static function fromNanoseconds(int $nanoseconds): self
        {
            if ($nanoseconds < 0) {
                throw new \ValueError(__METHOD__.'(): Argument #1 ($nanoseconds) must be greater than or equal to 0');
            }

            return self::create(intdiv($nanoseconds, self::NANOS_PER_SECOND), $nanoseconds % self::NANOS_PER_SECOND, false);
        }

        /**
         * Create a duration representing $microseconds microseconds. $microseconds must not be negative.
         *
         * @throws TimeException
         */
        public static function fromMicroseconds(int $microseconds): self
        {
            if ($microseconds < 0) {
                throw new \ValueError(__METHOD__.'(): Argument #1 ($microseconds) must be greater than or equal to 0');
            }

            return self::create(intdiv($microseconds, 1_000_000), ($microseconds % 1_000_000) * 1_000, false);
        }

        /**
         * Create a duration representing $milliseconds milliseconds. $milliseconds must not be negative.
         *
         * @throws TimeException
         */
        public static function fromMilliseconds(int $milliseconds): self
        {
            if ($milliseconds < 0) {
                throw new \ValueError(__METHOD__.'(): Argument #1 ($milliseconds) must be greater than or equal to 0');
            }

            return self::create(intdiv($milliseconds, 1_000), ($milliseconds % 1_000) * 1_000_000, false);
        }

        /**
         * Create a duration representing $minutes minutes. $minutes must not be negative.
         *
         * @throws TimeException
         */
        public static function fromMinutes(int $minutes): self
        {
            if ($minutes < 0) {
                throw new \ValueError(__METHOD__.'(): Argument #1 ($minutes) must be greater than or equal to 0');
            }

            if ($minutes > intdiv(self::MAX_SECONDS, 60)) {
                throw new TimeException(self::RANGE_ERROR);
            }

            return self::create($minutes * 60, 0, false);
        }

        /**
         * Create a duration representing $hours hours. $hours must not be negative.
         *
         * @throws TimeException
         */
        public static function fromHours(int $hours): self
        {
            if ($hours < 0) {
                throw new \ValueError(__METHOD__.'(): Argument #1 ($hours) must be greater than or equal to 0');
            }

            if ($hours > intdiv(self::MAX_SECONDS, 3_600)) {
                throw new TimeException(self::RANGE_ERROR);
            }

            return self::create($hours * 3_600, 0, false);
        }

        /**
         * Parse a ISO-8601 period. ISO-8601 periods with a date component will be rejected.
         * The biggest allowed component is H.
         *
         * @throws TimeException
         */
        public static function fromIso8601DurationString(string $specification): self
        {
            if (!preg_match(self::REGEXP_ISO8601, $specification, $parts)) {
                throw new TimeException(self::iso8601Error($specification));
            }

            $hours = (int) ($parts['hour'] ?? 0);
            $minutes = (int) ($parts['minute'] ?? 0);
            $seconds = (int) ($parts['second'] ?? 0);

            // each step is checked against the maximum so that no intermediate value can overflow
            if ($hours > intdiv(self::MAX_SECONDS, 3_600)) {
                throw new TimeException(self::RANGE_ERROR);
            }

            $total = $hours * 3_600;

            if ($minutes > intdiv(self::MAX_SECONDS - $total, 60)) {
                throw new TimeException(self::RANGE_ERROR);
            }

            $total += $minutes * 60;

            if ($seconds > self::MAX_SECONDS - $total) {
                throw new TimeException(self::RANGE_ERROR);
            }

            return self::create($total + $seconds, 0, false);
        }

        /**
         * Negates the duration.
         *
         * @return self -$this
         */
        public function negate(): self
        {
            if (0 === $this->seconds && 0 === $this->nanoseconds) {
                return $this;
            }

            return new self($this->seconds, $this->nanoseconds, !$this->negative);
        }

        /**
         * Returns the absolute value of the duration.
         *
         * @return self abs($this)
         */
        public function absolute(): self
        {
            if (!$this->negative) {
                return $this;
            }

            return new self($this->seconds, $this->nanoseconds, false);
        }

        /**
         * Add the given duration to the duration.
         *
         * @return self $this + $duration
         *
         * @throws TimeException
         */
        public function add(self $duration): self
        {
            $seconds = $this->negative ? -$this->seconds : $this->seconds;
            $nanoseconds = $this->negative ? -$this->nanoseconds : $this->nanoseconds;
            $addSeconds = $duration->negative ? -$duration->seconds : $duration->seconds;
            $addNanoseconds = $duration->negative ? -$duration->nanoseconds : $duration->nanoseconds;

            if ($this->negative === $duration->negative && $this->seconds > self::MAX_SECONDS - $duration->seconds) {
                throw new TimeException(self::RANGE_ERROR);
            }

            $seconds += $addSeconds;
            $nanoseconds += $addNanoseconds;

            if ($nanoseconds >= self::NANOS_PER_SECOND || $nanoseconds <= -self::NANOS_PER_SECOND) {
                $carry = intdiv($nanoseconds, self::NANOS_PER_SECOND);
                $nanoseconds %= self::NANOS_PER_SECOND;

                if ($carry > 0 ? $seconds > self::MAX_SECONDS - $carry : $seconds < -self::MAX_SECONDS - $carry) {
                    throw new TimeException(self::RANGE_ERROR);
                }

                $seconds += $carry;
            }

            if (0 < $seconds && 0 > $nanoseconds) {
                --$seconds;
                $nanoseconds += self::NANOS_PER_SECOND;
            }

            if (0 > $seconds && 0 < $nanoseconds) {
                ++$seconds;
                $nanoseconds -= self::NANOS_PER_SECOND;
            }

            return self::create(abs($seconds), abs($nanoseconds), 0 > $seconds || 0 > $nanoseconds);
        }

        /**
         * Subtract the given duration from the duration.
         *
         * @return self $this - $duration
         *
         * @throws TimeException
         */
        public function sub(self $duration): self
        {
            return $this->add($duration->negate());
        }

        /**
         * Multiply the length of the duration by the given factor. $factor must not be negative.
         *
         * @return self $this * $factor
         *
         * @throws TimeException
         */
        public function multiplyBy(int $factor): self
        {
            if ($factor < 0) {
                throw new \ValueError(__METHOD__.'(): Argument #1 ($factor) must be greater than or equal to 0');
            }

            if (0 === $factor) {
                return new self(0, 0, false);
            }

            if (1 === $factor) {
                return $this;
            }

            // double-and-add: multiplying the nanoseconds directly would overflow
            $seconds = 0;
            $nanoseconds = 0;
            $addSeconds = $this->seconds;
            $addNanoseconds = $this->nanoseconds;

            while (true) {
                if (1 & $factor) {
                    [$seconds, $nanoseconds] = self::addMagnitudes($seconds, $nanoseconds, $addSeconds, $addNanoseconds);
                }

                if (0 === $factor >>= 1) {
                    break;
                }

                // the remaining factor is not zero, so an out of range double is out of range for the result too
                [$addSeconds, $addNanoseconds] = self::addMagnitudes($addSeconds, $addNanoseconds, $addSeconds, $addNanoseconds);
            }

            return self::create($seconds, $nanoseconds, $this->negative);
        }

        /**
         * Divide the length of the duration by the given divisor. $divisor must be positive.
         *
         * Fractional nanoseconds will be truncated.
         *
         * @return self $this / $divisor
         *
         * @throws TimeException
         */
        public function divideBy(int $divisor): self
        {
            if ($divisor < 0) {
                throw new \ValueError(__METHOD__.'(): Argument #1 ($divisor) must be greater than or equal to 0');
            }

            if (0 === $divisor) {
                throw new \DivisionByZeroError('Division by zero');
            }

            if (1 === $divisor) {
                return $this;
            }

            $remainder = $this->seconds % $divisor;

            $nanoseconds = $remainder > intdiv(\PHP_INT_MAX - $this->nanoseconds, self::NANOS_PER_SECOND)
                ? self::divideNanoseconds($remainder, $this->nanoseconds, $divisor)
                : intdiv($this->nanoseconds + $remainder * self::NANOS_PER_SECOND, $divisor);

            return self::create(intdiv($this->seconds, $divisor), $nanoseconds, $this->negative);
        }

        /**
         * Returns -1, 0, 1 if $a is less than, equal to, or greater than $b respectively.
         */
        public static function compare(self $a, self $b): int
        {
            if ($a->negative !== $b->negative) {
                return $a->negative ? -1 : 1;
            }

            $comparison = $a->seconds <=> $b->seconds ?: $a->nanoseconds <=> $b->nanoseconds;

            return $a->negative ? -$comparison : $comparison;
        }

        /**
         * The native class is declared readonly, which forbids dynamic properties.
         */
        public function __set(string $name, mixed $value): void
        {
            throw new \Error(\sprintf('Cannot create dynamic property %s::$%s', self::class, $name));
        }

        /**
         * Adds two non-negative (seconds, nanoseconds) pairs.
         *
         * @return array{0: int, 1: int}
         *
         * @throws TimeException when the sum is out of the representable range
         */
        private static function addMagnitudes(int $seconds, int $nanoseconds, int $addSeconds, int $addNanoseconds): array
        {
            if ($seconds > self::MAX_SECONDS - $addSeconds) {
                throw new TimeException(self::RANGE_ERROR);
            }

            $seconds += $addSeconds;
            // the sum of two nanoseconds components is always below 2**31
            $nanoseconds += $addNanoseconds;

            if ($nanoseconds >= self::NANOS_PER_SECOND) {
                if (self::MAX_SECONDS === $seconds) {
                    throw new TimeException(self::RANGE_ERROR);
                }

                ++$seconds;
                $nanoseconds -= self::NANOS_PER_SECOND;
            }

            return [$seconds, $nanoseconds];
        }

        /**
         * Returns intdiv($remainder * 1_000_000_000 + $nanoseconds, $divisor) for
         * a $remainder that is lower than $divisor.
         *
         * The dividend does not fit in an integer on 32 bit platforms, so the
         * division is done one decimal digit at a time. Every intermediate value
         * stays below 2**53, where floats represent integers exactly.
         */
        private static function divideNanoseconds(int $remainder, int $nanoseconds, int $divisor): int
        {
            $quotient = 0;
            $rest = (float) $remainder;

            foreach (str_split(str_pad((string) $nanoseconds, 9, '0', \STR_PAD_LEFT)) as $digit) {
                $rest = $rest * 10 + (int) $digit;
                $digit = (int) ($rest / $divisor);
                $rest -= $digit * $divisor;

                // the float division may be off by one in either direction
                while (0 > $rest) {
                    --$digit;
                    $rest += $divisor;
                }
                while ($rest >= $divisor) {
                    ++$digit;
                    $rest -= $divisor;
                }

                $quotient = $quotient * 10 + $digit;
            }

            return $quotient;
        }

        /**
         * @throws TimeException when the duration is out of the representable range
         */
        private static function create(int $seconds, int $nanoseconds, bool $negative): self
        {
            if ($seconds > self::MAX_SECONDS) {
                throw new TimeException(self::RANGE_ERROR);
            }

            // a zero duration is never negative
            return new self($seconds, $nanoseconds, $negative && (0 !== $seconds || 0 !== $nanoseconds));
        }

        /**
         * Mirrors the error reporting of timelib_duration_create_from_iso8601string().
         */
        private static function iso8601Error(string $specification): string
        {
            if (preg_match(self::REGEXP_ISO8601_ANY, $specification, $parts)) {
                if ((int) ($parts['year'] ?? 0) || (int) ($parts['month'] ?? 0) || (int) ($parts['week'] ?? 0) || (int) ($parts['day'] ?? 0)) {
                    return 'The ISO 8601 duration string may only contain the time (T) aspect';
                }

                return 'The ISO 8601 duration string could not be parsed';
            }

            if (str_contains($specification, '/')) {
                return 'The ISO 8601 duration string may only contain the period (P) aspect';
            }

            if (!str_contains($specification, 'P') && false !== @strtotime($specification)) {
                return 'The ISO 8601 duration string is missing the period (P) aspect';
            }

            return 'The ISO 8601 duration string could not be parsed';
        }
    }
}
