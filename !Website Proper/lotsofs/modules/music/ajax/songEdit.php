<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ajax/ajax.php';

stringCatalogue('music');

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLoginJson(t('ajax.notLoggedIn'));
requireCsrfJson(t('ajax.badCsrf'));

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/auth.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

// a column name cannot be a bound parameter, so only these are ever used
$fields = [
	'title' => 'title',
	'note' => 'objective_note',
];

$rawId = $data['id'] ?? null;
$id = is_int($rawId) || (is_string($rawId) && ctype_digit($rawId)) ? (int)$rawId : 0;
$field = is_string($data['field'] ?? null) ? $data['field'] : '';
$value = is_string($data['value'] ?? null) ? trim($data['value']) : '';

if (!isset($fields[$field])) {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown field']);
	exit;
}

$song = $db->query("SELECT artist_id, title, objective_note FROM song WHERE id = ?", [$id])->fetch();

if (!$song) {
	echo json_encode(['status' => 'error', 'value' => '', 'message' => t('song.notFound')]);
	exit;
}

if ($field === 'title') {
	if ($value === '') {
		echo json_encode(['status' => 'error', 'value' => $song['title'], 'message' => t('song.required')]);
		exit;
	}

	$clash = $db->query("SELECT id FROM song WHERE artist_id = ? AND title = ? AND id != ?", [$song['artist_id'], $value, $id])->fetch();

	if ($clash) {
		echo json_encode(['status' => 'duplicate', 'value' => $song['title'], 'message' => t('song.duplicate')]);
		exit;
	}
}

$db->query("UPDATE song SET {$fields[$field]} = ? WHERE id = ?", [$value === '' ? null : $value, $id]);

echo json_encode(['status' => 'ok', 'value' => $value, 'message' => '']);
