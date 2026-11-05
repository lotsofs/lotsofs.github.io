<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/editAudit.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$songId = ajaxInt($data['song_id'] ?? null);
$value = ajaxNumericText($data['value'] ?? null);

requireSongJson($db, $songId);

if ($value !== '' && !ctype_digit($value)) {
	echo json_encode(['status' => 'error', 'message' => t('song.result.badYear')]);
	exit;
}

$year = $value === '' ? null : (int)$value;
$previous = $db->query("SELECT year FROM song WHERE id = ?", [$songId])->fetch()['year'];

$db->pdo->beginTransaction();
try {
	$db->query("UPDATE song SET year = ? WHERE id = ?", [$year, $songId]);
	musicLogEdit($db, 'song', $songId, 'year', $previous, $year);
	$db->pdo->commit();
}
catch (PDOException $e) {
	$db->pdo->rollBack();
	throw $e;
}

echo json_encode(['status' => 'ok', 'value' => $year, 'message' => '']);
