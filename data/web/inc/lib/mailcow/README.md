# mailcow PHP classes (`Mailcow\`)

New backend code of the mailcow UI/API lives here as plain PHP classes, one directory per module.
It replaces the procedural `data/web/inc/functions.*.inc.php` files step by step. There is no framework,
no DI container and no ORM: just small classes, constructor injection and PDO.

The directory sits below `inc/lib/`, which nginx never serves. Classes are loaded by Composer's PSR-4
autoloader (`"Mailcow\\": "mailcow/"` in `inc/lib/composer.json`), which every entry point already
includes (`prerequisites.inc.php`, crons, `mailcowauth.php`).

## Layout

```
Core/                     shared by all modules
  Context.php             who is calling: role, username, ACL, access-all; fromSession(), system()
  UserError.php           expected error with a lang key (+ args), shown as a danger message
  AccessDenied.php        UserError 'access_denied'
  ApiResponse.php         collects messages, writes $_SESSION['return'] (the only class that does)
  Input.php               helpers for loosely typed request values
  Repository.php          base class of all repositories: PDO, query(), insertRow(), updateRow(), transaction()
<Module>/
  <Module>Module.php      composition root: create(PDO, Context, array $config) wires everything;
                          fromGlobals() is the legacy entry point for procedural callers
  Model/                  plain typed objects (fromRow(), toArray() for the API shape), no DB
  Repository/             SQL only (prepared statements); takes and returns models
  Service/                use cases, business rules, permissions (Context), transactions via repositories
  Http/                   outbound I/O (e.g. OAuth requests)
  Controller/             request in, messages out: adapter for json_api.php and pages
```

Example: `Imapsync/` (sync jobs, sync sources, OAuth). Use it as the template for new modules.
`ApiKey/` (API keys incl. the service keys of mailcow.conf) is a small module with the same layout.
`MailboxGroup/` (mailbox groups) keeps its own tables as source of truth and maintains rows the legacy code and
Postfix read (`alias`, `sender_acl`, tagged with `group_id`); calls into procedural code go through `Legacy/`.
Shares become Dovecot ACLs on `group=mcg_<id>` (userdb `acl_groups`) and `sogo_acl` rows tracked in a ledger.
`Dovecot/` (doveadm through dockerapi: ACLs, subscriptions, auth cache) and `Sogo/` (user view, cache, sogo-tool
through dockerapi) are used by other modules instead of calling `docker()` or the SOGo functions themselves.
`Identity/` (IdP links, sync runs, offboarding) lists Keycloak/LDAP through `Directory/` adapters, decides in the
side-effect free `SyncPlanner` and is run by the `identity-sync.php` cron.
`Auth/` (logins) holds for now the IdP login steps the legacy login flows call (link checks fail closed, group
template before the attribute mapping); the auth logic of the function files moves here step by step.

## Layers and rules

- **Model:** typed public properties, `fromRow()` from a DB row, `toArray()` for the API format (keys and
  order are the API contract). Secrets stay in the model but never in `toArray()`. No getters/setters.
- **Repository:** extends `Core\Repository`; the only place with SQL (prepared statements via `query()`).
  No permission decisions (a visibility filter that is pure SQL may take the `Context`). A method that writes
  several tables wraps them in `transaction()`, which joins a transaction the caller already runs.
- **Service:** one use case per public method, working on single objects. Checks permissions with the
  `Context` and throws `UserError` / `AccessDenied` with a lang key. No `$_SESSION`, no `global`, no output.
  When add and edit share their rules, use one private routine for both (e.g. `JobService::save()`,
  `SourceService::applyBasics()`): missing attributes keep the current value, a new object starts with defaults.
- **Controller:** normalises request input (batch ids, attribute merging), loops over batch ids, calls
  services, turns `UserError` into messages via `ApiResponse` and returns what `json_api.php` passes to
  `process_*_return()`. The API format (routes, JSON, message keys and order) stays as it was. Each write
  method is a few lines on top of two private helpers: `handle()` (log entry, error mapping, flush) and
  `batch()` (one message per id).
- **View:** Twig templates, unchanged.

## Conventions

- `declare(strict_types=1);` in every file; typed properties, parameters and return types; constructor
  property promotion; `public readonly` properties instead of getters for values that never change.
- Class files contain only declarations (no code at file level), one class per file.
- Constructor injection. Global state (`$pdo`, `$_SESSION`, config arrays, `getenv()`) is only read at the
  entry points and passed to `<Module>Module::create()`. Procedural legacy callers (`json_api.php`, pages,
  `functions.*.inc.php`) use `<Module>Module::fromGlobals(?Context $context = null)`, the one documented
  place in the module that reads the globals, instead of repeating the config lines; entry points that build
  their own state (crons, CLI) call `create()` directly, e.g. with `Context::system()`.
  `ApiResponse::flush()` is the one class that writes `$_SESSION['return']`.
- No interfaces until there are two implementations; no closures as injected dependencies (local callbacks
  such as `transaction(fn ...)` or the controller's `handle()` are fine); small methods.
- Docblocks only where they add information (rules, API contract, non-obvious behaviour), not to repeat
  the signature.
- Classes are not `final`, so PHPUnit can mock them. I/O that tests must replace sits in protected methods
  (see `Imapsync/Http/OAuthClient::send()`).
- Messages: services throw, controllers report. The `msg` is a lang key of the matching section
  (`success` / `danger`), optionally with arguments: `new UserError('object_exists', [$name])`.
- Logs: the `log` part of a message is `[<legacy function>, <action>, <type>, <data>]` as before; request
  keys holding secrets are masked by `ApiResponse` (configure them in the module).
- A database error (`PDOException`) stops the call with one `mysql_error` message, like the legacy code.
- Naming: `Mailcow\<Module>\{Model,Repository,Service,Http,Controller}\<Class>`.

## Converting a legacy `functions.X.inc.php`

1. Capture the current API behaviour (E2E runs, raw JSON responses, DB state).
2. Build the module as above: models, repositories, services, controller, `<Module>Module::create()`.
3. Switch the callers (`json_api.php`, pages, crons, other function files) to the module.
4. Delete the function file and its `require` lines.
5. Compare the API output with step 1; the format must stay the same.

Large files (e.g. `functions.mailbox.inc.php`) are converted one area at a time (aliases, domains, ...),
each as its own module. Until `functions.inc.php` is converted, `Context::canAccessMailbox()` /
`canAccessDomain()` call the legacy `hasMailboxObjectAccess()` / `hasDomainAccess()`.

## Development

- Unit tests: `tests/php/<Module>/...` (dev-only, never deployed), run with
  `helper-scripts/dev_tests/run_php_unit_tests.sh`. Services are tested with mocked repositories;
  repositories are covered by the E2E suites (MariaDB-specific SQL).
- Autoloader: after adding the PSR-4 mapping nothing needs regenerating for new classes. If you ever run
  `composer dump-autoload` (inside the php-fpm container, `-w /web/inc/lib`), never use `-o`, `-a` or
  `--apcu`, and commit only the changed `autoload_*.php` files.
- No dev packages in `inc/lib/composer.json`.
- opcache runs with `validate_timestamps=0`: restart php-fpm after changing PHP files
  (`docker compose restart php-fpm-mailcow`).
