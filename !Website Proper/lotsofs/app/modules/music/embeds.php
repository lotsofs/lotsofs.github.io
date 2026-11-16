<?php

/// Whether song cards load the Spotify, YouTube and SoundCloud players.

/// The setting the page renders under, read from the session. Off where unset.
function musicEmbeds() {
	return (string)modulePreference('embeds', 'music') === '1';
}

/// A stored column, which PDO hands back as a string, as a boolean.
function musicEmbedsOf($stored) {
	return (string)$stored === '1';
}
