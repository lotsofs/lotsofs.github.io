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
$rawAlbumId = $data['album_id'] ?? null;
$albumId = is_int($rawAlbumId) || (is_string($rawAlbumId) && ctype_digit($rawAlbumId)) ? (int)$rawAlbumId : 0;
$action = is_string($data['action'] ?? null) ? $data['action'] : '';

if (!$db->query("SELECT id FROM song WHERE id = ?", [$songId])->fetch()) {
	echo json_encode(['status' => 'error', 'message' => t('song.result.notFound')]);
	exit;
}

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
