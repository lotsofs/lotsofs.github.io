-- 001_create.sql
--
-- The account system (accounts, the invite codes that gate registration after
-- the first one, the failed-login record the rate limiter reads), and the map
-- catalogue that moved here off the owner's Google sheet: one row per map with
-- the owner's own fields (the sheet's Numbered tab), one row per map with what
-- the analysis script found in its files (the AI tab), kept apart so a re-run
-- of the script can replace it without touching the owner's fields, the
-- points the script found in each map (the Mapgen tab) for its preview, and the
-- comment threads anyone signed in can leave on a map or on one of its fields.
--
-- Still unshipped, so still editable - but a database that has already recorded
-- it will not re-run it, so an edit here means deleting mtasa_test.sqlite.
--
-- No REFERENCES and no ON DELETE, by the site rule: the code keeps the ids in
-- step, and a row whose map or account is gone is left as it is.

CREATE TABLE IF NOT EXISTS account (
    id INTEGER PRIMARY KEY,
    account_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    is_admin BOOLEAN NOT NULL DEFAULT 0,
    lang TEXT NOT NULL DEFAULT 'en'
);

CREATE TABLE IF NOT EXISTS login_attempt (
    id INTEGER PRIMARY KEY,
    ip TEXT NOT NULL,
    attempted_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS invite (
    id INTEGER PRIMARY KEY,
    code TEXT NOT NULL,
    created_at TEXT NOT NULL,
    created_by_account_id INTEGER,
    used_at TEXT,
    used_by_account_id INTEGER,
    revoked_at TEXT
);

-- The id is the map's number, z(N).
CREATE TABLE IF NOT EXISTS map (
    id INTEGER PRIMARY KEY,
    original_name TEXT,
    custom_name TEXT,
    hypothesized_name TEXT,
    category TEXT,
    video TEXT,
    status TEXT,
    gamemode TEXT,
    duplicate_of INTEGER,
    custom_description TEXT,
    finished BOOLEAN,
    updated_at INTEGER
);

CREATE TABLE IF NOT EXISTS map_analysis (
    map_id INTEGER PRIMARY KEY,
    folder TEXT,
    title TEXT,
    title_source TEXT,
    description TEXT,
    author TEXT,
    version TEXT,
    category_guess TEXT,
    category_confidence TEXT,
    category_reason TEXT,
    duplicate_confidence TEXT,
    duplicate_of_closest INTEGER,
    duplicate_similarity INTEGER,
    duplicate_differences TEXT,
    errors TEXT,
    warnings TEXT,
    respawn_enabled BOOLEAN,
    respawn_mode TEXT,
    respawn_time INTEGER,
    duration INTEGER,
    time_of_day TEXT,
    weather INTEGER,
    checkpoints INTEGER,
    checkpoints_used INTEGER,
    returns_to_start BOOLEAN,
    route_length INTEGER,
    ring_checkpoints INTEGER,
    checkpoint_vehicle_changes INTEGER,
    spawnpoints INTEGER,
    objects INTEGER,
    top_object_models TEXT,
    repair_pickups INTEGER,
    nitro_pickups INTEGER,
    vehicle_change_pickups INTEGER,
    start_vehicles TEXT,
    vehicles_via_changes TEXT,
    vehicle_classes TEXT,
    start_zone TEXT,
    finish_zone TEXT,
    notes TEXT
);

-- Where the script found each map's spawnpoints, checkpoints (in race order)
-- and pickups, as the JSON arrays of the sheet's Mapgen tab, for the preview.
CREATE TABLE IF NOT EXISTS map_geometry (
    map_id INTEGER PRIMARY KEY,
    spawnpoints TEXT NOT NULL,
    checkpoints TEXT NOT NULL
);

-- field is a key of mtasaFields(), or NULL for the map in general. parent_id
-- is NULL on the comment that starts a thread, which alone carries resolved_*.
CREATE TABLE IF NOT EXISTS map_comment (
    id INTEGER PRIMARY KEY,
    map_id INTEGER NOT NULL,
    field TEXT,
    parent_id INTEGER,
    account_id INTEGER NOT NULL,
    body TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    edited_at INTEGER,
    deleted_at INTEGER,
    resolved_at INTEGER,
    resolved_by INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_account_name ON account (account_name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_invite_code ON invite (code);

CREATE INDEX IF NOT EXISTS idx_login_attempt_ip ON login_attempt (ip, attempted_at);

CREATE INDEX IF NOT EXISTS idx_map_comment_map ON map_comment (map_id, field);

CREATE INDEX IF NOT EXISTS idx_map_comment_parent ON map_comment (parent_id);
