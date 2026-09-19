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
$rawArtistId = $data['artist_id'] ?? null;
$artistId = is_int($rawArtistId) || (is_string($rawArtistId) && ctype_digit($rawArtistId)) ? (int)$rawArtistId : 0;
$action = is_string($data['action'] ?? null) ? $data['action'] : '';

if (!$db->query("SELECT id FROM song WHERE id = ?", [$songId])->fetch()) {
	echo json_encode(['status' => 'error', 'message' => t('song.result.notFound')]);
	exit;
}

if ($action === 'remove') {
	$db->query("DELETE FROM song_artist WHERE song_id = ? AND artist_id = ?", [$songId, $artistId]);
	echo json_encode(['status' => 'ok', 'message' => '']);
	exit;
}

if ($action !== 'add') {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown action']);
	exit;
}

if (!$db->query("SELECT id FROM artist WHERE id = ?", [$artistId])->fetch()) {
	echo json_encode(['status' => 'error', 'message' => t('song.result.artistNotFound')]);
	exit;
}

if ($db->query("SELECT id FROM song_artist WHERE song_id = ? AND artist_id = ?", [$songId, $artistId])->fetch()) {
	echo json_encode(['status' => 'duplicate', 'message' => t('song.result.artistDuplicate')]);
	exit;
}

$db->query("INSERT INTO song_artist (song_id, artist_id) VALUES (?, ?)", [$songId, $artistId]);

echo json_encode(['status' => 'ok', 'message' => '']);
