# Music module reference

Lookup companion to `music.md` (which explains *why*). Everything here is under `app/modules/music/` unless it starts with `public/`.

## Schema (after migrations 001–007)

All ids are `INTEGER PRIMARY KEY`. Foreign keys are enforced.

| Table | Columns | Notes |
|---|---|---|
| `artist` | `id` | id only; names live in `artist_alias` |
| `artist_alias` | `artist_id`, `name`, `is_actual` | unique `(artist_id, name)`; partial unique index: at most one `is_actual = 1` per artist |
| `song` | `id`, `year` (003), `duration` (004, whole seconds) | no title column. After 002 `artist_id` and `objective_note` are gone |
| `song_alias` | `song_id`, `name`, `is_actual` | same rules; indexed on `name` |
| `song_artist` | `song_id`, `artist_id` | many-to-many; unique pair; "first artist" = lowest `id` |
| `song_link` | `song_id` PK, `spotify_url`, `youtube_url`, `soundcloud_url`, `bandcamp_url`, `filepath`, `other_url` | one row per song, created on first link write |
| `song_relationship` | `variant_song_id`, `target_song_id`, `relationship_note` | created by 002, **referenced by no code**; indexes dropped in 006 |
| `album` | `artist_id` (nullable = various artists), `release_year` | |
| `album_alias` | `album_id`, `name`, `is_actual` | same rules |
| `album_track` | `album_id`, `song_id`, `song_alias_id` (nullable), `position` (nullable) | unique `(album_id, song_id)`; `song_alias_id` = which of the song's names this release credits; NULL = its actual name |
| `account` | `account_name` (unique), `password_hash`, `is_admin`, `lang` (default `'fy'`), `hue` (006, nullable), `blind_rating` (007, default 1) | |
| `account_song` | `account_id`, `song_id`, `score` REAL, `subjective_note`, `updated_at` (unix seconds) | unique pair; current state only |
| `rating_audit` | `account_id`, `song_id`, `field` (`score`\|`note`), `value`, `previous_value` (TEXT), `created_at` | append-only; polled by `id` |
| `invite` | `code` (unique), `created_at`, `created_by_account_id`, `used_at`, `used_by_account_id`, `revoked_at` | usable = both `used_at` and `revoked_at` NULL |
| `login_attempt` | `ip`, `attempted_at` | rate limiter |
| `schema_migrations` | `filename` PK, `applied_at` | |

**Name resolution idiom:** `(SELECT name FROM <x>_alias WHERE <x>_id = e.id ORDER BY is_actual DESC, id LIMIT 1)`. It falls back to the oldest alias when none is actual, and yields NULL when there are none (views render `artist.list.noName` / `album.list.noName`). Joined form: `LEFT JOIN song_alias st ON st.song_id = s.id AND st.is_actual = 1`.

**Effective song year** = `song.year`, else the earliest `album.release_year` over the albums it is on (computed in queries, never stored).

## Pages (all under `routes/`, GET unless noted)

| URL | File | Access | Notes |
|---|---|---|---|
| `/music` | `index.php` | public | landing, links to login |
| `/music/register` | `register.php` | public (signed-in → `/music/songs`) | form POST; invite needed once any account exists; the first account becomes admin; stores `activeLocale()` as the account's `lang` |
| `/music/login`, `/music/logout` (POST) | `login.php`, `logout.php` | public | login primes session: locale (if shipped), hue (`musicHueOf`), blind flag. Logout forgets hue and blind, then `logOut()`; redirects to `/music/songs` which bounces to login (TODO) |
| `/music/language`, `/music/colour`, `/music/settings` (POST) | same names | public (account written only if signed in) | nav menus. Redirect 303 to `return` if it matches `#^/music(/[a-z-]+)?$#`, else `/music`. `settings` treats an absent checkbox as off |
| `/music/songs` | `songs.php` | login | the main page; see below |
| `/music/artists`, `/music/albums` | `artists.php`, `albums.php` | login | stat-bearing lists, sorted in PHP via `listSort.php` |
| `/music/audit` | `audit.php` | login | 100 per page, `?before=<id>` keyset paging; non-admins may read |
| `/music/add-songs` | `addSongs.php` | admin | bulk import wizard |
| `/music/invites`, `/music/accounts` | `invites.php`, `accounts.php` | admin | POST-redirect-GET. Accounts: promote/demote (never self). Invites: create (default action) or `revoke` |

