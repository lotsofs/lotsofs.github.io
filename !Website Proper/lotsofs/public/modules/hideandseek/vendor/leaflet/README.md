# Leaflet 1.9.4 — vendored

Unmodified files from `https://unpkg.com/leaflet@1.9.4/dist/`:

- `leaflet.js`
- `leaflet.css`
- `images/marker-icon.png`, `marker-icon-2x.png`, `marker-shadow.png`, `layers.png`, `layers-2x.png`

BSD-2-Clause. The licence header at the top of `leaflet.js` must stay.

Vendored rather than loaded from a CDN so that no third party sees a visitor's
IP just for opening the page, and so the map still works if unpkg is down.

`leaflet.js.map` is deliberately **not** here — it is 225KB, larger than the
library, and only ever fetched with devtools open. That is why the console shows
a 404 for it while debugging.

To update: replace these files from the same paths at the new version, check
`leaflet.css` still only references `images/` relatively, and re-check the dark
theme overrides in `public/modules/hideandseek/css/styles.css`.
