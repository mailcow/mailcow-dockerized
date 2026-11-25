<?php

declare(strict_types=1);

namespace Mailcow\Tests\MailboxGroup\Service;

use Mailcow\MailboxGroup\Legacy\LegacyMailbox;
use Mailcow\MailboxGroup\Repository\LookupRepository;
use Mailcow\MailboxGroup\Service\TemplateService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateServiceTest extends TestCase
{
    private LookupRepository&MockObject $lookup;
    private LegacyMailbox&MockObject $legacy;

    protected function setUp(): void
    {
        $this->lookup = $this->createMock(LookupRepository::class);
        $this->legacy = $this->createMock(LegacyMailbox::class);
        $this->lookup->method('mailboxNames')->willReturnCallback(fn(array $u): array => array_fill_keys($u, 'Name'));
    }

    private function service(): TemplateService
    {
        return new TemplateService($this->lookup, $this->legacy);
    }

    public function testOnlyChangedTemplatesAreApplied(): void
    {
        $this->lookup->method('effectiveTemplates')->willReturn([
            'a@example.org' => 'Staff',     // unchanged
            'b@example.org' => 'Admins',    // changed
            'c@example.org' => null,        // left all groups: settings stay
            'd@example.org' => 'Staff',     // joined
        ]);
        $applied = [];
        $this->legacy->method('applyTemplate')->willReturnCallback(function (string $u, string $name, string $t) use (&$applied): ?string {
            $applied[] = [$u, $name, $t];
            return ($u === 'd@example.org') ? 'mailbox_quota_exceeded' : null;
        });

        $warnings = $this->service()->applyChanged([
            'a@example.org' => 'Staff', 'b@example.org' => 'Staff', 'c@example.org' => 'Staff', 'd@example.org' => null,
        ]);

        $this->assertSame([['b@example.org', 'Name', 'Admins'], ['d@example.org', 'Name', 'Staff']], $applied);
        $this->assertSame([['group_template_failed', 'Staff', 'd@example.org']], $warnings);
    }

    public function testLoginPrefersTheGroupTemplate(): void
    {
        $this->lookup->method('effectiveTemplates')->willReturnCallback(fn(array $u): array =>
            ($u === ['a@example.org']) ? ['a@example.org' => 'Staff'] : array_fill_keys($u, null));

        $this->assertSame('Staff', $this->service()->templateForLogin('a@example.org', 'Legacy'));
        $this->assertSame('Legacy', $this->service()->templateForLogin('b@example.org', 'Legacy'));
        $this->assertNull($this->service()->templateForLogin('b@example.org', null));
    }

    public function testAnEditedTemplateIsAppliedWhereItIsEffective(): void
    {
        $this->lookup->method('templateName')->with(3)->willReturn('Staff');
        $this->lookup->method('membersWithTemplate')->with(3)->willReturn(['a@example.org', 'b@example.org']);
        $this->lookup->method('effectiveTemplates')->willReturn(['a@example.org' => 'Staff', 'b@example.org' => 'Admins']);
        $this->legacy->expects($this->once())->method('applyTemplate')->with('a@example.org', 'Name', 'Staff')->willReturn(null);

        $this->assertSame([], $this->service()->onTemplateChanged(3));
    }

    public function testReapplyUsesTheEffectiveTemplates(): void
    {
        $this->lookup->method('effectiveTemplates')->willReturn(['a@example.org' => 'Staff', 'b@example.org' => null]);
        $this->legacy->expects($this->once())->method('applyTemplate')->with('a@example.org', 'Name', 'Staff')->willReturn(null);

        $this->service()->applyTo(['a@example.org', 'b@example.org']);
    }
}
