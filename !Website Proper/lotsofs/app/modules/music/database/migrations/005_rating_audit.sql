-- 005_rating_audit.sql
--
-- Adds an append-only log of score/note writes. account_song only ever holds
-- current state - one row per account/song, overwritten in place - so who
-- changed what, and what it used to be, was unrecoverable before this.
--
-- previous_value makes the very first logged edit of an already-existing
-- rating readable, since the log starts partway through that history.
--
-- The id is what the songs page polls on: monotonic, so a strict id > cursor
-- hands each event to a browser exactly once, unlike the account_song poll
-- whose inclusive updated_at cursor re-delivers the same row every tick.

CREATE TABLE IF NOT EXISTS rating_audit (
    id INTEGER PRIMARY KEY,
    account_id INTEGER NOT NULL,
    song_id INTEGER NOT NULL,
    field TEXT NOT NULL,
    value TEXT,
    previous_value TEXT,
    created_at INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (account_id) REFERENCES account(id),
    FOREIGN KEY (song_id) REFERENCES song(id)
);

CREATE INDEX IF NOT EXISTS idx_rating_audit_song ON rating_audit (song_id);
CREATE INDEX IF NOT EXISTS idx_rating_audit_created ON rating_audit (created_at);
