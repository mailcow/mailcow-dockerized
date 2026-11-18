<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Domains;

use Mailcow\Tests\Api\ApiTestCase;

/** get/add/edit/delete domain/template */
class DomainTemplateTest extends ApiTestCase
{
    private const TEMPLATE = ['id' => 1, 'template' => 'Default', 'type' => 'domain', 'attributes' => ['max_num_aliases_for_domain' => 400]];

    public function testAllTemplates(): void
    {
        $result = $this->get('get/domain/template/all', ['script' => [self::answer('mailbox', ['get', 'domain_templates'], [self::TEMPLATE])]]);

        $this->assertSame([['get', 'domain_templates']], $result->callsTo('mailbox'));
        $this->assertSame([self::TEMPLATE], $result->json());
    }

    public function testOneTemplate(): void
    {
        $result = $this->get('get/domain/template/1', ['script' => [self::answer('mailbox', ['get', 'domain_templates', '1'], self::TEMPLATE)]]);

        $this->assertSame(self::TEMPLATE, $result->json());
    }

    public function testUnknownTemplate(): void
    {
        $result = $this->get('get/domain/template/9', ['script' => [self::answer('mailbox', ['get', 'domain_templates'], false)]]);

        $this->assertCalled($result, 'mailbox', ['get', 'domain_templates', '9']);
        $this->assertSame('{}', $result->body);
    }

    public function testTemplateWithoutIdAsksForAll(): void
    {
        $result = $this->get('get/domain/template');

        $this->assertSame([['get', 'domain_templates', null]], $result->callsTo('mailbox'));
    }

    public function testAddTemplate(): void
    {
        $attr = ['template' => 'Small business', 'max_num_mboxes_for_domain' => 25, 'def_quota_for_mbox' => 2048, 'tags' => ['small']];

        $result = $this->post('add/domain/template', $attr);

        $this->assertSame([['add', 'domain_templates', $attr]], $result->callsTo('mailbox'));
    }

    public function testEditTemplates(): void
    {
        $result = $this->post('edit/domain/template', ['items' => [2, 3], 'attr' => ['template' => 'Small business', 'active' => 1]]);

        $this->assertCalled($result, 'mailbox', ['edit', 'domain_templates', ['ids' => [2, 3], 'template' => 'Small business', 'active' => 1]]);
    }

    public function testDeleteTemplates(): void
    {
        $result = $this->post('delete/domain/template', [2, 3]);

        $this->assertCalled($result, 'mailbox', ['delete', 'domain_templates', ['ids' => [2, 3]]]);
    }
}
