# TODO

Tasks and open decisions for the lotsofs site. Two lists, because they behave
differently: tasks get closed, decisions get answered and then stop recurring.

---

# Tasks

## Before anything goes to prod

- [ ] Set `TOAST_SHOW_OWN_EVENTS` to `false` in `public/modules/music/js/songs.js`.
      It's `true` only so rating popups can be tested from a single account;
      left on, every score or note you type pops up a notification at yourself.
      **Deliberately still `true` in production** — the user was asked on
      2026-09-20 and said to leave it ("The flag can stay true for now"), so
      don't flip it as a tidy-up; it comes off when they say so.
- [x] ~~`pdo_sqlite` was gone from the host.~~ PHP 8.5 rebuild dropped every PDO
      driver; the operator restored `pdo_sqlite` (and curl) after being emailed.
- [x] ~~The live site was PARKED on a diagnostic probe.~~ `public/index.php` is
      back to the plain 3-line bootstrap; the probe is gone.
- [x] ~~Check where PHP stores sessions on the host.~~ `/tmp` was unwritable once
      PHP started running as `lotsofs`; the operator says this is now fixed
      server-side too, but `startSession()` still points `session.save_path` at
      `__DATA__/sessions` itself (belt and suspenders) and re-enables
      probabilistic GC since the distro's cleanup cron won't watch that dir.
- [x] ~~`data/` needed to be writable by the web-server user.~~ The operator
      instead asked us to move off the web-adjacent directory entirely: the DB
      and sessions now live under `/home/lotsofs/` — a directory only the
      `lotsofs` unix user can reach, so no permission dance is needed there ever
      again. `__DATA__` in `app/util.php` auto-detects it (`is_dir('/home/lotsofs')`)
      and falls back to the old `<app>/../data` path when it's absent, which is
      always true locally — dev and tests are unaffected. Nothing needs
      uploading for this: `db.php` and `startSession()` both `mkdir` their own
      subdirectory on first use. Note for future manual access: SFTP sees this
      path at `/home/www/home/lotsofs`, not `/home/lotsofs` — that prefix is an
      SFTP-only quirk, the PHP-side path stays `/home/lotsofs`.
- [x] ~~Confirm the host honours `.htaccess mod_rewrite`.~~ It doesn't — the host
      ignores `.htaccess` entirely — but routing works anyway, so the operator
      must have front-controller rewriting (or equivalent) configured at the
      vhost level already. The repo's `.htaccess` files (`app/`, `data/`,
      `public/`) did nothing on prod and were removed.
