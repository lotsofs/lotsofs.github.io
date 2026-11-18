<?php

require_once __MODULES__ . '/mtasa/fields.php';

/// Where the import reads each table: the owner's sheet for the Numbered tab
/// (the owner's own columns), a public CSV export needing no key, and this
/// repo's `import/` for what tools/mtasa writes (docs/mtasa-analysis.md):
/// `classics.csv`, the analysis, and `points.csv`, where each map starts and
/// its checkpoints. The sheet's AI and Mapgen tabs held pasted copies of the
/// two, which Sheets had reformatted in places ("4.0" as 4, 06:00 as 6:00).
const MTASA_SOURCE = 'https://docs.google.com/spreadsheets/d/1dCBEA3Fx96X1L46n6uAVfM7oYlUDQNX6SYOe5nbxRNo/export?format=csv&gid=0';

/// How the messages name each source.
const MTASA_TAB_NAMES = ['map' => 'the sheet\'s Numbered tab', 'map_analysis' => 'classics.csv', 'map_geometry' => 'points.csv'];

/// The suite reads fixture files instead (tests/run.php).
function mtasaImportSources() {
	return [
		'map' => getenv('LOTSOFS_MTASA_SOURCE') ?: MTASA_SOURCE,
		'map_analysis' => getenv('LOTSOFS_MTASA_AI_SOURCE') ?: __MODULES__ . '/mtasa/import/classics.csv',
		'map_geometry' => getenv('LOTSOFS_MTASA_MAPGEN_SOURCE') ?: __MODULES__ . '/mtasa/import/points.csv',
	];
}

/// A URL by curl, anything else as a file. Null when it cannot be read.
function mtasaFetch($source) {
	if (!preg_match('#^https?://#i', $source)) {
		$text = @file_get_contents($source);
		return $text === false ? null : $text;
	}

	if (!function_exists('curl_init')) {
		return null;
	}

	$options = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 60,
	];
	/// The Windows dev machine ships no CA bundle and needs the system store;
	/// elsewhere this adds the system store to curl's own.
	if (defined('CURLSSLOPT_NATIVE_CA')) {
		$options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
	}

	$curl = curl_init($source);
	curl_setopt_array($curl, $options);
	$body = curl_exec($curl);
	$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

	return $body === false || $status !== 200 ? null : $body;
}

/// A CSV export as rows keyed by header.
function mtasaCsvRows($text) {
	$stream = fopen('php://memory', 'r+');
	fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $text));
	rewind($stream);

	$header = fgetcsv($stream, 0, ',', '"', '');
	$rows = [];
	while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
		if ($cells === [null]) {
			continue;
		}
		$row = [];
		foreach ($header as $i => $name) {
			$row[$name] = $cells[$i] ?? '';
		}
		$rows[] = $row;
	}
	fclose($stream);

	return [$header ?: [], $rows];
}

/// Reads one tab into rows of `id => [column => value]`, or a refusal naming
/// what is wrong. Every column the registry gives the table must be there.
function mtasaReadTab($table, $text) {
	[$header, $rows] = mtasaCsvRows($text);

	$wanted = array_filter(mtasaFields(), fn($field) => $field['table'] === $table);
	$needed = array_merge(['Number'], array_column($wanted, 'header'));
	$missing = array_values(array_diff($needed, $header));
	if ($missing) {
		return [null, t('import.error.missingColumn', ['tab' => MTASA_TAB_NAMES[$table], 'column' => $missing[0]])];
	}

	$read = [];
	foreach ($rows as $line => $row) {
		$number = trim($row['Number']);
		if (!preg_match('/^\d{1,7}$/', $number) || (int)$number < 1 || (int)$number > MTASA_MAX_ID) {
			return [null, t('import.error.badNumber', ['row' => $line + 2, 'value' => $number])];
		}
		$id = (int)$number;
		if (isset($read[$id])) {
			return [null, t('import.error.repeatedNumber', ['number' => $id])];
		}

		$values = [];
		foreach ($wanted as $key => $field) {
			[$value, $error] = mtasaFieldValue($key, $row[$field['header']], false);
			if ($error !== null) {
				return [null, t('import.error.badValue', ['number' => $id, 'column' => $field['header'], 'value' => $row[$field['header']]])];
			}
			$values[$key] = $value;
		}
		$read[$id] = $values;
	}

	return [$read, null];
}

