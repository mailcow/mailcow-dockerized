<?php

declare(strict_types=1);

namespace Mailcow\Imapsync\Service;

use Mailcow\Core\AccessDenied;
use Mailcow\Core\Context;
use Mailcow\Core\Input;
use Mailcow\Core\UserError;
use Mailcow\Imapsync\Model\Source;
use Mailcow\Imapsync\Repository\SourceRepository;
use Mailcow\Imapsync\Repository\TokenRepository;

/**
 * Use cases and rules for sync sources.
 *
 * Read: admin all; domain admin own + 'all' + sources targeting his domains/mailboxes + sources created by
 *       his mailboxes; user own + 'all' + sources targeting him or his domain (see SourceRepository).
 * Edit: admin all; creator own; domain admin sources created by his mailboxes.
 * XOAUTH2 sources are created and edited by admins and domain admins only (users may use them).
 * Users and domain admins need the `syncjobs` ACL for everything.
 */
class SourceService
{
    private const ENCRYPTIONS = ['TLS', 'SSL', 'PLAIN'];
    private const AUTH_TYPES = ['PLAIN', 'LOGIN', 'CRAM-MD5', 'XOAUTH2'];
    private const SCOPES = ['all', 'domain', 'user'];

    public function __construct(private SourceRepository $repo, private TokenRepository $tokens, private OAuthService $oauth,
        private Context $context)
    {
    }

    /** @throws AccessDenied without the syncjobs ACL */
    public function requireAcl(): void
    {
        if (!$this->context->hasAcl('syncjobs')) {
            throw new AccessDenied();
        }
    }

    /** Add/edit: a logged-in admin, domain admin or user with the syncjobs ACL */
    public function requireEditor(): void
    {
        if (!($this->context->isAdmin() || $this->context->isDomainAdmin() || $this->context->isUser())) {
            throw new AccessDenied();
        }
        $this->requireAcl();
    }

    /**
     * The source when the caller may see it (incl. secrets: never pass the object to the UI, use toArray()).
     * Users and domain admins without the syncjobs ACL see no sources.
     */
    public function get(int $id): ?Source
    {
        return $this->canRead() ? $this->repo->findVisible($id, $this->context) : null;
    }

    /** @return int[] ids of the sources the caller may see, ordered by name */
    public function visibleIds(): array
    {
        return $this->canRead() ? $this->repo->visibleIds($this->context) : [];
    }

    public function canEdit(Source $source): bool
    {
        if ($this->context->hasAdminRights() || $source->createdBy === $this->context->username) {
            return true;
        }
        // A domain admin may manage sources created by mailboxes of his domains
        return $this->context->isDomainAdmin() && $source->createdBy !== '' && $this->context->canAccessMailbox($source->createdBy);
    }

    /** API representation for the caller (can_edit, extra params only for editors, redirect URI) */
    public function toArray(Source $source): array
    {
        return $source->toArray($this->canEdit($source), $this->oauth->redirectUri());
    }

    /**
     * Creates a source from request attributes (C9 names; host1/port1/enc1 are accepted as aliases).
     *
     * @throws UserError
     */
    public function create(array $input): Source
    {
        $this->requireEditor();
        $source = new Source();
        $source->createdBy = (string)$this->context->username;
        $this->applyBasics($source, $input);
        $this->applyOAuth($source, $input);
        // Scope and targets are validated before anything is written
        $this->applyScope($source, $input['scope'] ?? 'user', $input['domains'] ?? [], $input['users'] ?? []);
        if ($this->repo->nameExists($source->name, $source->createdBy)) {
            throw new UserError('object_exists', [htmlspecialchars($source->name)]);
        }
        $this->repo->insert($source);
        $this->fetchToken($source);
        return $source;
    }

    /**
     * Updates a source; attributes missing in $input keep their value.
     *
     * @throws UserError
     */
    public function update(int $id, array $input): Source
    {
        $this->requireEditor();
        $current = $this->editable($id);
        $source = clone $current;
        $this->applyBasics($source, $input);
        if ($source->name !== $current->name && $this->repo->nameExists($source->name, $source->createdBy, $id)) {
            throw new UserError('object_exists', [htmlspecialchars($source->name)]);
        }
        $this->applyOAuth($source, $input);
        // Targets only change when the request carries `scope`; client_credentials sources are always private
        $writeTargets = isset($input['scope']) || $source->usesClientCredentials();
        if ($writeTargets) {
            $this->applyScope($source, $input['scope'] ?? $current->scope, $input['domains'] ?? [], $input['users'] ?? []);
        }
        $this->repo->update($source, $writeTargets, self::connection($current) !== self::connection($source));
        $this->fetchToken($source);
        return $source;
    }

