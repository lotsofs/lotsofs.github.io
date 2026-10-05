# Architecture

Cross-cutting machinery shared by every module. Module-specific rules live in the per-module docs.

## Layout

```
public/            web root (deploys to lotsofs.com/)
  index.php        the only PHP entry point
  js/util.js       global JS helpers, loaded by every module's head partial
  modules/<m>/     per-module static assets: css/, js/, json/, fonts/, img/, vendor/
  raw/ktane/       a self-contained static site, served as files
app/               PHP source, never web-served (deploys to ~lotsofs/app/)
  util.php         constants, i18n, asset(), url helpers, per-module preferences
  session.php      sessions, login state, CSRF, password hashing
  router.php       route table assembly, URL canonicalisation, dispatch, abort()
  classes/Database.php   PDO/SQLite wrapper
  config.php       returns [] and is loaded by nothing (see docs/TODO.md)
  modules/<m>/     routes.php, routes/, views/, ajax/, lang/, db.php, migrate.php, ...
  secure/          cacert.pem (gitignored, used only by disabled code)
data/              local SQLite files and sessions (gitignored; prod uses /home/lotsofs/data)
tests/             harness and cases (never deployed)
docs/              this folder
_deploy/           build output (gitignored, never edit)
build-deploy.php   rebuilds _deploy/
```

## Request flow

1. The host runs **only `index.php`** (any other `.php` requested by filename is served as a raw download, deliberately). Front-controller rewriting is configured at the vhost, not in `.htaccess` (the host ignores those). Consequence: every endpoint, including ajax, must be a route in a module's `routes.php`; nothing can be a directly hit `.php` under `public/`.
2. `public/index.php` picks `$appRoot` (`/home/lotsofs/app` if that directory exists, else `../app`), then requires `util.php`, `classes/Database.php`, `router.php`.
3. `router.php` `include`s each module's `routes.php`. Each does `$routes += [ "/path" => __MODULES__ . "/m/routes/x.php" ]`. **Exact string keys, no patterns, no parameters** (ids travel in the query string or POST body). Module order: main, swat4, ss2, music, hideandseek.
4. Canonicalisation: any path that is not already lowercase with no trailing slash gets a **301** to the canonical form, query string preserved. So `/Music/` and `/music` are the same URL, and route keys must be lowercase.
5. `route()` `require`s the matching file or calls `abort(404, ...)`, which sets the status and renders `main/routes/error.php` (so even a 404 inside `/music/...` wears the main site chrome).
6. The route file runs top to bottom in the global scope, so `$globalData`, `$pageTitle`, `$db` etc. are plain globals. It ends by `require`ing a view, or echoing JSON (ajax), or sending a `Location` header and `exit`.

### Constants (from `util.php`)

`__ROOT__` = `app/`, `__MODULES__` = `app/modules`, `__MAIN__` = `app/modules/main`, `__DATA__` = `/home/lotsofs/data` if `/home/lotsofs` exists, else `<repo>/data`.

### Page route skeleton (music/hideandseek)

```php
require_once __ROOT__ . '/session.php';
sessionScope('music');          // names the scope AND starts the session
requireLogin();                 // optional; redirects to /<scope>/login
loadStringCatalogue('music');   // after the session starts (locale lives in the session)
$pageTitle = t('...');
$db = require __MODULES__ . '/music/db.php';
require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);        // every route and ajax file, so schema changes apply on the next request
require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);       // signs out a session whose account row is gone
$globalData['isAdmin'] = musicIsAdmin($db);
... queries (inline SQL) into $globalData ...
require __MODULES__ . '/music/views/x.view.php';
```

`login`, `register`, `index` and the preference posts (`language`, `colour`, `settings`, `logout`) deliberately omit `requireLogin()`.

### Views

