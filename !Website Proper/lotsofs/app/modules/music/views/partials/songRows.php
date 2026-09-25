<?php

require_once __MODULES__ . '/music/links.php';
require_once __MODULES__ . '/music/format.php';

$artistLabel = htmlspecialchars(t('song.column.artist'));
$albumLabel = htmlspecialchars(t('song.column.album'));
$yearLabel = htmlspecialchars(t('song.column.year'));
$durationLabel = htmlspecialchars(t('song.column.duration'));
$linksLabel = htmlspecialchars(t('song.column.links'));
$resultLabel = htmlspecialchars(t('song.column.result'));
$tapToEnterAttr = ' data-placeholder="' . htmlspecialchars(t('song.list.tapToEnter')) . '"';

$songRaters = $globalData['raters'] ?? [];
$songLinkFields = $globalData['linkFields'] ?? songLinkFields();
$songLinksBySong = $globalData['songLinksBySong'] ?? [];
$listedAsBySong = $globalData['listedAsBySong'] ?? [];
$albumsBySong = $globalData['albumsBySong'] ?? [];
$filterArtist = $globalData['filterArtist'] ?? null;
$filterAlbum = $globalData['filterAlbum'] ?? null;

$raterLabels = [];
foreach ($songRaters as $rater) {
	$raterLabels[(int)$rater['id']] = [
		'score' => htmlspecialchars(t('song.list.raterScoreLabel', ['name' => $rater['account_name']])),
		'note' => htmlspecialchars(t('song.list.raterNoteLabel', ['name' => $rater['account_name']])),
		'name' => htmlspecialchars($rater['account_name']),
		'placeholder' => ($rater['isMine'] ?? false) ? $tapToEnterAttr : '',
	];
}

$emptyLinks = array_fill_keys(array_column($songLinkFields, 'key'), null);

