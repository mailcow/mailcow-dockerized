<?php

declare(strict_types=1);

namespace Mailcow\Tests\Backup\Service;

use Mailcow\Backup\Legacy\LegacyDomain;
use Mailcow\Backup\Repository\ExportRepository;
use Mailcow\Backup\Service\ExportService;
use Mailcow\Core\UserError;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExportServiceTest extends TestCase
{
    private ExportRepository&MockObject $repo;
    private LegacyDomain&MockObject $legacy;
    /** @var string[] usernames of the domain */
    private array $usernames = ['anna@firma.de', 'bob@firma.de'];

    protected function setUp(): void
    {
        $this->repo = $this->createMock(ExportRepository::class);
        $this->repo->method('dbVersion')->willReturn('08112026_1700');
        $this->repo->method('rows')->willReturnCallback(fn(string $table, string $column, string $value): array => match ($table) {
            'domain' => ($value === 'firma.de') ? [['domain' => 'firma.de', 'description' => 'Firma', 'mailboxes' => 10, 'relayhost' => '0']] : [],
            'alias_domain' => [['alias_domain' => 'firma.com', 'target_domain' => 'firma.de', 'active' => 1]],
            'tags_domain' => [['tag_name' => 'kunde', 'domain' => 'firma.de']],
            'filterconf' => [['prefid' => 1, 'object' => 'firma.de', 'option' => 'whitelist_from', 'value' => '*@partner.example']],
            'domain_wide_footer' => [['domain' => 'firma.de', 'html' => '<p>Firma</p>', 'plain' => 'Firma']],
            'domain_admins' => [['id' => 1, 'username' => 'da', 'domain' => 'firma.de', 'active' => 1]],
            'mailbox_group' => [['id' => 7, 'domain' => 'firma.de', 'name' => 'Team', 'address' => 'team@firma.de']],
            default => [],
        });
        $this->repo->method('domainRules')->willReturnCallback(fn(string $table): array => ($table === 'mail_policy')
            ? [['id' => 3, 'domain' => 'firma.de', 'name' => 'Strict', 'send_allow' => '["group:7"]', 'group_members' => ['Team']]] : []);
        $this->repo->method('mailboxesAfter')->willReturnCallback(fn(string $domain, string $after, int $limit): array => array_slice(array_values(array_filter(
            array_map(fn(string $username): array => ['username' => $username, 'password' => '{SSHA256}hash', 'attributes' => '{}', 'quota' => 0], $this->usernames),
            fn(array $row): bool => $row['username'] > $after)), 0, $limit));
        $this->repo->method('aliases')->willReturn([['id' => 1, 'address' => 'anna@firma.de', 'goto' => 'anna@firma.de', 'group_id' => null]]);
        $this->repo->method('aliasesPointingTo')->willReturn([
            ['id' => 1, 'address' => 'anna@firma.de', 'goto' => 'anna@firma.de', 'group_id' => null],
            ['id' => 2, 'address' => 'info@other.de', 'goto' => 'Anna@firma.de, bob@firma.de,x@ext.example', 'group_id' => null],
            ['id' => 3, 'address' => 'team@firma.de', 'goto' => 'anna@firma.de', 'group_id' => 7],
            ['id' => 4, 'address' => 'list@firma.de', 'goto' => 'notamailbox@firma.de', 'group_id' => null],
        ]);
        $this->repo->method('rowsOfMailboxes')->willReturnCallback(fn(string $table, string $column, array $usernames): array =>
            array_values(array_filter(match ($table) {
                'tags_mailbox' => [['tag_name' => 'vip', 'username' => 'anna@firma.de']],
                'user_acl' => [['username' => 'anna@firma.de', 'spam_alias' => 1]],
                'fido2' => [['username' => 'bob@firma.de', 'credentialId' => "\x00\xff\xfe", 'friendlyName' => 'Key']],
                'sender_acl' => ($column === 'send_as') ? [['id' => 5, 'logged_in_as' => 'bob@firma.de', 'send_as' => 'anna@firma.de']] : [],
                // anna forwards with a copy
                'alias' => [['id' => 1, 'address' => 'anna@firma.de', 'goto' => 'anna@firma.de,fwd@ext.example', 'group_id' => null]],
                default => [],
            }, fn(array $row): bool => in_array(strtolower($row[$column]), $usernames, true))));
        $this->repo->method('usernames')->willReturnCallback(fn(): array => $this->usernames);
        $this->repo->method('groupMemberships')->willReturn([['username' => 'anna@firma.de', 'id' => '7', 'name' => 'Team', 'domain' => 'firma.de', 'source' => 'manual']]);
        $this->repo->method('assignments')->willReturnCallback(fn(string $table, string $member, string $key, array $usernames): array =>
            ($table === 'retention_rule' && in_array('bob@firma.de', $usernames, true)) ? [['username' => 'bob@firma.de', 'id' => 5, 'name' => 'Papierkorb', 'domain' => 'firma.de']] : []);
        $this->repo->method('syncJobs')->willReturnCallback(fn(array $usernames): array => in_array('anna@firma.de', $usernames, true)
            ? [['id' => 9, 'user2' => 'anna@firma.de', 'user1' => 'a@old.de', 'password1' => 'pw', 'source_host1' => 'imap.old.de', 'source_port1' => 993]] : []);
        $this->legacy = $this->createMock(LegacyDomain::class);
        $this->legacy->method('dkim')->willReturnCallback(fn(string $domain): ?array => ($domain === 'firma.de')
            ? ['selector' => 'dkim', 'pubkey' => 'PUB', 'privkey' => 'PRIV'] : null);
        $this->legacy->method('rateLimits')->willReturnCallback(fn(array $objects): array =>
            array_intersect_key(['firma.de' => '100 / 1h', 'bob@firma.de' => '10 / 1m'], array_flip($objects)));
    }

    private function export(?string $after = null, int $pageSize = ExportService::PAGE_SIZE): array
    {
        return (new ExportService($this->repo, $this->legacy))->export(' Firma.de ', $after, $pageSize);
    }

    public function testTheExportHoldsEverythingOfTheMailboxes(): void
    {
        $export = $this->export();

        $this->assertSame([1, '08112026_1700', 'firma.de', null, 1], [$export['version'], $export['db_version'], $export['domain'],
            $export['next_after'], count($export['aliases'])]);
        $anna = $export['mailboxes']['anna@firma.de'];
        $bob = $export['mailboxes']['bob@firma.de'];
        // password hashes and secrets are part of the export on purpose
        $this->assertSame(['{SSHA256}hash', ['vip']], [$anna['mailbox']['password'], $anna['mailbox']['tags']]);
        $this->assertSame(['username' => 'anna@firma.de', 'spam_alias' => 1], $anna['user_acl']);
        $this->assertSame([null, null, '10 / 1m'], [$bob['user_acl'], $bob['pushover'], $bob['ratelimit']]);
        $this->assertSame([[7, 'Team', 'firma.de', 'manual']], array_map('array_values', $anna['groups']));
        $this->assertSame('Papierkorb', $bob['retention_rules'][0]['name']);
        $this->assertSame([['id' => 5, 'logged_in_as' => 'bob@firma.de', 'send_as' => 'anna@firma.de']], $anna['sender_acl_granted']);
        $this->assertSame('pw', $anna['imapsync'][0]['password1']);
        // binary values survive JSON
        $this->assertSame(['base64' => base64_encode("\x00\xff\xfe")], $bob['fido2'][0]['credentialId']);
        $this->assertNotFalse(json_encode($export));
        // aliases of any domain naming the mailbox, without its own alias and group addresses: once, with the first page
        $this->assertSame([[2], [2], [], []], [array_column($export['mailbox_aliases']['anna@firma.de'], 'id'),
            array_column($export['mailbox_aliases']['bob@firma.de'], 'id'), $anna['aliases'], $bob['aliases']]);
        // the own alias with its forwarding comes with the mailbox
        $this->assertSame(['anna@firma.de,fwd@ext.example', null], [$anna['own_alias']['goto'], $bob['own_alias']]);
        $this->assertSame(['mailbox', 'user_acl', 'pushover', 'ratelimit', 'own_alias', 'aliases', 'groups', 'mail_policies', 'retention_rules', 'imapsync', 'sender_acl',
            'sender_acl_granted', 'sieve_filters', 'filterconf', 'spamalias', 'bcc_maps', 'app_passwd', 'tfa', 'fido2', 'identity_link'], array_keys($anna));
    }

    public function testTheDomainDataRecreatesTheDomain(): void
    {
        $data = $this->export()['domain_data'];

        $this->assertSame(['domain', 'tags', 'alias_domains', 'dkim', 'ratelimit', 'filterconf', 'bcc_maps', 'footer', 'mta_sts', 'domain_admins', 'groups',
            'mail_policies', 'retention_rules'], array_keys($data));
        $this->assertSame(['Firma', ['kunde'], 'firma.com', '100 / 1h', null], [$data['domain']['description'], $data['tags'],
            $data['alias_domains'][0]['alias_domain'], $data['ratelimit'], $data['mta_sts']]);
        // DKIM keys of the domain and its alias domains, with the private key
        $this->assertSame([['domain' => 'firma.de', 'selector' => 'dkim', 'pubkey' => 'PUB', 'privkey' => 'PRIV']], $data['dkim']);
        $this->assertSame(['Team'], $data['mail_policies'][0]['group_members']);
        $this->assertSame('<p>Firma</p>', $data['footer']['html']);
    }

    public function testBigDomainsComeInPages(): void
    {
        $this->usernames = ['a@firma.de', 'anna@firma.de', 'bob@firma.de'];

        $first = $this->export(null, 2);
        $last = $this->export($first['next_after'], 2);

        // pages by username: the next one starts after the last username, whatever was added or deleted meanwhile
        $this->assertSame([['a@firma.de', 'anna@firma.de'], 'anna@firma.de'], [array_keys($first['mailboxes']), $first['next_after']]);
        $this->assertSame([['bob@firma.de'], null], [array_keys($last['mailboxes']), $last['next_after']]);
        // the domain's data and aliases come with the first page only
        $this->assertArrayHasKey('domain_data', $first);
        $this->assertSame(['version', 'domain', 'mailboxes', 'next_after'], array_keys($last));
        // the aliases naming mailboxes of later pages come with the first page as well
        $this->assertSame([[2], []], [array_column($first['mailbox_aliases']['bob@firma.de'], 'id'), $last['mailboxes']['bob@firma.de']['aliases']]);
        $this->assertSame('{}', json_encode($this->export('zzz@firma.de', 2)['mailboxes']));
    }

    public function testUnknownDomain(): void
    {
        $this->expectException(UserError::class);
        (new ExportService($this->repo, $this->legacy))->export('nix.de');
    }
}
