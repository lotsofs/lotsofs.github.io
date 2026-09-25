<?php

/// How the module writes the two values that appear in more than one place.
/// Neither is complicated; both were written out longhand at each site, which
/// is how you end up with one page saying 3:07 and another 3:7, or the audit
/// page and the live rating popups disagreeing about what a song is called.

/// Seconds as M:SS. Null stays empty rather than becoming 0:00, because "no
/// duration recorded" and "zero seconds long" are different things.
function musicDuration($seconds) {
	if ($seconds === null || $seconds === '') {
		return '';
	}

	$seconds = (int)$seconds;

	return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
}

/// How a song is named to a person outside the song list, where the artist
/// isn't already a column: "Artist — Title", or just the title when nothing
/// knows the artist. Used by the audit page and by the rating popups, which
/// have to agree.
function musicSongLabel($artists, $title) {
	$artists = $artists ?? '';
	$title = $title ?? '';

	return $artists === '' ? $title : $artists . ' — ' . $title;
}
