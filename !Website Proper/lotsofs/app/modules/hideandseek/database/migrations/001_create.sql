-- 001_create.sql
--
-- The account system this module starts from: accounts, the invite codes that
-- gate registration after the first one, and the failed-login record the rate
-- limiter reads. Everything the game itself needs is a later migration.

PRAGMA foreign_keys = ON;

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
    revoked_at TEXT,
    FOREIGN KEY (created_by_account_id) REFERENCES account(id),
    FOREIGN KEY (used_by_account_id) REFERENCES account(id)
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_account_name ON account (account_name);

CREATE UNIQUE INDEX IF NOT EXISTS idx_invite_code ON invite (code);

CREATE INDEX IF NOT EXISTS idx_login_attempt_ip ON login_attempt (ip, attempted_at);
