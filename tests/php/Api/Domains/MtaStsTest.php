<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Domains;

use Mailcow\Tests\Api\ApiTestCase;

/** add/mta-sts, edit/mta-sts (there is no read route) */
class MtaStsTest extends ApiTestCase
{
    public function testAddPolicy(): void
    {
        $attr = ['domain' => 'example.org', 'version' => 'stsv1', 'mode' => 'enforce', 'mx' => 'mail.example.org,mx2.example.org', 'max_age' => 86400, 'active' => 1];

        $result = $this->post('add/mta-sts', $attr);

        $this->assertSame([['add', 'mta_sts', $attr]], $result->callsTo('mailbox'));
        // the backend reports no success message of its own
        $this->assertSame(['type' => 'success', 'msg' => 'Task completed'], $result->json());
    }

    public function testEditPolicies(): void
    {
        $result = $this->post('edit/mta-sts', ['items' => ['example.org'], 'attr' => ['version' => 'stsv1', 'mode' => 'testing']]);

        $this->assertSame([['edit', 'mta_sts', ['domains' => ['example.org'], 'version' => 'stsv1', 'mode' => 'testing']]], $result->callsTo('mailbox'));
    }

    public function testThereIsNoReadRoute(): void
    {
        $this->assertApiError($this->get('get/mta-sts/example.org'), 404, 'route not found');
    }
}
