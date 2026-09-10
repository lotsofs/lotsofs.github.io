<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$picked = in_array($_POST['lang'] ?? '', AVAILABLE_LOCALES, true) ? $_POST['lang'] : 'en';

	$_SESSION['lang'] = $picked;

	$accountId = currentAccountId();
	if ($accountId) {
		$db->query("UPDATE account SET lang = ? WHERE id = ?", [$picked, $accountId]);
	}
}

$return = $_POST['return'] ?? '/music';
if (!preg_match('#^/music(/[a-z-]+)?$#', $return)) {
	$return = '/music';
}

header('Location: ' . $return, true, 303);
exit;
