-- 004_song_duration.sql
--
-- Adds a nullable duration to song, stored as whole seconds. The songs page
-- renders it as mm:ss, and minutes are never carried over into hours, so a
-- 75 minute live recording reads as 75:00 rather than 1:15:00.

ALTER TABLE song ADD COLUMN duration INTEGER;
