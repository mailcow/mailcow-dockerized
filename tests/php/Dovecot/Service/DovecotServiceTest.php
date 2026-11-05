<?php

declare(strict_types=1);

namespace Mailcow\Tests\Dovecot\Service;

use Mailcow\Dovecot\Service\DovecotService;
use PHPUnit\Framework\TestCase;

class DovecotServiceTest extends TestCase
{
    public function testAclOnAllFolders(): void
    {
        $dovecot = new FakeDovecot(['type' => 'success', 'msg' => 'command completed successfully']);

        $this->assertNull($dovecot->setAclAll('shared@example.org', 'group=mcg_7', ['lookup', 'read']));
        $this->assertSame([['cmd' => 'doveadm', 'task' => 'set_acl_all', 'user' => 'shared@example.org', 'id' => 'group=mcg_7',
            'rights' => ['lookup', 'read']]], $dovecot->calls);
    }

    public function testErrorsOfDockerapiAreReturned(): void
    {
        $this->assertSame('command failed: no such user', (new FakeDovecot(['type' => 'danger', 'msg' => 'command failed: no such user']))
            ->setAclAll('x@example.org', 'group=mcg_7', []));
        $this->assertSame('dockerapi did not answer', (new FakeDovecot(false))->setAclAll('x@example.org', 'group=mcg_7', []));
    }

    public function testSharedFoldersAreSubscribedThroughDockerapi(): void
    {
        $dovecot = new FakeDovecot(['type' => 'success']);

        $this->assertNull($dovecot->subscribeShared('team@example.org', ['a@example.org'], true));
        $this->assertSame([['cmd' => 'doveadm', 'task' => 'subscribe_shared', 'owner' => 'team@example.org', 'users' => ['a@example.org'],
            'unsubscribe' => true]], $dovecot->calls);
    }

    public function testAuthCacheFlushOnlyWithUsers(): void
    {
        $dovecot = new FakeDovecot([]);
        $dovecot->flushAuthCache([]);
        $dovecot->flushAuthCache(['a@example.org']);

        $this->assertSame([['cmd' => 'doveadm', 'task' => 'auth_cache_flush', 'users' => ['a@example.org']]], $dovecot->calls);
    }
}

class FakeDovecot extends DovecotService
{
    public array $calls = [];

    public function __construct(private mixed $result)
    {
    }

    protected function exec(array $fields): mixed
    {
        $this->calls[] = $fields;
        return is_array($this->result) ? json_encode($this->result) : $this->result;
    }
}
