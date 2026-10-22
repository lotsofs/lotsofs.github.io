<?php

/// The read side of hideandseek_geo.sqlite, which tools/import-buurten.php builds
/// and the owner copies to the host by hand. The site never writes it, and a
/// server that has not been given one simply has no buurten.

function hnsGeoPath() {
	return __DATA__ . '/hideandseek_geo.sqlite';
}

/// The geo database, or null when there is no file. Opened query-only, and never
/// created: a missing file must not turn into an empty one that looks real.
function hnsGeoDb() {
	if (!file_exists(hnsGeoPath())) {
		return null;
	}

	$geo = new Database(hnsGeoPath());
	$geo->execSQL('PRAGMA query_only = ON');

	return $geo;
}

function hnsGeoGemeenten($geo) {
	return $geo->query("SELECT code, name FROM gemeente ORDER BY name COLLATE NOCASE, code")->fetchAll();
}

function hnsGeoGemeenteExists($geo, $code) {
	return (bool)$geo->query("SELECT 1 FROM gemeente WHERE code = ?", [$code])->fetch();
}

/// Every buurt of a gemeente and of the gemeenten that touch it, as one JSON array
/// text, `own` marking the chosen gemeente's so the map can show where the play
/// area is. Each row's polygons are stored zlib-compressed (a quarter of the
/// size); the text is inflated and spliced in as it is, inside a MultiPolygon
/// wrapper: decoding and re-encoding it ran Amsterdam and its neighbours out of a
/// 128MB memory limit.
/// "[]" for a code that is not there.
function hnsGeoBuurtenJson($geo, $code) {
	$rows = $geo->query(
		"SELECT b.id, b.name, b.water, b.polygons, w.name AS wijk, g.name AS gemeente, (g.id = c.id) AS own
		FROM gemeente c
		JOIN gemeente g ON g.id = c.id OR g.id IN (SELECT neighbour_id FROM gemeente_neighbour WHERE gemeente_id = c.id)
		JOIN wijk w ON w.gemeente_id = g.id
		JOIN buurt b ON b.wijk_id = w.id
		WHERE c.code = ?
		ORDER BY b.name COLLATE NOCASE, b.code",
		[$code]
	);

	$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
	$parts = [];

	foreach ($rows as $row) {
		$parts[] = '{"id":' . (int)$row['id']
			. ',"name":' . json_encode($row['name'], $flags)
			. ',"wijk":' . json_encode($row['wijk'], $flags)
			. ',"gemeente":' . json_encode($row['gemeente'], $flags)
			. ',"own":' . ($row['own'] ? 'true' : 'false')
			. ',"water":' . ($row['water'] ? 'true' : 'false')
			. ',"geometry":{"type":"MultiPolygon","coordinates":' . gzuncompress($row['polygons']) . '}}';
	}

	return '[' . implode(',', $parts) . ']';
}
