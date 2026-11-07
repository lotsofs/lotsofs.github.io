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

/// The id inside a pasted Spotify URL, or null when there isn't one.
function spotifyTrackIdIn($value) {
	return preg_match('#/track/([A-Za-z0-9]+)#', $value, $match) ? $match[1] : null;
}

/* A YouTube link's video id and its start in whole seconds (null for none), from
   a pasted URL, a bare id, or the "id&t=656" that song-link stores; null when
   there is no id in it. The start is t= or start=, as 656, 656s or 1h2m3s.
   songs.js has a copy, youtubeLink(), for the card editor. */
function youtubeLinkIn($value) {
	$value = trim($value);
	if (!preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]+)~', $value, $match)
		&& !preg_match('~^([A-Za-z0-9_-]+)(?=[?&]|$)~', $value, $match)) {
		return null;
	}

	$start = null;
	if (preg_match('~[?&#](?:t|start)=(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?(?=[&#]|$)~', $value, $time)) {
		$seconds = (int)($time[1] ?? 0) * 3600 + (int)($time[2] ?? 0) * 60 + (int)($time[3] ?? 0);
		$start = $seconds > 0 ? $seconds : null;
	}

	return ['id' => $match[1], 'start' => $start];
}

/// What song-link stores for a YouTube link: the id, and "&t=" so the watch URL its prefix builds starts there too.
function youtubeLinkValue($link) {
	return $link['id'] . ($link['start'] !== null ? '&t=' . $link['start'] : '');
}
