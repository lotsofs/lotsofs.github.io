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

$accountId = (int)currentAccountId();

$fields = [
	'score' => 'score',
	'note' => 'subjective_note',
];

$rawId = $data['id'] ?? null;
$songId = is_int($rawId) || (is_string($rawId) && ctype_digit($rawId)) ? (int)$rawId : 0;
$field = is_string($data['field'] ?? null) ? $data['field'] : '';
$value = is_string($data['value'] ?? null) ? trim($data['value']) : '';

if (!isset($fields[$field])) {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown field']);
	exit;
}

if (!$db->query("SELECT id FROM song WHERE id = ?", [$songId])->fetch()) {
	echo json_encode(['status' => 'error', 'value' => '', 'message' => t('song.result.notFound')]);
	exit;
}

$existing = $db->query("SELECT score, subjective_note FROM account_song WHERE account_id = ? AND song_id = ?", [$accountId, $songId])->fetch();

if ($field === 'score' && $value !== '' && !is_numeric($value)) {
	echo json_encode([
		'status' => 'error',
		'value' => $existing && $existing['score'] !== null ? (float)$existing['score'] : '',
		'message' => t('song.result.badScore'),
	]);
	exit;
}

$stored = $value === '' ? null : ($field === 'score' ? (float)$value : $value);

$db->query("
	INSERT INTO account_song (account_id, song_id, {$fields[$field]}, updated_at)
	VALUES (?, ?, ?, ?)
	ON CONFLICT (account_id, song_id)
	DO UPDATE SET {$fields[$field]} = excluded.{$fields[$field]},
		updated_at = excluded.updated_at
", [$accountId, $songId, $stored, time()]);

echo json_encode([
	'status' => 'ok',
	'value' => $stored === null ? '' : $stored,
	'message' => '',
]);
