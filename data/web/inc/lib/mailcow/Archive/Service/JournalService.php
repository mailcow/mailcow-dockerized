<?php

declare(strict_types=1);

namespace Mailcow\Archive\Service;

use Mailcow\Archive\Model\Journal;
use Mailcow\Archive\Model\Settings;
use Mailcow\Archive\Redis\ArchiveState;
use Mailcow\Archive\Repository\SettingsRepository;
use Mailcow\Archive\Smtp\SmtpClient;
use Mailcow\Archive\Smtp\SmtpException;
use Mailcow\Core\Context;
use Mailcow\Core\UserError;
use Throwable;

/**
 * Archive copies: a copy of every accepted mail of an archived mailbox goes by SMTP to the archive, through the
 * Postfix queue. rspamd sends the copies of mails it scanned (rspamd.local.lua) and asks prepare() whether to send
 * one and for its header block; mails that bypass rspamd (quarantine release, mass mail) are sent here.
 *
 * Copies have the envelope sender Settings::SENDER, Postfix drops every notice to it: an archive copy never causes
 * a bounce or delay warning to a real sender.
 */
class JournalService
{
    /** Postfix service that takes copies (no milter, mynetworks only) */
    private const SUBMIT_PORT = 591;

    public function __construct(
        private SettingsRepository $settings,
        private OwnerResolver $resolver,
        private HeaderBuilder $headers,
        private SmtpClient $smtp,
        private ArchiveState $state,
        private Context $context,
        private string $postfixHost = 'postfix'
    ) {
    }

    /**
     * Archive decision for a mail rspamd accepted (dynmap archive.php), no copy while archiving is off or no
     * archived mailbox is involved.
     *
     * @param array $request from, rcpt[], user, bcc, qid, received, sha256 (of the content rspamd sends)
     * @return array{archive: bool, rcpt?: string, sender?: string, headers?: string}
     */
    public function prepare(array $request): array
    {
        $settings = $this->settings->load();
        if (!$this->state->enabled() || !$settings->canDeliver()) {
            return ['archive' => false];
        }
        $from = self::text($request['from'] ?? '');
        $rcpts = is_array($request['rcpt'] ?? null) ? array_values(array_filter($request['rcpt'], 'is_string')) : [];
        $resolved = $this->resolver->resolve($from, $rcpts, self::text($request['user'] ?? ''), ($request['bcc'] ?? false) === true);
        if ($resolved['owners'] === []) {
            return ['archive' => false];
        }
        $sha256 = self::text($request['sha256'] ?? '');
        $journal = new Journal($resolved['owners'], $rcpts, $from, mb_strcut(self::text($request['qid'] ?? ''), 0, 64),
            $resolved['direction'], 'smtp', is_numeric($request['received'] ?? null) ? (float)$request['received'] : microtime(true),
            preg_match('/^[0-9a-f]{64}$/', $sha256) ? $sha256 : '');
        return ['archive' => true, 'rcpt' => $settings->address, 'sender' => Settings::SENDER, 'headers' => $this->headers->build($settings, $journal)];
    }

    /**
     * Sends the copy of a mail that reached its mailbox without rspamd (quarantine release, mass mail) for its
     * archived recipients. Errors are logged and counted, never thrown: the mail is delivered already.
     *
     * @param string[] $rcpts
     */
    public function submitDelivered(string $message, string $from, array $rcpts, string $source): bool
    {
        try {
            $settings = $this->settings->load();
            if (!$this->state->enabled() || !$settings->canDeliver()) {
                return false;
            }
            $resolved = $this->resolver->resolve($from, $rcpts, '', false);
            if ($resolved['owners'] === []) {
                return false;
            }
            $this->send($settings, $message, $resolved['owners'], array_values($rcpts), $from, 'in', $source);
            return true;
        } catch (Throwable $e) {
            error_log('ARCHIVE: cannot send the archive copy (' . $source . '): ' . $e->getMessage());
            $this->state->dropped('', $source . ': ' . $e->getMessage(), self::messageId($message));
            return false;
        }
    }

    /**
     * Sends a test copy through Postfix to the archive and returns the Postfix queue id.
     *
     * @throws UserError
     */
    public function testMail(): string
    {
        $this->context->requireAdmin();
        $settings = $this->settings->load();
        if (!$settings->canDeliver()) {
            throw new UserError('archive_incomplete');
        }
        $message = implode("\r\n", [
            'From: mailcow <' . Settings::SENDER . '>',
            'To: <' . $settings->address . '>',
            'Subject: mailcow archive test',
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@archive-test.mailcow>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
            '',
            'Test copy from mailcow (System - Configuration - Archive).',
            '',
        ]);
        try {
            return $this->send($settings, $message, [], [$settings->address], Settings::SENDER, 'in', 'test');
        } catch (SmtpException $e) {
            throw new UserError('archive_testmail_failed', [htmlspecialchars($e->describe())]);
        }
    }

    /** @throws SmtpException */
    private function send(Settings $settings, string $message, array $owners, array $rcpts, string $from, string $direction, string $source): string
    {
        $message = SmtpClient::crlf($message);
        $journal = new Journal($owners, $rcpts, $from, '', $direction, $source, microtime(true), hash('sha256', $message));
        return $this->smtp->submit($this->postfixHost, self::SUBMIT_PORT, Settings::SENDER, $settings->address,
            $this->headers->build($settings, $journal) . $message);
    }

    /** Message-ID of a mail, for the archive log */
    private static function messageId(string $message): string
    {
        $headers = explode("\n\n", str_replace("\r\n", "\n", $message), 2)[0];
        return preg_match('/^Message-ID:\s*<?([^>\s]+)>?/mi', $headers, $m) ? $m[1] : '';
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string)preg_replace('/[\x00-\x1f\x7f]+/', ' ', (string)$value)) : '';
    }
}
