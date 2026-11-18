# The mtasa map analysis

Read on demand, from `mtasa.md`. This is how every script column on a map page was made: the owner's Python analysis of the classic MTA:SA race maps, handed over on 2026-11-17 by the session that wrote it, and what of it the site now works out itself in the browser (`public/modules/mtasa/js/analysis.js`).

The maps are one resource per map in the owner's repository `lotsofs/MTASA-Maps`, under `server/mods/deathmatch/resources/[maps]/[classics]/z(N)/`: a `meta.xml` and an `N.map`. **Never modify the `z(N)` folders** (the owner's ground rule).

## The pipeline (`tools/mtasa/`)

Python 3 with no extra packages, run with `-I` from `tools/mtasa/`, never shipped (`build-deploy.php` packs only `app/` and `public/`). It reads three places, each given as an argument:

- **the classics folder**, `d:/Repositories/MTASA-Maps/server/mods/deathmatch/resources/[maps]/[classics]`;
- **the repo root**, `d:/Repositories/MTASA-Maps`, searched for other copies of each map to find titles (skipping `[AncientMaps]`, `resource-cache` and `.git`);
- **the GTA folder**, `D:/Games/Standalone/Grand Theft Auto San Andreas MTA`, for `data/water.dat`, `data/info.zon`, `data/map.zon` and `text/american.gxt`.

Intermediate files go outside both repos: `features.json` is about 140 MB.

```
python -I gxt.py        <gta dir> zones.json                   # zone names; zones.json is already here
python -I extract.py    <classics> <repo root> <gta dir> <tmp>/features.json
python -I dupcheck.py   <tmp>/features.json <tmp>/dupcands.json
python -I analyze.py    <tmp>/features.json ../../app/modules/mtasa/import/classics.csv <tmp>/dupcands.json
python -I points.py     <classics> ../../app/modules/mtasa/import/points.csv
python -I overlay.py    <classics> <first N> <last N>          # writes <classics>/overlays/N.svg
python -I site_zones.py zones.json ../../public/modules/mtasa/data/zones.json ../../app/modules/mtasa/data/zone_names.json
```

