<?php

require_once __MODULES__ . '/music/ajaxGuard.php';

stringCatalogue('music');

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLoginJson(t('ajax.notLoggedIn'));
requireCsrfJson(t('ajax.badCsrf'));

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/auth.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$rawId = $data['id'] ?? null;
$id = is_int($rawId) || (is_string($rawId) && ctype_digit($rawId)) ? (int)$rawId : 0;
$field = is_string($data['field'] ?? null) ? $data['field'] : '';
$value = is_string($data['value'] ?? null) ? trim($data['value']) : '';

if ($field !== 'title' && $field !== 'note') {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown field']);
	exit;
}

$song = $db->query("
	SELECT s.artist_id, s.objective_note, st.name AS title
	FROM song s
	LEFT JOIN song_alias st ON st.song_id = s.id AND st.is_actual = 1
	WHERE s.id = ?
", [$id])->fetch();

if (!$song) {
	echo json_encode(['status' => 'error', 'value' => '', 'message' => t('song.result.notFound')]);
	exit;
}

if ($field === 'note') {
	$db->query("UPDATE song SET objective_note = ? WHERE id = ?", [$value === '' ? null : $value, $id]);
	echo json_encode(['status' => 'ok', 'value' => $value, 'message' => '']);
	exit;
}

if ($value === '') {
	echo json_encode(['status' => 'error', 'value' => $song['title'] ?? '', 'message' => t('song.result.required')]);
	exit;
}

$clash = $db->query("
	SELECT s.id
	FROM song s
	JOIN song_alias sa ON sa.song_id = s.id
	WHERE s.artist_id = ? AND sa.name = ? AND s.id != ?
", [$song['artist_id'], $value, $id])->fetch();

if ($clash) {
	echo json_encode(['status' => 'duplicate', 'value' => $song['title'] ?? '', 'message' => t('song.result.duplicate')]);
	exit;
}

if ($song['title'] === null) {
	$db->query("INSERT INTO song_alias (song_id, name, is_actual) VALUES (?, ?, 1)", [$id, $value]);
}
else {
	$db->query("UPDATE song_alias SET name = ? WHERE song_id = ? AND is_actual = 1", [$value, $id]);
}

echo json_encode(['status' => 'ok', 'value' => $value, 'message' => '']);
