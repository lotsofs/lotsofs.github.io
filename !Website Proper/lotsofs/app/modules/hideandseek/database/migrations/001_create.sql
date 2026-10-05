-- 001_create.sql
--
-- The account system this module starts from: accounts, the invite codes that
-- gate registration after the first one, and the failed-login record the rate
-- limiter reads. Plus the game's own tables: game_map, and the POI categories
-- and POIs imported onto one.
--
-- Still unshipped, so still editable - but a database that has already recorded
-- it will not re-run it, so an edit here means resetting dev databases too.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS account (
    id INTEGER PRIMARY KEY,
    account_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    is_admin BOOLEAN NOT NULL DEFAULT 0,
    lang TEXT NOT NULL DEFAULT 'en'
);

CREATE TABLE IF NOT EXISTS login_attempt (
    id INTEGER PRIMARY KEY,
    ip TEXT NOT NULL,
    attempted_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS invite (
    id INTEGER PRIMARY KEY,
    code TEXT NOT NULL,
    created_at TEXT NOT NULL,
    created_by_account_id INTEGER,
    used_at TEXT,
    used_by_account_id INTEGER,
    revoked_at TEXT,
    FOREIGN KEY (created_by_account_id) REFERENCES account(id),
    FOREIGN KEY (used_by_account_id) REFERENCES account(id)
);

-- One game, named. Everything the game holds hangs off this id later.
CREATE TABLE IF NOT EXISTS game_map (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS poi_category (
    id INTEGER PRIMARY KEY,
    game_map_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    colour TEXT,
    icon TEXT,
    FOREIGN KEY (game_map_id) REFERENCES game_map(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS poi (
    id INTEGER PRIMARY KEY,
    poi_category_id INTEGER NOT NULL,
    osm_type TEXT NOT NULL,
    osm_id INTEGER NOT NULL,
    name TEXT,
    lat REAL NOT NULL,
    lon REAL NOT NULL,
    FOREIGN KEY (poi_category_id) REFERENCES poi_category(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_account_name ON account (account_name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_poi_category_name ON poi_category (game_map_id, name COLLATE NOCASE);

CREATE UNIQUE INDEX IF NOT EXISTS idx_poi_osm ON poi (poi_category_id, osm_type, osm_id);

-- NOCASE, and the route's duplicate check collates the same way: if the two
-- disagreed, a clash would skip the message and surface as a 500 on the insert.
CREATE UNIQUE INDEX IF NOT EXISTS idx_game_map_name ON game_map (name COLLATE NOCASE);

CREATE UNIQUE INDEX IF NOT EXISTS idx_invite_code ON invite (code);

CREATE INDEX IF NOT EXISTS idx_login_attempt_ip ON login_attempt (ip, attempted_at);
