<?php

declare(strict_types=1);

namespace Mailcow\Tests\MailPolicy\Service;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\MailPolicy\Model\Policy;
use Mailcow\MailPolicy\Repository\PolicyRepository;
use Mailcow\MailPolicy\Service\PolicyEngine;
use Mailcow\MailPolicy\Service\PolicyService;
use Mailcow\Tests\Support\FakeContext;
use Mailcow\Tests\Support\FakeMailPolicyLookup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PolicyServiceTest extends TestCase
{
    private PolicyRepository&MockObject $repo;
    private FakeMailPolicyLookup $lookup;
    /** @var Policy[] saved policies */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->repo = $this->createMock(PolicyRepository::class);
        $this->repo->method('isDomain')->willReturnCallback(fn(string $domain): bool => in_array($domain, ['firma.de', 'tochter.de'], true));
        $this->repo->method('mailboxDomain')->willReturnCallback(fn(string $username): ?string =>
            ['azubi@firma.de' => 'firma.de', 'chef@firma.de' => 'firma.de', 'andere@tochter.de' => 'tochter.de'][$username] ?? null);
        $this->repo->method('groupDomain')->willReturnCallback(fn(int $id): ?string => [7 => 'firma.de', 9 => 'tochter.de'][$id] ?? null);
        $this->repo->method('save')->willReturnCallback(function (Policy $policy): int {
            $policy->id ??= 1;
            $this->saved[] = clone $policy;
            return $policy->id;
        });
        $this->lookup = new FakeMailPolicyLookup();
        $this->lookup->domains = ['firma.de', 'tochter.de'];
        $this->lookup->mailboxes = ['azubi@firma.de' => 'firma.de', 'chef@firma.de' => 'firma.de'];
    }

    private function service(?Context $context = null): PolicyService
    {
        return new PolicyService($this->repo, new PolicyEngine($this->lookup), $context ?? FakeContext::admin());
    }

    private function stored(array $values = []): Policy
    {
        $policy = new Policy();
        $policy->id = 3;
        $policy->domain = 'firma.de';
        $policy->name = 'Azubis';
        $policy->sendAllow = ['partner.de', 'group:7'];
        foreach ($values as $property => $value) {
            $policy->$property = $value;
        }
        return $policy;
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
        $policy = $this->service()->create([
            'domain' => 'Firma.de', 'name' => 'Azubis', 'description' => 'Nur intern', 'active' => ['0', '1'],
            'send_mode' => 'internal', 'send_allow' => "@Partner.de\nkunde@extern.example, partner.de", 'send_allow_groups' => ['', '7'],
            'send_block' => '', 'receive_mode' => 'list', 'receive_allow' => ['group:7'], 'reject_text' => 'Nur intern erlaubt',
            'mailboxes' => ['', 'Azubi@firma.de'], 'groups' => ['7'],
        ]);

        $this->assertSame([
            'domain' => 'firma.de', 'name' => 'Azubis', 'description' => 'Nur intern', 'send_mode' => 'internal',
            'send_allow' => ['partner.de', 'kunde@extern.example', 'group:7'], 'send_block' => [], 'receive_mode' => 'list',
            'receive_allow' => ['group:7'], 'receive_block' => [], 'reject_text' => 'Nur intern erlaubt', 'active' => 1,
            'mailboxes' => ['azubi@firma.de'], 'groups' => [7],
        ], array_intersect_key($policy->toArray(), array_flip(['domain', 'name', 'description', 'send_mode', 'send_allow', 'send_block',
            'receive_mode', 'receive_allow', 'receive_block', 'reject_text', 'active', 'mailboxes', 'groups'])));
        $this->assertCount(1, $this->saved);
    }

    public function testUpdateKeepsMissingValuesAndReplacesListGroups(): void
    {
        $this->repo->method('find')->willReturn($this->stored());

        $policy = $this->service()->update(3, ['description' => 'neu']);
        $this->assertSame(['neu', ['partner.de', 'group:7']], [$policy->description, $policy->sendAllow]);

        // the UI sends the groups apart: they replace the group entries, the other entries stay
        $this->repo->method('find')->willReturn($this->stored());
        $this->assertSame(['partner.de'], $this->service()->update(3, ['send_allow_groups' => ''])->sendAllow);
    }

    public static function invalidInput(): array
    {
        return [
            'empty name' => [['name' => ''], 'mail_policy_name_invalid'],
            'long name' => [['name' => str_repeat('a', 101)], 'mail_policy_name_invalid'],
            'mode' => [['send_mode' => 'nobody'], 'mail_policy_mode_invalid'],
            'domain without dot' => [['send_allow' => 'localhost'], ['mail_policy_entry_invalid', 'localhost']],
            'address' => [['receive_block' => 'a@@b.de'], ['mail_policy_entry_invalid', 'a@@b.de']],
            'unknown group' => [['receive_block_groups' => ['8']], ['mail_policy_entry_invalid', 'group:8']],
            'reject text with line break' => [['reject_text' => "a\r\nb"], 'mail_policy_reject_text_invalid'],
            'reject text not ASCII' => [['reject_text' => 'Nur für Azubis'], 'mail_policy_reject_text_invalid'],
            'mailbox of another domain' => [['mailboxes' => 'andere@tochter.de'], ['mail_policy_member_invalid', 'andere@tochter.de']],
            'group of another domain' => [['groups' => ['9']], ['mail_policy_member_invalid', '9']],
            'unknown domain' => [['domain' => 'fremd.de'], ['domain_not_found', 'fremd.de']],
        ];
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInput(array $input, string|array $error): void
    {
        $this->expectUserError($error, fn() => $this->service()->create($input + ['domain' => 'firma.de', 'name' => 'Test']));
        $this->assertSame([], $this->saved);
    }

    public function testNamesAreUniquePerDomain(): void
    {
        $this->repo->method('nameExists')->willReturn(true);
        $this->expectUserError(['object_exists', 'Azubis'], fn() => $this->service()->create(['domain' => 'firma.de', 'name' => 'Azubis']));
    }

    public function testPermissions(): void
    {
        $this->repo->method('find')->willReturn($this->stored());
        $da = FakeContext::domainAdmin([], ['firma.de'], ['mail_policies' => '1']);

        $this->assertSame('Test', $this->service($da)->create(['domain' => 'firma.de', 'name' => 'Test'])->name);
        $this->assertNotNull($this->service($da)->get(3));
        // other domains, no ACL, users and read-only keys
        $this->expectUserError('access_denied', fn() => $this->service($da)->create(['domain' => 'tochter.de', 'name' => 'Test']));
        $this->expectUserError(['mail_policy_entry_invalid', 'group:9'],
            fn() => $this->service($da)->create(['domain' => 'firma.de', 'name' => 'Test', 'send_block_groups' => ['9']]));
        $this->assertNull($this->service(FakeContext::domainAdmin([], ['tochter.de'], ['mail_policies' => '1']))->get(3));
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::domainAdmin([], ['firma.de'], []))->create(['domain' => 'firma.de', 'name' => 'Test']));
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::user('azubi@firma.de'))->update(3, []));
        $readOnly = new Context('admin', 'API', [], false, 'ro');
        $this->assertNotNull($this->service($readOnly)->get(3));
        $this->expectUserError('access_denied', fn() => $this->service($readOnly)->delete(3));
    }

    public function testRuleTest(): void
    {
        $policy = $this->stored(['sendMode' => 'internal', 'sendAllow' => []]);
        $this->lookup->mailboxPolicies['azubi@firma.de'] = [$policy];

        $this->assertSame(['allowed' => 0, 'side' => 'send', 'policy' => 'Azubis', 'internal_alias' => 0, 'logged_in' => 1,
            'text' => 'Sending to this recipient is not allowed by the mail policy of the sender'],
            $this->service()->test('azubi@firma.de', 'x@extern.example', true));
        // not logged in: the send rules of the mailbox do not apply
        $this->assertSame(1, $this->service()->test('azubi@firma.de', 'x@extern.example', false)['allowed']);
        // an external sender is never logged in
        $this->assertSame(0, $this->service()->test('x@extern.example', 'chef@firma.de', true)['logged_in']);
        $this->expectUserError('mail_policy_test_invalid', fn() => $this->service()->test('azubi@firma.de', 'kein-empfaenger', true));
        // domain admins test mail of their domains
        $da = FakeContext::domainAdmin([], ['tochter.de'], ['mail_policies' => '1']);
        $this->expectUserError('access_denied', fn() => $this->service($da)->test('azubi@firma.de', 'x@extern.example', true));
    }
}
