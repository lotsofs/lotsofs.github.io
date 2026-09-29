<?php

require_once __ROOT__ . '/session.php';

const MUSIC_HUE_DEFAULT = 240;

/// An account's default hue: 73 degrees per id, which is prime to 360.
function musicHueFor($accountId) {
	return ((int)$accountId * 73) % 360;
}

/// The hue the page renders in, read from the session.
function musicActiveHue() {
	$stored = modulePreference('hue', 'music');

	if ($stored !== null) {
		return (int)$stored;
	}

	$accountId = currentAccountId();

	return $accountId ? musicHueFor($accountId) : MUSIC_HUE_DEFAULT;
}

/// A stored hue, or the account's default where it never picked one.
function musicHueOf($accountId, $stored) {
	return $stored === null || $stored === '' ? musicHueFor($accountId) : (int)$stored;
}

/// 0-359, or null for anything else.
function musicReadHue($raw) {
	if (!is_string($raw) && !is_int($raw)) {
		return null;
	}

	$value = trim((string)$raw);

	if ($value === '' || !ctype_digit($value)) {
		return null;
	}

	$hue = (int)$value;

	return $hue > 359 ? null : $hue;
}
