<?php

declare(strict_types=1);

namespace Mailcow\Tests\Backup\Service;

use Mailcow\Backup\Legacy\LegacyDomain;
use Mailcow\Backup\Model\Job;
use Mailcow\Backup\Repository\JobRepository;
use Mailcow\Backup\Service\DomainRestoreService;
use Mailcow\Backup\Service\GroupMembers;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Tests\Backup\Support\FakeRestoreRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DomainRestoreServiceTest extends TestCase
{
    private FakeRestoreRepository $repo;
    private JobRepository&MockObject $jobs;
    private GroupMembers&MockObject $groups;
    private LegacyDomain&MockObject $legacy;
    private Job $job;
    /** @var array Redis of the legacy code: dkim, ratelimit, domain_map */
    private array $redis = ['dkim' => [], 'ratelimit' => [], 'domain_map' => []];
    private int $sogoRestarts = 0;
    /** @var array[] groups created through the group module: [role, input] */
    private array $createdGroups = [];

    protected function setUp(): void
    {
        $this->repo = new FakeRestoreRepository([
            'domain' => [['domain' => 'firma.de', 'aliases' => 400, 'mailboxes' => 10, 'quota' => 10240, 'maxquota' => 2048, 'active' => 1]],
            'relayhosts' => [['id' => 1, 'hostname' => 'relay.example.org']],
            'admin' => [['username' => 'da', 'superadmin' => 0, 'active' => 1], ['username' => 'admin', 'superadmin' => 1, 'active' => 1]],
            'sender_acl' => [['id' => 1, 'logged_in_as' => 'bob@firma.de', 'send_as' => 'info@alt.de', 'external' => 1, 'group_id' => null]],
        ]);
        $this->job = new Job('restore');
        $this->job->id = 20;
        $this->job->state = 'running';
        $this->job->domain = 'alt.de';
        $this->job->params = ['scope' => 'domain', 'domain' => 'alt.de', 'mailboxes' => ['x@alt.de'], 'components' => ['mails', 'aliases'],
            'requested_role' => 'admin', 'create_domain' => 1, 'create_mailboxes' => ['x@alt.de']];
        $this->jobs = $this->createMock(JobRepository::class);
        $this->jobs->method('find')->willReturnCallback(fn(int $id): ?Job => ($id === 20) ? $this->job : null);
        $this->groups = $this->createMock(GroupMembers::class);
        $this->groups->method('create')->willReturnCallback(function (Context $context, array $input): int {
            if ($input['name'] === 'Broken') {
                throw new UserError('group_address_invalid', ['x']);
            }
            $this->createdGroups[] = [$context->role, $input];
            $id = 100 + count($this->createdGroups);
            $this->repo->tables['mailbox_group'][] = ['id' => $id, 'domain' => $input['domain'], 'name' => $input['name'], 'address' => $input['address'] ?? null];
            return $id;
        });
        $this->legacy = $this->createMock(LegacyDomain::class);
        $this->legacy->method('dkim')->willReturnCallback(fn(string $domain): ?array => $this->redis['dkim'][$domain] ?? null);
        $this->legacy->method('addDkim')->willReturnCallback(function (string $domain, string $selector, string $pubkey, string $privkey): void {
            $this->redis['dkim'][$domain] = ['selector' => $selector, 'pubkey' => $pubkey, 'privkey' => $privkey];
        });
        $this->legacy->method('rateLimits')->willReturnCallback(fn(array $objects): array => array_intersect_key($this->redis['ratelimit'], array_flip($objects)));
        $this->legacy->method('setRateLimit')->willReturnCallback(function (string $object, string $value): void {
            $this->redis['ratelimit'][$object] = $value;
        });
        $this->legacy->method('addDomainMap')->willReturnCallback(function (string $domain): void {
            $this->redis['domain_map'][] = $domain;
        });
        $this->legacy->method('restartSogo')->willReturnCallback(function (): ?string {
            $this->sogoRestarts++;
            return null;
        });
    }

    private function service(): DomainRestoreService
    {
        return new DomainRestoreService($this->repo, $this->jobs, $this->groups, $this->legacy);
    }

    private static function data(): array
    {
        return [
            'domain' => ['domain' => 'alt.de', 'description' => 'Alt GmbH', 'aliases' => 50, 'mailboxes' => 5, 'defquota' => 1024, 'maxquota' => 2048,
                'quota' => 4096, 'relayhost' => '9', 'backupmx' => 0, 'gal' => 1, 'created' => '2020-01-01 00:00:00', 'active' => 1, 'gone_column' => 1],
            'tags' => ['kunde', 'kunde', ''],
            'alias_domains' => [['alias_domain' => 'alt.com', 'target_domain' => 'alt.de', 'active' => 1],
                ['alias_domain' => 'firma.de', 'target_domain' => 'alt.de', 'active' => 1]],
            'dkim' => [['domain' => 'alt.de', 'selector' => 'dkim', 'pubkey' => 'PUB', 'privkey' => 'PRIV'],
                ['domain' => 'alt.com', 'selector' => 's2', 'pubkey' => 'PUB2', 'privkey' => 'PRIV2'],
                ['domain' => 'other.de', 'selector' => 'dkim', 'pubkey' => 'X', 'privkey' => 'X']],
            'ratelimit' => '100 / 1h',
            'filterconf' => [['prefid' => 1, 'object' => 'alt.de', 'option' => 'whitelist_from', 'value' => '*@partner.example'],
                ['prefid' => 2, 'object' => 'alt.de', 'option' => 'highspamlevel', 'value' => '12']],
            'bcc_maps' => [['id' => 3, 'local_dest' => '@alt.de', 'bcc_dest' => 'archive@example.org', 'domain' => 'alt.de', 'type' => 'rcpt', 'active' => 1]],
            'footer' => ['domain' => 'alt.de', 'html' => '<p>Alt</p>', 'plain' => 'Alt', 'mbox_exclude' => '[]', 'alias_domain_exclude' => '[]', 'skip_replies' => 0],
            'mta_sts' => ['id' => 1700000000, 'domain' => 'alt.de', 'version' => 'STSv1', 'mode' => 'enforce', 'mx' => 'mail.alt.de', 'max_age' => '86400', 'active' => 1],
            'domain_admins' => [['id' => 4, 'username' => 'da', 'domain' => 'alt.de', 'active' => 1], ['id' => 5, 'username' => 'gone', 'domain' => 'alt.de', 'active' => 1]],
            'groups' => [['id' => 7, 'domain' => 'alt.de', 'name' => 'Team', 'description' => 'Alle', 'address' => 'team@alt.de', 'sender_allowed' => 1,
                'sogo_visible' => 1, 'internal' => 0, 'active' => 1, 'priority' => 5, 'idp_ref' => null, 'template_id' => 3],
                ['id' => 8, 'domain' => 'alt.de', 'name' => 'Broken', 'description' => '', 'address' => null]],
            'mail_policies' => [['id' => 3, 'domain' => 'alt.de', 'name' => 'Strict', 'send_mode' => 'list', 'send_allow' => '["group:7", "group:8", "x@alt.de"]',
                'send_block' => '[]', 'receive_mode' => 'all', 'receive_allow' => '[]', 'receive_block' => '[]', 'group_members' => ['Team', 'Gone']]],
            'retention_rules' => [['id' => 9, 'domain' => 'alt.de', 'name' => 'Papierkorb', 'folders' => '["trash"]', 'days' => 30, 'group_members' => []]],
        ];
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

    public function testADeletedDomainIsCreatedWithItsSettings(): void
    {
        $result = $this->service()->restore(20, self::data())['domain'];

        $domain = $this->repo->rows('domain', ['domain' => 'alt.de'])[0];
        // the row of the backup with its creation time; a relayhost that is gone is not used
        $this->assertSame(['Alt GmbH', 5, 4096, '0', '2020-01-01 00:00:00'], [$domain['description'], $domain['mailboxes'], $domain['quota'], $domain['relayhost'],
            $domain['created']]);
        $this->assertSame(['kunde'], array_column($this->repo->tables['tags_domain'], 'tag_name'));
        // effects of the legacy domain add: domain map, SOGo restart, external send-as rights to the domain dropped
        $this->assertSame(['alt.de', 'alt.com'], $this->redis['domain_map']);
        $this->assertSame(1, $this->sogoRestarts);
        $this->assertSame([], $this->repo->tables['sender_acl']);
        // alias domains that are free; DKIM keys of the domain and its alias domains
        $this->assertSame([['alt.com', 'alt.de']], array_map(fn(array $a): array => [$a['alias_domain'], $a['target_domain']], $this->repo->tables['alias_domain']));
        $this->assertSame(['alt.de', 'alt.com'], array_keys($this->redis['dkim']));
        $this->assertSame('PRIV2', $this->redis['dkim']['alt.com']['privkey']);
        $this->assertSame(['alt.de' => '100 / 1h'], $this->redis['ratelimit']);
        $this->assertSame([['whitelist_from', '*@partner.example'], ['highspamlevel', '12']],
            array_map(fn(array $f): array => [$f['option'], $f['value']], $this->repo->tables['filterconf']));
        $this->assertSame(['@alt.de', 'archive@example.org'], [$this->repo->tables['bcc_maps'][0]['local_dest'], $this->repo->tables['bcc_maps'][0]['bcc_dest']]);
        $this->assertSame(['<p>Alt</p>', 'enforce'], [$this->repo->tables['domain_wide_footer'][0]['html'], $this->repo->tables['mta_sts'][0]['mode']]);
        $this->assertSame([['da', 'alt.de']], array_map(fn(array $a): array => [$a['username'], $a['domain']], $this->repo->tables['domain_admins']));
        // groups through the group module (no template), then policies and rules with the groups' new ids
        $this->assertSame([['admin', ['domain' => 'alt.de', 'name' => 'Team', 'description' => 'Alle', 'sender_allowed' => 1, 'sogo_visible' => 1,
            'internal' => 0, 'active' => 1, 'priority' => 5, 'address' => 'team@alt.de']]], $this->createdGroups);
        $policy = $this->repo->rows('mail_policy', ['name' => 'Strict'])[0];
        $this->assertSame(['["group:101","x@alt.de"]', 'list'], [$policy['send_allow'], $policy['send_mode']]);
        $this->assertSame([[$policy['id'], 101]], array_map(fn(array $m): array => [$m['policy_id'], $m['group_id']], $this->repo->tables['mail_policy_member']));
        $this->assertSame(['Papierkorb', '["trash"]'], [$this->repo->tables['retention_rule'][0]['name'], $this->repo->tables['retention_rule'][0]['folders']]);
        $this->assertSame(1, $result['created']);
        $this->assertSame([
            'relayhost: does not exist',
            'domain admin gone: does not exist',
            'group Broken: group_address_invalid',
            'Strict (send_allow): group:8 dropped (group does not exist)',
            'Strict: group Gone does not exist',
        ], $result['errors']);
        // domain, alias domain, 2 filterconf, BCC, footer, MTA-STS, domain admin, 2 DKIM keys, rate limit, group, policy, rule
        $this->assertSame([14, 1], [$result['added'], $result['skipped']]);
    }

    public function testADomainCreatedMeanwhileGetsWhatIsMissing(): void
    {
        $this->repo->tables['domain'][] = ['domain' => 'alt.de', 'description' => 'Neu', 'aliases' => 10, 'mailboxes' => 1, 'quota' => 100, 'maxquota' => 100,
            'relayhost' => '0', 'active' => 1];
        $this->repo->tables['mailbox_group'][] = ['id' => 50, 'domain' => 'alt.de', 'name' => 'Team', 'address' => null];
        $this->redis['dkim']['alt.de'] = ['selector' => 'new', 'pubkey' => 'NEW', 'privkey' => 'NEW'];

        $result = $this->service()->restore(20, self::data())['domain'];

        $this->assertSame([0, 'Neu'], [$result['created'], $this->repo->rows('domain', ['domain' => 'alt.de'])[0]['description']]);
        $this->assertSame([0, 'NEW'], [$this->sogoRestarts, $this->redis['dkim']['alt.de']['pubkey']]);
        // the existing group gets the policy's assignments
        $this->assertSame([], $this->createdGroups);
        $this->assertSame([50], array_column($this->repo->tables['mail_policy_member'], 'group_id'));
        $again = $this->service()->restore(20, self::data())['domain'];
        $this->assertSame(0, $again['added']);
    }

    public function testOnlyRunningDomainRestoresOfAdmins(): void
    {
        $this->repo->tables['alias_domain'][] = ['alias_domain' => 'alt.de', 'target_domain' => 'firma.de', 'active' => 1];
        $this->assertSame(['alt.de is an alias domain now'], $this->service()->restore(20, self::data())['domain']['errors']);
        $this->assertSame([], $this->repo->rows('domain', ['domain' => 'alt.de']));

        $this->expectUserError('backup_export_invalid', fn() => $this->service()->restore(20, ['domain' => ['domain' => 'other.de']]));
        $this->expectUserError('backup_job_not_running', fn() => $this->service()->restore(21, self::data()));
        $this->job->params['create_domain'] = 0;
        $this->expectUserError('access_denied', fn() => $this->service()->restore(20, self::data()));
        $this->job->params['create_domain'] = 1;
        $this->job->params['requested_role'] = 'domainadmin';
        $this->expectUserError('access_denied', fn() => $this->service()->restore(20, self::data()));
        $this->job->params['requested_role'] = 'admin';
        $this->job->state = 'done';
        $this->expectUserError('backup_job_not_running', fn() => $this->service()->restore(20, self::data()));
        $this->job->state = 'running';
        $this->repo->busyLocks = ['20:domain'];
        $this->expectUserError('backup_job_busy', fn() => $this->service()->restore(20, self::data()));
    }
}
