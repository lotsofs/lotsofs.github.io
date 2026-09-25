<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	/// A locale the module doesn't ship leaves the choice alone, the way the
	/// colour route leaves an unparseable hue alone. This used to fall back to
	/// 'en' and *persist* it, so a stale tab posting a retired locale code
	/// permanently switched that account's language - and to a language that
	/// isn't even the module's default.
	$picked = in_array($_POST['lang'] ?? '', moduleLocales('music'), true) ? $_POST['lang'] : null;

	if ($picked !== null) {
		$_SESSION['lang'] = $picked;

		$accountId = currentAccountId();
		if ($accountId) {
			$db->query("UPDATE account SET lang = ? WHERE id = ?", [$picked, $accountId]);
		}
	}
}

$return = $_POST['return'] ?? '/music';
if (!preg_match('#^/music(/[a-z-]+)?$#', $return)) {
	$return = '/music';
}

header('Location: ' . $return, true, 303);
exit;
