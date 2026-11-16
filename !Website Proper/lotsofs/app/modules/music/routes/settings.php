<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/blindRating.php';
require_once __MODULES__ . '/music/toastDuration.php';

require_once __MODULES__ . '/music/embeds.php';

/* The fields read "missing" differently. An unchecked box is absent from the
   body rather than present and invalid, so a missing blind_rating or embeds has
   to mean off. The select always sends a value, so a missing or unknown
   toast_seconds is a post that did not come from the menu and leaves the
   choice alone, as the colour and language forms do. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$blind = isset($_POST['blind_rating']) ? '1' : '0';
	$embeds = isset($_POST['embeds']) ? '1' : '0';
	$toastSeconds = musicReadToastSeconds($_POST['toast_seconds'] ?? null);

	rememberModulePreference('blind', 'music', $blind);
	rememberModulePreference('embeds', 'music', $embeds);
	if ($toastSeconds !== null) {
		rememberModulePreference('toast', 'music', $toastSeconds);
	}

	$accountId = currentAccountId();
	if ($accountId) {
		$db->query("UPDATE account SET blind_rating = ?, embeds = ? WHERE id = ?", [(int)$blind, (int)$embeds, $accountId]);

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
