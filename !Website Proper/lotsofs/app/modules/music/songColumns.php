<?php

/// Which columns of the song list's table the column picker shows.

/// The columns left of the raters, in table order.
/// aliases is Other names, a song's and its versions'; the keys after links are the
/// relationship types' id columns, named in their `columns` (relationships.php).
const MUSIC_SONG_COLUMNS = ['id', 'artist', 'title', 'aliases', 'album', 'year', 'duration', 'links', 'remasteredBy', 'remasterOf', 'inGameAs', 'gameVersionOf', 'alternateTakes', 'alternateTakeOf'];

/// One entry per column every rater has, after the raters in the picker, each hiding
/// that column of every rater at once, in group order.
const MUSIC_SONG_RATER_COLUMNS = ['raterScore', 'raterNote', 'raterUpdated'];

/// The statistics right of the raters, in table order; also the orders sorted in PHP.
const MUSIC_SONG_STAT_COLUMNS = ['average', 'deviation', 'median', 'mode', 'highest', 'lowest', 'sum', 'rated'];

/// Date added and Last updated, right of the statistics.
const MUSIC_SONG_TIME_COLUMNS = ['added', 'updated'];

const MUSIC_SONG_COLUMNS_HIDDEN_BY_DEFAULT = ['aliases', 'raterUpdated', 'added', 'updated'];

/// A key the picker offers, or null for anything else.
function musicSongColumnKey($raw) {
	if (!is_string($raw)) {
		return null;
	}

	foreach ([MUSIC_SONG_COLUMNS, MUSIC_SONG_RATER_COLUMNS, MUSIC_SONG_STAT_COLUMNS, MUSIC_SONG_TIME_COLUMNS] as $keys) {
		if (in_array($raw, $keys, true)) {
			return $raw;
		}
	}

	return preg_match('/^rater_[1-9]\d*$/', $raw) ? $raw : null;
}

/// key => shown, for the columns this account has changed.
function musicSongColumnChoices($db, $accountId) {
	$choices = [];

	foreach ($db->query("SELECT column_key, shown FROM account_song_column WHERE account_id = ?", [$accountId])->fetchAll() as $row) {
		if (musicSongColumnKey($row['column_key']) !== null) {
			$choices[$row['column_key']] = (string)$row['shown'] === '1';
		}
	}

	return $choices;
}

function musicSongColumnShown($choices, $key) {
	return $choices[$key] ?? !in_array($key, MUSIC_SONG_COLUMNS_HIDDEN_BY_DEFAULT, true);
}

/// The class on #songListTable that hides a column: songHideAliases, songHideRater5, songHideRaterUpdated.
function musicSongHideClass($key) {
	if (preg_match('/^rater_(\d+)$/', $key, $match)) {
		return 'songHideRater' . $match[1];
	}

	return 'songHide' . ucfirst($key);
}
