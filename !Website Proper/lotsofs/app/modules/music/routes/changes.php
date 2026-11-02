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
	$groups = [];

	foreach (MUSIC_CHANGE_GROUPS as $group) {
		if (empty($release['changes'][$group])) {
			continue;
		}

		$groups[] = [
			'label' => match ($group) {
				'action' => t('changes.group.action'),
				'added' => t('changes.group.added'),
				'changed' => t('changes.group.changed'),
				'fixed' => t('changes.group.fixed'),
				'removed' => t('changes.group.removed'),
			},
			'entries' => array_map(
				fn($entry) => musicChangeHtml(musicChangeText($entry, activeLocale())),
				$release['changes'][$group]
			),
		];
	}

	/// An undated release with nothing in it yet is not shown at all.
	if ($groups) {
		$globalData['releases'][] = [
			'heading' => $release['date'] ?? t('changes.latest'),
			'groups' => $groups,
		];
	}
}

require __MODULES__ . "/music/views/changes.view.php";
