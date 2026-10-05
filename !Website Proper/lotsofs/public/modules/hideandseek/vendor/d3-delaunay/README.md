# d3-delaunay 6.0.4 — vendored

Unmodified `dist/d3-delaunay.min.js` from
`https://cdn.jsdelivr.net/npm/d3-delaunay@6.0.4/`, with its `LICENSE` (ISC).
Delaunator and robust-predicates are bundled inside it, so it needs nothing else.
It sets a global `d3` with `Delaunay` and `Voronoi`.

Used by `js/map.js` for the "nearest" toggle on an imported category: the Voronoi
diagram of that category's POIs, drawn as the borders between areas nearest to
each one, by great-circle distance (`map.js` triangulates a stereographic projection with it and places the corners itself). 19KB, injected only the first time a toggle is switched on, like
MapLibre.

To update: replace the file from the same path at the new version and keep the
copyright header at its top.
