<?php

/// How the module writes the values that appear in more than one place.

/// A score with trailing zeroes trimmed.
function scoreText($value) {
	return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
}

/// Seconds as M:SS; null stays empty rather than becoming 0:00.
function musicDuration($seconds) {
	if ($seconds === null || $seconds === '') {
		return '';
	}

	$seconds = (int)$seconds;

	return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
}

/// "Artist — Title", or just the title where no artist is known.
function musicSongLabel($artists, $title) {
	$artists = $artists ?? '';
	$title = $title ?? '';

	return $artists === '' ? $title : $artists . ' — ' . $title;
}
