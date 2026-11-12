<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Modules;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes syncjob, syncjobs, syncjob_source, syncjob_options and imapsync_settings: handed to the Imapsync module */
class SyncJobRoutesTest extends ApiTestCase
{
    private const API = 'Mailcow\Imapsync\ImapsyncModule->api->';

    public function testAddJob(): void
    {
        $attr = ['username' => 'alice@example.org', 'source_id' => 3, 'user1' => 'alice@example.com', 'password1' => 'secret', 'mins_interval' => 20];
        $messages = [self::message('success', ['mailbox_modified', 'alice@example.org'], ['syncjob', 'add', 'job', ['password1' => '*']])];

        $result = $this->post('add/syncjob', $attr, ['script' => [self::answer(self::API . 'addJob', [], true, $messages)]]);

        $this->assertCalled($result, self::API . 'addJob', [$attr]);
        $this->assertSame($messages, $result->json());
    }

    public function testAddSource(): void
    {
        $attr = ['name' => 'Old provider', 'host' => 'imap.example.com', 'port' => 993, 'enc' => 'SSL', 'scope' => 'domain', 'domains' => ['example.org']];

        $result = $this->post('add/syncjob_source', $attr);

        $this->assertCalled($result, self::API . 'addSource', [$attr]);
    }

    public function testAllJobsOfAnApiKey(): void
    {
        $jobs = [['id' => 12, 'user2' => 'alice@example.org', 'log' => 'done']];

        $result = $this->get('get/syncjobs/all', ['script' => [self::answer(self::API . 'getAllJobs', [], $jobs)]]);

        $this->assertCalled($result, self::API . 'getAllJobs', [null, []]);
        $this->assertNotCalled($result, 'mailbox');
        $this->assertSame($jobs, $result->json());
    }

    public function testAllJobsWithoutLog(): void
    {
        $result = $this->get('get/syncjobs/all/no_log');

        $this->assertCalled($result, self::API . 'getAllJobs', ['no_log', []]);
        $this->assertSame('{}', $result->body);
    }

    public function testAllJobsOfADomainAdminAreTheJobsOfHisMailboxes(): void
    {
        $result = $this->get('get/syncjobs/all/no_log', ['session' => self::DOMAIN_ADMIN, 'script' => [
            self::answer('mailbox', ['get', 'domains'], ['example.org', 'example.com']),
            self::answer('mailbox', ['get', 'mailboxes', 'example.org'], ['alice@example.org', 'bob@example.org']),
            self::answer('mailbox', ['get', 'mailboxes', 'example.com'], false),
        ]]);

        $this->assertCalled($result, self::API . 'getAllJobs', ['no_log', ['alice@example.org', 'bob@example.org']]);
    }

    public function testAllJobsOfAUser(): void
    {
        $result = $this->get('get/syncjobs/all', ['session' => self::USER, 'script' => [self::answer('mailbox', ['get', 'domains'], false)]]);

        $this->assertCalled($result, self::API . 'getAllJobs', [null, []]);
    }

    public function testJobsOfAMailbox(): void
    {
        $withLog = $this->get('get/syncjobs/alice@example.org');
        $this->assertCalled($withLog, self::API . 'getJobs', ['alice@example.org', null]);
        $this->assertNotCalled($withLog, self::API . 'getAllJobs');

        $withoutLog = $this->get('get/syncjobs/alice@example.org/no_log');
        $this->assertCalled($withoutLog, self::API . 'getJobs', ['alice@example.org', 'no_log']);

        $own = $this->get('get/syncjobs');
        $this->assertCalled($own, self::API . 'getJobs', [null, null]);
    }

    public function testSources(): void
    {
        $sources = [['id' => 3, 'name' => 'Old provider', 'has_client_secret' => false]];

        $all = $this->get('get/syncjob_source/all', ['script' => [self::answer(self::API . 'getSources', [], $sources)]]);
        $this->assertCalled($all, self::API . 'getSources', []);
        $this->assertSame($sources, $all->json());

        $one = $this->get('get/syncjob_source/3', ['script' => [self::answer(self::API . 'getSource', ['3'], $sources[0])]]);
        $this->assertCalled($one, self::API . 'getSource', ['3']);
        $this->assertSame($sources[0], $one->json());

        $this->assertSame('{}', $this->get('get/syncjob_source/99')->body);
    }

