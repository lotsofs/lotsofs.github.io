-- Every schema change for the deploy after 2026-11-02: one migration per
-- deploy, so each change below is added here until this file ships.
--
-- Which song list columns a person has ticked or unticked in the column picker,
-- one row per column they have changed. column_key is one of the keys in
-- songColumns.php or rater_<account id>; a column with no row takes its default
-- from there, so a column added later gets its own default even for someone who
-- has already chosen. The picker is the only thing that writes it.
CREATE TABLE account_song_column (
	account_id INTEGER NOT NULL,
	column_key TEXT NOT NULL,
	shown BOOLEAN NOT NULL,
	PRIMARY KEY (account_id, column_key)
);

-- song_relationship's free-text relationship_note becomes relationship_type, a
-- code from musicSongRelationTypes() in relationships.php, which names it from
-- either end ("remastered version of" from the variant, "original version of"
-- from the target). One row per pair: variant_song_id is the derivative,
-- target_song_id the original. Rebuilt rather than RENAME COLUMN, which needs
-- SQLite 3.25, because the host's version is not known. The table was empty
-- and nothing references it; like every new table here it declares no
-- REFERENCES, and no index, since it stays small.
CREATE TABLE song_relationship_new (
	id INTEGER PRIMARY KEY,
	variant_song_id INTEGER NOT NULL,
	target_song_id INTEGER NOT NULL,
	relationship_type TEXT NOT NULL,
	CHECK (variant_song_id != target_song_id)
);

INSERT INTO song_relationship_new (id, variant_song_id, target_song_id, relationship_type)
SELECT id, variant_song_id, target_song_id, relationship_note FROM song_relationship;

DROP TABLE song_relationship;

ALTER TABLE song_relationship_new RENAME TO song_relationship;

-- An append-only log of what admins change, shown on /music/audit beside
-- rating_audit. Kept apart from rating_audit, whose rows the poll turns into
-- toasts and whose note times decide which borrowed note is newest. kind is
-- song, album or artist, entity_id the one it is about; value and
-- previous_value are text as it read at the time (names, not ids), written by
-- musicLogEdit() in editAudit.php, which skips a write that changes nothing.
CREATE TABLE edit_audit (
	id INTEGER PRIMARY KEY,
	account_id INTEGER NOT NULL,
	kind TEXT NOT NULL,
	entity_id INTEGER NOT NULL,
	field TEXT NOT NULL,
	value TEXT,
	previous_value TEXT,
	created_at INTEGER NOT NULL DEFAULT 0
);

-- The links on the music home page, in position order, each a view of the song
-- list: an artist or album filter and a sort, stored as parts rather than as an
-- address so a rename shows in the label. label NULL means the song list's own
-- heading for that filter. At most one of artist_id and album_id is set; an
-- album link takes its artist from the album when it is drawn, since the song
-- list wants both. Admins pick them on the home page (homeLinks.php); not in
-- edit_audit, which is for songs, albums and artists.
CREATE TABLE home_link (
	id INTEGER PRIMARY KEY,
	position INTEGER NOT NULL,
	label TEXT,
	note TEXT,
	artist_id INTEGER,
	album_id INTEGER,
	sort TEXT NOT NULL DEFAULT 'id',
	dir TEXT NOT NULL DEFAULT 'asc'
);
