-- 003_song_year.sql
--
-- Adds a nullable year to song: the song's own year, which can differ
-- from whichever album it later ends up on (e.g. a compilation track
-- keeps its original recording year even though the compilation itself
-- released much later). Where a song has no year of its own, the songs
-- page falls back to the earliest release_year among the albums it's
-- linked to - that fallback is computed at query time, not stored here.

ALTER TABLE song ADD COLUMN year INTEGER;
