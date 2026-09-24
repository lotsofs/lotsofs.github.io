<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/hue.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$picked = musicReadHue($_POST['hue'] ?? null);

	if ($picked !== null) {
		$_SESSION['hue'] = $picked;

		$accountId = currentAccountId();
		if ($accountId) {
			$db->query("UPDATE account SET hue = ? WHERE id = ?", [$picked, $accountId]);
		}
	}
}

$return = $_POST['return'] ?? '/music';
if (!preg_match('#^/music(/[a-z-]+)?$#', $return)) {
	$return = '/music';
}

header('Location: ' . $return, true, 303);
exit;