    public function testOptions(): void
    {
        $options = ['folder' => true, 'syncinternaldates' => false, 'sslargs1' => '^SSL_verify_mode=[01]$'];

        $result = $this->get('get/syncjob_options', ['session' => self::USER, 'script' => [self::answer(self::API . 'getOptions', [], $options)]]);

        $this->assertCalled($result, self::API . 'getOptions', []);
        $this->assertSame($options, $result->json());
    }

    public function testSettings(): void
    {
        $settings = ['max_parallel' => 3, 'max_bytes_per_second' => 2048000, 'max_kb_per_second' => 2000];

        $result = $this->get('get/imapsync_settings', ['script' => [self::answer(self::API . 'getSettings', [], $settings)]]);
        $this->assertCalled($result, self::API . 'getSettings', []);
        $this->assertSame($settings, $result->json());

        $this->assertSame('{}', $this->get('get/imapsync_settings', ['session' => self::DOMAIN_ADMIN, 'script' => [self::answer(self::API . 'getSettings', [], [])]])->body);
    }

    public function testEditJobs(): void
    {
        $result = $this->post('edit/syncjob', ['items' => [12, '13'], 'attr' => ['active' => 0, 'mins_interval' => 60]]);

        $this->assertCalled($result, self::API . 'editJobs', [[12, '13'], ['active' => 0, 'mins_interval' => 60]]);
        $this->assertNotCalled($result, self::API . 'runNext');

        $other = $this->post('edit/syncjob/anything', ['items' => [12], 'attr' => ['active' => 1]]);
        $this->assertCalled($other, self::API . 'editJobs', [[12], ['active' => 1]]);
    }

    public function testRunNext(): void
    {
        $messages = [
            self::message('danger', ['imapsync_job_running', 13], ['syncjob', 'edit', 'run_next', ['id' => [12, 13]]]),
            self::message('success', ['imapsync_run_next_done', 1], ['syncjob', 'edit', 'run_next', ['id' => [12, 13]]]),
        ];

        $result = $this->post('edit/syncjob/run_next', ['items' => [12, 13], 'attr' => ['ignored' => 1]],
            ['script' => [self::answer(self::API . 'runNext', [], true, $messages)]]);

        $this->assertCalled($result, self::API . 'runNext', [[12, 13]]);
        $this->assertNotCalled($result, self::API . 'editJobs');
        $this->assertSame($messages, $result->json());
    }

    public function testRunNextWithoutMovedJobs(): void
    {
        $result = $this->post('edit/syncjob/run_next', ['items' => [99]], ['script' => [self::answer(self::API . 'runNext', [], false)]]);

        $this->assertSame(['type' => 'error', 'msg' => 'Cannot edit item'], $result->json());
    }

    public function testEditSettings(): void
    {
        $result = $this->post('edit/imapsync_settings', ['items' => ['ignored'], 'attr' => ['max_parallel' => 4, 'max_kb_per_second' => 2000]]);

        $this->assertCalled($result, self::API . 'editSettings', [['max_parallel' => 4, 'max_kb_per_second' => 2000]]);
    }

    public function testEditSources(): void
    {
        $attr = ['description' => 'Migration finished', 'scope' => 'user', 'users' => ['alice@example.org']];

        $result = $this->post('edit/syncjob_source', ['items' => [3], 'attr' => $attr]);

        $this->assertCalled($result, self::API . 'editSources', [[3], $attr]);
        $this->assertNotCalled($result, self::API . 'refreshSourceToken');
    }

    public function testRefreshToken(): void
    {
        $result = $this->post('edit/syncjob_source/refresh_token', ['items' => [5, 6], 'attr' => []]);

        $this->assertCalled($result, self::API . 'refreshSourceToken', [[5, 6]]);
        $this->assertNotCalled($result, self::API . 'editSources');
    }

    public function testDeleteJobs(): void
    {
        $result = $this->post('delete/syncjob', [12, '13']);

        $this->assertCalled($result, self::API . 'deleteJobs', [[12, '13']]);
    }

    public function testDeleteSources(): void
    {
        $messages = [self::message('danger', ['imapsync_source_in_use', 'Old provider'], ['syncjob', 'delete', 'source', ['id' => [3]]])];

        $result = $this->post('delete/syncjob_source', [3], ['script' => [self::answer(self::API . 'deleteSources', [], true, $messages)]]);

        $this->assertCalled($result, self::API . 'deleteSources', [[3]]);
        $this->assertSame($messages, $result->json());
    }
}
