<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

loadStringCatalogue('music');

$pageTitle = t("changes.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

require_once __MODULES__ . '/music/changes.php';

$globalData['releases'] = [];

foreach (musicChangelog() as $release) {
	$entries = musicChangeEntries($release, false);
	$adminEntries = musicChangeEntries($release, true);

	/// An undated release with nothing in it yet is not shown at all.
	if ($entries || $adminEntries) {
		$globalData['releases'][] = [
			'heading' => $release['date'] ?? t('changes.latest'),
			'entries' => $entries,
			'adminEntries' => $adminEntries,
		];
	}
}

require __MODULES__ . "/music/views/changes.view.php";
