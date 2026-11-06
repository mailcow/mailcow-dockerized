<?php

declare(strict_types=1);

namespace Mailcow\Tests\Archive\Smtp;

use Mailcow\Archive\Smtp\SmtpClient;
use Mailcow\Archive\Smtp\SmtpException;
use PHPUnit\Framework\TestCase;

/**
 * SMTP dialog against scripted server replies.
 */
class SmtpClientTest extends TestCase
{
    private static function client(array $replies): SmtpClient
    {
        return new class ($replies) extends SmtpClient {
            public array $written = [];
            public array $tls = [];
            public bool $closed = false;

            public function __construct(private array $replies)
            {
            }

            protected function open(string $host, int $port): void
            {
                $this->written[] = "OPEN $host:$port";
            }

            protected function close(): void
            {
                $this->closed = true;
            }

            protected function write(string $data): void
            {
                $this->written[] = $data;
            }

            protected function readLine(): ?string
            {
                return array_shift($this->replies);
            }

            protected function startTls(string $host, bool $verify): void
            {
                $this->tls[] = [$host, $verify];
            }
        };
    }

    public function testSubmitWithCrlfAndDotStuffing(): void
    {
        $client = self::client(['220 postfix ESMTP', '250-postfix', '250 8BITMIME', '250 2.1.0 Ok', '250 2.1.5 Ok',
            '354 End data with <CR><LF>.<CR><LF>', '250 2.0.0 Ok: queued as 4XyZ1']);

        $qid = $client->submit('postfix', 591, 'archive-bounce@localhost', 'journal@archive.example', "X-A: 1\r\n\r\n.dot\nline\r\n");

        $this->assertSame('4XyZ1', $qid);
        $this->assertSame(['OPEN postfix:591', 'EHLO ' . (gethostname() ?: 'mailcow') . "\r\n", "MAIL FROM:<archive-bounce@localhost>\r\n",
            "RCPT TO:<journal@archive.example>\r\n", "DATA\r\n", "X-A: 1\r\n\r\n..dot\r\nline\r\n.\r\n", "QUIT\r\n"], $client->written);
        $this->assertTrue($client->closed);
    }

    public function testProbeWithStartTlsAndAuth(): void
    {
        $client = self::client(['220 archive ESMTP', '250-archive', '250-STARTTLS', '250 AUTH PLAIN', '220 2.0.0 Ready',
            '250-archive', '250-8BITMIME', '250-SMTPUTF8', '250 AUTH PLAIN', '235 2.7.0 Authenticated', '250 Ok', '250 Ok', '250 Ok']);

        $extensions = $client->probe('archive.example', 25, 'verify', 'user', 'pw', 'archive-bounce@localhost', 'journal@archive.example');

        $this->assertSame(['8BITMIME', 'SMTPUTF8', 'AUTH'], $extensions);
        $this->assertSame([['archive.example', true]], $client->tls);
        $this->assertContains('AUTH PLAIN ' . base64_encode("\0user\0pw") . "\r\n", $client->written);
        $this->assertContains("RSET\r\n", $client->written);
    }

    public function testProbeNeedsStartTlsUnlessOptional(): void
    {
        try {
            self::client(['220 archive ESMTP', '250 archive'])->probe('archive.example', 25, 'encrypt', '', '', 'a@localhost', 'j@a.example');
            $this->fail('SmtpException expected');
        } catch (SmtpException $e) {
            $this->assertSame('tls: STARTTLS not offered', $e->describe());
        }
        $client = self::client(['220 archive ESMTP', '250 archive', '250 Ok', '250 Ok', '250 Ok']);
        $this->assertSame([], $client->probe('archive.example', 25, 'may', '', '', 'a@localhost', 'j@a.example'));
        $this->assertSame([], $client->tls);
    }

    public function testRefusalsNameTheStep(): void
    {
        $cases = [
            'connect' => ['421 4.3.2 Service not available'],
            'auth' => ['220 archive', '250-archive', '250 STARTTLS', '220 Go', '250 archive', '535 5.7.8 failed'],
            'recipient' => ['220 archive', '250-archive', '250 STARTTLS', '220 Go', '250 archive', '235 Ok', '250 Ok', '550 5.1.1 <j@a.example>: unknown'],
        ];
        foreach ($cases as $step => $replies) {
            try {
                self::client($replies)->probe('archive.example', 25, 'encrypt', 'user', 'pw', 'a@localhost', 'j@a.example');
                $this->fail("SmtpException at $step expected");
            } catch (SmtpException $e) {
                $this->assertSame($step, $e->step);
                $this->assertSame(end($replies), $e->reply);
            }
        }
    }

    public function testConnectionClosedDuringData(): void
    {
        $this->expectException(SmtpException::class);
        self::client(['220 postfix', '250 postfix', '250 Ok', '250 Ok', '354 go'])->submit('postfix', 591, 'a@localhost', 'j@a.example', "x\r\n");
    }

    public function testCrlfAndDotStuffing(): void
    {
        $this->assertSame("a\r\nb\r\nc\r\n", SmtpClient::crlf("a\nb\rc"));
        $this->assertSame("a\r\n", SmtpClient::crlf("a\r\n"));
        $this->assertSame("..a\r\n..\r\n", SmtpClient::dotStuff(".a\r\n.\r\n"));
    }
}
