<?php

declare(strict_types=1);

namespace Mailcow\Tests\Backup\Legacy;

use Mailcow\Backup\Legacy\NoTemplates;
use Mailcow\MailboxGroup\Repository\LookupRepository;
use Mailcow\MailboxGroup\Service\TemplateService;
use PHPUnit\Framework\TestCase;

class NoTemplatesTest extends TestCase
{
    public function testARestoredMembershipAppliesNoGroupTemplate(): void
    {
        $lookup = $this->createMock(LookupRepository::class);
        // the mailbox gets a group with a template
        $lookup->method('effectiveTemplates')->willReturn(['anna@firma.de' => 'Vertrieb']);
        $lookup->method('mailboxNames')->willReturn(['anna@firma.de' => 'Anna']);

        // the legacy mailbox code is never called (it is not loaded in the tests)
        $this->assertSame([], (new TemplateService($lookup, new NoTemplates()))->applyChanged(['anna@firma.de' => null]));
    }
}
