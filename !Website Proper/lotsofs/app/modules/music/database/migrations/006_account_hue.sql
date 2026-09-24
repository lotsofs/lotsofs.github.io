-- The hue, 0-359, that this account sees the site in and that its dots are
-- drawn with on an album card. NULL means the account has never picked one and
-- takes the hue its id gives it (see musicHueFor in hue.php), so a new account
-- lands on a distinct colour without anything having to write this column.
ALTER TABLE account ADD COLUMN hue INTEGER;

-- Neither index was ever used: every query against rating_audit goes by rowid
-- (WHERE ra.id > ? in songRatingPoll.php, the INSERT in songRating.php,
-- MAX(id) in routes/songs.php), so song_id and created_at appear in no WHERE
-- or ORDER BY. They cost a B-tree write per rating change and buy nothing.
DROP INDEX IF EXISTS idx_rating_audit_song;
DROP INDEX IF EXISTS idx_rating_audit_created;

-- Same story: song_relationship is referenced by no application code at all.
DROP INDEX IF EXISTS idx_song_relationship_unique;
DROP INDEX IF EXISTS idx_song_relationship_target;
