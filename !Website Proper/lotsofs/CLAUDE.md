# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A plain PHP site (no framework, no Composer, no npm/build step — vanilla PHP + vanilla JS/CSS) with one front controller and a hand-rolled router. Six modules live side by side: `main` (home/contact/exchange-rates/ktane pages, plus an unlinked rehearsal page), `swat4`, `ss2`, `music` (by far the largest and most actively developed — a song/artist/album cataloguing and rating tool, 60+ files), `hideandseek` (boilerplate only so far), and `basstabs` (read-only printable bass tab sheets, no accounts or database).

## Commands

- **Run the test suite:** `php tests/run.php`. It copies the whole repo into a scratch temp dir (skipping `.sqlite`/`.db` files and a few static-asset dirs), boots PHP's built-in server against that copy, and drives it over real HTTP (via curl) through `tests/cases/*.php`. There is no per-test-name filter, but naming case files runs only those: `php tests/run.php basstabs` takes 1.4 s against the full suite's 60, so use it when only that module changed. A filtered run only stands alone for a file that sets up its own state (`basstabs` does; `pages` does not). Test case files are plain PHP files returning `['test name' => function ($ctx) { ... }]`; add new cases as new entries or new files under `tests/cases/`. **Case files share one database and run in `glob()` (i.e. filename) order**, so `accounts.php` sorting first is what makes its "account table starts empty" assertion true — a new case file sorting before it that logs in would break that test rather than its own. **Every music account a case file creates, except `test_runner`, is deleted with everything it wrote when that file ends** (`dropAccountsAfter()` in `run.php`, which also starts a new session), so a case file can only use `test_runner` or accounts it makes itself. Every account is a rater column on the song list, so keeping them all grew that page as accounts × songs: 104 accounts and a 42.6 MB page by the end of a run, which ran the runner out of memory and made the suite take 145 s (2026-11-12). Helpers used by more than one case file go in `tests/helpers.php` (loaded once by `run.php`), never in whichever case file needed them first; `require`ing one case file from another is one rename away from a redeclaration fatal. **The suite runs in about a minute (2026-11-12), and bcrypt is the reason it is not several.** PHP 8.4 raised `PASSWORD_DEFAULT`'s cost to 12 — 165ms per hash and per verify on this machine — and the suite logs in around 350 times. `hashPassword()` in `app/session.php` drops the cost to 5 under the `cli-server` SAPI, which is the same signal `db.php` uses to pick the `_test` database, so a cheap hash can only ever be written to a database production does not have. Do not "fix" that branch to a constant cost, and do not reach for `password_hash` directly in a route. (Measured and rejected on the way: loading opcache into the test server, which came out inside the run-to-run noise, and reusing one curl handle, which is worth about half a second across the whole suite — an HTTP round trip to the built-in server is 0.6ms and a PHP route is 3.7ms.) What is left is dominated by the `songs` case file, 27 of the 60 seconds: 161 tests, most of which load `/music/songs`, which by then lists every song the earlier files made (436 by the end; the file alone takes 16.5 s). When asserting on markup, match a class *list* loosely (`assertClasses()`) rather than pinning the exact attribute string — `docs/music.md`'s rule for the card markup is "add to a class list, never replace one", so an exact pin fails on a legal change.
- **Check locale completeness:** `php tests/i18n-coverage.php` — lists untranslated/orphaned keys per music-module locale file; exits non-zero only on orphan keys (a locale file defining a key `en.php` doesn't have).
- **Rebuild the deploy folder:** `php build-deploy.php` — regenerates `_deploy/` (`_deploy/lotsofs.com/` = `public/`, `_deploy/app/` = `app/`) with dev-only files and any module in its `$unshippedModules` list (hideandseek and basstabs, for now) excluded, and a set of sanity checks (required files present, `notes.txt`/`data`/`tests`/db files absent). `_deploy/` itself never ships and there's no CI — deploying means dragging `_deploy/`'s contents onto the host by hand.
- **Rebuild the hideandseek buurten database:** `php tools/import-buurten.php` — pages through PDOK's CBS Wijken en Buurten (all of NL, about 40 seconds) into `data/hideandseek_geo.sqlite`, a file separate from `hideandseek.sqlite` because it is built at home and copied to the host by hand. See `docs/hideandseek.md`.
- **Run a dev server directly:** `php -S localhost:PORT -t public` from the repo root. Because each module's `db.php` detects PHP's built-in server (`cli-server` SAPI) and switches to `<module>_test.sqlite` instead of `<module>.sqlite`, this is safe to use against the real repo without touching production-shaped data — and if the live file exists and the test one doesn't yet, it's seeded from a copy of it before migrations run.

## Architecture

### Request flow

`public/index.php` → `app/util.php` (constants + i18n) → `app/classes/Database.php` → `app/router.php`, which `include`s every module's `routes.php` into one `$routes` array (exact-path-string keys, no pattern matching) and dispatches by requiring the matching file. Unknown paths and non-canonical URLs (non-lowercase, trailing slash) get redirected/aborted in `router.php` itself.

### Module shape (repeats across `main`/`swat4`/`ss2`/`music`/`hideandseek`/`basstabs`)

- `app/modules/<name>/routes.php` — the route table for that module, merged into the global one.
- `app/modules/<name>/routes/*.php` — page controllers: guard → load data via inline SQL → `require` a matching view. No model/service layer anywhere in this codebase — SQL lives directly in route and ajax files.
- `app/modules/<name>/views/*.view.php` — plain PHP templates.
- `app/modules/<name>/ajax/*.php` (music and hideandseek) — JSON POST endpoints for the front-end JS, gated by a shared `ajaxGuard.php` (JSON header, exception→500-JSON handler, POST-only, JSON-body parsing).
- `public/modules/<name>/{css,js,...}` — that module's static assets, served directly.

### Per-module notes

Each module with anything non-obvious in it has its own file, imported here:

@docs/music.md
@docs/hideandseek.md
@docs/basstabs.md

`main`, `swat4` and `ss2` have none — they are plain routes and views with no
traps worth writing down.

Further reference lives in `docs/` and is **not** imported (read on demand): start from `docs/README.md` — architecture, conventions, testing, music/hideandseek lookup tables, music front end, and `main`/`swat4`/`ss2`.

## Environment gotchas

- **CSS has no line comments, and a `//` silently eats the next rule.** The parser treats the slashes as a bad token and recovers by swallowing the declaration block that follows, leaving something that reads perfectly and never applies — it cost a debugging cycle presenting as "the card modal is transparent". Two tests forbid it (`tests/cases/catalogue.php` for music's stylesheet, `tests/cases/hideandseek.php` for its own). Use `/* */`, including for one-liners.
- **No `mbstring`.** No `mb_*` function works on this PHP build (and presumably not on the host either) — use `strlen` and friends.
- **`proc_open` on Windows spawns through a shell wrapper**, so `proc_terminate` only kills the wrapper and orphans the real child process. `bypass_shell => true` (already used in `tests/run.php`) is required to avoid leaking a server process per test run.

## Other notes

**Start from `docs/TODO.md`** for any open work: it indexes open tasks and unresolved design decisions (e.g. how song versions/covers are modelled) by three-letter code, each linking to its detail in `docs/tasks.md` or `docs/decisions.md`; finished items are logged in `docs/DONE.md`. This project overrides the global layout in one way: the index is grouped by module first (`## Music`, `## Hide and seek`, …, `## Site` for cross-module work), then by kind under each (`### Defects and tasks`, `### Open decisions`, `### Possible, not planned`), with no Area column, and the detail docs keep the same order. Check it for context before assuming a rough edge is unintentional. It can lag behind the code, so verify against current code rather than trusting it blindly.

**`CHANGELOG.md`** is what changed for the site's users, kept under `## Unreleased` in the same change as the work — **except the music module**, whose changes go on its own What's New page instead: entries in `app/modules/music/changelog.php`, English plus drafted `fy` and `de` (see `docs/music.md`). This overrides the global changelog layout for music only; the global rules for what makes an entry and how to word it still apply to both. A release is a deploy: when the owner says they pushed to prod, Unreleased becomes a section headed with that date and an empty Unreleased goes on top, and the undated release in `changelog.php` takes the same date, with an empty undated one added above it. `_deploy/` is normally the last build that shipped (check its file dates against the last deploy), so `diff -rq _deploy/app app` and `diff -rq _deploy/lotsofs.com public` show what Unreleased has to cover.
