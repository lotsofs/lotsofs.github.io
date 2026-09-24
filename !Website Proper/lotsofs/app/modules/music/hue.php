<?php

require_once __ROOT__ . '/session.php';

const MUSIC_HUE_DEFAULT = 240;

/// 73 degrees apart per account id: prime to 360, so it walks the whole circle
/// before it repeats, and far enough per step that the first several accounts
/// land on hues nobody would confuse without anyone having to choose one.
function musicHueFor($accountId) {
	return ((int)$accountId * 73) % 360;
}

/// What the page renders in: the hue this account picked, else the one its id
/// gives it, else the site blue for a visitor who is not signed in. Read from
/// the session so every view can ask without a query; login and the colour
/// route are what put it there.
function musicActiveHue() {
	if (isset($_SESSION['hue'])) {
		return (int)$_SESSION['hue'];
	}

	$accountId = currentAccountId();

	return $accountId ? musicHueFor($accountId) : MUSIC_HUE_DEFAULT;
}

/// A stored hue, or the account's default where it never picked one. Takes the
/// raw column so callers reading other accounts' rows (the album card graph)
/// resolve them the same way the session does.
function musicHueOf($accountId, $stored) {
	return $stored === null || $stored === '' ? musicHueFor($accountId) : (int)$stored;
}

/// 0-359 or null: anything else is a request to leave the stored hue alone.
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
