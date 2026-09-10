<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ajax/ajax.php';

stringCatalogue('music');

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLoginJson(t('ajax.notLoggedIn'));
requireCsrfJson(t('ajax.badCsrf'));

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/auth.php';

if (!musicAccount($db)) {
	http_response_code(403);
	echo json_encode(['error' => t('ajax.notLoggedIn')]);
	exit;
}

$rawSince = $data['since'] ?? null;
$since = is_int($rawSince) || (is_string($rawSince) && ctype_digit($rawSince)) ? (int)$rawSince : 0;

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

echo json_encode(['cursor' => $cursor, 'changes' => $changes]);