### `/music/songs` query parameters

`sort` (a `$sortable` key, `score_<id>`/`note_<id>` for each rater, or a stat key `average|deviation|median|mode|highest|lowest|rated`; anything else → `id`), `dir` (`desc`, else asc), `artist`, `album` (validated against the option lists; an album only counts if it belongs to the chosen artist, or has no artist when none is chosen; album wins over artist for filtering), plus card links `songCard`, `albumCard`, `artistCard` (read client-side). The page always renders **all** songs and hides filtered ones with `hidden`; filtering/sorting then happens client-side on the same DOM and mirrors itself into the address with `replaceState`.

`$globalData` produced: `isAdmin`, `raters` (own account first, then by id; each with `isMine`, `scoreClass`, `noteClass`), `sort`, `dir`, `artistOptions`, `albumOptions`, `filterArtist`, `filterAlbum`, `listHeading`, `ratingCursor` (`MAX(account_song.updated_at)`), `auditCursor` (`MAX(rating_audit.id)`), `songs` (one pivoted row per song: `score_<id>`/`note_<id>` columns built with `MAX(CASE WHEN account_id = N ...)`), `linkFields`, `songLinksBySong`, `albumsBySong`, `artistsBySong`, `trackAliases`, `listedAsBySong`, `columns`, `statColumns`, `blindRating`.

The per-rater column pivot and ORDER BY expressions are the only dynamic SQL; rater ids are cast to int.

Rendering is split: `views/songs.view.php` (table), `partials/songRows.php` (single derivation pass; see below), `partials/songCard.php` (card per row). Three `<script type="application/json">` blobs carry data to JS (`songArtistData`, `songAlbumData`, `songLinkFieldData`, `songTrackAliases`) plus `songGateSetting`, `songRatingCursor`, `songAuditCursor`.

### `songRows.php` contract

Requires `$globalData['songs']` (and optionally `raters`, `linkFields`, `songLinksBySong`, `albumsBySong`, `artistsBySong`, `listedAsBySong`, `filterArtist/Album`, `statColumns`, `blindRating`). Produces `$songRows[]`, each song augmented with escaped, ready-to-echo values: `hiddenAttr`, `artistHtml`/`albumsHtml` (comma-joined links carrying `data-artist-card-id` / `data-album-card-id`), `titleValue`, `titleTooltipAttr` (all names), `titleAliasedClass`, `displayYearValue`, `durationValue`, `linkChips`, `linkAbbrs`, embed ids, `ratings[raterId]` (value, empty/gated classes, pills, title attr), `stats` (from `musicSongStatFields`) and `statCells[key]`. Also defines `$raterLabels`, `$visibleCount` and the label variables used by both view and card. Gate decision lives here for first render: `$notesGated` / `$scoresGated` = `blindRating` ∧ reader has no note / no score on that song; statistics cells (except `rated`) follow the score gate.

## Ajax endpoints (`ajax/`, route prefix `/music/ajax/`)

All POST + JSON + CSRF header. "Admin" = `requireMusicAdminJson`. Business results are HTTP 200 with `status`.

