<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/stats.php';
require_once __MODULES__ . '/music/hue.php';

$artistId = ajaxInt($data['artist_id'] ?? null);

/// Passed through as typed; the graph partial owns the list of orders.
$graphSort = ajaxTrimmed($data['sort'] ?? null);
$graphDir = ajaxTrimmed($data['dir'] ?? null);

/// Whose scores the album table reports; 0 means everyone.
$statsWho = ajaxInt($data['who'] ?? null);

$artist = $db->query("
	SELECT
		a.id,
		(SELECT name FROM artist_alias WHERE artist_id = a.id ORDER BY is_actual DESC, id LIMIT 1) AS name,
		(SELECT group_concat(name, ', ') FROM (
			SELECT name FROM artist_alias
			WHERE artist_id = a.id
				AND id != (SELECT id FROM artist_alias WHERE artist_id = a.id ORDER BY is_actual DESC, id LIMIT 1)
			ORDER BY name COLLATE NOCASE
		)) AS aliases
	FROM artist a
	WHERE a.id = ?
", [$artistId])->fetch();

if (!$artist) {
	echo json_encode(['status' => 'error', 'message' => t('artist.card.notFound')]);
	exit;
}

/// Every song credited to this artist, dated by its earliest album where it has no year of its own.
$artist['songs'] = $db->query("
	SELECT
		s.song_id,
		s.duration,
		s.year,
		(SELECT name FROM song_alias WHERE song_id = s.song_id ORDER BY is_actual DESC, id LIMIT 1) AS title
	FROM (
		SELECT DISTINCT
			sa.song_id,
			sg.duration,
			COALESCE(sg.year, (
				SELECT MIN(al.release_year) FROM album_track at
				JOIN album al ON al.id = at.album_id
				WHERE at.song_id = sg.id AND al.release_year IS NOT NULL
			)) AS year
		FROM song_artist sa
		JOIN song sg ON sg.id = sa.song_id
		WHERE sa.artist_id = ?
	) s
	ORDER BY title COLLATE NOCASE
", [$artistId])->fetchAll();

/// The albums this artist is credited on. Not a track list.
$artist['albums'] = $db->query("
	SELECT al.id, al.release_year,
		(SELECT name FROM album_alias WHERE album_id = al.id ORDER BY is_actual DESC, id LIMIT 1) AS name
	FROM album al
	WHERE al.artist_id = ?
	ORDER BY al.release_year, name COLLATE NOCASE
", [$artistId])->fetchAll();

$scoresByAccount = [];
$allScores = [];
$artist['songScores'] = [];
foreach ($db->query("
	SELECT acs.account_id, acs.song_id, acs.score
	FROM account_song acs
	WHERE acs.score IS NOT NULL
		AND acs.song_id IN (SELECT song_id FROM song_artist WHERE artist_id = ?)
", [$artistId])->fetchAll() as $row) {
	$scoresByAccount[(int)$row['account_id']][] = (float)$row['score'];
	$artist['songScores'][(int)$row['song_id']][(int)$row['account_id']] = (float)$row['score'];
	$allScores[] = (float)$row['score'];
}

foreach ($artist['songs'] as $index => $song) {
	$artist['songs'][$index] = array_merge($song, musicScoreStats($artist['songScores'][(int)$song['song_id']] ?? []));
}

$artist['averages'] = [];
foreach ($db->query("SELECT id, account_name, hue FROM account ORDER BY id = ? DESC, id", [(int)currentAccountId()])->fetchAll() as $account) {
	$artist['averages'][] = array_merge(
		[
			'id' => (int)$account['id'],
			'account_name' => $account['account_name'],
			'hue' => musicHueOf($account['id'], $account['hue']),
		],
		musicScoreStats($scoresByAccount[(int)$account['id']] ?? [])
	);
}

$artist['totals'] = musicScoreStats($allScores);
$artist['totals']['possible'] = count($artist['songs']) * count($artist['averages']);

$artist['statsWho'] = musicStatsWho($statsWho, $artist['averages']);

/// Per-album statistics, over every score on the record pooled, or one rater's alone.
$albumIds = [];
foreach ($artist['albums'] as $album) {
	$albumIds[] = (int)$album['id'];
}

if ($albumIds) {
	$albumPlaceholders = implode(',', array_fill(0, count($albumIds), '?'));

	$albumScores = [];
	$albumRaterScores = [];
	foreach ($db->query("
		SELECT at.album_id, acs.account_id, acs.score
		FROM album_track at
		JOIN account_song acs ON acs.song_id = at.song_id
		WHERE acs.score IS NOT NULL AND at.album_id IN ({$albumPlaceholders})
	", $albumIds)->fetchAll() as $row) {
		$albumScores[(int)$row['album_id']][] = (float)$row['score'];
		$albumRaterScores[(int)$row['album_id']][(int)$row['account_id']][] = (float)$row['score'];
	}

	$albumTracks = [];
	foreach ($db->query("
		SELECT album_id, COUNT(*) AS tracks
		FROM album_track
		WHERE album_id IN ({$albumPlaceholders})
		GROUP BY album_id
	", $albumIds)->fetchAll() as $row) {
		$albumTracks[(int)$row['album_id']] = (int)$row['tracks'];
	}

	foreach ($artist['albums'] as $index => $album) {
		$albumId = (int)$album['id'];
		$trackCount = $albumTracks[$albumId] ?? 0;

		$scores = $artist['statsWho'] === null
			? ($albumScores[$albumId] ?? [])
			: ($albumRaterScores[$albumId][$artist['statsWho']] ?? []);

		$artist['albums'][$index] = array_merge(
			$album,
			musicScoreStats($scores),
			[
				'tracks' => $trackCount,
				'possible' => $artist['statsWho'] === null ? $trackCount * count($artist['averages']) : $trackCount,
			]
		);
	}
}

$artist['songCount'] = count($artist['songs']);
$artist['graphSort'] = $graphSort;
$artist['graphDir'] = $graphDir;
$artist['isAdmin'] = musicIsAdmin($db);

ob_start();
require __MODULES__ . '/music/views/partials/artistCard.php';

echo json_encode(['status' => 'ok', 'html' => ob_get_clean()]);
