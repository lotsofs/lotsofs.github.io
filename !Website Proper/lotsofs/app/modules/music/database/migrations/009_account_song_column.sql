-- Which song list columns a person has ticked or unticked in the column picker,
-- one row per column they have changed. column_key is one of the keys in
-- songColumns.php or rater_<account id>; a column with no row takes its default
-- from there, so a column added later gets its own default even for someone who
-- has already chosen. The picker is the only thing that writes it.
CREATE TABLE account_song_column (
	account_id INTEGER NOT NULL,
	column_key TEXT NOT NULL,
	shown BOOLEAN NOT NULL,
	PRIMARY KEY (account_id, column_key)
);
