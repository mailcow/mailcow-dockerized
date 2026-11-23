<?php

declare(strict_types=1);

namespace Mailcow\Tests\Scim\Controller;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Scim\Controller\TokenApiController;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Service\TokenService;
use PHPUnit\Framework\TestCase;

class TokenApiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTheNewTokenIsNeverLogged(): void
    {
        $service = $this->createMock(TokenService::class);
        $service->method('create')->willReturnOnConsecutiveCalls([Token::fromRow(['id' => 3, 'description' => 'Entra']), 'secret-value'],
            [Token::fromRow(['id' => 4, 'description' => 'Entra']), 'other-value'], [Token::fromRow(['id' => 5]), 'api-value']);
        $api = new TokenApiController($service, new ApiResponse());

        $this->assertTrue($api->addToken(['description' => 'Entra']));
        // API callers get the value in the response entry, beside the parts logger() stores (type, msg, log)
        $this->assertSame('secret-value', $_SESSION['return'][0]['scim_token']);
        $logged = array_map(fn(array $entry): array => array_intersect_key($entry, array_flip(['type', 'msg', 'log'])), $_SESSION['return']);
        $this->assertSame([['type' => 'success', 'log' => ['scim', 'add', 'token', ['description' => 'Entra']],
            'msg' => ['scim_token_added', 'Entra']]], $logged);
        $this->assertStringNotContainsString('secret-value', json_encode($logged));
        // the admin page shows every token added since it was last shown, also two with the same description
        $api->addToken(['description' => 'Entra']);
        $this->assertSame([3 => ['Entra', 'secret-value'], 4 => ['Entra', 'other-value']], $_SESSION['scim_new_tokens']);
        // an API key session is never shown a page: it does not keep the value
        $_SESSION = ['mailcow_cc_api' => true];
        $api->addToken([]);
        $this->assertArrayNotHasKey('scim_new_tokens', $_SESSION);
        $this->assertSame('api-value', $_SESSION['return'][0]['scim_token']);
    }

    public function testOneMessagePerId(): void
    {
        $service = $this->createMock(TokenService::class);
        $service->method('delete')->willReturnCallback(fn(int $id): Token => ($id === 3) ? Token::fromRow(['id' => 3]) : throw new AccessDenied());

        (new TokenApiController($service, new ApiResponse()))->deleteTokens(['3', '4', 'x']);
        $this->assertSame(['success', 'danger', 'danger'], array_column($_SESSION['return'], 'type'));
        $this->assertSame(['scim_token_deleted', '3'], $_SESSION['return'][0]['msg']);
    }
}
