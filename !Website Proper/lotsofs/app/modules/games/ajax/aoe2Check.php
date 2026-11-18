<?php

require_once __MODULES__ . '/games/ajaxGuard.php';
requireGamesAccountJson($db, t('ajax.notLoggedIn'));

require_once __MODULES__ . '/games/aoe2.php';

$key = ajaxText($data['key'] ?? null);
$done = $data['done'] ?? null;

if (!is_bool($done)) {
	http_response_code(400);
	echo json_encode(['error' => t('ajax.badBody')]);
	exit;
}

/// Only what the sheet holds now, so the table never fills with keys nothing shows.
$loaded = gamesAoe2LoadLive();
if ($loaded === null || !in_array($key, array_column($loaded['scenarios'], 'key'), true)) {
	echo json_encode(['status' => 'error', 'message' => t('aoe2.unknownScenario')]);
	exit;
}

$accountId = (int)currentAccountId();

if ($done) {
	$db->query(
		"INSERT INTO aoe2_check (account_id, scenario_key, checked_at) VALUES (?, ?, ?)
		ON CONFLICT (account_id, scenario_key) DO NOTHING",
		[$accountId, $key, time()]
	);
}
else {
	$db->query("DELETE FROM aoe2_check WHERE account_id = ? AND scenario_key = ?", [$accountId, $key]);
}

echo json_encode(['status' => 'ok', 'done' => $done]);
