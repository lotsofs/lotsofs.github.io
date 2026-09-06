<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ajax/ajax.php';

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
		$results[] = ['provided_name' => $providedName, 'artist_id' => null, 'status' => 'skipped', 'message' => 'Skipped'];
		continue;
	}

	if ($aliasName === '') {
		$results[] = ['provided_name' => $providedName, 'artist_id' => null, 'status' => 'error', 'message' => 'Artist name is required'];
		continue;
	}

	if ($rawId === ARTIST_ID_NEW) {
		$groupKey = trim($datum['group'] ?? '') ?: $providedName;
		if (isset($groupArtistIds[$groupKey])) {
			$id = $groupArtistIds[$groupKey];
		}
		else {
			$db->query("INSERT INTO artist DEFAULT VALUES");
			$id = $db->pdo->lastInsertId();
			$groupArtistIds[$groupKey] = $id;
		}
	}
	else {
		$id = $rawId;
	}

	$existingStmt = $db->query("SELECT id, is_actual FROM artist_alias WHERE artist_id = ? AND name = ?", [$id, $aliasName]);
	$existing = $existingStmt ? $existingStmt->fetch() : false;

	if ($existing) {
		if ($isActual && !$existing['is_actual']) {
			$db->query("UPDATE artist_alias SET is_actual = 0 WHERE artist_id = ? AND id != ?", [$id, $existing['id']]);
			$db->query("UPDATE artist_alias SET is_actual = 1 WHERE id = ?", [$existing['id']]);
			$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$id, 'status' => 'ok', 'message' => "Marked \"{$aliasName}\" as the actual name"];
		}
		else {
			$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$id, 'status' => 'duplicate', 'message' => 'Alias already existed'];
		}
	}
	else {
		if ($isActual) {
			$db->query("UPDATE artist_alias SET is_actual = 0 WHERE artist_id = ?", [$id]);
		}
		$db->query("INSERT INTO artist_alias (artist_id, name, is_actual) VALUES (?, ?, ?)", [$id, $aliasName, $isActual ? 1 : 0]);
		$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$id, 'status' => 'ok', 'message' => $isActual ? "Added as \"{$aliasName}\" (actual name)" : "Added as \"{$aliasName}\""];
	}

	if (!empty($datum['also_alias_provided_name']) && $providedName !== '' && $providedName !== $aliasName) {
		$secondExistingStmt = $db->query("SELECT id FROM artist_alias WHERE artist_id = ? AND name = ?", [$id, $providedName]);
		$secondExisting = $secondExistingStmt ? $secondExistingStmt->fetch() : false;
		if (!$secondExisting) {
			$db->query("INSERT INTO artist_alias (artist_id, name, is_actual) VALUES (?, ?, 0)", [$id, $providedName]);
			$results[count($results) - 1]['message'] .= " (also aliased as \"{$providedName}\")";
		}
	}
}

echo json_encode($results);
