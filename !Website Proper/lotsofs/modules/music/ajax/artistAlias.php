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

const ARTIST_ID_NEW = 'new';

$results = [];

$groupArtistIds = [];

foreach ($data as $datum) {
	$providedName = trim($datum['provided_name'] ?? '');
	$rawId = $datum['artist_id'] ?? null;
	$aliasName = trim($datum['og_name'] ?? '');
	if ($aliasName === '') {
		$aliasName = $providedName;
	}
	$isActual = !empty($datum['is_actual']);

	if ($rawId === null || $rawId === '') {
		$results[] = ['provided_name' => $providedName, 'artist_id' => null, 'status' => 'skipped', 'message' => t('alias.skipped')];
		continue;
	}

	if ($aliasName === '') {
		$results[] = ['provided_name' => $providedName, 'artist_id' => null, 'status' => 'error', 'message' => t('alias.nameRequired')];
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

	// a freshly created artist must be named, or it would have no name at all
	$storeName = $createdArtist || !isset($datum['store_name']) || !empty($datum['store_name']);

	if (!$storeName) {
		$matchedRow = $db->query("SELECT name FROM artist_alias WHERE artist_id = ? ORDER BY is_actual DESC LIMIT 1", [$id])->fetch();
		$matchedName = $matchedRow ? $matchedRow['name'] : $aliasName;
		$results[] = [
			'provided_name' => $providedName,
			'artist_id' => (int)$id,
			'artist_name' => $matchedName,
			'status' => 'duplicate',
			'message' => t('alias.matchedOnly', ['artist' => $matchedName]),
		];
		continue;
	}

	$existingStmt = $db->query("SELECT id, is_actual FROM artist_alias WHERE artist_id = ? AND name = ?", [$id, $aliasName]);
	$existing = $existingStmt ? $existingStmt->fetch() : false;

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
		$outcome = $createdArtist ? 'createdArtist' : 'added';
	}

	// the artist this row landed on, so the message can name it
	$artistNameRow = $db->query("SELECT name FROM artist_alias WHERE artist_id = ? ORDER BY is_actual DESC LIMIT 1", [$id])->fetch();
	$artistName = $artistNameRow ? $artistNameRow['name'] : $aliasName;

	$message = t('alias.' . $outcome, ['name' => $aliasName, 'artist' => $artistName]);

	// also store the pasted spelling as an alias
	if (!empty($datum['also_alias_provided_name']) && $providedName !== '' && $providedName !== $aliasName) {
		$secondExistingStmt = $db->query("SELECT id FROM artist_alias WHERE artist_id = ? AND name = ?", [$id, $providedName]);
		$secondExisting = $secondExistingStmt ? $secondExistingStmt->fetch() : false;
		if (!$secondExisting) {
			$db->query("INSERT INTO artist_alias (artist_id, name, is_actual) VALUES (?, ?, 0)", [$id, $providedName]);
			$message .= t('alias.alsoAliased', ['name' => $providedName]);
		}
	}

	// the resolved name, since an artist created just now is not in the page's list
	$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$id, 'artist_name' => $artistName, 'status' => $status, 'message' => $message];
}

// the song form needs each artist's existing songs to offer as alias targets
$songsByArtist = [];
foreach ($results as $index => $result) {
	$artistId = $result['artist_id'] ?? null;
	if (!$artistId) {
		continue;
	}

	if (!isset($songsByArtist[$artistId])) {
		// every alias, not just the actual name, so a pasted spelling can match one
		$songsByArtist[$artistId] = $db->query("
			SELECT s.id, sa.name, sa.is_actual
			FROM song s
			JOIN song_alias sa ON sa.song_id = s.id
			WHERE s.artist_id = ?
			ORDER BY sa.name COLLATE NOCASE
		", [$artistId])->fetchAll();
	}

	$results[$index]['songs'] = $songsByArtist[$artistId];
}

echo json_encode($results);
