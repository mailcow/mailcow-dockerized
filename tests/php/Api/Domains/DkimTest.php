<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api\Domains;

use Mailcow\Tests\Api\ApiTestCase;

/** get/dkim, add/dkim, add/dkim_duplicate, add/dkim_import, delete/dkim */
class DkimTest extends ApiTestCase
{
    public function testDkimKeyOfADomain(): void
    {
        $key = ['pubkey' => 'MIIBIjAN', 'length' => '2048', 'dkim_txt' => 'v=DKIM1;k=rsa;t=s;s=email;p=MIIBIjAN', 'dkim_selector' => 'dkim', 'privkey' => ''];

        $result = $this->get('get/dkim/example.org', ['script' => [self::answer('dkim', ['details', 'example.org'], $key)]]);

        $this->assertSame([['details', 'example.org']], $result->callsTo('dkim'));
        $this->assertSame($key, $result->json());
    }

    public function testDomainWithoutKey(): void
    {
        $result = $this->get('get/dkim/example.com', ['script' => [self::answer('dkim', ['details'], [])]]);

        $this->assertSame('{}', $result->body);
    }

    public function testGenerateKeys(): void
    {
        $attr = ['domains' => 'example.org, example.net', 'dkim_selector' => 'dkim', 'key_size' => 2048];

        $result = $this->post('add/dkim', $attr);

        $this->assertSame([['add', $attr]], $result->callsTo('dkim'));
    }

    public function testDuplicateKey(): void
    {
        $attr = ['from_domain' => 'example.org', 'to_domain' => ['example.net', 'example.com']];

        $result = $this->post('add/dkim_duplicate', $attr);

        $this->assertSame([['duplicate', $attr]], $result->callsTo('dkim'));
    }

    public function testImportKey(): void
    {
        $attr = ['domain' => 'example.org', 'dkim_selector' => 'dkim2026', 'private_key_file' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----", 'overwrite_existing' => '1'];

        $result = $this->post('add/dkim_import', $attr);

        $this->assertSame([['import', $attr]], $result->callsTo('dkim'));
    }

    public function testDeleteKeys(): void
    {
        $result = $this->post('delete/dkim', ['example.org', 'example.net']);

        $this->assertSame([['delete', ['domains' => ['example.org', 'example.net']]]], $result->callsTo('dkim'));
    }
}
