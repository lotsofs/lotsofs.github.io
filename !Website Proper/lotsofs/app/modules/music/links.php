<?php

/// Spotify and YouTube are stored as a bare track/video id, which is not a
/// usable href on its own - a relative one resolves against /music/. urlPrefix
/// rebuilds the outward link; a value that already carries a scheme is used
/// untouched. songs.js reads the same prefixes out of songLinkFieldData.
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

/// The song list only offers an album in its filter when that album's artist
/// matches the artist filter, so a link to one album's songs has to carry the
/// artist too or the album half is dropped as out of scope.
function albumSongsHref($albumId, $artistId) {
	return '/music/songs?' . ($artistId === null ? '' : 'artist=' . (int)$artistId . '&') . 'album=' . (int)$albumId;
}

/// The id inside a pasted Spotify or YouTube URL, or null when there isn't
/// one. Two callers with deliberately different fallbacks use these: songLink
/// keeps whatever was pasted when no id is found (it may already be a bare
/// id), while the song list treats "no id" as "don't embed this". Only the
/// patterns are shared - supporting a new URL shape then reaches both, which
/// is the failure the separate copies invited: an id that stored correctly but
/// never rendered as a player.
function spotifyTrackIdIn($value) {
	return preg_match('#/track/([A-Za-z0-9]+)#', $value, $match) ? $match[1] : null;
}

function youtubeVideoIdIn($value) {
	return preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]+)#', $value, $match) ? $match[1] : null;
}
