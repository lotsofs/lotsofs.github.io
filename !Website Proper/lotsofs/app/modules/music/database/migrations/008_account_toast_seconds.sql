-- How long a toast on the song list stays, in seconds. 0 means rating toasts
-- are off and -1 means they stay until closed; anything else must be one of
-- MUSIC_TOAST_CHOICES in toastDuration.php. 180 is the lifetime toasts had when
-- this column was added, so every existing account keeps it; the cog menu in
-- the nav is the only thing that writes it.
ALTER TABLE account ADD COLUMN toast_seconds INTEGER NOT NULL DEFAULT 180;
