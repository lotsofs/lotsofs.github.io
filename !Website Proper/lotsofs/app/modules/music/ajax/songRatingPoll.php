<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/format.php';
require_once __MODULES__ . '/music/hue.php';
require_once __MODULES__ . '/music/stats.php';

requireMusicAccountJson($db, t('ajax.notLoggedIn'));

$accountId = (int)currentAccountId();

$since = ajaxInt($data['since'] ?? null);

$sinceAudit = ajaxInt($data['sinceAudit'] ?? null);

$rows = $db->query("
	SELECT song_id, account_id, updated_at
	FROM account_song
	WHERE updated_at >= ?
	ORDER BY updated_at
", [$since])->fetchAll();

$cursor = $since;
$songsByAccount = [];

foreach ($rows as $row) {
	$cursor = max($cursor, (int)$row['updated_at']);
	$songsByAccount[(int)$row['account_id']][] = (int)$row['song_id'];
}

/* Each change as the cells hold it, effective, so a cleared score that borrows
   again says so; and the same account's ratings on the songs a changed one
   lends to, which no row of their own would bring. */
require_once __MODULES__ . '/music/ratings.php';
musicRatingView($db);

$changes = [];
foreach ($songsByAccount as $changedAccount => $songs) {
	$changes = array_merge($changes, musicEffectiveRatings($db, array_merge($songs, musicVersionIds($db, $songs)), [$changedAccount]));
}

/// The song list's statistics columns for the songs in this tick's changes.
$stats = [];
$changedSongs = array_values(array_unique(array_column($changes, 'song')));

if ($changedSongs) {
	$placeholders = implode(',', array_fill(0, count($changedSongs), '?'));

	$scoresBySong = [];
	foreach ($db->query("
		SELECT song_id, score
		FROM effective_rating
		WHERE score IS NOT NULL AND song_id IN ({$placeholders})
	", $changedSongs)->fetchAll() as $row) {
		$scoresBySong[(int)$row['song_id']][] = (float)$row['score'];
	}

	foreach ($changedSongs as $songId) {
		$stats[] = array_merge(['song' => $songId], musicSongStatFields($scoresBySong[$songId] ?? []));
	}
}

$auditRows = $db->query("
	SELECT ra.id, ra.account_id, ra.song_id, ra.field, ra.value, ra.previous_value,
		a.account_name, a.hue,
		(SELECT name FROM song_alias WHERE song_id = ra.song_id
			ORDER BY is_actual DESC, id LIMIT 1) AS song_title,
		(SELECT group_concat(artist_name, ', ') FROM (
			SELECT (SELECT name FROM artist_alias WHERE artist_id = sa.artist_id
				ORDER BY is_actual DESC, id LIMIT 1) AS artist_name
			FROM song_artist sa WHERE sa.song_id = ra.song_id ORDER BY sa.id
		)) AS artists
	FROM rating_audit ra
	JOIN account a ON a.id = ra.account_id
	WHERE ra.id > ?
	ORDER BY ra.id
	LIMIT 50
", [$sinceAudit])->fetchAll();

$auditCursor = $sinceAudit;
$events = [];

foreach ($auditRows as $row) {
	$auditCursor = max($auditCursor, (int)$row['id']);

	$events[] = [
		'id' => (int)$row['id'],
		'account' => (int)$row['account_id'],
		'name' => $row['account_name'],
		'hue' => musicHueOf($row['account_id'], $row['hue']),
		'songId' => (int)$row['song_id'],
		'songLabel' => musicSongLabel($row['artists'], $row['song_title']),
		'field' => $row['field'],
		'value' => $row['value'],
		'previousValue' => $row['previous_value'],
		'mine' => (int)$row['account_id'] === $accountId,
	];
}

echo json_encode([
	'cursor' => $cursor,
	'changes' => $changes,
	'stats' => $stats,
	'auditCursor' => $auditCursor,
	'events' => $events,
]);
