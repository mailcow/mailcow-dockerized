<?php

declare(strict_types=1);

namespace Mailcow\Scim\Service;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Mailcow\Scim\Model\Token;
use Mailcow\Scim\Repository\TokenRepository;
use Mailcow\Scim\ScimError;

/**
 * SCIM tokens: managed by admins (not with a read-only API key), used by IdPs as Bearer token.
 */
class TokenService
{
    public function __construct(private TokenRepository $repo, private Context $context)
    {
    }

    /** @throws AccessDenied unless an admin (login or read-write API key) */
    public function requireAdmin(): void
    {
        $this->context->requireAdmin();
    }

    /** @return Token[] all tokens; [] for other callers */
    public function all(): array
    {
        return $this->canRead() ? $this->repo->all() : [];
    }

    public function get(int $id): ?Token
    {
        return $this->canRead() ? $this->repo->find($id) : null;
    }

    /**
     * Creates a token with a random value.
     *
     * @return array{0: Token, 1: string} the token and its value, which is not stored
     * @throws UserError
     */
    public function create(array $input): array
    {
        $this->requireAdmin();
        $token = new Token();
        $this->apply($token, $input);
        $value = bin2hex(random_bytes(32));
        $token->hash = hash('sha256', $value);
        $this->repo->insert($token);
        return [$token, $value];
    }

    /**
     * Updates a token; attributes missing in $input keep their value.
     *
     * @throws UserError
     */
    public function update(int $id, array $input): Token
    {
        $this->requireAdmin();
        $token = $this->repo->find($id) ?? throw new AccessDenied();
        $this->apply($token, $input);
        $this->repo->update($token);
        return $token;
    }

    /** @throws UserError */
    public function delete(int $id): Token
    {
        $this->requireAdmin();
        $token = $this->repo->find($id) ?? throw new AccessDenied();
        $this->repo->delete($id);
        return $token;
    }

    /**
     * The active token of an `Authorization: Bearer` header, used from an allowed IP.
     *
     * @throws ScimError 401 with a Bearer challenge (RFC 6750 3)
     */
    public function authenticate(string $authorization, string $ip): Token
    {
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $m)) {
            throw new ScimError(401, 'Bearer token required', null, ['WWW-Authenticate: Bearer realm="mailcow SCIM"']);
        }
        $token = $this->repo->findActiveByHash(hash('sha256', $m[1]));
        if ($token === null || ($token->allowFrom !== [] && !$this->ipAllowed($ip, $token->allowFrom))) {
            throw new ScimError(401, ($token === null) ? 'Invalid or inactive token' : 'IP address not allowed for this token',
                null, ['WWW-Authenticate: Bearer realm="mailcow SCIM", error="invalid_token"']);
        }
        return $token;
    }

    protected function ipAllowed(string $ip, array $networks): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false && ip_acl($ip, $networks);
    }

    private function canRead(): bool
    {
        return $this->context->hasAdminRights() && !$this->context->isReadOnlyApi();
    }

    /**
     * Add/edit rules: attributes missing in $input keep their value, a new token starts with the model defaults.
     *
     * @throws UserError
     */
    private function apply(Token $token, array $input): void
    {
        if (isset($input['description'])) {
            $description = Input::text($input['description']);
            if ($description === null || mb_strlen($description) > 255) {
                throw new UserError('scim_token_description_invalid');
            }
            $token->description = $description;
        }
        foreach (['domain_restriction' => 'domainRestriction', 'group_domain' => 'groupDomain'] as $key => $property) {
            // an unchanged value is kept without check: its domain may have been deleted since, and an edit of
            // another field must neither fail nor widen the token
            if (array_key_exists($key, $input) && !(is_string($input[$key]) && $token->$property !== null
                && strcasecmp(trim($input[$key]), $token->$property) === 0)) {
                $token->$property = $this->domain($input[$key]);
            }
        }
        if (isset($input['allow_from'])) {
            $token->allowFrom = self::networks($input['allow_from']);
        }
        if (array_key_exists('default_template', $input)) {
            $token->defaultTemplate = (Input::text($input['default_template']) ?: null);
        }
        if (isset($input['mappers']) || isset($input['templates'])) {
            [$mappers, $templates] = [self::listOf($input['mappers'] ?? []), self::listOf($input['templates'] ?? [])];
            $token->mappers = $token->templates = [];
            foreach ($mappers as $i => $mapper) {
                if ($mapper !== '' && ($templates[$i] ?? '') !== '') {
                    $token->mappers[] = $mapper;
                    $token->templates[] = $templates[$i];
                }
            }
        }
        foreach (['active' => 'active', 'allow_rename' => 'allowRename', 'rename_alias' => 'renameAlias'] as $key => $property) {
            if (isset($input[$key])) {
                // forms send a hidden 0 before the checkbox
                $value = is_array($input[$key]) ? end($input[$key]) : $input[$key];
                $token->$property = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }
    }

    /** @throws UserError domain_invalid for a non-string (no silent "all domains"), domain_not_found */
    private function domain(mixed $value): ?string
    {
        $domain = Input::text($value);
        if ($domain === null) {
            throw new UserError('domain_invalid');
        }
        if ($domain === '') {
            return null;
        }
        $ascii = idn_to_ascii(strtolower($domain), 0, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false || !$this->repo->domainExists($ascii)) {
            throw new UserError('domain_not_found', [htmlspecialchars($domain)]);
        }
        return $ascii;
    }

    /** List input: array, or JSON / single value from forms; trimmed strings */
    private static function listOf(mixed $value): array
    {
        if (is_string($value) && is_array($decoded = json_decode($value, true))) {
            $value = $decoded;
        }
        return array_map(fn(mixed $v): string => is_scalar($v) ? trim((string)$v) : '', array_values((array)$value));
    }

    /**
     * @return string[] IPs/networks of a list (array or string separated by spaces, commas, semicolons or newlines)
     * @throws UserError network_host_invalid
     */
    private static function networks(mixed $value): array
    {
        $list = is_array($value) ? $value : preg_split('/[\s,;]+/', is_scalar($value) ? (string)$value : '');
        foreach ($list as $v) {
            // a nested value must not vanish: an empty list means "any IP"
            if (!is_scalar($v) && $v !== null) {
                throw new UserError('network_host_invalid', ['?']);
            }
        }
        $networks = array_values(array_unique(array_filter(array_map(fn(mixed $v): string => trim((string)$v), $list), 'strlen')));
        foreach ($networks as $network) {
            [$ip, $prefix] = array_pad(explode('/', $network, 2), 2, null);
            $max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
            if (filter_var($ip, FILTER_VALIDATE_IP) === false || ($prefix !== null && (!ctype_digit($prefix) || (int)$prefix > $max))) {
                throw new UserError('network_host_invalid', [htmlspecialchars($network)]);
            }
        }
        return $networks;
    }
}
