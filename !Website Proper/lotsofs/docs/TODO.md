# TODO

Open tasks and unresolved design decisions. Every item has a three-letter code in brackets for reference. Finished items are deleted, not struck through; git history keeps them. Environment traps live in `conventions.md`, not here.

---

# Tasks

## Before anything goes to prod

- [ ] **[TOA] `TOAST_SHOW_OWN_EVENTS` in `public/modules/music/js/songs.js` is `true`**, so every score or note a rater types pops a notification at themself. Deliberately left on in production (owner, 2026-09-20). Flip to `false` only when told to.
- [ ] **[SEC] Check the session cookie `Secure` flag engages.** `session.php` reads `$_SERVER['HTTPS']`; behind a proxy or CDN PHP may see plain HTTP, and the cookie would ship without `Secure`. Check whether the host sets `HTTP_X_FORWARDED_PROTO`.
- [ ] **[CFG] `app/config.php` (`return [];`) ships but nothing loads it.** Settings live in per-module `config.php` files (`moduleConfig()`). Either delete it, or give it a purpose: a site-wide setting means adding the `require` back; a secret belongs in `data/config.php` (never deployed) or an env var, with a `config.example.php`.

## Music module

- [ ] **[TRN] `de` and `fy` have never been reviewed by a native speaker.** Every key is translated (`php tests/i18n-coverage.php`) but all of it is first-draft quality, written by Claude. Start with the three invented score-column abbreviations: `Sc.` (en), `Wert.` (de), `Wurd.` (fy); `Wurd.` could be read as the start of another word. `lang/de/*.php` has a few `// ?` marks on uncertain choices (Interpret/Künstler, Wertung/Punkte, Titel/Tracks, Widerrufen). `fy` is the site default; English stays the source catalogue and the test language.
- [ ] **[ALI] Aliases can be added but never deleted or promoted from the UI** (album, artist, and song cards). A typo'd rename stays in the "also known as" line forever; promoting an old name back means retyping it.
- [ ] **[SWH] The "Statistics for" control does not reach the song card or the graph.** It re-reads the album card's track table and the artist card's album table as one rater instead of everyone. Deliberate: the graph has its own per-rater order, the per-rater stats table is the breakdown, and the song card has no statistics table.
- [ ] **[REN] Rename `#albumCardModal` / `albumCard.js` to something kind-neutral** (`#cardModal`, `cardModal.js`); they host artist cards too. Mechanical: the partial, CSS, several test assertions and the docs. Do it as its own change, with the suite as the check.
- [ ] **[PWR] Password reset.** None exists; a locked-out account means editing a `0640` database file by hand.
- [ ] **[LGO] Logout takes two redirects** (`/music/logout` → `/music/songs` → `/music/login`) because the landing page is gated. Redirect straight to `/music/login`.
- [ ] **[REG] `register.php` reports "invalid invite" for any exception in its transaction**, so a real database failure is misreported. Fine for users, misleading when debugging.
- [ ] **[BND] Bandcamp links link out instead of embedding.** An embed needs the numeric id from Bandcamp's `EmbeddedPlayer` URL, which is not in the page URL. Bandcamp's oEmbed returned a bot-detection page from the dev environment. Options: have the admin paste the Share/Embed URL (the id is then extractable by regex, like Spotify/YouTube), or retest oEmbed from the real host.
- [ ] **[LFT] Two leftovers from the 2026-09-25 audit.** (a) Song renames keep no history (only an alias the song already had is promoted); keeping the old name on every rename, as `albumEdit.php` does, would be consistent but is permanent clutter without delete-alias UI. (b) The album header SELECT is duplicated in `routes/albums.php` and `ajax/albumCard.php`, and the audit-row SELECT in `routes/audit.php` and `ajax/songRatingPoll.php`; sharing them means a shared SQL fragment, which cuts against "SQL lives in route and ajax files".

## Hide and seek module

