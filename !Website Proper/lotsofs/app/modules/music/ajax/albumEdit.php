<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$albumId = ajaxInt($data['album_id'] ?? null);
$field = ajaxText($data['field'] ?? null);
$value = $field === 'name' ? ajaxTrimmed($data['value'] ?? null) : ajaxNumericText($data['value'] ?? null);

requireAlbumJson($db, $albumId);

/// Renaming keeps history: the name it had stays behind as a plain alias and
/// the new one becomes the actual name, which is the same thing the importer
/// does and what makes a known misspelling still resolve to this album. The
/// partial unique index allows one is_actual row per album, so the clear has
/// to land before the set.
if ($field === 'name') {
	if ($value === '') {
		echo json_encode(['status' => 'error', 'message' => t('album.result.nameRequired')]);
		exit;
	}

	$existing = $db->query("SELECT id FROM album_alias WHERE album_id = ? AND name = ?", [$albumId, $value])->fetch();

	$db->pdo->beginTransaction();
	try {
		$db->query("UPDATE album_alias SET is_actual = 0 WHERE album_id = ?", [$albumId]);

		if ($existing) {
			$db->query("UPDATE album_alias SET is_actual = 1 WHERE id = ?", [$existing['id']]);
		}
		else {
			$db->query("INSERT INTO album_alias (album_id, name, is_actual) VALUES (?, ?, 1)", [$albumId, $value]);
		}

		$db->pdo->commit();
	}
	catch (PDOException $e) {
		$db->pdo->rollBack();
		throw $e;
	}

	echo json_encode(['status' => 'ok', 'value' => $value, 'message' => '']);
	exit;
}

if ($field === 'artist') {
	if ($value !== '' && !ctype_digit($value)) {
		echo json_encode(['status' => 'error', 'message' => t('album.result.artistNotFound')]);
		exit;
	}

	$artistId = $value === '' ? null : (int)$value;

	if ($artistId !== null && !$db->query("SELECT id FROM artist WHERE id = ?", [$artistId])->fetch()) {
		echo json_encode(['status' => 'error', 'message' => t('album.result.artistNotFound')]);
		exit;
	}

	$db->query("UPDATE album SET artist_id = ? WHERE id = ?", [$artistId, $albumId]);

	echo json_encode(['status' => 'ok', 'value' => $artistId, 'message' => '']);
	exit;
}

if ($field === 'year') {
	if ($value !== '' && !ctype_digit($value)) {
		echo json_encode(['status' => 'error', 'message' => t('album.result.badYear')]);
		exit;
	}

	$year = $value === '' ? null : (int)$value;

	$db->query("UPDATE album SET release_year = ? WHERE id = ?", [$year, $albumId]);

	echo json_encode(['status' => 'ok', 'value' => $year, 'message' => '']);
	exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown field']);
