# main, swat4 and ss2

These three have no database, no accounts, no i18n and no tests beyond "every route serves" (`tests/cases/pages.php`). They are plain routes and views.

## main

Routes (`app/modules/main/routes.php`): `/` (home), `/exchange-rates`, `/contact` (a link dump titled "Links" in the nav), `/ktane` (302 to `/raw/ktane/translated.html`).

- Views use `main/views/partials/{head,nav,foot}.php`. `head.php` is English-only, sets `<title>$pageTitle - LotsOfS</title>`, loads `modules/main/css/styles.css` and `js/util.js` through `asset()`.
- `error.php` (`abort()` target for **every** module's 404/other errors) renders `error.view.php` with `$responseCode` and `$errorMessage`.
- `home.view.php` shows a whitelisted subset of exchange rates and links to `/contact`, `/exchange-rates`, `/ktane` and `/music` (relative hrefs, so only valid because every route is one segment deep).
- **Exchange rates are frozen.** `exchangeRatesController.php` has both fetch implementations (file_get_contents and curl against `https://fer.eltrick.uk/latest?base=EUR`) commented out and falls back to a stub JSON; but the pages do not even use `$exchangeRatesJson`: `public/modules/main/js/exchangeRates.js` fetches the checked-in `modules/main/json/exchangeRates.json` (dated 2025-10-04) and `iso4217.json` itself. To revive live rates, a server-side job must refresh that JSON file (or the controller must output the data), and `cacert.pem` handling restored. The fetch paths in JS are *relative* (`modules/main/json/...`).
- `exchangeRates.js` globals: `processExchangeRates()` (entry; the view calls it inline), `currencyWhiteList` (set before the call on the home page to restrict rows), `displayExchangeRates`, `addCurrencyTableRow`, `calculateConversions` (editing one currency box recomputes the others via EUR, rounding to ISO 4217 minor units; 5-decimal rate display). Currencies missing from the rates JSON appear with rate `-1` and no input box.
- `public/raw/ktane/` is a static copy of a translated Keep Talking and Nobody Explodes manual site (including ~120 Korean font subsets). Not part of the app; excluded from the test copy; shipped by the build.

## swat4

One route: `/swat4/2` → `routes/map02.php` → `views/map02.view.php`. A self-contained page (own `<html>`, inline CSS, black background) that fetches `/modules/swat4/svg/02Fairfax_Map.svg` into a div and toggles SVG layers `floor1`/`floor0` with checkboxes (`hideLayer`). It does not use the shared partials or `util.js`. The SVG has layer groups by id (e.g. `f1Patrols`). Adding another map means a route entry, a route file and a view (copy `map02`).

## ss2 (Serious Sam 2 "Serious Compendium")

A data-driven reference site: a level page is a fixed HTML skeleton that JS fills from one JSON file per level.

### Server side

- `routes.php` registers `/ss2/11` … `/ss2/18`, all to `routes/level.php`. That file maps level id → title (`$levels`), reads the id with `getLastUrlPart()`, sets `$pageTitle`, requires `views/level.view.php`. A level id missing from `$levels` would raise an undefined-index; only 11–13 have data (see below).
- `views/partials/nav.php` is a header with a banner image, a main nav (`General`, `/ss2/1` M'Digbo … `/ss2/7` Sirius) and a sub-nav that currently only has real content for M'Digbo (`/ss2/1*`). **Eight of the nine main-nav links 404** (`/ss2/`, `/ss2/1`…`/ss2/7` are not routes). `urlStartsWith("/ss2/1")` drives the highlight and also matches `/ss2/11`…`/ss2/18`.
- `head.php` uses bare asset paths (no `asset()` stamp). `level.view.php` ends with `<div id="levelInfo" data-id="<level>">` and `<script type="module" src="/modules/ss2/js/level.js">`.

### Client side (native ES modules, `public/modules/ss2/js/`)

- `level.js` awaits `initLevel()` (`levelLoader.js`: reads `#levelInfo[data-id]`, fetches `/modules/ss2/json/<id>.json` with `readJsonFileAsync`), sets `#mapName`, then calls `section.populate(levelData)` on each of `psl, waa, ccr, sat, ess, sbd, map, mps`, each in its own `try/catch` so one broken section does not blank the rest.
- Each section owns a `<div id="...">` in the view and writes into it:

| Module | Section | Source data |
|---|---|---|
| `psl` | Plot/Story/Lore: objectives list; blurb fetched from `text/<id>.dsc` | `objectives`, `.dsc` |
| `ccr` | Chapter completion requirements table | `chapters[i].passCondition`, `securityTimer`, `requiresStart`, `name` |
| `waa` | Weapons and ammo matrix | `chapters[i].ammoRatio`, `customAmmo`; `data/ammo.js` (per-ammo max). Bold = fixed value, italic = ratio × max, dash = impossible (`< 0`) |
| `sat` | Spawners and timings: per-spawner table (cloned from `#satTemplateTable` via `data-field`) and an SVG timeline; checkbox switches to v2.090 spawner behaviour | `spawnerGroups[].spawners[]` |
| `ess` | Editor screenshots | `imagery[]` → `img/levels/<id>/<fileName>` |
| `sbd` | Score breakdown: per-chapter pickups/kills, cumulative score, enemy-multiplier stepper (1×–11×), end-screen bonus maths | `chapters[i].points`, `eta`, `secrets`; `data/score.js` (default worth per item) |
| `map` | SVG over `img/maps/<id>.png` with tooltip markers | `map.width/height/markers[]` |
| `mps` | Macro Program Snippets: fetches `text/macros/<id>/<fileName>.mps`, regex syntax-highlights it, then recolours variables per `badVars`/`goodVars` | `macros[]` |

- `ui/tooltip.js` is `tooltipPopup.link(el, text)` over `#popupToolTip`; it is the template the music module's `tooltip.js` was copied from (deliberately not shared).
- Uses globals from `public/js/util.js` (`appendChildToElement`, `createSVGElement`, `SVG_NS`, `readJsonFileAsync`, `readTextFileAsync`, `setElementByIdTextContent`, `convertSecondsToTimestamp`), so `util.js` must load as a classic script before the module.

### Level JSON shape (`public/modules/ss2/json/NN.json`; `00.json` is the blank template)

```
name, id, eta (seconds), secrets, objectives[],
chapters[]: { id, securityTimer, passCondition, requiresStart, ammoRatio, preferredWeapon?,
              customAmmo{handGrenades,shells,bullets,rockets,grenades,plasma,sniperBullets,klodovik,cannonBalls,seriousBombs: int, -1 = unobtainable},
              addedWeapons[], points{ "<Area name>": [ {name, count, countsAsKill, note, worth?, maxMultiplier?, impossible?, killsItself?} ] } }
              (an empty {} chapter is skipped by every section)
spawnerGroups[]: { groupName, note, spawners[]: { name, color, spawnType ("simple"|"maintainGroup"), spawnEffectDelay, spawnEffectType, spawnLaunchDuration, spawnLaunchType, spawnFormation, totalNumber, numberInGroup, initialDelay, singleDelay, groupDelay, spawneeDeathDelay, timeToKill, initialDelayByScript, otherInitialDelay } }
imagery[]: { fileName, title, description }
map: { width, height, markers[]: { x, y, r, color, tooltip } }
macros[]: { title, fileName, badVars[], goodVars[] }
```

Item worth falls back to `data/score.js` when `worth` is absent, and to `"????"` when neither has it. `impossible` (a string used as the tooltip) excludes an item from the score; `killsItself` still counts the kill. Level data exists for 11, 12, 13; routes 14–18 render the skeleton and fail to fetch JSON (console error, empty sections). To add a level: JSON (copy `00.json`), `.dsc`, `macros/<id>/*.mps`, `img/levels/<id>/`, `img/maps/<id>.png`, and the sub-nav link; the route and `$levels` title already exist for 11–18.
