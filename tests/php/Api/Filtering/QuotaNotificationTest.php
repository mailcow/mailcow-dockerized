<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Filtering;

use Mailcow\Tests\Api\ApiTestCase;

/** Quota warning mails and their per-domain BCC */
class QuotaNotificationTest extends ApiTestCase
{
    public function testEditQuotaNotificationIgnoresItems(): void
    {
        $attr = ['sender' => 'quota-warning@example.org', 'subject' => 'Quota warning', 'html_tmpl' => ''];

        $result = $this->post('edit/quota_notification', ['items' => ['quota_notification'], 'attr' => $attr]);

        $this->assertSame([['edit', $attr]], $result->callsTo('quota_notification'));
    }

    public function testEditQuotaNotificationBccIgnoresItems(): void
    {
        $attr = ['domain' => 'example.org', 'bcc_rcpt' => "postmaster@example.org\nhelpdesk@example.org", 'active' => '1'];
        $messages = [self::message('success', 'saved_settings')];

        $result = $this->post('edit/quota_notification_bcc', ['items' => ['quota_bcc'], 'attr' => $attr], ['script' => [self::answer('quota_notification_bcc', ['edit'], null, $messages)]]);

        $this->assertSame([['edit', $attr]], $result->callsTo('quota_notification_bcc'));
        $this->assertSame($messages, $result->json());
    }
}
