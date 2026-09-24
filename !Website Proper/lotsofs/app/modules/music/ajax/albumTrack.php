<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$albumId = ajaxInt($data['album_id'] ?? null);
$songId = ajaxInt($data['song_id'] ?? null);
$action = ajaxText($data['action'] ?? null);
$rawPosition = ajaxNumericText($data['position'] ?? null);

requireAlbumJson($db, $albumId);

if ($action === 'remove') {
	$db->query("DELETE FROM album_track WHERE album_id = ? AND song_id = ?", [$albumId, $songId]);
	echo json_encode(['status' => 'ok', 'message' => '']);
	exit;
}

if ($rawPosition !== '' && !ctype_digit($rawPosition)) {
	echo json_encode(['status' => 'error', 'message' => t('album.result.badPosition')]);
	exit;
}

$position = $rawPosition === '' ? null : (int)$rawPosition;

requireSongJson($db, $songId);

$existing = $db->query("SELECT id FROM album_track WHERE album_id = ? AND song_id = ?", [$albumId, $songId])->fetch();

function albumTrackAliases($db, $songId) {
	$aliases = [];

	foreach ($db->query("
		SELECT id, name, is_actual FROM song_alias
		WHERE song_id = ?
		ORDER BY is_actual DESC, name COLLATE NOCASE
	", [$songId])->fetchAll() as $alias) {
		$aliases[] = [
			'id' => (int)$alias['id'],
			'name' => $alias['name'],
			'isActual' => (bool)$alias['is_actual'],
		];
	}

	return $aliases;
}

/// Which of the song's names this release credits it under. Empty means the
/// song's own actual name, which is what a track with no alias of its own
/// already falls back to everywhere it is shown.
if ($action === 'alias') {
	if (!$existing) {
		echo json_encode(['status' => 'error', 'message' => t('album.result.trackNotFound')]);
		exit;
	}

	$rawAlias = ajaxNumericText($data['song_alias_id'] ?? null);

	if ($rawAlias !== '' && !ctype_digit($rawAlias)) {
		echo json_encode(['status' => 'error', 'message' => t('album.result.aliasNotFound')]);
		exit;
	}

	$aliasId = $rawAlias === '' ? null : (int)$rawAlias;

	if ($aliasId !== null && !$db->query("SELECT id FROM song_alias WHERE id = ? AND song_id = ?", [$aliasId, $songId])->fetch()) {
		echo json_encode(['status' => 'error', 'message' => t('album.result.aliasNotFound')]);
		exit;
	}

	$db->query("UPDATE album_track SET song_alias_id = ? WHERE id = ?", [$aliasId, $existing['id']]);
	echo json_encode(['status' => 'ok', 'value' => $aliasId, 'message' => '']);
	exit;
}

if ($action === 'position') {
	if (!$existing) {
		echo json_encode(['status' => 'error', 'message' => t('album.result.trackNotFound')]);
		exit;
	}

	$db->query("UPDATE album_track SET position = ? WHERE id = ?", [$position, $existing['id']]);
	echo json_encode(['status' => 'ok', 'value' => $position, 'message' => '']);
	exit;
}

if ($action !== 'add') {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown action']);
	exit;
}

if ($existing) {
	echo json_encode(['status' => 'duplicate', 'message' => t('album.result.trackDuplicate'), 'aliases' => albumTrackAliases($db, $songId)]);
	exit;
}

/// song_alias_id stays null: the "listed as" title a release credits a song
/// under is set by the bulk importer, and picking a song out of a dropdown
/// here says nothing about which of its names this album uses.
$db->query("INSERT INTO album_track (album_id, song_id, song_alias_id, position) VALUES (?, ?, NULL, ?)", [$albumId, $songId, $position]);

echo json_encode(['status' => 'ok', 'value' => $position, 'message' => '', 'aliases' => albumTrackAliases($db, $songId)]);
