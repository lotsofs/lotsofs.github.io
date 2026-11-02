# TODO

Index of open work, one row per item, each with a three-letter code (the owner refers to items by code). Items are grouped by module first, then by kind; each kind names the doc holding its items' detail (the evidence, a plan, how to verify it and which docs to update). Within a group, rows are in suggested order of work. A finished item leaves both places and is logged in [DONE.md](DONE.md). Environment traps live in `conventions.md`, not here.

## Music

### Defects and tasks

Detail in [tasks.md](tasks.md).

| Code | Summary | Severity |
|---|---|---|
| [PWR](tasks.md#pwr--no-password-reset) | No password reset: a locked-out user needs the live database edited by hand (hideandseek has the same gap) | Medium |
| [TRN](tasks.md#trn--de-and-fy-translations-are-unreviewed) | The `de` and `fy` (site default) translations are first drafts by Claude, never reviewed by a native speaker | Medium |
| [ALI](tasks.md#ali--aliases-cannot-be-deleted-or-promoted) | Album, artist and song aliases can be added but never deleted or made current again | Medium |
| [LGO](tasks.md#lgo--logout-takes-two-redirects) | Logout redirects to `/music/songs`, which redirects again to `/music/login`; go straight to login | Low |
| [REG](tasks.md#reg--registration-misreports-database-failures) | Registration reports "invalid invite" for any failure in its transaction, hiding real database errors (hideandseek too) | Low |
| [REN](tasks.md#ren--rename-the-album-card-modal) | `#albumCardModal` and `albumCard.js` host artist cards too; rename them kind-neutral, as a change of its own | Low |
| [LFT](tasks.md#lft--leftovers-from-the-2026-09-25-audit) | Audit leftovers: song renames keep no old name, and two SELECTs are duplicated between route and ajax files | Low |
| [BND](tasks.md#bnd--bandcamp-does-not-embed) | Bandcamp links open Bandcamp instead of embedding; an embed needs a numeric id that the page URL lacks | Low |
| [SWH](tasks.md#swh--statistics-for-does-not-reach-the-graph-or-song-card) | The cards' "Statistics for" control changes their tables but not the graph or the song card; deliberate for now | Low |
| [TOA](tasks.md#toa--own-ratings-pop-toasts) | `TOAST_SHOW_OWN_EVENTS` is `true`, so raters get toasts for their own ratings; kept on by the owner, flip only when told | Low |

### Open decisions

Detail in [decisions.md](decisions.md). No done state until the owner decides; they block work downstream and keep resurfacing.

| Code | Summary | Severity |
|---|---|---|
| [EMB](decisions.md#emb--do-the-embeds-need-consent-and-in-what-shape) | Spotify, YouTube and SoundCloud iframes load before any consent; the workable shape is an account setting, off by default | Medium |
| [COV](decisions.md#cov--how-are-covers-modelled) | Covers are not modelled; a cover as a separate song sharing a composition would need `composition` back | Low |
| [VER](decisions.md#ver--when-is-a-recording-a-separate-song) | When a recording becomes a separate song: the rule is settled (split on edit or performance) but nothing models or enforces it | Low |

### Possible, not planned

Detail in [tasks.md](tasks.md).

| Code | Summary | Severity |
|---|---|---|
| [AFL](tasks.md#afl--audit-page-links-and-filters) | `/music/audit` shows songs as plain text and has no filter by rater or song; song cards are linkable now | Low |

## Hide and seek

### Defects and tasks

Detail in [tasks.md](tasks.md).

| Code | Summary | Severity |
|---|---|---|
| [CAS](tasks.md#cas--hideandseek-001-breaks-the-never-cascade-rule) | `001` breaks the never-cascade rule (`ON DELETE CASCADE` on `poi_category` and `poi`, bare `REFERENCES` on `invite`); cheap to fix while `001` is unshipped | Medium |
| [PRV](tasks.md#prv--no-privacy-page-for-hideandseek) | No privacy page covering accounts, the session cookie and the OpenFreeMap tile requests; wanted before players arrive | Medium |
| [WHO](tasks.md#who--delete-the-whoami-example-endpoint) | `ajax/whoami.php` was an example endpoint to copy then delete; real endpoints exist now, but the guard tests still target it | Low |
| [PIN](tasks.md#pin--map-pins-are-not-saved) | Map pins live only in the page and vanish on reload; saving them needs a table in `001` and a decision on sharing and retention | Medium |
| [GMP](tasks.md#gmp--only-imported-pois-attach-to-a-game-map) | Only imported POIs attach to a game map, and new game data should too; the map still opens on a hardcoded Groningen centre | Medium |
| [MIG](tasks.md#mig--hideandseek-001-freezes-when-it-first-ships) | `001_create.sql` is unshipped and still edited in place; it freezes the day hideandseek first deploys | Low |
| [GEO](tasks.md#geo--the-geo-database-is-copied-by-hand) | `hideandseek_geo.sqlite` is built at home and copied by hand; nothing checks the host has it or that it is this year's CBS data | Low |
| [LOC](tasks.md#loc--hideandseek-has-one-locale) | hideandseek has only `en`; its language menu appears once a second locale is added | Low |
| [PAR](tasks.md#par--no-tests-for-the-poi-parser-or-the-map) | The POI paste parser and everything drawn on the map have no automated test, since the suite runs no JS | Low |
| [BUS](tasks.md#bus--no-bus-stops-or-routes-on-the-base-map) | The base map shows no bus stops or routes; stops must be imported as POIs, and routes would need a Geofabrik or GTFS bake | Low |

## Main

### Defects and tasks

Detail in [tasks.md](tasks.md).

| Code | Summary | Severity |
|---|---|---|
| [FXR](tasks.md#fxr--exchange-rates-are-frozen) | Exchange rates are checked-in JSON frozen at 2025-10-04, and the home page shows them without a date | Low |

## ss2

### Defects and tasks

Detail in [tasks.md](tasks.md).

| Code | Summary | Severity |
|---|---|---|
| [NAV](tasks.md#nav--ss2-nav-links-to-pages-that-404) | The ss2 nav links `/ss2/` and `/ss2/1`–`/ss2/7`, which have no route and 404; only levels 11–18 are routed, and only 11–13 have data | Medium |

### Possible, not planned

Detail in [tasks.md](tasks.md).

| Code | Summary | Severity |
|---|---|---|
| [SLG](tasks.md#slg--logins-for-ss2) | ss2 could get logins: `session.php` is module-agnostic | Low |

## Bass tabs

### Defects and tasks

Detail in [tasks.md](tasks.md).

| Code | Summary | Severity |
|---|---|---|
| [TAB](tasks.md#tab--bass-tabs-are-held-back-from-the-deploy) | The bass tabs are on `$unshippedModules` for now, so they are not on the host; shipping them means taking them off that list and putting their `CHANGELOG.md` entry back | Low |

## Site

Work that belongs to no one module, or to all of them.

### Defects and tasks

Detail in [tasks.md](tasks.md).

| Code | Summary | Severity |
|---|---|---|
| [CFG](tasks.md#cfg--the-site-wide-config-file-is-loaded-by-nothing) | `app/config.php` (`return [];`) ships with every deploy but nothing loads it; delete it or give it a purpose | Low |
| [MGR](tasks.md#mgr--no-command-to-run-migrations) | No command to run migrations; they apply on the first request after a deploy, with no way to confirm them | Low |

### Open decisions

Detail in [decisions.md](decisions.md).

| Code | Summary | Severity |
|---|---|---|
| [PER](decisions.md#per--is-music-a-personal-tool-or-part-of-the-site) | Is music a personal tool or part of the public site? The home page still says there isn't much here | Low |
| [SHR](decisions.md#shr--should-music-and-hideandseek-share-code) | Music and hideandseek keep near-copies of the rate limiter, invite codes, hue, auth, ajax guard and tooltip; revisit once hideandseek has real pages | Low |

### Possible, not planned

Detail in [tasks.md](tasks.md).

| Code | Summary | Severity |
|---|---|---|
| [JST](tasks.md#jst--javascript-tests) | JS tests: `addSongs.js` has testable pure functions, but there is no Node on this machine | Low |
