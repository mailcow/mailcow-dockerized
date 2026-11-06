<?php

declare(strict_types=1);

namespace Mailcow\Archive\Service;

use DateTime;
use Mailcow\Archive\Model\Journal;
use Mailcow\Archive\Model\Settings;

/**
 * Metadata header block in front of an archive copy, by profile:
 *
 * - archivecow: X-Archive-Owner, -Rcpt, -From, -Queue-ID, -Received, -Direction, -Source, -SHA256 and as the last
 *   line X-Archive-Signature: v=1; a=hmac-sha256; s=<hex>. The signature is HMAC-SHA256 with the signing key over
 *   the lines above it, each as "<lower case name>:<value without any whitespace>", joined by "\n".
 *   The block ends with the signature line, everything after it is the mail as accepted (CRLF line ends).
 * - envelope: SMTP sender and recipients under configurable names (default X-Envelope-From / X-Envelope-To).
 * - none: no headers.
 *
 * Empty values are left out. Lists are folded after commas (CRLF + space).
 */
class HeaderBuilder
{
    /** Fold a list before a line would get longer */
    private const FOLD_AT = 78;

    public function build(Settings $settings, Journal $journal): string
    {
        return match ($settings->profile) {
            'archivecow' => $this->archivecow($settings, $journal),
            'envelope' => $this->envelope($settings, $journal),
            default => '',
        };
    }

    /** One line of the signed text */
    public static function canonical(string $name, string $value): string
    {
        return strtolower($name) . ':' . preg_replace('/\s+/', '', $value);
    }

    private function archivecow(Settings $settings, Journal $journal): string
    {
        $values = [
            'X-Archive-Owner' => $journal->owners,
            'X-Archive-Rcpt' => $journal->rcpts,
            'X-Archive-From' => $journal->from,
            'X-Archive-Queue-ID' => $journal->qid,
            'X-Archive-Received' => self::time($journal->received),
            'X-Archive-Direction' => $journal->direction,
            'X-Archive-Source' => $journal->source,
            'X-Archive-SHA256' => $journal->sha256,
        ];
        $lines = [];
        $signed = [];
        foreach ($values as $name => $value) {
            $value = is_array($value) ? self::cleanList($value) : self::clean($value);
            if ($value === '' || $value === []) {
                continue;
            }
            $lines[] = self::line($name, $value);
            $signed[] = self::canonical($name, is_array($value) ? implode(',', $value) : $value);
        }
        $lines[] = 'X-Archive-Signature: v=1; a=hmac-sha256; s=' . hash_hmac('sha256', implode("\n", $signed), $settings->signingKey);
        return implode("\r\n", $lines) . "\r\n";
    }

    private function envelope(Settings $settings, Journal $journal): string
    {
        $from = self::clean($journal->from);
        $lines = [self::line($settings->headerFrom, ($from === '') ? '<>' : $from)];
        $rcpts = self::cleanList($journal->rcpts);
        if ($rcpts !== []) {
            $lines[] = self::line($settings->headerRcpt, $rcpts);
        }
        return implode("\r\n", $lines) . "\r\n";
    }

    /** Header line; a list is folded after commas */
    private static function line(string $name, string|array $value): string
    {
        if (is_string($value)) {
            return $name . ': ' . $value;
        }
        $out = $name . ': ' . array_shift($value);
        $length = strlen($out);
        foreach ($value as $item) {
            if ($length + strlen($item) + 1 > self::FOLD_AT) {
                $out .= ",\r\n " . $item;
                $length = strlen($item) + 1;
            } else {
                $out .= ',' . $item;
                $length += strlen($item) + 1;
            }
        }
        return $out;
    }

    /** Header value without control characters (no header injection) */
    private static function clean(string $value): string
    {
        return trim((string)preg_replace('/[\x00-\x1f\x7f]+/', ' ', $value));
    }

    /** @return string[] */
    private static function cleanList(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            $value = is_string($value) ? str_replace(',', '', self::clean($value)) : '';
            if ($value !== '') {
                $list[] = $value;
            }
        }
        return $list;
    }

    /** RFC 3339 in UTC with milliseconds */
    private static function time(float $time): string
    {
        $date = DateTime::createFromFormat('U.u', sprintf('%.3F', $time));
        return $date ? $date->format('Y-m-d\TH:i:s.v\Z') : '';
    }
}
