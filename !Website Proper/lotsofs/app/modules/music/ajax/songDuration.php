<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$songId = ajaxInt($data['song_id'] ?? null);
$value = ajaxTrimmed($data['value'] ?? null);

requireSongJson($db, $songId);

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
