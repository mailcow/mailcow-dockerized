<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Service;

use Mailcow\Archive\Model\Journal;
use Mailcow\Archive\Model\Settings;
use Mailcow\Archive\Service\HeaderBuilder;
use PHPUnit\Framework\TestCase;

class HeaderBuilderTest extends TestCase
{
    private static function settings(string $profile): Settings
    {
        $settings = new Settings();
        $settings->profile = $profile;
        $settings->signingKey = 'key';
        return $settings;
    }

    private static function journal(array $rcpts = ['anna@firma.de', 'bcc@extern.example'], string $from = 'x@extern.example'): Journal
    {
        return new Journal(['anna@firma.de'], $rcpts, $from, '4XyZ1', 'internal', 'smtp', 1700000000.25, str_repeat('a', 64));
    }

    public function testArchivecowHeadersAndSignature(): void
    {
        $block = (new HeaderBuilder())->build(self::settings('archivecow'), self::journal());

        $this->assertSame("X-Archive-Owner: anna@firma.de\r\nX-Archive-Rcpt: anna@firma.de,bcc@extern.example\r\n"
            . "X-Archive-From: x@extern.example\r\nX-Archive-Queue-ID: 4XyZ1\r\nX-Archive-Received: 2023-11-14T22:13:20.250Z\r\n"
            . "X-Archive-Direction: internal\r\nX-Archive-Source: smtp\r\nX-Archive-SHA256: " . str_repeat('a', 64) . "\r\n",
            substr($block, 0, strpos($block, 'X-Archive-Signature:')));
        $signed = implode("\n", ['x-archive-owner:anna@firma.de', 'x-archive-rcpt:anna@firma.de,bcc@extern.example',
            'x-archive-from:x@extern.example', 'x-archive-queue-id:4XyZ1', 'x-archive-received:2023-11-14T22:13:20.250Z',
            'x-archive-direction:internal', 'x-archive-source:smtp', 'x-archive-sha256:' . str_repeat('a', 64)]);
        $this->assertStringEndsWith("X-Archive-Signature: v=1; a=hmac-sha256; s=" . hash_hmac('sha256', $signed, 'key') . "\r\n", $block);
    }

    /** Fixed vector shared with archivecow: if this changes, every archive that verifies the signature refuses the copies */
    public function testWireFormatIsStable(): void
    {
        $message = "From: sender@example.com\r\nTo: anna.schmidt@example.org\r\nSubject: Test\r\nMessage-ID: <1@example.com>\r\n\r\nHallo.\r\n";
        $settings = self::settings('archivecow');
        $settings->signingKey = 'test-signing-key';
        $journal = new Journal(['anna.schmidt@example.org', 'buchhaltung@example.org'],
            ['anna.schmidt@example.org', 'buchhaltung@example.org', 'extern.partner@example.net'],
            'sender@example.com', '4ZxYw1abcDz9', 'in', 'smtp', 1793983297.25, hash('sha256', $message));

        $this->assertSame('0042f6cd8a846e5ce8f4a57c97121324c1213380fc204736d15ffe5693a9ef5c', hash('sha256', $message));
        $this->assertSame("X-Archive-Owner: anna.schmidt@example.org,buchhaltung@example.org\r\n"
            . "X-Archive-Rcpt: anna.schmidt@example.org,buchhaltung@example.org,\r\n extern.partner@example.net\r\n"
            . "X-Archive-From: sender@example.com\r\n"
            . "X-Archive-Queue-ID: 4ZxYw1abcDz9\r\n"
            . "X-Archive-Received: 2026-11-06T16:41:37.250Z\r\n"
            . "X-Archive-Direction: in\r\n"
            . "X-Archive-Source: smtp\r\n"
            . "X-Archive-SHA256: 0042f6cd8a846e5ce8f4a57c97121324c1213380fc204736d15ffe5693a9ef5c\r\n"
            . "X-Archive-Signature: v=1; a=hmac-sha256; s=443c927e165fd1f418fdfde2be54645d3201ef3b17d9e74ed3569d937fcf84d1\r\n",
            (new HeaderBuilder())->build($settings, $journal));
    }

    public function testLongListsAreFoldedAndSignedUnfolded(): void
    {
        $rcpts = array_map(fn(int $i): string => sprintf('recipient%04d@extern.example', $i), range(1, 200));
        $block = (new HeaderBuilder())->build(self::settings('archivecow'), self::journal($rcpts));

        foreach (explode("\r\n", $block) as $line) {
            $this->assertLessThanOrEqual(78 + 30, strlen($line));
        }
        preg_match('/^X-Archive-Rcpt: (.*?)\r\n(?=\S)/ms', $block, $m);
        $this->assertSame($rcpts, explode(',', preg_replace('/\r\n /', '', $m[1])));
        // the signature covers the values without whitespace, so folding does not matter
        $this->assertStringContainsString(hash_hmac('sha256', implode("\n", [
            'x-archive-owner:anna@firma.de', 'x-archive-rcpt:' . implode(',', $rcpts), 'x-archive-from:x@extern.example',
            'x-archive-queue-id:4XyZ1', 'x-archive-received:2023-11-14T22:13:20.250Z', 'x-archive-direction:internal',
            'x-archive-source:smtp', 'x-archive-sha256:' . str_repeat('a', 64)]), 'key'), $block);
    }

    public function testEmptyValuesAreLeftOutAndControlCharactersRemoved(): void
    {
        $journal = new Journal(["anna@firma.de\r\nX-Evil: 1"], ['a@b.example', ''], '', '', 'in', 'quarantine', 1700000000.0, '');
        $block = (new HeaderBuilder())->build(self::settings('archivecow'), $journal);

        $this->assertStringNotContainsString("\r\nX-Evil", $block);
        $this->assertStringNotContainsString('X-Archive-From', $block);
        $this->assertStringNotContainsString('X-Archive-Queue-ID', $block);
        $this->assertStringNotContainsString('X-Archive-SHA256', $block);
        $this->assertStringContainsString("X-Archive-Owner: anna@firma.de X-Evil: 1\r\n", $block);
        $this->assertStringContainsString("X-Archive-Rcpt: a@b.example\r\n", $block);
    }

    public function testEnvelopeAndNoneProfiles(): void
    {
        $settings = self::settings('envelope');
        $settings->headerFrom = 'X-Real-MailFrom';
        $settings->headerRcpt = 'X-Real-RcptTo';
        $this->assertSame("X-Real-MailFrom: x@extern.example\r\nX-Real-RcptTo: anna@firma.de,bcc@extern.example\r\n",
            (new HeaderBuilder())->build($settings, self::journal()));
        $this->assertSame("X-Real-MailFrom: <>\r\nX-Real-RcptTo: anna@firma.de,bcc@extern.example\r\n",
            (new HeaderBuilder())->build($settings, self::journal(from: '')));
        $this->assertSame('', (new HeaderBuilder())->build(self::settings('none'), self::journal()));
    }
}
