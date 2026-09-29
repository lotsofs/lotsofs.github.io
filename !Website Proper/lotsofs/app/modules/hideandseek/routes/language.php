<?php

require_once __ROOT__ . '/session.php';
sessionScope('hideandseek');

$db = require __MODULES__ . '/hideandseek/db.php';

require_once __MODULES__ . '/hideandseek/migrate.php';
runHideAndSeekMigrations($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	/// A locale the module doesn't ship leaves the stored choice alone.
	$picked = in_array($_POST['lang'] ?? '', moduleLocales('hideandseek'), true) ? $_POST['lang'] : null;

	if ($picked !== null) {
		rememberLocale('hideandseek', $picked);

		$accountId = currentAccountId();
		if ($accountId) {
			$db->query("UPDATE account SET lang = ? WHERE id = ?", [$picked, $accountId]);
		}
	}
}

$return = $_POST['return'] ?? '/hideandseek';
if (!preg_match('#^/hideandseek(/[a-z-]+)?$#', $return)) {
	$return = '/hideandseek';
}

header('Location: ' . $return, true, 303);
exit;
