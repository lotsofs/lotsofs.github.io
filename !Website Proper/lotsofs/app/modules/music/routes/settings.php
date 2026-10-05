<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/blindRating.php';

/* Unlike the colour and language forms, a post here is authoritative for both
   values: an unchecked box is absent from the body rather than present and
   invalid, so "missing" has to mean off instead of leaving the choice alone. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$blind = isset($_POST['blind_rating']) ? '1' : '0';

	rememberModulePreference('blind', 'music', $blind);

	$accountId = currentAccountId();
	if ($accountId) {
		$db->query("UPDATE account SET blind_rating = ? WHERE id = ?", [(int)$blind, $accountId]);
	}
}

$return = $_POST['return'] ?? '/music';
if (!preg_match('#^/music(/[a-z-]+)?$#', $return)) {
	$return = '/music';
}

header('Location: ' . $return, true, 303);
exit;
