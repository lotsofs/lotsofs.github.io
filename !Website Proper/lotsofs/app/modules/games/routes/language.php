<?php

require_once __ROOT__ . '/session.php';
sessionScope('games');

$db = require __MODULES__ . '/games/db.php';

require_once __MODULES__ . '/games/migrate.php';
runGamesMigrations($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	/// A locale the module doesn't ship leaves the stored choice alone.
	$picked = in_array($_POST['lang'] ?? '', moduleLocales('games'), true) ? $_POST['lang'] : null;

	if ($picked !== null) {
		rememberLocale('games', $picked);

		$accountId = currentAccountId();
		if ($accountId) {
			$db->query("UPDATE account SET lang = ? WHERE id = ?", [$picked, $accountId]);
		}
	}
}

$return = $_POST['return'] ?? '/games';
if (!preg_match('#^/games(/[a-z-]+)?$#', $return)) {
	$return = '/games';
}

header('Location: ' . $return, true, 303);
exit;
