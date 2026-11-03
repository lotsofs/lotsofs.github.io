# Tasks

Detail for the **Defects and tasks** and **Possible, not planned** rows in [TODO.md](TODO.md), grouped by module in the index's order. Open decisions are in [decisions.md](decisions.md); finished items go to [DONE.md](DONE.md).

Dependencies between the plans:

- hideandseek's `001` freezes the day the module first ships (MIG). CAS, and the table PIN needs, are edits in place before that and table rebuilds after, so they come first. PRV and WHO also want doing before players arrive. Shipping it means taking it off `$unshippedModules` in `build-deploy.php`.
- LFT's song-rename half waits on ALI: keeping every old name is clutter until aliases can be deleted.
- NAM can only be fixed through the site once ALI lands; until then it means editing the database by hand.
- PAR waits on JST: there is nothing to run a JS test with.

## PWR — No password reset

Music has no password reset. A user who forgets their password stays locked out until someone edits `account.password_hash` in the live database file (mode `0640`) by hand. hideandseek copied music's account system and has none either. Read in the code.

**Plan.** The site has no email, so reset has to go through an admin: an admin action on the accounts page issues a one-time reset code (like an invite code; `inviteCode.php` already generates and normalises those), and the user redeems it on a reset page to set a new password. Hash with `hashPassword()`, never `password_hash()` directly (see `CLAUDE.md`). Clear that account's login rate-limit rows on success. Music needs a new migration for the code table, `010` or later.

**Verify.** A test that issues a code, resets, logs in with the new password, and refuses the same code a second time.

**Docs.** `music-reference.md` (schema, routes); `hideandseek-reference.md` if hideandseek gets it too.

## TRN — de and fy translations are unreviewed

Every music key is translated into `de` and `fy` (`php tests/i18n-coverage.php`), but all of it is first-draft quality, written by Claude and never checked by a native speaker. `fy` is the site default, so it is what most users read. Start with the invented score-column abbreviations: `Sc.` (en), `Wert.` (de) and `Wurd.` (fy), where `Wurd.` can read as the start of another word. `lang/de/*.php` marks uncertain choices with `// ?` (Interpret or Künstler, Wertung or Punkte, Titel or Tracks, Widerrufen, and others). English stays the source catalogue and the test language. Read in the code.

**Plan.** A native speaker reads each locale in the running site rather than in the PHP files, since context and width matter (the score columns are 2em wide). Fixes go in the `lang/<locale>/*.php` part files; each `// ?` goes once its choice is settled.

**Verify.** `php tests/i18n-coverage.php` stays clean; no `// ?` is left in `lang/de`.

**Docs.** None, unless a term choice becomes a rule worth recording in `music.md`.

## ALI — Aliases cannot be deleted or promoted

Album, artist and song cards can add an alias (a former or alternative name, shown on the "also known as" line) but cannot delete one or make an old one current again. A typo'd rename stays on the card for good, and going back to an earlier name means typing it again. No code deletes from `album_alias`, `artist_alias` or `song_alias` outside migration `002`. Read in the code.

**Plan.** In a card's edit mode, a delete button and a "make current" button per alias, backed by an endpoint per entity or one dispatching on kind. Refuse to delete the current name (`is_actual = 1`). Promoting must clear the old `is_actual` before setting the new one, because the partial unique index allows at most one. Admin-only, like the card's other edits.

**Verify.** Tests for delete, for refusing to delete the current name, and for promote; the lists and the songs page show the promoted name.

**Docs.** `music-reference.md` (endpoints), `music-frontend.md` (card edit mode), `music.md` if the alias-pattern bullet needs it.

## NAM — Wrong song names and album listings in the data

Every song in `data/music.sqlite` that has a second `song_alias` name was judged wrong by the owner on 2026-11-03, who will fix them by hand. Which part of each is wrong (the current title, the second name, or the album listing) was not said. Read with a read-only query on 2026-11-03.

Album tracks whose `album_track.song_alias_id` points at a name that is not the song's current one, so filtering the song list to that album retitles them (song id, current title, listed as, album and track):

