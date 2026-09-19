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

$duration = null;

if ($value !== '') {
	if (ctype_digit($value)) {
		$duration = (int)$value;
	}
	elseif (preg_match('/^(\d+):([0-5]\d)$/', $value, $match)) {
		$duration = (int)$match[1] * 60 + (int)$match[2];
	}
	else {
		echo json_encode(['status' => 'error', 'message' => t('song.result.badDuration')]);
		exit;
	}
}

$db->query("UPDATE song SET duration = ? WHERE id = ?", [$duration, $songId]);

echo json_encode(['status' => 'ok', 'value' => $duration, 'message' => '']);
