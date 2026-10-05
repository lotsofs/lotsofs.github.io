# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A plain PHP site (no framework, no Composer, no npm/build step — vanilla PHP + vanilla JS/CSS) with one front controller and a hand-rolled router. Five modules live side by side: `main` (home/contact/exchange-rates/ktane pages), `swat4`, `ss2`, `music` (by far the largest and most actively developed — a song/artist/album cataloguing and rating tool, 60+ files), and `hideandseek` (boilerplate only so far).

## Commands

- **Run the test suite:** `php tests/run.php`. It copies the whole repo into a scratch temp dir (skipping `.sqlite`/`.db` files and a few static-asset dirs), boots PHP's built-in server against that copy, and drives it over real HTTP (via curl) through `tests/cases/*.php`. There is no per-test-name filter — every case file's tests run every time. Test case files are plain PHP files returning `['test name' => function ($ctx) { ... }]`; add new cases as new entries or new files under `tests/cases/`. **Case files share one database and run in `glob()` (i.e. filename) order**, so `accounts.php` sorting first is what makes its "account table starts empty" assertion true — a new case file sorting before it that logs in would break that test rather than its own. Helpers used by more than one case file go in `tests/helpers.php` (loaded once by `run.php`), never in whichever case file needed them first; `require`ing one case file from another is one rename away from a redeclaration fatal. **The suite runs in about 35 seconds, and bcrypt is the reason it is not two minutes.** PHP 8.4 raised `PASSWORD_DEFAULT`'s cost to 12 — 165ms per hash and per verify on this machine — and the suite logs in around 350 times. `hashPassword()` in `app/session.php` drops the cost to 5 under the `cli-server` SAPI, which is the same signal `db.php` uses to pick the `_test` database, so a cheap hash can only ever be written to a database production does not have. Do not "fix" that branch to a constant cost, and do not reach for `password_hash` directly in a route. (Measured and rejected on the way: loading opcache into the test server, which came out inside the run-to-run noise, and reusing one curl handle, which is worth about half a second across the whole suite — an HTTP round trip to the built-in server is 0.6ms and a PHP route is 3.7ms.) What is left is dominated by `/music/songs`: the case files share one database, so by the end of a run that page renders every song against 40-odd accounts' rater columns, twice, which is most of the `songs` group's 22 seconds. When asserting on markup, match a class *list* loosely (`assertClasses()`) rather than pinning the exact attribute string — `docs/music.md`'s rule for the card markup is "add to a class list, never replace one", so an exact pin fails on a legal change.
- **Check locale completeness:** `php tests/i18n-coverage.php` — lists untranslated/orphaned keys per music-module locale file; exits non-zero only on orphan keys (a locale file defining a key `en.php` doesn't have).
- **Rebuild the deploy folder:** `php build-deploy.php` — regenerates `_deploy/` (`_deploy/lotsofs.com/` = `public/`, `_deploy/app/` = `app/`) with dev-only files excluded and a set of sanity checks (required files present, `notes.txt`/`data`/`tests`/db files absent). `_deploy/` itself never ships and there's no CI — deploying means dragging `_deploy/`'s contents onto the host by hand.
- **Run a dev server directly:** `php -S localhost:PORT -t public` from the repo root. Because each module's `db.php` detects PHP's built-in server (`cli-server` SAPI) and switches to `<module>_test.sqlite` instead of `<module>.sqlite`, this is safe to use against the real repo without touching production-shaped data — and if the live file exists and the test one doesn't yet, it's seeded from a copy of it before migrations run.

## Architecture

### Request flow

`public/index.php` → `app/util.php` (constants + i18n) → `app/classes/Database.php` → `app/router.php`, which `include`s every module's `routes.php` into one `$routes` array (exact-path-string keys, no pattern matching) and dispatches by requiring the matching file. Unknown paths and non-canonical URLs (non-lowercase, trailing slash) get redirected/aborted in `router.php` itself.

### Module shape (repeats across `main`/`swat4`/`ss2`/`music`/`hideandseek`)

- `app/modules/<name>/routes.php` — the route table for that module, merged into the global one.
- `app/modules/<name>/routes/*.php` — page controllers: guard → load data via inline SQL → `require` a matching view. No model/service layer anywhere in this codebase — SQL lives directly in route and ajax files.
- `app/modules/<name>/views/*.view.php` — plain PHP templates.
- `app/modules/<name>/ajax/*.php` (music and hideandseek) — JSON POST endpoints for the front-end JS, gated by a shared `ajaxGuard.php` (JSON header, exception→500-JSON handler, POST-only, JSON-body parsing).
- `public/modules/<name>/{css,js,...}` — that module's static assets, served directly.

### Per-module notes

Each module with anything non-obvious in it has its own file, imported here:

@docs/music.md
@docs/hideandseek.md

`main`, `swat4` and `ss2` have none — they are plain routes and views with no
traps worth writing down.

Further reference lives in `docs/` and is **not** imported (read on demand): start from `docs/README.md` — architecture, conventions, testing, music/hideandseek lookup tables, music front end, and `main`/`swat4`/`ss2`.

## Environment gotchas

- **CSS has no line comments, and a `//` silently eats the next rule.** The parser treats the slashes as a bad token and recovers by swallowing the declaration block that follows, leaving something that reads perfectly and never applies — it cost a debugging cycle presenting as "the card modal is transparent". Two tests forbid it (`tests/cases/catalogue.php` for music's stylesheet, `tests/cases/hideandseek.php` for its own). Use `/* */`, including for one-liners.
- **No `mbstring`.** No `mb_*` function works on this PHP build (and presumably not on the host either) — use `strlen` and friends.
- **`proc_open` on Windows spawns through a shell wrapper**, so `proc_terminate` only kills the wrapper and orphans the real child process. `bypass_shell => true` (already used in `tests/run.php`) is required to avoid leaking a server process per test run.

## Other notes

`docs/TODO.md` tracks open tasks and unresolved design decisions (e.g. how song versions/covers are modelled) — check it for context before assuming a rough edge is unintentional. It can lag behind the schema/code after a session like this one's migration work, so verify against current code rather than trusting it blindly.