// One pass to derive every value/class/attribute either tree needs, so the
// table and card templates just read $song['x'] rather than recomputing it.
$songRows = [];
$visibleCount = 0;
foreach ($globalData['songs'] as $song) {
	$albumIds = ($song['album_ids'] ?? null) === null ? [] : explode(',', $song['album_ids']);
	$artistIds = ($song['artist_ids'] ?? null) === null ? [] : explode(',', $song['artist_ids']);
	if ($filterAlbum !== null) {
		$song['hidden'] = !in_array((string)$filterAlbum, $albumIds, true);
	}
	else {
		$song['hidden'] = $filterArtist !== null && !in_array((string)$filterArtist, $artistIds, true);
	}
	if (!$song['hidden']) {
		$visibleCount++;
	}

	$song['hiddenAttr'] = $song['hidden'] ? ' hidden' : '';
	$song['albumIdsAttr'] = htmlspecialchars($song['album_ids'] ?? '');
	$song['artistValue'] = htmlspecialchars($song['artist'] ?? '');

	$canonicalTitle = $song['title'] ?? '';
	$listedAs = $listedAsBySong[(int)$song['id']] ?? null;
	$allNames = $song['all_names'] ?? '';
	$song['titleAliasedClass'] = $listedAs === null ? '' : ' songTitleAliased';
	$song['canonicalTitleAttr'] = htmlspecialchars($canonicalTitle);
	$titleTooltip = $allNames !== '' ? $allNames : $canonicalTitle;
	$song['titleTooltipAttr'] = $titleTooltip === '' ? '' : ' title="' . htmlspecialchars($titleTooltip) . '"';
	$song['titleValue'] = htmlspecialchars($listedAs ?? $canonicalTitle);

	$song['albumsValue'] = htmlspecialchars($song['albums'] ?? '');
	$song['artistIdsAttr'] = htmlspecialchars($song['artist_ids'] ?? '');

	$song['albumsHtml'] = $song['albumsValue'];
	if (isset($albumsBySong[(int)$song['id']])) {
		$albumLinks = [];
		foreach ($albumsBySong[(int)$song['id']] as $albumLink) {
			$albumLinks[] = '<a class="songAlbumLink" href="' . htmlspecialchars(albumSongsHref($albumLink['id'], $albumLink['artist_id']))
				. '" data-album-card-id="' . (int)$albumLink['id'] . '">'
				. htmlspecialchars(($albumLink['name'] ?? '') === '' ? t('album.list.noName') : $albumLink['name'])
				. '</a>';
		}
		$song['albumsHtml'] = implode(', ', $albumLinks);
	}

	$song['songYearAttr'] = htmlspecialchars(($song['song_year'] ?? null) !== null ? (string)(int)$song['song_year'] : '');
	$song['fallbackYearAttr'] = htmlspecialchars(($song['fallback_year'] ?? null) !== null ? (string)(int)$song['fallback_year'] : '');
	$displayYear = $song['song_year'] ?? $song['fallback_year'] ?? null;
	$song['displayYearValue'] = htmlspecialchars($displayYear !== null ? (string)(int)$displayYear : '');

	$duration = ($song['duration'] ?? null) !== null ? (int)$song['duration'] : null;
	$song['durationAttr'] = htmlspecialchars($duration !== null ? (string)$duration : '');
	$song['durationValue'] = musicDuration($duration);

	$songLinks = $songLinksBySong[$song['id']] ?? $emptyLinks;

	/// A stored value is normally a bare id, but rows written before that
	/// rule may still hold a whole URL - hence both branches. No id at all
	/// means no player, just a link.
	$song['spotifyTrackId'] = null;
	if (!empty($songLinks['spotify_url'])) {
		$song['spotifyTrackId'] = spotifyTrackIdIn($songLinks['spotify_url']);
		if ($song['spotifyTrackId'] === null && preg_match('#^[A-Za-z0-9]+$#', trim($songLinks['spotify_url']))) {
			$song['spotifyTrackId'] = trim($songLinks['spotify_url']);
		}
	}

	$song['youtubeTrackId'] = null;
	if (!empty($songLinks['youtube_url'])) {
		$song['youtubeTrackId'] = youtubeVideoIdIn($songLinks['youtube_url']);
		if ($song['youtubeTrackId'] === null && preg_match('#^[A-Za-z0-9_-]+$#', trim($songLinks['youtube_url']))) {
			$song['youtubeTrackId'] = trim($songLinks['youtube_url']);
		}
	}

	$song['soundcloudEmbedUrl'] = null;
	if (!empty($songLinks['soundcloud_url'])) {
		$song['soundcloudEmbedUrl'] = 'https://w.soundcloud.com/player/?url=' . urlencode($songLinks['soundcloud_url'])
			. '&color=%23ff5500&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true';
	}

	$song['linksAttr'] = htmlspecialchars(json_encode($songLinks));

	$song['linkChips'] = [];
	foreach ($songLinkFields as $field) {
		$linkValue = $songLinks[$field['key']] ?? null;
		if ($linkValue === null || $linkValue === '') {
			continue;
		}
		if ($field['key'] === 'spotify_url' && $song['spotifyTrackId'] !== null) {
			continue;
		}
		if ($field['key'] === 'youtube_url' && $song['youtubeTrackId'] !== null) {
			continue;
		}
		if ($field['key'] === 'soundcloud_url' && $song['soundcloudEmbedUrl'] !== null) {
			continue;
		}
		$song['linkChips'][] = [
			'label' => htmlspecialchars($field['label']),
			'url' => htmlspecialchars($linkValue),
			'href' => htmlspecialchars(songLinkHref($linkValue, $field['urlPrefix'])),
			'isPath' => $field['key'] === 'filepath',
			'isOther' => $field['key'] === 'other_url',
		];
	}

	$song['linkAbbrs'] = [];
	foreach ($songLinkFields as $field) {
		$linkValue = $songLinks[$field['key']] ?? null;
		$song['linkAbbrs'][] = [
			'abbr' => htmlspecialchars($field['abbr']),
			'label' => htmlspecialchars($field['label']),
			'url' => $linkValue !== null && $linkValue !== '' ? htmlspecialchars($linkValue) : null,
			'href' => htmlspecialchars(songLinkHref($linkValue, $field['urlPrefix'])),
			'isPath' => $field['key'] === 'filepath',
		];
	}

	$song['ratings'] = [];
	foreach ($songRaters as $rater) {
		$raterId = (int)$rater['id'];

		$score = $song['score_' . $raterId] ?? null;
		$score = $score === null ? '' : (string)(float)$score;

		$note = $song['note_' . $raterId] ?? '';

		$song['ratings'][$raterId] = [
			'scoreValue' => htmlspecialchars($score),
			'scoreEmptyClass' => $score === '' ? ' songCellEmpty' : '',
			'noteValue' => htmlspecialchars($note),
			'noteEmptyClass' => $note === '' ? ' songCellEmpty' : '',
		];
	}

	$songRows[] = $song;
}
