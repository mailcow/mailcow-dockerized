<?php

declare(strict_types=1);

namespace Mailcow\Tests\Api;

/** What json_api.php does for every route: methods, read-only keys, request bodies, answers, unknown routes */
class RequestHandlingTest extends ApiTestCase
{
    public function testWritesNeedPost(): void
    {
        foreach (['add/alias', 'edit/alias', 'delete/alias'] as $route) {
            $result = $this->request('GET', $route, ['post' => ['attr' => '{}', 'items' => '[]']]);
            $this->assertApiError($result, 405, 'only POST method is allowed');
            $this->assertNotCalled($result, 'mailbox');
        }
    }

    public function testReadsNeedGet(): void
    {
        $this->assertApiError($this->post('get/domain/all'), 405, 'only GET method is allowed');
    }

    public function testSearchNeedsPost(): void
    {
        $this->assertApiError($this->get('search/domain'), 405, 'only POST method is allowed');
    }

    public function testReadOnlyKeysCannotWrite(): void
    {
        foreach (['add/alias' => ['address' => 'a@example.org'], 'edit/alias' => ['items' => [1], 'attr' => []], 'delete/alias' => [1]] as $route => $body) {
            $result = $this->post($route, $body, ['session' => self::API_RO]);
            $this->assertApiError($result, 403, 'API read/write access denied');
            $this->assertNotCalled($result, 'mailbox');
        }
    }

    public function testReadOnlyKeysCanRead(): void
    {
        $result = $this->get('get/alias/1', ['session' => self::API_RO]);

        $this->assertSame(200, $result->status);
        $this->assertCalled($result, 'mailbox', ['get', 'alias_details', '1']);
    }

    public function testWritesNeedALogin(): void
    {
        $this->assertApiError($this->post('add/alias', ['address' => 'a@example.org'], ['session' => self::NO_SESSION]), 403, 'API read/write access denied');
    }

    public function testReadsWithoutLoginAnswerNothing(): void
    {
        // a wrong API key ends in sessions.inc.php with 401; without key and session the read routes are skipped
        $result = $this->get('get/domain/all', ['session' => self::NO_SESSION]);

        $this->assertSame([200, ''], [$result->status, $result->body]);
        $this->assertNotCalled($result, 'mailbox');
    }

    public function testUnknownRoutes(): void
    {
        $this->assertApiError($this->get('get/no-such-thing'), 404, 'route not found');
        $this->assertApiError($this->post('add/no-such-thing', ['x' => 1]), 404, 'route not found');
        $this->assertApiError($this->post('edit/no-such-thing', ['items' => [], 'attr' => []]), 404, 'route not found');
        $this->assertApiError($this->post('delete/no-such-thing', [1]), 404, 'route not found');
        $this->assertApiError($this->get('nothing/at/all'), 404, 'route not found');
    }

    public function testInvalidJsonBody(): void
    {
        $this->assertApiError($this->post('add/alias', '{"address": '), 400, 'Request body doesn\'t contain valid json!');
    }

    public function testJsonBodies(): void
    {
        // add: the body is attr
        $add = $this->post('add/alias', ['address' => 'a@example.org', 'goto' => 'b@example.org']);
        $this->assertCalled($add, 'mailbox', ['add', 'alias', ['address' => 'a@example.org', 'goto' => 'b@example.org']]);

        // edit: {"items": ..., "attr": ...}
        $edit = $this->post('edit/alias', ['items' => [3, 4], 'attr' => ['active' => '0']]);
        $this->assertCalled($edit, 'mailbox', ['edit', 'alias', ['id' => [3, 4], 'active' => '0']]);

        // delete: the body is the list of items
        $delete = $this->post('delete/alias', [3, 4]);
        $this->assertCalled($delete, 'mailbox', ['delete', 'alias', ['id' => [3, 4]]]);
    }

    public function testFormBodiesOfTheUi(): void
    {
        $result = $this->postForm('edit/alias', ['items' => '[3]', 'attr' => '{"active":"1","csrf_token":"t"}'], ['session' => self::ADMIN]);

        $this->assertCalled($result, 'mailbox', ['edit', 'alias', ['id' => [3], 'active' => '1']]);
    }

    public function testMissingAttributes(): void
    {
        $this->assertSame(['type' => 'error', 'msg' => 'Cannot find attributes in post data'], $this->request('POST', 'add/alias')->json());
        $this->assertSame(['type' => 'error', 'msg' => 'Cannot find attributes in post data'], $this->request('POST', 'delete/alias')->json());
    }

    public function testWriteAnswers(): void
    {
        $messages = [self::message('success', ['alias_added', 'a@example.org'], ['mailbox', 'add', 'alias'])];

        $done = $this->post('add/alias', ['address' => 'a@example.org'], ['script' => [self::answer('mailbox', ['add', 'alias'], true, $messages)]]);
        $this->assertSame($messages, $done->json());

        $silent = $this->post('add/alias', ['address' => 'a@example.org'], ['script' => [self::answer('mailbox', ['add', 'alias'], true)]]);
        $this->assertSame(['type' => 'success', 'msg' => 'Task completed'], $silent->json());

        $failed = $this->post('add/alias', ['address' => 'a@example.org'], ['script' => [self::answer('mailbox', ['add', 'alias'], false)]]);
        $this->assertSame(['type' => 'error', 'msg' => 'Cannot add item'], $failed->json());
    }

    public function testReadAnswers(): void
    {
        $found = $this->get('get/alias/1', ['script' => [self::answer('mailbox', ['get', 'alias_details'], ['id' => 1, 'address' => 'a@example.org'])]]);
        $this->assertSame(['id' => 1, 'address' => 'a@example.org'], $found->json());

        $this->assertSame('{}', $this->get('get/alias/1')->body);
    }

    public function testRequestsFromABrowserPageAreRefused(): void
    {
        $result = $this->get('get/domain/all', ['server' => ['HTTP_SEC_FETCH_DEST' => 'document']]);

        $this->assertSame('', $result->body);
        $this->assertSame(['cors'], $result->callNames());
    }
}
