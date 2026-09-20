<?php

require_once __MODULES__ . '/music/ajaxGuard.php';

requireMusicAccountJson($db, t('ajax.notLoggedIn'));

$accountId = (int)currentAccountId();

$since = ajaxInt($data['since'] ?? null);

$sinceAudit = ajaxInt($data['sinceAudit'] ?? null);

$rows = $db->query("
	SELECT song_id, account_id, score, subjective_note, updated_at
	FROM account_song
	WHERE updated_at >= ?
	ORDER BY updated_at
", [$since])->fetchAll();

$cursor = $since;
$changes = [];

foreach ($rows as $row) {
	$cursor = max($cursor, (int)$row['updated_at']);

	$changes[] = [
		'song' => (int)$row['song_id'],
		'account' => (int)$row['account_id'],
		'score' => $row['score'] === null ? '' : (string)(float)$row['score'],
		'note' => $row['subjective_note'] ?? '',
	];
}

$auditRows = $db->query("
	SELECT ra.id, ra.account_id, ra.song_id, ra.field, ra.value, ra.previous_value,
		a.account_name,
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

	$title = $row['song_title'] ?? '';
	$artists = $row['artists'] ?? '';

	$events[] = [
		'id' => (int)$row['id'],
		'account' => (int)$row['account_id'],
		'name' => $row['account_name'],
		'songId' => (int)$row['song_id'],
		'songLabel' => $artists === '' ? $title : $artists . ' — ' . $title,
		'field' => $row['field'],
		'value' => $row['value'],
		'previousValue' => $row['previous_value'],
		'mine' => (int)$row['account_id'] === $accountId,
	];
}

echo json_encode([
	'cursor' => $cursor,
	'changes' => $changes,
	'auditCursor' => $auditCursor,
	'events' => $events,
]);
