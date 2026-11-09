<?php

declare(strict_types=1);

namespace Mailcow\Tests\Backup\Controller;

use Mailcow\Backup\Controller\BackupApiController;
use Mailcow\Backup\Model\Job;
use Mailcow\Backup\Model\Settings;
use Mailcow\Backup\Service\DomainRestoreService;
use Mailcow\Backup\Service\ExportService;
use Mailcow\Backup\Service\JobService;
use Mailcow\Backup\Service\RestoreService;
use Mailcow\Backup\Service\SettingsService;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BackupApiControllerTest extends TestCase
{
    private SettingsService&MockObject $settings;
    private JobService&MockObject $jobs;
    private RestoreService&MockObject $restore;
    private DomainRestoreService&MockObject $domainRestore;
    private BackupApiController $api;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->settings = $this->createMock(SettingsService::class);
        $this->jobs = $this->createMock(JobService::class);
        $this->jobs->method('view')->willReturnCallback(fn(Job $job, bool $withLog = false): array => $job->toArray($withLog));
        $this->restore = $this->createMock(RestoreService::class);
        $this->domainRestore = $this->createMock(DomainRestoreService::class);
        $this->api = new BackupApiController($this->settings, $this->jobs, $this->createMock(ExportService::class), $this->restore,
            $this->domainRestore, new ApiResponse(Settings::SECRETS));
    }

    private static function job(int $id, string $type): Job
    {
        $job = new Job($type);
        $job->id = $id;
        $job->params = ($type === 'restore') ? ['mailboxes' => ['a@firma.de', 'b@firma.de']] : [];
        return $job;
    }

    public function testSecretsAreMaskedInTheLog(): void
    {
        $this->settings->expects($this->once())->method('save');

        $this->assertTrue($this->api->editSettings(['password' => 'repo-pw', 's3_secret_key' => 'S3', 'rest_password' => '', 'keep_daily' => '9']));

        $this->assertSame([['type' => 'success', 'log' => ['backup', 'edit', 'settings', ['password' => '*', 's3_secret_key' => '*', 'rest_password' => '',
            'keep_daily' => '9']], 'msg' => 'backup_settings_saved']], $_SESSION['return']);
    }

    public function testJobMessages(): void
    {
        $this->jobs->method('create')->willReturnOnConsecutiveCalls(self::job(4, 'backup'), $this->throwException(new UserError('backup_job_active', ['backup'])),
            $this->throwException(new PDOException('gone away')));
        $this->jobs->method('restore')->willReturn(self::job(5, 'restore'));
        $this->jobs->method('cancel')->willReturnCallback(fn(int $id): Job => ($id === 4) ? self::job(4, 'backup') : throw new UserError('backup_job_not_cancellable', [$id]));

        $this->assertTrue($this->api->addJob(['type' => 'backup']));
        $this->assertFalse($this->api->addJob(['type' => 'backup']));
        $this->assertFalse($this->api->addJob(['type' => 'backup']));
        $this->assertTrue($this->api->addRestore(['snapshot' => 'abc']));
        $this->assertTrue($this->api->deleteJobs([4, '7', 'x']));

        $this->assertSame([
            ['success', ['backup_job_added', 'backup', 4]],
            ['danger', ['backup_job_active', 'backup']],
            ['danger', ['mysql_error', 'gone away']],
            ['success', ['backup_restore_added', 5, 2]],
            ['success', ['backup_job_cancelled', 4]],
            ['danger', ['backup_job_not_cancellable', 7]],
            ['danger', 'access_denied'],
        ], array_map(fn(array $m): array => [$m['type'], $m['msg']], $_SESSION['return']));
        $this->assertSame(['backup', 'delete', 'job', ['id' => [4, '7', 'x']]], $_SESSION['return'][4]['log']);
    }

    public function testDemoModeChangesNothing(): void
    {
        $this->settings->expects($this->never())->method('save');
        foreach (['create', 'restore', 'cancel'] as $method) {
            $this->jobs->expects($this->never())->method($method);
        }
        $this->jobs->method('status')->willReturn(['configured' => false]);
        $api = new BackupApiController($this->settings, $this->jobs, $this->createMock(ExportService::class), $this->restore,
            $this->domainRestore, new ApiResponse(Settings::SECRETS), true);

        $this->assertFalse($api->editSettings(['target' => 's3', 's3_endpoint' => 'https://s3.example.org']));
        $this->assertFalse($api->addJob(['type' => 'backup']));
        $this->assertFalse($api->addRestore(['snapshot' => 'abc']));
        $this->assertFalse($api->deleteJobs([4]));

        $this->assertSame(array_fill(0, 4, ['danger', 'demo_mode_enabled']),
            array_map(fn(array $m): array => [$m['type'], $m['msg']], $_SESSION['return']));
        $this->assertSame(['configured' => false], $api->getStatus());
    }

    public function testReads(): void
    {
        $this->jobs->method('get')->willReturnCallback(fn(int $id): ?Job => ($id === 4) ? self::job(4, 'backup') : null);
        $this->jobs->method('list')->willReturn([self::job(4, 'backup')]);

        $this->assertSame([4, ''], [$this->api->getJob('4')['id'], $this->api->getJob('4')['log']]);
        $this->assertSame([], $this->api->getJob('x'));
        $this->assertSame([], $this->api->getJob(5));
        $this->assertArrayNotHasKey('log', $this->api->getJobs()[0]);
        $this->assertSame([], $this->api->getSettings());
    }

    public function testTheRunnersActions(): void
    {
        $this->restore->expects($this->once())->method('merge')->with(12, 'a@firma.de', ['mailbox' => []])
            ->willReturn(['bcc' => ['added' => 1, 'skipped' => 0, 'errors' => []]]);
        $this->restore->expects($this->once())->method('mergeAliases')->with(12, [['address' => 'x@firma.de']])
            ->willReturn(['aliases' => ['added' => 1, 'skipped' => 0, 'errors' => []]]);
        $this->domainRestore->expects($this->once())->method('restore')->with(12, ['domain' => ['domain' => 'firma.de']])
            ->willReturn(['domain' => ['added' => 1, 'skipped' => 0, 'errors' => [], 'created' => 1]]);

        // the components come from the job, never from the request
        $this->assertSame(['result' => ['bcc' => ['added' => 1, 'skipped' => 0, 'errors' => []]]],
            $this->api->post(['action' => 'restore', 'job' => 12, 'username' => 'a@firma.de', 'data' => ['mailbox' => []], 'components' => ['password']]));
        $this->assertSame(['result' => ['aliases' => ['added' => 1, 'skipped' => 0, 'errors' => []]]],
            $this->api->post(['action' => 'restore_aliases', 'job' => 12, 'aliases' => [['address' => 'x@firma.de']]]));
        $this->assertSame(1, $this->api->post(['action' => 'restore_domain', 'job' => 12, 'data' => ['domain' => ['domain' => 'firma.de']]])['result']['domain']['created']);
        try {
            $this->api->post(['action' => 'drop_everything']);
            $this->fail('UserError expected');
        } catch (UserError $e) {
            $this->assertSame('backup_action_invalid', $e->key);
        }
    }

    public function testSearchAndJobsOfOneType(): void
    {
        $this->jobs->method('search')->with('abc', 'anna@')->willReturn(['domains' => [], 'truncated' => 0]);
        $this->jobs->expects($this->once())->method('list')->with(0, 'restore')->willReturn([self::job(5, 'restore')]);

        $this->assertSame(['domains' => [], 'truncated' => 0], $this->api->getSnapshotSearch('abc', 'anna@'));
        $this->assertSame(['restore'], array_column($this->api->getJobs(0, 'restore'), 'type'));
    }

    public function testSnapshotContent(): void
    {
        $this->jobs->method('content')->with('abc')->willReturn(['snapshot' => 'abc']);
        $this->jobs->method('domainContent')->with('abc', 'firma.de')->willReturn(['domain' => 'firma.de']);

        $this->assertSame(['snapshot' => 'abc'], $this->api->getSnapshotContent('abc', null));
        $this->assertSame(['domain' => 'firma.de'], $this->api->getSnapshotContent('abc', 'firma.de'));
    }
}