`x.view.php` starts with `require head.php` and `nav.php`, ends with `foot.php`. They read `$globalData` and `$pageTitle`. `head.php` ships three things to the browser every time: `<meta name="csrfToken">`, `<script id="langStrings" type="application/json">` (the whole active catalogue), and `util.js`. Escape with `htmlspecialchars()` by hand; there is no templating layer. JSON embedded in a `<script type="application/json">` should use `JSON_HEX_TAG` (hideandseek also `JSON_HEX_AMP|APOS|QUOT`) because the data came from users.

## Database

`Database` wraps one PDO (SQLite) with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `foreign_keys = ON`, `busy_timeout = 5000`. API: `->query($sql, $params)` returns a statement; `->selectAllFromTable($t)`; `->execSQL($sql)` (multi-statement, no params); `->pdo` for `beginTransaction/commit/rollBack/lastInsertId`.

Each module's `db.php` returns a `Database`. **Under the `cli-server` SAPI it opens `<module>_test.sqlite` instead of `<module>.sqlite`**, seeding it from a copy of the live file if the test one does not exist yet. Hence `php -S` and the test suite can never touch the live database. The same signal drops bcrypt cost (see `hashPassword`).

Migrations: `NNN_name.sql` files under `<module>/database/migrations/`, applied in sorted order inside a transaction by `run<Module>Migrations($db)`, tracked **by filename only** in `schema_migrations`. Shipped migrations are frozen; see `music.md` and `hideandseek.md` for the module-specific boundary and rebuild recipe.

Mind SQLite specifics used throughout: `group_concat`, partial unique indexes (`WHERE is_actual = 1`), `COLLATE NOCASE` (ASCII only; PHP side mirrors with `strtolower`/`strcasecmp`), `ON CONFLICT ... DO UPDATE` upserts, `INSERT ... DEFAULT VALUES` for id-only tables.

## Sessions and auth (`session.php`)

- `sessionScope('x')` records the active scope and calls `startSession()`. Cookie: httponly, SameSite=Lax, secure when HTTPS. Session files go in `__DATA__/sessions` with probabilistic GC re-enabled (the distro cron will not watch that directory).
- One PHP session, **namespaced per module**: `$_SESSION['music'] = ['account_id', 'account_name']`, `$_SESSION['hideandseek'] = [..., 'gameMapId']`. `logIn()` regenerates the id and *replaces* the scope array; `logOut()` unsets it and regenerates. Accounts are entirely separate per module (separate tables, separate databases).
- **Not scoped:** `$_SESSION['csrf_token']` is one token for the whole session, shared by modules.
- **Per-module browser preferences** live in `$_SESSION['lang'][module]`, `$_SESSION['hue'][module]`, `$_SESSION['blind'][module]`, touched only through `modulePreference()`, `rememberModulePreference()`, `forgetModulePreference()` (in `util.php`, which loads first). They sit outside the scope array, so `logOut()` does not clear them; routes that should forget them (music `logout`) do so explicitly.
- Guards: `requireLogin($path)` (302), `requireLoginJson($msg)` (401), `requireCsrfJson($msg)` (403, reads `X-CSRF-Token`), `checkCsrf($token)` for form posts (`csrf_token` field). `csrfToken()` mints lazily.
- `hashPassword()` uses bcrypt cost 5 under `cli-server`, `PASSWORD_DEFAULT` otherwise. Never call `password_hash` directly in a route.
- Login rate limit (per module, `rateLimit.php`): 5 failures per IP per 900 s, rows in `login_attempt`, cleared on success. `REMOTE_ADDR` only (no proxy header handling; see TODO).
- Invite codes: 8 letters A–Z shown as `XXXX-XXXX`, normalised by stripping non-letters and upper-casing. The first account on an empty `account` table needs no invite and becomes admin.

## Ajax contract

Every ajax route first requires `<module>/ajaxGuard.php`, which in this order: sets JSON header and a 500-as-JSON exception handler, starts the scoped session, loads the catalogue, **401** if signed out, **405** if not POST, **403** on bad CSRF header, **400** if the body is not a JSON array/object, then opens the DB (music also requires `auth.php`). So an anonymous caller is told to log in before anything about the request is judged. Fixed order, pinned by tests.

