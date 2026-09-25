<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$songId = ajaxInt($data['song_id'] ?? null);
$value = ajaxNumericText($data['value'] ?? null);

requireSongJson($db, $songId);

if ($value !== '' && !ctype_digit($value)) {
	echo json_encode(['status' => 'error', 'message' => t('song.result.badYear')]);
	exit;
}

$year = $value === '' ? null : (int)$value;

$db->query("UPDATE song SET year = ? WHERE id = ?", [$year, $songId]);

echo json_encode(['status' => 'ok', 'value' => $year, 'message' => '']);