| Route | File | Who | Request → response |
|---|---|---|---|
| `artist-alias` | `artistAlias.php` | admin | Array of `{provided_name, og_name?, artist_id: id\|'new', is_actual, group?, store_name?, also_alias_provided_name?}` → array of `{provided_name, artist_id, artist_name, status, message, songs[]}`. One transaction. `'new'` creates an artist; rows sharing `group` (default the provided name) share one new artist. `store_name:false` resolves without storing a spelling. Returns the artist's songs for the song-step dropdown |
| `song` | `song.php` | admin | Array of `{artist_id, title, song_id: 'new'\|'skip'\|id, og_name?, also_alias_provided_name?}` → results with `song_id`, `song_alias_id` (when the song is listed under a non-actual alias), `status` ∈ ok/duplicate/skipped/error. Duplicate = same artist + same name |
| `song-edit` | `songEdit.php` | admin | `{id, field:'title', value}`: rename. Refuses empty or clashing (same artist, other song) names; promotes an existing alias, else renames the actual alias (no history kept: TODO decision) |
| `song-artist`, `song-album` | `songArtist.php`, `songAlbum.php` (share `songJoin.php`, which is **not a route**) | admin | `{song_id, artist_id\|album_id, action: add\|remove}` → `ok`/`duplicate`/`error` |
| `song-link` | `songLink.php` | admin | `{song_id, field ∈ six columns, value}`; Spotify/YouTube URLs are reduced to the bare id, empty clears |
| `song-year`, `song-duration` | `songYear.php`, `songDuration.php` | admin | `{song_id, value}`; year digits only; duration `M:SS` (seconds 00–59) or whole seconds; empty clears |
| `song-rating` | `songRating.php` | any account | `{id, field: score\|note, value}` → `{status, value, message, stats}`; writes only the caller's own `account_song` row (upsert) plus a `rating_audit` row **when the value changed**, in one transaction. Score accepts `,` decimals; non-numeric → status error |
| `song-rating-poll` | `songRatingPoll.php` | any account | `{since, sinceAudit}` → `{cursor, changes[], stats[], auditCursor, events[]}`; see cursor rules in `music.md`. `events` ≤ 50 per tick, each `{id, account, name, songId, songLabel, field, value, previousValue, mine}` |
| `album` | `album.php` | admin | Array of `{provided_name, album_id: 'new'\|id, og_name?, is_actual, artist_id?, release_year?, also_alias_provided_name?, tracks[{song_id, position, song_alias_id}]}`; existing albums keep artist/year when the import omits them (COALESCE); existing tracks are updated, not duplicated |
| `album-card` | `albumCard.php` | any account | `{album_id, sort?, dir?, who?}` → `{status, html}` (+ `trackAliases` for admins). Renders `partials/albumCard.php` server-side |
| `artist-card` | `artistCard.php` | any account | `{artist_id, sort?, dir?, who?}` → `{status, html}` |
| `album-options` | `albumOptions.php` | admin | `{}` → `{artists[], songs[]}`, the whole catalogue for the card's edit dropdowns (fetched once per page) |
| `album-edit` | `albumEdit.php` | admin | `{album_id, field: name\|artist\|year, value}`; rename promotes/creates an alias, keeping the old one |
| `album-track` | `albumTrack.php` | admin | `{album_id, song_id, action: add\|remove\|position\|alias, position?, song_alias_id?}`; `add` returns the song's aliases |
| `artist-edit` | `artistEdit.php` | admin | `{artist_id, field:'name', value}` |

Guard helpers available in endpoints: `requireSongJson($db, $id, $extra)`, `requireAlbumJson(...)` (emit a 200 `{status:'error', message}` and exit).

## Shared PHP helpers

| File | Functions |
|---|---|
| `db.php` | returns `$db` (test vs live file) |
| `migrate.php` | `runMusicMigrations($db)` |
| `auth.php` | `musicAccount($db)` (cached row), `musicIsAdmin`, `requireMusicAccount` (boots sessions whose account vanished), `requireMusicAdmin($db, $redirect)`, `requireMusicAccountJson`, `requireMusicAdminJson` |
| `stats.php` | `musicScoreStats($scores)` → `rated, average, median, deviation (population), modes[] (empty when all distinct), lowest, highest`; `musicSongStatFields($scores)` → finished strings incl. `modeSort`; `musicStatsWho($requested, $rows)` validates the "statistics for" choice against raters with something rated |
| `format.php` | `scoreText()` (2 dp, trailing zeros trimmed), `musicDuration()` (`M:SS`, minutes never roll into hours), `musicSongLabel()` (`Artist — Title`) |
| `links.php` | `songLinkFields()`, `songLinkHref()`, `albumSongsHref()`, `spotifyTrackIdIn()`, `youtubeVideoIdIn()` |
| `hue.php` | `MUSIC_HUE_DEFAULT` (240), `musicHueFor($id)` = `(id*73)%360`, `musicActiveHue()`, `musicHueOf($id, $stored)`, `musicReadHue($raw)` (0–359 or null) |
| `blindRating.php` | `musicBlindRating()` (session pref, default on), `musicBlindRatingOf($stored)` |
| `listSort.php` | `musicListSort`, `musicListDir`, `musicListStatColumns`, `musicListSorted`, `musicListHeaders` |
| `rateLimit.php`, `inviteCode.php` | login throttle; invite code generation/format/normalise |
| `ajaxGuard.php` | see `architecture.md`; plus `ajaxInt/Text/Trimmed/NumericText`, `requireSongJson`, `requireAlbumJson` |

