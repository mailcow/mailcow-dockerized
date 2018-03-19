<?php

namespace OAuth2;use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testRenderAsXml()
    {
        $response = new Response(array(
            'foo' => 'bar',
            'halland' => 'oates',
        ));

        $string = $response->getResponseBody('xml');
        $this->assertContains('<response><foo>bar</foo><halland>oates</halland></response>', $string);
    }
}
