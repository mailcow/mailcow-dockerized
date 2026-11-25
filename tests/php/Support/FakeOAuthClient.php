<?php

declare(strict_types=1);

namespace Mailcow\Tests\Support;

use Mailcow\Imapsync\Http\OAuthClient;

/**
 * OAuthClient without network: resolve() answers from $addresses, send() records the curl options
 * and returns $response.
 */
class FakeOAuthClient extends OAuthClient
{
    /** @var array<string, string[]> host => addresses */
    public array $addresses = [];
    /** @var array{0: string|false, 1: int} */
    public array $response = ['{}', 200];
    /** @var array[] curl options of every send() */
    public array $sent = [];
    /** @var string[] hosts passed to resolve() */
    public array $resolved = [];

    protected function resolve(string $host): array
    {
        $this->resolved[] = $host;
        return $this->addresses[$host] ?? [];
    }

    protected function send(array $options): array
    {
        $this->sent[] = $options;
        return $this->response;
    }
}