    /**
     * Deletes an unused source and returns it.
     *
     * @throws UserError access_denied, imapsync_source_in_use
     */
    public function delete(int $id): Source
    {
        $this->requireAcl();
        $source = $this->editable($id);
        // FK ON DELETE RESTRICT prevents removal while sync jobs reference it
        if ($this->repo->isInUse($id)) {
            throw new UserError('imapsync_source_in_use', [$source->name]);
        }
        $this->repo->delete($id);
        return $source;
    }

    /**
     * Fetches a new shared token for a client_credentials source now.
     *
     * @throws UserError access_denied, imapsync_source_refresh_unsupported, imapsync_source_token_refresh_failed
     */
    public function refreshToken(int $id): Source
    {
        $this->requireAcl();
        $source = $this->editable($id);
        if (!$source->usesClientCredentials()) {
            throw new UserError('imapsync_source_refresh_unsupported');
        }
        $error = $this->oauth->refreshSourceToken($source);
        if ($error !== null) {
            throw new UserError('imapsync_source_token_refresh_failed', [$source->name . ' (' . $error . ')']);
        }
        return $source;
    }

    /** A visible, active authorization_code source the caller may sign in to (OAuth popup), else null */
    public function findConnectable(int $id): ?Source
    {
        $source = $this->get($id);
        return ($source !== null && $source->active === 1 && $source->usesAuthorizationCode()) ? $source : null;
    }

    /**
     * Account rename (mailbox, admin, domain admin): sources, user targets and token owners follow.
     * Leftover sources of an earlier account named $new are released first (see releaseSourcesOf()).
     * Runs on the caller's PDO; a PDOException is not caught, so a caller transaction can roll back.
     */
    public function onAccountRenamed(string $old, string $new): void
    {
        if ($old === '' || $old === $new) {
            return;
        }
        $this->releaseSourcesOf($new);
        $this->repo->renameAccount($old, $new);
        $this->tokens->renameOwner($old, $new);
    }

    /** Account deleted (after its jobs were deleted): sources released, tokens and user targets removed */
    public function onAccountDeleted(string $old): void
    {
        if ($old === '') {
            return;
        }
        $this->releaseSourcesOf($old);
        $this->tokens->deleteByOwner($old);
        $this->repo->deleteUserTarget($old);
    }

    private function canRead(): bool
    {
        return $this->context->hasAcl('syncjobs') || $this->context->isAdmin();
    }

    /** @throws AccessDenied when the caller cannot see or edit the source */
    private function editable(int $id): Source
    {
        $source = $this->get($id);
        if ($source === null || !$this->canEdit($source)) {
            throw new AccessDenied();
        }
        return $source;
    }

    /**
     * Sources of an account are deleted when unused, otherwise handed over to the admins
     * (created_by ''), so a re-created account inherits nothing.
     */
    private function releaseSourcesOf(string $username): void
    {
        foreach ($this->repo->listCreatedBy($username) as $source) {
            $id = (int)$source->id;
            if (!$this->repo->isInUse($id)) {
                // Explicit child deletes: the mailbox rename runs with FOREIGN_KEY_CHECKS = 0 (no cascade)
                $this->repo->deleteWithChildren($id);
                continue;
            }
            // Keep (created_by, name) unique when handing over
            $name = $this->repo->nameExists($source->name, '') ? mb_substr($source->name, 0, 80) . ' (#' . $id . ')' : $source->name;
            $this->repo->handOver($id, $name);
        }
    }

