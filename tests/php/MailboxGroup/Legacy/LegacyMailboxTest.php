<?php

declare(strict_types=1);

namespace Mailcow\Tests\MailboxGroup\Legacy;

use Mailcow\MailboxGroup\Legacy\LegacyMailbox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LegacyMailboxTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['return' => [['type' => 'success', 'msg' => 'caller']], 'access_all_exception' => '0'];
    }

    /** Replaces the global calls: $result is returned, $messages are written like the legacy code does */
    private static function legacy(mixed $result, array $messages = [], array &$calls = []): LegacyMailbox
    {
        return new class($result, $messages, $calls) extends LegacyMailbox {
            private array $calls;

            public function __construct(private mixed $result, private array $messages, array &$calls)
            {
                $this->calls = &$calls;
            }

            protected function callTemplate(string $username, string $name, string $template): mixed
            {
                $this->calls[] = [$username, $name, $template, $_SESSION['access_all_exception']];
                if ($this->result instanceof RuntimeException) {
                    throw $this->result;
                }
                $_SESSION['return'] = array_merge($_SESSION['return'], $this->messages);
                return $this->result;
            }
        };
    }

    public function testTemplatesAreAppliedAsSystemAndTheSessionIsRestored(): void
    {
        $calls = [];

        $this->assertNull(self::legacy(true, [['type' => 'success', 'msg' => 'mailbox_modified']], $calls)->applyTemplate('a@example.org', 'A', 'Staff'));

        $this->assertSame([['a@example.org', 'A', 'Staff', '1']], $calls);
        $this->assertSame(['return' => [['type' => 'success', 'msg' => 'caller']], 'access_all_exception' => '0'], $_SESSION);
    }

    public function testTheFirstLegacyErrorIsReturned(): void
    {
        $error = self::legacy(false, [['type' => 'success', 'msg' => 'x'], ['type' => 'danger', 'msg' => ['mailbox_quota_exceeded', 1]]])
            ->applyTemplate('a@example.org', 'A', 'Staff');

        $this->assertSame('mailbox_quota_exceeded', $error);
        $this->assertSame('0', $_SESSION['access_all_exception']);
    }

    public function testTheSessionIsRestoredOnExceptions(): void
    {
        try {
            self::legacy(new RuntimeException('boom'))->applyTemplate('a@example.org', 'A', 'Staff');
            $this->fail('exception expected');
        } catch (RuntimeException) {
        }

        $this->assertSame(['return' => [['type' => 'success', 'msg' => 'caller']], 'access_all_exception' => '0'], $_SESSION);
    }
}
