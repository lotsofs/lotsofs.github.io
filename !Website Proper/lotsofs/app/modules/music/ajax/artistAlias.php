<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/editAudit.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

const ARTIST_ID_NEW = 'new';

$results = [];

$groupArtistIds = [];

$db->pdo->beginTransaction();
try {
	foreach ($data as $datum) {
		$providedName = trim($datum['provided_name'] ?? '');
		$rawId = $datum['artist_id'] ?? null;
		$aliasName = trim($datum['og_name'] ?? '');
		if ($aliasName === '') {
			$aliasName = $providedName;
		}
		$isActual = !empty($datum['is_actual']);

		if ($rawId === null || $rawId === '') {
			$results[] = ['provided_name' => $providedName, 'artist_id' => null, 'status' => 'skipped', 'message' => t('artist.result.skipped')];
			continue;
		}

		if ($aliasName === '') {
			$results[] = ['provided_name' => $providedName, 'artist_id' => null, 'status' => 'error', 'message' => t('artist.result.nameRequired')];
			continue;
		}

		$createdArtist = false;

		if ($rawId === ARTIST_ID_NEW) {
			$groupKey = trim($datum['group'] ?? '') ?: $providedName;
			if (isset($groupArtistIds[$groupKey])) {
				$id = $groupArtistIds[$groupKey];
			}
			else {
				$db->query("INSERT INTO artist DEFAULT VALUES");
				$id = $db->pdo->lastInsertId();
				$groupArtistIds[$groupKey] = $id;
				$createdArtist = true;
			}
		}
		else {
			$id = $rawId;
		}

		$storeName = $createdArtist || !isset($datum['store_name']) || !empty($datum['store_name']);

		if (!$storeName) {
			$matchedRow = $db->query("SELECT name FROM artist_alias WHERE artist_id = ? ORDER BY is_actual DESC, id LIMIT 1", [$id])->fetch();
			$matchedName = $matchedRow ? $matchedRow['name'] : $aliasName;
			$results[] = [
				'provided_name' => $providedName,
				'artist_id' => (int)$id,
				'artist_name' => $matchedName,
				'status' => 'duplicate',
				'message' => t('artist.result.matchedOnly', ['artist' => $matchedName]),
			];
			continue;
		}

		$existing = $db->query("SELECT id, is_actual FROM artist_alias WHERE artist_id = ? AND name = ?", [$id, $aliasName])->fetch();
		$previousName = $createdArtist ? null : musicAuditName($db, 'artist', $id);

		$status = 'ok';

		if ($existing) {
			if ($isActual && !$existing['is_actual']) {
				$db->query("UPDATE artist_alias SET is_actual = 0 WHERE artist_id = ? AND id != ?", [$id, $existing['id']]);
				$db->query("UPDATE artist_alias SET is_actual = 1 WHERE id = ?", [$existing['id']]);
				$outcome = 'markedActual';
			}
			else {
				$status = 'duplicate';
				$outcome = 'duplicate';
			}
		}
		else {
			if ($isActual) {
				$db->query("UPDATE artist_alias SET is_actual = 0 WHERE artist_id = ?", [$id]);
			}
			$db->query("INSERT INTO artist_alias (artist_id, name, is_actual) VALUES (?, ?, ?)", [$id, $aliasName, $isActual ? 1 : 0]);
			$outcome = $createdArtist ? 'created' : 'added';
		}

		/// A name made current is a rename; the name it replaced stays as another name.
		if ($createdArtist) {
			musicLogEdit($db, 'artist', $id, 'added', null, $aliasName);
		}
		else {
			musicLogEdit($db, 'artist', $id, 'name', $previousName, musicAuditName($db, 'artist', $id));
			if (!$existing && !$isActual) {
				musicLogEdit($db, 'artist', $id, 'alias', null, $aliasName);
			}
		}

		$artistNameRow = $db->query("SELECT name FROM artist_alias WHERE artist_id = ? ORDER BY is_actual DESC, id LIMIT 1", [$id])->fetch();
		$artistName = $artistNameRow ? $artistNameRow['name'] : $aliasName;

		$params = ['name' => $aliasName, 'artist' => $artistName];

		if ($outcome === 'created') {
			$message = t('artist.result.created', $params);
		}
		else if ($outcome === 'markedActual') {
			$message = t('artist.result.markedActual', $params);
		}
		else if ($outcome === 'duplicate') {
			$message = t('artist.result.duplicate', $params);
		}
		else {
			$message = t('artist.result.added', $params);
		}

		if (!empty($datum['also_alias_provided_name']) && $providedName !== '' && $providedName !== $aliasName) {
			if (!$db->query("SELECT id FROM artist_alias WHERE artist_id = ? AND name = ?", [$id, $providedName])->fetch()) {
				$db->query("INSERT INTO artist_alias (artist_id, name, is_actual) VALUES (?, ?, 0)", [$id, $providedName]);
				musicLogEdit($db, 'artist', $id, 'alias', null, $providedName);
				$message .= t('artist.result.alsoAliased', ['name' => $providedName]);
			}
		}

		$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$id, 'artist_name' => $artistName, 'status' => $status, 'message' => $message];
	}

	$db->pdo->commit();
}
catch (PDOException $e) {
	$db->pdo->rollBack();
	throw $e;
}

/* Each song's albums, for two songs of one artist that share a title: labels to
   tell them apart in the Song step, and every name of those albums, which a
   pasted album is matched against as the Album step matches one. */
function artistSongAlbums($db, $artistId) {
	$albums = [];

	foreach ($db->query("
		SELECT at.song_id,
			(SELECT name FROM album_alias WHERE album_id = at.album_id ORDER BY is_actual DESC, id LIMIT 1) AS label,
			aa.name
		FROM album_track at
		JOIN song_artist art ON art.song_id = at.song_id
		JOIN album_alias aa ON aa.album_id = at.album_id
		WHERE art.artist_id = ?
		ORDER BY at.song_id, at.album_id, aa.id
	", [$artistId])->fetchAll() as $row) {
		$songId = (int)$row['song_id'];
		$albums[$songId] ??= ['labels' => [], 'names' => []];
		if ($row['label'] !== null && !in_array($row['label'], $albums[$songId]['labels'], true)) {
			$albums[$songId]['labels'][] = $row['label'];
		}
		if (!in_array($row['name'], $albums[$songId]['names'], true)) {
			$albums[$songId]['names'][] = $row['name'];
		}
	}

	return (object)$albums;
}

$songsByArtist = [];
$albumsByArtist = [];
foreach ($results as $index => $result) {
	$artistId = $result['artist_id'] ?? null;
	if (!$artistId) {
		continue;
	}

	if (!isset($songsByArtist[$artistId])) {
		$songsByArtist[$artistId] = $db->query("
			SELECT s.id, sa.name, sa.is_actual
			FROM song s
			JOIN song_alias sa ON sa.song_id = s.id
			JOIN song_artist art ON art.song_id = s.id
			WHERE art.artist_id = ?
			ORDER BY sa.name COLLATE NOCASE
		", [$artistId])->fetchAll();
		$albumsByArtist[$artistId] = artistSongAlbums($db, $artistId);
	}

	$results[$index]['songs'] = $songsByArtist[$artistId];
	$results[$index]['song_albums'] = $albumsByArtist[$artistId];
}

echo json_encode($results);
