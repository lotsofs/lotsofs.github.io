# Testing

Run: `php tests/run.php` (about 35 s; ~8k lines of cases). Locale coverage: `php tests/i18n-coverage.php`.

## How the harness works (`tests/run.php`)

1. Copies the repo to `sys_get_temp_dir()/lotsofs_tests_<pid>`, skipping `public/modules/ss2/img`, `.../ss2/fonts`, `public/raw`, `tests`, `_deploy`, and every `*.sqlite`/`*.db` file. The scratch copy has its own empty `data/`.
2. Starts `php -S localhost:<free port> -t <copy>/public` with `LOTSOFS_LOCALE=en`, via `proc_open` with `bypass_shell => true` (without it Windows orphans the server). The `cli-server` SAPI is what routes every module to its `_test` database and drops bcrypt cost to 5.
3. `require`s `tests/helpers.php`, then each `tests/cases/*.php` in `glob()` order. A case file returns `['test name' => function ($ctx) {...}]`; a throwing closure is a failure. There is **no filter by name**: all tests always run.
4. A shutdown function kills the server and deletes the copy, cookie jar and log.

Everything is driven over real HTTP with curl against real PHP and a real SQLite file. The suite never executes JavaScript or CSS.

### Shared state: the traps

- **One database for all case files**, and files run in filename order. `accounts.php` sorts first, which is what makes its "account table starts empty" assertion true. A new case file that sorts before it and logs in breaks *that* test, not its own. `hideandseek.php` has its own database (`hideandseek_test.sqlite`) but shares the cookie jar/session with the music cases.
- By the end of a run `/music/songs` renders every song against 40-odd accounts' rater columns, which dominates the runtime.
- The cookie jar is one file; `$ctx->newSession()` deletes it. Logging in while already signed in is a redirect no-op, so tests that need another identity log out or start a new session first.
- The CSRF token is **session-wide, not per module**. `$ctx->post()` lazily fetches it from the `<meta name="csrfToken">` on `/music`, and hideandseek's ajax tests reuse it.

## `TestContext` (`$ctx`)

| Method | Purpose |
|---|---|
| `get($path, $follow = false, $headers = [])` | GET; returns `status`, `body`, `headers`, `location`, `json` |
| `post($path, $payload)` | JSON POST **with** `X-CSRF-Token` (array payloads are `json_encode`d) |
| `postWithoutCsrf($path, $payload)` | JSON POST without the header (for 403 tests) |
| `postForm($path, $fields, $follow = false, $headers = [])` | urlencoded form POST (fields must include `csrf_token` where the route checks it) |
| `csrfTokenFrom($path)` | scrapes `name="csrf_token" value="..."` from a form page |
| `newSession()` | drop cookies and cached token |
| `db()` / `dbFor($file)` | PDO on the scratch copy's SQLite (`music_test.sqlite` / named file), `FETCH_ASSOC`, `busy_timeout` set |
| `ensureLoggedIn($name = 'test_runner', $pw = 'test password', $isAdmin = true)` | creates the account row directly (bcrypt cost 5) if missing and logs in via the real form |
| `songId($title)`, `songTitle($id)`, `songArtistId($id)`, `songCount($title)`, `makeArtist($name)` | catalogue shortcuts |

Global asserts: `assertSame($expected, $actual, $what)`, `assertTrue($cond, $what)`, `assertContains($needle, $haystack, $what)`.

### `tests/helpers.php` (shared by more than one case file)

`registerAccount`, `logInAs`, `makeAlbum($ctx, $name, $artistId, $tracks)`, `makeSong($ctx, $artistId, $title)`, list-page scrapers `listRowFor`, `listCells`, `listOrder`, `listOrderOf`, class matcher `assertClasses($needles, $body, $pattern, $what)`, songs-page scrapers `songsRowChunks`, `songsCellValue`, `songsValuesInOrder`, `songsFieldCell`, `songsCardNoteCell`, `songsRowFor`, `songsCardFor`.

**Rules:** a helper used by two or more case files goes in `helpers.php`, never in the first file that needed it (`require`ing one case file from another is one rename from a redeclaration fatal). A helper used by one file stays in that file (`hideandseek.php` defines its own `hns*` helpers, prefixed to avoid collisions). Match class attributes loosely with `assertClasses()`; pinning an exact `class="..."` string fails on legal changes. Splitting HTML on a card's opening tag must use a regex (`songsCardFor`).

## Case files

| File | Covers |
|---|---|
| `accounts.php` | registration, invite lifecycle, login/logout, rate limiting, CSRF on forms (must sort first) |
| `admin.php` | admin vs non-admin gating on pages and endpoints, promote/demote, session outliving its account |
| `albums.php` | `/music/ajax/album` import semantics, album card, track editing, graph ordering, album list stats and sorting |
| `artistcard.php` | artist card, its graph, rename, album table, card links |
| `artists.php` | `/music/ajax/artist-alias` semantics, alias rules, artist list stats and sorting |
| `audit.php` | rating history page and the `previous_value` trail |
| `catalogue.php` | artists/albums list pages, escaping, `//`-in-CSS ban, header/cell count agreement |
| `colour.php` | hue storage, default derivation, forged posts, graph dot colours |
| `hideandseek.php` | the whole hideandseek module: accounts, game maps, import/export, categories, shell, map script/style invariants, label hiding, Voronoi/circle overlays (structure only) |
| `locale.php` | locale files complete and mergeable, language switching, per-account language |
| `pages.php` | every route serves, auth redirects, 404/301 behaviour, CSRF on endpoints, asset stamps, no PHP warnings, card-link script order |
| `settings.php` | blind-rating switch: default, toggle, persistence across login, forged post |
| `songs.php` | the biggest: song import, rename, ratings, sorting (incl. injection attempts), filters, rater columns, stats columns, gate markup, poll cursors, toasts markup, card markup |
| `strings.php` | every `t('literal.key')` exists, none built by concatenation, no orphan keys, no duplicate keys, no raw key rendered |

`tests/i18n-coverage.php` reports, per non-English music locale, which keys are absent/blank and which are orphans (keys English lacks). Exits non-zero on orphans only. Both it and `strings.php` are **hardcoded to the music module**; hideandseek has no orphan/coverage check.

## Writing a new test

- Add an entry to the right case file (or a new file whose sort position cannot disturb `accounts.php`).
- Start with `$ctx->ensureLoggedIn()` unless the test is about being signed out. Create data through the real endpoints (`makeArtist`, `makeSong`, `makeAlbum`) rather than raw SQL where an endpoint exists; read back through `$ctx->db()` for assertions about stored state.
- Pin both directions of a rule that has a "do not harmonise" warning (the two poll cursors; blind-rating leaks that are deliberate).
- Assert on markup structurally (regex on `data-field`, classes) not on whole attribute strings.
- Because the database persists across files, use unique names (artist/song titles) per test or assert on relative state.

## What the suite cannot see

- Any JavaScript behaviour: `songs.js`, `albumCard.js`, `addSongs.js`, `map.js`, `poiImport.js` (including the Overpass parser), ss2 modules. Tests only pin that the page ships the script/markup and the endpoint validation.
- CSS cascade correctness (only brace/comment sanity checks).
- The real host (PHP build differences, vhost rewriting, HTTPS cookie flag).

Say so in your report when a change is only front-end; do not claim it was verified.
