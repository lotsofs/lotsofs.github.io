PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS artist (
    id INTEGER PRIMARY KEY
);

CREATE TABLE IF NOT EXISTS artist_alias (
    id INTEGER PRIMARY KEY,
    artist_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    is_actual BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (artist_id) REFERENCES artist(id)
);

CREATE TABLE IF NOT EXISTS song (
    id INTEGER PRIMARY KEY,
    artist_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    objective_note TEXT,
    FOREIGN KEY (artist_id) REFERENCES artist(id)
);

CREATE TABLE IF NOT EXISTS account (
    id INTEGER PRIMARY KEY,
    account_name TEXT NOT NULL,
    password_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS account_song (
    id INTEGER PRIMARY KEY,
    account_id INTEGER NOT NULL,
    song_id INTEGER NOT NULL,
    score REAL,
    subjective_note TEXT,
    FOREIGN KEY (account_id) REFERENCES account(id),
    FOREIGN KEY (song_id) REFERENCES song(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_artist_alias_unique
ON artist_alias (artist_id, name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_artist_alias_one_actual
ON artist_alias (artist_id) WHERE is_actual = 1;

CREATE UNIQUE INDEX IF NOT EXISTS idx_song_unique
ON song (artist_id, title);

CREATE UNIQUE INDEX IF NOT EXISTS idx_account_name_unique
ON account (account_name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_account_song_unique
ON account_song (account_id, song_id);

CREATE INDEX IF NOT EXISTS idx_account_song_song ON account_song (song_id);
