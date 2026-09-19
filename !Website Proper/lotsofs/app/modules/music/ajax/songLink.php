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

$allowedFields = ['spotify_url', 'youtube_url', 'soundcloud_url', 'bandcamp_url', 'filepath', 'other_url'];

function spotifyIdOnly($value) {
	if (preg_match('#/track/([A-Za-z0-9]+)#', $value, $match)) {
		return $match[1];
	}
	return $value;
}

function youtubeIdOnly($value) {
	if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]+)#', $value, $match)) {
		return $match[1];
	}
	return $value;
}

$rawSongId = $data['song_id'] ?? null;
$songId = is_int($rawSongId) || (is_string($rawSongId) && ctype_digit($rawSongId)) ? (int)$rawSongId : 0;
$field = is_string($data['field'] ?? null) ? $data['field'] : '';
$value = is_string($data['value'] ?? null) ? trim($data['value']) : '';

if (!in_array($field, $allowedFields, true)) {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown field']);
	exit;
}

if ($field === 'spotify_url' && $value !== '') {
	$value = spotifyIdOnly($value);
}
elseif ($field === 'youtube_url' && $value !== '') {
	$value = youtubeIdOnly($value);
}

if (!$db->query("SELECT id FROM song WHERE id = ?", [$songId])->fetch()) {
	echo json_encode(['status' => 'error', 'message' => t('song.result.notFound')]);
	exit;
}

$storedValue = $value === '' ? null : $value;

if ($db->query("SELECT song_id FROM song_link WHERE song_id = ?", [$songId])->fetch()) {
	$db->query("UPDATE song_link SET {$field} = ? WHERE song_id = ?", [$storedValue, $songId]);
}
else {
	$db->query("INSERT INTO song_link (song_id, {$field}) VALUES (?, ?)", [$songId, $storedValue]);
}

echo json_encode(['status' => 'ok', 'field' => $field, 'value' => $storedValue, 'message' => '']);
