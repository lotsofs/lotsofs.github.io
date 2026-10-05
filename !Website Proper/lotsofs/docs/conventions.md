# Conventions and traps

## Owner's standing preferences (for Claude sessions)

- **Never run git commands.** Not even with a go-ahead to edit. Offer a commit message in chat if asked.
- **Never run `build-deploy.php`.** The owner runs it.
- **Comments (policy set 2026-10-05):** a one-line warning next to a tricky spot and a short note on a non-obvious invariant are wanted. Design prose and narration of what code does are not; that goes in `docs/`. Use `///` in PHP/JS and `/* */` in CSS. Keep comments and the docs describing the same code current in the same pass as the change, and delete a comment that stopped being true. Register: plain and impersonal, no "you", no "so" connectives, no trailing consequence clauses.
- **Do not publish Artifacts** unless explicitly asked.
- **Do not delete or "clean up" files in `data/`** (the test databases are used and shared).
- **"Don't show X" means filter the query or UI**, never delete or mutate stored data to hide a display bug.
- **Never kill processes by image name** (`taskkill /IM php.exe` killed the owner's dev server). Target a PID you started.
- **Ambiguous requests default to the music module** unless another is named.
- **`docs/TODO.md` is yours to maintain without asking**: close, update and add items in the same pass as the work.
- Shipped migrations are frozen (see `music.md`).

## Code style (match the neighbours)

- **Indentation: tabs** in app PHP, JS and CSS. (`Database.php` and `public/js/util.js` use 4 spaces; leave those files as they are.)
- **Braces:** opening brace on the same line; `else` / `else if` go on a *new line* after the closing brace (`}` newline `else {`) in most route and ajax code. Keep a file's existing style.
- **PHP files** start `<?php`; route and view files have no closing tag. Views use the short echo `<?= ... ?>` and the alternative syntax (`if: ... endif`, `foreach: ... endforeach`) and `htmlspecialchars()` by hand.
- **Naming:** camelCase for PHP functions, JS functions, variables and CSS classes/ids (`songCardModal`, `.albumStatsTable`). Module prefixes on helpers: music uses `music*` (`musicScoreStats`, `musicHueFor`) or plain nouns (`scoreText`, `songLinkFields`); hideandseek uses `hns*`. JSON/DB columns are snake_case (`artist_id`, `is_actual`); JSON keys that are only ever read by JS in the hideandseek module and the poll payloads are camelCase.
- **File names:** routes/ajax/partials camelCase (`songRating.php`), URLs kebab-case (`/music/ajax/song-rating`), views `name.view.php`.
- **Data flow in PHP:** controller → `$globalData` array → view. SQL lives inline in route and ajax files; there is no model layer (see "Two leftovers" in `TODO.md`). Do not introduce one casually.
- **Transactions:** multi-statement writes use `$db->pdo->beginTransaction()` / `commit()` with `catch (PDOException $e) { rollBack(); throw $e; }`. The audit log write must share the transaction with the rating write.
- **Parameterise everything.** The only interpolated SQL is built from validated whitelists or integer-cast ids (`score_{$id}` columns in `routes/songs.php`, the `$sortable` map, `$field` after an `in_array` check).
- **Input validation helpers** live in the ajax guard; ids are validated with `ctype_digit` before `(int)`.
- **No `mbstring`.** Use `strlen`, `strtolower`, `strcasecmp`. PCRE `\X` with `/u` works (hideandseek icons rely on it).
- **No composer, no npm, no build.** Vendored libraries are committed under `public/modules/<m>/vendor/` with their licence and README.
- **JS:** plain browser scripts, `const`/`let`, tabs, double quotes, semicolons; globals across scripts (load order matters). ss2 is the exception: native ES modules. Delegated listeners on `document` or a stable ancestor are preferred because content is injected after load.
- **DOM ↔ server contract through `data-*` attributes**: `data-field`, `data-song-id`, `data-album-card-id`, `data-artist-card-id`, `data-card-kind`, `data-card-id`, `data-sort-key`, `data-sort-type`, `data-sort-value`, `data-tooltip`, `data-tooltip-titles`. Tests and scripts both select on them; do not rename casually.
- **Add to class lists, never replace them** (`class="songCard card"`); tests match class lists loosely.

## Traps that have bitten before

1. **CSS has no `//` comments.** The parser swallows the next rule. Use `/* */`. Two tests forbid it.
2. **Nested-looking selector groups in the stylesheets.** `#songCards, #songCardModal {` and `.albumCard, .artistCard {` open one long block; a rule inside applies to every branch. Resolve a selector by walking *up* through every line of the group. A bare `.x .y` rule can lose to an id-prefixed rule elsewhere; the blind-rating hiding rules carry three id prefixes for exactly this reason.
3. **Editing a shipped migration is a silent no-op** on databases that already recorded it. New file, next number.
4. **Positional pairing in the song list**: nothing may ever be added as a child of `#songCards` or the table `<tbody>`.
5. **Numbers sent as JSON numbers** are not strings: use `ajaxNumericText`, not `ajaxTrimmed`, for scores, years, durations, positions.
6. **`0` is a real score.** Test `=== null`, `=== ''`, `.trim() === ""`, never truthiness.
7. **Statistics have exactly one implementation** (`musicScoreStats()`, population σ). Never recompute in JS or in a second PHP place.
8. **Blank values sort last in both directions**, decided before applying direction, in all three sort sites for the song list.
9. **Two poll cursors behave oppositely** (inclusive `>=` on `updated_at`, strict `>` on audit id). Do not harmonise.
10. **`proc_open` on Windows leaks the child** unless `bypass_shell => true`.
11. **`filemtime` cache stamps fail silently** (bare path) if the document root is not as expected.
12. **hideandseek base map must be repointed** after pasting any foreign map style (see `hideandseek.md`).
13. **Two modules, same function names**: hideandseek's ajax helpers (`ajaxInt` etc.) match music's. One module runs per request, but requiring both guards in one request is a redeclaration fatal.
14. **`$_SESSION['lang']` etc. are arrays keyed by module**, not scalars; always use the `*ModulePreference` helpers.
15. **Test case file order is filename order and the database is shared**: see `testing.md`.
