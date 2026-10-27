<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Http;

/**
 * HTTPS requests to OAuth provider endpoints with an address policy against SSRF (C5).
 *
 * Every endpoint must be https://, without userinfo, with a valid hostname. In strict mode (endpoints of
 * sources not created by an admin) every resolved address must be public; curl is then pinned to the checked
 * addresses (CURLOPT_RESOLVE), so a DNS change between check and request does not help an attacker.
 *
 * resolve() and send() are the only I/O and protected, so tests can replace them.
 */
class OAuthClient
{
    private const MAX_URL_LENGTH = 500;
    private const MAX_ERROR_LENGTH = 200;

    public function isAllowed(string $url, bool $strict): bool
    {
        return $this->pin($url, $strict) !== null;
    }

    /**
     * Sends one request (POST form when $post is given, otherwise GET) and returns the decoded JSON object.
     *
     * @throws OAuthException with a sanitised message
     */
    public function request(string $url, bool $strict, ?array $post = null, array $headers = []): array
    {
        $pin = $this->pin($url, $strict);
        if ($pin === null) {
            throw new OAuthException('endpoint not allowed');
        }
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_MAXFILESIZE => 1048576,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ];
        if (!empty($pin)) {
            $options[CURLOPT_RESOLVE] = $pin;
        }
        if ($post !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($post);
        }
        [$body, $code] = $this->send($options);
        if ($body === false) {
            throw new OAuthException('connection failed');
        }
        $data = json_decode($body, true);
        $ok = ($code >= 200 && $code < 300);
        if ($ok && is_array($data)) {
            return $data;
        }
        throw new OAuthException($this->errorText($code, $ok, $data));
    }

    /**
     * IPv4 and IPv6 addresses of a host.
     *
     * @return string[]
     */
    protected function resolve(string $host): array
    {
        $ips = @gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ipv6'] ?? '';
        }
        return $ips;
    }

    /**
     * Runs curl with the given options.
     *
     * @return array{0: string|false, 1: int} response body (false on connection errors) and HTTP status
     */
    protected function send(array $options): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [is_string($body) ? $body : false, $code];
    }

    /**
     * Applies the endpoint policy. Returns null when the URL is not allowed, otherwise the CURLOPT_RESOLVE
     * pin (empty when not strict).
     */
    private function pin(string $url, bool $strict): ?array
    {
        if (strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        if (!$strict) {
            return [];
        }
        $ips = array_values(array_unique($this->resolve($host)));
        if (empty($ips)) {
            return null;
        }
        $pinned = [];
        foreach ($ips as $ip) {
            if (!$this->isPublic($ip)) {
                return null;
            }
            $pinned[] = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $ip . ']' : $ip;
        }
        return [$host . ':' . (int)($parts['port'] ?? 443) . ':' . implode(',', $pinned)];
    }

    /**
     * Public unicast address only: no private, loopback, link-local or reserved ranges, no CGNAT (100.64/10),
     * benchmarking (198.18/15) or multicast/reserved (224/3); IPv6 only global unicast 2000::/3 without
     * 6to4 (2002::/16), Teredo (2001::/32) and documentation (2001:db8::/32).
     */
    private function isPublic(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            return ($long & 0xFFC00000) != 0x64400000
                && ($long & 0xFFFE0000) != 0xC6120000
                && ($long & 0xE0000000) != 0xE0000000;
        }
        $hex = bin2hex((string)inet_pton($ip));
        return ($hex[0] === '2' || $hex[0] === '3')
            && substr($hex, 0, 4) !== '2002'
            && !in_array(substr($hex, 0, 8), ['20010000', '20010db8'], true);
    }

    /**
     * Error text that is safe to store: "HTTP <code>" plus the provider's error / error_description.
     */
    private function errorText(int $code, bool $ok, mixed $data): string
    {
        $text = 'HTTP ' . $code . ($ok ? ': malformed response' : '');
        $detail = [];
        foreach (['error', 'error_description'] as $key) {
            if (is_array($data) && isset($data[$key]) && is_scalar($data[$key]) && (string)$data[$key] !== '') {
                $detail[] = (string)$data[$key];
            }
        }
        if (!empty($detail)) {
            $text .= ': ' . implode(' - ', $detail);
        }
        return mb_substr(trim((string)preg_replace('/[\x00-\x1f\x7f]+/', ' ', strip_tags($text))), 0, self::MAX_ERROR_LENGTH);
    }
}
