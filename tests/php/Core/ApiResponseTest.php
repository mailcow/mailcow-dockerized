<?php

declare(strict_types=1);

namespace Mailcow\Tests\Core;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testMessagesUseTheMailcowFormat(): void
    {
        $response = new ApiResponse();

        $this->assertTrue($response->success(['mailbox_modified', 'a@example.org'], ['syncjob', 'add', 'job', []]));
        $this->assertFalse($response->danger('access_denied', ['syncjob', 'edit', 'job', []]));

        $this->assertSame([
            ['type' => 'success', 'log' => ['syncjob', 'add', 'job', []], 'msg' => ['mailbox_modified', 'a@example.org']],
            ['type' => 'danger', 'log' => ['syncjob', 'edit', 'job', []], 'msg' => 'access_denied'],
        ], $response->messages());
    }

    public function testWarningsKeepTheCallSuccessful(): void
    {
        $response = new ApiResponse();

        $response->warning(['group_template_failed', 'Staff', 'a@example.org'], ['mailbox_group', 'edit', 'group', []]);

        $this->assertSame([
            ['type' => 'warning', 'log' => ['mailbox_group', 'edit', 'group', []], 'msg' => ['group_template_failed', 'Staff', 'a@example.org']],
        ], $response->messages());
    }

    public function testSecretsAreMaskedInArrayLogParts(): void
    {
        $response = new ApiResponse(['password1', 'oauth_client_secret']);

        $response->success('ok', ['syncjob', 'add', 'job', [
            'password1' => 'secret',
            'oauth_client_secret' => '',
            'user1' => 'remote',
            'nested' => ['password1' => 'kept'],
        ]]);

        $this->assertSame(['syncjob', 'add', 'job', [
            'password1' => '*',
            'oauth_client_secret' => '',
            'user1' => 'remote',
            'nested' => ['password1' => 'kept'],
        ]], $response->messages()[0]['log']);
    }

    public function testNullSecretStaysNull(): void
    {
        $response = new ApiResponse(['password1']);

        $response->danger('x', ['syncjob', 'add', 'job', ['password1' => null]]);

        $this->assertSame(['password1' => null], $response->messages()[0]['log'][3]);
    }

    public function testUserErrorBecomesDangerMessage(): void
    {
        $response = new ApiResponse();

        $this->assertFalse($response->error(new UserError('object_exists', ['name']), ['l']));
        $response->error(new AccessDenied(), ['l']);

        $this->assertSame(['object_exists', 'name'], $response->messages()[0]['msg']);
        $this->assertSame('danger', $response->messages()[0]['type']);
        $this->assertSame('access_denied', $response->messages()[1]['msg']);
    }

    public function testFlushAppendsToSessionAndStartsOver(): void
    {
        $_SESSION['return'] = [['type' => 'success', 'log' => [], 'msg' => 'earlier']];
        $response = new ApiResponse();
        $response->success('first', ['l']);
        $response->danger('second', ['l']);

        $response->flush();
        $response->flush();

        $this->assertSame(['earlier', 'first', 'second'], array_column($_SESSION['return'], 'msg'));
        $this->assertSame([], $response->messages());
    }

    public function testFlushWithoutMessagesLeavesSessionUntouched(): void
    {
        (new ApiResponse())->flush();

        $this->assertArrayNotHasKey('return', $_SESSION);
    }
}