- [x] ~~The host only executes `index.php` — any other `.php` file requested by
      its literal filename gets served as a raw download, deliberately (the
      operator doesn't trust PHP's security and gates execution to the one
      entry point).~~ This broke all 6 music ajax endpoints, which lived as
      directly-hit files under `public/modules/music/ajax/` — every "add
      artist"/"add song"/rating action 405'd with no PHP involved at all
      (Apache's static handler rejecting POST). Fixed by moving them into
      `app/modules/music/ajax/` and routing them through the front controller
      like every page route already is: `/music/ajax/artist-alias`,
      `/song`, `/song-edit`, `/song-rating`, `/song-rating-poll`, `/album` in
      `app/modules/music/routes.php`. The shared POST/JSON-body guard moved
      from `public/ajax/ajax.php` to `app/modules/music/ajaxGuard.php` (dropped
      its now-redundant `util.php`/`Database.php` requires — those are already
      loaded by the time any route runs). JS `fetch()` calls in `addSongs.js`
      and `songs.js` point at the new paths. **Both `lotsofs.com/` and `app/`
      need deploying together for this** — `lotsofs.com/` drops the dead files,
      `app/` adds the new ones and the route table; a partial deploy leaves the
      two out of sync.
- [x] **Claim the first account immediately after deploying.** Prod has no
      database yet. On first visit the schema is created and the account table
      is empty, so the first person to reach `/music/register` gets in without
      an invite. Alternatives: deny `/music/register` in `router.php` until you
      have registered (`.htaccess` doesn't work on this host), or require a
      bootstrap secret from `config.php`.
- [x] ~~Check the asset cache stamps actually appear on the host.~~ Added
      2026-09-20: `asset()` in `app/util.php` appends `?v=<filemtime>` to every
      css/js link so a deploy busts the browser cache. It resolves the file on
      disk from `$_SERVER['DOCUMENT_ROOT']`; the operator confirmed the host
      won't change that, so there is nothing to verify. (If it ever did change,
      the failure is quiet rather than loud — `filemtime` fails and the function
      returns the bare path, so links keep working but stop busting.)
- [ ] **Check the secure cookie flag actually engages.** `session.php` decides
      from `$_SERVER['HTTPS']`, but behind a proxy or CDN PHP often sees plain
      HTTP even when the visitor is on HTTPS, so the session cookie would ship
      without `Secure`. Check whether the host sets `HTTP_X_FORWARDED_PROTO`.
- [x] ~~Confirm `music.sqlite` is not web readable.~~ It lives under
      `/home/lotsofs/`, entirely outside the web root — no URL reaches it.
- [ ] `app/config.php` (currently `return [];`) ships with every deploy and
      **nothing loads it** — `$config` was read in `app/util.php` and threaded
      through `route()` as a fourth argument no route file ever touched, so all
      three lines were removed on 2026-09-20. The file is still there and still
      deployed. Settings since then have gone into **per-module** configs
      instead (`app/modules/<name>/config.php`, read by `moduleConfig()`), which
      is where the music locale list and fallback now live. Decide whether
      `app/config.php` still has a purpose: if a genuinely site-wide setting
      turns up, add the `require` back; if a secret turns up, it belongs in
      `data/config.php` (never deployed) or an env var, with a
      `config.example.php` template. Otherwise delete it.

## Music module

- [ ] **`de` and `fy` are fully translated (run `php tests/i18n-coverage.php`
      for the current count) but never reviewed by a native speaker.**
      Every key has been filled in incrementally across sessions (by Claude, not
      a fluent speaker) as new features landed, so there's no "first draft vs.
      finished" split left to find — it's all first-draft quality throughout.
      `lang/de/*.php` has some lines marked `// ?` flagging specific uncertain
      choices (Interpret/Künstler, Wertung/Punkte, Titel/Tracks, Widerrufen) but
      the rest wasn't flagged either. Needs a native-speaker pass over both,
      not just the marked spots.
      **Start with the three abbreviations.** As of 2026-09-22 the score column
      header is shortened per locale — `Sc.` / `Wert.` / `Wurd.` — and those
      were invented, not translated. An abbreviation that reads wrong is more
      jarring than a slightly-off word, and `Wurd.` in particular could just as
      well be read as the start of a different word.
      `fy` is the **site default** — a visitor who has never touched the nav
      switcher sees Frysk, browser `Accept-Language` is ignored entirely. English
      stays the source catalogue and the test-suite language (the harness sets
      `LOTSOFS_LOCALE=en`; setting that env var on a host overrides the
      module's own fallback without editing its config).
- [x] ~~**Decide whether the note preview panel comes back, then delete or
      restore it.**~~ Deleted on 2026-09-25, after the card-and-flash behaviour
      had been live since 2026-09-20. Gone: the `#songNotePreview` markup, its
      CSS and `songNotePreviewFlash` keyframes, the `NOTE_PREVIEW_ENABLED` flag
      and both of its branches, `setPreview`/`showNotePreview`/
      `showFilepathPreview`/`clearNotePreview`, the `previewedNoteCell` the
      rating poll kept in step, four catalogue keys in three locales, and the
      `--songHeaderHeight` custom property, whose only reader was the panel's
      sticky offset. Clicking a note or a filepath opens that song's card and
      flashes the value there, which is now the only behaviour.
- [ ] **The album card can only add album names, never remove one.** Clicking an
      album name — in the song table, on a song card, or in the album list —
      opens the album's card in a modal, rendered server-side by
      `views/partials/albumCard.php` and fetched through `/music/ajax/album-card`
      (fetched fresh every time, deliberately not cached: the song list can move
      songs on and off an album while the page is open, and leaving edit mode
      re-fetches rather than patching the DOM). Admin edit mode covers the name,
      the artist, the year and the track list (add/remove a song, renumber it,
      pick which of the song's names this release credits it under) via
      `/music/ajax/album-edit` and `/music/ajax/album-track`. Renaming keeps the
      old name as a non-actual `album_alias` row, which is the point — but
      nothing in the UI can then **delete** a wrong alias or promote one back
      without typing it out again, and a typo'd rename is therefore permanent
      clutter in the "also known as" line. An artist card would be the same
      shape again (partial + one endpoint + the same `data-album-card-id`-style
      trigger `albumCard.js` delegates). **The artist card was built on
      2026-09-27** and is exactly that shape; the alias gap above still stands
      for both, and now for artists too.
- [ ] **Neither card's "Statistics for" control reaches the song card or the
      graph.** Added 2026-09-27: a dropdown over the album card's track table
      and the artist card's album table re-reads those statistics as one person
      instead of as everyone pooled (`musicStatsWho()` validates the choice
      against the raters *that* card can offer, so a stale pick falls back to
      everyone; `albumCard.js` holds it for the page session alongside the graph
      order). Two things it deliberately does not touch: the graph, which has a
      per-rater order of its own and would be one dot per song, and the per-rater
      stats table, which *is* the breakdown. The song card has no statistics
      table to apply it to. Note the two tables answer differently on purpose —
      a track gets one score from one person, so the album card's three columns
      collapse to that person's score under their own name, while an album is
      several songs to one person and keeps all five.
- [ ] **`#albumCardModal` and `albumCard.js` host artist cards as well.** Both
      names predate the artist card and now undersell what they do: one modal,
      one script, `CARD_KINDS` deciding which endpoint renders into it. The
      rename (`#cardModal`, `cardModal.js`) is mechanical — the partial, the
      CSS, half a dozen test assertions and two CLAUDE.md lines — and was left
      out of the artist-card change deliberately, to keep that change
      reviewable. Do it on its own, with the suite as the check.
- [x] ~~**A cleanup pass over the music module**~~ — done 2026-09-25, after an
      audit. Fixed: the phone song card (its `min-width` override lost to an
      id-scoped rule and had never once applied, on the one viewport where card
      view is the default); the bulk importer blanking an existing album's
      artist and year on re-import; renaming a song onto one of its own aliases
      500ing with the raw SQL error; three numeric endpoints still on
      `ajaxTrimmed` (and `ajaxNumericText` itself not handling floats, so
      scores were still exposed); a stray "Links" heading left behind after any
      Edit → Done; and `/music/language` persisting `'en'` when handed a locale
      the module doesn't ship. Each is pinned by a new test except the two
      front-end ones, which nothing here can execute. Also deduplicated:
      `musicScoreStats()`, `musicDuration()`, `musicSongLabel()`, and the
      Spotify/YouTube id patterns.
- [ ] **Two decisions left from that audit.** (a) Song renames keep no history
      — the fix above only promotes an alias the song already had; adopting
      `albumEdit.php`'s shape everywhere would leave the old name behind on
      *every* rename, which is consistent but permanent clutter while there is
      no delete-alias UI. (b) The album header SELECT is byte-identical in
      `routes/albums.php` and `ajax/albumCard.php`, likewise the audit row
      SELECT in `routes/audit.php` and `ajax/songRatingPoll.php`; factoring
      them means a shared SQL fragment, which contradicts this repo's "SQL
      lives directly in route and ajax files". Left duplicated for now.
- [ ] **Password reset.** There is none, and there is now a real account. Being
      locked out means editing a `0640` database file through the file manager.
- [ ] Logout takes two redirects: `/music/logout` -> `/music/songs` ->
      `/music/login`, because the page it lands on is gated. Send it straight
      to `/music` or `/music/login`.
- [ ] `register.php` reports "invalid invite" for *any* exception inside the
      transaction, so a real database failure is misreported. Fine for users,
      misleading when debugging.
- [x] ~~Endpoint guard ordering: a signed out caller with a malformed body gets
      `400` before the `401`.~~ Fixed on 2026-09-20 and shipped. `ajaxGuard.php`
      now runs in the order: who are you (`401`) -> is this a valid method
      (`405`) -> is it forged (`403`) -> is the payload valid (`400`), so an
      anonymous caller is told to log in before anything about the request is
      judged, and can no longer reach the exception handler that echoes
      `$e->getMessage()`. Pinned in both directions by two tests in
      `tests/cases/pages.php`. The 405/400 messages became `ajax.badMethod` and
      `ajax.badBody` (they were the last hardcoded English in the module — a
      stale tab posting after a deploy is a plausible way for a real person to
      hit them). `'Unknown field'`/`'Unknown action'` stay English on purpose:
      only a broken client reaches those, so they are developer-facing.
- [ ] **Bandcamp links don't embed, just link out.** A real embed needs the
      numeric album/track ID Bandcamp's `EmbeddedPlayer` URLs use, which isn't
      in the normal page URL you'd copy — the only server-side way to resolve
      it (Bandcamp's oEmbed API) came back as a bot-detection challenge page
      when tried from this environment, not JSON, so a fetch-and-cache approach
      isn't reliable. If revisited: either ask the admin to paste the
      "Share/Embed" URL specifically (which does contain the ID, extractable
      with a plain regex, same as Spotify/YouTube — no network call needed) or
      re-test whether oEmbed works from wherever the app actually deploys.
- [x] ~~**Fold into `006` whenever one gets written for a real reason:** drop
      the four unused indexes.~~ Done: `006_account_hue.sql` adds `account.hue`
      and drops `idx_rating_audit_song`, `idx_rating_audit_created`,
      `idx_song_relationship_unique` and `idx_song_relationship_target` in the
      same migration. None of the four appeared in any `WHERE` or `ORDER BY` —
      every `rating_audit` query goes by rowid and `song_relationship` is
      referenced by zero lines of application code — so they only cost a B-tree
      write per rating change. `006` shipped on 2026-09-25 and is frozen with
      the rest; `007` is the next free number.

## Hide and seek module

- [ ] **It is boilerplate and nothing else.** Added 2026-09-28: accounts,
      invites, login rate limiting, admin gating, the nav/head/foot chrome, the
      hover tooltip, the per-account hue, a stylesheet and one example ajax
      endpoint (`ajax/whoami.php`, meant to be copied and then deleted). No game
      in it. Everything is `hns`-prefixed and it keeps its own database, session
      scope, catalogue and migrations, so nothing it grows can reach the music
      module.
- [ ] **The map places markers but saves nothing.** Added 2026-09-29, moved to
      vector tiles on 2026-09-30: MapLibre GL JS 4.7.1 is vendored at
      `public/modules/hideandseek/vendor/maplibre/`, the basemap is painted by
      `json/mapStyle.json` (edit it in Maputnik), and `/hideandseek` renders a
      click-to-load map you can click to drop markers on. The Leaflet/raster
      alternative was built alongside it and deleted once this one won. They live in `hnsMarkers` in `js/map.js` as `{lat, lng, marker}` and
      vanish on reload. Persisting them is a `marker` table folded into `001`
      (see the plan) plus one `/hideandseek/ajax/marker` endpoint dispatching on
      an action, the same shape as `routes/accounts.php`. Two things to settle
      first: whether markers are shared between accounts or private, and how
      long they live — a marker meaning "where a person is hiding" is location
      data about an identified user, which carries retention and deletion
      expectations that a song rating does not.
- [ ] **The base map shows neither bus stops nor bus routes.** Since
      2026-10-04 every base-map label is hidden, stops included, so anything
      transit-shaped on the map has to come in as imported POIs. Bus *lines*
      cannot come from these tiles: a bus route is a `type=route` relation in OSM
      and nothing in the tileset carries one — only physical bus-only roads
      (`busway`, `bus_guideway`). Route relations as such *do* reach the tiles,
      contrary to what these notes said until 2026-10-01:
      `transportation_name` has `route_1_*` … `route_21_*` fields, and the z14
      tile over Groningen fills them with `rwn`/`nwn` walking networks. Just not
      bus ones, so a road a bus runs along looks like any other road.
      Overpass can supply route geometry and did
      once, but both mirrors time out often enough that a rendering feature must
      not depend on it. If routes are wanted, bake them from a Geofabrik
      regional extract or a GTFS feed, as a deliberate piece of work.
- [x] ~~**Places drawn on the map are listed under it, shops excluded.**~~
      Retired 2026-10-04 along with the label allowlist it read
      (`HNS_KEPT_CLASSES` for bus/railway/ferry, airports via
      `HNS_KEPT_SOURCE_LAYERS`): the base map now writes no text at all, so
      there was nothing left for a list of drawn places to list. The one rule
      that survived is `hnsHideLabels()` hiding every `type: symbol` layer with
      a `text-field` — which keeps the canals, the roads and the one-way arrows,
      and still must not be "simplified" to hiding by source layer. The side
      column now lists only imported POIs.
- [ ] **No privacy page yet, and the module now talks to a third party.** The
      map defers every tile request until the visitor presses a button, so
      nothing reaches OpenFreeMap unasked, and the button says so. To drop
      the third party altogether, serve a `.pmtiles` extract from this site
      and change `sources.openmaptiles.url` in the style — no layer changes. That is the mitigation, not a substitute for a privacy note covering
      the accounts, the session cookie and the tile requests.
- [ ] **`001_create.sql` has not shipped, so it is still editable.** It holds
      `account`, `invite`, `login_attempt`, `game_map` (2026-10-01), and
      `poi_category`/`poi` (2026-10-04).
      Keep folding game tables into it rather than adding `002`, but only until
      it deploys, after which the same freeze rule as music applies.
      **Editing it means resetting every database that already recorded it**:
      migrations are filename-tracked with no content hash, so the change
      silently never lands and the suite cannot see it (tests start from a fresh
      database every run). `DELETE FROM schema_migrations WHERE filename =
      '001_create.sql'` re-runs it and keeps the accounts, since every statement
      in it is `CREATE … IF NOT EXISTS`.
- [ ] **The game map creator exists; only imported POIs attach to a game map.**
      `/hideandseek/game-maps` creates, loads, renames and deletes them, and
      `hnsLoadedGameMap()` in `gameMap.php` answers "which one is open" under one
      rule (`?map=` wins, else the session). That is the hook everything else was
      waiting for: markers, bounds, a starting point, a POI set. The first of
      those to be built should take a `game_map_id` and nothing should be global
      any more — `routes/index.php` still hardcodes one map centre.
- [x] ~~**Imported POIs are saved but not shown on the map.**~~ Added 2026-10-04:
      `/hideandseek/import-pois` takes a category name and pasted Overpass Turbo
      raw data (JSON or XML), parses it in the browser (`js/poiImport.js`) and
      posts the finished POIs to `/hideandseek/ajax/import-pois`, which
      validates each one and writes them onto the loaded game map — ways and
      relations by their `center`, or the middle of their `bounds` with
      `out geom`. The parser has no automated test: the suite drives PHP over
      HTTP and runs no JS, so only the endpoint's validation is pinned. Re-importing into a category with the same
      name (any casing) merges by OSM type and id rather than duplicating. The
      page lists categories with counts, renames and restyles them one row or
      all rows at a time, deletes them, and an Export
      button gives the whole map back as text the same box imports — categories,
      colours and icons included — so a POI set can be copied between maps.
      Since 2026-10-04 they are drawn on `/hideandseek` (names from z13) and
      listed beside the map, all of them whether in frame or not; clicking one
      out of frame pans to it. Categories can be switched off one by one,
      places without a name are hidden unless asked for, and one category at a
      time can draw its Voronoi borders (the area nearest to each of its
      places) by great-circle distance — triangulated with a vendored
      d3-delaunay on a stereographic projection, corners at the spherical
      circumcentres, with no border between two places of the same name — or, instead (one overlay at a time), circles around
      every POI of a category at the distance from the latest pin to the
      nearest one, merged into a single outline. Pins are still not saved. Each category has its own
      colour and optional one-character icon (emoji), set on import and editable
      on the import page; the marker is the icon drawn as text (one-colour,
      bold, outlined in the category colour, filled black, or white when
      the colour is dark) on a canvas, since MapLibre's glyph fonts carry
      no emoji. The map still opens on the hardcoded
      Groningen centre rather than on the loaded game map's POIs.
- [ ] **One locale (`en`).** The language route and menu are wired but the menu
      only renders once there is more than one locale to pick from, so adding
      `nl`/`fy` is a line in `config.php` plus a `lang/<code>.php`.
- [ ] **Decide whether the two modules should share code.** Right now
      `rateLimit.php`, `inviteCode.php`, `hue.php`, `auth.php`, `ajaxGuard.php`
      and `tooltip.js` are near-identical copies of music's. That is deliberate
      for now — this codebase has no service layer, and factoring a framework
      out of two modules before the second one has any features would be
      guessing at what they actually share. Worth revisiting once hide and seek
      has real pages: the invite code and rate limiter are the two most obvious
      candidates, since neither has anything to do with either subject.

## Site wide

- [ ] **`migrate.php` as a real command.** Migrations only run implicitly, as
      a side effect of the next page visit after a deploy (`runMusicMigrations()`
      at the top of every music route/ajax file) — there's still no way to
      apply one deliberately ahead of traffic, or to know it succeeded without
      just checking the site works afterward.
- [ ] **ss2 nav is mostly broken.** Eight of nine links 404 — `/ss2/`, and
      `/ss2/1` through `/ss2/7`. Only levels 11-18 are registered routes.
- [ ] **Exchange rates are frozen at 2025-10-04.** Both fetch paths in
      `exchangeRatesController.php` are commented out, so the page serves
      checked-in JSON. The home page shows the numbers with no date at all.
- [ ] Nothing anywhere links to `/music`.
- [x] ~~Dead `serveDirectFile` block in `router.php`~~ — removed in the split.
- [x] ~~`config.php` empty but required in two places~~ — now loaded once, in
      `app/util.php` (`ajax/ajax.php` no longer loads it separately).
- [x] ~~Orphan `database/test_db.sqlite`~~ — the root `database/` folder is gone.

## Possible, not planned

- [x] ~~Move the document root to a `public/` directory.~~ Done: the repo is now
      `public/` + `app/` + `data/`; the host serves `~lotsofs/lotsofs.com/`
      (= `public/`) and the rest sits above it. See the plan for the deploy
      procedure.
- [ ] ss2 login. `session.php` is already module-agnostic and ready for it —
      `sessionScope('ss2')`, its own account table, separate accounts.
- [ ] JS tests. The pure functions in `addSongs.js` are testable, but there is
      no Node on this machine and therefore no runner.
- [ ] Two gaps left open on `/music/audit` (shipped 2026-09-22), neither asked
      for, both cheap if they start to matter: the song is plain text rather
      than a link, because there is no per-song page and no anchor on the songs
      table to jump to (adding `id="song-<n>"` there would give one); and there
      is no filtering by rater or by song, which a log that only ever grows will
      eventually want. The paging and the indexes are already in place for it.

---

# Open decisions

These have no done state. They block work downstream and keep resurfacing, so
they are worth answering deliberately rather than rediscovering.

### How are covers modelled?

Currently not at all — `song_cover` and `composition` both went in the ground
up rewrite. The earlier reasoning was that a cover is a separate song sharing a
composition, which needs `composition` back. Nothing forces this until you want
to record one.

### When does a recording become a separate version?

The rule settled on: split on edit or performance, not on master or encoding,
and override when it would change the score. Not implemented — `song_version`
was dropped, and there is no schema-level constraint stopping two same-titled
songs by one artist (title moved into `song_alias`; duplicate detection is
now an application-code check in `song.php`, not a unique index), so nothing
currently blocks recording this case, it just isn't modelled deliberately.

### Do the Spotify/YouTube embeds need consent, and in what shape?

The songs page renders real Spotify, YouTube and SoundCloud iframes, which set
cookies and storage the moment they load, and transmit the visitor's IP and
referring URL to those services regardless (*Fashion ID*, C-40/17, makes the
site operator jointly responsible for that transmission). Under ePrivacy —
in NL, Telecommunicatiewet 11.7a — consent must come *before* the iframe
loads, so a banner sitting on top of already-running embeds satisfies nothing.

A mandatory checkbox at signup is specifically the wrong answer: GDPR Art. 7(4)
says consent isn't freely given when access is conditional on consent that
isn't necessary for the service, and the list works fine without players.
Art. 7(3) also requires withdrawal to be as easy as granting, which a one-time
registration checkbox doesn't allow.

The shape that does work here: an **account setting, default off, changeable
at any time** ("load Spotify/YouTube players inline"), rendering a plain link
instead of an iframe while it's off. Optionally offered at signup as an
unticked box that doesn't block registration. Because every viewer is logged
in, that setting covers 100% of the audience and no banner or click-to-load
placeholder is ever needed. Would be a new `account` column plus a toggle on
the accounts page.

Not urgent while the audience is a handful of invited friends — the login gate
is what keeps the practical exposure near zero — but it decides whether embeds
can ever be shown to a wider audience.

### Is this a personal tool or part of the site?

The music module is 60+ files and the most developed thing here. `main` still
says "There currently isn't much here yet" and has a broken rates fetch.
Nothing links to `/music`. Worth deciding rather than drifting.

---

# Environment gotchas

All found the hard way, all cost a debugging cycle.

- **No `mbstring`.** No `mb_*` function works on this PHP build, and presumably
  not on the host either. Use `strlen` and friends.
- **`proc_open` on Windows spawns through a shell wrapper**, so `proc_terminate`
  kills the wrapper and orphans the real child. `bypass_shell => true` is why
  the test harness no longer leaks a server per run.
- **A nested rule inside a comma-separated selector group applies to every
  branch of it, and `styles.css` is full of them.** `#songCards,` and
  `#songCardModal {` open one block spanning ~160 lines, so `.songCardEditBtn`
  and `.songLinkChip` nested inside it are `#songCards .x, #songCardModal .x` —
  not modal-only, which is what they look like if you read backwards from the
  rule and stop at the first line carrying the `{`. Hoisting two of those into
  a shared rule under only the `#songCardModal` half silently removed their
  styling from card view (i.e. mobile), where nothing in the suite looks. When
  rewriting a selector, resolve its chain by walking *up* through every line of
  the group, not just the one with the brace.
- **CSS has no `//` comments, and using one is silently destructive.** Every
  other file in this repo takes `//` or `///`, so it is an easy reflex. In a
  stylesheet the parser hits the slashes, treats them as a bad token, and
  recovers by consuming everything up to and including the *next* rule's
  closing brace — so the rule underneath the comment vanishes while still
  reading perfectly in the file. This ate `.card`'s background and border,
  `.cardModalDialog`'s background and `#albumCardModal`'s z-index at once, and
  presented as "the card modal is transparent", which looks like a colour
  problem and is not. `/* ... */` only. A test in `tests/cases/catalogue.php`
  now fails on any line starting with `//` in `styles.css`; the brace counter
  never saw it, because the braces do still balance.
- **Nothing on this machine executes JavaScript or renders CSS.** There is no
  node (see "JS tests" above), so a JS syntax error or a broken cascade reaches
  production unless a human opens the page. `php tests/run.php` fetches pages
  over HTTP but never runs their scripts. A delimiter-balance checker catches
  gross JS breakage and a brace count catches gross CSS breakage; neither
  catches a wrong selector or a runtime `TypeError`.
