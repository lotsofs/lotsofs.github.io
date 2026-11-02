<?php

/// Reading changelog.php for the What's New page.

/// The kinds of change a release can list, in the order the page shows them.
const MUSIC_CHANGE_GROUPS = ['action', 'added', 'changed', 'fixed', 'removed'];

function musicChangelog() {
	return require __DIR__ . '/changelog.php';
}

/// One locale's text of an entry; English where that one is missing or blank,
/// as the string catalogue falls back.
function musicChangeText($entry, $locale) {
	$text = $entry[$locale] ?? '';

	return trim($text) === '' ? $entry['en'] : $text;
}

/// An entry as HTML: escaped, with **bold** the only markup.
function musicChangeHtml($text) {
	return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars($text));
}
