<?php

require_once __ROOT__ . '/session.php';
sessionScope('games');
requireLogin('/games/login');

loadStringCatalogue('games');

$pageTitle = t('aoe2.title');

$db = require __MODULES__ . '/games/db.php';

require_once __MODULES__ . '/games/migrate.php';
runGamesMigrations($db);

require_once __MODULES__ . '/games/auth.php';
requireGamesAccount($db);

$globalData['isAdmin'] = gamesIsAdmin($db);

require_once __MODULES__ . '/games/aoe2.php';

$loaded = gamesAoe2LoadLive();
$accountId = (int)currentAccountId();

/// The filter form posts, and what it posts is saved to the account (the
/// owner's wish) and shown on the redirect back. Only what the sheet offers is
/// kept, so the filters have to be known first.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if ($loaded !== null && checkCsrf($_POST['csrf_token'] ?? null)) {
		/// The to-scale switch rides along in the same row, and the folded and
		/// unfolded places, which the form does not send, are kept as they were;
		/// the selection only reads filter keys, so it never mistakes one for them.
		$saved = gamesAoe2Settings($db, $accountId);
		$posted = gamesAoe2Selection(gamesAoe2Filters($loaded['scenarios'], $loaded['packs']), $_POST)
			+ ['scale' => ($_POST['scale'] ?? '') === '1'];
		foreach (GAMES_AOE2_FOLD_LISTS as $list) {
			$posted[$list] = $saved[$list] ?? [];
		}
		gamesAoe2SaveSettings($db, $accountId, $posted);
	}

	header('Location: /games/aoe2', true, 303);
	exit;
}

$globalData['loaded'] = $loaded;
$globalData['filters'] = [];
$globalData['selection'] = [];
$globalData['rows'] = [];
$globalData['lanes'] = ['width' => 0, 'rows' => []];
$globalData['checked'] = [];
$globalData['entries'] = [];
$globalData['outOfPlace'] = [];
$globalData['scale'] = false;
$globalData['collapsed'] = [];
$globalData['expanded'] = [];
$globalData['steps'] = [];
$globalData['presentStep'] = null;

if ($loaded !== null) {
	$scenarios = $loaded['scenarios'];
	$hues = gamesAoe2Hues($scenarios);

	/// The ticks come first: a DLC holding a ticked scenario is on by default.
	$keys = $db->query("SELECT scenario_key FROM aoe2_check WHERE account_id = ?", [$accountId])->fetchAll();
	$globalData['checked'] = array_fill_keys(array_column($keys, 'scenario_key'), true);
	$globalData['filters'] = gamesAoe2Filters($scenarios, $loaded['packs'], $globalData['checked']);
	$entries = $db->query("SELECT scenario_key, descriptor, rating, note, youtube FROM aoe2_entry WHERE account_id = ?", [$accountId])->fetchAll();
	$globalData['entries'] = array_column($entries, null, 'scenario_key');

	/// An address that names filters shows exactly those, without saving them,
	/// so a link can be followed without losing anything; one that names none
	/// shows what the account saved. A filter added since that save was made is
	/// missing from it, and so takes its default. The to-scale switch and the
	/// folded and unfolded places are always the saved ones.
	$decoded = gamesAoe2Settings($db, $accountId);
	$globalData['scale'] = ($decoded['scale'] ?? false) === true;
	foreach (GAMES_AOE2_FOLD_LISTS as $list) {
		$globalData[$list] = array_fill_keys(array_filter((array)($decoded[$list] ?? []), 'is_string'), true);
	}

	$query = $_GET;
	if (!array_intersect_key($query, $globalData['filters']) && $decoded) {
		$query = $decoded;
	}
	$globalData['selection'] = gamesAoe2Selection($globalData['filters'], $query);
	$globalData['rows'] = array_map(function ($row) use ($loaded, $hues) {
		$colour = $loaded['colours'][$row['key']] ?? gamesAoe2FallbackColour($hues[$row['campaignKey']]);
		return $row + ['colour' => $colour, 'marker' => gamesAoe2MarkerColour($colour), 'ink' => gamesAoe2RowInk($colour)];
	}, gamesAoe2Filtered($scenarios, $globalData['filters'], $globalData['selection']));
	if ($globalData['scale']) {
		$globalData['rows'] = gamesAoe2UndatedLast($globalData['rows']);
	}
	/// To scale, one row per year: a year of several scenarios is one step.
	$globalData['steps'] = $globalData['scale']
		? gamesAoe2YearSteps($globalData['rows'])
		: array_map(fn($i) => [$i], array_keys($globalData['rows']));
	$globalData['lanes'] = gamesAoe2Lanes($globalData['rows'], $globalData['steps']);
	$globalData['presentStep'] = gamesAoe2PresentStep($globalData['rows'], $globalData['steps']);
	$globalData['outOfPlace'] = gamesAoe2OutOfPlace(array_column($globalData['rows'], 'year'));
}

require __MODULES__ . '/games/views/aoe2.view.php';
