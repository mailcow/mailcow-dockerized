<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Modules;

use Mailcow\Tests\Api\ApiTestCase;

/** Routes identity-provider, identity-provider-test, identity-provider-sync and identity-link */
class IdentityRoutesTest extends ApiTestCase
{
    private const API = 'Mailcow\Identity\IdentityModule->api->';
    /** The Identity Provider settings that prerequisites.inc.php loads into $iam_settings */
    private const IAM_SETTINGS = ['iam_settings' => [
        'authsource' => 'keycloak',
        'server_url' => 'https://auth.example.org',
        'realm' => 'mailcow',
        'client_id' => 'mailcow',
        'client_secret' => 'client-secret-value',
        'bindpass' => 'bind-password',
        'access_token' => 'cached-token',
        'redirect_url_extra' => ['https://webmail.example.org'],
        'login_provisioning' => true,
    ]];
    private const SETTINGS = [
        'authsource' => 'keycloak',
        'server_url' => 'https://auth.example.org',
        'realm' => 'mailcow',
        'client_id' => 'mailcow',
        'client_secret' => 'client-secret-value',
        'bindpass' => 'bind-password',
        'access_token' => 'cached-token',
        'redirect_url_extra' => ['https://webmail.example.org'],
        'login_provisioning' => true,
    ];

    public function testSettingsForReadWriteKeys(): void
    {
        $result = $this->get('get/identity-provider', ['globals' => self::IAM_SETTINGS]);

        $this->assertSame(self::SETTINGS, $result->json());
        $this->assertNotCalled($result, self::API . 'getStatus');
    }

    public function testReadOnlyKeysGetNoSecrets(): void
    {
        $result = $this->get('get/identity-provider', ['globals' => self::IAM_SETTINGS, 'session' => self::API_RO]);

        $this->assertSame(array_replace(self::SETTINGS, ['client_secret' => '*', 'bindpass' => '*', 'access_token' => '*']), $result->json());
    }

    public function testSettingsOnlyForAdmins(): void
    {
        foreach ([self::DOMAIN_ADMIN, self::USER] as $session) {
            $this->assertSame('{}', $this->get('get/identity-provider', ['globals' => self::IAM_SETTINGS, 'session' => $session])->body);
        }
    }

    public function testOtherObjectsAnswerTheSettingsToo(): void
    {
        $result = $this->get('get/identity-provider/anything', ['globals' => self::IAM_SETTINGS]);

        $this->assertSame(self::SETTINGS, $result->json());
    }

    public function testSyncStatus(): void
    {
        $status = ['provider' => 'keycloak', 'can_sync' => true, 'sync_enabled' => false, 'counts' => ['accounts' => 2], 'runs' => []];

        $result = $this->get('get/identity-provider/sync-status', ['session' => self::API_RO, 'script' => [self::answer(self::API . 'getStatus', [], $status)]]);

        $this->assertCalled($result, self::API . 'getStatus', []);
        $this->assertSame($status, $result->json());
    }

    public function testLink(): void
    {
        $link = ['provider' => 'keycloak', 'external_id' => '8d5da28d-7541-428b-8356-a9704f73e7f4', 'idp_disabled' => 0, 'missing_since' => null];

        $result = $this->get('get/identity-link/alice@example.org', ['script' => [self::answer(self::API . 'getLink', ['alice@example.org'], $link)]]);
        $this->assertCalled($result, self::API . 'getLink', ['alice@example.org']);
        $this->assertSame($link, $result->json());

        $none = $this->get('get/identity-link');
        $this->assertCalled($none, self::API . 'getLink', ['']);
        $this->assertSame('{}', $none->body);
    }

    public function testEditSettings(): void
    {
        $attr = ['authsource' => 'ldap', 'host' => 'ldap.example.org', 'basedn' => 'dc=example,dc=org', 'binddn' => 'cn=mailcow', 'bindpass' => 'secret'];
        $messages = [self::message('success', ['object_modified', ''], ['identity_provider', 'edit', ['bindpass' => '*']])];

        $result = $this->post('edit/identity-provider', ['items' => ['identity-provider'], 'attr' => $attr],
            ['script' => [self::answer('identity_provider', ['edit'], true, $messages)]]);

        $this->assertCalled($result, 'identity_provider', ['edit', $attr]);
        $this->assertSame($messages, $result->json());
    }

    public function testConnectionTestAnswersWithoutDetails(): void
    {
        $attr = ['authsource' => 'keycloak', 'server_url' => 'https://auth.example.org', 'realm' => 'mailcow', 'client_id' => 'mailcow', 'client_secret' => 'secret'];

        $ok = $this->post('edit/identity-provider-test', ['attr' => $attr], ['script' => [self::answer('identity_provider', ['test'], true)]]);
        $this->assertCalled($ok, 'identity_provider', ['test', $attr]);
        $this->assertSame(['type' => 'success', 'msg' => 'Task completed'], $ok->json());

        $failed = $this->post('edit/identity-provider-test', ['attr' => $attr], ['script' => [self::answer('identity_provider', ['test'], false)]]);
        $this->assertSame(['type' => 'error', 'msg' => 'Cannot edit item'], $failed->json());
    }

    public function testRequestSync(): void
    {
        $result = $this->post('edit/identity-provider-sync', ['items' => ['sync'], 'attr' => ['mode' => 'preview']]);

        $this->assertCalled($result, self::API . 'requestRun', [['mode' => 'preview']]);
    }

    public function testDeleteSettingsIgnoresTheBody(): void
    {
        $result = $this->post('delete/identity-provider', ['anything']);

        $this->assertSame([['delete']], $result->callsTo('identity_provider'));
    }

    public function testDeleteLinks(): void
    {
        $messages = [self::message('success', ['iam_link_removed', 'alice@example.org'], ['identity_provider', 'delete', 'link', ['alice@example.org']])];

        $result = $this->post('delete/identity-link', ['alice@example.org'], ['script' => [self::answer(self::API . 'unlink', [], true, $messages)]]);

        $this->assertCalled($result, self::API . 'unlink', [['alice@example.org']]);
        $this->assertSame($messages, $result->json());
    }
}
