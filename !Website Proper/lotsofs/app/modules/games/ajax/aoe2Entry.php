<?php

require_once __MODULES__ . '/games/ajaxGuard.php';
requireGamesAccountJson($db, t('ajax.notLoggedIn'));

require_once __MODULES__ . '/games/aoe2.php';

/// Writes one field of what the account says about a scenario: its
/// descriptor, rating, note or YouTube link. Empty clears it, and a scenario with nothing
/// left loses its row.
$key = ajaxText($data['key'] ?? null);
$field = $data['field'] ?? null;
$value = $data['value'] ?? null;

if (!is_string($field) || !isset(GAMES_AOE2_ENTRY_FIELDS[$field]) || !is_string($value)) {
	http_response_code(400);
	echo json_encode(['error' => t('ajax.badBody')]);
	exit;
}

/// Only what the sheet holds now, as for a tick.
$loaded = gamesAoe2LoadLive();
if ($loaded === null || !in_array($key, array_column($loaded['scenarios'], 'key'), true)) {
	echo json_encode(['status' => 'error', 'message' => t('aoe2.unknownScenario')]);
	exit;
}

try {
	$stored = gamesAoe2EntryValue($field, $value);
}
catch (InvalidArgumentException $e) {
	echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
	exit;
}

$accountId = (int)currentAccountId();

$db->pdo->beginTransaction();
try {
	$db->query(
		"INSERT INTO aoe2_entry (account_id, scenario_key, $field, updated_at) VALUES (?, ?, ?, ?)
		ON CONFLICT (account_id, scenario_key) DO UPDATE SET $field = excluded.$field, updated_at = excluded.updated_at",
		[$accountId, $key, $stored, time()]
	);
	$db->query(
		"DELETE FROM aoe2_entry WHERE account_id = ? AND scenario_key = ? AND descriptor IS NULL AND rating IS NULL AND note IS NULL AND youtube IS NULL",
		[$accountId, $key]
	);
	$db->pdo->commit();
}
catch (Throwable $e) {
	$db->pdo->rollBack();
	throw $e;
}

echo json_encode(['status' => 'ok', 'value' => gamesAoe2EntryText($field, $stored)]);
