<?php

require_once __MODULES__ . '/music/ajaxGuard.php';

$albumId = ajaxInt($data['album_id'] ?? null);

/// Which order the graph draws its tracks in. Passed through as typed: the
/// partial builds the list of orders it offers (it needs the rater names for
/// the labels) and falls back to album order for anything it doesn't know, so
/// there is one list rather than a whitelist here and another one there.
$graphSort = ajaxTrimmed($data['sort'] ?? null);
$graphDir = ajaxTrimmed($data['dir'] ?? null);

/// Which order the graph draws its tracks in. Passed through as typed: the
/// partial builds the list of orders it offers (it needs the rater names for
/// the labels) and falls back to album order for anything it doesn't know, so
/// there is one list rather than a whitelist here and another one there.
$graphSort = ajaxTrimmed($data['sort'] ?? null);
$graphDir = ajaxTrimmed($data['dir'] ?? null);

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

/// A track is listed under the alias the album credits it as when there is one,
/// which is the same rule the song list uses for its "listed as" titles.
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

/// Every statistic is over the tracks that rater actually scored, not over the
/// album - `rated` says how many that was, so an average over three of fifteen
/// songs can't be read as an album score. One query pivoted two ways: by
/// account for the per-rater table, by song for the per-track columns and the
/// graph. musicScoreStats() turns either into the same five numbers.
require_once __MODULES__ . '/music/stats.php';

$scoresByAccount = [];
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
}

/// What the album thinks of each track: the mean of the scores it was actually
/// given, so a track two of four raters scored is the average of those two and
/// not of four with two zeroes in it - which is why the count sits next to it,
/// and the spread beside that, since an average of two is a different claim
/// from an average of five whether or not they agreed.
/// The track table shows three of these; the graph can be ordered by any of
/// them, which is why the median and mode are kept rather than dropped.
foreach ($album['tracks'] as $index => $track) {
	$stats = musicScoreStats($album['trackScores'][(int)$track['song_id']] ?? []);

	$album['tracks'][$index]['average'] = $stats['average'];
	$album['tracks'][$index]['deviation'] = $stats['deviation'];
	$album['tracks'][$index]['median'] = $stats['median'];
	$album['tracks'][$index]['modes'] = $stats['modes'];
	$album['tracks'][$index]['rated'] = $stats['rated'];
}

/// One row per account, not per rater who has rated something here: an album
/// nobody has scored still lists everyone, so the card reads the same whichever
/// album it is showing.
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

$album['graphSort'] = $graphSort;
$album['graphDir'] = $graphDir;
$album['trackCount'] = count($album['tracks']);
$album['isAdmin'] = musicIsAdmin($db);

ob_start();
require __MODULES__ . '/music/views/partials/albumCard.php';
$html = ob_get_clean();

$response = ['status' => 'ok', 'html' => $html];

/// The artist and song lists the edit dropdowns need are NOT here: they are
/// the whole catalogue, they do not change with the album, and a card is opened
/// to read far more often than to edit. /music/ajax/album-options fetches them
/// on the first Edit click instead. At ten thousand songs that is the
/// difference between a third of a millisecond and twenty-five, and between
/// nothing and 636KB, on every single card open.
///
/// These aliases do stay: they are only the names of the songs already on this
/// album, a few rows, and the alias list for a song added mid-edit comes back
/// with that add's own response.
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
