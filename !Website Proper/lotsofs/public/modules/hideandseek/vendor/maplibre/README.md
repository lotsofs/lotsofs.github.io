# MapLibre GL JS 4.7.1 — vendored

Unmodified `maplibre-gl.js` and `maplibre-gl.css` from
`https://unpkg.com/maplibre-gl@4.7.1/dist/`. 3-Clause BSD; the licence header at
the top of the js must stay.

Vendored rather than loaded from a CDN so that no third party sees a visitor's
IP just for opening the page, and so the map still works if unpkg is down. The
tile server is a separate matter — see below.

803KB, and it needs WebGL, which is why `js/map.js` injects the script only when
somebody presses the Load map button rather than shipping it to everyone.

`maplibre-gl.js.map` is deliberately not here: it is only ever fetched with
devtools open, which is why the console shows a 404 for it while debugging.

To update: replace both files from the same paths at the new version and
re-check the control overrides in `public/modules/hideandseek/css/styles.css`.

## The style

The basemap is painted by `../../json/mapStyle.json` — **OSM Bright**, the
OpenMapTiles reference style, 128 layers. Nothing in this folder draws anything;
`map.js` only loads the library and handles markers.

**Any style pasted in must be repointed first.** Upstream copies ship pointing at
whichever vendor published them, with that vendor's API key, and will render a
blank map here. Two fields:

    sources.openmaptiles.url  ->  https://tiles.openfreemap.org/planet
    glyphs                    ->  https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf

A test enforces this — it fails on any tile or glyph host that is not
OpenFreeMap, and on any credential-shaped string in the file.

Edit in Maputnik (https://maplibre.org/maputnik) via **Open → Upload**, choosing
this file. Its Open dialog offers gallery styles right beside the upload button;
picking one silently replaces everything, which has now happened twice.

The sprite is the one exception, still fetched from `openmaptiles.github.io`. It
works, and the icon names only match that sheet — swapping to another sprite
would empty the 11 layers that use `icon-image`. Vendor its four files here to
drop that host when it matters.

Bus routes are not drawn and cannot be from these tiles: a bus route is a
`type=route` relation in OSM and nothing in the tileset carries one. Route
relations in general do arrive — `transportation_name` has `route_1_*` …
`route_21_*` fields, carrying walking networks over Groningen — but no bus ones,
so a road a bus runs along looks like any other road. Drawing lines would need a
Geofabrik extract or a GTFS feed baked into a file.

The map draws no text of the style's own: `map.js` hides every symbol layer
that has a `text-field` at load, so the only labels are the imported POIs'.
Maputnik's preview will show labels that the site does not. Nothing to preserve
through a re-paste — that is why the rule is not in the style.
