<?php

namespace OAuth2;

use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testRenderAsXml()
    {
        $response = new Response(array(
            'foo' => 'bar',
            'halland' => 'oates',
        ));

        $string = $response->getResponseBody('xml');
        $this->assertStringContainsString('<response><foo>bar</foo><halland>oates</halland></response>', $string);
    }

    public function testSetRedirect()
    {
        $response = new Response();
        $url = 'https://foo/bar';
        $state = 'stateparam';
        $response->setRedirect(301, $url, $state);
        $this->assertEquals(
            sprintf('%s?state=%s', $url, $state),
            $response->getHttpHeader('Location')
        );

        $query = 'query=foo';
        $response->setRedirect(301, $url . '?' . $query, $state);
        $this->assertEquals(
            sprintf('%s?%s&state=%s', $url, $query, $state),
            $response->getHttpHeader('Location')
        );
    }
}
