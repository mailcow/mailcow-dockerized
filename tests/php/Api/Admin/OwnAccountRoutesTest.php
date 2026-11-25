<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Admin;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes self and fido2-fn act on the account of the session; the pending password update of a login */
class OwnAccountRoutesTest extends ApiTestCase
{
    private const PASSWORDS = ['user_old_pass' => 'old', 'user_new_pass' => 'N3w-Passw0rd!', 'user_new_pass2' => 'N3w-Passw0rd!'];

    public function testSelfDoesNothingForAnApiKey(): void
    {
        $result = $this->post('edit/self', ['attr' => self::PASSWORDS]);

        $this->assertSame([200, ''], [$result->status, $result->body]);
        $this->assertNotCalled($result, 'domain_admin');
        $this->assertNotCalled($result, 'edit_user_account');
    }

    public function testSelfOfADomainAdmin(): void
    {
        $result = $this->post('edit/self', ['attr' => self::PASSWORDS], ['session' => self::DOMAIN_ADMIN]);

        $this->assertSame([['edit', self::PASSWORDS]], $result->callsTo('domain_admin'));
    }

    public function testSelfOfAMailboxUser(): void
    {
        $result = $this->post('edit/self', ['attr' => self::PASSWORDS], ['session' => self::USER]);

        $this->assertSame([[self::PASSWORDS]], $result->callsTo('edit_user_account'));
    }

    public function testFido2FriendlyName(): void
    {
        $attr = ['fido2_cid' => hash('sha256', 'credential'), 'fido2_fn' => 'YubiKey'];

        $result = $this->post('edit/fido2-fn', ['attr' => $attr]);

        $this->assertSame([[['action' => 'edit_fn', 'fido2_attrs' => $attr]]], $result->callsTo('fido2'));
    }

    public function testPendingPasswordUpdateAllowsOnlyThePasswordRoutes(): void
    {
        $session = self::ADMIN + ['pending_pw_update' => true];

        $this->assertCalled($this->post('edit/admin', ['items' => 'admin', 'attr' => ['password' => 'x', 'password2' => 'x']], ['session' => $session]),
            'admin', ['edit', ['username' => ['admin'], 'password' => 'x', 'password2' => 'x']]);
        $this->assertCalled($this->get('get/passwordpolicy', ['session' => $session]), 'password_complexity', ['get']);

        $denied = $this->post('edit/domain-admin', ['items' => 'domainadmin1', 'attr' => ['active' => 0]], ['session' => $session]);
        $this->assertApiError($denied, 403, 'API access denied until the pending account setup is finished');
        $this->assertNotCalled($denied, 'domain_admin');
    }
}