- [ ] **[PIN] Pins are not saved.** `hnsMarkers` in `js/map.js` holds `{lat, lng, marker}` and vanishes on reload. Persisting them means a `marker` table (in `001` while unshipped) and one ajax endpoint dispatching on an action, shaped like `routes/accounts.php`. Settle first: shared between accounts or private, and retention. A marker for "where a person is hiding" is location data about an identified user.
- [ ] **[GMP] Nothing but imported POIs attaches to a game map.** `hnsLoadedGameMap()` is the hook; whatever is built next (pins, bounds, a start point) should take a `game_map_id` and nothing should stay global. `routes/index.php` still hardcodes the map centre (Groningen) instead of centring on the loaded map's POIs.
- [ ] **[BUS] The base map has no bus stops or routes.** Every base-map label is hidden, stops included, so transit has to arrive as imported POIs. Bus lines cannot come from the tiles: a route is a `type=route` relation and the tileset carries none for buses (only bus-only roads, `busway`/`bus_guideway`). Overpass can supply geometry but both mirrors time out too often to depend on. If wanted, bake routes from a Geofabrik extract or GTFS feed as deliberate work.
- [ ] **[PRV] No privacy page, and the module talks to a third party.** Tile requests are deferred until the visitor presses a button, which says so. To drop the third party, serve a `.pmtiles` extract and change `sources.openmaptiles.url` in the style. That mitigates; it does not replace a privacy note covering accounts, the session cookie and tile requests.
- [ ] **[MIG] `001_create.sql` has not shipped, so it is still editable.** Keep folding game tables into it until it deploys, then the music freeze rule applies. Editing it means resetting dev databases that already recorded it (`DELETE FROM schema_migrations WHERE filename = '001_create.sql'` re-runs it and keeps accounts); see `hideandseek.md`.
- [ ] **[LOC] One locale (`en`).** The menu renders only with more than one: add a line to `config.php` and a `lang/<code>.php`.
- [ ] **[SHR] Decide whether music and hideandseek should share code.** `rateLimit.php`, `inviteCode.php`, `hue.php`, `auth.php`, `ajaxGuard.php` and `tooltip.js` are near-copies. Intentional for now (no service layer; two modules is too few to see what is shared). Revisit once hideandseek has real pages; the invite code and rate limiter are the obvious candidates.
- [ ] **[WHO] `ajax/whoami.php` is a worked example** meant to be copied and then deleted.
- [ ] **[PAR] The POI parser has no automated test.** The suite runs no JS; only the endpoint's validation is pinned.

## Site wide

- [ ] **[MGR] No command to run migrations.** They apply as a side effect of the first request after a deploy, so there is no way to apply one ahead of traffic or confirm it succeeded short of using the site.
- [ ] **[NAV] ss2 nav is mostly broken.** `/ss2/` and `/ss2/1`…`/ss2/7` 404; only levels 11–18 are routes, and only 11–13 have data.
- [ ] **[FXR] Exchange rates are frozen at 2025-10-04.** Both fetch paths in `exchangeRatesController.php` are commented out; the page serves checked-in JSON, and the home page shows the numbers without a date.

## Possible, not planned

- [ ] **[SLG]** ss2 login: `session.php` is module-agnostic (`sessionScope('ss2')`, its own account table).
- [ ] **[JST]** JS tests. The pure functions in `addSongs.js` are testable but there is no Node on this machine.
- [ ] **[AFL]** `/music/audit`: the song is plain text rather than a link (no per-song anchor on the songs table; `id="song-<n>"` would give one), and there is no filter by rater or song. Paging is in place for it.

---

# Open decisions

No done state. They block work downstream and keep resurfacing.

### [COV] How are covers modelled?

Not at all. `song_cover` and `composition` went in the ground-up rewrite (`app/modules/music/notes.txt` has the original sketch). The earlier reasoning: a cover is a separate song sharing a composition, which needs `composition` back. Nothing forces it until a cover needs recording.

### [VER] When does a recording become a separate version?

The settled rule: split on edit or performance, not on master or encoding, and override when it would change the score. Not implemented: `song_version` was dropped, and no constraint stops two same-titled songs by one artist (duplicate detection is an application check in `song.php`). Nothing blocks recording one; it is just not modelled deliberately.

### [EMB] Do the Spotify/YouTube embeds need consent, and in what shape?

The songs page loads real Spotify, YouTube and SoundCloud iframes, which set cookies and send the visitor's IP and referrer to those services regardless (*Fashion ID*, C-40/17, makes the operator jointly responsible). Under ePrivacy (NL: Telecommunicatiewet 11.7a) consent must come before the iframe loads, so a banner over running embeds satisfies nothing. A mandatory signup checkbox is the wrong answer (GDPR 7(4): not freely given; 7(3): withdrawal must be as easy as granting). The shape that works: an **account setting, default off, changeable any time** ("load Spotify/YouTube players inline"), rendering a plain link while off, optionally offered unticked at signup. Every viewer is logged in, so it covers the whole audience with no banner. Needs an `account` column and a toggle (the cog menu is the obvious home). Not urgent while the audience is a few invited friends; it decides whether embeds can ever be shown more widely.

### [PER] Is the music module a personal tool or part of the site?

It is 60+ files and the most developed thing here; `main` still says "There currently isn't much here yet" and only the home page links to `/music`. Worth deciding rather than drifting.
