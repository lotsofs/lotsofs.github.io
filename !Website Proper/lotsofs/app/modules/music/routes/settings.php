<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/blindRating.php';
require_once __MODULES__ . '/music/toastDuration.php';

/* The two fields read "missing" differently. An unchecked box is absent from
   the body rather than present and invalid, so a missing blind_rating has to
   mean off. The select always sends a value, so a missing or unknown
   toast_seconds is a post that did not come from the menu and leaves the
   choice alone, as the colour and language forms do. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$blind = isset($_POST['blind_rating']) ? '1' : '0';
	$toastSeconds = musicReadToastSeconds($_POST['toast_seconds'] ?? null);

	rememberModulePreference('blind', 'music', $blind);
	if ($toastSeconds !== null) {
		rememberModulePreference('toast', 'music', $toastSeconds);
	}

	$accountId = currentAccountId();
	if ($accountId) {
		$db->query("UPDATE account SET blind_rating = ? WHERE id = ?", [(int)$blind, $accountId]);

		if ($toastSeconds !== null) {
			$db->query("UPDATE account SET toast_seconds = ? WHERE id = ?", [$toastSeconds, $accountId]);
		}
	}
}

$return = $_POST['return'] ?? '/music';
if (!preg_match('#^/music(/[a-z-]+)?$#', $return)) {
	$return = '/music';
}

header('Location: ' . $return, true, 303);
exit;
