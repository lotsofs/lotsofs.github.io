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

/// Per column: `value` is tested for emptiness, `order` is what rows sort by.
$sortable = [
	'id' => ['value' => 's.id', 'order' => 's.id'],
	'artist' => ['value' => 'artist', 'order' => 'artist COLLATE NOCASE'],
	'title' => ['value' => 'title', 'order' => 'title COLLATE NOCASE'],
	'album' => ['value' => 'albums', 'order' => 'albums COLLATE NOCASE'],
	'year' => ['value' => 'COALESCE(song_year, fallback_year)', 'order' => 'COALESCE(song_year, fallback_year)'],
	'duration' => ['value' => 's.duration', 'order' => 's.duration'],
];


$aggregates = '';
$selects = '';

foreach ($raters as $rater) {
	$id = (int)$rater['id'];

	$aggregates .= ", MAX(CASE WHEN account_id = {$id} THEN score END) AS score_{$id}, MAX(CASE WHEN account_id = {$id} THEN subjective_note END) AS note_{$id}";
	$selects .= ", r.score_{$id} AS score_{$id}, r.note_{$id} AS note_{$id}";

	$sortable["score_{$id}"] = ['value' => "score_{$id}", 'order' => "score_{$id}"];
	$sortable["note_{$id}"] = ['value' => "note_{$id}", 'order' => "note_{$id} COLLATE NOCASE"];
}

$joins = " LEFT JOIN (SELECT song_id{$aggregates} FROM account_song GROUP BY song_id) r ON r.song_id = s.id";

/// The orders SQLite cannot express; these rows are sorted in PHP below.
$statOrders = ['average', 'deviation', 'median', 'mode', 'highest', 'lowest', 'rated'];

/// ORDER BY that keeps blank cells last in both directions.
function songsOrderBy($column, $dir) {
	if ($column === null) {
		return "s.id {$dir}, s.id";
	}

	return "({$column['value']} IS NULL OR {$column['value']} = ''), {$column['order']} {$dir}, s.id";
}

$requested = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : '';
$sort = isset($sortable[$requested]) || in_array($requested, $statOrders, true) ? $requested : 'id';
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
	ORDER BY " . songsOrderBy($sortable[$sort] ?? null, $dir) . "
")->fetchAll();

if (in_array($sort, $statOrders, true)) {
	require_once __MODULES__ . '/music/stats.php';

	/// The statistic one song sorts on, off the scores already pivoted into its row.
	$statValue = function ($song) use ($raters, $sort) {
		$scores = [];
		foreach ($raters as $rater) {
			$score = $song['score_' . (int)$rater['id']] ?? null;
			if ($score !== null) {
				$scores[] = (float)$score;
			}
		}

		$stats = musicScoreStats($scores);

		return $sort === 'mode'
			? ($stats['modes'] ? max($stats['modes']) : null)
			: $stats[$sort];
	};

	$decorated = [];
	foreach ($globalData['songs'] as $song) {
		$decorated[] = ['value' => $statValue($song), 'id' => (int)$song['id'], 'song' => $song];
	}

	$flip = $dir === 'DESC' ? -1 : 1;

	/// Blanks last in both directions, ties on id, matching songsOrderBy().
	usort($decorated, function ($a, $b) use ($flip) {
		if ($a['value'] === null || $b['value'] === null) {
			if ($a['value'] === $b['value']) {
				return $a['id'] <=> $b['id'];
			}
			return $a['value'] === null ? 1 : -1;
		}

		$order = $a['value'] <=> $b['value'];

		return $order === 0 ? $a['id'] <=> $b['id'] : $order * $flip;
	});

	$globalData['songs'] = array_column($decorated, 'song');
}

require_once __MODULES__ . '/music/links.php';

$globalData['linkFields'] = songLinkFields();

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

/// Album ids paired with their names, so each name can be its own link.
$albumsBySong = [];
foreach ($db->query("
	SELECT at.song_id, at.album_id, al.artist_id,
		(SELECT name FROM album_alias WHERE album_id = al.id ORDER BY is_actual DESC, id LIMIT 1) AS name
	FROM album_track at
	JOIN album al ON al.id = at.album_id
	ORDER BY name COLLATE NOCASE
")->fetchAll() as $row) {
	$albumsBySong[(int)$row['song_id']][] = [
		'id' => (int)$row['album_id'],
		'artist_id' => $row['artist_id'] === null ? null : (int)$row['artist_id'],
		'name' => $row['name'],
	];
}

$globalData['albumsBySong'] = $albumsBySong;

/// Artist ids paired with their names, the same as the albums above.
$artistsBySong = [];
foreach ($db->query("
	SELECT sa.song_id, sa.artist_id,
		(SELECT name FROM artist_alias WHERE artist_id = sa.artist_id ORDER BY is_actual DESC, id LIMIT 1) AS name
	FROM song_artist sa
	ORDER BY sa.id
")->fetchAll() as $row) {
	$artistsBySong[(int)$row['song_id']][] = [
		'id' => (int)$row['artist_id'],
		'name' => $row['name'],
	];
}

$globalData['artistsBySong'] = $artistsBySong;

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
	$raters[$index]['scoreClass'] = 'songRatingCell songRatingScoreCell songScoreColoured' . ($isMine ? ' songMineCell songMyScoreCell' : '');
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

/// The statistics columns, to the right of the rater columns.
$statColumns = [
	['key' => 'average', 'label' => t('album.card.statAverage'), 'name' => t('album.card.sortAverage'), 'score' => true],
	['key' => 'deviation', 'label' => t('album.card.statDeviation'), 'name' => t('album.card.sortDeviation')],
	['key' => 'median', 'label' => t('album.card.statMedian'), 'name' => t('album.card.sortMedian'), 'score' => true],
	['key' => 'mode', 'label' => t('album.card.statMode'), 'name' => t('album.card.sortMode')],
	['key' => 'highest', 'label' => t('song.column.statHighest'), 'name' => t('album.card.sortHighest'), 'score' => true],
	['key' => 'lowest', 'label' => t('song.column.statLowest'), 'name' => t('album.card.sortLowest'), 'score' => true],
	['key' => 'rated', 'label' => t('album.card.statRated'), 'name' => t('album.card.sortRated')],
];

foreach ($statColumns as $stat) {
	$columns[] = array_merge($stat, [
		'type' => 'number',
		'section' => 'stats',
		/// songScoreColoured marks the cells the score ramp paints.
		'class' => 'songStatCell songStat' . ucfirst($stat['key']) . 'Cell'
			. (($stat['score'] ?? false) ? ' songScoreColoured' : ''),
	]);
}

$globalData['statColumns'] = $statColumns;
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
