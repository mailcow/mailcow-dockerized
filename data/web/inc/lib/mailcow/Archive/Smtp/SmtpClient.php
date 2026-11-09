<?php

declare(strict_types=1);

namespace Mailcow\Archive\Smtp;

/**
 * Small SMTP client: hands archive copies to Postfix (port 591: no milter, mynetworks only) and tests the
 * connection to the archive server (EHLO, STARTTLS, AUTH, MAIL/RCPT, no mail is sent).
 *
 * The socket I/O sits in protected methods, so tests can replace it.
 */
class SmtpClient
{
    private const TIMEOUT = 20;

    /** @var resource|null */
    private $socket = null;

    /**
     * Sends one mail and returns the queue id Postfix reports. $data must end with CRLF; it is received as it is.
     *
     * @throws SmtpException
     */
    public function submit(string $host, int $port, string $from, string $rcpt, string $data): string
    {
        $this->open($host, $port);
        try {
            $this->expect('connect', 220);
            $this->ehlo();
            $this->command('sender', 'MAIL FROM:<' . $from . '>', 250);
            $this->command('recipient', 'RCPT TO:<' . $rcpt . '>', 250);
            $this->command('data', 'DATA', 354);
            $this->write(self::dotStuff(self::crlf($data)) . ".\r\n");
            $reply = $this->expect('data', 250);
            $this->write("QUIT\r\n");
            return preg_match('/queued as ([0-9A-Za-z]+)/', $reply, $m) ? $m[1] : '';
        } finally {
            $this->close();
        }
    }

    /**
     * Connection test against the archive server: STARTTLS (unless $tls is 'may' and it is not offered), AUTH when
     * a username is set, then MAIL and RCPT for the archive address, then RSET.
     *
     * @return string[] extensions the server announced (after STARTTLS)
     * @throws SmtpException
     */
    public function probe(string $host, int $port, string $tls, string $username, string $password, string $from, string $rcpt): array
    {
        $this->open($host, $port);
        try {
            $this->expect('connect', 220);
            $extensions = $this->ehlo();
            if ($tls !== 'may' || in_array('STARTTLS', $extensions, true)) {
                if (!in_array('STARTTLS', $extensions, true)) {
                    throw new SmtpException('tls', 'STARTTLS not offered');
                }
                $this->command('tls', 'STARTTLS', 220);
                $this->startTls($host, $tls === 'verify');
                $extensions = $this->ehlo();
            }
            if ($username !== '') {
                $this->command('auth', 'AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password), 235);
            }
            $this->command('sender', 'MAIL FROM:<' . $from . '>', 250);
            $this->command('recipient', 'RCPT TO:<' . $rcpt . '>', 250);
            $this->command('recipient', 'RSET', 250);
            $this->write("QUIT\r\n");
            return $extensions;
        } finally {
            $this->close();
        }
    }

    /** Mail server of a domain: the MX with the lowest preference, else the domain itself */
    public function mailHost(string $domain): string
    {
        if (@getmxrr($domain, $hosts, $weights) && !empty($hosts)) {
            array_multisort($weights, SORT_NUMERIC, $hosts);
            return rtrim($hosts[0], '.');
        }
        return $domain;
    }

    /** CRLF line ends, ending with CRLF */
    public static function crlf(string $data): string
    {
        $data = (string)preg_replace('/\r\n|\r|\n/', "\r\n", $data);
        return str_ends_with($data, "\r\n") ? $data : $data . "\r\n";
    }

    /** RFC 5321 4.5.2: a line starting with "." gets another "." */
    public static function dotStuff(string $data): string
    {
        $data = str_replace("\r\n.", "\r\n..", $data);
        return str_starts_with($data, '.') ? '.' . $data : $data;
    }

    /** @throws SmtpException */
    protected function open(string $host, int $port): void
    {
        $target = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $host . ']' : $host;
        $socket = @stream_socket_client('tcp://' . $target . ':' . $port, $errno, $error, self::TIMEOUT);
        if ($socket === false) {
            throw new SmtpException('connect', $error !== '' ? $error : 'connection failed');
        }
        stream_set_timeout($socket, self::TIMEOUT);
        $this->socket = $socket;
    }

    protected function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    /** @throws SmtpException */
    protected function write(string $data): void
    {
        for ($written = 0; $written < strlen($data); $written += $count) {
            $count = @fwrite($this->socket, substr($data, $written, 1048576));
            if ($count === false || $count === 0) {
                throw new SmtpException('data', 'connection lost while sending');
            }
        }
    }

    /** One reply line without CRLF, null when the connection ended */
    protected function readLine(): ?string
    {
        $line = fgets($this->socket, 4096);
        return ($line === false) ? null : rtrim($line, "\r\n");
    }

    /** @throws SmtpException */
    protected function startTls(string $host, bool $verify): void
    {
        stream_context_set_option($this->socket, 'ssl', 'verify_peer', $verify);
        stream_context_set_option($this->socket, 'ssl', 'verify_peer_name', $verify);
        stream_context_set_option($this->socket, 'ssl', 'allow_self_signed', !$verify);
        stream_context_set_option($this->socket, 'ssl', 'peer_name', $host);
        if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new SmtpException('tls', $verify ? 'TLS handshake failed or certificate not valid' : 'TLS handshake failed');
        }
    }

    /** @return string[] extensions in upper case */
    private function ehlo(): array
    {
        $reply = $this->command('connect', 'EHLO ' . (gethostname() ?: 'mailcow'), 250);
        $extensions = [];
        foreach (array_slice(explode("\n", $reply), 1) as $line) {
            $extensions[] = strtoupper((string)strtok(substr($line, 4), ' '));
        }
        return $extensions;
    }

    /** @throws SmtpException */
    private function command(string $step, string $command, int $code): string
    {
        $this->write($command . "\r\n");
        return $this->expect($step, $code);
    }

    /**
     * Reads a (multi-line) reply and returns it, lines joined by "\n".
     *
     * @throws SmtpException when the code differs
     */
    private function expect(string $step, int $code): string
    {
        $lines = [];
        do {
            $line = $this->readLine();
            if ($line === null) {
                throw new SmtpException($step, 'connection closed');
            }
            $lines[] = $line;
        } while (strlen($line) > 3 && $line[3] === '-');
        $reply = implode("\n", $lines);
        if ((int)substr($reply, 0, 3) !== $code && !($code === 250 && (int)substr($reply, 0, 3) === 251)) {
            throw new SmtpException($step, $reply);
        }
        return $reply;
    }
}
