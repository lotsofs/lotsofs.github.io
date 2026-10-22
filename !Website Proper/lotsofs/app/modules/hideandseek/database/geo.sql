-- The CBS Wijken en Buurten data, in a database file of its own:
-- hideandseek_geo.sqlite. Built at home by tools/import-buurten.php and copied to
-- the host by hand, so the site only ever reads it and the live database, which
-- is written online, is never touched by the build.
--
-- No REFERENCES anywhere, per the site rule (never cascade, never block). code
-- is CBS's own: GM0014, WK001400, BU00140000.

CREATE TABLE gemeente (
    id INTEGER PRIMARY KEY,
    code TEXT NOT NULL,
    name TEXT NOT NULL
);

CREATE TABLE wijk (
    id INTEGER PRIMARY KEY,
    code TEXT NOT NULL,
    name TEXT NOT NULL,
    gemeente_id INTEGER NOT NULL
);

-- polygons is the coordinates of a GeoJSON MultiPolygon as JSON text, zlib
-- compressed into a BLOB (gzcompress; 100MB of text becomes 25MB), the same shape
-- for every buurt (CBS ships them all as one, and the build wraps a Polygon):
-- the map only ever wants the whole shape, and SQLite has no spatial functions
-- to query one with.
CREATE TABLE buurt (
    id INTEGER PRIMARY KEY,
    code TEXT NOT NULL,
    name TEXT NOT NULL,
    wijk_id INTEGER NOT NULL,
    water BOOLEAN NOT NULL DEFAULT 0,
    polygons BLOB NOT NULL
);

-- Both directions of every pair, so "who borders A" is one column.
CREATE TABLE gemeente_neighbour (
    gemeente_id INTEGER NOT NULL,
    neighbour_id INTEGER NOT NULL,
    PRIMARY KEY (gemeente_id, neighbour_id)
) WITHOUT ROWID;

-- Scratch for working out the neighbours; the build drops it again.
CREATE TABLE _vertex (
    lon INTEGER NOT NULL,
    lat INTEGER NOT NULL,
    gemeente_id INTEGER NOT NULL,
    PRIMARY KEY (lon, lat, gemeente_id)
) WITHOUT ROWID;

CREATE UNIQUE INDEX idx_gemeente_code ON gemeente (code);

CREATE UNIQUE INDEX idx_wijk_code ON wijk (code);

CREATE INDEX idx_wijk_gemeente ON wijk (gemeente_id);

CREATE UNIQUE INDEX idx_buurt_code ON buurt (code);

CREATE INDEX idx_buurt_wijk ON buurt (wijk_id);
