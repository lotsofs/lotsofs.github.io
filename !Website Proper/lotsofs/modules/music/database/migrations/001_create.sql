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
    objective_note TEXT,
    FOREIGN KEY (artist_id) REFERENCES artist(id)
);

CREATE TABLE IF NOT EXISTS song_alias (
    id INTEGER PRIMARY KEY,
    song_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    is_actual BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (song_id) REFERENCES song(id)
);

CREATE TABLE IF NOT EXISTS album (
    id INTEGER PRIMARY KEY,
    artist_id INTEGER,
    release_year INTEGER,
    FOREIGN KEY (artist_id) REFERENCES artist(id)
);

CREATE TABLE IF NOT EXISTS album_alias (
    id INTEGER PRIMARY KEY,
    album_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    is_actual BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (album_id) REFERENCES album(id)
);

CREATE TABLE IF NOT EXISTS album_track (
    id INTEGER PRIMARY KEY,
    album_id INTEGER NOT NULL,
    song_id INTEGER NOT NULL,
    song_alias_id INTEGER,
    position INTEGER,
    FOREIGN KEY (album_id) REFERENCES album(id),
    FOREIGN KEY (song_id) REFERENCES song(id),
    FOREIGN KEY (song_alias_id) REFERENCES song_alias(id)
);

CREATE TABLE IF NOT EXISTS account (
    id INTEGER PRIMARY KEY,
    account_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    is_admin BOOLEAN NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS account_song (
    id INTEGER PRIMARY KEY,
    account_id INTEGER NOT NULL,
    song_id INTEGER NOT NULL,
    score REAL,
    subjective_note TEXT,
    updated_at INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (account_id) REFERENCES account(id),
    FOREIGN KEY (song_id) REFERENCES song(id)
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

CREATE UNIQUE INDEX IF NOT EXISTS idx_artist_alias_unique
ON artist_alias (artist_id, name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_artist_alias_one_actual
ON artist_alias (artist_id) WHERE is_actual = 1;

CREATE UNIQUE INDEX IF NOT EXISTS idx_song_alias_unique
ON song_alias (song_id, name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_song_alias_one_actual
ON song_alias (song_id) WHERE is_actual = 1;

CREATE INDEX IF NOT EXISTS idx_song_alias_name ON song_alias (name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_album_alias_unique
ON album_alias (album_id, name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_album_alias_one_actual
ON album_alias (album_id) WHERE is_actual = 1;

CREATE UNIQUE INDEX IF NOT EXISTS idx_album_track_unique
ON album_track (album_id, song_id);

CREATE INDEX IF NOT EXISTS idx_album_track_song ON album_track (song_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_account_name_unique
ON account (account_name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_account_song_unique
ON account_song (account_id, song_id);

CREATE INDEX IF NOT EXISTS idx_account_song_song ON account_song (song_id);

CREATE INDEX IF NOT EXISTS idx_account_song_updated ON account_song (updated_at);

CREATE UNIQUE INDEX IF NOT EXISTS idx_invite_code_unique
ON invite (code);

CREATE INDEX IF NOT EXISTS idx_login_attempt_ip
ON login_attempt (ip, attempted_at);
