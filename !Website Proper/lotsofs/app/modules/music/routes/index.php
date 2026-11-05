<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

loadStringCatalogue('music');

$pageTitle = t("music.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

require_once __MODULES__ . '/music/homeLinks.php';

$filterOptions = musicHomeFilterOptions($db);
$sortOptions = musicHomeSortOptions($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!$globalData['isAdmin']) {
		header('Location: /music', true, 302);
		exit;
	}

	if (checkCsrf($_POST['csrf_token'] ?? null)) {
		$action = $_POST['action'] ?? '';
		$linkId = ctype_digit($_POST['link_id'] ?? '') ? (int)$_POST['link_id'] : 0;

		if ($action === 'add' || $action === 'save') {
			$filter = musicHomeFilterFrom($filterOptions, $_POST['filter'] ?? null);
			$sort = $_POST['sort'] ?? null;
			$dir = $_POST['dir'] ?? null;
			$label = trim(is_string($_POST['label'] ?? null) ? $_POST['label'] : '');
			$note = trim(is_string($_POST['note'] ?? null) ? $_POST['note'] : '');

			/// Only a tampered form sends any of these, so it writes nothing rather than explaining.
			if ($filter !== null && is_string($sort) && isset($sortOptions[$sort]) && in_array($dir, MUSIC_HOME_LINK_DIRS, true)) {
				$values = [$label === '' ? null : $label, $note === '' ? null : $note, $filter[0], $filter[1], $sort, $dir];

				if ($action === 'add') {
					$position = (int)$db->query("SELECT COALESCE(MAX(position), 0) p FROM home_link")->fetch()['p'] + 1;
					$db->query("INSERT INTO home_link (label, note, artist_id, album_id, sort, dir, position) VALUES (?, ?, ?, ?, ?, ?, ?)", [...$values, $position]);
				}
				else if ($linkId !== 0) {
					$db->query("UPDATE home_link SET label = ?, note = ?, artist_id = ?, album_id = ?, sort = ?, dir = ? WHERE id = ?", [...$values, $linkId]);
				}
			}
		}
		else if ($action === 'up' || $action === 'down') {
			$links = musicHomeLinks($db);
			$index = array_search($linkId, array_map('intval', array_column($links, 'id')), true);
			$otherIndex = $index === false ? null : ($action === 'up' ? $index - 1 : $index + 1);

			/// Renumbered whole, so two links that share a position still swap.
			if ($otherIndex !== null && isset($links[$otherIndex])) {
				[$links[$index], $links[$otherIndex]] = [$links[$otherIndex], $links[$index]];

				$db->pdo->beginTransaction();
				foreach ($links as $position => $link) {
					$db->query("UPDATE home_link SET position = ? WHERE id = ?", [$position + 1, $link['id']]);
				}
				$db->pdo->commit();
			}
		}
		else if ($action === 'delete' && $linkId !== 0) {
			$db->query("DELETE FROM home_link WHERE id = ?", [$linkId]);
		}
	}

	header('Location: /music?edit=1', true, 302);
	exit;
}

require_once __MODULES__ . '/music/ratings.php';
musicRatingView($db);

$songRatings = musicHomeSongRatings($db, currentAccountId());
$accountCount = (int)$db->query("SELECT COUNT(*) c FROM account")->fetch()['c'];

$globalData['links'] = [];
foreach (musicHomeLinks($db) as $link) {
	$globalData['links'][] = $link + [
		'href' => musicHomeLinkHref($link, $filterOptions),
		'text' => musicHomeLinkText($link, $filterOptions, $sortOptions),
		'stats' => musicHomeLinkStats(musicHomeLinkCounts($link, $filterOptions, $songRatings, $accountCount)),
		'fallback' => musicHomeLinkText(['label' => null] + $link, $filterOptions, $sortOptions),
		'filter' => musicHomeFilterValue($link),
	];
}

/// The picker's filters, each album named as the song list heads it, so by artist.
$filterChoices = ['artists' => [], 'albums' => []];
if ($globalData['isAdmin']) {
	foreach ($filterOptions['artists'] as $id => $name) {
		$filterChoices['artists']["artist:{$id}"] = $name;
	}
	foreach ($filterOptions['albums'] as $id => $album) {
		$filterChoices['albums']["album:{$id}"] = musicHomeFilterHeading($filterOptions, null, $id);
	}
	uasort($filterChoices['albums'], 'strcasecmp');
}

$globalData['filterChoices'] = $filterChoices;
$globalData['sortOptions'] = $sortOptions;
$globalData['editing'] = isset($_GET['edit']);

require __MODULES__ . "/music/views/index.view.php";
