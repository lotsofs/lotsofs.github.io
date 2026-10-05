# hideandseek reference

Lookup companion to `hideandseek.md` (design and traps). Source: `app/modules/hideandseek/` and `public/modules/hideandseek/`. A "hide and seek" game built around real OpenStreetMap data: a *game map* holds imported *points of interest* (POIs) that are drawn on a MapLibre map. No game logic exists yet.

## Schema (`001_create.sql`, unshipped and editable; see the reset recipe in `hideandseek.md`)

| Table | Columns / constraints |
|---|---|
| `account` | `account_name` (unique), `password_hash`, `is_admin`, `lang` (default `'en'`) |
| `invite`, `login_attempt` | same shape and rules as music's |
| `game_map` | `id`, `name`; unique index on `name COLLATE NOCASE`. No owner, no timestamps |
| `poi_category` | `game_map_id` (FK, **ON DELETE CASCADE**), `name`, `colour` (`#rrggbb` lowercase or NULL), `icon` (one grapheme or NULL); unique `(game_map_id, name COLLATE NOCASE)` |
| `poi` | `poi_category_id` (FK, ON DELETE CASCADE), `osm_type` (`node`\|`way`\|`relation`), `osm_id`, `name` (nullable), `lat`, `lon`; unique `(poi_category_id, osm_type, osm_id)` |
| `schema_migrations` | as music |

Deleting a map removes its categories and POIs (a test pins it). Re-importing into a category upserts POIs by `(osm_type, osm_id)`.

## Routes (`routes.php`)

| URL | File | Access | Purpose |
|---|---|---|---|
| `/hideandseek` | `routes/index.php` | public shell; data only if signed in | Map + imported-POI list. Builds `$globalData['imported'] = {categories[{id,name,colour,icon}], pois[{category,name,lat,lng}]}` for the loaded game map and `mapCentre` (hardcoded Groningen 53.2194, 6.5665, zoom 13) |
| `/hideandseek/register`, `/login`, `/logout`(POST), `/language`(POST) | same-named | public | copies of music's flows; first account = admin, later ones need an invite |
| `/hideandseek/game-maps` | `routes/gameMaps.php` | login | actions: `create` (opens the new map), `rename`, `delete`, `load`, `close`; unknown actions do nothing. Validation errors re-render without redirect |
| `/hideandseek/import-pois` | `routes/importPois.php` | login | the import form (processed in JS, see below) plus category table. Actions: `delete` (category), `styles` (rename/colour/icon, `only=<id>` for one row). Result banner from `?imported=&skipped=&categories=&category=` |
| `/hideandseek/accounts` | `routes/accounts.php` | admin | one page for promote/demote (never self) and invites (`invite`, `revoke`) |
| `/hideandseek/ajax/import-pois` | `ajax/importPois.php` | login | see below |
| `/hideandseek/ajax/export-pois` | `ajax/exportPois.php` | login | `{mapId}` → `{status, export}` |
| `/hideandseek/ajax/whoami` | `ajax/whoami.php` | login | worked example of an endpoint; meant to be copied then deleted |

Note: pages require *login* (any account), not admin, except `/accounts`. The game-map pages are open to every account and every account shares all maps.

### Loaded game map

`hnsLoadedGameMap($db)` (`gameMap.php`): `?map=<id>` wins and is stored in `$_SESSION['hideandseek']['gameMapId']`; otherwise the session answers; the row is re-read each call and an id whose row is gone is dropped. `hnsLoadGameMap($id)`, `hnsCloseGameMap()`, `hnsGameMapNameError($db, $name, $exceptId)` (NOCASE, matching the index).

## POI import pipeline

1. User pastes Overpass Turbo output (JSON `{elements: [...]}` or XML `<osm>`; see `docs/exampleImport.txt`) **or** a previous export (`docs/exampleExport.txt`) into the form on `/hideandseek/import-pois`.
2. `public/modules/hideandseek/js/poiImport.js` parses in the browser (`hnsParsePaste`): `{format:'hideandseek-pois'}` → kind `export`; `elements` → kind `overpass` via `hnsOverpassPois`. The server never sees the raw paste.
   - Position: `lat/lon`, else `center`, else midpoint of `bounds`; none → counted as `skipped`.
   - Untagged nodes referenced by a way/relation in the same paste (`>; out skel`) are geometry, not POIs, and are dropped silently.
   - Duplicates within a paste collapse by `type/id`. Name = `tags.name` trimmed or null.
3. It posts `{mapId, categories: [{name, colour, icon, pois: [{osmType, osmId, name, lat, lon}]}]}` to `/hideandseek/ajax/import-pois` (an Overpass paste is a list of one category whose name/colour/icon come from the form; an export brings its own categories and ignores those fields).
4. Server (`poiImport.php`): `hnsValidPoi` (osm type, positive integer id as int or digit string, numeric lat/lon in range, name null or string), `hnsPoiColour` (`#rrggbb`, empty ok), `hnsPoiIcon` (`/^\X$/u`, ≤32 bytes). **Any invalid entry refuses the whole request (400)**, as does an empty list, an unknown map, or a blank category name. `hnsImportCategories` writes all categories in one transaction. Response `{status:'ok', imported, categories:[{id,name,imported}]}`.
5. JS navigates to `/hideandseek/import-pois?map=..&imported=..&skipped=..` to show the counts.

