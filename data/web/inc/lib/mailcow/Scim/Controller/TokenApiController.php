<?php

declare(strict_types=1);

namespace Mailcow\Scim\Controller;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\ApiResponse;
use Mailcow\Core\UserError;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Service\TokenService;
use PDOException;

/**
 * HTTP API adapter for json_api.php and edit.php (routes `scim-token`).
 *
 * Write methods return what json_api passes to process_*_return() and write their messages via ApiResponse.
 * Messages are logged as ['scim', action, 'token', data]. The value of a new token is never part of a message
 * (messages reach the log table): API callers get it in the `scim_token` key of the success entry, which
 * logger() does not store; for the admin page it is kept in $_SESSION['scim_new_tokens'] (id => [description,
 * value]) until the page shows it once. API key sessions are never shown a page, they do not keep it.
 */
class TokenApiController
{
    public function __construct(private TokenService $tokens, private ApiResponse $response)
    {
    }

    /** POST add/scim-token */
    public function addToken(array $attr): bool
    {
        $value = null;
        $result = $this->handle('add', $attr, function (array $log) use ($attr, &$value): bool {
            [$token, $value] = $this->tokens->create($attr);
            if (empty($_SESSION['mailcow_cc_api'])) {
                // keyed by id: two tokens with the same description are both shown; Twig escapes the description
                $_SESSION['scim_new_tokens'][$token->id] = [($token->description !== '') ? $token->description : (string)$token->id, $value];
            }
            return $this->response->success(['scim_token_added', self::label($token)], $log);
        });
        if ($value !== null) {
            $_SESSION['return'][array_key_last($_SESSION['return'])]['scim_token'] = $value;
        }
        return $result;
    }

    /** POST edit/scim-token: one message per id */
    public function editTokens(?array $items, array $attr): bool
    {
        $data = array_merge(['id' => $items], $attr);
        return $this->handle('edit', $data, function (array $log) use ($items, $attr): bool {
            $this->tokens->requireAdmin();
            return $this->batch((array)$items, $log, fn(int $id): array => ['scim_token_modified', self::label($this->tokens->update($id, $attr))]);
        });
    }

    /** POST delete/scim-token: one message per id */
    public function deleteTokens(array $items): bool
    {
        return $this->handle('delete', ['id' => $items], function (array $log) use ($items): bool {
            $this->tokens->requireAdmin();
            return $this->batch($items, $log, fn(int $id): array => ['scim_token_deleted', self::label($this->tokens->delete($id))]);
        });
    }

    /** GET scim-token/all */
    public function getTokens(): array
    {
        return array_map(fn(Token $token): array => $token->toArray(), $this->tokens->all());
    }

    /** GET scim-token/<id>, also edit.php */
    public function getToken(mixed $id): array
    {
        $token = is_numeric($id) ? $this->tokens->get((int)$id) : null;
        return ($token === null) ? [] : $token->toArray();
    }

    private static function label(Token $token): string
    {
        return htmlspecialchars($token->description !== '' ? $token->description : (string)$token->id);
    }

    /** Runs one write call; a UserError becomes a danger message, a database error one `mysql_error` message */
    private function handle(string $action, mixed $data, callable $work): bool
    {
        $log = ['scim', $action, 'token', $data];
        try {
            $result = $work($log);
        } catch (UserError $e) {
            $result = $this->response->error($e, $log);
        } catch (PDOException $e) {
            $result = $this->response->danger(['mysql_error', $e->getMessage()], $log);
        }
        $this->response->flush();
        return $result;
    }

    /** One message per id: the success message returned by $work(id), or the UserError */
    private function batch(array $ids, array $log, callable $work): bool
    {
        foreach ($ids as $id) {
            try {
                $this->response->success($work(is_numeric($id) ? (int)$id : throw new AccessDenied()), $log);
            } catch (UserError $e) {
                $this->response->error($e, $log);
            }
        }
        return true;
    }
}
