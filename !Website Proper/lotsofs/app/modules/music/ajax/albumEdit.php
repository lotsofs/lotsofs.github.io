<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/editAudit.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$albumId = ajaxInt($data['album_id'] ?? null);
$field = ajaxText($data['field'] ?? null);
$value = $field === 'name' ? ajaxTrimmed($data['value'] ?? null) : ajaxNumericText($data['value'] ?? null);

requireAlbumJson($db, $albumId);

/// Renaming promotes an alias, keeping the old name. Clear before set: one is_actual row per album.
if ($field === 'name') {
	if ($value === '') {
		echo json_encode(['status' => 'error', 'message' => t('album.result.nameRequired')]);
		exit;
	}

	$existing = $db->query("SELECT id FROM album_alias WHERE album_id = ? AND name = ?", [$albumId, $value])->fetch();
	$previousName = musicAuditName($db, 'album', $albumId);

	$db->pdo->beginTransaction();
	try {
		$db->query("UPDATE album_alias SET is_actual = 0 WHERE album_id = ?", [$albumId]);

		if ($existing) {
			$db->query("UPDATE album_alias SET is_actual = 1 WHERE id = ?", [$existing['id']]);
		}
		else {
			$db->query("INSERT INTO album_alias (album_id, name, is_actual) VALUES (?, ?, 1)", [$albumId, $value]);
		}

		musicLogEdit($db, 'album', $albumId, 'name', $previousName, $value);
		$db->pdo->commit();
	}
	catch (PDOException $e) {
		$db->pdo->rollBack();
		throw $e;
	}

	echo json_encode(['status' => 'ok', 'value' => $value, 'message' => '', 'names' => musicAliasNames($db, 'album', $albumId)]);
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

	$previous = $db->query("SELECT artist_id FROM album WHERE id = ?", [$albumId])->fetch()['artist_id'];

	$db->pdo->beginTransaction();
	try {
		$db->query("UPDATE album SET artist_id = ? WHERE id = ?", [$artistId, $albumId]);
		musicLogEdit($db, 'album', $albumId, 'artist', musicAuditName($db, 'artist', $previous), musicAuditName($db, 'artist', $artistId));
		$db->pdo->commit();
	}
	catch (PDOException $e) {
		$db->pdo->rollBack();
		throw $e;
	}

	echo json_encode(['status' => 'ok', 'value' => $artistId, 'message' => '']);
	exit;
}

if ($field === 'year') {
	if ($value !== '' && !ctype_digit($value)) {
		echo json_encode(['status' => 'error', 'message' => t('album.result.badYear')]);
		exit;
	}

	$year = $value === '' ? null : (int)$value;

	$previous = $db->query("SELECT release_year FROM album WHERE id = ?", [$albumId])->fetch()['release_year'];

	$db->pdo->beginTransaction();
	try {
		$db->query("UPDATE album SET release_year = ? WHERE id = ?", [$year, $albumId]);
		musicLogEdit($db, 'album', $albumId, 'year', $previous, $year);
		$db->pdo->commit();
	}
	catch (PDOException $e) {
		$db->pdo->rollBack();
		throw $e;
	}

	echo json_encode(['status' => 'ok', 'value' => $year, 'message' => '']);
	exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown field']);
