<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

loadStringCatalogue('music');

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

$accountId = (int)currentAccountId();

$raters = $db->query("
	SELECT a.id, a.account_name
	FROM account a
	ORDER BY a.id = ? DESC, a.id
", [$accountId])->fetchAll();

$sortable = [
	'id' => 's.id',
	'artist' => 'artist COLLATE NOCASE',
	'title' => 'title COLLATE NOCASE',
	'album' => 'albums COLLATE NOCASE',
	'year' => 'COALESCE(song_year, fallback_year)',
	'duration' => 's.duration',
];


$aggregates = '';
$selects = '';

foreach ($raters as $rater) {
	$id = (int)$rater['id'];

	$aggregates .= ", MAX(CASE WHEN account_id = {$id} THEN score END) AS score_{$id}, MAX(CASE WHEN account_id = {$id} THEN subjective_note END) AS note_{$id}";
	$selects .= ", r.score_{$id} AS score_{$id}, r.note_{$id} AS note_{$id}";

	$sortable["score_{$id}"] = "score_{$id}";
	$sortable["note_{$id}"] = "note_{$id} COLLATE NOCASE";
}

$joins = " LEFT JOIN (SELECT song_id{$aggregates} FROM account_song GROUP BY song_id) r ON r.song_id = s.id";

$requested = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : '';
$sort = isset($sortable[$requested]) ? $requested : 'id';
$dir = ($_GET['dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';

$globalData['sort'] = $sort;
$globalData['dir'] = strtolower($dir);

$rawArtist = filter_input(INPUT_GET, 'artist', FILTER_VALIDATE_INT);
$rawAlbum = filter_input(INPUT_GET, 'album', FILTER_VALIDATE_INT);

$artistOptions = $db->query("
	SELECT a.id,
		(SELECT name FROM artist_alias WHERE artist_id = a.id ORDER BY is_actual DESC, id LIMIT 1) AS name
	FROM artist a
	ORDER BY name COLLATE NOCASE, a.id
")->fetchAll();

$filterArtist = null;
foreach ($artistOptions as $option) {
	if ((int)$option['id'] === $rawArtist) {
		$filterArtist = $rawArtist;
	}
}

$albumOptions = $db->query("
	SELECT al.id, al.artist_id,
		(SELECT name FROM album_alias WHERE album_id = al.id ORDER BY is_actual DESC, id LIMIT 1) AS name
	FROM album al
	ORDER BY name COLLATE NOCASE, al.id
")->fetchAll();

$filterAlbum = null;
foreach ($albumOptions as $option) {
	$inScope = $filterArtist !== null
		? (int)$option['artist_id'] === $filterArtist
		: $option['artist_id'] === null;
	if ((int)$option['id'] === $rawAlbum && $inScope) {
		$filterAlbum = $rawAlbum;
	}
}

$globalData['artistOptions'] = $artistOptions;
$globalData['albumOptions'] = $albumOptions;
$globalData['filterArtist'] = $filterArtist;
$globalData['filterAlbum'] = $filterAlbum;

$listHeading = t('song.list.heading');

if ($filterAlbum !== null) {
	foreach ($albumOptions as $option) {
		if ((int)$option['id'] !== $filterAlbum) {
			continue;
		}

		$albumArtist = null;
		foreach ($artistOptions as $artist) {
			if ((int)$artist['id'] === (int)$option['artist_id']) {
				$albumArtist = $artist['name'] ?? t('artist.list.noName');
			}
		}

		$albumName = $option['name'] ?? t('album.list.noName');
		$listHeading = $albumArtist === null
			? $albumName
			: t('song.list.headingAlbumBy', ['album' => $albumName, 'artist' => $albumArtist]);
	}
}
else if ($filterArtist !== null) {
	foreach ($artistOptions as $option) {
		if ((int)$option['id'] === $filterArtist) {
			$listHeading = $option['name'] ?? t('artist.list.noName');
		}
	}
}

$globalData['listHeading'] = $listHeading;
$pageTitle = $listHeading;

$globalData['ratingCursor'] = (int)$db->query("SELECT COALESCE(MAX(updated_at), 0) c FROM account_song")->fetch()['c'];
$globalData['auditCursor'] = (int)$db->query("SELECT COALESCE(MAX(id), 0) c FROM rating_audit")->fetch()['c'];

$globalData['songs'] = $db->query("
	SELECT
		s.id,
		(SELECT artist_id FROM song_artist WHERE song_id = s.id ORDER BY id LIMIT 1) AS artist_id,
		(SELECT group_concat(artist_id) FROM (
			SELECT artist_id FROM song_artist WHERE song_id = s.id ORDER BY id
		)) AS artist_ids,
		(SELECT group_concat(album_id) FROM album_track WHERE song_id = s.id) AS album_ids,
		(SELECT group_concat(album_name, ', ') FROM (
			SELECT (SELECT name FROM album_alias
				WHERE album_id = at.album_id
				ORDER BY is_actual DESC, id LIMIT 1) AS album_name
			FROM album_track at
			WHERE at.song_id = s.id
			ORDER BY album_name COLLATE NOCASE
		)) AS albums,
		st.name AS title,
		(SELECT group_concat(name, ', ') FROM (
			SELECT name FROM song_alias
			WHERE song_id = s.id
			ORDER BY is_actual DESC, name COLLATE NOCASE
		)) AS all_names,
		(SELECT group_concat(artist_name, ', ') FROM (
			SELECT (SELECT name FROM artist_alias
				WHERE artist_id = sa.artist_id
				ORDER BY is_actual DESC, id LIMIT 1) AS artist_name
			FROM song_artist sa
			WHERE sa.song_id = s.id
			ORDER BY sa.id
		)) AS artist,
		s.year AS song_year,
		s.duration,
		(SELECT MIN(al.release_year) FROM album_track at
			JOIN album al ON al.id = at.album_id
			WHERE at.song_id = s.id AND al.release_year IS NOT NULL) AS fallback_year
		{$selects}
	FROM song s
	LEFT JOIN song_alias st ON st.song_id = s.id AND st.is_actual = 1
	{$joins}
	ORDER BY {$sortable[$sort]} {$dir}, s.id
")->fetchAll();

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

$linkFields = [
	['key' => 'spotify_url', 'label' => t('song.link.spotify'), 'abbr' => t('song.link.abbr.spotify'), 'urlPrefix' => 'https://open.spotify.com/track/'],
	['key' => 'youtube_url', 'label' => t('song.link.youtube'), 'abbr' => t('song.link.abbr.youtube'), 'urlPrefix' => 'https://www.youtube.com/watch?v='],
	['key' => 'soundcloud_url', 'label' => t('song.link.soundcloud'), 'abbr' => t('song.link.abbr.soundcloud'), 'urlPrefix' => ''],
	['key' => 'bandcamp_url', 'label' => t('song.link.bandcamp'), 'abbr' => t('song.link.abbr.bandcamp'), 'urlPrefix' => ''],
	['key' => 'filepath', 'label' => t('song.link.filepath'), 'abbr' => t('song.link.abbr.filepath'), 'urlPrefix' => ''],
	['key' => 'other_url', 'label' => t('song.link.other'), 'abbr' => t('song.link.abbr.other'), 'urlPrefix' => ''],
];
$globalData['linkFields'] = $linkFields;

$songLinksBySong = [];
foreach ($db->query("SELECT song_id, spotify_url, youtube_url, soundcloud_url, bandcamp_url, filepath, other_url FROM song_link")->fetchAll() as $row) {
	$songLinksBySong[(int)$row['song_id']] = [
		'spotify_url' => $row['spotify_url'],
		'youtube_url' => $row['youtube_url'],
		'soundcloud_url' => $row['soundcloud_url'],
		'bandcamp_url' => $row['bandcamp_url'],
		'filepath' => $row['filepath'],
		'other_url' => $row['other_url'],
	];
}

$globalData['songLinksBySong'] = $songLinksBySong;

$trackAliases = [];
foreach ($db->query("
	SELECT at.album_id, at.song_id, (SELECT name FROM song_alias WHERE id = at.song_alias_id) AS name
	FROM album_track at
	WHERE at.song_alias_id IS NOT NULL
")->fetchAll() as $track) {
	$trackAliases[] = [
		'album_id' => (int)$track['album_id'],
		'song_id' => (int)$track['song_id'],
		'name' => $track['name'],
	];
}

$listedAsBySong = [];
foreach ($trackAliases as $track) {
	if ($filterAlbum !== null && $track['album_id'] === $filterAlbum) {
		$listedAsBySong[$track['song_id']] = $track['name'];
	}
}

$globalData['trackAliases'] = $trackAliases;
$globalData['listedAsBySong'] = $listedAsBySong;

$columns = [
	['key' => 'id', 'type' => 'number', 'class' => 'songIdCell', 'label' => t('song.column.id')],
	['key' => 'artist', 'type' => 'text', 'class' => 'songArtistCell', 'label' => t('song.column.artist')],
	['key' => 'title', 'type' => 'text', 'class' => 'songTitleCell', 'label' => t('song.column.title')],
	['key' => 'album', 'type' => 'text', 'class' => 'songAlbumCell', 'label' => t('song.column.album')],
	['key' => 'year', 'type' => 'number', 'class' => 'songYearCell', 'label' => t('song.column.year')],
	['key' => 'duration', 'type' => 'duration', 'class' => 'songDurationCell', 'label' => t('song.column.duration')],
];

foreach ($raters as $index => $rater) {
	$id = (int)$rater['id'];
	$isMine = $id === $accountId;

	$raters[$index]['isMine'] = $isMine;
	$raters[$index]['scoreClass'] = 'songRatingCell songRatingScoreCell' . ($isMine ? ' songMineCell songMyScoreCell' : '');
	$raters[$index]['noteClass'] = 'songRatingCell songRatingNoteCell' . ($isMine ? ' songMineCell songMyNoteCell' : '');

	$columns[] = [
		'key' => "score_{$id}",
		'type' => 'number',
		'class' => $raters[$index]['scoreClass'],
		'label' => t('song.column.ratingScore'),
		'name' => t('song.list.raterScoreLabel', ['name' => $rater['account_name']]),
		'group' => "rater_{$id}",
		'groupStart' => true,
		'groupLabel' => $rater['account_name'],
		'groupClass' => 'songRaterGroup' . ($isMine ? ' songMineCell songMineGroup' : ''),
	];
	$columns[] = [
		'key' => "note_{$id}",
		'type' => 'text',
		'class' => $raters[$index]['noteClass'],
		'label' => t('song.column.ratingNote'),
		'name' => t('song.list.raterNoteLabel', ['name' => $rater['account_name']]),
		'group' => "rater_{$id}",
	];
}

$globalData['raters'] = $raters;
$globalData['accountId'] = $accountId;

$filterQuery = ($filterArtist !== null ? '&artist=' . $filterArtist : '')
	. ($filterAlbum !== null ? '&album=' . $filterAlbum : '');

$globalData['columns'] = [];
foreach ($columns as $index => $column) {

	$isActive = $column['key'] === $sort;
	$nextDir = $isActive && $globalData['dir'] === 'asc' ? 'desc' : 'asc';

	$column['link'] = '?sort=' . $column['key'] . '&dir=' . $nextDir . $filterQuery;
	$column['indicator'] = $isActive ? ($globalData['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
	$column['title'] = t('song.list.columnSortHint', ['column' => $column['name'] ?? $column['label']]);

	$globalData['columns'][] = $column;
}

require __MODULES__ . "/music/views/songs.view.php";