/// points.csv into rows of `id => [spawnpoints, checkpoints]`, each the JSON
/// array re-encoded compact, or a refusal naming the map. A map is named by its
/// folder, `z(N)`. Since 2026-11-18 it holds only x and y (the owner's
/// choice): `spawnpoints` one point, the middle of the spawn grid, or none,
/// with a third number on a map with no checkpoints, the radius of its arena
/// (the furthest spawnpoint), and the checkpoints in race order; every spawnpoint, the colours, the
/// pickups and the heights come from GitHub on Increase detail. Every point is
/// checked here, so the preview can trust what it reads.
function mtasaReadMapgen($text) {
	[$header, $rows] = mtasaCsvRows($text);

	foreach (['folder', 'spawnpoints', 'checkpoints'] as $column) {
		if (!in_array($column, $header, true)) {
			return [null, t('import.error.missingColumn', ['tab' => MTASA_TAB_NAMES['map_geometry'], 'column' => $column])];
		}
	}

	$number = fn($value) => is_int($value) || is_float($value);
	$point = fn($p) => count($p) === 2 && $number($p[0]) && $number($p[1]);
	/// A map with no checkpoints adds its arena's radius to the spawn point.
	$spawn = fn($p) => (count($p) === 2 || (count($p) === 3 && $number($p[2]) && $p[2] >= 0)) && $number($p[0]) && $number($p[1]);
	$valid = ['spawnpoints' => $spawn, 'checkpoints' => $point];

	$read = [];
	foreach ($rows as $line => $row) {
		if (!preg_match('/^z\((\d{1,7})\)$/', trim($row['folder']), $match) || (int)$match[1] < 1 || (int)$match[1] > MTASA_MAX_ID) {
			return [null, t('import.error.badFolder', ['row' => $line + 2, 'value' => $row['folder']])];
		}
		$id = (int)$match[1];
		if (isset($read[$id])) {
			return [null, t('import.error.repeatedNumber', ['number' => $id])];
		}

		$values = [];
		foreach ($valid as $column => $isPoint) {
			$cell = trim($row[$column]);
			$points = $cell === '' ? [] : json_decode($cell, true);
			if (!is_array($points) || !array_is_list($points)
				|| array_filter($points, fn($p) => !is_array($p) || !array_is_list($p) || !$isPoint($p))
				|| ($column === 'spawnpoints' && count($points) > 1)) {
				return [null, t('import.error.badPoints', ['number' => $id, 'column' => $column])];
			}
			$values[$column] = json_encode($points, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
		}
		$read[$id] = $values;
	}

	return [$read, null];
}

/// The one-time import: all three tabs or nothing, and nothing at all once the
/// catalogue holds a single map. `['maps' => n, 'analysed' => n, 'drawn' => n]`,
/// or `['error' => message]`.
function mtasaImport($db) {
	$tabs = [];
	foreach (mtasaImportSources() as $table => $source) {
		$text = mtasaFetch($source);
		if ($text === null) {
			return ['error' => t('import.error.fetch', ['tab' => MTASA_TAB_NAMES[$table]])];
		}
		[$rows, $error] = $table === 'map_geometry' ? mtasaReadMapgen($text) : mtasaReadTab($table, $text);
		if ($error !== null) {
			return ['error' => $error];
		}
		$tabs[$table] = $rows;
	}

	$maps = $tabs['map'];

	/// A map only the AI or Mapgen tab has still gets its row, empty of the owner's fields.
	$empty = array_fill_keys(array_keys(array_filter(mtasaFields(), fn($field) => $field['table'] === 'map')), null);
	foreach (array_merge(array_keys($tabs['map_analysis']), array_keys($tabs['map_geometry'])) as $id) {
		$maps[$id] ??= $empty;
	}
	ksort($maps);

	$db->pdo->beginTransaction();
	try {
		/// Checked inside the transaction, so a double click or a second admin
		/// pressing at once cannot import twice.
		$existing = (int)$db->query("SELECT (SELECT COUNT(*) FROM map) + (SELECT COUNT(*) FROM map_analysis) + (SELECT COUNT(*) FROM map_geometry) n")->fetch()['n'];
		if ($existing > 0) {
			$db->pdo->rollBack();
			return ['error' => t('import.error.notEmpty')];
		}

		foreach (['map' => $maps, 'map_analysis' => $tabs['map_analysis']] as $table => $rows) {
			$idColumn = $table === 'map' ? 'id' : 'map_id';
			$keys = array_keys(array_filter(mtasaFields(), fn($field) => $field['table'] === $table));
			$statement = $db->pdo->prepare(
				"INSERT INTO {$table} ({$idColumn}, " . implode(', ', $keys) . ") VALUES (" . implode(', ', array_fill(0, count($keys) + 1, '?')) . ")"
			);
			foreach ($rows as $id => $values) {
				$statement->execute(array_merge([$id], array_map(fn($key) => $values[$key], $keys)));
			}
		}

		mtasaWriteGeometry($db, $tabs['map_geometry']);

		$db->pdo->commit();
	}
	catch (Throwable $e) {
		$db->pdo->rollBack();
		throw $e;
	}

	return ['maps' => count($maps), 'analysed' => count($tabs['map_analysis']), 'drawn' => count($tabs['map_geometry'])];
}

/// Writes points.csv's rows into `map_geometry`. A database made before
/// 2026-11-18 still has its old `pickups` column, NOT NULL, which an edit to
/// the unshipped 001 cannot take away, so it gets an empty list there.
function mtasaWriteGeometry($db, $rows) {
	$hasPickups = in_array('pickups', array_column($db->query("PRAGMA table_info(map_geometry)")->fetchAll(), 'name'), true);
	$statement = $db->pdo->prepare($hasPickups
		? "INSERT INTO map_geometry (map_id, spawnpoints, checkpoints, pickups) VALUES (?, ?, ?, '[]')"
		: "INSERT INTO map_geometry (map_id, spawnpoints, checkpoints) VALUES (?, ?, ?)");
	foreach ($rows as $id => $values) {
		$statement->execute([$id, $values['spawnpoints'], $values['checkpoints']]);
	}
}

/// Replaces what tools/mtasa writes, after a rerun of it: the script's
/// columns (`map_analysis`) from classics.csv and the points (`map_geometry`)
/// from points.csv, every row, in one transaction, the owner's fields and the
/// comments untouched. A map only classics.csv has gets its row, empty of the
/// owner's fields, as on import. Refused while the catalogue is empty, since
/// that is the import's work.
/// `['analysed' => n, 'added' => n, 'drawn' => n]`, or `['error' => message]`.
function mtasaReloadAnalysis($db) {
	$read = [];
	foreach (['map_analysis', 'map_geometry'] as $table) {
		$text = mtasaFetch(mtasaImportSources()[$table]);
		if ($text === null) {
			return ['error' => t('import.error.fetch', ['tab' => MTASA_TAB_NAMES[$table]])];
		}
		[$read[$table], $error] = $table === 'map_geometry' ? mtasaReadMapgen($text) : mtasaReadTab($table, $text);
		if ($error !== null) {
			return ['error' => $error];
		}
	}
	$rows = $read['map_analysis'];

	$keys = array_keys(array_filter(mtasaFields(), fn($field) => $field['table'] === 'map_analysis'));
	$ownerKeys = array_keys(array_filter(mtasaFields(), fn($field) => $field['table'] === 'map'));
	$added = 0;
	$db->pdo->beginTransaction();
	try {
		if ((int)$db->query("SELECT COUNT(*) n FROM map")->fetch()['n'] === 0) {
			$db->pdo->rollBack();
			return ['error' => t('reload.error.empty')];
		}
		$known = array_flip(array_map('intval', array_column($db->query("SELECT id FROM map")->fetchAll(), 'id')));
		$db->pdo->exec("DELETE FROM map_analysis");
		$insert = $db->pdo->prepare("INSERT INTO map_analysis (map_id, " . implode(', ', $keys) . ") VALUES (" . implode(', ', array_fill(0, count($keys) + 1, '?')) . ")");
		$newMap = $db->pdo->prepare("INSERT INTO map (id, " . implode(', ', $ownerKeys) . ") VALUES (" . implode(', ', array_fill(0, count($ownerKeys) + 1, '?')) . ")");
		foreach ($rows as $id => $values) {
			if (!isset($known[$id])) {
				$newMap->execute(array_merge([$id], array_fill(0, count($ownerKeys), null)));
				$added++;
			}
			$insert->execute(array_merge([$id], array_map(fn($key) => $values[$key], $keys)));
		}
		$db->pdo->exec("DELETE FROM map_geometry");
		mtasaWriteGeometry($db, $read['map_geometry']);
		$db->pdo->commit();
	}
	catch (Throwable $e) {
		$db->pdo->rollBack();
		throw $e;
	}

	return ['analysed' => count($rows), 'added' => $added, 'drawn' => count($read['map_geometry'])];
}
