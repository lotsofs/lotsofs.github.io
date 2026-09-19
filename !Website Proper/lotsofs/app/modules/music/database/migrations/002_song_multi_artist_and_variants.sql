-- 002_song_multi_artist_and_variants.sql
--
-- 1. Adds song_artist: many-to-many link between song and artist,
--    replacing song.artist_id (which supported only one artist per song).
-- 2. Rebuilds song: drops objective_note and artist_id. song_alias keeps
--    its original job as the sole source of a song's name/title history.
-- 3. Adds song_relationship: directed duplicate/variant links between
--    songs (e.g. "radio edit of", "CD release of"), free-text described.
-- 4. Adds song_link: external links for a song, one row per song with a
--    fixed column per platform (Spotify, YouTube, SoundCloud, a local file
--    path, and a catch-all "other").
--
-- This needs a full table rebuild rather than a plain ALTER TABLE:
-- SQLite refuses to DROP COLUMN artist_id because it's part of a FOREIGN
-- KEY constraint. The rebuild runs inside migrate.php's already-open
-- transaction, so PRAGMA foreign_keys=OFF is a no-op here; defer_foreign_keys
-- is used instead. Verified empirically: dropping/recreating song while
-- song_alias/album_track/account_song still hold rows that reference it
-- trips SQLite's deferred-violation counter even under defer_foreign_keys,
-- and nothing afterward clears it - the fix is to stash and empty those
-- dependent tables first (deepest dependency first), rebuild song, then
-- restore them (shallowest first). song_artist is populated only after
-- the rebuild for the same reason.

PRAGMA foreign_keys = ON;
PRAGMA defer_foreign_keys = ON;

-- Step 1: capture what we need from the CURRENT state before any
-- structural changes. song_artist is populated later (after the song
-- rebuild), but the source data has to be captured now.
CREATE TEMP TABLE stash_song_artist AS
    SELECT id AS song_id, artist_id
    FROM song
    WHERE artist_id IS NOT NULL;

-- Final shape of the rebuilt song table: just the id. Titles stay purely
-- in song_alias, as before.
CREATE TABLE song_new (
    id INTEGER PRIMARY KEY
);

INSERT INTO song_new (id)
SELECT id FROM song;

-- Step 2: empty every table with a live FK into song or (transitively)
-- into song_alias, deepest dependency first. Rows are preserved in TEMP
-- tables and restored after the rebuild.
CREATE TEMP TABLE stash_album_track AS SELECT * FROM album_track;
DELETE FROM album_track;

CREATE TEMP TABLE stash_account_song AS SELECT * FROM account_song;
DELETE FROM account_song;

CREATE TEMP TABLE stash_song_alias AS SELECT * FROM song_alias;
DELETE FROM song_alias;

-- Step 3: rebuild song itself.
DROP TABLE song;
ALTER TABLE song_new RENAME TO song;

-- Step 4: restore the emptied tables, shallowest dependency first.
INSERT INTO song_alias SELECT * FROM stash_song_alias;
DROP TABLE stash_song_alias;

INSERT INTO account_song SELECT * FROM stash_account_song;
DROP TABLE stash_account_song;

INSERT INTO album_track SELECT * FROM stash_album_track;
DROP TABLE stash_album_track;

-- Step 5: song <-> artist many-to-many link table, populated only now
-- that `song` exists again with the correct id values.
CREATE TABLE IF NOT EXISTS song_artist (
    id INTEGER PRIMARY KEY,
    song_id INTEGER NOT NULL,
    artist_id INTEGER NOT NULL,
    FOREIGN KEY (song_id) REFERENCES song(id),
    FOREIGN KEY (artist_id) REFERENCES artist(id)
);

INSERT INTO song_artist (song_id, artist_id)
SELECT song_id, artist_id FROM stash_song_artist;

DROP TABLE stash_song_artist;

CREATE UNIQUE INDEX IF NOT EXISTS idx_song_artist_unique ON song_artist (song_id, artist_id);
CREATE INDEX IF NOT EXISTS idx_song_artist_artist ON song_artist (artist_id);

-- Step 6: new directed song-relationship (duplicate/variant) table.
-- relationship_note is free text by design (e.g. "radio edit of", "CD
-- release of", "game file version of", "first half of"); no enum/CHECK
-- on its values. variant_song_id is the derivative song (A),
-- target_song_id is the original it's described relative to (T); the
-- relationship is directed, not symmetric. Created empty.
CREATE TABLE IF NOT EXISTS song_relationship (
    id INTEGER PRIMARY KEY,
    variant_song_id INTEGER NOT NULL,
    target_song_id INTEGER NOT NULL,
    relationship_note TEXT NOT NULL,
    FOREIGN KEY (variant_song_id) REFERENCES song(id),
    FOREIGN KEY (target_song_id) REFERENCES song(id),
    CHECK (variant_song_id != target_song_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_song_relationship_unique ON song_relationship (variant_song_id, target_song_id);
CREATE INDEX IF NOT EXISTS idx_song_relationship_target ON song_relationship (target_song_id);

-- Step 7: song links - one row per song, a fixed column per platform
-- (Spotify and YouTube get embedded players; the rest are plain links).
-- Created empty.
CREATE TABLE IF NOT EXISTS song_link (
    song_id INTEGER PRIMARY KEY,
    spotify_url TEXT,
    youtube_url TEXT,
    soundcloud_url TEXT,
    bandcamp_url TEXT,
    filepath TEXT,
    other_url TEXT,
    FOREIGN KEY (song_id) REFERENCES song(id)
);

PRAGMA foreign_key_check;