- 11 *Will Of The People*, as "Will of the People" (Will Of The People #1)
- 17 *Kill Or Be Killed*, as "Kill or Be Killed" (Will Of The People #7)
- 28 *Get up and Fight*, as "Get Up and Fight" (Simulation Theory #8)
- 33 *(Drill Sergeant)*, as "Drill Sergeant" (Drones #2)
- 38 *(JFK)*, as "JFK" (Drones #7)
- 60 *United States of Eurasia (+Collateral Damage)*, as "United States of Eurasia" (The Resistance #4)
- 64 *I Belong to You (+Mon Coeur S'Ouvre a Ta Voix)*, as "I Belong to You" (The Resistance #8)
- 65 *Exogenesis: Symphony Pt. 1 (Overture)*, as "Exogenesis: Symphony Part 1 (Overture)" (The Resistance #9)
- 66 *Exogenesis: Symphony Pt. 2 (Cross-pollination)*, as "Exogenesis: Symphony Part 2 (Cross-Pollination)" (The Resistance #10)
- 67 *Exogenesis: Symphony Pt. 3 (Redemption)*, as "Exogenesis: Symphony Part 3 (Redemption)" (The Resistance #11)
- 82 *Time is Running Out*, as "Time Is Running Out" (Absolution #3)
- 89 *Butterflies & Hurricanes*, as "Butterflies and Hurricanes" (Absolution #10)
- 99 *Plug in Baby*, as "Plug In Baby" (Origin of Symmetry #5)
- 118 *Hate This and I'll Love You*, as "Hate This & I'll Love You" (Showbiz #12)

Songs whose second name only swaps the straight apostrophe for a curly one, with no album listing them under it:

- 14 *Won't Stand Down* (Won’t Stand Down), on Will Of The People
- 16 *You Make Me Feel Like It's Halloween* (You Make Me Feel Like It’s Halloween), on Will Of The People

**Plan.** The owner corrects each by hand. The site can rename a song (`song-edit` promotes a name the song already has, or overwrites the current one), but it cannot delete a name or clear a track's listed-as name. That is ALI, so either do ALI first or edit the database directly. The query ran against the local `data/music.sqlite`; check that the host's file holds the same before fixing there.

**Verify.** Rerun both queries, `album_track` joined to `song_alias` where `is_actual = 0` and songs with any non-current name, and get only what the owner means to keep.

**Docs.** None.

## LGO — Logout takes two redirects

`routes/logout.php` always redirects to `/music/songs`, which, with nobody signed in, redirects again to `/music/login`. Read in the code.

**Plan.** Redirect to `/music/login` directly. Keep it unconditional, so a failed CSRF check lands there too.

**Verify.** The logout test in `tests/cases/accounts.php` asserts only the 302; add an assertion on `Location: /music/login`.

**Docs.** `music-reference.md` (the login/logout row).

## REG — Registration misreports database failures

`routes/register.php` inserts the account and spends the invite in one transaction, and its `catch (Throwable $e)` turns every failure into `register.error.badInvite`. A real database error (a locked file, a full disk, a schema mismatch) reaches the user as "invalid invite" and is logged nowhere. hideandseek's `register.php` has the same shape. Read in the code.

**Plan.** Throw a dedicated exception for the already-spent invite and catch only that as a bad invite; let anything else reach normal error handling. Same change in both modules.

**Verify.** The existing invite tests still pass; a forced failure (a temporarily renamed table, say) no longer reads as a bad invite.

**Docs.** None.

## REN — Rename the album card modal

`#albumCardModal`, `partials/albumCardModal.php` and `js/albumCard.js` host the artist card too, so their names undersell them. Read in the code.

**Plan.** Rename to `#cardModal`, `partials/cardModal.php` and `js/cardModal.js`. Mechanical but wide: the views that include the partial, the stylesheet, `songs.js`, and assertions in `tests/cases/albums.php`, `artistcard.php`, `catalogue.php` and `songs.php`. A change of its own, so nothing else hides in the diff.

**Verify.** The suite; `grep -rn albumCardModal app public tests docs` finds nothing.

**Docs.** `music.md` (cards section), `music-frontend.md`, `music-reference.md`.

## LFT — Leftovers from the 2026-09-25 audit

Loose ends from that audit. Read in the code.

- **Song renames keep no history.** `ajax/songEdit.php` promotes an alias the song already has, and otherwise overwrites the current one. `albumEdit.php` keeps the old name instead. Doing the same for songs would be consistent, but every rename would leave a permanent alias until ALI makes them deletable.
- **Two SELECTs are written out twice**: the album header in `routes/albums.php` and `ajax/albumCard.php`, and the audit row in `routes/audit.php` and `ajax/songRatingPoll.php`. Sharing them means a shared SQL fragment, which cuts against "SQL lives in route and ajax files" (`conventions.md`).

**Plan.** After ALI, make `songEdit.php` keep the old name as an alias. For the SELECTs, decide whether a shared fragment is worth the exception; if not, drop that half of this item.

**Verify.** A test that a rename leaves the old name among the song's aliases.

**Docs.** `music-reference.md` (the `song-edit` row points here); `conventions.md` if the SQL rule changes.

## BND — Bandcamp does not embed

Bandcamp links render as plain links, while Spotify, YouTube and SoundCloud embed. An embed needs the numeric id in Bandcamp's `EmbeddedPlayer` URL, which a normal page URL does not contain, and Bandcamp's oEmbed API answered with a bot-detection page when called from the dev environment. The link-only rendering is read in the code; the oEmbed refusal was seen during development (date not recorded).

**Plan.** Either have the admin paste Bandcamp's Share/Embed URL, from which the id can be pulled by regex (beside `spotifyTrackIdIn()` in `links.php`), or retry oEmbed from the real host, which may not be challenged.

**Verify.** A pasted embed URL stores the id, and the card shows the player.

**Docs.** `music.md` (the `song_link` bullet says Bandcamp is link-only, and why), `music-reference.md`.

## GRD — Song table lines are thicker in the header at some display scales

The song table's column lines are box-shadows in the header (a sticky header cannot use borders, see `music.md`) but borders in the body, and the two round differently at a fractional display scale. Some lines come out 2 device pixels wide in the header and 1 in the body. They never sit in a different place: the first pixel always agrees. Reproduced 2026-11-03 in headless Chrome, comparing a header pixel row with the first body row. With the default columns it shows at 150% and 175%, but not at 100% or 125%. Which lines it hits depends on where the boundaries fall, so hiding columns with the column picker can bring it out at 125% too. The rater-edge check logged in DONE.md the same day found no differences at four scales, on different data. The rater edges and the note columns' grey line are already shadows in both, for this reason.

**Plan.** Draw the body's grid lines as inset shadows too, and drop the `td` borders. A `box-shadow` list does not merge across rules, so every rule that gives a `td` a shadow (rater edges, the last row's bottom edge, the statistics block's left edge and its column-picker chain, the left-edge chain on the fixed columns) has to carry the grid lines in its own list.

**Verify.** Header against body, line for line, at 100%, 125%, 150% and 175%, with the default columns and with some hidden.

**Docs.** `music.md` (the sticky-header trap and the rater-edge note in "Cards").

## SWH — Statistics for does not reach the graph or song card

On the album and artist cards, the "Statistics for" control re-reads the track table (album) or the album table (artist) as one rater instead of everyone. It does not change the graph, and the song card has no such control. Deliberate for now: the graph has its own per-rater orders, the per-rater stats table is already the breakdown, and the song card has no statistics table. Read in the code.

**Plan.** None unless it is asked for. Then decide first what "one rater" means for the graph (dim the others, or hide them).

**Docs.** `music.md` (cards) if it changes.

## TOA — Own ratings pop toasts

`TOAST_SHOW_OWN_EVENTS` in `public/modules/music/js/songs.js` is `true`, so every score or note a rater types pops a toast at themself. Deliberately left on in production (owner, 2026-09-20).

**Plan.** Flip it to `false` only when the owner says so.

**Verify.** Rating a song shows no toast to the rater; other raters still get one.

**Docs.** `music-frontend.md` (the live-updates paragraph points here).

## AFL — Audit page links and filters

`/music/audit` shows each song as plain text and cannot be filtered by rater or song. A song card can be linked now (`/music/songs?songCard=<id>`, see the linking bullet in `music.md`), which gives the names something to point at. Paging (100 rows a page) is already in place. Read in the code. Possible, not planned.

**Plan.** Link each song name to its card; add rater and song filters to the audit query as query parameters, kept across the paging links.

**Verify.** A test that a filter narrows the rows and survives paging.

**Docs.** `music-reference.md` (the audit route).

## CAS — hideandseek 001 breaks the never-cascade rule

The site rule (`conventions.md`, owner 2026-10-06) is that tables declare no `REFERENCES` and no `ON DELETE`. hideandseek's `001_create.sql` predates it. `poi_category.game_map_id` and `poi.poi_category_id` carry `ON DELETE CASCADE`, which `routes/gameMaps.php` and `routes/importPois.php` rely on, and a test in `tests/cases/hideandseek.php` pins that deleting a map takes its POIs. `invite.created_by_account_id` and `invite.used_by_account_id` carry a bare `REFERENCES account(id)`, which with `foreign_keys = ON` would refuse to delete an account that ever issued or used an invite. Read in the code.

**Plan.** While `001` is unshipped this is an edit in place: drop the constraints from `001`, replace each cascade with explicit deletes in those routes (POIs, then categories, then the map, in one transaction), and delete the disposable `data/hideandseek_test.sqlite`. The test keeps its assertion; the code satisfies it instead of the schema. Once `001` ships, the same change needs a table rebuild, which is why this comes before MIG.

**Verify.** The suite, including the delete-map test. `PRAGMA foreign_key_list` is empty on every hideandseek table.

**Docs.** `conventions.md` (drop hideandseek from the "older tables" list), `hideandseek-reference.md` (schema, and the line saying a map delete removes its POIs).

## PRV — No privacy page for hideandseek

hideandseek has accounts and a session cookie, kept for 399 days once signed in, and sends map-tile requests (the visitor's IP and referrer) to OpenFreeMap. No page says so. Tile requests already wait until the visitor presses a button that says it will contact a third party. Read in the code. Wanted before the module is opened to players.

**Plan.** A privacy page (route, view, a link from every page) covering accounts, the session cookie, the tile requests, and pins once PIN stores location data. Serving a `.pmtiles` extract and pointing `sources.openmaptiles.url` in `json/mapStyle.json` at it would drop the third party and shorten the page, but not replace it. Music's embeds raise the same question (EMB), so one shared page may serve both.

**Verify.** The page is reachable from every hideandseek page, and what it lists matches the browser's network panel while the map loads.

**Docs.** `hideandseek.md` (what the module sends where), `hideandseek-reference.md` (route).

## WHO — Delete the whoami example endpoint

`ajax/whoami.php` was written as a worked example of `ajaxGuard.php`'s shape, to be copied and then deleted. Real endpoints exist now (`buurten.php`, `importPois.php`, `exportPois.php`), so it has done its job. The guard tests in `tests/cases/hideandseek.php` (401 signed out, 405 on GET, 403 without a token, 400 on a bad body) use it as their target. Read in the code.

**Plan.** Point those guard tests at a real endpoint, then delete `whoami.php` and its line in `routes.php`.

**Verify.** The suite; `grep -rn whoami app public tests` finds nothing.

**Docs.** `hideandseek.md` (its last bullet), `hideandseek-reference.md` (endpoint table).

## PIN — Map pins are not saved

`hnsMarkers` in `public/modules/hideandseek/js/map.js` holds `{lat, lng, marker}` for each pin placed by clicking the map, and nothing else holds them: a reload loses every pin, and the ◎ overlay, which measures from the latest pin, starts over. Read in the code.

To settle first: are pins shared between the accounts on a game map or private to whoever placed them, and how long are they kept? A pin for "where a person is hiding" is location data about an identified user, which also feeds PRV.

**Plan.** A `pin` table in `001` while it is unshipped, with a `game_map_id` (GMP) and no `REFERENCES` (CAS), and one ajax endpoint dispatching on an action (add, move, delete), shaped like `routes/accounts.php`. `hnsMarkers` loads from it with the page.

**Verify.** A test that adds, lists and deletes pins through the endpoint and refuses another map's pins; by eye, pins survive a reload.

**Docs.** `hideandseek.md` (the ◎ paragraph says pins live only in the page), `hideandseek-reference.md` (schema, endpoint).

## GMP — Only imported POIs attach to a game map

`game_map` is what game data hangs off, and so far only `poi_category` (and through it `poi`) carries a `game_map_id`. `hnsLoadedGameMap()` in `gameMap.php` is the hook: whatever comes next (pins, play-area bounds, a start point) should take a `game_map_id`. The CBS gemeenten, wijken and buurten are the deliberate exception: global, owner's call (2026-10-06); a map points at them through `gemeente_code`. Separately, `routes/index.php` opens the map on a hardcoded centre in Groningen (`mapCentre`) rather than on the loaded map's POIs or gemeente. Read in the code.

**Plan.** Give each new game table a `game_map_id` as it is built (PIN first). For the opening view, fit the map to the loaded map's POIs (they are already in the page), else to its gemeente, else keep Groningen.

**Verify.** By eye: a map whose POIs are elsewhere in the country opens on them, and with no map loaded the page still opens on Groningen.

**Docs.** `hideandseek.md` (the game map bullet), `hideandseek-reference.md`.

## MIG — hideandseek 001 freezes when it first ships

hideandseek's `001_create.sql` has not shipped (owner, 2026-10-06; the deploys of 2026-10-19 and 2026-11-02 left the whole module out). New tables therefore go straight into it rather than into a `002`, and applying an edit means deleting the disposable `data/hideandseek_test.sqlite`. The day the module first deploys, music's freeze rule applies: `001` is never edited again, and the next schema change is `002`.

**Plan.** Before that deploy, land what is cheaper in `001` (CAS, PIN's table). The deploy itself is taking `hideandseek` off `$unshippedModules` in `build-deploy.php`, which puts the module back in `_deploy/` and its `$mustExist` checks back in force. After it, confirm the host's hideandseek database lists `001_create.sql` in `schema_migrations`, then close this item.

**Verify.** `schema_migrations` on the host.

**Docs.** `hideandseek.md` (the "has not shipped" sentence), `conventions.md` (the `hideandseek_test.sqlite` exception, whose reason is applying edits to `001`), `CLAUDE.md` if it names the boundary.

## GEO — The geo database is copied by hand

`hideandseek_geo.sqlite` (gemeenten, wijken, buurten) is built at home by `php tools/import-buurten.php` (about 40 s, 31 MB) and uploaded by hand to `/home/lotsofs/data`. Nothing checks that the host has the file or that it holds the current year, and CBS publishes a new dataset every year. A game map's `gemeente_code` is a CBS code, and CBS retires and merges gemeenten, so a stored code can stop existing after a refresh; the game maps page then shows it as plain text. Read in the code. It matters once hideandseek ships (MIG).

**Plan.** Rerun with `--year=` when CBS publishes, and recopy. Optionally store the dataset year in the file and show it beside the CBS credit under the map, so a stale file is visible.

**Verify.** On the host, `/hideandseek/ajax/buurten` returns buurten for a map with a gemeente.

**Docs.** `hideandseek.md` (the geo bullet) if the year is stored.

## LOC — hideandseek has one locale

hideandseek has one locale, `en` (`config.php`), and `nav.php` renders the language menu only when there is more than one. The route and the menu are already built. Read in the code.

**Plan.** Add the code to `locales` in `config.php` and a `lang/<code>.php` overlay with part files, as music has. `tests/cases/strings.php` and `tests/i18n-coverage.php` check music only; widen them in the same change.

**Verify.** The menu appears, and switching changes the page's language.

**Docs.** `hideandseek.md` (the one-locale bullet).

## PAR — No tests for the POI parser or the map

`js/poiImport.js` parses Overpass JSON and XML in the browser, and the suite runs no JS, so only the import endpoint's validation is tested. The same goes for everything `map.js` draws (POIs, overlays, buurten): the map is only ever checked by eye. Read in the code.

**Plan.** Waits on JST. Until then, check the parser by hand against `docs/exampleImport.txt` after changing it.

**Docs.** `testing.md` (what the suite cannot see).

## BUS — No bus stops or routes on the base map

Every base-map label is hidden, bus stops included, so transit has to arrive as imported POIs. Bus lines cannot come from the tiles: a route is a `type=route` relation, and the tileset carries none for buses (only bus-only roads, `busway` and `bus_guideway`). Overpass can supply route geometry, but both public mirrors time out too often to rely on. See `hideandseek.md` for how the tiles were checked.

**Plan.** Only if wanted, as deliberate work: bake routes from a Geofabrik extract or a GTFS feed into a file the page loads, as the buurten are baked into `hideandseek_geo.sqlite`.

**Docs.** `hideandseek.md`.

## FXR — Exchange rates are frozen

The exchange-rates page and the home page show rates from checked-in JSON dated 2025-10-04, and the home page shows them without a date. Both fetch paths in `app/modules/main/exchangeRatesController.php` (`file_get_contents` and curl) are commented out, and the pages' JS reads `public/modules/main/json/exchangeRates.json` directly. Read in the code.

**Plan.** A server-side job that refreshes that JSON file (the host's cron, if it has one), with `cacert.pem` handling restored. At the least, print the date beside the numbers.

**Verify.** The JSON's date moves; the home page shows a date.

**Docs.** `other-modules.md`.

## NAV — ss2 nav links to pages that 404

The ss2 nav (`views/partials/nav.php`) links `/ss2/` and `/ss2/1` to `/ss2/7`, none of which is in `ss2/routes.php`, so they 404. Only `/ss2/11` to `/ss2/18` are routed, and only 11 to 13 have data (`public/modules/ss2/json/` holds `00`, `11`, `12` and `13`). Read in the code, not clicked through.

**Plan.** Remove or disable the nav entries that have no route, or add routes as their data arrives. Decide what `/ss2/` should show.

**Verify.** Every ss2 nav link answers 200. `tests/cases/pages.php` could walk them.

**Docs.** `other-modules.md`.

## SLG — Logins for ss2

`session.php` is module-agnostic, so ss2 could have accounts through `sessionScope('ss2')` and its own account table, as hideandseek did. Possible, not planned.

**Plan.** Copy hideandseek's account system, or wait for SHR to decide what is shared first.

**Docs.** `other-modules.md`.

## TAB — Bass tabs are held back from the deploy

The owner took the module out of the build for now on 2026-11-02, by adding `basstabs` to `$unshippedModules` in `build-deploy.php`. It has never shipped: the 2026-11-02 build has no `basstabs` folder, and `/basstabs` is a 404 on the host (reproduced 2026-11-02).

**Plan.** Take `basstabs` off `$unshippedModules`, which puts the module back in `_deploy/` and its `$mustExist` checks back in force. Put this entry back under Unreleased in `CHANGELOG.md`, under `### Added`, updated for any sheets added since:

> Bass tab sheets at lotsofs.com/basstabs: a list of songs, each opening a sheet of notes for 5-string bass, headed with the song's title, artist and key, with the name of each note under it and fretboards in greys beside the parts they belong to, each showing a key, the notes of its chord, and a hand position to play it from. Each sheet prints on A4 and fits a phone screen. There are seven so far: My Favourite Game by The Cardigans, Amsterdam by Nothing but Thieves, Bring Me to Life by Evanescence, Love Myself by Rondé, whose sheet is its riff and a fretboard for each of its four chords, Monday Morning by Melanie Fiona, which ends with the song's lyric cues in order, Inside by Stiltskin, and Creep by Radiohead. Sheets can show rests and slides. A sheet longer than a page carries on onto the next one without splitting a part in two.

**Verify.** `php build-deploy.php` (run by the owner) passes its checks, and `/basstabs` answers on the host after the deploy.

**Docs.** `basstabs.md`, `architecture.md` and `CLAUDE.md`, which all name it as unshipped.

## CFG — The site-wide config file is loaded by nothing

`app/config.php` returns an empty array and nothing requires it; settings live in each module's `config.php`, read through `moduleConfig()`. It still ships: `build-deploy.php` lists it in `$mustExist`, and its header comment says to leave the host's copy alone "if it ever holds a real secret". Read in the code.

**Plan.** Delete it, with its `$mustExist` line and that comment. If a site-wide setting turns up later, `require` a config file from `util.php` again. A secret belongs in `data/config.php` (never deployed) or an environment variable, with a `config.example.php` beside it.

**Verify.** The suite; `php build-deploy.php` (run by the owner) passes its checks.

**Docs.** `architecture.md` (the file tree line points here).

## MGR — No command to run migrations

Migrations apply as a side effect of the first request after a deploy (`runMusicMigrations()` runs at the top of every music route and ajax file; hideandseek does the same). There is no way to apply one ahead of traffic, or to confirm it succeeded short of using the site. Read in the code.

**Plan.** Find out whether the host offers shell access. If it does, a CLI entry point under `app/` (outside the web root) that runs each module's migrations and prints `schema_migrations`; if not, an admin-only page doing the same.

**Verify.** Running it twice applies nothing the second time and lists the same files.

**Docs.** `architecture.md` (migrations, deploy), `CLAUDE.md` (Commands).

## JST — JavaScript tests

The suite drives PHP over HTTP and runs no JavaScript. The pure functions in `public/modules/music/js/addSongs.js` are testable as they stand, but there is no Node on this machine. Possible, not planned.

**Plan.** Install Node, or have the harness drive a headless browser. Start with `addSongs.js` and hideandseek's parser (PAR).

**Docs.** `testing.md`, `CLAUDE.md` (Commands).
