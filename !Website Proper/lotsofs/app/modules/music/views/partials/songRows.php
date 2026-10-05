<?php

require_once __MODULES__ . '/music/links.php';
require_once __MODULES__ . '/music/format.php';
require_once __MODULES__ . '/music/stats.php';

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
$artistsBySong = $globalData['artistsBySong'] ?? [];
$filterArtist = $globalData['filterArtist'] ?? null;
$filterAlbum = $globalData['filterAlbum'] ?? null;

$raterLabels = [];
foreach ($songRaters as $rater) {
	$raterLabels[(int)$rater['id']] = [
		'score' => htmlspecialchars(t('song.list.raterScoreLabel', ['name' => $rater['account_name']])),
		'note' => htmlspecialchars(t('song.list.raterNoteLabel', ['name' => $rater['account_name']])),
		'name' => htmlspecialchars($rater['account_name']),
		'placeholder' => ($rater['isMine'] ?? false) ? $tapToEnterAttr : '',
		/* The rater's class list with the colour ramp taken out, derived rather
		   than written out a second time: a covered score must not be painted,
		   since hsl(score * 12) is the number back again. Taken out rather than
		   added to, because routes/songs.php hands the same string to the
		   header cell and a test pins what ends that attribute. */
		'scoreGatedClass' => implode(' ', array_diff(preg_split('/\s+/', $rater['scoreClass'] ?? ''), ['songScoreColoured'])),
	];
}

$emptyLinks = array_fill_keys(array_column($songLinkFields, 'key'), null);

$myRaterId = null;
foreach ($songRaters as $rater) {
	if ($rater['isMine'] ?? false) {
		$myRaterId = (int)$rater['id'];
		break;
	}
}

$blindRating = $globalData['blindRating'] ?? false;
$songStatColumns = $globalData['statColumns'] ?? [];

/* Three pills, built once rather than per cell. The long label where there is
   room - the card, and the table's 12em note columns - and the short one in the
   table's 2em score and 2.6em statistics columns, which take the width of
   whatever is in them. The hint names the half that is missing. */
$gatePill = function ($label, $hint) {
	return '<button type="button" class="songGateReveal" title="' . htmlspecialchars($hint) . '">'
		. htmlspecialchars($label) . '</button>';
};

$notePill = $gatePill(t('song.list.gateReveal'), t('song.list.gateNoteHint'));
$scorePillWide = $gatePill(t('song.list.gateReveal'), t('song.list.gateScoreHint'));
$scorePillNarrow = $gatePill(t('song.list.gateRevealShort'), t('song.list.gateScoreHint'));

// One pass deriving every value, class and attribute the table and cards need.
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

	$song['artistHtml'] = $song['artistValue'];
	if (isset($artistsBySong[(int)$song['id']])) {
		$artistLinks = [];
		foreach ($artistsBySong[(int)$song['id']] as $artistLink) {
			$artistLinks[] = '<a class="songArtistLink" href="' . htmlspecialchars('/music/songs?artist=' . (int)$artistLink['id'])
				. '" data-artist-card-id="' . (int)$artistLink['id'] . '">'
				. htmlspecialchars(($artistLink['name'] ?? '') === '' ? t('artist.list.noName') : $artistLink['name'])
				. '</a>';
		}
		$song['artistHtml'] = implode(', ', $artistLinks);
	}

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

	/// A stored value is normally a bare id, but older rows may hold a whole URL.
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

	/* Nobody else's note is shown until this reader has written one of their own
	   for the song, and nobody else's score until they have scored it. The two
	   are independent: a score does not uncover notes, nor a note scores. No
	   rater is "mine" on a page with no viewer, which covers nothing. */
	$notesGated = $blindRating && $myRaterId !== null && ($song['note_' . $myRaterId] ?? '') === '';
	$scoresGated = $blindRating && $myRaterId !== null && ($song['score_' . $myRaterId] ?? null) === null;

	$song['statsGated'] = $scoresGated;

	$song['ratings'] = [];
	$songScores = [];
	foreach ($songRaters as $rater) {
		$raterId = (int)$rater['id'];

		$score = $song['score_' . $raterId] ?? null;
		$score = $score === null ? '' : (string)(float)$score;

		if ($score !== '') {
			$songScores[] = (float)$score;
		}

		$labels = $raterLabels[$raterId];
		$note = $song['note_' . $raterId] ?? '';
		$noteGated = $notesGated && $raterId !== $myRaterId && $note !== '';
		$scoreGated = $scoresGated && $raterId !== $myRaterId && $score !== '';

		$song['ratings'][$raterId] = [
			'scoreValue' => htmlspecialchars($score),
			'scoreEmptyClass' => $score === '' ? ' songCellEmpty' : '',
			'scoreCellClass' => $scoreGated ? $labels['scoreGatedClass'] : ($rater['scoreClass'] ?? ''),
			'scoreGatedClass' => $scoreGated ? ' songGated' : '',
			'scoreGateHtml' => $scoreGated ? $scorePillNarrow : '',
			'scoreCardGateHtml' => $scoreGated ? $scorePillWide : '',
			'noteValue' => htmlspecialchars($note),
			'noteEmptyClass' => $note === '' ? ' songCellEmpty' : '',
			'noteGatedClass' => $noteGated ? ' songGated' : '',
			'noteGateHtml' => $noteGated ? $notePill : '',
			/// Withheld with the text: the tooltip would hand it straight back.
			'noteTitleAttr' => $noteGated ? '' : ' title="' . htmlspecialchars($note) . '"',
		];
	}

	/// The song's statistics, from the scores just read off the row.
	$song['stats'] = musicSongStatFields($songScores);

	/* The statistics are over everybody's scores, so a score covers them. The
	   rated count is not covered: how many people have an opinion gives away
	   nothing about what it is, and it reads '0' rather than blank, so the
	   emptiness test alone would not have spared it. */
	$song['statCells'] = [];
	foreach ($songStatColumns as $stat) {
		$key = $stat['key'];
		$statValue = $song['stats'][$key];
		$statGated = $scoresGated && $key !== 'rated' && $statValue !== '';

		$song['statCells'][$key] = [
			'class' => 'songStatCell songStat' . ucfirst($key) . 'Cell'
				. (($stat['score'] ?? false) && !$statGated ? ' songScoreColoured' : '')
				. ($statValue === '' ? ' songCellEmpty' : '')
				. ($statGated ? ' songGated' : ''),
			'sortAttr' => $key === 'mode' ? ' data-sort-value="' . htmlspecialchars($song['stats']['modeSort']) . '"' : '',
			'gateHtml' => $statGated ? $scorePillNarrow : '',
			'value' => htmlspecialchars($statValue),
		];
	}

	$songRows[] = $song;
}
