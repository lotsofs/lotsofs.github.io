<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$songId = ajaxInt($data['song_id'] ?? null);
$albumId = ajaxInt($data['album_id'] ?? null);
$action = ajaxText($data['action'] ?? null);

requireSongJson($db, $songId);

if ($action === 'remove') {
	$db->query("DELETE FROM album_track WHERE song_id = ? AND album_id = ?", [$songId, $albumId]);
	echo json_encode(['status' => 'ok', 'message' => '']);
	exit;
}

if ($action !== 'add') {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown action']);
	exit;
}

if (!$db->query("SELECT id FROM album WHERE id = ?", [$albumId])->fetch()) {
	echo json_encode(['status' => 'error', 'message' => t('song.result.albumNotFound')]);
	exit;
}

if ($db->query("SELECT id FROM album_track WHERE song_id = ? AND album_id = ?", [$songId, $albumId])->fetch()) {
	echo json_encode(['status' => 'duplicate', 'message' => t('song.result.albumDuplicate')]);
	exit;
}

$db->query("INSERT INTO album_track (album_id, song_id, position, song_alias_id) VALUES (?, ?, NULL, NULL)", [$albumId, $songId]);

echo json_encode(['status' => 'ok', 'message' => '']);
