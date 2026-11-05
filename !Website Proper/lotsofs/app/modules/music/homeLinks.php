<?php

/// The links on the music home page, each a view of the song list: a filter and a sort (docs/music.md).

const MUSIC_HOME_LINK_DIRS = ['asc', 'desc'];

function musicHomeLinks($db) {
	return $db->query("SELECT id, position, label, note, artist_id, album_id, sort, dir FROM home_link ORDER BY position, id")->fetchAll();
}

/// Every artist and album by id, named and ordered as the song list's filter has them.
function musicHomeFilterOptions($db) {
	$options = ['artists' => [], 'albums' => []];

	foreach ($db->query("
		SELECT a.id,
			(SELECT name FROM artist_alias WHERE artist_id = a.id ORDER BY is_actual DESC, id LIMIT 1) AS name
		FROM artist a
		ORDER BY name COLLATE NOCASE, a.id
	")->fetchAll() as $row) {
		$options['artists'][(int)$row['id']] = $row['name'] ?? t('artist.list.noName');
	}

	foreach ($db->query("
		SELECT al.id, al.artist_id,
			(SELECT name FROM album_alias WHERE album_id = al.id ORDER BY is_actual DESC, id LIMIT 1) AS name
		FROM album al
		ORDER BY name COLLATE NOCASE, al.id
	")->fetchAll() as $row) {
		$options['albums'][(int)$row['id']] = [
			'name' => $row['name'] ?? t('album.list.noName'),
			'artist_id' => $row['artist_id'] === null ? null : (int)$row['artist_id'],
		];
	}

	return $options;
}

/// The orders the song list takes, key => name, in its column order.
function musicHomeSortOptions($db) {
	$sorts = [
		'id' => t('song.column.id'),
		'artist' => t('song.column.artist'),
		'title' => t('song.column.title'),
		'album' => t('song.column.album'),
		'year' => t('song.column.year'),
		'duration' => t('song.column.duration'),
	];

	foreach ($db->query("SELECT id, account_name FROM account ORDER BY id")->fetchAll() as $rater) {
		$id = (int)$rater['id'];
		$sorts["score_{$id}"] = t('song.list.raterScoreLabel', ['name' => $rater['account_name']]);
		$sorts["note_{$id}"] = t('song.list.raterNoteLabel', ['name' => $rater['account_name']]);
	}

	return $sorts + [
		'average' => t('album.card.sortAverage'),
		'deviation' => t('album.card.sortDeviation'),
		'median' => t('album.card.sortMedian'),
		'mode' => t('album.card.sortMode'),
		'highest' => t('album.card.sortHighest'),
		'lowest' => t('album.card.sortLowest'),
		'sum' => t('album.card.sortSum'),
		'rated' => t('album.card.sortRated'),
	];
}

/// The picker's value for a link's filter: '', artist:<id> or album:<id>.
function musicHomeFilterValue($link) {
	if ($link['album_id'] !== null) {
		return 'album:' . (int)$link['album_id'];
	}

	return $link['artist_id'] !== null ? 'artist:' . (int)$link['artist_id'] : '';
}

/// [artist_id, album_id] for a picker value, or null for one naming nothing that exists.
function musicHomeFilterFrom($options, $raw) {
	if ($raw === '') {
		return [null, null];
	}

	if (!is_string($raw) || !preg_match('/^(artist|album):([1-9]\d*)$/', $raw, $match)) {
		return null;
	}

	$id = (int)$match[2];

	if ($match[1] === 'artist') {
		return isset($options['artists'][$id]) ? [$id, null] : null;
	}

	return isset($options['albums'][$id]) ? [null, $id] : null;
}

/// The artist the song list wants beside a link's filter: an album's own, as it is now.
function musicHomeFilterArtist($link, $options) {
	if ($link['album_id'] !== null) {
		return $options['albums'][(int)$link['album_id']]['artist_id'] ?? null;
	}

	return $link['artist_id'] === null ? null : (int)$link['artist_id'];
}

/// The heading the song list shows for a filter, as routes/songs.php words it.
function musicHomeFilterHeading($options, $artistId, $albumId) {
	if ($albumId !== null && isset($options['albums'][$albumId])) {
		$album = $options['albums'][$albumId];
		$artist = $album['artist_id'] === null ? null : ($options['artists'][$album['artist_id']] ?? null);

		return $artist === null
			? $album['name']
			: t('song.list.headingAlbumBy', ['album' => $album['name'], 'artist' => $artist]);
	}

	if ($artistId !== null && isset($options['artists'][$artistId])) {
		return $options['artists'][$artistId];
	}

	return t('song.list.heading');
}

/// The song list's address for a link, in songQuery()'s parameter order.
function musicHomeLinkHref($link, $options) {
	$params = ['sort' => $link['sort'], 'dir' => $link['dir']];

	$artistId = musicHomeFilterArtist($link, $options);
	if ($artistId !== null) {
		$params['artist'] = $artistId;
	}
	if ($link['album_id'] !== null) {
		$params['album'] = (int)$link['album_id'];
	}

	return '/music/songs?' . http_build_query($params);
}

/// What a link reads: its label, else the song list's heading and any order other than the default.
function musicHomeLinkText($link, $options, $sorts) {
	if ($link['label'] !== null) {
		return $link['label'];
	}

	$heading = musicHomeFilterHeading($options, musicHomeFilterArtist($link, $options), $link['album_id'] === null ? null : (int)$link['album_id']);

	/// An order the song list no longer has falls back to its default there too.
	$sort = isset($sorts[$link['sort']]) ? $link['sort'] : 'id';

	if ($sort === 'id' && $link['dir'] === 'asc') {
		return $heading;
	}

	return t('home.link.sorted', [
		'heading' => $heading,
		'column' => $sorts[$sort],
		'arrow' => $link['dir'] === 'desc' ? '▼' : '▲',
	]);
}

/// Per song: its artists, its albums, how many accounts scored it and whether $accountId did.
/// A borrowed score counts, as on the song list; needs musicRatingView($db).
function musicHomeSongRatings($db, $accountId) {
	$songs = [];
	foreach ($db->query("SELECT id FROM song")->fetchAll() as $row) {
		$songs[(int)$row['id']] = ['artists' => [], 'albums' => [], 'rated' => 0, 'mine' => false];
	}

	foreach ($db->query("SELECT song_id, artist_id FROM song_artist")->fetchAll() as $row) {
		if (isset($songs[(int)$row['song_id']])) {
			$songs[(int)$row['song_id']]['artists'][] = (int)$row['artist_id'];
		}
	}

	foreach ($db->query("SELECT song_id, album_id FROM album_track")->fetchAll() as $row) {
		if (isset($songs[(int)$row['song_id']])) {
			$songs[(int)$row['song_id']]['albums'][] = (int)$row['album_id'];
		}
	}

	foreach ($db->query("
		SELECT er.song_id, er.account_id
		FROM effective_rating er
		JOIN account ac ON ac.id = er.account_id
		WHERE er.score IS NOT NULL
	")->fetchAll() as $row) {
		$songId = (int)$row['song_id'];
		if (isset($songs[$songId])) {
			$songs[$songId]['rated']++;
			$songs[$songId]['mine'] = $songs[$songId]['mine'] || (int)$row['account_id'] === (int)$accountId;
		}
	}

	return $songs;
}

/// How much of what a link shows is scored: its songs, this reader's, and everyone's out of songs × accounts.
/// A filter naming nothing that exists shows every song, as the song list does.
function musicHomeLinkCounts($link, $options, $songs, $accountCount) {
	$albumId = $link['album_id'] !== null && isset($options['albums'][(int)$link['album_id']]) ? (int)$link['album_id'] : null;
	$artistId = $link['artist_id'] !== null && isset($options['artists'][(int)$link['artist_id']]) ? (int)$link['artist_id'] : null;

	$counts = ['total' => 0, 'mine' => 0, 'rated' => 0];
	foreach ($songs as $song) {
		$shown = $albumId !== null
			? in_array($albumId, $song['albums'], true)
			: $artistId === null || in_array($artistId, $song['artists'], true);

		if ($shown) {
			$counts['total']++;
			$counts['mine'] += $song['mine'] ? 1 : 0;
			$counts['rated'] += $song['rated'];
		}
	}

	return $counts + ['possible' => $counts['total'] * $accountCount];
}

/// A whole percentage that reads 0 or 100 only when it is exactly that.
function musicHomePercent($part, $whole) {
	$percent = (int)round($part / $whole * 100);

	if ($part > 0 && $percent === 0) {
		return 1;
	}

	return $part < $whole && $percent === 100 ? 99 : $percent;
}

/// The line under a link saying how much of it you and everyone have scored, or null when it shows no songs.
function musicHomeLinkStats($counts) {
	if ($counts['total'] === 0) {
		return null;
	}

	$mine = ['rated' => $counts['mine'], 'total' => $counts['total'], 'percent' => musicHomePercent($counts['mine'], $counts['total'])];

	return ($counts['total'] === 1 ? t('home.stats.mineOne', $mine) : t('home.stats.mine', $mine)) . ' ' . t('home.stats.everyone', [
		'rated' => $counts['rated'],
		'possible' => $counts['possible'],
		'percent' => musicHomePercent($counts['rated'], $counts['possible']),
	]);
}
