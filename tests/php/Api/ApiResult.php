<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api;

/** What json_api.php did for one request of ApiTestCase: status, output and the calls it made */
final class ApiResult
{
    /**
     * @param list<array{0: string, 1: array}> $calls function or method name and arguments, in call order
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $calls,
        public readonly ?array $sessionReturn,
        public readonly ?string $error
    ) {
    }

    /** The decoded output, null if it is no JSON */
    public function json(): mixed
    {
        return json_decode($this->body, true);
    }

    /**
     * The arguments of every call of $name, e.g. 'mailbox' or 'Mailcow\ApiKey\ApiKeyModule->api->addKey'
     *
     * @return list<array>
     */
    public function callsTo(string $name): array
    {
        return array_values(array_map(fn(array $call): array => $call[1], array_filter($this->calls, fn(array $call): bool => $call[0] === $name)));
    }

    /** Names of the calls, without the API log of every request (redis->lPush) */
    public function callNames(): array
    {
        return array_values(array_filter(array_column($this->calls, 0), fn(string $name): bool => $name !== 'redis->lPush'));
    }
}
