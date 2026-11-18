-- 001_create.sql
--
-- The account system this module starts from: accounts, the invite codes that
-- gate registration after the first one, and the failed-login record the rate
-- limiter reads. Plus the Age of Empires checklist: one row per scenario an
-- account has ticked, keyed by the scenario's game, campaign and name in the
-- spreadsheet the page reads (see games.md), each account's saved filters,
-- one row holding them as JSON, and what each account wrote about a scenario:
-- a descriptor, a rating from 0 to 10, a note and a YouTube link, keyed as a
-- tick is. And the Sims 2 checklists: one row per item an account has ticked,
-- keyed by its list and name in sims2.php, and the vehicles the owner writes
-- up for the Vehicle Encounters list, shared by every account: a name and a
-- JSON array of the occasions that make it appear.
--
-- Still unshipped, so still editable - but a database that has already recorded
-- it will not re-run it, so an edit here means deleting games_test.sqlite.
--
-- No REFERENCES and no ON DELETE, by the site rule: the code keeps the ids in
-- step, and a row whose account is gone is left as it is.

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

CREATE TABLE IF NOT EXISTS aoe2_check (
    id INTEGER PRIMARY KEY,
    account_id INTEGER NOT NULL,
    scenario_key TEXT NOT NULL,
    checked_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS aoe2_entry (
    id INTEGER PRIMARY KEY,
    account_id INTEGER NOT NULL,
    scenario_key TEXT NOT NULL,
    descriptor TEXT,
    rating REAL,
    note TEXT,
    youtube TEXT,
    updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS sims2_check (
    id INTEGER PRIMARY KEY,
    account_id INTEGER NOT NULL,
    item_key TEXT NOT NULL,
    checked_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS sims2_vehicle (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    occasions TEXT NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS aoe2_filter (
    id INTEGER PRIMARY KEY,
    account_id INTEGER NOT NULL,
    filters TEXT NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_account_name ON account (account_name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_aoe2_check ON aoe2_check (account_id, scenario_key);

CREATE UNIQUE INDEX IF NOT EXISTS idx_aoe2_entry ON aoe2_entry (account_id, scenario_key);

CREATE UNIQUE INDEX IF NOT EXISTS idx_aoe2_filter ON aoe2_filter (account_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_sims2_check ON sims2_check (account_id, item_key);

CREATE UNIQUE INDEX IF NOT EXISTS idx_sims2_vehicle_name ON sims2_vehicle (name COLLATE NOCASE);

CREATE UNIQUE INDEX IF NOT EXISTS idx_invite_code ON invite (code);

CREATE INDEX IF NOT EXISTS idx_login_attempt_ip ON login_attempt (ip, attempted_at);