    /**
     * Name, description, connection and active from the request; missing attributes keep the value of $source.
     * On edit, stored host/port/enc that do not change are not validated again, so migrated legacy sources
     * stay editable (e.g. host 'imap_server.lan'). A host that only differs in case keeps the stored spelling.
     *
     * @throws UserError
     */
    private function applyBasics(Source $source, array $input): void
    {
        $stored = ($source->id !== null);
        $name = isset($input['name']) ? Input::text($input['name']) : $source->name;
        $description = isset($input['description']) ? Input::text($input['description']) : $source->description;
        $host = $input['host'] ?? $input['host1'] ?? null;
        $host = ($host === null) ? $source->host : Input::text($host);
        if ($host !== null) {
            $host = (strtolower($host) === strtolower($source->host)) ? $source->host : strtolower($host);
        }
        $port = $input['port'] ?? $input['port1'] ?? $source->port;
        $port = is_scalar($port) ? (int)$port : 0;
        $enc = $input['enc'] ?? $input['enc1'] ?? $source->enc;
        $authType = $input['auth_type'] ?? $source->authType;

        if ($name === null || $name === '' || mb_strlen($name) > 100) {
            throw new UserError('imapsync_source_name_invalid');
        }
        if ($description === null || mb_strlen($description) > 255) {
            throw new UserError('imapsync_source_description_invalid');
        }
        if (!($stored && $host === $source->host) && !$this->isValidHost($host)) {
            throw new UserError('imapsync_source_host_invalid');
        }
        if (!($stored && $port === $source->port) && ($port < 1 || $port > 65535)) {
            throw new UserError('imapsync_source_port_invalid');
        }
        if (!($stored && $enc === $source->enc) && !in_array($enc, self::ENCRYPTIONS, true)) {
            throw new UserError('imapsync_source_enc_invalid');
        }
        if (!in_array($authType, self::AUTH_TYPES, true)) {
            throw new UserError('imapsync_source_auth_invalid');
        }
        $source->name = $name;
        $source->description = $description;
        $source->host = $host;
        $source->port = $port;
        $source->enc = $enc;
        $source->authType = $authType;
        $source->active = isset($input['active']) ? (int)$input['active'] : $source->active;
    }

    private function isValidHost(?string $host): bool
    {
        return $host !== null && $host !== '' && strlen($host) <= 253
            && (filter_var($host, FILTER_VALIDATE_IP) || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME));
    }

    /** @throws UserError */
    private function applyOAuth(Source $source, array $input): void
    {
        // XOAUTH2 sources are managed by admins and domain admins only
        if ($source->isXOAuth2() && $this->context->isUser()) {
            throw new AccessDenied();
        }
        $this->oauth->applySettings($source, $input);
    }

    /**
     * Validates scope and targets for the caller and sets them; nothing is written here.
     * Users' sources and client_credentials sources (one app-wide token that reaches every mailbox) are always private.
     *
     * @throws UserError access_denied, imapsync_source_scope_empty
     */
    private function applyScope(Source $source, mixed $scope, mixed $domains, mixed $users): void
    {
        if ($source->usesClientCredentials() || $this->context->isUser()) {
            [$source->scope, $source->domains, $source->users] = ['user', [], []];
            return;
        }
        // Only admins may create globally visible sources
        if (!is_string($scope) || !in_array($scope, self::SCOPES, true) || ($scope === 'all' && !$this->context->isAdmin())) {
            throw new AccessDenied();
        }
        $domains = ($scope === 'domain') ? $this->cleanList($domains) : [];
        $users = ($scope === 'user') ? $this->cleanList($users) : [];
        if ($scope === 'domain' && empty($domains)) {
            throw new UserError('imapsync_source_scope_empty');
        }
        foreach ($domains as $domain) {
            if (!$this->context->canAccessDomain($domain)) {
                throw new AccessDenied();
            }
        }
        foreach ($users as $user) {
            if (!$this->context->canAccessMailbox($user)) {
                throw new AccessDenied();
            }
        }
        [$source->scope, $source->domains, $source->users] = [$scope, $domains, $users];
    }

    /** @return string[] trimmed, lowercase, unique, non-empty scalar values */
    private function cleanList(mixed $list): array
    {
        $clean = [];
        foreach ((array)$list as $value) {
            if (is_scalar($value) && trim((string)$value) !== '') {
                $clean[] = strtolower(trim((string)$value));
            }
        }
        return array_values(array_unique($clean));
    }

    /** A saved, active client_credentials source gets its token now instead of at the next cron run (error in lastRefreshError) */
    private function fetchToken(Source $source): void
    {
        if ($source->usesClientCredentials() && $source->active === 1) {
            $source->lastRefreshError = $this->oauth->refreshSourceToken($source);
        }
    }

    /** Tokens never follow a source to another OAuth client or IMAP server, so these values reset them */
    private static function connection(Source $source): array
    {
        return [(string)$source->authType, $source->host, $source->port, (string)$source->enc, $source->oauthFlow,
            (string)$source->tokenEndpoint, (string)$source->clientId];
    }
}
