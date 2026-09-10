<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ajax/ajax.php';

stringCatalogue('music');

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLoginJson(t('ajax.notLoggedIn'));
requireCsrfJson(t('ajax.badCsrf'));

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/auth.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

const ALBUM_ID_NEW = 'new';

$results = [];

foreach ($data as $datum) {
	$providedName = trim($datum['provided_name'] ?? '');
	$rawId = $datum['album_id'] ?? null;
	$aliasName = trim($datum['og_name'] ?? '');
	if ($aliasName === '') {
		$aliasName = $providedName;
	}
	$isActual = !empty($datum['is_actual']);

	if ($rawId === null || $rawId === '') {
		$results[] = ['provided_name' => $providedName, 'album_id' => null, 'status' => 'skipped', 'message' => t('album.result.skipped')];
		continue;
	}

	if ($aliasName === '') {
		$results[] = ['provided_name' => $providedName, 'album_id' => null, 'status' => 'error', 'message' => t('album.result.nameRequired')];
		continue;
	}

	$artistId = isset($datum['artist_id']) && ctype_digit((string)$datum['artist_id']) ? (int)$datum['artist_id'] : null;
	$releaseYear = isset($datum['release_year']) && ctype_digit((string)$datum['release_year']) ? (int)$datum['release_year'] : null;

	if ($rawId === ALBUM_ID_NEW) {
		$db->query("INSERT INTO album (artist_id, release_year) VALUES (?, ?)", [$artistId, $releaseYear]);
		$id = (int)$db->pdo->lastInsertId();
	}
	else {
		$id = (int)$rawId;
		$db->query("UPDATE album SET artist_id = ?, release_year = ? WHERE id = ?", [$artistId, $releaseYear, $id]);
	}

	$existing = $db->query("SELECT id, is_actual FROM album_alias WHERE album_id = ? AND name = ?", [$id, $aliasName])->fetch();

	if ($existing) {
		if ($isActual && !$existing['is_actual']) {
			$db->query("UPDATE album_alias SET is_actual = 0 WHERE album_id = ? AND id != ?", [$id, $existing['id']]);
			$db->query("UPDATE album_alias SET is_actual = 1 WHERE id = ?", [$existing['id']]);
			$message = t('album.result.markedActual', ['name' => $aliasName]);
		}
		else {
			$message = t('album.result.duplicate');
		}
	}
	else {
		if ($isActual) {
			$db->query("UPDATE album_alias SET is_actual = 0 WHERE album_id = ?", [$id]);
		}
		$db->query("INSERT INTO album_alias (album_id, name, is_actual) VALUES (?, ?, ?)", [$id, $aliasName, $isActual ? 1 : 0]);
		$message = $isActual ? t('album.result.addedActual', ['name' => $aliasName]) : t('album.result.added', ['name' => $aliasName]);
	}

	if (!empty($datum['also_alias_provided_name']) && $providedName !== '' && $providedName !== $aliasName) {
		if (!$db->query("SELECT id FROM album_alias WHERE album_id = ? AND name = ?", [$id, $providedName])->fetch()) {
			$db->query("INSERT INTO album_alias (album_id, name, is_actual) VALUES (?, ?, 0)", [$id, $providedName]);
			$message .= t('album.result.alsoAliased', ['name' => $providedName]);
		}
	}

	$added = 0;
	foreach ($datum['tracks'] ?? [] as $track) {
		$songId = isset($track['song_id']) && ctype_digit((string)$track['song_id']) ? (int)$track['song_id'] : 0;
		if ($songId === 0) {
			continue;
		}

		$position = isset($track['position']) && ctype_digit((string)$track['position']) ? (int)$track['position'] : null;
		$aliasId = isset($track['song_alias_id']) && ctype_digit((string)$track['song_alias_id']) ? (int)$track['song_alias_id'] : null;

		if ($db->query("SELECT id FROM album_track WHERE album_id = ? AND song_id = ?", [$id, $songId])->fetch()) {
			$db->query("UPDATE album_track SET position = ?, song_alias_id = ? WHERE album_id = ? AND song_id = ?", [$position, $aliasId, $id, $songId]);
			continue;
		}

		$db->query("INSERT INTO album_track (album_id, song_id, song_alias_id, position) VALUES (?, ?, ?, ?)", [$id, $songId, $aliasId, $position]);
		$added++;
	}

	$results[] = [
		'provided_name' => $providedName,
		'album_id' => $id,
		'status' => 'ok',
		'message' => $message . t('album.result.trackCount', ['count' => $added]),
	];
}

echo json_encode($results);
