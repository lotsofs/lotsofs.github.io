<?php

/// How long a toast on the song list stays, in seconds.

const MUSIC_TOAST_DEFAULT = 180;
const MUSIC_TOAST_OFF = 0;
const MUSIC_TOAST_UNTIL_CLOSED = -1;

/// Every value the cog menu offers, in the order it lists them.
const MUSIC_TOAST_CHOICES = [0, 10, 30, 60, 180, 300, 600, 1800, 3600, -1];

/// One of the choices, or null for anything else.
function musicReadToastSeconds($raw) {
	if (!is_string($raw) && !is_int($raw)) {
		return null;
	}

	$value = trim((string)$raw);

	if (!preg_match('/^-?\d+$/', $value)) {
		return null;
	}

	return in_array((int)$value, MUSIC_TOAST_CHOICES, true) ? (int)$value : null;
}

/// A stored column, which PDO hands back as a string, as a choice.
function musicToastSecondsOf($stored) {
	return musicReadToastSeconds($stored) ?? MUSIC_TOAST_DEFAULT;
}

/// The setting the page renders under, read from the session.
function musicToastSeconds() {
	return musicToastSecondsOf(modulePreference('toast', 'music'));
}

/// Seconds => what the cog menu calls it.
function musicToastChoiceLabels() {
	$labels = [];

	foreach (MUSIC_TOAST_CHOICES as $seconds) {
		if ($seconds === MUSIC_TOAST_OFF) {
			$labels[$seconds] = t('settings.toastOff');
		}
		else if ($seconds === MUSIC_TOAST_UNTIL_CLOSED) {
			$labels[$seconds] = t('settings.toastUntilClosed');
		}
		else if ($seconds < 60) {
			$labels[$seconds] = t('settings.toastSeconds', ['count' => $seconds]);
		}
		else if ($seconds === 60) {
			$labels[$seconds] = t('settings.toastMinute');
		}
		else {
			$labels[$seconds] = t('settings.toastMinutes', ['count' => intdiv($seconds, 60)]);
		}
	}

	return $labels;
}
