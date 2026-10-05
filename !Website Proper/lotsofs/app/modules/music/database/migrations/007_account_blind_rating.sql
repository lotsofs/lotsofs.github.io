-- Whether this account has other people's scores and notes covered on a song it
-- has not rated itself. 1 is the behaviour the song list already had when this
-- column was added, so every existing account keeps it; the cog menu in the nav
-- is the only thing that writes it.
ALTER TABLE account ADD COLUMN blind_rating BOOLEAN NOT NULL DEFAULT 1;
