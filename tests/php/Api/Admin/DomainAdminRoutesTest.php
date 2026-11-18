<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Admin;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes domain-admin, da-acl and sso/domain-admin */
class DomainAdminRoutesTest extends ApiTestCase
{
    private const DETAILS = ['username' => 'domainadmin1', 'tfa_active' => 0, 'tfa_active_int' => 0, 'active' => 1, 'active_int' => 1,
        'created' => '2026-09-21 10:24:48', 'attributes' => ['force_tfa' => '0', 'force_pw_update' => '0'],
        'selected_domains' => ['example.org'], 'unselected_domains' => ['example.com']];

    public function testGetAllCollectsTheDetailsOfEveryDomainAdmin(): void
    {
        $result = $this->get('get/domain-admin/all', ['script' => [
            self::answer('domain_admin', ['get'], ['domainadmin1', 'gone']),
            self::answer('domain_admin', ['details', 'domainadmin1'], self::DETAILS),
            self::answer('domain_admin', ['details', 'gone'], false),
        ]]);

        $this->assertSame([self::DETAILS], $result->json());
        $this->assertSame([['get'], ['details', 'domainadmin1'], ['details', 'gone']], $result->callsTo('domain_admin'));
    }

    public function testGetAllWithoutDomainAdmins(): void
    {
        $result = $this->get('get/domain-admin/all', ['script' => [self::answer('domain_admin', ['get'], [])]]);

        $this->assertSame('{}', $result->body);
    }

    public function testGetOne(): void
    {
        $found = $this->get('get/domain-admin/domainadmin1', ['script' => [self::answer('domain_admin', ['details', 'domainadmin1'], self::DETAILS)]]);
        $this->assertSame(self::DETAILS, $found->json());

        $missing = $this->get('get/domain-admin/nobody', ['script' => [self::answer('domain_admin', ['details'], false)]]);
        $this->assertSame('{}', $missing->body);
        $this->assertSame([['details', 'nobody']], $missing->callsTo('domain_admin'));
    }

    public function testAdd(): void
    {
        $attr = ['username' => 'domainadmin1', 'password' => 'Rand0m-Passw0rd!', 'password2' => 'Rand0m-Passw0rd!',
            'domains' => ['example.org', 'example.com'], 'active' => 1];

        $result = $this->post('add/domain-admin', $attr);

        $this->assertSame([['add', $attr]], $result->callsTo('domain_admin'));
    }

    public function testEdit(): void
    {
        $result = $this->post('edit/domain-admin', ['items' => ['domainadmin1'], 'attr' => ['domains' => ['example.org'], 'disable_tfa' => 1]]);

        $this->assertSame([['edit', ['username' => ['domainadmin1'], 'domains' => ['example.org'], 'disable_tfa' => 1]]], $result->callsTo('domain_admin'));
    }

    public function testDelete(): void
    {
        $result = $this->post('delete/domain-admin', ['domainadmin1', 'domainadmin2']);

        $this->assertSame([['delete', ['username' => ['domainadmin1', 'domainadmin2']]]], $result->callsTo('domain_admin'));
    }

    public function testEditAcl(): void
    {
        $messages = [self::message('success', ['acl_saved', 'domainadmin1'])];

        $result = $this->post('edit/da-acl', ['items' => 'domainadmin1', 'attr' => ['da_acl' => ['syncjobs', 'quarantine']]],
            ['script' => [self::answer('acl', ['edit', 'domainadmin'], null, $messages)]]);

        $this->assertSame([['edit', 'domainadmin', ['username' => ['domainadmin1'], 'da_acl' => ['syncjobs', 'quarantine']]]], $result->callsTo('acl'));
        $this->assertSame($messages, $result->json());
    }

    public function testSsoTokenIsTheAnswer(): void
    {
        $token = ['token' => '591F6D-5C3DD2-7455CD-DAF1C1-AA4FCC'];

        $result = $this->post('add/sso/domain-admin', ['username' => 'domainadmin1'],
            ['script' => [self::answer('domain_admin_sso', ['issue'], $token)]]);

        $this->assertSame([['issue', ['username' => 'domainadmin1']]], $result->callsTo('domain_admin_sso'));
        $this->assertSame($token, $result->json());
    }

    public function testSsoTokenFailureAnswersTheMessages(): void
    {
        $messages = [self::message('danger', ['object_doesnt_exist', 'nobody'])];

        $failed = $this->post('add/sso/domain-admin', ['username' => 'nobody'],
            ['script' => [self::answer('domain_admin_sso', ['issue'], false, $messages)]]);
        $this->assertSame($messages, $failed->json());

        $silent = $this->post('add/sso/domain-admin', ['username' => 'nobody'],
            ['script' => [self::answer('domain_admin_sso', ['issue'], false)]]);
        $this->assertSame(['type' => 'error', 'msg' => 'Cannot add item'], $silent->json());
    }

    public function testSsoForOtherObjectsAnswersNothing(): void
    {
        $result = $this->post('add/sso/mailbox', ['username' => 'user@example.org']);

        $this->assertSame([200, ''], [$result->status, $result->body]);
        $this->assertNotCalled($result, 'domain_admin_sso');
    }
}
