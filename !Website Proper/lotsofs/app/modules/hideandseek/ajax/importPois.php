<?php

require_once __MODULES__ . '/hideandseek/ajaxGuard.php';
require_once __MODULES__ . '/hideandseek/poiImport.php';

requireHnsAccountJson($db, t('ajax.notLoggedIn'));

function hnsImportRefused($message) {
	http_response_code(400);
	echo json_encode(['error' => $message]);
	exit;
}

$mapId = ajaxInt($data['mapId'] ?? null);
$rawCategories = $data['categories'] ?? null;

if ($mapId === 0 || !$db->query("SELECT id FROM game_map WHERE id = ?", [$mapId])->fetch()) {
	hnsImportRefused(t('poiImport.error.mapGone'));
}

if (!is_array($rawCategories) || !array_is_list($rawCategories) || !$rawCategories) {
	hnsImportRefused(t('poiImport.error.nothingFound'));
}

$categories = [];

foreach ($rawCategories as $raw) {
	if (!is_array($raw)) {
		hnsImportRefused(t('poiImport.error.nothingFound'));
	}

	$name = ajaxTrimmed($raw['name'] ?? null);
	$colour = hnsPoiColour($raw['colour'] ?? null);
	$icon = hnsPoiIcon($raw['icon'] ?? null);
	$rawPois = $raw['pois'] ?? null;

	if ($name === '') {
		hnsImportRefused(t('poiImport.error.noCategory'));
	}

	if ($colour === null) {
		hnsImportRefused(t('poiImport.error.badColour'));
	}

	if ($icon === null) {
		hnsImportRefused(t('poiImport.error.badIcon'));
	}

	if (!is_array($rawPois) || !array_is_list($rawPois) || !$rawPois) {
		hnsImportRefused(t('poiImport.error.nothingFound'));
	}

	$pois = [];

	foreach ($rawPois as $rawPoi) {
		$poi = hnsValidPoi($rawPoi);

		if ($poi === null) {
			hnsImportRefused(t('poiImport.error.badPoi'));
		}

		$pois[$poi['osmType'] . '/' . $poi['osmId']] = $poi;
	}

	$categories[] = ['name' => $name, 'colour' => $colour, 'icon' => $icon, 'pois' => array_values($pois)];
}

$saved = hnsImportCategories($db, $mapId, $categories);

echo json_encode([
	'status' => 'ok',
	'imported' => array_sum(array_column($saved, 'imported')),
	'categories' => $saved,
]);
