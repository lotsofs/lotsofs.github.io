<?php

require_once __ROOT__ . '/session.php';
sessionScope('hideandseek');
requireLogin('/hideandseek/login');

loadStringCatalogue('hideandseek');

$pageTitle = t('poiImport.title');

$db = require __MODULES__ . '/hideandseek/db.php';

require_once __MODULES__ . '/hideandseek/migrate.php';
runHideAndSeekMigrations($db);

require_once __MODULES__ . '/hideandseek/auth.php';
require_once __MODULES__ . '/hideandseek/gameMap.php';
require_once __MODULES__ . '/hideandseek/poiImport.php';
requireHnsAccount($db);

$globalData['isAdmin'] = hnsIsAdmin($db);
$globalData['formError'] = '';
$globalData['categoryEdits'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$action = $_POST['action'] ?? '';
	$mapId = ctype_digit($_POST['map_id'] ?? '') ? (int)$_POST['map_id'] : 0;
	$categoryId = ctype_digit($_POST['category_id'] ?? '') ? (int)$_POST['category_id'] : 0;

	if ($action === 'delete') {
		$db->query("DELETE FROM poi_category WHERE id = ? AND game_map_id = ?", [$categoryId, $mapId]);
	}
	else if ($action === 'styles') {
		$only = ctype_digit($_POST['only'] ?? '') ? (int)$_POST['only'] : 0;
		$globalData['formError'] = hnsSaveCategoryEdits($db, $mapId, $_POST['categories'] ?? [], $only);

		if ($globalData['formError'] !== '' && is_array($_POST['categories'] ?? null)) {
			$globalData['categoryEdits'] = $_POST['categories'];
		}
	}

	if ($globalData['formError'] === '') {
		header('Location: /hideandseek/import-pois', true, 302);
		exit;
	}
}

$loaded = hnsLoadedGameMap($db);
$globalData['loadedGameMap'] = $loaded;
$globalData['categories'] = [];
$globalData['importResult'] = null;

if ($loaded) {
	$globalData['categories'] = $db->query(
		"SELECT c.id, c.name, c.colour, c.icon, COUNT(p.id) AS poi_count
		FROM poi_category c
		LEFT JOIN poi p ON p.poi_category_id = c.id
		WHERE c.game_map_id = ?
		GROUP BY c.id
		ORDER BY c.name COLLATE NOCASE",
		[$loaded['id']]
	)->fetchAll();

	$resultCategoryId = ctype_digit($_GET['category'] ?? '') ? (int)$_GET['category'] : 0;

	if (ctype_digit($_GET['imported'] ?? '')) {
		$result = [
			'category' => null,
			'categories' => ctype_digit($_GET['categories'] ?? '') ? (int)$_GET['categories'] : 0,
			'imported' => (int)$_GET['imported'],
			'skipped' => ctype_digit($_GET['skipped'] ?? '') ? (int)$_GET['skipped'] : 0,
		];

		foreach ($globalData['categories'] as $category) {
			if ((int)$category['id'] === $resultCategoryId) {
				$result['category'] = $category['name'];
			}
		}

		if ($result['category'] !== null || $result['categories'] > 0) {
			$globalData['importResult'] = $result;
		}
	}
}

require __MODULES__ . '/hideandseek/views/importPois.view.php';
