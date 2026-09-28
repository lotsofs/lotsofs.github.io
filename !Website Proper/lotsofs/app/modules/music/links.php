<?php

/// Per platform: the column, its labels, and the prefix that rebuilds an outward link.
function songLinkHref($value, $prefix) {
	if ($value === null || $value === '') {
		return null;
	}

	if (preg_match('#^https?://#i', $value)) {
		return $value;
	}

	return ($prefix === '' ? 'https://' : $prefix) . $value;
}

function songLinkFields() {
	return [
		['key' => 'spotify_url', 'label' => t('song.link.spotify'), 'abbr' => t('song.link.abbr.spotify'), 'urlPrefix' => 'https://open.spotify.com/track/'],
		['key' => 'youtube_url', 'label' => t('song.link.youtube'), 'abbr' => t('song.link.abbr.youtube'), 'urlPrefix' => 'https://www.youtube.com/watch?v='],
		['key' => 'soundcloud_url', 'label' => t('song.link.soundcloud'), 'abbr' => t('song.link.abbr.soundcloud'), 'urlPrefix' => ''],
		['key' => 'bandcamp_url', 'label' => t('song.link.bandcamp'), 'abbr' => t('song.link.abbr.bandcamp'), 'urlPrefix' => ''],
		['key' => 'filepath', 'label' => t('song.link.filepath'), 'abbr' => t('song.link.abbr.filepath'), 'urlPrefix' => ''],
		['key' => 'other_url', 'label' => t('song.link.other'), 'abbr' => t('song.link.abbr.other'), 'urlPrefix' => ''],
	];
}

/// A link to one album's songs, carrying the artist the filter needs.
function albumSongsHref($albumId, $artistId) {
	return '/music/songs?' . ($artistId === null ? '' : 'artist=' . (int)$artistId . '&') . 'album=' . (int)$albumId;
}

/// The id inside a pasted Spotify or YouTube URL, or null when there isn't one.
function spotifyTrackIdIn($value) {
	return preg_match('#/track/([A-Za-z0-9]+)#', $value, $match) ? $match[1] : null;
}

function youtubeVideoIdIn($value) {
	return preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]+)#', $value, $match) ? $match[1] : null;
}
