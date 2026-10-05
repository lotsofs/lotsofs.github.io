<?php

/// Whether other people's ratings are covered until this reader has rated a song.

/// The setting the page renders under, read from the session. On where unset.
function musicBlindRating() {
	$stored = modulePreference('blind', 'music');

	return $stored === null ? true : (string)$stored === '1';
}

/// A stored column, which PDO hands back as a string, as a boolean.
function musicBlindRatingOf($stored) {
	return $stored === null || (string)$stored === '1';
}
