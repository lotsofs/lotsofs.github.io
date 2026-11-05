<?php

/// Reading changelog.php for the What's New page.

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

/// A release's entries for everyone, or only those marked 'admin', as HTML in the reader's language.
function musicChangeEntries($release, $forAdmins) {
	$entries = array_filter($release['changes'], fn($entry) => !empty($entry['admin']) === $forAdmins);

	return array_values(array_map(fn($entry) => musicChangeHtml(musicChangeText($entry, activeLocale())), $entries));
}
