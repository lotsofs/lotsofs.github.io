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

$rawSongId = $data['song_id'] ?? null;
$songId = is_int($rawSongId) || (is_string($rawSongId) && ctype_digit($rawSongId)) ? (int)$rawSongId : 0;
$value = is_string($data['value'] ?? null) ? trim($data['value']) : '';

if (!$db->query("SELECT id FROM song WHERE id = ?", [$songId])->fetch()) {
	echo json_encode(['status' => 'error', 'message' => t('song.result.notFound')]);
	exit;
}

if ($value !== '' && !ctype_digit($value)) {
	echo json_encode(['status' => 'error', 'message' => t('song.result.badYear')]);
	exit;
}

$year = $value === '' ? null : (int)$value;

$db->query("UPDATE song SET year = ? WHERE id = ?", [$year, $songId]);

echo json_encode(['status' => 'ok', 'value' => $year, 'message' => '']);
