<?php

require_once __MODULES__ . '/music/ajaxGuard.php';

$albumId = ajaxInt($data['album_id'] ?? null);

/// Passed through as typed; the graph partial owns the list of orders.
$graphSort = ajaxTrimmed($data['sort'] ?? null);
$graphDir = ajaxTrimmed($data['dir'] ?? null);

/// Whose scores the track table reports; 0 means everyone.
$statsWho = ajaxInt($data['who'] ?? null);

$album = $db->query("
	SELECT
		al.id,
		al.artist_id,
		al.release_year,
		(SELECT name FROM album_alias WHERE album_id = al.id ORDER BY is_actual DESC, id LIMIT 1) AS name,
		(SELECT group_concat(name, ', ') FROM (
			SELECT name FROM album_alias
			WHERE album_id = al.id
				AND id != (SELECT id FROM album_alias WHERE album_id = al.id ORDER BY is_actual DESC, id LIMIT 1)
			ORDER BY name COLLATE NOCASE
		)) AS aliases,
		(SELECT name FROM artist_alias
			WHERE artist_id = al.artist_id
			ORDER BY is_actual DESC, id LIMIT 1) AS artist
	FROM album al
	WHERE al.id = ?
", [$albumId])->fetch();

if (!$album) {
	echo json_encode(['status' => 'error', 'message' => t('album.card.notFound')]);
	exit;
}

/// A track is listed under the alias this release credits it as, where there is one.
$album['tracks'] = $db->query("
	SELECT
		at.song_id,
		at.position,
		at.song_alias_id,
		s.duration,
		COALESCE(
			(SELECT name FROM song_alias WHERE id = at.song_alias_id),
			(SELECT name FROM song_alias WHERE song_id = at.song_id ORDER BY is_actual DESC, id LIMIT 1)
		) AS title,
		(SELECT group_concat(artist_name, ', ') FROM (
			SELECT (SELECT name FROM artist_alias
				WHERE artist_id = sa.artist_id
				ORDER BY is_actual DESC, id LIMIT 1) AS artist_name
			FROM song_artist sa
			WHERE sa.song_id = at.song_id
			ORDER BY sa.id
		)) AS artist
	FROM album_track at
	JOIN song s ON s.id = at.song_id
	WHERE at.album_id = ?
	ORDER BY at.position IS NULL, at.position, title COLLATE NOCASE
", [$albumId])->fetchAll();

/// One score query, pivoted by account for the rater table and by song for the tracks.
require_once __MODULES__ . '/music/stats.php';

$scoresByAccount = [];
$allScores = [];
$album['trackScores'] = [];
foreach ($db->query("
	SELECT acs.account_id, acs.song_id, acs.score
	FROM account_song acs
	JOIN album_track at ON at.song_id = acs.song_id
	WHERE at.album_id = ? AND acs.score IS NOT NULL
	ORDER BY acs.score
", [$albumId])->fetchAll() as $row) {
	$scoresByAccount[(int)$row['account_id']][] = (float)$row['score'];
	$album['trackScores'][(int)$row['song_id']][(int)$row['account_id']] = (float)$row['score'];
	$allScores[] = (float)$row['score'];
}

/// One row per account, whether or not they have scored anything here.
require_once __MODULES__ . '/music/hue.php';

$album['averages'] = [];
foreach ($db->query("SELECT id, account_name, hue FROM account ORDER BY id = ? DESC, id", [(int)currentAccountId()])->fetchAll() as $account) {
	$album['averages'][] = array_merge(
		[
			'id' => (int)$account['id'],
			'account_name' => $account['account_name'],
			'hue' => musicHueOf($account['id'], $account['hue']),
		],
		musicScoreStats($scoresByAccount[(int)$account['id']] ?? [])
	);
}

$album['statsWho'] = musicStatsWho($statsWho, $album['averages']);

/// Per-track statistics over the scores each track was actually given.
foreach ($album['tracks'] as $index => $track) {
	$stats = musicScoreStats($album['trackScores'][(int)$track['song_id']] ?? []);

	$album['tracks'][$index]['average'] = $stats['average'];
	$album['tracks'][$index]['deviation'] = $stats['deviation'];
	$album['tracks'][$index]['median'] = $stats['median'];
	$album['tracks'][$index]['modes'] = $stats['modes'];
	$album['tracks'][$index]['lowest'] = $stats['lowest'];
	$album['tracks'][$index]['highest'] = $stats['highest'];
	$album['tracks'][$index]['rated'] = $stats['rated'];

	/// One rater's score for this track, where the card is read as one person.
	$album['tracks'][$index]['whoScore'] = $album['statsWho'] === null
		? null
		: ($album['trackScores'][(int)$track['song_id']][$album['statsWho']] ?? null);
}

/// Every score on the album pooled; `possible` is tracks times accounts.
$album['totals'] = musicScoreStats($allScores);
$album['totals']['possible'] = count($album['tracks']) * count($album['averages']);

$album['graphSort'] = $graphSort;
$album['graphDir'] = $graphDir;
$album['trackCount'] = count($album['tracks']);
$album['isAdmin'] = musicIsAdmin($db);

ob_start();
require __MODULES__ . '/music/views/partials/albumCard.php';
$html = ob_get_clean();

$response = ['status' => 'ok', 'html' => $html];

/// Only the aliases of songs already on this album. The edit dropdowns' catalogue comes from /music/ajax/album-options.
if ($album['isAdmin']) {
	$response['trackAliases'] = [];
	foreach ($db->query("
		SELECT sa.id, sa.song_id, sa.name, sa.is_actual
		FROM song_alias sa
		JOIN album_track at ON at.song_id = sa.song_id
		WHERE at.album_id = ?
		ORDER BY sa.is_actual DESC, sa.name COLLATE NOCASE
	", [$albumId])->fetchAll() as $alias) {
		$response['trackAliases'][(string)(int)$alias['song_id']][] = [
			'id' => (int)$alias['id'],
			'name' => $alias['name'],
			'isActual' => (bool)$alias['is_actual'],
		];
	}
}

echo json_encode($response);
