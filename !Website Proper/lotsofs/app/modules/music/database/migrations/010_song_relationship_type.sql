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
