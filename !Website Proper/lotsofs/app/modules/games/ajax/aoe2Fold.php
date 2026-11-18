<?php

require_once __MODULES__ . '/games/ajaxGuard.php';
requireGamesAccountJson($db, t('ajax.notLoggedIn'));

require_once __MODULES__ . '/games/aoe2.php';

/// Puts places on the to-scale timeline on one of the account's fold lists, or
/// takes them off: stretches folded away, each named by the scenario after it,
/// or years unfolded, each named by its first scenario. One place is `key`;
/// the fold-all buttons send every place they change as `keys`, in one write.
$list = $data['list'] ?? null;
$keys = array_key_exists('keys', $data) ? $data['keys'] : [ajaxText($data['key'] ?? null)];
$on = $data['on'] ?? null;

if (!in_array($list, GAMES_AOE2_FOLD_LISTS, true) || !is_bool($on) || !is_array($keys) || !$keys
		|| array_filter($keys, fn($key) => !is_string($key))) {
	http_response_code(400);
	echo json_encode(['error' => t('ajax.badBody')]);
	exit;
}

/// Only names the sheet has, or the stretch to the present, which has a name
/// of its own when nothing follows it; one unknown name refuses them all.
$loaded = gamesAoe2LoadLive();
$known = $loaded === null ? [] : array_flip(array_column($loaded['scenarios'], 'key'));
foreach ($keys as $key) {
	if (!isset($known[$key]) && !($list === 'collapsed' && $key === GAMES_AOE2_PRESENT_KEY)) {
		echo json_encode(['status' => 'error', 'message' => t('aoe2.unknownScenario')]);
		exit;
	}
}

$accountId = (int)currentAccountId();
$changed = array_flip($keys);

$db->pdo->beginTransaction();
try {
	$settings = gamesAoe2Settings($db, $accountId);
	$kept = array_values(array_filter((array)($settings[$list] ?? []), fn($saved) => is_string($saved) && !isset($changed[$saved])));
	$settings[$list] = $on ? array_merge($kept, array_keys($changed)) : $kept;
	gamesAoe2SaveSettings($db, $accountId, $settings);
	$db->pdo->commit();
}
catch (Throwable $e) {
	$db->pdo->rollBack();
	throw $e;
}

echo json_encode(['status' => 'ok', 'on' => $on]);
