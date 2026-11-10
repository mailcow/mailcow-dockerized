<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Admin;

use Mailcow\Tests\Api\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** Routes passwordpolicy, reset-password-notification, the UI customization routes and cors */
class SettingsRoutesTest extends ApiTestCase
{
    private const POLICY = ['length' => '8', 'chars' => '1', 'special_chars' => '0', 'lowerupper' => '1', 'numbers' => '1'];

    public function testGetPasswordPolicy(): void
    {
        foreach (['get/passwordpolicy', 'get/passwordpolicy/all'] as $route) {
            $result = $this->get($route, ['script' => [self::answer('password_complexity', ['get'], self::POLICY)]]);
            $this->assertSame([['get']], $result->callsTo('password_complexity'), $route);
            $this->assertSame(self::POLICY, $result->json(), $route);
        }
    }

    public function testGetPasswordPolicyAsHtml(): void
    {
        $html = '<p class="help-block small">- Minimum password length is 8</p>';

        $result = $this->get('get/passwordpolicy/html', ['script' => [self::answer('password_complexity', ['html'], $html)]]);

        $this->assertSame([['html']], $result->callsTo('password_complexity'));
        $this->assertSame($html, $result->json());
    }

    public function testGetPasswordPolicyFailure(): void
    {
        $this->assertSame('{}', $this->get('get/passwordpolicy', ['script' => [self::answer('password_complexity', ['get'], false)]])->body);
        $this->assertSame('{}', $this->get('get/passwordpolicy/html', ['script' => [self::answer('password_complexity', ['html'], false)]])->body);
    }

    public function testEditPasswordPolicyIgnoresItems(): void
    {
        $result = $this->post('edit/passwordpolicy', ['items' => ['x'], 'attr' => ['length' => 12, 'numbers' => 1]]);

        $this->assertSame([['edit', ['length' => 12, 'numbers' => 1]]], $result->callsTo('password_complexity'));
    }

    public function testEditPasswordResetNotification(): void
    {
        $attr = ['from' => 'noreply@example.org', 'subject' => 'Reset your password', 'text_tmpl' => '{{link}}', 'html_tmpl' => ''];

        $result = $this->post('edit/reset-password-notification', ['attr' => $attr]);

        $this->assertSame([['edit_notification', $attr]], $result->callsTo('reset_password'));
    }

    /** @return array<string, array{0: string, 1: array}> */
    public static function customizations(): array
    {
        return [
            'ui_texts' => ['ui_texts', ['title_name' => 'Example Mail', 'ui_announcement_type' => 'warning', 'ui_announcement_active' => 1]],
            'ip_check' => ['ip_check', ['ip_check_opt_in' => '1']],
            'custom_login' => ['custom_login', ['hide_admin_quicklink' => 1, 'force_sso' => 0]],
            'ui_theme' => ['ui_theme', ['ui_theme' => 'servercow']],
            'app_links' => ['app_links', ['app' => ['Wiki'], 'href' => ['https://wiki.example.org'], 'user_href' => [''], 'hide' => ['0']]],
        ];
    }

    #[DataProvider('customizations')]
    public function testCustomizationsPassAttrOnly(string $item, array $attr): void
    {
        $messages = [self::message('success', $item === 'ip_check' ? 'ip_check_opt_in_modified' : $item)];

        $result = $this->post("edit/$item", ['items' => ['ignored'], 'attr' => $attr], ['script' => [self::answer('customize', ['edit', $item], null, $messages)]]);

        $this->assertSame([['edit', $item, $attr]], $result->callsTo('customize'));
        $this->assertSame($messages, $result->json());
    }

    public function testCustomizationWithoutAttrPassesNothing(): void
    {
        $result = $this->post('edit/app_links', ['items' => []]);

        $this->assertSame([['edit', 'app_links', []]], $result->callsTo('customize'));
    }

    public function testEditCors(): void
    {
        $attr = ['allowed_origins' => ['https://app.example.org'], 'allowed_methods' => ['GET', 'POST']];

        $result = $this->post('edit/cors', ['attr' => $attr]);

        $this->assertSame([['set_headers'], ['edit', $attr]], $result->callsTo('cors'));
    }
}
