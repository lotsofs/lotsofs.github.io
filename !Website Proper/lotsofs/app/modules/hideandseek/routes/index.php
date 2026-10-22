<?php

require_once __ROOT__ . '/session.php';
sessionScope('hideandseek');

loadStringCatalogue('hideandseek');

$pageTitle = t('hideandseek.title');

$db = require __MODULES__ . '/hideandseek/db.php';

require_once __MODULES__ . '/hideandseek/migrate.php';
runHideAndSeekMigrations($db);

require_once __MODULES__ . '/hideandseek/auth.php';
requireHnsAccount($db);

$globalData['isAdmin'] = hnsIsAdmin($db);

$globalData['imported'] = ['categories' => [], 'pois' => []];
$globalData['hasBuurten'] = false;

if (currentAccountId()) {
	require_once __MODULES__ . '/hideandseek/gameMap.php';
	require_once __MODULES__ . '/hideandseek/geo.php';
	$loaded = hnsLoadedGameMap($db);

	if ($loaded) {
		$geo = $loaded['gemeente_code'] !== null ? hnsGeoDb() : null;

		/// Only whether there are any: the buurten themselves are megabytes, so map.js
		/// fetches them when the map loads rather than every page view carrying them.
		$globalData['hasBuurten'] = $geo && hnsGeoGemeenteExists($geo, $loaded['gemeente_code']);

		$categories = $db->query(
			"SELECT id, name, colour, icon FROM poi_category WHERE game_map_id = ? ORDER BY name COLLATE NOCASE",
			[$loaded['id']]
		)->fetchAll();

		foreach ($categories as $category) {
			$globalData['imported']['categories'][] = [
				'id' => (int)$category['id'],
				'name' => $category['name'],
				'colour' => $category['colour'],
				'icon' => $category['icon'],
			];
		}

		$pois = $db->query(
			"SELECT p.poi_category_id, p.name, p.lat, p.lon
			FROM poi p
			JOIN poi_category c ON c.id = p.poi_category_id
			WHERE c.game_map_id = ?
			ORDER BY p.name COLLATE NOCASE",
			[$loaded['id']]
		)->fetchAll();

		foreach ($pois as $poi) {
			$globalData['imported']['pois'][] = [
				'category' => (int)$poi['poi_category_id'],
				'name' => $poi['name'] ?? '',
				'lat' => (float)$poi['lat'],
				'lng' => (float)$poi['lon'],
			];
		}
	}
}

/// Where the map opens, until there is a game to centre it on. Read off the
/// container by map.js rather than hardcoded in the script.
$globalData['mapCentre'] = ['lat' => 53.2194, 'lng' => 6.5665, 'zoom' => 13];

require __MODULES__ . '/hideandseek/views/index.view.php';
