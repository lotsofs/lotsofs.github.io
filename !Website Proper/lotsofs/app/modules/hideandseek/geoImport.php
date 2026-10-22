<?php

/// The build side of hideandseek_geo.sqlite: schema, validation, storing a PDOK
/// feature, and working out which gemeenten touch. Only tools/import-buurten.php
/// and the tests use it; the site reads the file through geo.php.

const HNS_GEO_SCALE = 1000000;

function hnsGeoCreateSchema($geo) {
	$geo->execSQL(file_get_contents(__DIR__ . '/database/geo.sql'));
}

function hnsValidCbsCode($raw) {
	return is_string($raw) && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $raw) ? $raw : null;
}

function hnsValidCbsName($raw) {
	if (!is_string($raw)) {
		return null;
	}

	$name = trim($raw);

	return $name !== '' && strlen($name) <= 200 ? $name : null;
}

/// [lon, lat] rounded to six decimals (about 11 cm), or null. PDOK's 2026 data
/// carries a Z on every position, which is dropped here.
function hnsGeoPosition($raw) {
	if (!is_array($raw) || !array_is_list($raw) || count($raw) < 2) {
		return null;
	}

	[$lon, $lat] = $raw;

	if (!(is_int($lon) || is_float($lon)) || !(is_int($lat) || is_float($lat))) {
		return null;
	}

	if (!is_finite($lon) || !is_finite($lat) || $lon < -180 || $lon > 180 || $lat < -90 || $lat > 90) {
		return null;
	}

	return [round($lon, 6), round($lat, 6)];
}

function hnsGeoRing($raw) {
	if (!is_array($raw) || !array_is_list($raw) || count($raw) < 4) {
		return null;
	}

	$ring = [];

	foreach ($raw as $position) {
		$valid = hnsGeoPosition($position);

		if ($valid === null) {
			return null;
		}

		$ring[] = $valid;
	}

	return $ring;
}

function hnsGeoPolygon($raw) {
	if (!is_array($raw) || !array_is_list($raw) || !$raw) {
		return null;
	}

	$polygon = [];

	foreach ($raw as $ring) {
		$valid = hnsGeoRing($ring);

		if ($valid === null) {
			return null;
		}

		$polygon[] = $valid;
	}

	return $polygon;
}

/// The cleaned MultiPolygon coordinates (a Polygon becomes a one-part
/// MultiPolygon, so every buurt is stored in one shape), or null. Rings need four
/// positions; whether they close is not checked.
function hnsGeoPolygons($raw) {
	if (!is_array($raw)) {
		return null;
	}

	$type = $raw['type'] ?? null;
	$coordinates = $raw['coordinates'] ?? null;

	if ($type === 'Polygon') {
		$polygon = hnsGeoPolygon($coordinates);
		$valid = $polygon === null ? null : [$polygon];
	}
	else if ($type === 'MultiPolygon') {
		$valid = null;

		if (is_array($coordinates) && array_is_list($coordinates) && $coordinates) {
			$valid = [];

			foreach ($coordinates as $polygon) {
				$part = hnsGeoPolygon($polygon);

				if ($part === null) {
					return null;
				}

				$valid[] = $part;
			}
		}
	}
	else {
		return null;
	}

	return $valid;
}

/// Every position of cleaned polygons, as it walks.
function hnsGeoEachPosition($polygons, $each) {
	foreach ($polygons as $polygon) {
		foreach ($polygon as $ring) {
			foreach ($ring as $position) {
				$each($position);
			}
		}
	}
}

/// $state remembers the ids of the gemeenten and wijken already written, so a
/// feature costs one lookup rather than a query per level. Returns true when the
/// feature was stored, false when it was not usable.
function hnsGeoStoreFeature($geo, &$state, $feature) {
	$p = is_array($feature) && is_array($feature['properties'] ?? null) ? $feature['properties'] : [];

	$buurtCode = hnsValidCbsCode($p['bu_code'] ?? null);
	$buurtName = hnsValidCbsName($p['bu_naam'] ?? null);
	$wijkCode = hnsValidCbsCode($p['wk_code'] ?? null);
	$wijkName = hnsValidCbsName($p['wk_naam'] ?? null);
	$gemeenteCode = hnsValidCbsCode($p['gm_code'] ?? null);
	$gemeenteName = hnsValidCbsName($p['gm_naam'] ?? null);
	$polygons = hnsGeoPolygons(($feature['geometry'] ?? null));

	if (in_array(null, [$buurtCode, $buurtName, $wijkCode, $wijkName, $gemeenteCode, $gemeenteName, $polygons], true)) {
		return false;
	}

	if (!isset($state['gemeente'][$gemeenteCode])) {
		$geo->query("INSERT INTO gemeente (code, name) VALUES (?, ?)", [$gemeenteCode, $gemeenteName]);
		$state['gemeente'][$gemeenteCode] = (int)$geo->pdo->lastInsertId();
	}

	if (!isset($state['wijk'][$wijkCode])) {
		$geo->query(
			"INSERT INTO wijk (code, name, gemeente_id) VALUES (?, ?, ?)",
			[$wijkCode, $wijkName, $state['gemeente'][$gemeenteCode]]
		);
		$state['wijk'][$wijkCode] = (int)$geo->pdo->lastInsertId();
	}

	/// A LOB, not text: bound as text the compressed bytes are cut at their first NUL.
	$insert = $geo->pdo->prepare("INSERT OR REPLACE INTO buurt (code, name, wijk_id, water, polygons) VALUES (?, ?, ?, ?, ?)");
	$insert->bindValue(1, $buurtCode);
	$insert->bindValue(2, $buurtName);
	$insert->bindValue(3, $state['wijk'][$wijkCode], PDO::PARAM_INT);
	$insert->bindValue(4, ($p['water'] ?? 'NEE') === 'JA' ? 1 : 0, PDO::PARAM_INT);
	$insert->bindValue(5, gzcompress(json_encode($polygons), 9), PDO::PARAM_LOB);
	$insert->execute();

	$vertex = $geo->pdo->prepare("INSERT OR IGNORE INTO _vertex (lon, lat, gemeente_id) VALUES (?, ?, ?)");
	$gemeenteId = $state['gemeente'][$gemeenteCode];

	hnsGeoEachPosition($polygons, function ($position) use ($vertex, $gemeenteId) {
		$vertex->execute([(int)round($position[0] * HNS_GEO_SCALE), (int)round($position[1] * HNS_GEO_SCALE), $gemeenteId]);
	});

	return true;
}

/// Two gemeenten touch when a buurt of each has a vertex at the very same place.
/// Exact, on the rounded vertices: CBS draws the boundary between two gemeenten
/// once, so both sides carry the same points. A single shared corner counts.
/// Both directions are stored, so a lookup never has to check two columns.
function hnsGeoComputeNeighbours($geo) {
	$geo->execSQL(
		"INSERT OR IGNORE INTO gemeente_neighbour (gemeente_id, neighbour_id)
		SELECT DISTINCT a.gemeente_id, b.gemeente_id
		FROM _vertex a JOIN _vertex b ON b.lon = a.lon AND b.lat = a.lat AND b.gemeente_id <> a.gemeente_id"
	);
}
