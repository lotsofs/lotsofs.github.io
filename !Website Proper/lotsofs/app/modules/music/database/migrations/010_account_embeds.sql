-- Every schema change for the deploy after 2026-11-12: one migration per
-- deploy, so each change below is added here until this file ships.
--
-- Whether song cards load the Spotify, YouTube and SoundCloud players, which
-- set cookies and reach those services. 0 for every account, existing ones
-- included, since consent cannot be assumed; the cog menu in the nav is the
-- only thing that writes it.
ALTER TABLE account ADD COLUMN embeds BOOLEAN NOT NULL DEFAULT 0;
