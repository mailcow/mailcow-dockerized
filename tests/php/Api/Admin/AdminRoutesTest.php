<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Admin;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes admin: administrator accounts */
class AdminRoutesTest extends ApiTestCase
{
    private const DETAILS = ['username' => 'admin', 'tfa_active' => 0, 'tfa_active_int' => 0, 'active' => 1, 'active_int' => 1,
        'created' => '2026-09-21 10:23:08', 'attributes' => ['force_tfa' => '0', 'force_pw_update' => '0']];

    public function testGetAllCollectsTheDetailsOfEveryAdmin(): void
    {
        $result = $this->get('get/admin/all', ['script' => [
            self::answer('admin', ['get'], ['admin', 'gone']),
            self::answer('admin', ['details', 'admin'], self::DETAILS),
            self::answer('admin', ['details', 'gone'], false),
        ]]);

        $this->assertSame([self::DETAILS], $result->json());
        $this->assertSame([['get'], ['details', 'admin'], ['details', 'gone']], $result->callsTo('admin'));
    }

    public function testGetAllWithoutAdmins(): void
    {
        $result = $this->get('get/admin/all', ['script' => [self::answer('admin', ['get'], [])]]);

        $this->assertSame('{}', $result->body);
        $this->assertSame([['get']], $result->callsTo('admin'));
    }

    public function testGetOne(): void
    {
        $found = $this->get('get/admin/admin', ['script' => [self::answer('admin', ['details', 'admin'], self::DETAILS)]]);
        $this->assertSame(self::DETAILS, $found->json());

        $missing = $this->get('get/admin/nobody', ['script' => [self::answer('admin', ['details'], false)]]);
        $this->assertSame('{}', $missing->body);
        $this->assertCalled($missing, 'admin', ['details', 'nobody']);
    }

    public function testAdd(): void
    {
        $attr = ['username' => 'admin2', 'password' => 'Rand0m-Passw0rd!', 'password2' => 'Rand0m-Passw0rd!', 'active' => 1];
        $messages = [self::message('success', ['admin_added', 'admin2'], ['admin', 'add', ['username' => 'admin2', 'password' => '*']])];

        $result = $this->post('add/admin', $attr, ['script' => [self::answer('admin', ['add'], null, $messages)]]);

        $this->assertSame([['add', $attr]], $result->callsTo('admin'));
        $this->assertSame($messages, $result->json());
    }

    public function testEdit(): void
    {
        $result = $this->post('edit/admin', ['items' => ['admin2', 'admin3'], 'attr' => ['active' => '0', 'force_tfa' => 1]]);

        $this->assertSame([['edit', ['username' => ['admin2', 'admin3'], 'active' => '0', 'force_tfa' => 1]]], $result->callsTo('admin'));
    }

    public function testEditTakesASingleUsername(): void
    {
        $result = $this->post('edit/admin', ['items' => 'admin2', 'attr' => ['password' => 'x', 'password2' => 'x']]);

        $this->assertCalled($result, 'admin', ['edit', ['username' => ['admin2'], 'password' => 'x', 'password2' => 'x']]);
    }

    public function testDelete(): void
    {
        $messages = [
            self::message('success', ['admin_removed', 'admin2']),
            self::message('danger', ['username_invalid', 'nobody']),
        ];

        $result = $this->post('delete/admin', ['admin2', 'nobody'], ['script' => [self::answer('admin', ['delete'], null, $messages)]]);

        $this->assertSame([['delete', ['username' => ['admin2', 'nobody']]]], $result->callsTo('admin'));
        $this->assertSame($messages, $result->json());
    }
}
