PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS artist (
    id INTEGER PRIMARY KEY
    -- name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS artist_alias (
    id INTEGER PRIMARY KEY,
    artist_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    is_actual BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (artist_id) REFERENCES artist(id)
);




CREATE TABLE IF NOT EXISTS composition (
    id INTEGER PRIMARY KEY
);

CREATE TABLE IF NOT EXISTS song (
    id INTEGER PRIMARY KEY,
    composition_id INTEGER NOT NULL,
    subtitle TEXT,
    version TEXT,
    FOREIGN KEY (composition_id) REFERENCES composition(id)
);

CREATE TABLE IF NOT EXISTS song_cover (
    id INTEGER PRIMARY KEY,
    original_id INTEGER NOT NULL,
    cover_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    FOREIGN KEY (original_id) REFERENCES song(id),
    FOREIGN KEY (cover_id) REFERENCES song(id),
    CHECK (original_id <> cover_id)
);


CREATE TABLE IF NOT EXISTS song_artist (
    id INTEGER PRIMARY KEY,
    song_id INTEGER NOT NULL,
    artist_id INTEGER NOT NULL,
    role TEXT,
    is_primary BOOLEAN NOT NULL DEFAULT 1,
    FOREIGN KEY (song_id) REFERENCES song(id),
    FOREIGN KEY (artist_id) REFERENCES artist(id)
);

CREATE TABLE IF NOT EXISTS album (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    year INTEGER,
    type TEXT NOT NULL
);

-- CREATE TABLE IF NOT EXISTS album_version (
--     id INTEGER PRIMARY KEY,
--     album_id INTEGER NOT NULL,
--     edition TEXT,
--     FOREIGN KEY (album_id) REFERENCES album(id)
-- );

CREATE TABLE IF NOT EXISTS album_artist (
    id INTEGER PRIMARY KEY,
    artist_id INTEGER NOT NULL,
    album_id INTEGER NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT 1,
    FOREIGN KEY (artist_id) REFERENCES artist(id),
    FOREIGN KEY (album_id) REFERENCES album(id)
);

CREATE TABLE IF NOT EXISTS track (
    id INTEGER PRIMARY KEY,
    song_id INTEGER NOT NULL,
    album_id INTEGER NOT NULL,
    disc INTEGER,
    track_number INTEGER,
    title TEXT,
    duration INTEGER,
    year INTEGER,
    -- special_edition TEXT,
    FOREIGN KEY (song_id) REFERENCES song(id),
    FOREIGN KEY (album_id) REFERENCES album(id)
);

-- investigate:
-- CREATE VIEW fast_access_song_details AS ....
-- CREATE INDEX index_song_version ON SONG(Version);


CREATE UNIQUE INDEX IF NOT EXISTS idx_song_artist_unique
ON song_artist (song_id, artist_id, role);

CREATE UNIQUE INDEX IF NOT EXISTS idx_album_artist_unique
ON album_artist (album_id, artist_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_artist_alias_unique
ON artist_alias (artist_id, name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_artist_alias_one_actual
ON artist_alias (artist_id) WHERE is_actual = 1;

CREATE INDEX IF NOT EXISTS idx_song_composition ON song(composition_id);
CREATE INDEX IF NOT EXISTS idx_track_song ON track(song_id);
CREATE INDEX IF NOT EXISTS idx_track_album ON track(album_id);
CREATE INDEX IF NOT EXISTS idx_song_artist_song ON song_artist(song_id);
CREATE INDEX IF NOT EXISTS idx_song_artist_artist ON song_artist(artist_id);