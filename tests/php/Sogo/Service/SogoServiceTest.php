<?php

declare(strict_types=1);

namespace Mailcow\Tests\Sogo\Service;

use Mailcow\Sogo\Service\SogoService;
use PHPUnit\Framework\TestCase;

class SogoServiceTest extends TestCase
{
    public function testSubscriptionsGoThroughDockerapi(): void
    {
        $sogo = new FakeSogo(['type' => 'success']);

        $this->assertNull($sogo->subscribe('team@example.org', 'Calendar/personal', ['a@example.org']));
        $this->assertSame('dockerapi did not answer', (new FakeSogo(false))->subscribe('team@example.org', 'Calendar/personal', ['a@example.org'], true));
        $this->assertSame([['cmd' => 'sogo', 'task' => 'subscribe', 'owner' => 'team@example.org', 'folder' => 'Calendar/personal',
            'users' => ['a@example.org'], 'unsubscribe' => false]], $sogo->calls);
    }

    public function testFoldersAreCreatedThroughDockerapi(): void
    {
        $sogo = new FakeSogo(['type' => 'success']);

        $this->assertNull($sogo->initUsers(['team@example.org']));
        $this->assertSame([['cmd' => 'sogo', 'task' => 'init_user', 'users' => ['team@example.org']]], $sogo->calls);
    }

    public function testManyChangesRebuildTheWholeView(): void
    {
        $few = new FakeSogo(true);
        $few->refreshStaticView(['a@example.org', 'a@example.org', 'b@example.org']);
        $many = new FakeSogo(true);
        $many->refreshStaticView(array_map(fn(int $i): string => 'u' . $i . '@example.org', range(1, 26)));
        $none = new FakeSogo(true);
        $none->refreshStaticView([]);

        $this->assertSame([['view', 'a@example.org'], ['view', 'b@example.org']], $few->calls);
        $this->assertSame([['view', null]], $many->calls);
        $this->assertSame([], $none->calls);
    }
}

class FakeSogo extends SogoService
{
    public array $calls = [];

    public function __construct(private mixed $result)
    {
    }

    protected function updateStaticView(?string $username): void
    {
        $this->calls[] = ['view', $username];
    }

    protected function exec(array $fields): mixed
    {
        $this->calls[] = $fields;
        return is_array($this->result) ? json_encode($this->result) : $this->result;
    }
}
