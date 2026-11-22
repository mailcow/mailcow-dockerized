<?php

declare(strict_types=1);

namespace Mailcow\Tests\Support;

// State behind legacy_login_flow_functions.php and the Redis stand-in of the login flow tests
final class LegacyLoginFlow
{
    /** @var array<string, array<int, array<string, mixed>>> second factors per username */
    public static array $tfa = [];

    /** @var list<array{0: string, 1: string}> set_sasl_log() calls */
    public static array $saslLog = [];

    /** @var list<array{0: string, 1: string}> Redis publish() calls: channel, message */
    public static array $published = [];

    public static function reset(): void
    {
        self::$tfa = [];
        self::$saslLog = [];
        self::$published = [];
    }

    /** @return list<string> messages published on F2B_CHANNEL (netfilter strikes) */
    public static function strikes(): array
    {
        return array_values(array_map(fn ($p) => $p[1], array_filter(self::$published, fn ($p) => $p[0] === 'F2B_CHANNEL')));
    }

    public static function redis(): object
    {
        return new class {
            public function publish(string $channel, string $message): int
            {
                LegacyLoginFlow::$published[] = [$channel, $message];
                return 1;
            }
        };
    }
}
