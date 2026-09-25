<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/links.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$allowedFields = ['spotify_url', 'youtube_url', 'soundcloud_url', 'bandcamp_url', 'filepath', 'other_url'];

/// A value with no id in it is kept as pasted - it may already be a bare
/// id, which is what this endpoint stores.
function spotifyIdOnly($value) {
	return spotifyTrackIdIn($value) ?? $value;
}

function youtubeIdOnly($value) {
	return youtubeVideoIdIn($value) ?? $value;
}

$songId = ajaxInt($data['song_id'] ?? null);
$field = ajaxText($data['field'] ?? null);
$value = ajaxTrimmed($data['value'] ?? null);

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

requireSongJson($db, $songId);

$storedValue = $value === '' ? null : $value;

if ($db->query("SELECT song_id FROM song_link WHERE song_id = ?", [$songId])->fetch()) {
	$db->query("UPDATE song_link SET {$field} = ? WHERE song_id = ?", [$storedValue, $songId]);
}
else {
	$db->query("INSERT INTO song_link (song_id, {$field}) VALUES (?, ?)", [$songId, $storedValue]);
}

echo json_encode(['status' => 'ok', 'field' => $field, 'value' => $storedValue, 'message' => '']);
