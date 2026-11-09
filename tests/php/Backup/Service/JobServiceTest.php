<?php

declare(strict_types=1);

namespace Mailcow\Tests\Backup\Service;

use Mailcow\Backup\Model\Job;
use Mailcow\Backup\Model\Snapshot;
use Mailcow\Backup\Repository\JobRepository;
use Mailcow\Backup\Repository\SettingsRepository;
use Mailcow\Backup\Repository\SnapshotRepository;
use Mailcow\Backup\Service\JobService;
use Mailcow\Backup\Service\SettingsService;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Tests\Support\FakeContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class JobServiceTest extends TestCase
{
    private JobRepository&MockObject $jobs;
    private SnapshotRepository&MockObject $snapshots;
    private SettingsRepository&MockObject $settingsRepo;
    private array $settings = ['target' => 'local', 'password' => 'repo-password', 'password_confirmed' => '1'];
    /** @var Job[] queued or running jobs */
    private array $active = [];
    /** @var Job[] inserted jobs */
    private array $inserted = [];
    /** @var Job[] jobs by id */
    private array $stored = [];

    protected function setUp(): void
    {
        $this->settingsRepo = $this->createMock(SettingsRepository::class);
        $this->settingsRepo->method('load')->willReturnCallback(fn(): array => $this->settings);
        $this->jobs = $this->createMock(JobRepository::class);
        $this->jobs->method('insertExclusive')->willReturnCallback(function (Job $job, callable $check): int {
            $check($this->active);
            $job->id = 100 + count($this->inserted);
            $this->inserted[] = $job;
            return $job->id;
        });
        $this->jobs->method('isDomain')->willReturnCallback(fn(string $domain): bool => in_array($domain, ['firma.de', 'tochter.de'], true));
        $this->jobs->method('mailboxDomain')->willReturnCallback(fn(string $username): ?string =>
            ['anna@firma.de' => 'firma.de', 'bob@firma.de' => 'firma.de', 'carl@tochter.de' => 'tochter.de'][$username] ?? null);
        $this->jobs->method('mailboxesOf')->willReturnCallback(fn(string $domain): array =>
            ['firma.de' => ['anna@firma.de', 'bob@firma.de'], 'tochter.de' => []][$domain] ?? []);
        $this->jobs->method('mailboxNames')->willReturnCallback(fn(string $domain): array =>
            ['firma.de' => ['anna@firma.de' => 'Anna', 'bob@firma.de' => 'Bob'], 'tochter.de' => ['carl@tochter.de' => 'Carl']][$domain] ?? []);
        $this->jobs->method('domainCreated')->willReturnCallback(fn(string $domain): ?int => ['firma.de' => 1500, 'tochter.de' => 100][$domain] ?? null);
        $this->jobs->method('domainsAndUsernames')->willReturnCallback(function (array $domains): array {
            $names = ['firma.de' => ['anna@firma.de', 'bob@firma.de'], 'tochter.de' => ['carl@tochter.de']];
            $existing = array_fill_keys(array_values(array_intersect($domains, array_keys($names))), true);
            $usernames = array_map(fn(array $list): array => array_fill_keys($list, true), array_intersect_key($names, array_flip($domains)));
            return [$existing, $usernames];
        });
        $this->jobs->method('find')->willReturnCallback(fn(int $id): ?Job => $this->stored[$id] ?? null);
        $this->jobs->method('active')->willReturnCallback(fn(): array => $this->active);
        $this->snapshots = $this->createMock(SnapshotRepository::class);
        $this->snapshots->method('find')->willReturnCallback(fn(string $id): ?Snapshot => array_values(array_filter($this->snapshotList(),
            fn(Snapshot $s): bool => $s->id === $id || $s->shortId === $id))[0] ?? null);
        $this->snapshots->method('all')->willReturnCallback(fn(): array => $this->snapshotList());
    }

    /** @return Snapshot[] newest first */
    private function snapshotList(): array
    {
        // alt.de was deleted since, so were dora@firma.de and the former firma.de of snapshot d (firma.de is newer)
        $content = gzencode(json_encode(['domains' => ['firma.de' => ['created' => null, 'mailboxes' => ['anna@firma.de' => 'Anna', 'bob@firma.de' => 'Bob',
            'dora@firma.de' => 'Dora']], 'tochter.de' => ['created' => null, 'mailboxes' => ['carl@tochter.de' => 'Carl']],
            'alt.de' => ['created' => null, 'mailboxes' => ['x@alt.de' => 'X']]]]));
        return [
            Snapshot::fromRow(['id' => str_repeat('b', 64), 'short_id' => 'bbbbbbbb', 'time' => 2000, 'hostname' => 'mail', 'tags' => 'mailcow',
                'summary' => '{"data_added": 5}', 'domains' => '{"firma.de": null, "tochter.de": null, "alt.de": null}', 'content' => $content]),
            Snapshot::fromRow(['id' => str_repeat('d', 64), 'short_id' => 'dddddddd', 'time' => 1200, 'hostname' => 'mail', 'tags' => '',
                'summary' => null, 'domains' => '{"firma.de": null}']),
            Snapshot::fromRow(['id' => str_repeat('a', 64), 'short_id' => 'aaaaaaaa', 'time' => 1000, 'hostname' => 'mail', 'tags' => '',
                'summary' => null, 'domains' => '{"tochter.de": null}']),
            Snapshot::fromRow(['id' => str_repeat('c', 64), 'short_id' => 'cccccccc', 'time' => 500, 'hostname' => 'other', 'tags' => '',
                'summary' => null, 'domains' => null]),
        ];
    }

    private function service(?Context $context = null, string $timezone = 'Europe/Berlin'): JobService
    {
        $context ??= FakeContext::admin();
        return new JobService($this->jobs, $this->snapshots, new SettingsService($this->settingsRepo, $context), $context, $timezone);
    }

    private static function domainAdmin(array $acl = []): FakeContext
    {
        return FakeContext::domainAdmin(['anna@firma.de', 'bob@firma.de'], ['firma.de'], $acl);
    }

    /** A job requested at unix time 2000, restores by a domain admin unless $params say otherwise */
    private static function job(int $id, string $type, string $state = 'queued', string $domain = '', array $params = [], int $created = 2000): Job
    {
        $job = new Job($type);
        $job->id = $id;
        $job->state = $state;
        $job->domain = $domain;
        $job->params = $params + (($type === 'restore') ? ['requested_role' => 'domainadmin'] : []);
        $job->created = $created;
        return $job;
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

    private static function restore(array $input = []): array
    {
        return $input + ['snapshot' => 'bbbbbbbb', 'scope' => 'mailbox', 'mailboxes' => ['anna@firma.de'], 'components' => ['mails', 'aliases']];
    }

    public function testAdminsQueueOneJobOfEachType(): void
    {
        $job = $this->service()->create(['type' => 'check']);

        $this->assertSame(['check', 'queued', 'admin', 100], [$job->type, $job->state, $job->requestedBy, $job->id]);
        $this->active = [self::job(1, 'check', 'running'), self::job(2, 'restore')];
        $this->expectUserError(['backup_job_active', 'check'], fn() => $this->service()->create(['type' => 'check']));
        $this->assertSame('prune', $this->service()->create(['type' => 'prune'])->type);
        $this->expectUserError(['backup_job_type_invalid', 'restore'], fn() => $this->service()->create(['type' => 'restore']));
        $this->expectUserError(['backup_job_type_invalid', '&lt;x&gt;'], fn() => $this->service()->create(['type' => '<x>']));
    }

    public function testJobsNeedAConfiguredRepository(): void
    {
        $this->settings['password_confirmed'] = '0';
        $this->expectUserError('backup_password_not_confirmed', fn() => $this->service()->create(['type' => 'backup']));
        // testing the connection needs no confirmation
        $this->assertSame('init', $this->service()->create(['type' => 'init'])->type);
        $this->settings['password'] = '';
        $this->expectUserError('backup_not_configured', fn() => $this->service()->create(['type' => 'init']));
        $this->expectUserError('backup_not_configured', fn() => $this->service()->restore(self::restore()));
    }

    public function testOnlyWritingAdminsQueueJobs(): void
    {
        foreach ([new Context('admin', 'API', [], false, 'ro'), self::domainAdmin(['syncjobs' => '1']), FakeContext::user('anna@firma.de'), Context::system()] as $context) {
            $this->expectUserError('access_denied', fn() => $this->service($context)->create(['type' => 'backup']));
        }
        $this->assertSame([], $this->inserted);
    }

    public function testRestoreOfMailboxes(): void
    {
        $job = $this->service()->restore(self::restore(['mailboxes' => "Anna@firma.de\nbob@firma.de", 'components' => 'password, mails,aliases,mails',
            'mail_target' => 'original']));

        $this->assertSame(['restore', 'files', 'firma.de', 'admin'], [$job->type, $job->step, $job->domain, $job->requestedBy]);
        $this->assertSame(['snapshot' => str_repeat('b', 64), 'snapshot_time' => 2000, 'scope' => 'mailbox', 'domain' => 'firma.de',
            'mailboxes' => ['anna@firma.de', 'bob@firma.de'], 'components' => ['mails', 'aliases', 'password'], 'mail_target' => 'original',
            'requested_role' => 'admin', 'create_domain' => 0, 'create_mailboxes' => []], $job->params);
    }

    public function testRestoreOfADomain(): void
    {
        // the snapshot's mailboxes of the domain; deleted ones are created
        $job = $this->service()->restore(self::restore(['scope' => 'domain', 'domain' => 'Firma.de', 'mailboxes' => 'ignored@firma.de']));

        $this->assertSame([['anna@firma.de', 'bob@firma.de', 'dora@firma.de'], ['dora@firma.de'], 0, 'folder'],
            [$job->params['mailboxes'], $job->params['create_mailboxes'], $job->params['create_domain'], $job->params['mail_target']]);
        // snapshots without content: the current mailboxes
        $this->expectUserError('backup_mailboxes_empty', fn() => $this->service()->restore(self::restore(['snapshot' => 'aaaaaaaa', 'scope' => 'domain',
            'domain' => 'tochter.de'])));
        $this->expectUserError(['domain_not_found', 'nix.de'], fn() => $this->service()->restore(self::restore(['scope' => 'domain', 'domain' => 'nix.de'])));
    }

    public function testDeletedMailboxesAndDomainsAreCreated(): void
    {
        $job = $this->service()->restore(self::restore(['mailboxes' => ['anna@firma.de', 'dora@firma.de']]));
        $this->assertSame([['dora@firma.de'], 0], [$job->params['create_mailboxes'], $job->params['create_domain']]);

        $job = $this->service()->restore(self::restore(['scope' => 'domain', 'domain' => 'alt.de']));
        $this->assertSame(['alt.de', ['x@alt.de'], ['x@alt.de'], 1], [$job->domain, $job->params['mailboxes'], $job->params['create_mailboxes'],
            $job->params['create_domain']]);
        $this->assertSame(1, $this->service()->restore(self::restore(['mailboxes' => ['x@alt.de']]))->params['create_domain']);
        // only mailboxes the snapshot knows, and only from snapshots with content
        $this->expectUserError(['backup_mailbox_not_found', 'dora@firma.de'], fn() => $this->service()->restore(self::restore(['snapshot' => 'dddddddd',
            'mailboxes' => ['dora@firma.de']])));
        $this->expectUserError(['domain_not_found', 'alt.de'], fn() => $this->service()->restore(self::restore(['snapshot' => 'aaaaaaaa', 'scope' => 'domain',
            'domain' => 'alt.de'])));
        // domain admins create mailboxes in their domains, never domains
        $da = self::domainAdmin();
        $this->assertSame(['dora@firma.de'], $this->service($da)->restore(self::restore(['mailboxes' => ['dora@firma.de']]))->params['create_mailboxes']);
        $this->expectUserError('access_denied', fn() => $this->service($da)->restore(self::restore(['scope' => 'domain', 'domain' => 'alt.de'])));
        $this->expectUserError('access_denied', fn() => $this->service($da)->restore(self::restore(['mailboxes' => ['x@alt.de']])));
    }

    public function testInvalidRestores(): void
    {
        $cases = [
            [['snapshot' => 'ffff'], ['backup_snapshot_not_found', 'ffff']],
            [['snapshot' => ''], ['backup_snapshot_not_found', '']],
            [['scope' => 'server'], 'backup_scope_invalid'],
            [['mailboxes' => []], 'backup_mailboxes_empty'],
            [['mailboxes' => ['anna@firma.de', 'gone@firma.de']], ['backup_mailbox_not_found', 'gone@firma.de']],
            [['mailboxes' => ['anna@firma.de', 'carl@tochter.de']], 'backup_mailboxes_one_domain'],
            [['domain' => 'tochter.de'], 'backup_mailboxes_one_domain'],
            [['mailboxes' => array_map(fn(int $i): string => "u$i@firma.de", range(1, 1001))], ['backup_entries_exceeded', 1000]],
            [['components' => []], ['backup_components_invalid', '']],
            [['components' => ['mails', 'everything']], ['backup_components_invalid', 'everything']],
            [['components' => ['policies']], ['backup_components_invalid', 'policies']],
            [['components' => ['sogo_public']], ['backup_components_invalid', 'sogo_public']],
            [['components' => ['mails', 'sieve_activate']], ['backup_component_requires', 'sieve_activate', 'filters']],
            [['mail_target' => 'elsewhere'], 'backup_mail_target_invalid'],
            // the snapshot holds tochter.de only
            [['snapshot' => 'aaaaaaaa'], ['backup_domain_not_in_snapshot', 'firma.de']],
        ];
        foreach ($cases as [$input, $error]) {
            $this->expectUserError($error, fn() => $this->service()->restore(self::restore($input)));
        }
        // snapshots not made by mailcow have no domain list: the runner reports what is missing
        $this->assertNotNull($this->service()->restore(self::restore(['snapshot' => 'cccccccc'])));
    }

    public function testAMailboxIsRestoredOnceAtATime(): void
    {
        $this->active = [self::job(1, 'restore', 'running', 'firma.de', ['mailboxes' => ['bob@firma.de']]), self::job(2, 'backup', 'running')];

        $this->expectUserError(['backup_restore_active', 'bob@firma.de'], fn() => $this->service()->restore(self::restore(['mailboxes' => ['anna@firma.de', 'bob@firma.de']])));
        $this->assertCount(1, [$this->service()->restore(self::restore())]);
    }

    public function testDomainAdminsRestoreTheirMailboxesWithinTheirAcl(): void
    {
        $da = self::domainAdmin(['syncjobs' => '1', 'protocol_access' => '1']);

        $job = $this->service($da)->restore(self::restore(['components' => ['mails', 'sogo', 'syncjobs', 'settings']]));

        $this->assertSame(['domainadmin', 'da', 1, 0, 0], [$job->params['requested_role'], $job->requestedBy, $job->params['acl']['syncjobs'],
            $job->params['acl']['unlimited_quota'], $job->params['acl']['app_passwds']]);
        $this->assertSame([1, ['firma.de']], [$job->params['acl']['protocol_access'], $job->params['allowed_domains']]);
        $this->assertSame('firma.de', $this->service($da)->restore(self::restore(['scope' => 'domain', 'domain' => 'firma.de']))->domain);
        // components he could not manage himself
        $this->expectUserError(['backup_component_denied', 'app_passwords'], fn() => $this->service($da)->restore(self::restore(['components' => ['app_passwords']])));
        $this->expectUserError(['backup_component_denied', 'groups'], fn() => $this->service($da)->restore(self::restore(['components' => ['groups']])));
        $this->expectUserError(['backup_component_denied', 'sieve_activate'], fn() => $this->service($da)->restore(self::restore(['components' => ['sieve_activate']])));
        $retention = self::domainAdmin(['retention' => '1']);
        $this->assertNotNull($this->service($retention)->restore(self::restore(['components' => ['retention_rules']])));
        $this->expectUserError(['backup_component_denied', 'mail_policies'], fn() => $this->service($retention)->restore(self::restore(['components' => ['mail_policies']])));
        // snapshots made before his domain was created hold a former domain of that name
        $this->expectUserError(['backup_snapshot_not_found', 'dddddddd'], fn() => $this->service($da)->restore(self::restore(['snapshot' => 'dddddddd'])));
        // mailboxes and domains of others
        $this->expectUserError('access_denied', fn() => $this->service($da)->restore(self::restore(['mailboxes' => ['carl@tochter.de']])));
        $this->expectUserError('access_denied', fn() => $this->service($da)->restore(self::restore(['scope' => 'domain', 'domain' => 'tochter.de'])));
        // unknown ones look the same
        $this->expectUserError('access_denied', fn() => $this->service($da)->restore(self::restore(['mailboxes' => ['nobody@tochter.de']])));
        $this->expectUserError('access_denied', fn() => $this->service($da)->restore(self::restore(['mailboxes' => ['x@nirgends.de']])));
        $this->expectUserError('access_denied', fn() => $this->service($da)->restore(self::restore(['scope' => 'domain', 'domain' => 'nirgends.de'])));
    }

    public function testUsersAndReadOnlyKeysCannotRestore(): void
    {
        foreach ([FakeContext::user('anna@firma.de'), new Context('admin', 'API', [], false, 'ro'), Context::system()] as $context) {
            $this->expectUserError('access_denied', fn() => $this->service($context)->restore(self::restore()));
        }
        $this->assertSame([], $this->inserted);
    }

    public function testVisibilityOfJobs(): void
    {
        $this->stored = [1 => self::job(1, 'backup', 'done'), 2 => self::job(2, 'restore', 'done', 'firma.de', ['mailboxes' => ['anna@firma.de']]),
            3 => self::job(3, 'restore', 'done', 'tochter.de'), 4 => self::job(4, 'restore', 'done', 'firma.de', [], 1400)];
        $da = self::domainAdmin();

        $this->assertNotNull($this->service()->get(1));
        $this->assertNotNull($this->service(new Context('admin', 'API', [], false, 'ro'))->get(3));
        $this->assertSame([null, 2, null], [$this->service($da)->get(1), $this->service($da)->get(2)?->id, $this->service($da)->get(3)]);
        // a restore of a former domain of the name (before firma.de was created at 1500)
        $this->assertNull($this->service($da)->get(4));
        $this->assertSame(4, $this->service()->get(4)?->id);
        $this->assertNull($this->service(FakeContext::user('anna@firma.de'))->get(2));
        // the list is filtered in SQL: the repository gets the caller and the limit
        $this->jobs->expects($this->exactly(2))->method('visible')->willReturnCallback(function (Context $context, int $limit): array {
            $this->assertContains($limit, [100, 1000]);
            return [];
        });
        $this->service($da)->list();
        $this->service()->list(5000);
    }

    public function testQueuedAndRunningJobsCanBeCancelled(): void
    {
        $mails = self::job(5, 'restore', 'running', 'firma.de');
        $mails->step = 'mails_running';
        $files = self::job(6, 'restore', 'running', 'firma.de');
        $files->step = 'files';
        $this->stored = [1 => self::job(1, 'backup'), 2 => self::job(2, 'backup', 'running'), 3 => self::job(3, 'restore', 'queued', 'firma.de'),
            4 => self::job(4, 'restore', 'queued', 'tochter.de'), 5 => $mails, 6 => $files, 7 => self::job(7, 'check', 'done'),
            8 => self::job(8, 'restore', 'queued', 'firma.de', ['requested_role' => 'admin'])];
        $this->jobs->method('cancel')->willReturn(true);

        $this->assertSame('cancelled', $this->service()->cancel(1)->state);
        $this->assertSame('cancelled', $this->service()->cancel(2)->state);
        $this->assertSame('cancelled', $this->service()->cancel(6)->state);
        // the dovecot step runs to its end
        $this->expectUserError(['backup_job_not_cancellable', 5], fn() => $this->service()->cancel(5));
        $this->expectUserError(['backup_job_not_cancellable', 7], fn() => $this->service()->cancel(7));
        $this->assertSame('cancelled', $this->service(self::domainAdmin())->cancel(3)->state);
        $this->expectUserError('access_denied', fn() => $this->service(self::domainAdmin())->cancel(4));
        // a restore an admin requested is not the domain admin's to cancel
        $this->expectUserError('access_denied', fn() => $this->service(self::domainAdmin())->cancel(8));
        $this->assertSame('cancelled', $this->service()->cancel(8)->state);
        $this->expectUserError('access_denied', fn() => $this->service(self::domainAdmin())->cancel(1));
        $this->expectUserError('access_denied', fn() => $this->service(new Context('admin', 'API', [], false, 'ro'))->cancel(1));
        $this->expectUserError('access_denied', fn() => $this->service()->cancel(99));
    }

    public function testTheRunnerMayHaveFinishedMeanwhile(): void
    {
        $this->stored = [1 => self::job(1, 'backup', 'running')];
        $this->jobs->method('cancel')->willReturn(false);

        $this->expectUserError(['backup_job_not_cancellable', 1], fn() => $this->service()->cancel(1));
    }

    public function testContentOfASnapshot(): void
    {
        $this->assertSame(['snapshot' => str_repeat('b', 64), 'time' => 2000, 'available' => 1, 'domains' => [
            ['domain' => 'firma.de', 'exists' => 1, 'mailboxes' => 3, 'missing' => 1],
            ['domain' => 'tochter.de', 'exists' => 1, 'mailboxes' => 1, 'missing' => 0],
            ['domain' => 'alt.de', 'exists' => 0, 'mailboxes' => 1, 'missing' => 1],
        ]], $this->service()->content('bbbbbbbb'));
        $this->assertSame(['domain' => 'firma.de', 'exists' => 1, 'mailboxes' => [['username' => 'anna@firma.de', 'name' => 'Anna', 'exists' => 1],
            ['username' => 'bob@firma.de', 'name' => 'Bob', 'exists' => 1], ['username' => 'dora@firma.de', 'name' => 'Dora', 'exists' => 0]]],
            $this->service()->domainContent(str_repeat('b', 64), 'Firma.de'));
        $this->assertSame([['username' => 'x@alt.de', 'name' => 'X', 'exists' => 0]], $this->service()->domainContent('bbbbbbbb', 'alt.de')['mailboxes']);
        // without content: the manifest's domains with their current mailboxes
        $this->assertSame(['snapshot' => str_repeat('a', 64), 'time' => 1000, 'available' => 0, 'domains' => [
            ['domain' => 'tochter.de', 'exists' => 1, 'mailboxes' => 1, 'missing' => 0]]], $this->service()->content('aaaaaaaa'));
        $this->assertSame([['username' => 'carl@tochter.de', 'name' => 'Carl', 'exists' => 1]], $this->service()->domainContent('aaaaaaaa', 'tochter.de')['mailboxes']);
        $this->assertSame([], $this->service()->content('nope'));
        $this->assertSame([], $this->service()->domainContent('bbbbbbbb', 'nix.de'));
    }

    public function testSearchInASnapshot(): void
    {
        $names = fn(array $result): array => array_map(fn(array $domain): array => [$domain['domain'], $domain['exists'],
            array_map(fn(array $m): string => $m['username'] . ($m['exists'] ? '' : ' (deleted)'), $domain['mailboxes'])], $result['domains']);

        // local part, address and name; a matching domain comes with all its mailboxes
        $this->assertSame([['firma.de', 1, ['dora@firma.de (deleted)']]], $names($this->service()->search('bbbbbbbb', 'DOR')));
        $this->assertSame([['firma.de', 1, ['bob@firma.de']]], $names($this->service()->search('bbbbbbbb', 'Bob')));
        $this->assertSame([['alt.de', 0, ['x@alt.de (deleted)']]], $names($this->service()->search('bbbbbbbb', 'alt')));
        // "x@": local part only, "@d": the mailboxes of matching domains, "x@d": the address
        $this->assertSame([['firma.de', 1, ['anna@firma.de']]], $names($this->service()->search('bbbbbbbb', 'ann@')));
        $this->assertSame([['tochter.de', 1, ['carl@tochter.de']]], $names($this->service()->search('bbbbbbbb', '@tochter')));
        $this->assertSame([['firma.de', 1, ['anna@firma.de']]], $names($this->service()->search('bbbbbbbb', 'na@firma.de')));
        $this->assertSame([], $names($this->service()->search('bbbbbbbb', 'x@firma')));
        $this->assertSame(['domains' => [], 'truncated' => 0], $this->service()->search('bbbbbbbb', '@'));
        $this->assertSame(['domains' => [], 'truncated' => 0], $this->service()->search('bbbbbbbb', ''));
        $this->assertSame(0, $this->service()->search('bbbbbbbb', 'a')['truncated']);
        // snapshots without content: the current mailboxes
        $this->assertSame([['tochter.de', 1, ['carl@tochter.de']]], $names($this->service()->search('aaaaaaaa', 'carl')));
        // domain admins: their domains of newer snapshots only
        $da = self::domainAdmin();
        $this->assertSame([['firma.de', 1, ['anna@firma.de', 'bob@firma.de', 'dora@firma.de (deleted)']]], $names($this->service($da)->search('bbbbbbbb', '.de')));
        $this->assertSame([], $this->service($da)->search('dddddddd', 'anna'));
        $this->assertSame([], $this->service(FakeContext::user('anna@firma.de'))->search('bbbbbbbb', 'anna'));
        $this->assertSame([], $this->service()->search('nope', 'anna'));
    }

    public function testSearchStopsAt500Mailboxes(): void
    {
        $mailboxes = [];
        foreach (range(1, 600) as $i) {
            $mailboxes["user$i@gross.de"] = "User $i";
        }
        $this->snapshots = $this->createMock(SnapshotRepository::class);
        $this->snapshots->method('find')->willReturn(Snapshot::fromRow(['id' => str_repeat('e', 64), 'short_id' => 'eeeeeeee', 'time' => 3000,
            'domains' => '{"gross.de": null, "z.de": null}', 'content' => gzencode(json_encode(['domains' => ['gross.de' => ['created' => null, 'mailboxes' => $mailboxes],
                'z.de' => ['created' => null, 'mailboxes' => ['user@z.de' => 'Z']]]]))]));

        $result = $this->service()->search('eeeeeeee', 'user');

        $this->assertSame([1, 1, 500], [$result['truncated'], count($result['domains']), count($result['domains'][0]['mailboxes'])]);
    }

    public function testJobsOfOneType(): void
    {
        $types = [];
        $this->jobs->method('visible')->willReturnCallback(function (Context $context, int $limit, ?string $type) use (&$types): array {
            $types[] = [$limit, $type];
            return [];
        });

        $this->service()->list(0, 'restore');
        $this->service()->list(20, '');
        $this->service()->list(20, null);
        $this->assertSame([], $this->service()->list(20, 'everything'));
        $this->assertSame([[100, 'restore'], [20, null], [20, null]], $types);
    }

    public function testDomainAdminsSeeTheirDomainsOfNewerSnapshotsOnly(): void
    {
        $da = self::domainAdmin();

        $this->assertSame([['domain' => 'firma.de', 'exists' => 1, 'mailboxes' => 3, 'missing' => 1]], $this->service($da)->content('bbbbbbbb')['domains']);
        $this->assertSame([], $this->service($da)->domainContent('bbbbbbbb', 'alt.de'));
        $this->assertSame([], $this->service($da)->domainContent('bbbbbbbb', 'tochter.de'));
        $this->assertSame([], $this->service($da)->content('dddddddd'));
        $this->assertSame([], $this->service(FakeContext::user('anna@firma.de'))->content('bbbbbbbb'));
    }

    public function testSnapshotsForDomainAdminsShowOnlyTheirDomains(): void
    {
        $this->assertCount(4, $this->service()->snapshots());
        $this->assertSame(['data_added' => 5], $this->service()->snapshots()[0]['summary']);
        // snapshot d holds a former firma.de (older than the domain)
        $this->assertSame([['id' => str_repeat('b', 64), 'short_id' => 'bbbbbbbb', 'time' => 2000, 'domains' => ['firma.de'], 'mode' => 'all']],
            $this->service(self::domainAdmin())->snapshots());
        $this->assertSame([], $this->service(FakeContext::user('anna@firma.de'))->snapshots());
    }

    public function testStatusAndNextRun(): void
    {
        $daily = '[{"days":[0,1,2,3,4,5,6],"time":"02:30"}]';
        $this->settings += ['enabled' => '1', 'schedule' => $daily, 'state_runner_seen' => '1000', 'state_repo_size' => '4096',
            'state_schedule_since' => '1794000000', 'state_schedule' => $daily];
        $this->jobs->method('latestOfEachType')->willReturn(['backup' => self::job(7, 'backup', 'done')]);
        $scheduled = [];
        $this->jobs->method('scheduledSince')->willReturnCallback(function (int $since) use (&$scheduled): bool {
            return in_array($since, $scheduled, true);
        });
        // 2026-11-08 01:00 Berlin, armed since 2026-11-06: yesterday's run (2026-11-07 02:30) is due until it was made
        $status = $this->service()->status(1794096000);
        $this->assertSame(1794015000, $status['next_run']);
        $this->assertSame([1, 1, 1794095000, 0, 4096, 4, 2000, 7, null, null], [$status['enabled'], $status['configured'], $status['runner_age'],
            $status['runner_ok'], $status['repo_size'], $status['snapshots'], $status['latest_snapshot'], $status['jobs']['backup']['id'],
            $status['jobs']['check'], $status['secrets_error']]);
        $scheduled[] = 1794015000;
        $this->assertSame(1794101400, $this->service()->status(1794096000)['next_run']);
        // 03:00: today's run is due until the schedule made it, then tomorrow
        $this->assertSame(1794101400, $this->service()->status(1794103200)['next_run']);
        $scheduled[] = 1794101400;
        $this->assertSame(1794187800, $this->service()->status(1794103200)['next_run']);
        // switched on (armed) after today's time: no catch-up, tomorrow
        $this->settings['state_schedule_since'] = '1794102000';
        $this->assertSame(1794187800, $this->service()->status(1794103200)['next_run']);
        // not armed yet, or armed for another time: the next schedule time
        $this->settings['state_schedule_since'] = '';
        $this->assertSame(1794101400, $this->service()->status(1794096000)['next_run']);
        $this->settings['state_schedule_since'] = '1794000000';
        $this->settings['state_schedule'] = '[{"days":[0,1,2,3,4,5,6],"time":"01:00"}]';
        $this->assertSame(1794187800, $this->service()->status(1794103200)['next_run']);
        $this->settings['state_schedule'] = $daily;
        $this->assertSame(86400, $this->service()->status(1794103200)['schedule_gap']);
        // Monday, Wednesday and Friday at 02:30 and 14:00: from Sunday 2026-11-08 03:00 Monday 02:30 (due until made), then
        // Monday 14:00, from Monday 15:00 Wednesday 02:30; the gap from Friday 14:00 to Monday 02:30 (60.5 hours)
        $this->settings['schedule'] = '[{"days":[1,3,5],"time":"02:30"},{"days":[1,3,5],"time":"14:00"}]';
        $this->settings['state_schedule'] = $this->settings['schedule'];
        $this->assertSame(1794187800, $this->service()->status(1794103200)['next_run']);
        $this->assertSame(1794187800, $this->service()->status(1794189600)['next_run']);
        $scheduled[] = 1794187800;
        $this->assertSame(1794229200, $this->service()->status(1794189600)['next_run']);
        $scheduled[] = 1794229200;
        $this->assertSame(1794360600, $this->service()->status(1794232800)['next_run']);
        $this->assertSame(217800, $this->service()->status(1794232800)['schedule_gap']);
        // no entry: no run
        $this->settings['schedule'] = '[]';
        $this->assertNull($this->service()->status(1794232800)['next_run']);
        $this->assertNull($this->service()->status(1794232800)['schedule_gap']);
        // the schedule is off
        $this->settings['enabled'] = '0';
        $this->assertNull($this->service()->status(1794103200)['next_run']);
        $this->assertSame([], $this->service(self::domainAdmin())->status());
        // the runner cannot decrypt the credentials
        $this->settings['state_secrets_error'] = 'credentials must be entered again';
        $this->assertSame('credentials must be entered again', $this->service()->status(1794103200)['secrets_error']);
        // RESTIC_BACKUP_MODE once the runner reported it
        $this->assertNull($this->service()->status(1794103200)['mode']);
        $this->settings['state_backup_mode'] = 'data';
        $this->assertSame('data', $this->service()->status(1794103200)['mode']);
    }

    public function testDomainAdminsSeeJobsRestricted(): void
    {
        $job = self::job(9, 'restore', 'done', 'firma.de', ['requested_role' => 'admin']);
        $job->result = ['mailboxes' => ['anna@firma.de' => ['aliases' => ['added' => 0, 'skipped' => 0,
            'errors' => ['info@firma.de: goto bob@tochter.de dropped (no mailbox or alias)', 'x@firma.com: in use']]]]];
        $job->error = 'restic: Fatal: unable to open repository at sftp:u1@backup.example.org:/srv/repo';
        $job->log = "09:00 restore from s3:https://s3.example.org/bucket by admin@tochter.de\n09:01 anna@firma.de done";
        $this->jobs->method('aliasDomainsOf')->willReturn(['firma.com']);

        $view = $this->service(self::domainAdmin())->view($job, true);

        $this->assertSame(['info@firma.de: goto … dropped (no mailbox or alias)', 'x@firma.com: in use'],
            $view['result']['mailboxes']['anna@firma.de']['aliases']['errors']);
        $this->assertSame('restic: Fatal: unable to open repository at …', $view['error']);
        $this->assertSame("09:00 restore from … by …\n09:01 anna@firma.de done", $view['log']);
        // and not the account of the admin who requested it
        $job->requestedBy = 'superuser';
        $job->log = "09:00 restore job started (requested by superuser)";
        $view = $this->service(self::domainAdmin())->view($job, true);
        $this->assertSame(['', '09:00 restore job started (requested by an administrator)'], [$view['requested_by'], $view['log']]);
        // admins see everything
        $this->assertSame($job->error, $this->service()->view($job, true)['error']);
        $this->assertSame('superuser', $this->service()->view($job, true)['requested_by']);
    }

    public function testADomainAdminRestoresADomainOnceAtATime(): void
    {
        $this->active = [self::job(1, 'restore', 'running', 'firma.de', ['mailboxes' => ['bob@firma.de']])];
        $da = self::domainAdmin();

        $this->expectUserError(['backup_restore_domain_active', 'firma.de'], fn() => $this->service($da)->restore(self::restore()));
        // admins may queue restores of other mailboxes of the domain
        $this->assertSame('firma.de', $this->service()->restore(self::restore())->domain);
    }

    public function testSnapshotsRecordTheDomainsCreationTime(): void
    {
        // snapshot f holds the current firma.de (created 1500), snapshot g a former one; content without manifest entry
        // and manifest without content (its export failed) both count
        $content = fn(int $created): string => gzencode(json_encode(['domains' => ['firma.de' => ['created' => $created,
            'mailboxes' => ['anna@firma.de' => 'Anna', 'old@firma.de' => 'Old']]]]));
        $this->snapshots = $this->createMock(SnapshotRepository::class);
        $list = [
            Snapshot::fromRow(['id' => str_repeat('f', 64), 'short_id' => 'ffffffff', 'time' => 1000, 'domains' => '{"firma.de": null, "tochter.de": null}',
                'content' => $content(1500)]),
            Snapshot::fromRow(['id' => str_repeat('9', 64), 'short_id' => '99999999', 'time' => 3000, 'domains' => '{"firma.de": null}', 'content' => $content(900)]),
        ];
        $this->snapshots->method('all')->willReturn($list);
        $this->snapshots->method('find')->willReturnCallback(fn(string $id): ?Snapshot => array_values(array_filter($list, fn(Snapshot $s): bool => $s->shortId === $id))[0] ?? null);
        $da = self::domainAdmin();

        // the recorded time decides, not the snapshot time
        $this->assertSame(['ffffffff'], array_column($this->service($da)->snapshots(), 'short_id'));
        $this->assertSame([], $this->service($da)->content('99999999'));
        $this->assertSame([['domain' => 'firma.de', 'exists' => 1, 'mailboxes' => 2, 'missing' => 1],
            ['domain' => 'tochter.de', 'exists' => 1, 'mailboxes' => 1, 'missing' => 0]], $this->service()->content('ffffffff')['domains']);
        $this->assertSame(['old@firma.de'], $this->service($da)->restore(self::restore(['snapshot' => 'ffffffff', 'mailboxes' => ['old@firma.de']]))->params['create_mailboxes']);
    }

    public function testTheSnapshotListNeedsNoContent(): void
    {
        // the runner writes {domain: creation time} into the row: a list of snapshots without their content is enough
        $this->snapshots = $this->createMock(SnapshotRepository::class);
        $this->snapshots->expects($this->once())->method('all')->with(false)->willReturn([
            Snapshot::fromRow(['id' => str_repeat('f', 64), 'short_id' => 'ffffffff', 'time' => 1000, 'domains' => '{"firma.de": 1500, "tochter.de": 99}']),
            Snapshot::fromRow(['id' => str_repeat('9', 64), 'short_id' => '99999999', 'time' => 3000, 'domains' => '{"firma.de": 900}']),
        ]);

        $list = $this->service(FakeContext::domainAdmin(['anna@firma.de'], ['firma.de', 'tochter.de']))->snapshots();

        // firma.de of snapshot f is the current one, tochter.de is not (created 100 now); snapshot 9 holds a former firma.de
        $this->assertSame([['ffffffff', ['firma.de']]], array_map(fn(array $s): array => [$s['short_id'], $s['domains']], $list));
        $this->assertSame([1500, ['firma.de', 'tochter.de']], [Snapshot::fromRow(['id' => 'x', 'short_id' => 'x', 'time' => 1,
            'domains' => '{"firma.de": 1500, "tochter.de": null}'])->createdOf('firma.de'), Snapshot::fromRow(['id' => 'x', 'short_id' => 'x',
            'time' => 1, 'domains' => '{"firma.de": 1500, "tochter.de": null}'])->domains]);
    }

    public function testADeletedDomainWithoutMailboxesCanBeRestored(): void
    {
        $this->snapshots = $this->createMock(SnapshotRepository::class);
        $this->snapshots->method('find')->willReturn(Snapshot::fromRow(['id' => str_repeat('e', 64), 'short_id' => 'eeeeeeee', 'time' => 3000,
            'domains' => '{"leer.de": null}', 'content' => gzencode(json_encode(['domains' => ['leer.de' => ['created' => 100, 'mailboxes' => []]]]))]));

        $job = $this->service()->restore(self::restore(['snapshot' => 'eeeeeeee', 'scope' => 'domain', 'domain' => 'leer.de', 'components' => ['aliases']]));

        $this->assertSame([[], [], 1], [$job->params['mailboxes'], $job->params['create_mailboxes'], $job->params['create_domain']]);
    }

    public function testSnapshotsWithoutMailsRestoreNoMailData(): void
    {
        $content = gzencode(json_encode(['domains' => ['firma.de' => ['created' => 1500, 'mailboxes' => ['anna@firma.de' => 'Anna']]]]));
        $snapshot = fn(string $tags): Snapshot => Snapshot::fromRow(['id' => str_repeat('e', 64), 'short_id' => 'eeeeeeee', 'time' => 3000,
            'tags' => $tags, 'domains' => '{"firma.de": null}', 'content' => $content]);
        $this->snapshots = $this->createMock(SnapshotRepository::class);
        $this->snapshots->method('find')->willReturnOnConsecutiveCalls($snapshot('mailcow,instance:x,mode:config'),
            $snapshot('mailcow,instance:x,mode:config'), $snapshot('mailcow,instance:x,mode:data'));

        $this->expectUserError(['backup_components_no_mails', 'mails, imap_acl'],
            fn() => $this->service()->restore(self::restore(['snapshot' => 'eeeeeeee', 'components' => ['mails', 'imap_acl', 'aliases']])));
        $job = $this->service()->restore(self::restore(['snapshot' => 'eeeeeeee', 'components' => ['aliases', 'filters', 'password']]));
        $this->assertSame(['aliases', 'filters', 'password'], $job->params['components']);
        // data snapshots lack the mailcow directory only
        $this->assertNotNull($this->service()->restore(self::restore(['snapshot' => 'eeeeeeee', 'mailboxes' => ['bob@firma.de']])));
        $this->assertSame(['all', 'config', 'data'], [$this->snapshotList()[0]->mode(), $snapshot('mailcow,mode:config')->toArray()['mode'],
            $snapshot('mode:data')->mode()]);
    }

    public function testTheMailComponentsOfTheUiMatch(): void
    {
        $script = (string)file_get_contents(__DIR__ . '/../../../../data/web/js/site/backup.js');
        $this->assertSame(1, preg_match('/MAIL_COMPONENTS\s*=\s*\[([^\]]*)\]/', $script, $match), 'MAIL_COMPONENTS not found in backup.js');
        preg_match_all("/'(\w+)'/", $match[1], $items);

        $this->assertSame(JobService::MAIL_COMPONENTS, $items[1]);
    }

    public function testTheAclMapOfTheUiMatches(): void
    {
        $script = (string)file_get_contents(__DIR__ . '/../../../../data/web/js/site/backup.js');
        $this->assertSame(1, preg_match('/COMPONENT_ACL\s*=\s*\{([^}]*)\}/', $script, $match), 'COMPONENT_ACL not found in backup.js');
        preg_match_all("/(\w+)\s*:\s*'(\w+)'/", $match[1], $pairs, PREG_SET_ORDER);
        $ui = array_column($pairs, 2, 1);
        $php = (new \ReflectionClassConstant(JobService::class, 'COMPONENT_ACL'))->getValue();
        ksort($ui);
        ksort($php);

        $this->assertSame($php, $ui);
    }
}
