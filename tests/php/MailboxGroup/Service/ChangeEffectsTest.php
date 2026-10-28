<?php

declare(strict_types=1);

namespace Mailcow\Tests\MailboxGroup\Service;

use Mailcow\Dovecot\Service\DovecotService;
use Mailcow\MailboxGroup\Repository\GroupRepository;
use Mailcow\MailboxGroup\Service\ChangeEffects;
use Mailcow\MailboxGroup\Service\TemplateService;
use Mailcow\Sogo\Service\SogoService;
use PHPUnit\Framework\TestCase;

class ChangeEffectsTest extends TestCase
{
    public function testCachesAreFlushedForTheWritesOfTheRepository(): void
    {
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('takeEffects')->willReturn(['members' => ['a@example.org'], 'sogo_acl' => true]);
        $legacy = $this->createMock(SogoService::class);
        $legacy->expects($this->once())->method('refreshStaticView')->with(['b@example.org']);
        $legacy->expects($this->once())->method('flushCache');
        $dovecot = $this->createMock(DovecotService::class);
        $dovecot->expects($this->once())->method('flushAuthCache')->with(['a@example.org']);
        $templates = $this->createMock(TemplateService::class);
        $templates->method('applyChanged')->willReturn([]);

        (new ChangeEffects($templates, $legacy, $repo, $dovecot))->apply(['b@example.org'], []);
    }

    public function testSogoCacheStaysWithoutAclChanges(): void
    {
        $repo = $this->createMock(GroupRepository::class);
        $repo->method('takeEffects')->willReturn(['members' => [], 'sogo_acl' => false]);
        $legacy = $this->createMock(SogoService::class);
        $legacy->expects($this->never())->method('flushCache');
        $templates = $this->createMock(TemplateService::class);
        $templates->method('applyChanged')->willReturn([]);

        (new ChangeEffects($templates, $legacy, $repo, $this->createMock(DovecotService::class)))->apply([], []);
    }
}