**Export format** (`hnsExportPois`): `{format:'hideandseek-pois', version:1, map, categories:[{name, colour:''|'#..', icon:''|'x', pois:[{osmType, osmId, name, lat, lon}]}]}`; categories with no POIs are omitted; pretty-printed one POI per line by `hnsFormatExport` (JS). A test round-trips export → fresh map → identical export.

Category edits (`hnsSaveCategoryEdits($db, $mapId, $rows, $only)`): validates everything first (non-empty names, valid colours/icons, names unique as a *set* after the edit, ASCII case-folded), renamed rows take a placeholder name (a space plus the id) inside the transaction so swaps work, returns `''` or a localized error.

## Front-end files

- **`js/map.js` (~1000 lines, global `hns*` names).** Click-to-load map: nothing contacts the tile server until the button is pressed. Libraries are injected on demand: MapLibre GL (`vendor/maplibre/`, v4.7.1) and d3-delaunay (`vendor/d3-delaunay/`). Groups: setup (`hnsMapCentre`, `hnsLoadScript/Stylesheet`, `hnsLoadMap`, `hnsRenderMapPlaceholder`, pins `hnsAddMarker` / `hnsMarkers`); styling (`hnsHideLabels`); imported data (`hnsImportedData`, `hnsImportedCategories`, `hnsImportedPois`, `hnsAddImportedLayers`, icon/halo helpers `hnsPoiIconImage`, `hnsTextPresentation`, `hnsIsDark`, `hnsLabelHalo`, `hnsLuminance`); the list (`hnsRenderImportedPoiTable`, `hnsImportedGroup`, `hnsPoiRow`, `hnsFocusPoi`, `hnsRingPoi`, `hnsApplyImportedFilter`); overlays (`hnsOverlay`, `hnsSetOverlay`, `hnsUpdateOverlay`, spherical geometry `hnsVec/Dot/Cross/Unit/Arc/Circumcentre/BisectorRay/NearestLines`, circle union `hnsCircleLines`, `hnsFormatDistance`).
- **`js/poiImport.js`**: parsing and form wiring above, plus the Export button (`hnsInitPoiExport`), colour-input defaulting (`hnsFillUnsetColours`: an `<input type=color>` with no `value` attribute reads `#000000`) and category prefill (`hnsPrefillFromCategory` from the datalist's `data-colour`/`data-icon`).
- **`js/tooltip.js`**: a copy of music's delegated tooltip with `hns` names (`#hnsTooltip` box from `partials/tooltip.php`, `showHnsTooltip`, `hnsTooltipBox`); same `data-tooltip` / `data-tooltip-titles` contract.
- **`json/mapStyle.json`**: OSM Bright (128 layers), repointed to OpenFreeMap tiles/glyphs; edit in Maputnik. Sprite stays on `openmaptiles.github.io`.
- **`css/styles.css`**: ten theme custom properties on `:root` (`ink`, `inkDim`, `ground`, `panel`, `panelRaised`, `border`, `accent`, `accentDim`, `danger`, `imported`); light theme. Layout classes: `hnsLayout`, `hnsMain`, `hnsNav*`, `hnsMapLayout` (two columns ≥1200 px), `hnsMapColumn`, `hnsPoiColumn`, `hnsPoiArea`, `hnsPoiGroup*`.
- **`localStorage` keys** (per-viewer, guarded by try/catch): `hnsHiddenCategories` (JSON list of category ids), `hnsShowUnnamed` (`"1"`).
- Page chrome: `views/partials/head.php` opens `<div class="hnsLayout">`, includes `nav.php`, opens `<main class="hnsMain">`; `foot.php` closes both, so a view only has to include head and foot. `head.php` also ships `csrfToken` meta and `langStrings`.

## Language catalogue

`lang/en.php` merges `lang/en/common.php` and `lang/en/accounts.php`. Key areas: `hideandseek.*`, `map.*`, `poi.*`, `poiImport.*` (incl. `poiImport.error.*`), `gameMap.*` (incl. `gameMap.error.*`), `nav.*`, `ajax.*`, `status.*`, `accounts.*`, `register.*`, `login.*`. Only `en` is configured, so the language menu does not render. Neither `tests/cases/strings.php` nor `i18n-coverage.php` checks this catalogue.

## Duplicated-from-music code

`rateLimit.php`, `inviteCode.php` (`hnsGenerateInviteCode`), `auth.php` (`hns*`), `ajaxGuard.php`, `migrate.php`, `db.php` and `tooltip.js` are intentional near-copies of music's (decision recorded in `hideandseek.md` and TODO). When fixing a bug in one, check the other.