- **gxt.py** decodes the zone names: it reads `american.gxt` and matches the zone labels of `info.zon` and `map.zon` by CRC32, into `zones.json`, which `extract.py` loads from its own folder.
- **extract.py** reads every map and meta leniently (UTF-16 or UTF-8, the xml declaration stripped). It simulates the checkpoint chains as the race gamemode builds them, checks spawnpoints against water heights and nearby objects, finds the zone and city of spawnpoints and checkpoints, builds the fingerprint tokens for duplicates, and indexes the copies elsewhere in the repo.
- **dupcheck.py** compares each map with every earlier one: elements count as the same within 3 m, objects only with the same model and pickups the same type. It keeps each map's top 5 candidates with an overall similarity and one per kind (s spawnpoints, c checkpoints, o objects, p pickups).
- **analyze.py** turns the features and candidates into `classics.csv`, which the site imports from `app/modules/mtasa/import/classics.csv` (it was pasted into the sheet's AI tab until 2026-11-17). It writes vehicles and zones by id since then (below), every map from 1 (entry 1 was left out so that entry N sat on line N of the sheet), and no longer Laps, Armed vehicles, Start city or Cities on route.
- **points.py** writes `points.csv`, which the site imports from `app/modules/mtasa/import/points.csv` (it was pasted into the sheet's Mapgen tab until 2026-11-18): only where each map starts and its checkpoints (below). It runs the parsing helpers at the top of `overlay.py` by `exec`, so the two stay together.
- **overlay.py** writes standalone SVG previews in the look `mtasa.md` describes; the site draws its own since 2026-11-16.
- **site_zones.py** (the site's own) writes the slim zones file `analysis.js` reads (below).

A rerun reaches the site by an admin's **Reload the analysis** under the list, which replaces the script's columns from `classics.csv` and leaves the owner's alone (`mtasa.md`, The import). The whole run takes about a minute (2026-11-17). Rerun on 2026-11-17 against the handed-over output, every column matched but the ones whose format changed, and those named the same things, except three start zones whose tie the zone-and-county count used to break (1502, 5147, 5974) and one similarity, z(6751), 97% then 99%: `dupcheck.py` walks Python sets, whose order changes from run to run.

## How the race gamemode reads a map

Read in `old/gamemode-race-default` in the MTASA-Maps repo, not reproduced on a live server.

- **Checkpoint chains.** `nextid` links checkpoints into chains, and **only the first chain is raced**. A `nextid` that merges a chain into itself hangs the race while it loads; no classic map does.
- **Race mode.** Checkpoints make it Sprint; with none, `#respawn` "none" (the default) is Destruction Derby, "timelimit" Freeroam, and "waves" (from old MTA:Race) fits no mode. The site's derived Race mode follows the owner's rule instead, which counts "waves" as Freeroam (`mtasa.md`, Classification).
- **Rotation.** A spawnpoint's `rotation` is degrees anticlockwise from north, the world direction (−sin r, cos r); none means 0.
- **Default colour.** A checkpoint with no `color` is `#0000FE`.

## The columns

`classics.csv` is UTF-8 with a BOM, one row per map number with entry 1 left out so that entry N is on line N; a missing folder is a row empty but for `Errors = folder missing`. Here by the site's sections.

**About and Guesses.** Title and Title source: meta's `<info name>` when it is not just the number, else another copy of the map elsewhere in the repo (at least 75% of exact tokens the same), else "inherited from copy N", a titled member of the same duplicate group. Description, Author and Version: meta's `<info>`, HTML-unescaped, whitespace collapsed.

**Classification guess.** One of Race, Hunt the Hunter, Teamwork, Destruction Derby, Misc, Freeroam, Cops Robbers, Versus Race or Vehicle Weapon Combat, blank when the map has errors: description keywords plus the spawn vehicle mix (police share, armed share, one-against-many grids, checkpoint count, respawn mode), `categorize()` in `analyze.py`. A heuristic; check the low-confidence ones.

**Duplicates.** The best earlier candidate is judged by `judge()`, picked by confidence, then similarity, then the lowest number:

| Rule | Confidence |
|---|---|
| similarity ≥ 0.9 | high |
| similarity ≥ 0.7 | medium |
| spawnpoints ≥ 0.9 and objects ≥ 0.9 and the map has objects ("same spawn grid and objects, other route") | medium with the same author or description, else low |
| similarity ≥ 0.5 | low |
| similarity ≥ 0.3 and the same author, description or spawn grid ("partial match with …") | low |
| the same spawn grid and objects and the same author or description | low |

The older exact-position check (Jaccard ≥ 0.75 on 1 m-rounded tokens) stays as a fallback, so nothing it found is lost. **The owner prefers false positives over false negatives**: low is where they are, and some are versions rather than copies (2892 "smaller bumper cars" against 2891 "large bumper cars") or share only a start grid (1640 against 839). Duplicate of (closest) is the single most similar earlier map, which can differ from the group's first when maps chain A → B → C. The sheet's Duplicate ("triplicate of R") and Copies of this map columns are not imported: the site works the sets out (`mtasa.md`, Not stored, worked out).

**Problems.** Errors break the map: folder missing, no spawnpoints, an empty map file, a meta or map file missing or unreadable, invalid spawn vehicles, unreadable positions, a chain that hangs. Warnings may be wrong; "no objects nearby" means no object within 120 m horizontally from 80 m below to 30 m above, water heights come from `water.dat`, and past ±3000 is open sea at z = 0:

- **under water**: a spawnpoint more than 2 m under the surface, no objects nearby;
- **in water**: a land vehicle's spawnpoint within 1.2 m of the surface, no objects nearby;
- **beyond the map edge**: past ±3000, no objects nearby (boats near z = 0 and aircraft exempt);
- **floating**: above 600 m inside the map, no objects nearby (aircraft exempt);
- **boats on land**, **overlapping spawns** (pairs under 1 m apart), **extremely far** (over 20 km out);
- **checkpoints under water**: more than 3 m under, on a map of land vehicles only;
- **chain problems**: several chains, duplicate checkpoint ids, or a `nextid` to an id that does not exist;
- **"waves" with no checkpoints**, and **stray meta attributes** on `<info>` (244 has its map data pasted into meta).

Notes are informational: only N spawnpoints, interior height (z > 700), a description that says test or beta, a description's lap count that differs from the checkpoints, other copies titled otherwise, extra files, unknown elements.

**Settings.** Respawn enabled is `null` when `#respawn` is not set, `false` for "none", `true` otherwise. Respawn mode, Respawn time, Duration, Time of day and Weather are the raw `#respawn`, `#respawntime`, `#duration`, `#time` and `#weather`.

**Route.** Checkpoints counts them all, Checkpoints used by race the first chain's. Returns to start is "yes" when the finish is under 75 m horizontally (height ignored) from the middle of the spawn grid, so a finish right above the start on a multi-level map counts, and 75 m is arbitrary. Route length is the 3D distance from the middle of the spawn grid through the raced checkpoints. Ring checkpoints and Checkpoint vehicle changes count `type="ring"` and checkpoints with a `vehicle`. The site dropped Laps (the owner, 2026-11-17: its rule, the shortest period with every checkpoint *i* and *i+p* within 5 m, gave inaccurate results; `analyze.py` still uses its own for the lap-count note) and adds Route length 2D, worked out from the stored points.

**Content.** Spawnpoints and Objects are counts; Top object models the 5 most used, `M xN`. Repair, Nitro and Vehicle change pickups count `racepickup` types. Start vehicles gives each model id with its count, `461 x16, 522`; Vehicles via changes every model reached by a vehicle change pickup or a checkpoint's vehicle, commonest first (it named 25, then "...", until 2026-11-17). The site names them. Vehicle classes are car, motorbike, boat, plane and so on. The site dropped Armed vehicles (the owner: not needed).

**Location, by zone label.** A zone is stored as its game text label (`GANTON`; one per name, Linden Station alone having two), with two ids of the site's own, `OUTSIDE` past ±3000 and `SA` where no county holds the point; the site names them. Start zone is every zone holding a spawnpoint, commonest first; Finish zone the last raced checkpoint's. A zone is the smallest `info.zon` box holding the point with its height allowed 5 m under and 50 m over, else the smallest holding it at any height, else the county. Start city and Cities on route are gone (the owner, 2026-11-17); Zones on route is worked out only, in the browser.

## points.csv

UTF-8, no BOM, header `folder,spawnpoints,checkpoints`, one row per existing `z(N)` folder in numeric order (missing folders left out, so line numbers are not map numbers). Cells are compact JSON, x and y only, rounded to 0.1 m, whole numbers without `.0`: `spawnpoints` one point, `[[x, y]]`, the middle of the spawn grid, or `[]` for a map with none, and `[[x, y, r]]` on a map with no checkpoints, `r` the furthest spawnpoint from the middle, the radius of the arena the site rings; `checkpoints` `[[x, y], …]`, every chain in turn, the raced one first, then any left over. That is all since 2026-11-18 (the owner's choice): every spawnpoint with its rotation, the checkpoint colours, the pickups and the heights come from the map's own files on GitHub on Increase detail, and the file went from 11.6 to 3.1 MB. (It used to carry them, its rounding and its colours without `#` keeping every cell under the 50,000 characters a sheet cell holds.)

## What the site works out itself

`analysis.js` reads a map's own files from GitHub on Increase detail (`mtasa.md`, the preview's settings) and works out, as `analyze.py` did:

- **Start, Route and Finish**: Spawnpoints, Start zone, Checkpoints, Checkpoints used by race (the chains ported as `mtasaSimulateChains()`), Route length, Route length 2D, Ring checkpoints, Checkpoint vehicle changes, Finish zone, Returns to start;
- the three pickup counts, Objects, Top object models, Start vehicles, Vehicles via changes, Vehicle classes, and Zones on route, which nothing stores;
- and for each copy the owner has confirmed, how much of its checkpoints, spawnpoints and pickups are the same as the marked map's (`mtasaComparePoints()`, the site's own measure, `mtasa.md`), from both maps' files;
- **Settings**: all six.

**Checked against the Python on all 6,504 folders** (2026-11-17, in headless Chrome over the local files, against the rerun's `classics.csv`): every one of the 23 columns matches on every map, except Route length on 3 (z(3534), z(3536), z(3565)), whose routes are exactly 18.5 m. JS rounds that up and Python to the even 18; the owner chose JS's own rounding and sorting over copying Python's, so those three show a ! after Increase detail. The checkpoint order the preview draws matches `points.csv` on every map.

**The zones file** is `public/modules/mtasa/data/zones.json`, 26 KB, written by `site_zones.py` from `zones.json` (58 KB): `info.zon` only, as `{"counties": [[label, x1, y1, x2, y2], …], "zones": [[label, x1, y1, z1, x2, y2, z2], …], "names": {label: name}}`, the zones sorted smallest area first; it also writes the names alone to `app/modules/mtasa/data/zone_names.json` for PHP. Dropped as never read: `map.zon` (6 unnamed entries), `type` and `level` (the same in every zone), `code` (it only told the 9 counties from the 369 zones, which two lists do) and the counties' heights.

**Vehicle names** are MTA's 212, the script's, except 599, which the owner calls Ranger rather than Police Ranger: `app/modules/mtasa/data/vehicles.json`, the one list, which PHP reads and the map page hands to the browser.

**Still Python only**, and why:

- **duplicates**: they compare every map with every earlier one;
- **titles from other copies**: they search the rest of the MTASA-Maps repo;
- **the water checks**: they need `water.dat` from the GTA install;
- **the rest of Problems, the Notes, the category guess, and About's author, description, version and meta title**: portable, not ported yet (JSP in `TODO.md`).

## Caveats

- **The backdrop does not suit every map.** 98 and 99 sit about 1,040 m up over the sea, 79 and 95 past the world's edge, and interiors (z > 700) are drawn over whatever lies below them.
- **Behaviour was read, not reproduced**: the race gamemode's chains, modes and defaults come from its Lua, and no map was run on a live server.
