<?php

declare(strict_types=1);

namespace Mailcow\MailboxGroup\Legacy;

/**
 * Bridge to the procedural mailbox code. The global calls sit in protected methods so tests can replace them.
 */
class LegacyMailbox
{
    /**
     * Applies a mailbox template like the IdP code does: as system caller, with the current name, so only the
     * template attributes change.
     *
     * @return ?string null on success, else the message key of the legacy error
     */
    public function applyTemplate(string $username, string $name, string $template): ?string
    {
        return $this->asSystem(fn(): mixed => $this->callTemplate($username, $name, $template));
    }

    /**
     * Runs a legacy call as system caller (access_all_exception); the caller's session messages are kept.
     *
     * @return ?string null unless the call returned false, else the message key of the legacy error
     */
    protected function asSystem(callable $call): ?string
    {
        $hasReturn = array_key_exists('return', $_SESSION ?? []);
        $return = $_SESSION['return'] ?? null;
        $accessAll = $_SESSION['access_all_exception'] ?? null;
        $_SESSION['return'] = [];
        $_SESSION['access_all_exception'] = '1';
        try {
            if ($call() !== false) {
                return null;
            }
            foreach ($_SESSION['return'] as $message) {
                if (($message['type'] ?? '') === 'danger') {
                    return (string)(is_array($message['msg']) ? $message['msg'][0] : $message['msg']);
                }
            }
            return 'generic_server_error';
        } finally {
            if ($hasReturn) {
                $_SESSION['return'] = $return;
            } else {
                unset($_SESSION['return']);
            }
            $_SESSION['access_all_exception'] = $accessAll ?? '0';
        }
    }

    protected function callTemplate(string $username, string $name, string $template): mixed
    {
        return mailbox('edit', 'mailbox_from_template', ['username' => $username, 'name' => $name, 'template' => $template]);
    }
}
