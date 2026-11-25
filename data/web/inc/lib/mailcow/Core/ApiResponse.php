<?php

declare(strict_types=1);

namespace Mailcow\Core;

/**
 * Collects the messages of one API call and writes them to $_SESSION['return'] in the mailcow format
 * (`type`, `log`, `msg`), which json_api.php and the UI read. This is the only class that touches the session.
 *
 * Values of the configured secret keys are replaced by '*' inside every array of the `log` part,
 * so passwords or client secrets never reach the mailcow log or an API response.
 */
class ApiResponse
{
    private array $messages = [];

    public function __construct(private array $secretKeys = [])
    {
    }

    /** Returns true, so callers can `return $response->success(...)` */
    public function success(string|array $msg, array $log): bool
    {
        $this->add('success', $msg, $log);
        return true;
    }

    /** Returns false, so callers can `return $response->danger(...)` */
    public function danger(string|array $msg, array $log): bool
    {
        $this->add('danger', $msg, $log);
        return false;
    }

    public function error(UserError $error, array $log): bool
    {
        return $this->danger($error->msg(), $log);
    }

    /** Something went partly wrong, but the call itself succeeded */
    public function warning(string|array $msg, array $log): void
    {
        $this->add('warning', $msg, $log);
    }

    /** Messages collected since the last flush() */
    public function messages(): array
    {
        return $this->messages;
    }

    /** Appends the collected messages to $_SESSION['return'] and starts over */
    public function flush(): void
    {
        foreach ($this->messages as $message) {
            $_SESSION['return'][] = $message;
        }
        $this->messages = [];
    }

    private function add(string $type, string|array $msg, array $log): void
    {
        foreach ($log as $i => $part) {
            if (!is_array($part)) {
                continue;
            }
            foreach ($this->secretKeys as $key) {
                if (isset($part[$key]) && $part[$key] !== '') {
                    $log[$i][$key] = '*';
                }
            }
        }
        $this->messages[] = ['type' => $type, 'log' => $log, 'msg' => $msg];
    }
}
