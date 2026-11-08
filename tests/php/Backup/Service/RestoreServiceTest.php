<?php

declare(strict_types=1);

namespace Mailcow\Tests\Backup\Service;

use Mailcow\Backup\Legacy\LegacyDomain;
use Mailcow\Backup\Model\Job;
use Mailcow\Backup\Repository\JobRepository;
use Mailcow\Backup\Service\GroupMembers;
use Mailcow\Backup\Service\RestoreService;
use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Mailcow\Dovecot\Service\DovecotService;
use Mailcow\Sogo\Service\SogoService;
use Mailcow\Tests\Backup\Support\FakeRestoreRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RestoreServiceTest extends TestCase
{
    private const ALL = ['mails', 'sogo', 'aliases', 'groups', 'sender_acl', 'mail_policies', 'retention_rules', 'filters', 'sieve_activate',
        'spam', 'bcc', 'syncjobs', 'app_passwords', 'tfa', 'settings', 'password'];
    private const MIB = 1048576;

    private FakeRestoreRepository $repo;
    private JobRepository&MockObject $jobs;
    private GroupMembers&MockObject $groups;
    private LegacyDomain&MockObject $legacy;
    /** @var array<string, string> rate limits in Redis */
    private array $rateLimits = [];
    private SogoService&MockObject $sogo;
    private DovecotService&MockObject $dovecot;
    private ?Job $job = null;
    /** @var array[] memberships added through the group module: [role of the caller, group id, username] */
    private array $groupUpdates = [];
    private array $sogoUsers = [];
    private array $authUsers = [];

    protected function setUp(): void
    {
        $attributes = json_encode(['imap_access' => '1', 'pop3_access' => '1', 'relayhost' => '0', 'passwd_update' => '2026-01-01 10:00:00',
            'mailbox_format' => 'maildir:', 'tls_enforce_in' => '0']);
        $this->repo = new FakeRestoreRepository([
            'domain' => [
                ['domain' => 'firma.de', 'aliases' => 400, 'mailboxes' => 10, 'quota' => 10240, 'maxquota' => 2048, 'active' => 1],
                ['domain' => 'other.de', 'aliases' => 400, 'quota' => 10240, 'maxquota' => 10240, 'active' => 1],
            ],
            'alias_domain' => [['alias_domain' => 'firma.com', 'target_domain' => 'firma.de', 'active' => 1]],
            'mailbox' => [
                self::mailbox('anna@firma.de', ['attributes' => $attributes, 'quota' => 1024 * self::MIB]),
                self::mailbox('bob@firma.de', ['quota' => 1024 * self::MIB]),
                self::mailbox('room@firma.de', ['kind' => 'location']),
                self::mailbox('carl@other.de'),
            ],
            'alias' => [
                ['id' => 1, 'address' => 'anna@firma.de', 'goto' => 'anna@firma.de', 'domain' => 'firma.de', 'active' => 1],
                ['id' => 2, 'address' => 'bob@firma.de', 'goto' => 'bob@firma.de', 'domain' => 'firma.de', 'active' => 1],
                ['id' => 3, 'address' => 'info@firma.de', 'goto' => 'bob@firma.de', 'domain' => 'firma.de', 'active' => 1],
                ['id' => 4, 'address' => 'team@firma.de', 'goto' => 'bob@firma.de', 'domain' => 'firma.de', 'active' => 1, 'group_id' => 7],
            ],
            'mailbox_group' => [['id' => 7, 'domain' => 'firma.de', 'name' => 'Team'], ['id' => 9, 'domain' => 'firma.de', 'name' => 'Sales']],
            'mail_policy' => [['id' => 3, 'domain' => 'firma.de', 'name' => 'Strict']],
            'retention_rule' => [['id' => 5, 'domain' => 'firma.de', 'name' => 'Papierkorb']],
            'imapsync_source' => [
                ['id' => 1, 'host1' => 'imap.old.de', 'port1' => 993, 'scope' => 'all'],
                ['id' => 2, 'host1' => 'imap.legacy.de', 'port1' => 143, 'scope' => 'domain'],
            ],
            'imapsync_source_domain' => [['source_id' => 2, 'domain' => 'firma.de']],
            'relayhosts' => [['id' => 1, 'hostname' => 'relay.example.org']],
            'user_acl' => [['username' => 'anna@firma.de', 'spam_alias' => 1, 'syncjobs' => 0, 'pushover' => 1]],
        ]);
        $this->jobs = $this->createMock(JobRepository::class);
        $this->jobs->method('find')->willReturnCallback(fn(int $id): ?Job => ($this->job !== null && $this->job->id === $id) ? $this->job : null);
        $this->groups = $this->createMock(GroupMembers::class);
        $this->groups->method('add')->willReturnCallback(function (Context $context, int $id, string $username): void {
            $this->groupUpdates[] = [$context->role, $id, $username];
        });
        $this->legacy = $this->createMock(LegacyDomain::class);
        $this->legacy->method('rateLimits')->willReturnCallback(fn(array $objects): array => array_intersect_key($this->rateLimits, array_flip($objects)));
        $this->legacy->method('setRateLimit')->willReturnCallback(function (string $object, string $value): void {
            $this->rateLimits[$object] = $value;
        });
        $this->sogo = $this->createMock(SogoService::class);
        $this->sogo->method('refreshStaticView')->willReturnCallback(function (array $users): void {
            $this->sogoUsers = $users;
        });
        $this->dovecot = $this->createMock(DovecotService::class);
        $this->dovecot->method('flushAuthCache')->willReturnCallback(function (array $users): void {
            $this->authUsers = $users;
        });
        $this->job(self::ALL);
    }

    private static function mailbox(string $username, array $values = []): array
    {
        [$local, $domain] = explode('@', $username);
        return $values + ['username' => $username, 'password' => 'HASH-NOW', 'name' => $local, 'quota' => 0, 'local_part' => $local,
            'domain' => $domain, 'attributes' => '{}', 'custom_attributes' => '{}', 'kind' => '', 'authsource' => 'mailcow', 'active' => 1];
    }

    private function job(array $components, string $role = 'admin', array $acl = [], string $state = 'running', array $params = []): void
    {
        $this->job = new Job('restore');
        $this->job->id = 12;
        $this->job->state = $state;
        $this->job->domain = 'firma.de';
        $this->job->params = $params + ['snapshot' => 'abc', 'scope' => 'mailbox', 'domain' => 'firma.de', 'mailboxes' => ['anna@firma.de'],
            'components' => $components, 'mail_target' => 'folder', 'requested_role' => $role] + ($role === 'admin' ? [] : ['acl' => $acl]);
    }

    /** A domain admin account of firma.de with these da_acl flags (all others 0) */
    private function domainAdmin(string $username, array $acl): void
    {
        $this->repo->tables['admin'][] = ['username' => $username, 'superadmin' => 0, 'active' => 1];
        $this->repo->tables['domain_admins'][] = ['id' => 1, 'username' => $username, 'domain' => 'firma.de', 'active' => 1];
        $this->repo->tables['da_acl'][] = ['username' => $username] + $acl + array_fill_keys(array_diff($this->repo->columns('da_acl'), ['username']), 0);
    }

    private function service(bool $externalDisabled = false): RestoreService
    {
        return new RestoreService($this->repo, $this->jobs, $this->groups, $this->sogo, $this->dovecot, $this->legacy, $externalDisabled);
    }

    /** The mailbox section of anna's export, as the runner sends it */
    private static function backup(array $override = []): array
    {
        $attributes = json_encode(['imap_access' => '0', 'pop3_access' => '1', 'relayhost' => '1', 'passwd_update' => '2025-05-05 05:05:05',
            'mailbox_format' => 'sdbox:', 'tls_enforce_in' => '1']);
        return $override + [
            'mailbox' => self::mailbox('anna@firma.de', ['password' => 'HASH-OLD', 'quota' => 2048 * self::MIB, 'attributes' => $attributes,
                'custom_attributes' => '{"dept": "IT"}']) + ['tags' => ['vip', 'it']],
            'user_acl' => ['username' => 'anna@firma.de', 'spam_alias' => 0, 'syncjobs' => 1, 'pushover' => 1, 'gone_column' => 1],
            'pushover' => ['username' => 'anna@firma.de', 'key' => 'K', 'token' => 'T', 'attributes' => '{}', 'title' => 'Mail', 'active' => 1],
            'ratelimit' => '20 / 1h',
            'aliases' => [
                ['id' => 30, 'address' => 'info@firma.de', 'goto' => 'bob@firma.de,anna@firma.de', 'domain' => 'firma.de', 'active' => 1],
                ['id' => 31, 'address' => 'sales@firma.com', 'goto' => 'anna@firma.de,gone@firma.de,partner@example.org,room@firma.de',
                    'domain' => 'firma.com', 'active' => 1, 'public_comment' => 'Vertrieb', 'old_column' => 'x'],
                ['id' => 32, 'address' => 'team@firma.de', 'goto' => 'anna@firma.de,bob@firma.de', 'domain' => 'firma.de', 'group_id' => 7],
                ['id' => 33, 'address' => 'help@other.de', 'goto' => 'anna@firma.de', 'domain' => 'other.de', 'active' => 1],
            ],
            'sender_acl' => [
                ['id' => 1, 'logged_in_as' => 'anna@firma.de', 'send_as' => 'info@firma.de', 'external' => 0, 'group_id' => null],
                ['id' => 2, 'logged_in_as' => 'anna@firma.de', 'send_as' => '@firma.com', 'external' => 0, 'group_id' => null],
                ['id' => 3, 'logged_in_as' => 'anna@firma.de', 'send_as' => 'gone@firma.de', 'external' => 0, 'group_id' => null],
                ['id' => 4, 'logged_in_as' => 'anna@firma.de', 'send_as' => 'team@firma.de', 'external' => 0, 'group_id' => 7],
                ['id' => 5, 'logged_in_as' => 'anna@firma.de', 'send_as' => 'anna@private.example', 'external' => 1, 'group_id' => null],
            ],
            'sender_acl_granted' => [
                ['id' => 6, 'logged_in_as' => 'bob@firma.de', 'send_as' => 'anna@firma.de', 'external' => 0, 'group_id' => null],
                ['id' => 7, 'logged_in_as' => 'ghost@firma.de', 'send_as' => 'anna@firma.de', 'external' => 0, 'group_id' => null],
            ],
            'groups' => [
                ['id' => 7, 'name' => 'Team', 'domain' => 'firma.de', 'source' => 'manual'],
                ['id' => 99, 'name' => 'Sales', 'domain' => 'firma.de', 'source' => 'manual'],
                ['id' => 98, 'name' => 'Gone', 'domain' => 'firma.de', 'source' => 'manual'],
                ['id' => 97, 'name' => 'Synced', 'domain' => 'firma.de', 'source' => 'idp'],
            ],
            'mail_policies' => [['id' => 3, 'name' => 'Strict', 'domain' => 'firma.de']],
            'retention_rules' => [['id' => 77, 'name' => 'Papierkorb', 'domain' => 'firma.de'], ['id' => 78, 'name' => 'Weg', 'domain' => 'firma.de']],
            'sieve_filters' => [
                ['id' => 4, 'username' => 'anna@firma.de', 'script_desc' => 'Spam', 'script_name' => 'active', 'script_data' => 'discard;', 'filter_type' => 'prefilter'],
                ['id' => 5, 'username' => 'anna@firma.de', 'script_desc' => 'Old', 'script_name' => 'inactive', 'script_data' => 'keep;', 'filter_type' => 'postfilter'],
            ],
            'filterconf' => [
                ['prefid' => 1, 'object' => 'anna@firma.de', 'option' => 'whitelist_from', 'value' => 'boss@example.org'],
                ['prefid' => 2, 'object' => 'anna@firma.de', 'option' => 'highspamlevel', 'value' => '12'],
            ],
            'spamalias' => [
                ['address' => 'abc123@firma.de', 'goto' => 'anna@firma.de', 'description' => 'Shop', 'validity' => time() + 3600, 'permanent' => 0],
                ['address' => 'old999@firma.de', 'goto' => 'anna@firma.de', 'description' => 'Old', 'validity' => time() - 3600, 'permanent' => 0],
                ['address' => 'bob@firma.de', 'goto' => 'anna@firma.de', 'description' => 'Taken', 'validity' => 0, 'permanent' => 1],
                ['address' => 'xyz@other.de', 'goto' => 'anna@firma.de', 'description' => 'Foreign', 'validity' => 0, 'permanent' => 1],
            ],
            'bcc_maps' => [['id' => 1, 'local_dest' => 'anna@firma.de', 'bcc_dest' => 'archive@example.org', 'domain' => 'firma.de', 'type' => 'rcpt', 'active' => 1]],
            'imapsync' => [
                ['id' => 40, 'user2' => 'anna@firma.de', 'source_id' => 1, 'user1' => 'anna@old.de', 'password1' => 'pw', 'maxage' => 30,
                    'source_host1' => 'imap.old.de', 'source_port1' => 993, 'is_running' => 1, 'last_run' => '2026-01-01 00:00:00', 'prio' => 9, 'active' => 1],
                ['id' => 41, 'user2' => 'anna@firma.de', 'source_id' => 55, 'user1' => 'anna@legacy.de', 'password1' => 'pw',
                    'source_host1' => 'imap.legacy.de', 'source_port1' => 143, 'active' => 1],
                ['id' => 42, 'user2' => 'anna@firma.de', 'source_id' => 56, 'user1' => 'anna@gone.de', 'password1' => 'pw',
                    'source_host1' => 'imap.gone.de', 'source_port1' => 993, 'active' => 1],
            ],
            'app_passwd' => [['id' => 8, 'name' => 'Phone', 'mailbox' => 'anna@firma.de', 'domain' => 'firma.de', 'password' => 'APPHASH', 'imap_access' => 1, 'active' => 1]],
            'tfa' => [['id' => 6, 'key_id' => 'Phone', 'username' => 'anna@firma.de', 'authmech' => 'totp', 'secret' => 'SECRET', 'active' => 1]],
            'fido2' => [['username' => 'anna@firma.de', 'friendlyName' => 'Key', 'rpId' => 'mail.firma.de', 'credentialPublicKey' => 'pk',
                'credentialId' => ['base64' => base64_encode("\x00\xff\x10")], 'AAGUID' => ['base64' => base64_encode("\xfe\x01")], 'active' => 1]],
        ];
    }

    private function merge(array $data = [], string $username = 'anna@firma.de'): array
    {
        return $this->service()->merge(12, $username, $data ?: self::backup());
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

    private function alias(string $address): ?array
    {
        return $this->repo->rows('alias', ['address' => $address])[0] ?? null;
    }

    public function testMissingThingsAreAddedAndNothingIsDeleted(): void
    {
        $this->repo->tables['sieve_filters'][] = ['id' => 1, 'username' => 'anna@firma.de', 'script_desc' => 'Mine', 'script_name' => 'active',
            'script_data' => 'stop;', 'filter_type' => 'prefilter'];
        $this->repo->tables['filterconf'][] = ['prefid' => 9, 'object' => 'anna@firma.de', 'option' => 'highspamlevel', 'value' => '20'];

        $result = $this->merge();

        $this->assertSame(['aliases', 'sender_acl', 'mail_policies', 'retention_rules', 'filters', 'spam', 'bcc', 'syncjobs', 'app_passwords', 'tfa',
            'settings', 'password', 'groups'], array_keys($result));
        // aliases: anna back into info@, sales@ created with the gotos that exist now, group address and foreign domain left alone
        $this->assertSame('bob@firma.de,anna@firma.de', $this->alias('info@firma.de')['goto']);
        $sales = $this->alias('sales@firma.com');
        $this->assertSame(['anna@firma.de,partner@example.org', 'firma.com', 'Vertrieb', null], [$sales['goto'], $sales['domain'], $sales['public_comment'], $sales['group_id']]);
        $this->assertSame('bob@firma.de', $this->alias('team@firma.de')['goto']);
        $this->assertSame('anna@firma.de', $this->alias('help@other.de')['goto']);
        $this->assertSame(['added' => 3, 'skipped' => 1], array_slice($result['aliases'], 0, 2));
        $this->assertSame(['sales@firma.com: goto gone@firma.de dropped (no mailbox or alias)', 'sales@firma.com: goto room@firma.de dropped (no mailbox or alias)'],
            $result['aliases']['errors']);
        // sender_acl: both directions, only for what exists, group rows never
        $this->assertEqualsCanonicalizing([['anna@firma.de', 'info@firma.de', 0], ['anna@firma.de', '@firma.com', 0], ['anna@firma.de', 'anna@private.example', 1],
            ['bob@firma.de', 'anna@firma.de', 0]], array_map(fn(array $r): array => [$r['logged_in_as'], $r['send_as'], $r['external']], $this->repo->tables['sender_acl']));
        $this->assertSame(['gone@firma.de: address does not exist', 'ghost@firma.de: mailbox does not exist'], $result['sender_acl']['errors']);
        // policies by id, else by name
        $this->assertSame([3], array_column($this->repo->tables['mail_policy_member'], 'policy_id'));
        $this->assertSame([5], array_column($this->repo->tables['retention_rule_member'], 'rule_id'));
        $this->assertSame(['added' => 1, 'skipped' => 0, 'errors' => []], $result['mail_policies']);
        $this->assertSame(['Weg: does not exist'], $result['retention_rules']['errors']);
        // filters: the mailbox has an active prefilter, so the restored one stays inactive
        $this->assertSame([['Mine', 'active'], ['Spam', 'inactive'], ['Old', 'inactive']],
            array_map(fn(array $f): array => [$f['script_desc'], $f['script_name']], $this->repo->tables['sieve_filters']));
        // spam: the existing level stays, the list entry and the valid free spam alias are added
        $this->assertSame([['highspamlevel', '20'], ['whitelist_from', 'boss@example.org']],
            array_map(fn(array $f): array => [$f['option'], $f['value']], $this->repo->tables['filterconf']));
        $this->assertSame(['abc123@firma.de'], array_column($this->repo->tables['spamalias'], 'address'));
        // spam aliases only in the mailbox's domain and its alias domains, like the legacy form
        $this->assertSame(['added' => 2, 'skipped' => 2, 'errors' => ['bob@firma.de: address is in use (mailbox)', 'xyz@other.de: not a domain of the mailbox']],
            $result['spam']);
        $this->assertSame(['archive@example.org'], array_column($this->repo->tables['bcc_maps'], 'bcc_dest'));
        // sync jobs: inactive, source by id and host, else another usable source with the same host
        $this->assertSame([['anna@old.de', 1, 0, null, null, 0], ['anna@legacy.de', 2, 0, null, null, 0]],
            array_map(fn(array $j): array => [$j['user1'], $j['source_id'], $j['active'], $j['last_run'], $j['prio'], $j['is_running'] ?? 0], $this->repo->tables['imapsync']));
        $this->assertSame(['anna@gone.de (imap.gone.de:993): sync source does not exist'], $result['syncjobs']['errors']);
        $this->assertSame([['Phone', 'APPHASH', 'firma.de']], array_map(fn(array $a): array => [$a['name'], $a['password'], $a['domain']], $this->repo->tables['app_passwd']));
        $this->assertSame('SECRET', $this->repo->tables['tfa'][0]['secret']);
        $this->assertSame(["\x00\xff\x10", "\xfe\x01"], [$this->repo->tables['fido2'][0]['credentialId'], $this->repo->tables['fido2'][0]['AAGUID']]);
        $this->assertSame(['added' => 2, 'skipped' => 0, 'errors' => []], $result['tfa']);
        // groups through the group module, by id or name; IdP memberships are the IdP sync's
        $this->assertSame([['admin', 7, 'anna@firma.de'], ['admin', 9, 'anna@firma.de']], $this->groupUpdates);
        $this->assertSame(['added' => 2, 'skipped' => 1, 'errors' => ['Gone: does not exist']], $result['groups']);
        // caches of the touched mailboxes
        $this->assertEqualsCanonicalizing(['anna@firma.de', 'bob@firma.de'], $this->sogoUsers);
        $this->assertSame(['anna@firma.de'], $this->authUsers);
    }

    public function testSettingsAndPasswordOverwrite(): void
    {
        $this->job(['settings', 'password']);
        $this->repo->tables['tags_mailbox'][] = ['tag_name' => 'vip', 'username' => 'anna@firma.de'];

        $result = $this->merge();

        $anna = $this->repo->rows('mailbox', ['username' => 'anna@firma.de'])[0];
        $attributes = json_decode($anna['attributes'], true);
        // overwritten except storage format; the password component sets passwd_update of the backup
        $this->assertSame(['imap_access' => '0', 'pop3_access' => '1', 'relayhost' => '1', 'passwd_update' => '2025-05-05 05:05:05',
            'mailbox_format' => 'maildir:', 'tls_enforce_in' => '1'], $attributes);
        $this->assertSame([2048 * self::MIB, 'HASH-OLD', ['dept' => 'IT']], [$anna['quota'], $anna['password'], json_decode($anna['custom_attributes'], true)]);
        $this->assertSame(['vip', 'it'], array_column($this->repo->tables['tags_mailbox'], 'tag_name'));
        $this->assertSame(['username' => 'anna@firma.de', 'spam_alias' => 0, 'syncjobs' => 1, 'pushover' => 1], $this->repo->tables['user_acl'][0]);
        $this->assertSame('Mail', $this->repo->tables['pushover'][0]['title']);
        $this->assertSame(['anna@firma.de' => '20 / 1h'], $this->rateLimits);
        // quota, 3 attributes, custom attributes, a tag, 2 user ACL values, Pushover, rate limit
        $this->assertSame(['added' => 10, 'skipped' => 3, 'errors' => []], $result['settings']);
        $this->assertSame(['added' => 1, 'skipped' => 0, 'errors' => []], $result['password']);
        $this->assertSame(['anna@firma.de'], $this->authUsers);
    }

    public function testQuotaStaysWithinTheDomainLimits(): void
    {
        $this->job(['settings']);
        $tooBig = self::backup();
        $tooBig['mailbox']['quota'] = 4096 * self::MIB;
        $this->assertSame(['quota: exceeds the maximum quota of a mailbox in the domain'], $this->merge($tooBig)['settings']['errors']);

        $this->repo->update('mailbox', ['username' => 'bob@firma.de'], ['quota' => 9000 * self::MIB]);
        $this->assertSame(['quota: exceeds the quota left in the domain'], $this->merge()['settings']['errors']);
        $this->assertSame(1024 * self::MIB, $this->repo->rows('mailbox', ['username' => 'anna@firma.de'])[0]['quota']);
    }

    public function testDomainAdminRestoresKeepToHisAcl(): void
    {
        $this->job(['aliases', 'sender_acl', 'settings', 'mail_policies', 'retention_rules', 'groups'], 'domainadmin', ['alias_external_goto' => 0,
            'extend_sender_acl' => 0, 'protocol_access' => 0, 'mailbox_relayhost' => 1, 'unlimited_quota' => 0, 'pushover' => 0, 'mail_policies' => 1,
            'retention' => 0, 'ratelimit' => 0, 'tls_policy' => 0, 'mailbox_groups' => 1]);
        $this->job->requestedBy = 'da@firma.de';
        $this->domainAdmin('da@firma.de', ['mailbox_relayhost' => 1, 'mail_policies' => 1, 'mailbox_groups' => 1]);
        $this->groups = $this->createMock(GroupMembers::class);
        $this->groups->method('add')->willReturnCallback(function (Context $context, int $id): void {
            $this->groupUpdates[] = [$context->role, $context->username, $context->hasAcl('mailbox_groups')];
            // the group module refuses groups that are read-only for him
            if ($id === 9) {
                throw new AccessDenied();
            }
        });
        $data = self::backup();
        $data['mailbox']['quota'] = 0;
        $data['sender_acl'][] = ['id' => 9, 'logged_in_as' => 'anna@firma.de', 'send_as' => '*', 'external' => 0, 'group_id' => null];
        $data['sender_acl'][] = ['id' => 10, 'logged_in_as' => 'anna@firma.de', 'send_as' => 'carl@other.de', 'external' => 0, 'group_id' => null];

        $result = $this->merge($data);

        // the alias of another domain is not his; external gotos are not allowed for him
        $this->assertSame('anna@firma.de', $this->alias('sales@firma.com')['goto']);
        $this->assertContains('help@other.de: outside the domain of the restore', $result['aliases']['errors']);
        $this->assertContains('sales@firma.com: goto partner@example.org dropped (external gotos are not allowed)', $result['aliases']['errors']);
        $this->assertSame(['gone@firma.de: address does not exist', 'anna@private.example: not allowed', '*: only admins grant *',
            'carl@other.de: outside the domain of the restore', 'ghost@firma.de: mailbox does not exist'], $result['sender_acl']['errors']);
        // user ACLs he does not have himself keep their value (syncjobs; pushover is equal)
        $this->assertSame(['quota: unlimited quota not allowed', 'imap_access: not allowed', 'tls_enforce_in: not allowed', 'user ACL syncjobs: not allowed',
            'pushover: not allowed', 'rate limit: not allowed'], $result['settings']['errors']);
        $this->assertSame([0, 0, 1], [$this->repo->tables['user_acl'][0]['spam_alias'], $this->repo->tables['user_acl'][0]['syncjobs'],
            $this->repo->tables['user_acl'][0]['pushover']]);
        // groups as the domain admin with his ACL of the request
        $this->assertSame([['domainadmin', 'da@firma.de', true], ['domainadmin', 'da@firma.de', true]], $this->groupUpdates);
        $this->assertSame(['added' => 1, 'skipped' => 1, 'errors' => ['Sales: not allowed', 'Gone: does not exist']], $result['groups']);
        $anna = $this->repo->rows('mailbox', ['username' => 'anna@firma.de'])[0];
        $this->assertSame(['1', '1'], [json_decode($anna['attributes'], true)['imap_access'], json_decode($anna['attributes'], true)['relayhost']]);
        $this->assertSame([], $this->repo->tables['pushover']);
        $this->assertSame(['not allowed'], $result['retention_rules']['errors']);
        $this->assertCount(1, $this->repo->tables['mail_policy_member']);
        $this->assertSame([], $this->repo->tables['retention_rule_member']);
    }

    public function testExternalGotosCanBeDisabledGlobally(): void
    {
        $this->job(['aliases']);
        $result = $this->service(true)->merge(12, 'anna@firma.de', self::backup());

        $this->assertSame('anna@firma.de', $this->alias('sales@firma.com')['goto']);
        $this->assertContains('sales@firma.com: goto partner@example.org dropped (external gotos are not allowed)', $result['aliases']['errors']);
    }

    public function testASecondMergeAddsNothing(): void
    {
        $this->merge();
        $this->groupUpdates = [];
        $this->repo->tables['mailbox_group_member'] = [['group_id' => 7, 'username' => 'anna@firma.de', 'source' => 'manual'],
            ['group_id' => 9, 'username' => 'anna@firma.de', 'source' => 'manual']];
        $tables = $this->repo->tables;

        $result = $this->merge();

        $this->assertSame($tables, $this->repo->tables);
        $this->assertSame([], $this->groupUpdates);
        foreach ($result as $component => $counts) {
            $this->assertSame(0, $counts['added'], $component);
        }
    }

    public function testOnlyTheComponentsOfTheJobAreMerged(): void
    {
        $this->job(['mails', 'sogo', 'bcc']);

        $this->assertSame(['bcc'], array_keys($this->merge()));
        $this->assertSame([], $this->repo->tables['tfa']);
        $this->assertSame([], $this->groupUpdates);
    }

    public function testTheAliasesOfADomainRestoreComeInParts(): void
    {
        $this->job(['aliases'], 'admin', [], 'running', ['scope' => 'domain', 'mailboxes' => ['anna@firma.de', 'bob@firma.de']]);
        $domainAliases = [
            ['id' => 1, 'address' => 'anna@firma.de', 'goto' => 'anna@firma.de', 'domain' => 'firma.de'],
            ['id' => 50, 'address' => '@firma.com', 'goto' => 'bob@firma.de', 'domain' => 'firma.com', 'active' => 1],
            ['id' => 51, 'address' => 'news@firma.de', 'goto' => 'null@localhost', 'domain' => 'firma.de', 'active' => 1],
            // existing aliases get their mailboxes back with the mailboxes' merge
            ['id' => 3, 'address' => 'info@firma.de', 'goto' => 'bob@firma.de,anna@firma.de', 'domain' => 'firma.de', 'active' => 1],
        ];

        $result = $this->service()->mergeAliases(12, $domainAliases);

        $this->assertSame(['aliases' => ['added' => 2, 'skipped' => 1, 'errors' => []]], $result);
        $this->assertSame(['bob@firma.de', 'null@localhost', 'bob@firma.de'], [$this->alias('@firma.com')['goto'], $this->alias('news@firma.de')['goto'],
            $this->alias('info@firma.de')['goto']]);
        $this->assertSame(['bob@firma.de'], $this->sogoUsers);
        // mailboxes the restore creates count as existing gotos
        $this->job(['aliases'], 'admin', [], 'running', ['scope' => 'domain', 'mailboxes' => ['anna@firma.de', 'dora@firma.de'],
            'create_mailboxes' => ['dora@firma.de']]);
        $this->assertSame(['added' => 1, 'skipped' => 0, 'errors' => ['x@firma.de: goto gone@firma.de dropped (no mailbox or alias)']],
            $this->service()->mergeAliases(12, [['address' => 'x@firma.de', 'goto' => 'dora@firma.de,gone@firma.de', 'domain' => 'firma.de']])['aliases']);
        $this->assertSame('dora@firma.de', $this->alias('x@firma.de')['goto']);
        $this->expectUserError(['backup_entries_exceeded', 500], fn() => $this->service()->mergeAliases(12, array_fill(0, 501, [])));
        $this->expectUserError('backup_export_invalid', fn() => $this->service()->mergeAliases(12, 'x'));
        // only for domain restores with aliases
        $this->job(['aliases']);
        $this->expectUserError('access_denied', fn() => $this->service()->mergeAliases(12, $domainAliases));
        $this->job(['mails'], 'admin', [], 'running', ['scope' => 'domain']);
        $this->expectUserError('access_denied', fn() => $this->service()->mergeAliases(12, $domainAliases));
    }

    public function testADeletedMailboxIsCreatedFromTheBackup(): void
    {
        $this->job(['aliases', 'settings'], 'admin', [], 'running', ['mailboxes' => ['dora@firma.de'], 'create_mailboxes' => ['dora@firma.de']]);
        $data = self::backup();
        $data['mailbox'] = self::mailbox('dora@firma.de', ['password' => 'HASH-DORA', 'quota' => 512 * self::MIB, 'name' => 'Dora',
            'attributes' => '{"imap_access": "1"}', 'created' => '2020-01-01 00:00:00']) + ['tags' => ['vip']];
        $data['user_acl'] = ['username' => 'dora@firma.de', 'spam_alias' => 0, 'syncjobs' => 1, 'pushover' => 0];
        $data['aliases'] = [['address' => 'info@firma.de', 'goto' => 'bob@firma.de,dora@firma.de', 'domain' => 'firma.de']];

        $result = $this->merge($data, 'dora@firma.de');

        $this->assertSame(['added' => 1, 'skipped' => 0, 'errors' => [], 'created' => 1], $result['mailbox']);
        $dora = $this->repo->rows('mailbox', ['username' => 'dora@firma.de'])[0];
        $this->assertSame(['HASH-DORA', 'Dora', 512 * self::MIB, 'dora', 'firma.de', '2020-01-01 00:00:00'], [$dora['password'], $dora['name'], $dora['quota'],
            $dora['local_part'], $dora['domain'], $dora['created']]);
        // the side effects of the legacy mailbox add
        $this->assertSame('dora@firma.de', $this->alias('dora@firma.de')['goto']);
        $this->assertSame([['username' => 'dora@firma.de', 'bytes' => 0, 'messages' => 0]], $this->repo->tables['quota2']);
        $this->assertSame([0, 1], [$this->repo->rows('user_acl', ['username' => 'dora@firma.de'])[0]['spam_alias'],
            $this->repo->rows('user_acl', ['username' => 'dora@firma.de'])[0]['syncjobs']]);
        $this->assertSame(['vip'], array_column($this->repo->rows('tags_mailbox', ['username' => 'dora@firma.de']), 'tag_name'));
        // then the components as for an existing mailbox
        $this->assertSame('bob@firma.de,dora@firma.de', $this->alias('info@firma.de')['goto']);
        // quota, attributes, user ACL and tags came with the mailbox: only Pushover and the rate limit are new
        $this->assertSame(2, $result['settings']['added']);
        $this->assertContains('dora@firma.de', $this->sogoUsers);
        $this->assertContains('dora@firma.de', $this->authUsers);
        // a second request finds it
        $this->assertSame(['added' => 0, 'skipped' => 1, 'errors' => [], 'created' => 0], $this->merge($data, 'dora@firma.de')['mailbox']);
    }

    public function testACreatedMailboxGetsItsForwardingBack(): void
    {
        $this->job(['aliases'], 'admin', [], 'running', ['mailboxes' => ['dora@firma.de'], 'create_mailboxes' => ['dora@firma.de']]);
        $data = self::backup(['aliases' => []]);
        $data['mailbox'] = self::mailbox('dora@firma.de');
        // forwarding with a copy, one goto no longer valid
        $data['own_alias'] = ['id' => 60, 'address' => 'dora@firma.de', 'goto' => 'dora@firma.de,fwd@ext.example,gone@firma.de', 'domain' => 'firma.de'];

        $result = $this->merge($data, 'dora@firma.de');

        $this->assertSame(1, $result['mailbox']['created']);
        $this->assertSame('dora@firma.de,fwd@ext.example', $this->alias('dora@firma.de')['goto']);
        $this->assertSame(['dora@firma.de: goto gone@firma.de dropped (no mailbox or alias)'], $result['aliases']['errors']);
        // forwarding without a copy
        $this->repo->tables['mailbox'] = array_values(array_filter($this->repo->tables['mailbox'], fn(array $row): bool => $row['username'] !== 'dora@firma.de'));
        $this->repo->tables['alias'] = array_values(array_filter($this->repo->tables['alias'], fn(array $row): bool => $row['address'] !== 'dora@firma.de'));
        $data['own_alias']['goto'] = 'fwd@ext.example';
        $this->merge($data, 'dora@firma.de');
        $this->assertSame('fwd@ext.example', $this->alias('dora@firma.de')['goto']);
        // an existing mailbox keeps its own alias as it is now
        $this->job(['aliases']);
        $this->merge(self::backup(['aliases' => [], 'own_alias' => ['address' => 'anna@firma.de', 'goto' => 'anna@firma.de,fwd@ext.example']]));
        $this->assertSame('anna@firma.de', $this->alias('anna@firma.de')['goto']);
    }

    public function testTheAliasLeftByADeletedForwardingMailboxIsTakenBack(): void
    {
        // deleting dora (forwarding with a copy) left dora@ -> fwd@ext.example
        $this->repo->tables['alias'][] = ['id' => 70, 'address' => 'dora@firma.de', 'goto' => 'fwd@ext.example', 'domain' => 'firma.de', 'active' => 1];
        $this->job(['aliases'], 'admin', [], 'running', ['mailboxes' => ['dora@firma.de'], 'create_mailboxes' => ['dora@firma.de']]);
        $data = self::backup(['aliases' => []]);
        $data['mailbox'] = self::mailbox('dora@firma.de');
        $data['own_alias'] = ['address' => 'dora@firma.de', 'goto' => 'dora@firma.de,fwd@ext.example', 'domain' => 'firma.de'];

        $this->assertSame(1, $this->merge($data, 'dora@firma.de')['mailbox']['created']);
        $this->assertSame([70, 'dora@firma.de,fwd@ext.example'], [$this->alias('dora@firma.de')['id'], $this->alias('dora@firma.de')['goto']]);
        // an alias with other targets (added since) still blocks
        $this->repo->tables['mailbox'] = array_values(array_filter($this->repo->tables['mailbox'], fn(array $row): bool => $row['username'] !== 'dora@firma.de'));
        $this->repo->update('alias', ['id' => 70], ['goto' => 'bob@firma.de']);
        $this->assertSame(['the address is in use (alias)'], $this->merge($data, 'dora@firma.de')['mailbox']['errors']);
    }

    public function testTheOwnAliasOfADeletedMailboxDoesNotBlockIt(): void
    {
        // a domain restore: dora is created with her mailbox, gone@ stays deleted; neither becomes a plain alias
        $this->job(['aliases'], 'admin', [], 'running', ['scope' => 'domain', 'mailboxes' => ['anna@firma.de', 'dora@firma.de'],
            'create_mailboxes' => ['dora@firma.de']]);
        $result = $this->service()->mergeAliases(12, [
            ['address' => 'dora@firma.de', 'goto' => 'dora@firma.de,fwd@ext.example', 'domain' => 'firma.de', 'active' => 1],
            ['address' => 'gone@firma.de', 'goto' => 'gone@firma.de,anna@firma.de', 'domain' => 'firma.de', 'active' => 1],
        ]);
        $this->assertSame(['added' => 0, 'skipped' => 1, 'errors' => []], $result['aliases']);
        $this->assertSame([null, null], [$this->alias('dora@firma.de'), $this->alias('gone@firma.de')]);
        $data = self::backup(['aliases' => []]);
        $data['mailbox'] = self::mailbox('dora@firma.de');
        $data['own_alias'] = ['address' => 'dora@firma.de', 'goto' => 'dora@firma.de,fwd@ext.example', 'domain' => 'firma.de'];
        $this->assertSame(1, $this->merge($data, 'dora@firma.de')['mailbox']['created']);
        $this->assertSame('dora@firma.de,fwd@ext.example', $this->alias('dora@firma.de')['goto']);
        // a mailbox restore: the own alias of a deleted mailbox naming anna is not created as an alias
        $this->job(['aliases']);
        $this->merge(self::backup(['aliases' => [['address' => 'gone@firma.de', 'goto' => 'gone@firma.de,anna@firma.de', 'domain' => 'firma.de']]]));
        $this->assertNull($this->alias('gone@firma.de'));
    }

    public function testTheAliasLimitCountsTheAliasesOfTheRequest(): void
    {
        $this->job(['aliases']);
        // info@ and team@ use 2 of 3
        $this->repo->update('domain', ['domain' => 'firma.de'], ['aliases' => 3]);
        $result = $this->merge(self::backup(['aliases' => [
            ['address' => 'one@firma.de', 'goto' => 'anna@firma.de', 'domain' => 'firma.de'],
            ['address' => 'two@firma.de', 'goto' => 'anna@firma.de', 'domain' => 'firma.de'],
        ]]));
        $this->assertSame([1, ['two@firma.de: alias limit of the domain reached']], [$result['aliases']['added'], $result['aliases']['errors']]);
    }

    public function testAMailboxIsCreatedWithinTheDomainLimits(): void
    {
        $this->job(['aliases'], 'admin', [], 'running', ['mailboxes' => ['dora@firma.de', 'eve@firma.de'], 'create_mailboxes' => ['dora@firma.de']]);
        $data = self::backup();
        $data['mailbox'] = self::mailbox('dora@firma.de', ['quota' => 4096 * self::MIB]);

        $this->assertSame(['mailbox' => ['added' => 0, 'skipped' => 0, 'errors' => ['quota: exceeds the maximum quota of a mailbox in the domain'], 'created' => 0]],
            $this->merge($data, 'dora@firma.de'));
        $data['mailbox']['quota'] = 0;
        $this->repo->update('domain', ['domain' => 'firma.de'], ['mailboxes' => 2]);
        $this->assertSame(['mailbox limit of the domain reached'], $this->merge($data, 'dora@firma.de')['mailbox']['errors']);
        $this->repo->update('domain', ['domain' => 'firma.de'], ['mailboxes' => 10]);
        $this->repo->tables['alias'][] = ['id' => 60, 'address' => 'dora@firma.de', 'goto' => 'bob@firma.de', 'domain' => 'firma.de', 'active' => 1];
        $this->assertSame(['the address is in use (alias)'], $this->merge($data, 'dora@firma.de')['mailbox']['errors']);
        $this->assertSame([], $this->repo->rows('mailbox', ['username' => 'dora@firma.de']));
        // a mailbox missing now that was not missing when the restore was requested is not created
        $eve = self::backup();
        $eve['mailbox'] = self::mailbox('eve@firma.de');
        $this->expectUserError(['backup_mailbox_not_found', 'eve@firma.de'], fn() => $this->merge($eve, 'eve@firma.de'));
    }

    public function testARestoreStopsWhenTheDomainAdminLostHisRights(): void
    {
        $revoked = ['mailbox' => ['added' => 0, 'skipped' => 0, 'errors' => ['permission revoked'], 'created' => 0]];
        $this->job(['aliases', 'groups'], 'domainadmin', ['mailbox_groups' => 1, 'filters' => 0]);
        $this->job->requestedBy = 'da@firma.de';
        // no account
        $this->assertSame($revoked, $this->merge());
        // an ACL of the request taken away
        $this->domainAdmin('da@firma.de', ['mailbox_groups' => 0]);
        $this->assertSame($revoked, $this->merge());
        $this->repo->update('da_acl', ['username' => 'da@firma.de'], ['mailbox_groups' => 1]);
        $this->assertSame(['aliases', 'groups'], array_keys($this->merge()));
        // no longer administers the domain, or the account is disabled
        $this->repo->update('domain_admins', ['username' => 'da@firma.de'], ['active' => 0]);
        $this->assertSame($revoked, $this->merge());
        $this->repo->update('domain_admins', ['username' => 'da@firma.de'], ['active' => 1]);
        $this->repo->update('admin', ['username' => 'da@firma.de'], ['active' => 0]);
        $this->assertSame($revoked, $this->merge());
        $this->job(['aliases'], 'domainadmin', ['mailbox_groups' => 1], 'running', ['scope' => 'domain']);
        $this->job->requestedBy = 'da@firma.de';
        $this->assertSame(['aliases' => ['added' => 0, 'skipped' => 0, 'errors' => ['permission revoked']]], $this->service()->mergeAliases(12, []));
        // only the merge with all rights reached the group module
        $this->assertCount(2, $this->groupUpdates);
    }

    public function testAMailboxCreatedByADomainAdminGetsDefaultsForAclsHeLacks(): void
    {
        $this->job(['aliases'], 'domainadmin', ['app_passwds' => 1], 'running', ['mailboxes' => ['dora@firma.de'], 'create_mailboxes' => ['dora@firma.de']]);
        $this->job->requestedBy = 'da@firma.de';
        $this->domainAdmin('da@firma.de', ['app_passwds' => 1]);
        $data = self::backup();
        $data['mailbox'] = self::mailbox('dora@firma.de', ['quota' => 512 * self::MIB]);
        $data['user_acl'] = ['username' => 'dora@firma.de', 'spam_alias' => 0, 'syncjobs' => 1, 'pushover' => 1, 'app_passwds' => 1];

        $this->assertSame(1, $this->merge($data, 'dora@firma.de')['mailbox']['created']);

        // null: the column's default in the table
        $this->assertSame(['username' => 'dora@firma.de', 'spam_alias' => 0, 'syncjobs' => null, 'pushover' => null, 'quarantine' => null, 'app_passwds' => 1],
            $this->repo->rows('user_acl', ['username' => 'dora@firma.de'])[0]);
    }

    public function testARetriedRequestWaitsForTheFirst(): void
    {
        $this->repo->busyLocks = ['12:anna@firma.de'];

        $this->expectUserError('backup_job_busy', fn() => $this->merge());
        $this->assertSame([], $this->groupUpdates);
    }

    public function testAliasLimitAndAddressesInUse(): void
    {
        $this->job(['aliases']);
        $this->repo->tables['spamalias'][] = ['address' => 'shop@firma.de', 'goto' => 'bob@firma.de', 'validity' => time() + 60, 'permanent' => 0];
        $data = self::backup(['aliases' => [
            ['address' => 'shop@firma.de', 'goto' => 'anna@firma.de', 'domain' => 'firma.de'],
            ['address' => 'bob@firma.com', 'goto' => 'anna@firma.de', 'domain' => 'firma.com'],
            ['address' => 'x@unknown.de', 'goto' => 'anna@firma.de', 'domain' => 'unknown.de'],
        ]]);
        $this->assertSame(['shop@firma.de: address is in use (spamalias)', 'bob@firma.com: address is in use (mailbox)', 'x@unknown.de: domain does not exist'],
            $this->merge($data)['aliases']['errors']);

        $this->repo->update('domain', ['domain' => 'firma.de'], ['aliases' => 2]);
        $this->assertSame(['sales@firma.com: alias limit of the domain reached'], array_slice($this->merge()['aliases']['errors'], 0, 1));
    }

    public function testADatabaseErrorDropsOnlyItsComponent(): void
    {
        $this->job(['bcc', 'tfa', 'app_passwords']);
        $this->repo->failingTables = ['fido2'];

        $result = $this->merge();

        $this->assertSame(['added' => 0, 'skipped' => 0, 'errors' => ['database error 23000']], $result['tfa']);
        // the 2FA entry written before the failing FIDO2 key was rolled back with it
        $this->assertSame([[], []], [$this->repo->tables['tfa'], $this->repo->tables['fido2']]);
        $this->assertSame(1, $result['bcc']['added']);
        $this->assertCount(1, $this->repo->tables['app_passwd']);
    }

    public function testPasswordOnlyForMailcowLogins(): void
    {
        $this->job(['password']);
        $this->repo->update('mailbox', ['username' => 'anna@firma.de'], ['authsource' => 'keycloak']);

        $this->assertSame(['the mailbox logs in through an identity provider'], $this->merge()['password']['errors']);
        $this->assertSame('HASH-NOW', $this->repo->rows('mailbox', ['username' => 'anna@firma.de'])[0]['password']);
        $this->assertSame([], $this->authUsers);
    }

    public function testGroupErrorsAreReported(): void
    {
        $this->job(['groups']);
        $this->groups = $this->createMock(GroupMembers::class);
        $this->groups->method('add')->willThrowException(new UserError('group_member_invalid', ['anna@firma.de']));

        $this->assertSame(['Team: group_member_invalid', 'Sales: group_member_invalid', 'Gone: does not exist'], $this->merge()['groups']['errors']);
    }

    public function testTheJobDecidesWhatMayBeMerged(): void
    {
        $this->expectUserError('backup_job_not_running', fn() => $this->service()->merge(13, 'anna@firma.de', self::backup()));
        $this->expectUserError('backup_job_not_running', fn() => $this->service()->merge('x', 'anna@firma.de', self::backup()));
        $this->job(self::ALL, 'admin', [], 'done');
        $this->expectUserError('backup_job_not_running', fn() => $this->merge());
        $this->job(self::ALL);
        $this->expectUserError('access_denied', fn() => $this->merge([], 'bob@firma.de'));
        $this->expectUserError('backup_export_invalid', fn() => $this->merge(['mailbox' => ['username' => 'bob@firma.de']]));
        $this->expectUserError('backup_export_invalid', fn() => $this->service()->merge(12, 'Anna@firma.de', 'text'));
        $this->repo->tables['mailbox'] = array_values(array_filter($this->repo->tables['mailbox'], fn(array $m): bool => $m['username'] !== 'anna@firma.de'));
        $this->expectUserError(['backup_mailbox_not_found', 'anna@firma.de'], fn() => $this->merge());
        $this->assertSame([], $this->groupUpdates);
    }
}