Response conventions: success `{status:'ok', ...}`; business refusal returns HTTP 200 with `{status:'error'|'duplicate'|'skipped', message}` (localized); malformed/forged requests return 4xx with `{error}`. `'Unknown field'`/`'Unknown action'` stay English on purpose (developer-facing). Input helpers from the guard: `ajaxInt`, `ajaxText`, `ajaxTrimmed`, `ajaxNumericText` (numbers sent as JSON numbers must not be treated as empty; that bug happened). Admin-only endpoints add `requireMusicAdminJson`.

Client side, `public/js/util.js` supplies `postJson(url, body, {signal})` (adds the CSRF header, rejects with `Error(result.error)` on non-2xx), `t(key, params)` over `LANG_STRINGS`, `CSRF_TOKEN`, `escapeHtml`, `readJsonFileAsync`, `readTextFileAsync`, `appendChildToElement`, `setElementByIdTextContent`, `createSVGElement`, `convertSecondsToTimestamp`. `util.js` is a classic script (not a module) so its names are globals for every other script; ss2's ES modules use them as globals too.

## i18n

- A module declares `locales` and `defaultLocale` in `app/modules/<m>/config.php` (`moduleConfig()`). Music: `en, de, fy`, default `fy`. hideandseek: `en` only. Other modules have no catalogue.
- Resolution order in `resolveLocale()`: the module's session entry (if the module offers it) → env `LOTSOFS_LOCALE` → module default. Browser `Accept-Language` is ignored on purpose.
- `loadStringCatalogue($module)` merges `lang/<locale>.php` over `lang/en.php`, dropping null/blank overlay values so English fills gaps. `t($key, ['name' => $v])` replaces `{name}`; unknown key returns the key itself.
- Both sides use the same `{placeholder}` syntax (`t()` in PHP, `t()` in `util.js`). Keys are shipped to JS wholesale via `langStrings`, so any key can be used client-side.
- A key must be written as a literal `t('a.b')` somewhere (the strings test greps for it); do not build keys by concatenation.
- Adding a language: a line in `config.php`, a `lang/<code>.php` wrapper merging `lang/<code>/*.php`, and a `language.<code>` entry in each locale's `common.php` so the switcher can name it (a test checks every locale names every available language).

## Assets

Always emit `asset('/modules/x/css/y.css')`, which appends `?v=<filemtime>` for cache-busting; it resolves from `$_SERVER['DOCUMENT_ROOT']` and silently returns the bare path if the file is missing. Exception: `ss2/views/partials/head.php` and `level.view.php` still use bare paths. A test checks that stamped assets actually serve. HTML is never cached (a test pins that).

URL helpers: `urlIs($path)` (query stripped), `urlStartsWith($prefix)` (raw URI including query), `getLastUrlPart()`.

## Deploy

`php build-deploy.php` regenerates `_deploy/{lotsofs.com,app}` from `public/` and `app/` excluding sqlite/db/log/bak files, `.git*`, `app/modules/music/notes.txt`, then runs sanity checks (required files present; `data`, `tests`, `docs/TODO.md`, ajax dirs under public absent). It exits 1 on problems. **Do not run it yourself; the owner does**, then drags `_deploy/` contents onto the host by hand. No CI. When adding a vendored or must-ship file that is easy to lose, add it to `$mustExist` in the script.

Production specifics: DB and sessions under `/home/lotsofs/` (reachable only by that unix user); migrations run implicitly on the first request after a deploy; the first visitor to `/music/register` on an empty database becomes admin (claim it immediately after a fresh deploy).

## Local development

`php -S localhost:PORT -t public` from the repo root. Test databases and sessions land in `data/` (gitignored). Nothing on this machine runs JavaScript or renders CSS (no node), so front-end changes are only verified by the owner opening the page; say so when you change JS/CSS rather than implying it was exercised.
