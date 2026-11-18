<?php

require_once __MODULES__ . '/games/ajaxGuard.php';
requireGamesAccountJson($db, t('ajax.notLoggedIn'));

require_once __MODULES__ . '/games/sims2.php';

$key = ajaxText($data['key'] ?? null);
$done = $data['done'] ?? null;

if (!is_bool($done)) {
	http_response_code(400);
	echo json_encode(['error' => t('ajax.badBody')]);
	exit;
}

/// Only items the lists hold now, so the table never fills with keys nothing shows.
if (!isset(gamesSims2Keys(gamesSims2WithVehicles(gamesSims2Lists(), gamesSims2Vehicles($db)))[$key])) {
	echo json_encode(['status' => 'error', 'message' => t('sims2.unknownItem')]);
	exit;
}

$accountId = (int)currentAccountId();

if ($done) {
	$db->query(
		"INSERT INTO sims2_check (account_id, item_key, checked_at) VALUES (?, ?, ?)
		ON CONFLICT (account_id, item_key) DO NOTHING",
		[$accountId, $key, time()]
	);
}
else {
	$db->query("DELETE FROM sims2_check WHERE account_id = ? AND item_key = ?", [$accountId, $key]);
}

echo json_encode(['status' => 'ok', 'done' => $done]);
