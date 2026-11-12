# Testing

Run: `php tests/run.php` (about 60 s; ~8k lines of cases), or `php tests/run.php basstabs` for one case file. Locale coverage: `php tests/i18n-coverage.php`.

## How the harness works (`tests/run.php`)

1. Copies the repo to `sys_get_temp_dir()/lotsofs_tests_<pid>`, skipping `public/modules/ss2/img`, `.../ss2/fonts`, `public/raw`, `tests`, `_deploy`, and every `*.sqlite`/`*.db` file. The scratch copy has its own empty `data/`.
2. Starts `php -S localhost:<free port> -t <copy>/public` with `LOTSOFS_LOCALE=en`, via `proc_open` with `bypass_shell => true` (without it Windows orphans the server). The `cli-server` SAPI is what routes every module to its `_test` database and drops bcrypt cost to 5.
3. `require`s `tests/helpers.php`, then each `tests/cases/*.php` in `glob()` order. A case file returns `['test name' => function ($ctx) {...}]`; a throwing closure is a failure. Naming case files runs only those: `php tests/run.php basstabs` takes 1.4 s against about 60 s for the lot (2026-11-12), and an unknown name exits 1 rather than passing nothing. Files share one database (below), so a filtered run is only trustworthy for a file that sets up its own state: `basstabs` does; `pages` does not, and two of its music tests fail on their own.
4. After each case file, `dropAccountsAfter()` deletes every music account the file created except `test_runner`, with its ratings, audit rows, column choices and invites, and starts a new session (below).
5. A shutdown function kills the server and deletes the copy, cookie jar and log.

Each response is held once: curl hands back the body alone and the headers arrive through `CURLOPT_HEADERFUNCTION`. Splitting one combined string afterwards held a page twice, which at 42.6 MB mattered.

Everything is driven over real HTTP with curl against real PHP and a real SQLite file. The suite never executes JavaScript or CSS.

### Shared state: the traps

- **One database for all case files**, and files run in filename order. `accounts.php` sorts first, which is what makes its "account table starts empty" assertion true. A new case file that sorts before it and logs in breaks *that* test, not its own. `hideandseek.php` has its own database (`hideandseek_test.sqlite`) but shares the cookie jar/session with the music cases.
- **A case file's accounts end with it.** Every music account but `test_runner` is deleted when the file that made it ends, so a test can only use `test_runner` or an account its own file makes, and a file that made any leaves the next one signed out. Every account is a rater on the song list, whether or not it rated anything, so keeping them made that page grow as accounts × songs: 104 accounts and 42.6 MB by the end of a run, a 142 MB peak in the runner against PHP's 128 MB default, and 145 s for the suite (2026-11-12). Now the page ends at 2.8 MB, the peak is 42 MB and the suite takes about 60 s.
- What dominates the runtime now is the `songs` case file (27 s): 161 tests, most loading `/music/songs`, which by then lists the 436 songs every earlier file made. Songs are never deleted.
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
| `lastAccountId()` / `dropAccountsAfter($id)` | the runner's own, between case files: the newest music account id, and deleting every account after it but `test_runner` |

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
| `audit.php` | the History page: ratings and admin edits in one list, the `previous_value` trail, what each admin endpoint logs, and paging across both logs |
| `basstabs.php` | the bass tab list and every sheet; Bring Me to Life's hammer-ons (each arcs up one string, marked h) and its dotted notes' short holds, not its notes, which the owner is still changing; Monday Morning's three parts, their Fuzz brackets, its flats and its eight lyric cues, not its notes, which the owner is still changing; Inside's dotted riff and verse, its rests each with room of their own, its verse chords' boards (Em beside the verse, C and A right-aligned under it); every board marking exactly its triad's notes, heavier in the hand position; Creep's C minor board in flats on a sheet in sharps, and its outro beside the main riff with a slide up into it; slides and where they may go; `beside` and where it is refused; a board's key name in its left column, root over scale, one width for the sheet; boards right-aligning only under a section with a board; Love Myself's four board-only sections (one block, no names or staves, each chord's key, anchor and hand position, one above another); each sheet's artist and key in its top-left corner; which staves' bracket bands rise beside the next heading and which may not; Amsterdam read back section by section (repeats, sharps, the bridge's dots and tildes over their digits, the quiet part's holds and the B4's double length, its D Phrygian board); all four sections of My Favourite Game read back off the SVG (frets on their lines, bar lines and repeat dots, the held note, half-length spacing, the bridge's pairs grouped by a small gap, brackets and arrow, the outro's text, a note name under every fret); every staff on a sheet shrinking by one factor; song file names; the notation parser and its refusals (held dashes doubling, a /2 after them halving, marks, their order, hammer-ons and line breaks and where they may go, the one-dot mark, quarter and eighth rests) and the note names in sharps and flats, keys (Phrygian, scale names in any case, printed with real signs) and the hand positions a fretboard accepts (required directly); My Favourite Game's fretboards: C minor beside the riff (37 dots all in the key, fret 12, the shaded inlay frets, the minor box, the anchor, the open strings left of the nut, white names on the hand position and anchor, no legend) and F major beside the outro (39 dots, the major shape on B6), none beside the chorus or bridge, each key named inside its frame; a staff's lines running on under its board (the CSS that does it, and the frame drawn first); the board's colours all grey, its outline one of them; no `//` in its stylesheet |
| `catalogue.php` | artists/albums list pages, escaping, `//`-in-CSS ban, header/cell count agreement |
| `changes.php` | the What's New page: `changelog.php`'s shape (English on every entry, only offered locales, known kinds, dates newest first, one undated release at most and on top), the English fallback and `**bold**`, the page in English and Frisian |
| `colour.php` | hue storage, default derivation, forged posts, graph dot colours |
| `hideandseek.php` | the whole hideandseek module: accounts, game maps, import/export, categories, shell, map script/style invariants, label hiding, Voronoi/circle overlays (structure only) |
| `locale.php` | locale files complete and mergeable, language switching, per-account language |
| `pages.php` | every route serves, auth redirects, 404/301 behaviour, CSRF on endpoints, asset stamps, no PHP warnings, card-link script order |
| `sessions.php` | the 399-day login cookie from logging in and registering, its daily renewal and the upgrade of an older session, an anonymous cookie that ends with the browser, logging out, and `sweepSessions()` on fixture files and a real login (required directly); PHP's collector staying off |
| `settings.php` | the cog menu: blind-rating switch and toast duration, each with default, saving, persistence across login, logout, forged post; the duration also refuses values the menu does not offer |
| `songs.php` | the biggest: song import, rename, ratings, sorting (incl. injection attempts), filters, rater columns, stats columns, gate markup, poll cursors, toasts markup, card markup |
| `strings.php` | every `t('literal.key')` exists, none built by concatenation, no orphan keys, no duplicate keys, no raw key rendered |

`tests/i18n-coverage.php` reports, per non-English music locale, which keys are absent/blank and which are orphans (keys English lacks), and which `changelog.php` entries lack that locale. Exits non-zero on orphans only. Both it and `strings.php` are **hardcoded to the music module**; hideandseek has no orphan/coverage check.

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
