<?php

const HNS_EXPORT_FORMAT = 'hideandseek-pois';

function hnsValidPoi($raw) {
	if (!is_array($raw)) {
		return null;
	}

	$type = $raw['osmType'] ?? null;
	$id = $raw['osmId'] ?? null;
	$name = $raw['name'] ?? null;
	$lat = $raw['lat'] ?? null;
	$lon = $raw['lon'] ?? null;

	if (!in_array($type, ['node', 'way', 'relation'], true)) {
		return null;
	}

	if (!(is_int($id) && $id > 0) && !(is_string($id) && ctype_digit($id) && (int)$id > 0)) {
		return null;
	}

	if ($name !== null && !is_string($name)) {
		return null;
	}

	if (!(is_int($lat) || is_float($lat)) || !(is_int($lon) || is_float($lon))) {
		return null;
	}

	if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
		return null;
	}

	$name = $name === null ? '' : trim($name);

	return [
		'osmType' => $type,
		'osmId' => (int)$id,
		'name' => $name === '' ? null : $name,
		'lat' => (float)$lat,
		'lon' => (float)$lon,
	];
}

function hnsPoiColour($raw) {
	if ($raw === '' || $raw === null) {
		return '';
	}

	return is_string($raw) && preg_match('/^#[0-9a-fA-F]{6}$/', $raw) ? strtolower($raw) : null;
}

function hnsPoiIcon($raw) {
	if ($raw === null) {
		return '';
	}

	if (!is_string($raw)) {
		return null;
	}

	$icon = trim($raw);

	if ($icon === '') {
		return '';
	}

	return strlen($icon) <= 32 && preg_match('/^\X$/u', $icon) ? $icon : null;
}

function hnsSaveCategoryEdits($db, $gameMapId, $rows, $only) {
	$current = [];
	foreach ($db->query("SELECT id, name FROM poi_category WHERE game_map_id = ?", [$gameMapId])->fetchAll() as $row) {
		$current[(int)$row['id']] = $row['name'];
	}

	$edits = [];

	foreach (is_array($rows) ? $rows : [] as $id => $row) {
		$id = (int)$id;

		if (!isset($current[$id]) || ($only !== 0 && $id !== $only) || !is_array($row)) {
			continue;
		}

		$name = trim((string)($row['name'] ?? ''));
		$colour = hnsPoiColour($row['colour'] ?? '');
		$icon = hnsPoiIcon($row['icon'] ?? '');

		if ($name === '') {
			return t('poiImport.error.noName');
		}

		if ($colour === null) {
			return t('poiImport.error.badColour');
		}

		if ($icon === null) {
			return t('poiImport.error.badIcon');
		}

		$edits[$id] = ['name' => $name, 'colour' => $colour === '' ? null : $colour, 'icon' => $icon === '' ? null : $icon];
	}

	$final = $current;
	foreach ($edits as $id => $edit) {
		$final[$id] = $edit['name'];
	}

	$seen = [];
	foreach ($final as $name) {
		$key = strtolower($name);

		if (isset($seen[$key])) {
			return t('poiImport.error.nameTaken', ['name' => $name]);
		}

		$seen[$key] = true;
	}

	$db->pdo->beginTransaction();

	try {
		foreach ($edits as $id => $edit) {
			if ($edit['name'] !== $current[$id]) {
				$db->query("UPDATE poi_category SET name = ? WHERE id = ?", [' ' . $id, $id]);
			}
		}

		foreach ($edits as $id => $edit) {
			$db->query(
				"UPDATE poi_category SET name = ?, colour = ?, icon = ? WHERE id = ?",
				[$edit['name'], $edit['colour'], $edit['icon'], $id]
			);
		}

		$db->pdo->commit();
	}
	catch (Throwable $e) {
		$db->pdo->rollBack();
		throw $e;
	}

	return '';
}

function hnsSaveCategory($db, $gameMapId, $categoryName, $colour, $icon, $pois) {
	$category = $db->query(
		"SELECT id FROM poi_category WHERE game_map_id = ? AND name = ? COLLATE NOCASE",
		[$gameMapId, $categoryName]
	)->fetch();

	$colour = $colour === '' ? null : $colour;
	$icon = $icon === '' ? null : $icon;

	if ($category) {
		$categoryId = (int)$category['id'];
		$db->query("UPDATE poi_category SET colour = ?, icon = ? WHERE id = ?", [$colour, $icon, $categoryId]);
	}
	else {
		$db->query(
			"INSERT INTO poi_category (game_map_id, name, colour, icon) VALUES (?, ?, ?, ?)",
			[$gameMapId, $categoryName, $colour, $icon]
		);
		$categoryId = (int)$db->pdo->lastInsertId();
	}

	foreach ($pois as $poi) {
		$db->query(
			"INSERT INTO poi (poi_category_id, osm_type, osm_id, name, lat, lon) VALUES (?, ?, ?, ?, ?, ?)
			ON CONFLICT (poi_category_id, osm_type, osm_id) DO UPDATE SET name = excluded.name, lat = excluded.lat, lon = excluded.lon",
			[$categoryId, $poi['osmType'], $poi['osmId'], $poi['name'], $poi['lat'], $poi['lon']]
		);
	}

	return $categoryId;
}

function hnsImportCategories($db, $gameMapId, $categories) {
	$db->pdo->beginTransaction();

	try {
		$saved = [];

		foreach ($categories as $category) {
			$saved[] = [
				'id' => hnsSaveCategory($db, $gameMapId, $category['name'], $category['colour'], $category['icon'], $category['pois']),
				'name' => $category['name'],
				'imported' => count($category['pois']),
			];
		}

		$db->pdo->commit();
	}
	catch (Throwable $e) {
		$db->pdo->rollBack();
		throw $e;
	}

	return $saved;
}

function hnsExportPois($db, $gameMap) {
	$categories = $db->query(
		"SELECT id, name, colour, icon FROM poi_category WHERE game_map_id = ? ORDER BY name COLLATE NOCASE",
		[$gameMap['id']]
	)->fetchAll();

	$export = [
		'format' => HNS_EXPORT_FORMAT,
		'version' => 1,
		'map' => $gameMap['name'],
		'categories' => [],
	];

	foreach ($categories as $category) {
		$pois = $db->query(
			"SELECT osm_type, osm_id, name, lat, lon FROM poi WHERE poi_category_id = ? ORDER BY name COLLATE NOCASE, osm_type, osm_id",
			[$category['id']]
		)->fetchAll();

		if (!$pois) {
			continue;
		}

		$export['categories'][] = [
			'name' => $category['name'],
			'colour' => $category['colour'] ?? '',
			'icon' => $category['icon'] ?? '',
			'pois' => array_map(function ($poi) {
				return [
					'osmType' => $poi['osm_type'],
					'osmId' => (int)$poi['osm_id'],
					'name' => $poi['name'],
					'lat' => (float)$poi['lat'],
					'lon' => (float)$poi['lon'],
				];
			}, $pois),
		];
	}

	return $export;
}