`notes.txt` is the original design scratchpad (composition/version/cover model that was *not* built); it is excluded from the deploy and is background reading only.

## Views and partials

`views/partials/`: `head.php` (stamps `--hue` on `<html>`), `nav.php` (tabs; hue picker, ⚙ settings, 🌐 language, account menu; loads `nav.js` and `colour.js`), `foot.php` (includes `tooltip.php`), `tooltip.php` (`#musicTooltip` + `tooltip.js`), `songCard.php`, `songRows.php`, `albumCard.php`, `artistCard.php`, `albumCardModal.php` (the one shared modal; loads `cardLink.js` then `albumCard.js`), `scoreStats.php` (per-rater table), `scoreGraph.php` (inline SVG), `scorePresentation.php` (`scoreColourAttr`, `scoreRaterColours`, `scoreStatCells`, `scoreWhoSelect`, `scoreWhoName`, `scoreActiveRaters`).

**Score colour ramp:** `hsl(clamp(score,0,10) * 12, 75%, 55%)` (red 0 → green 10). Implemented twice on purpose: `scoreColourAttr()` in PHP and `scoreColour()` in `songs.js`. Change both. **Rater colour:** `hsl(<that account's hue>, 70%, 62%)`.

**Graph geometry** (`scoreGraph.php`): fixed 528×270 viewBox, paddings (L38/R10/T14/B8 or 28 with track numbers), y = 0..10, dots r = clamp(slot/4, 2, 5.5), labels at most ~18, polylines split at gaps, one invisible `rect.albumGraphColumn` per song carrying the multi-line tooltip (`data-tooltip`, with an SVG `<title>` fallback). Inputs the including card must set: `$graphSongs`, `$graphScores` (`[song_id][account_id] => score`), `$graphRaters`, `$graphColours`, `$graphSortKey`, `$graphSortDir`, `$graphNaturalLabel` (null disables "Album order"). Orders offered: album (if natural label), year (if any song has one; becomes default), title, average, deviation, median, highest, lowest, mode, rated, duration, `rater_<id>`.

## Language catalogue

Keys are dotted and namespaced by area: `nav.*`, `ajax.*`, `status.*`, `register.*`, `login.*`, `accounts.*`, `invites.*`, `artist.*` (`artist.result.*`, `artist.card.*`, `artist.list.*`, `artist.column.*`), `song.*` (`song.result.*`, `song.link.*`, `song.list.*`, `song.column.*`, `song.extras.*`), `album.*` (`album.result.*`, `album.card.*`, `album.list.*`, `album.column.*`), `addSongs.*`, `audit.*`, `colour.*`, `settings.*`, `language.<code>`. Part files: `common` (nav, ajax, status, language names, colour, settings), `accounts` (register/login/accounts/invites), `artists`, `songs`, `albums`, `audit`. A key used in JS (via `langStrings`) must still appear as a literal `t('key')` in a `.js`/`.php` file under the music module or its public dir, or the orphan test fails.

## CSS (`public/modules/music/css/styles.css`, ~1670 lines, starts with a UTF-8 BOM)

Single stylesheet. Structure to know: `:root` (colour scheme dark, `--hue`, button fills), `body` (min-content width, gradient), shared button group, nav, login/register forms, song list table and sticky header, rater columns, gate pill rules (~l.523–560, three id prefixes), shared card chrome (`.card`, `.cardModal*`, ~l.803), song card/modal blocks (`#songCards, #songCardModal`), album+artist card block (`.albumCard, .artistCard`, ~l.1347), toasts, tooltip box (~l.1642), artist-card extras (~l.1661). Remember the comma-group rule and the no-`//` rule in `conventions.md`.
