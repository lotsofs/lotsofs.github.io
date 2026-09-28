<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$artistId = ajaxInt($data['artist_id'] ?? null);
$field = ajaxText($data['field'] ?? null);
$value = ajaxTrimmed($data['value'] ?? null);

if (!$db->query("SELECT id FROM artist WHERE id = ?", [$artistId])->fetch()) {
	echo json_encode(['status' => 'error', 'message' => t('artist.card.notFound')]);
	exit;
}

if ($field !== 'name') {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown field']);
	exit;
}

if ($value === '') {
	echo json_encode(['status' => 'error', 'message' => t('artist.result.nameRequired')]);
	exit;
}

/// Renaming promotes an alias, keeping the old name. Clear before set: one is_actual row per artist.
$existing = $db->query("SELECT id FROM artist_alias WHERE artist_id = ? AND name = ?", [$artistId, $value])->fetch();

$db->pdo->beginTransaction();
try {
	$db->query("UPDATE artist_alias SET is_actual = 0 WHERE artist_id = ?", [$artistId]);

	if ($existing) {
		$db->query("UPDATE artist_alias SET is_actual = 1 WHERE id = ?", [$existing['id']]);
	}
	else {
		$db->query("INSERT INTO artist_alias (artist_id, name, is_actual) VALUES (?, ?, 1)", [$artistId, $value]);
	}

	$db->pdo->commit();
}
catch (PDOException $e) {
	$db->pdo->rollBack();
	throw $e;
}

echo json_encode(['status' => 'ok', 'value' => $value, 'message' => '']);
