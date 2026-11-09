<?php

declare(strict_types=1);

namespace Mailcow\Tests\Retention\Service;

use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Retention\Model\Rule;
use Mailcow\Retention\Repository\RuleRepository;
use Mailcow\Retention\Service\Planner;
use Mailcow\Retention\Service\RuleService;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RuleServiceTest extends TestCase
{
    private RuleRepository&MockObject $repo;
    /** @var Rule[] saved rules */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->repo = $this->createMock(RuleRepository::class);
        $this->repo->method('isDomain')->willReturnCallback(fn(string $domain): bool => in_array($domain, ['firma.de', 'tochter.de', 'xn--mller-kva.de'], true));
        $this->repo->method('mailboxDomain')->willReturnCallback(fn(string $username): ?string =>
            ['azubi@firma.de' => 'firma.de', 'chef@firma.de' => 'firma.de', 'andere@tochter.de' => 'tochter.de'][$username] ?? null);
        $this->repo->method('groupDomain')->willReturnCallback(fn(int $id): ?string => [7 => 'firma.de', 9 => 'tochter.de'][$id] ?? null);
        $this->repo->method('groupsIn')->willReturn([['id' => 7, 'name' => 'Azubis', 'domain' => 'firma.de', 'address' => null]]);
        $this->repo->method('save')->willReturnCallback(function (Rule $rule): int {
            $rule->id ??= 1;
            $this->saved[] = clone $rule;
            return $rule->id;
        });
    }

    private function service(?Context $context = null): RuleService
    {
        return new RuleService($this->repo, $context ?? FakeContext::admin());
    }

    private function stored(array $values = []): Rule
    {
        $rule = new Rule();
        $rule->id = 3;
        $rule->domain = 'firma.de';
        $rule->name = 'Papierkorb';
        $rule->folders = ['trash', 'folder:Newsletter'];
        $rule->groups = [7];
        foreach ($values as $property => $value) {
            $rule->$property = $value;
        }
        return $rule;
    }

    private function expectUserError(string|array $msg, callable $call): void
    {
        try {
            $call();
            $this->fail('UserError ' . json_encode($msg) . ' expected');
        } catch (UserError $e) {
            $this->assertSame($msg, $e->msg());
        }
    }

    public function testCreateNormalizesTheInput(): void
    {
        $rule = $this->service()->create([
            'domain' => 'Firma.de', 'name' => 'Aufräumen', 'folders' => ['', 'trash', 'junk', 'trash'], 'own_folders' => "Projekte/*\n\n /Newsletter/ ",
            'days' => '30', 'keep_flagged' => ['0', '1'], 'all_mailboxes' => ['0'], 'mailboxes' => ['', 'Azubi@firma.de'], 'groups' => ['7'],
        ]);
        $this->assertSame(['domain' => 'firma.de', 'name' => 'Aufräumen', 'folders' => ['trash', 'junk', 'folder:Projekte/*', 'folder:Newsletter'],
            'days' => 30, 'keep_flagged' => 1, 'mode' => 'simulate', 'all_mailboxes' => 0, 'active' => 1, 'mailboxes' => ['azubi@firma.de'], 'groups' => [7]],
            array_intersect_key($rule->toArray(), array_flip(['domain', 'name', 'folders', 'days', 'keep_flagged', 'mode', 'all_mailboxes', 'active', 'mailboxes', 'groups'])));
        $this->assertSame('xn--mller-kva.de', $this->service()->create(['domain' => 'Müller.de', 'name' => 'Test', 'folders' => ['trash'], 'days' => 30])->domain);
    }

    public function testUpdateKeepsMissingValuesAndReplacesOwnFolders(): void
    {
        $this->repo->method('find')->willReturn($this->stored());
        $this->assertSame(['trash', 'folder:Archiv/2020'], $this->service()->update(3, ['own_folders' => 'Archiv/2020'])->folders);
        // the API names all folders in folders, the UI sends the own folders apart
        $this->assertSame(['junk', 'folder:Alt'], $this->service()->update(3, ['folders' => ['junk', 'folder:Alt']])->folders);
        $this->assertSame(['sent', 'folder:Neu'], $this->service()->update(3, ['folders' => ['sent'], 'own_folders' => 'Neu'])->folders);
        // a list keeps commas in names, INBOX is written like Dovecot does
        $this->assertSame(['sent', 'folder:Kunden, alt', 'folder:INBOX/Alt', 'folder:Inboxen'],
            $this->service()->update(3, ['own_folders' => ['Kunden, alt', 'inbox/Alt', 'Inboxen']])->folders);
        $this->assertSame('delete', $this->service()->update(3, ['mode' => 'delete'])->mode);
    }

    public static function invalidInput(): array
    {
        return [
            'no folder' => [['folders' => '', 'own_folders' => ''], 'retention_folders_invalid'],
            'unknown folder' => [['folders' => ['spam']], ['retention_folder_invalid', 'spam']],
            'folder with control character' => [['own_folders' => "a\tb"], ['retention_folder_invalid', "folder:a\tb"]],
            'folder list with a list' => [['own_folders' => [['a']]], ['retention_folder_invalid', '']],
            'no days' => [['days' => '0'], ['retention_days_invalid', 36500]],
            'too many days' => [['days' => '36501'], ['retention_days_invalid', 36500]],
            'mode' => [['mode' => 'now'], 'retention_mode_invalid'],
            'name' => [['name' => ''], 'retention_name_invalid'],
            'mailbox of another domain' => [['mailboxes' => 'andere@tochter.de'], ['retention_member_invalid', 'andere@tochter.de']],
            'group of another domain' => [['groups' => ['9']], ['retention_member_invalid', '9']],
            'too many mailboxes' => [['mailboxes' => array_map(fn(int $i): string => "m$i@firma.de", range(1, 501))], ['retention_entries_exceeded', 500]],
        ];
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInput(array $input, string|array $error): void
    {
        $this->expectUserError($error, fn() => $this->service()->create($input + ['domain' => 'firma.de', 'name' => 'Test', 'folders' => ['trash'], 'days' => 30]));
        $this->assertSame([], $this->saved);
    }

    public function testPermissions(): void
    {
        $this->repo->method('find')->willReturn($this->stored());
        $da = FakeContext::domainAdmin([], ['firma.de'], ['retention' => '1', 'mailbox_groups' => '1']);
        $create = fn(string $domain): array => ['domain' => $domain, 'name' => 'Test', 'folders' => ['trash'], 'days' => 30];

        $this->assertSame('Test', $this->service($da)->create($create('firma.de'))->name);
        $this->assertNotNull($this->service($da)->get(3));
        $this->expectUserError('access_denied', fn() => $this->service($da)->create($create('tochter.de')));
        $this->expectUserError('access_denied', fn() => $this->service($da)->create($create('unbekannt.de')));
        $this->expectUserError(['domain_not_found', 'unbekannt.de'], fn() => $this->service()->create($create('unbekannt.de')));
        $this->assertNull($this->service(FakeContext::domainAdmin([], ['tochter.de'], ['retention' => '1']))->get(3));
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::domainAdmin([], ['firma.de'], ['mail_policies' => '1']))->create($create('firma.de')));
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::user('azubi@firma.de'))->update(3, []));
        $readOnly = new Context('admin', 'API', [], false, 'ro');
        $this->assertNotNull($this->service($readOnly)->get(3));
        $this->expectUserError('access_denied', fn() => $this->service($readOnly)->delete(3));
    }

    public function testGroupsNeedTheGroupAcl(): void
    {
        $this->repo->method('find')->willReturn($this->stored());
        $da = FakeContext::domainAdmin([], ['firma.de'], ['retention' => '1']);

        $this->assertSame([], $this->service($da)->groupChoices(['firma.de']));
        // the form sends no group field: the assignment stays; sending it back unchanged is accepted
        $this->assertSame([7], $this->service($da)->update(3, ['days' => 60])->groups);
        $this->assertSame([7], $this->service($da)->update(3, ['groups' => ['7']])->groups);
        $this->expectUserError('access_denied', fn() => $this->service($da)->update(3, ['groups' => ['7', '8']]));
        $this->assertCount(1, $this->service(FakeContext::domainAdmin([], ['firma.de'], ['retention' => '1', 'mailbox_groups' => '1']))->groupChoices(['firma.de']));
    }

    public function testEffectiveRulesOfAMailbox(): void
    {
        $rule = $this->stored(['folders' => ['trash'], 'days' => 30]);
        $this->repo->method('candidates')->willReturn(['azubi@firma.de' => [['rule' => $rule, 'level' => Planner::LEVEL_DOMAIN]]]);

        $this->assertSame('trash', $this->service(FakeContext::user('Azubi@firma.de'))->effective('azubi@firma.de')[0]['folder']);
        // users only see their own mailbox
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::user('chef@firma.de'))->effective('azubi@firma.de'));
        $this->assertCount(2, $this->service()->effective('azubi@firma.de'));
        // domain admins need the ACL as well
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::domainAdmin(['azubi@firma.de'], ['firma.de'], []))->effective('azubi@firma.de'));
        $this->assertCount(2, $this->service(FakeContext::domainAdmin(['azubi@firma.de'], ['firma.de'], ['retention' => '1']))->effective('azubi@firma.de'));
    }

    public function testThePlanComesInPages(): void
    {
        $rule = $this->stored(['folders' => ['all'], 'days' => 30]);
        $this->repo->method('mailboxesWithRules')->willReturnCallback(fn(string $after, int $limit): array =>
            array_slice(array_values(array_filter(['a@firma.de', 'b@firma.de', 'c@firma.de'], fn(string $u): bool => $u > $after)), 0, $limit));
        $this->repo->method('candidates')->willReturnCallback(fn(array $usernames): array =>
            array_fill_keys(array_diff($usernames, ['b@firma.de']), [['rule' => $rule, 'level' => Planner::LEVEL_DOMAIN]]));

        $first = $this->service(Context::system())->plan('', 2);
        // b has no rule of its own: not in the plan, but the page goes on after it
        $this->assertSame(['a@firma.de'], array_keys($first['mailboxes']));
        $this->assertSame('b@firma.de', $first['next']);
        $last = $this->service(Context::system())->plan('b@firma.de', 2);
        $this->assertSame(['c@firma.de'], array_keys($last['mailboxes']));
        $this->assertNull($last['next']);
    }

    public function testReportDropsBrokenEntries(): void
    {
        $this->repo->expects($this->once())->method('addLog')->with(
            [['username' => 'azubi@firma.de', 'rule_id' => 3, 'folder' => 'Trash', 'matched' => 2]],
            [['rule_id' => 3, 'mailboxes' => 1]],
            ['mailboxes' => 1]
        )->willReturn(3);
        $this->assertSame(3, $this->service(Context::system())->report(
            [['username' => 'azubi@firma.de', 'rule_id' => 3, 'folder' => 'Trash', 'matched' => 2], ['username' => ['x']], 'text'],
            [['rule_id' => 3, 'mailboxes' => 1], ['rule_id' => 'x']],
            ['mailboxes' => 1]
        ));
        // the runner sends parts of 200
        $this->expectUserError(['retention_entries_exceeded', 1000], fn() => $this->service(Context::system())->report(array_fill(0, 1001, []), []));
    }
}
