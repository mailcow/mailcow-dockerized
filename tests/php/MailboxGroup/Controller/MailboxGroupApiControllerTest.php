<?php

declare(strict_types=1);

namespace Mailcow\Tests\MailboxGroup\Controller;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use Mailcow\MailboxGroup\Controller\MailboxGroupApiController;
use Mailcow\MailboxGroup\Model\Group;
use Mailcow\MailboxGroup\Service\AccountHooks;
use Mailcow\MailboxGroup\Service\GroupService;
use Mailcow\MailboxGroup\Service\TemplateService;
use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MailboxGroupApiControllerTest extends TestCase
{
    private GroupService&MockObject $groups;
    private AccountHooks&MockObject $hooks;
    private TemplateService&MockObject $templates;
    private MailboxGroupApiController $api;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->groups = $this->createMock(GroupService::class);
        $this->hooks = $this->createMock(AccountHooks::class);
        $this->templates = $this->createMock(TemplateService::class);
        $this->api = new MailboxGroupApiController($this->groups, $this->hooks, $this->templates, new ApiResponse());
    }

    private static function group(string $name, array $warnings = []): Group
    {
        $group = Group::fromRow(['id' => 7, 'domain' => 'example.org', 'name' => $name]);
        $group->warnings = $warnings;
        return $group;
    }

    /** Messages written to the session as [type, msg] */
    private function messages(): array
    {
        return array_map(fn(array $m): array => [$m['type'], $m['msg']], $_SESSION['return'] ?? []);
    }

    public function testAddReportsWarningsAndTheEscapedName(): void
    {
        $this->groups->method('create')->willReturn(self::group('<Sales>', [['group_template_failed', 'Staff', 'a@example.org']]));

        $this->assertTrue($this->api->addGroup(['name' => '<Sales>']));

        $this->assertSame([
            ['warning', ['group_template_failed', 'Staff', 'a@example.org']],
            ['success', ['group_added', '&lt;Sales&gt;']],
        ], $this->messages());
        $this->assertSame(['mailbox_group', 'add', 'group', ['name' => '<Sales>']], $_SESSION['return'][1]['log']);
    }

    public function testErrorsBecomeDangerMessages(): void
    {
        $this->groups->method('create')->willReturnOnConsecutiveCalls(
            $this->throwException(new UserError('group_name_invalid')),
            $this->throwException(new PDOException('gone away'))
        );

        $this->assertFalse($this->api->addGroup([]));
        $this->assertFalse($this->api->addGroup([]));

        $this->assertSame([['danger', 'group_name_invalid'], ['danger', ['mysql_error', 'gone away']]], $this->messages());
    }

    public function testEditReportsEachId(): void
    {
        $this->groups->method('update')->willReturnCallback(fn(int $id, array $attr): Group => ($id === 2)
            ? throw new UserError('group_member_idp', ['a@example.org']) : self::group('G' . $id));

        $this->assertTrue($this->api->editGroups(['1', '2', 'x'], ['active' => '0']));

        $this->assertSame([
            ['success', ['group_modified', 'G1']],
            ['danger', ['group_member_idp', 'a@example.org']],
            ['danger', 'access_denied'],
        ], $this->messages());
    }

    public function testEditAndDeleteNeedAnEditorOnce(): void
    {
        $this->groups->method('requireEditor')->willThrowException(new AccessDenied());
        $this->groups->expects($this->never())->method('update');
        $this->groups->expects($this->never())->method('delete');

        $this->assertFalse($this->api->editGroups(['1', '2'], []));
        $this->assertFalse($this->api->deleteGroups(['1', '2']));

        $this->assertSame([['danger', 'access_denied'], ['danger', 'access_denied']], $this->messages());
    }

    public function testDelete(): void
    {
        $this->groups->expects($this->once())->method('delete')->with(7)->willReturn(self::group('Sales'));

        $this->assertTrue($this->api->deleteGroups(['7']));

        $this->assertSame([['success', ['group_removed', 'Sales']]], $this->messages());
    }

    public function testGetGroupsSplitsTheDomainList(): void
    {
        $this->groups->expects($this->once())->method('visibleIds')->with(['a.org', 'b.org'])->willReturn([7]);
        $this->groups->method('get')->willReturnMap([[7, self::group('Sales')]]);
        $this->groups->method('toArray')->willReturn(['id' => 7]);

        $this->assertSame([['id' => 7]], $this->api->getGroups(' a.org,,b.org '));
        $this->assertSame([], $this->api->getGroup('all'));
    }

    public function testHooksTurnDatabaseErrorsIntoMessages(): void
    {
        $this->hooks->method('onMailboxDeleted')->willThrowException(new PDOException('locked'));

        $this->api->mailboxDeleted('a@example.org');

        $this->assertSame([['danger', ['mysql_error', 'locked']]], $this->messages());
    }

    public function testTemplateChangesReportWarnings(): void
    {
        $this->templates->method('onTemplateChanged')->with(3)->willReturn([['group_template_failed', 'Staff', 'a@example.org']]);

        $this->api->templateChanged(3);

        $this->assertSame([['warning', ['group_template_failed', 'Staff', 'a@example.org']]], $this->messages());
    }
}
