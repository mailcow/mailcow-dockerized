<?php

declare(strict_types=1);

namespace Mailcow\Tests\Imapsync\Service;

use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Imapsync\Model\Job;
use Mailcow\Imapsync\Model\Settings;
use Mailcow\Imapsync\Model\Source;
use Mailcow\Imapsync\Repository\JobRepository;
use Mailcow\Imapsync\Repository\SettingsRepository;
use Mailcow\Imapsync\Repository\TokenRepository;
use Mailcow\Imapsync\Service\JobService;
use Mailcow\Imapsync\Service\SourceService;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class JobServiceTest extends TestCase
{
    private const ALLOWLIST = ['debug' => false, 'folder' => true];

    private JobRepository&MockObject $jobs;
    private SourceService&MockObject $sources;
    private TokenRepository&MockObject $tokens;
    private SettingsRepository&MockObject $settings;

    protected function setUp(): void
    {
        $this->jobs = $this->createMock(JobRepository::class);
        $this->sources = $this->createMock(SourceService::class);
        $this->tokens = $this->createMock(TokenRepository::class);
        $this->settings = $this->createMock(SettingsRepository::class);
    }

    private function service(Context $context): JobService
    {
        return new JobService($this->jobs, $this->sources, $this->tokens, $this->settings, $context, self::ALLOWLIST);
    }

    private static function source(array $row = []): Source
    {
        return Source::fromRow($row + ['id' => 5, 'name' => 'src', 'created_by' => 'admin', 'host1' => 'imap.example.org',
            'port1' => 993, 'enc1' => 'SSL', 'auth_type' => 'PLAIN', 'active' => 1]);
    }

    private static function storedJob(array $row = [], array $source = []): Job
    {
        $source += ['source_name' => 'src', 'source_auth_type' => 'PLAIN', 'source_oauth_flow' => 'client_credentials',
            'source_created_by' => 'admin', 'source_active' => 1];
        return Job::fromRow($row + $source + [
            'id' => 9, 'user2' => 'local@example.org', 'user1' => 'remote@example.net', 'password1' => 'stored-pw',
            'source_id' => 5, 'mins_interval' => 20, 'maxage' => 0, 'timeout1' => 600, 'timeout2' => 600,
            'maxbytespersecond' => '0', 'exclude' => '', 'subfolder2' => '', 'custom_params' => '[{"o":"debug","v":""}]',
            'last_run' => '2026-10-01 10:00:00', 'success' => 1, 'prio' => 3, 'active' => 1,
        ]);
    }

    private static function jobInput(array $override = []): array
    {
        return $override + ['username' => 'local@example.org', 'user1' => 'remote@example.net', 'password1' => 'pw',
            'source_id' => 5, 'mins_interval' => 20];
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

    public function testCreateNeedsAcl(): void
    {
        $this->jobs->expects($this->never())->method('insert');

        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::user('u@example.org', []))->create(self::jobInput()));
    }

    public function testOwnerMailboxRules(): void
    {
        $this->expectUserError('no_user_defined', fn() => $this->service(FakeContext::domainAdmin())->create(self::jobInput(['username' => null])));
        $this->expectUserError('no_user_defined', fn() => $this->service(FakeContext::admin())->create(self::jobInput(['username' => 'not-a-mailbox'])));
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::user('u@example.org'))->create(self::jobInput()));
    }

    public function testCreateStoresJobWithDefaults(): void
    {
        $this->sources->method('get')->with(5)->willReturn(self::source());
        $this->jobs->expects($this->once())->method('insert')->with($this->callback(function (Job $job): bool {
            return $job->user2 === 'u@example.org' && $job->user1 === 'remote@example.net' && $job->password1 === 'pw'
                && $job->maxage === 0 && $job->timeout1 === 600 && $job->timeout2 === 600 && $job->maxBytesPerSecond === '0'
                && $job->minsInterval === 20 && $job->active === 0 && $job->customParams === '[{"o":"folder","v":"INBOX"}]';
        }));

        $job = $this->service(FakeContext::user('u@example.org'))->create(self::jobInput([
            'username' => null, 'user1' => ' remote@example.net ', 'maxage' => 'abc', 'timeout1' => 0, 'maxbytespersecond' => 200000000,
            'custom_params' => [['o' => 'folder', 'v' => 'INBOX']],
        ]));

        $this->assertSame('u@example.org', $job->user2);
    }

    public function testCreateKeepsValidNumbers(): void
    {
        $this->sources->method('get')->willReturn(self::source());
        $this->jobs->expects($this->once())->method('insert')->with($this->callback(
            fn(Job $job): bool => $job->maxage === 30 && $job->timeout1 === 120 && $job->maxBytesPerSecond === '5000' && $job->minsInterval === 43800
        ));

        $this->service(FakeContext::admin())->create(self::jobInput(['maxage' => '30', 'timeout1' => '120', 'maxbytespersecond' => '5000', 'mins_interval' => 43800]));
    }

    public static function invalidCreates(): array
    {
        return [
            'user1 missing' => [['user1' => ''], 'imapsync_user1_invalid'],
            'user1 array' => [['user1' => ['x']], 'imapsync_user1_invalid'],
            'user1 too long' => [['user1' => str_repeat('u', 256)], 'imapsync_user1_invalid'],
            'password missing' => [['password1' => ''], 'password_empty'],
            'password array' => [['password1' => ['x']], 'password_empty'],
            'custom params string' => [['custom_params' => '--debug'], 'imapsync_custom_params_format'],
            'custom param unknown' => [['custom_params' => [['o' => 'logfile', 'v' => 'x']]], ['imapsync_custom_param_invalid', 'logfile']],
            'exclude array' => [['exclude' => ['x']], 'access_denied'],
            'interval 0' => [['mins_interval' => 0], 'access_denied'],
            'interval too large' => [['mins_interval' => 43801], 'access_denied'],
            'exclude no regex' => [['exclude' => '(unclosed'], 'access_denied'],
            'user1 checked before password' => [['user1' => '', 'password1' => ''], 'imapsync_user1_invalid'],
        ];
    }

    #[DataProvider('invalidCreates')]
    public function testCreateValidation(array $override, string|array $msg): void
    {
        $this->sources->method('get')->willReturn(self::source());
        $this->jobs->expects($this->never())->method('insert');

        $this->expectUserError($msg, fn() => $this->service(FakeContext::admin())->create(self::jobInput($override)));
    }

    public function testCreateNeedsVisibleActiveSource(): void
    {
        $this->sources->method('get')->willReturnOnConsecutiveCalls(null, self::source(['active' => 0]));
        $service = $this->service(FakeContext::admin());

        $this->expectUserError('imapsync_source_unavailable', fn() => $service->create(self::jobInput()));
        $this->expectUserError('imapsync_source_unavailable', fn() => $service->create(self::jobInput()));
    }

    public function testDuplicateTupleIsRejected(): void
    {
        $this->sources->method('get')->willReturn(self::source(['name' => '<src>']));
        $this->jobs->method('tupleExists')->with(5, 'local@example.org', 'remote@example.net', null)->willReturn(true);

        $this->expectUserError(['object_exists', '&lt;src&gt; / remote@example.net'], fn() => $this->service(FakeContext::admin())->create(self::jobInput()));
    }

    public function testAuthorizationCodeJobNeedsTokenOfOwnerAndRemoteUser(): void
    {
        $this->sources->method('get')->willReturn(self::source(['auth_type' => 'XOAUTH2', 'oauth_flow' => 'authorization_code']));
        $this->tokens->method('exists')->with(5, 'local@example.org', 'remote@example.net')->willReturnOnConsecutiveCalls(false, true);
        $this->jobs->expects($this->once())->method('insert')->with($this->callback(fn(Job $job): bool => $job->password1 === ''));
        $service = $this->service(FakeContext::admin());

        $this->expectUserError('imapsync_source_oauth_connect_required', fn() => $service->create(self::jobInput()));
        $service->create(self::jobInput());
    }

    public function testClientCredentialsJobStoresNoPassword(): void
    {
        $this->sources->method('get')->willReturn(self::source(['auth_type' => 'XOAUTH2', 'oauth_flow' => 'client_credentials']));
        $this->tokens->expects($this->never())->method('exists');
        $this->jobs->expects($this->once())->method('insert')->with($this->callback(fn(Job $job): bool => $job->password1 === ''));

        $this->service(FakeContext::admin())->create(self::jobInput(['password1' => '']));
    }

    public function testUpdateNeedsAccessToTheJob(): void
    {
        $this->jobs->method('find')->willReturnOnConsecutiveCalls(null, self::storedJob());
        $this->jobs->expects($this->never())->method('update');
        $service = $this->service(FakeContext::user('u@example.org'));

        $this->expectUserError('access_denied', fn() => $service->update(9, []));
        $this->expectUserError('access_denied', fn() => $service->update(9, []));
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::user('local@example.org', []))->update(9, []));
    }

    public function testEmptyPasswordKeepsTheStoredOne(): void
    {
        $this->jobs->method('find')->willReturn(self::storedJob());
        $stored = [];
        $this->jobs->method('update')->willReturnCallback(function (Job $job) use (&$stored): void {
            $stored[] = $job->password1;
        });
        $service = $this->service(FakeContext::user('local@example.org'));

        $service->update(9, ['password1' => '']);
        $service->update(9, ['password1' => ['x']]);
        $service->update(9, ['password1' => 'new']);

        $this->assertSame(['stored-pw', 'stored-pw', 'new'], $stored);
    }

    public function testMissingAttributesKeepTheirValue(): void
    {
        $this->jobs->method('find')->willReturn(self::storedJob());
        $this->jobs->expects($this->once())->method('update')->with($this->callback(function (Job $job): bool {
            return $job->active === 0 && $job->user1 === 'remote@example.net' && $job->minsInterval === 20 && $job->prio === 3
                && $job->customParams === '[{"o":"debug","v":""}]' && $job->lastRun === null && $job->success === 1
                && $job->maxage === 7 && $job->timeout1 === 600;
        }));
        $this->sources->expects($this->never())->method('get');
        $this->tokens->expects($this->never())->method('deleteIfUnused');

        $this->service(FakeContext::user('local@example.org'))->update(9, [
            'active' => '0', 'user1' => '', 'mins_interval' => '', 'prio' => 99, 'last_run' => '', 'maxage' => '7', 'timeout1' => '',
        ]);
    }

    public function testOnlyAdminsChangePrio(): void
    {
        $this->jobs->method('find')->willReturn(self::storedJob());
        $this->jobs->expects($this->once())->method('update')->with($this->callback(fn(Job $job): bool => $job->prio === 99));

        $this->service(FakeContext::admin())->update(9, ['prio' => '99']);
    }

    public function testJobOnInvisibleSourceStaysEditable(): void
    {
        $this->jobs->method('find')->willReturn(self::storedJob([], ['source_created_by' => 'someone-else', 'source_active' => 0]));
        $this->sources->expects($this->never())->method('get');
        $this->jobs->expects($this->once())->method('update');

        $this->service(FakeContext::user('local@example.org'))->update(9, ['active' => 0]);
    }

    public function testMovingToAnotherSourceNeedsVisibleActiveSource(): void
    {
        $this->jobs->method('find')->willReturn(self::storedJob());
        $this->sources->method('get')->with(6)->willReturnOnConsecutiveCalls(null, self::source(['id' => 6, 'active' => 0]));
        $service = $this->service(FakeContext::user('local@example.org'));

        $this->expectUserError('imapsync_source_unavailable', fn() => $service->update(9, ['source_id' => 6]));
        $this->expectUserError('imapsync_source_unavailable', fn() => $service->update(9, ['source_id' => 6]));
    }

    public function testMovingToXOAuth2SourceWipesPasswordAndChecksToken(): void
    {
        $this->jobs->method('find')->willReturn(self::storedJob());
        $this->sources->method('get')->willReturn(self::source(['id' => 6, 'auth_type' => 'XOAUTH2', 'oauth_flow' => 'authorization_code']));
        $this->tokens->method('exists')->with(6, 'local@example.org', 'remote@example.net')->willReturnOnConsecutiveCalls(false, true);
        $this->jobs->expects($this->once())->method('update')->with($this->callback(fn(Job $job): bool => $job->password1 === '' && $job->sourceId === 6));
        $this->tokens->expects($this->once())->method('deleteIfUnused')->with(5, 'local@example.org', 'remote@example.net');
        $service = $this->service(FakeContext::user('local@example.org'));

        $this->expectUserError('imapsync_source_oauth_connect_required', fn() => $service->update(9, ['source_id' => 6]));
        $service->update(9, ['source_id' => 6]);
    }

    public function testSwitchingFromXOAuth2NeedsPassword(): void
    {
        $this->jobs->method('find')->willReturn(self::storedJob(['password1' => '']));
        $this->sources->method('get')->willReturn(self::source(['id' => 6]));

        $this->expectUserError('password_empty', fn() => $this->service(FakeContext::user('local@example.org'))->update(9, ['source_id' => 6]));
    }

    public function testRemoteUserOfClientCredentialsSourceNeedsSourceAccess(): void
    {
        $job = self::storedJob(['password1' => ''], ['source_auth_type' => 'XOAUTH2', 'source_oauth_flow' => 'client_credentials',
            'source_created_by' => 'admin']);
        $this->jobs->method('find')->willReturn($job);
        $this->sources->method('get')->with(5)->willReturnOnConsecutiveCalls(null, self::source());
        $this->sources->method('canEdit')->willReturn(false);
        $this->jobs->expects($this->once())->method('update');
        $service = $this->service(FakeContext::user('local@example.org'));

        $this->expectUserError('access_denied', fn() => $service->update(9, ['user1' => 'ceo@example.net']));
        $service->update(9, ['user1' => 'ceo@example.net']);
    }

    public function testChangedTupleMustStayUnique(): void
    {
        $this->jobs->method('find')->willReturn(self::storedJob());
        $this->jobs->method('tupleExists')->with(5, 'local@example.org', 'other@example.net', 9)->willReturn(true);

        $this->expectUserError(['object_exists', 'src / other@example.net'],
            fn() => $this->service(FakeContext::user('local@example.org'))->update(9, ['user1' => 'other@example.net']));
    }

    public function testDeleteDropsUnusedToken(): void
    {
        $this->jobs->method('find')->willReturnOnConsecutiveCalls(self::storedJob(['user2' => 'x@example.org']), self::storedJob());
        $this->jobs->expects($this->once())->method('delete')->with(9);
        $this->tokens->expects($this->once())->method('deleteIfUnused')->with(5, 'local@example.org', 'remote@example.net');
        $service = $this->service(FakeContext::user('local@example.org'));

        $this->expectUserError('access_denied', fn() => $service->delete(9));
        $service->delete(9);
    }

    public function testAdminsManageOrphanedJobs(): void
    {
        $this->jobs->method('find')->willReturn(self::storedJob(['user2' => 'deleted@example.org']));

        $this->assertNotNull($this->service(new FakeContext('admin', 'admin'))->get(9));
        $this->assertNull($this->service(FakeContext::domainAdmin())->get(9));
    }

    public function testJobListsFollowMailboxAccess(): void
    {
        $this->jobs->method('idsForMailbox')->willReturnMap([['u@example.org', [1, 2]], ['m@example.org', [3]]]);
        $this->jobs->method('allIds')->willReturn([3, 1, 2]);
        $user = $this->service(FakeContext::user('u@example.org'));

        $this->assertSame([1, 2], $user->idsForMailbox(null));
        $this->assertSame([1, 2], $user->idsForMailbox('not-an-address'));
        $this->assertSame([], $user->idsForMailbox('m@example.org'));
        $this->assertSame([3], $this->service(FakeContext::domainAdmin(['m@example.org']))->idsForMailbox('m@example.org'));
        $this->assertSame([], $this->service(new FakeContext('', null))->idsForMailbox(null));
        $this->assertSame([], $user->allIds());
        $this->assertSame([3, 1, 2], $this->service(FakeContext::admin())->allIds());
    }

    public function testRunNextIsAdminOnly(): void
    {
        $this->jobs->expects($this->once())->method('moveToFront')->with([4, 2])->willReturn(['running' => [], 'moved' => 2]);

        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::domainAdmin())->runNext([4]));
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::admin())->runNext([]));
        $this->assertSame(['running' => [], 'moved' => 2], $this->service(FakeContext::admin())->runNext([4, 2, 4]));
    }

    public static function invalidSettings(): array
    {
        return [
            'parallel 0' => [['max_parallel' => 0], 'imapsync_max_parallel_invalid'],
            'parallel 33' => [['max_parallel' => 33], 'imapsync_max_parallel_invalid'],
            'parallel text' => [['max_parallel' => 'many', 'max_kb_per_second' => -1], 'imapsync_max_parallel_invalid'],
            'kb negative' => [['max_parallel' => 2, 'max_kb_per_second' => -1], 'imapsync_max_kb_per_second_invalid'],
            'kb too large' => [['max_parallel' => 2, 'max_kb_per_second' => 10485761], 'imapsync_max_kb_per_second_invalid'],
        ];
    }

    #[DataProvider('invalidSettings')]
    public function testSettingsValidation(array $input, string $msg): void
    {
        $this->settings->expects($this->never())->method('save');

        $this->expectUserError($msg, fn() => $this->service(FakeContext::admin())->saveSettings($input));
    }

    public function testSettingsAreStoredInBytes(): void
    {
        $this->settings->expects($this->exactly(2))->method('save')->with($this->callback(
            fn(Settings $s): bool => $s->maxParallel === 4 && in_array($s->maxBytesPerSecond, [0, 2048], true)
        ));
        $service = $this->service(FakeContext::admin());

        $this->assertSame(2048, $service->saveSettings(['max_parallel' => '4', 'max_kb_per_second' => '2'])->maxBytesPerSecond);
        $this->assertSame(0, $service->saveSettings(['max_parallel' => 4, 'max_kb_per_second' => ''])->maxBytesPerSecond);
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::domainAdmin())->saveSettings(['max_parallel' => 1]));
        $this->expectUserError('access_denied', fn() => $this->service(FakeContext::domainAdmin())->getSettings());
    }
}
