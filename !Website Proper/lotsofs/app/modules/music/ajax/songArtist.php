<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$songId = ajaxInt($data['song_id'] ?? null);
$artistId = ajaxInt($data['artist_id'] ?? null);
$action = ajaxText($data['action'] ?? null);

requireSongJson($db, $songId);

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
