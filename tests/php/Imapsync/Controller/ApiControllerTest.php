<?php

declare(strict_types=1);

namespace Mailcow\Tests\Imapsync\Controller;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use Mailcow\Imapsync\Controller\ImapsyncApiController;
use Mailcow\Imapsync\Model\Job;
use Mailcow\Imapsync\Model\Settings;
use Mailcow\Imapsync\Model\Source;
use Mailcow\Imapsync\Service\JobService;
use Mailcow\Imapsync\Service\SourceService;
use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiControllerTest extends TestCase
{
    private SourceService&MockObject $sources;
    private JobService&MockObject $jobs;
    private ImapsyncApiController $api;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->sources = $this->createMock(SourceService::class);
        $this->jobs = $this->createMock(JobService::class);
        $this->api = new ImapsyncApiController($this->sources, $this->jobs, new ApiResponse(['password1', 'oauth_client_secret']));
    }

    private static function job(string $user2 = 'local@example.org', array $row = []): Job
    {
        return Job::fromRow($row + ['id' => 1, 'user2' => $user2, 'user1' => 'remote', 'source_id' => 5]);
    }

    private static function source(string $name = 'src'): Source
    {
        return Source::fromRow(['id' => 5, 'name' => $name]);
    }

    /**
     * Messages written to the session as [type, msg].
     */
    private function messages(): array
    {
        return array_map(fn(array $m): array => [$m['type'], $m['msg']], $_SESSION['return'] ?? []);
    }

    public function testAddJobSuccessIsLoggedWithoutSecrets(): void
    {
        $this->jobs->method('create')->willReturn(self::job('new@example.org'));

        $this->assertTrue($this->api->addJob(['username' => 'new@example.org', 'password1' => 'pw']));

        $this->assertSame([[
            'type' => 'success',
            'log' => ['syncjob', 'add', 'job', ['username' => 'new@example.org', 'password1' => '*']],
            'msg' => ['mailbox_modified', 'new@example.org'],
        ]], $_SESSION['return']);
    }

    public function testUserErrorAndDatabaseErrorBecomeDangerMessages(): void
    {
        $this->jobs->method('create')->willReturnOnConsecutiveCalls(
            $this->throwException(new UserError('object_exists', ['src / remote'])),
            $this->throwException(new PDOException('deadlock'))
        );

        $this->assertFalse($this->api->addJob([]));
        $this->assertFalse($this->api->addJob([]));

        $this->assertSame([['danger', ['object_exists', 'src / remote']], ['danger', ['mysql_error', 'deadlock']]], $this->messages());
    }

    public function testAddSource(): void
    {
        $this->sources->method('create')->willReturn(self::source('new'));

        $this->assertTrue($this->api->addSource(['name' => 'new', 'oauth_client_secret' => 's']));

        $this->assertSame([['success', ['imapsync_source_added', 'new']]], $this->messages());
        $this->assertSame('*', $_SESSION['return'][0]['log'][3]['oauth_client_secret']);
    }

    public function testSavingAClientCredentialsSourceReportsAFailedFirstToken(): void
    {
        $source = Source::fromRow(['id' => 5, 'name' => 'm365', 'auth_type' => 'XOAUTH2', 'oauth_last_refresh_error' => 'HTTP 401: invalid_client']);
        $this->sources->method('create')->willReturn($source);
        $this->sources->method('update')->willReturn($source);

        $this->assertTrue($this->api->addSource(['name' => 'm365']));
        $this->assertTrue($this->api->editSources([5], ['name' => 'm365']));

        $failed = ['danger', ['imapsync_source_token_refresh_failed', 'm365 (HTTP 401: invalid_client)']];
        $this->assertSame([$failed, ['success', ['imapsync_source_added', 'm365']], $failed, ['success', ['imapsync_source_modified', 'm365']]],
            $this->messages());
    }

    public function testEditJobsKeepsBatchMessageOrder(): void
    {
        $this->jobs->method('update')->willReturnCallback(function (int $id, array $data): Job {
            if ($id === 2) {
                throw new UserError('imapsync_source_unavailable');
            }
            return self::job('mb' . $id . '@example.org');
        });

        $this->assertTrue($this->api->editJobs([1, 'x', 2, '3', 1.5], ['active' => 0]));

        $this->assertSame([
            ['success', ['mailbox_modified', 'mb1@example.org']],
            ['danger', 'access_denied'],
            ['danger', 'imapsync_source_unavailable'],
            ['success', ['mailbox_modified', 'mb3@example.org']],
            ['danger', 'access_denied'],
        ], $this->messages());
        $this->assertSame(['syncjob', 'edit', 'job', ['id' => [1, 'x', 2, '3', 1.5], 'active' => 0]], $_SESSION['return'][0]['log']);
    }

    public function testEditJobsIdInAttributesWinsLikeArrayMerge(): void
    {
        $this->jobs->expects($this->once())->method('update')->with(7, ['id' => 7])->willReturn(self::job());

        $this->assertTrue($this->api->editJobs([1, 2], ['id' => 7]));
    }

    public function testEditJobsWithoutAclIsOneRejection(): void
    {
        $this->jobs->method('requireAcl')->willThrowException(new AccessDenied());
        $this->jobs->expects($this->never())->method('update');

        $this->assertFalse($this->api->editJobs([1, 2], []));
        $this->assertSame([['danger', 'access_denied']], $this->messages());
    }

    public function testDatabaseErrorStopsTheBatch(): void
    {
        $this->jobs->method('update')->willReturnOnConsecutiveCalls(self::job(), $this->throwException(new PDOException('gone')));

        $this->assertFalse($this->api->editJobs([1, 2, 3], []));
        $this->assertSame([['success', ['mailbox_modified', 'local@example.org']], ['danger', ['mysql_error', 'gone']]], $this->messages());
    }

    public function testEditJobsWithoutItemsDoesNothing(): void
    {
        $this->jobs->expects($this->never())->method('update');

        $this->assertTrue($this->api->editJobs(null, ['active' => 1]));
        $this->assertSame([], $this->messages());
    }

    public function testRunNextReportsRunningJobsThenTheMove(): void
    {
        $this->jobs->expects($this->once())->method('runNext')->with([4, 1, 2])->willReturn(['running' => [4], 'moved' => 2]);

        $this->assertTrue($this->api->runNext(['4', 'x', 1.7, 2]));
        $this->assertSame([['danger', ['imapsync_job_running', 4]], ['success', ['imapsync_run_next_done', 2]]], $this->messages());
        $this->assertSame(['syncjob', 'edit', 'run_next', ['id' => ['4', 'x', 1.7, 2]]], $_SESSION['return'][0]['log']);
    }

    public function testRunNextWithoutMoveReturnsFalse(): void
    {
        $this->jobs->method('runNext')->willReturnOnConsecutiveCalls(['running' => [], 'moved' => 0], $this->throwException(new AccessDenied()));

        $this->assertFalse($this->api->runNext([9]));
        $this->assertSame([], $this->messages());
        $this->assertFalse($this->api->runNext([9]));
        $this->assertSame([['danger', 'access_denied']], $this->messages());
    }

    public function testEditSourcesSkipsNonNumericIds(): void
    {
        $this->sources->method('update')->willReturnCallback(function (int $id): Source {
            if ($id === 3) {
                throw new AccessDenied();
            }
            return self::source('s' . $id);
        });

        $this->assertTrue($this->api->editSources(['a', 2, 3, '1.5'], ['name' => 'x']));
        $this->assertSame([['success', ['imapsync_source_modified', 's2']], ['danger', 'access_denied'], ['danger', 'access_denied']], $this->messages());
    }

    public function testEditSourcesNeedsAnEditor(): void
    {
        $this->sources->method('requireEditor')->willThrowException(new AccessDenied());

        $this->assertFalse($this->api->editSources([1], []));
        $this->assertSame([['danger', 'access_denied']], $this->messages());
    }

    public function testRefreshTokenUsesTheFirstId(): void
    {
        $this->sources->expects($this->once())->method('refreshToken')->with(6)->willReturn(self::source('m365'));

        $this->assertTrue($this->api->refreshSourceToken(['6', '7']));
        $this->assertFalse($this->api->refreshSourceToken([]));
        $this->assertFalse($this->api->refreshSourceToken(null));

        $this->assertSame([['success', ['imapsync_source_token_refreshed', 'm365']], ['danger', 'access_denied'], ['danger', 'access_denied']], $this->messages());
        $this->assertSame(['syncjob', 'edit', 'refresh_token', ['id' => ['6', '7']]], $_SESSION['return'][0]['log']);
    }

    public function testEditSettings(): void
    {
        $this->jobs->method('saveSettings')->willReturnOnConsecutiveCalls(new Settings(), $this->throwException(new UserError('imapsync_max_parallel_invalid')));

        $this->assertTrue($this->api->editSettings(['max_parallel' => 2]));
        $this->assertFalse($this->api->editSettings(['max_parallel' => 0]));
        $this->assertSame([['success', 'max_parallel_saved'], ['danger', 'imapsync_max_parallel_invalid']], $this->messages());
        $this->assertSame(['syncjob', 'edit', 'settings', ['max_parallel' => 2]], $_SESSION['return'][0]['log']);
    }

    public function testDeleteJobsUsesTheRequestIdInTheMessage(): void
    {
        $this->jobs->method('delete')->willReturnCallback(function (int $id): void {
            if ($id === 8) {
                throw new AccessDenied();
            }
        });

        $this->assertTrue($this->api->deleteJobs(['5', 8, 'x']));
        $this->assertSame([['success', ['deleted_syncjob', '5']], ['danger', 'access_denied'], ['danger', 'access_denied']], $this->messages());
    }

    public function testDeleteSources(): void
    {
        $this->sources->method('delete')->willReturnCallback(function (int $id): Source {
            if ($id === 2) {
                throw new UserError('imapsync_source_in_use', ['busy']);
            }
            return self::source('gone');
        });

        $this->assertTrue($this->api->deleteSources([1, 'x', 2]));
        $this->assertSame([['success', ['imapsync_source_deleted', 'gone']], ['danger', ['imapsync_source_in_use', 'busy']]], $this->messages());

        $_SESSION = [];
        $this->sources->method('requireAcl')->willThrowException(new AccessDenied());
        $this->assertFalse($this->api->deleteSources([1]));
        $this->assertSame([['danger', 'access_denied']], $this->messages());
    }

    public function testReadRoutes(): void
    {
        $source = self::source();
        $this->jobs->method('customParamOptions')->willReturn(['debug' => false]);
        $this->jobs->method('getSettings')->willReturn(new Settings());
        $this->sources->method('visibleIds')->willReturn([5, 6]);
        $this->sources->method('get')->willReturnMap([[5, $source], [6, null]]);
        $this->sources->method('toArray')->with($source)->willReturn(['id' => 5]);

        $this->assertSame(['debug' => false], $this->api->getOptions());
        $this->assertSame(['max_parallel' => 1, 'max_bytes_per_second' => 0, 'max_kb_per_second' => 0], $this->api->getSettings());
        $this->assertSame([['id' => 5]], $this->api->getSources());
        $this->assertSame(['id' => 5], $this->api->getSource('5'));
        $this->assertSame([], $this->api->getSource('abc'));
        $this->assertSame([], $this->api->getSource(6));
        $this->assertSame([], $this->messages());
    }

    public function testSettingsAreEmptyForNonAdmins(): void
    {
        $this->jobs->method('getSettings')->willThrowException(new AccessDenied());

        $this->assertSame([], $this->api->getSettings());
    }

    public function testJobRoutesHonourNoLog(): void
    {
        $job = self::job('local@example.org', ['returned_text' => 'log text', 'password1' => 'secret']);
        $this->jobs->method('idsForMailbox')->with('local@example.org')->willReturn([1]);
        $this->jobs->expects($this->exactly(2))->method('get')
            ->willReturnCallback(fn(int $id, bool $withLog): Job => $job);

        $withLog = $this->api->getJobs('local@example.org', null);
        $withoutLog = $this->api->getJob('1', 'no_log,with_password');

        $this->assertSame('log text', $withLog[0]['log']);
        $this->assertSame('', $withoutLog['log']);
        $this->assertArrayNotHasKey('password1', $withLog[0]);
        $this->assertArrayNotHasKey('returned_text', $withLog[0]);
    }

    public function testAllJobsForAdminsAndOthers(): void
    {
        $jobs = [
            1 => self::job('a@example.org', ['id' => 1, 'prio' => 0, 'last_run' => '2026-10-01 10:00:00']),
            2 => self::job('b@example.org', ['id' => 2, 'prio' => 0]),
            3 => self::job('a@example.org', ['id' => 3, 'prio' => 5, 'last_run' => '2026-10-02 10:00:00']),
            4 => self::job('b@example.org', ['id' => 4, 'prio' => 0, 'last_run' => '2026-09-01 10:00:00']),
        ];
        $this->jobs->method('canListAll')->willReturnOnConsecutiveCalls(true, false);
        $this->jobs->method('allIds')->willReturn([3, 2, 4, 1]);
        $this->jobs->method('idsForMailbox')->willReturnMap([['a@example.org', [3, 1]], ['b@example.org', [2, 4]]]);
        $this->jobs->method('get')->willReturnCallback(fn(int $id): ?Job => $jobs[$id] ?? null);

        $this->assertSame([3, 2, 4, 1], array_column($this->api->getAllJobs(null, []), 'id'));
        $this->assertSame([3, 2, 4, 1], array_column($this->api->getAllJobs('no_log', ['a@example.org', 'b@example.org']), 'id'));
    }

    public function testAccountChangedOutsideTransactionReportsDatabaseErrors(): void
    {
        $this->sources->expects($this->once())->method('onAccountRenamed')->with('old@example.org', 'new@example.org');
        $this->sources->method('onAccountDeleted')->willThrowException(new PDOException('locked'));

        $this->api->accountChanged('old@example.org', 'new@example.org');
        $this->assertSame([], $this->messages());

        $this->api->accountChanged('old@example.org');
        $this->assertSame([[
            'type' => 'danger',
            'log' => ['imapsync_account_changed', 'old@example.org', null],
            'msg' => ['mysql_error', 'locked'],
        ]], $_SESSION['return']);
    }
}
