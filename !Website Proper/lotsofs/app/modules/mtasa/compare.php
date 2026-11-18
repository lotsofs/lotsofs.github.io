<?php

/// The analysis fields that make up a map's settings, compared as one.
const MTASA_COMPARE_SETTINGS = ['respawn_enabled', 'respawn_mode', 'respawn_time', 'duration', 'time_of_day', 'weather'];

/// One map's analysis as the comparison needs it, or null when it has none.
function mtasaCompareRow($db, $id) {
	return $db->query("SELECT a.* FROM map_analysis a WHERE a.map_id = ?", [$id])->fetch() ?: null;
}

/// What differs between a map and the one it is marked a copy of, from the
/// stored analysis: the summary words in the script's own manner (the owner's
/// wish, so a confirmed mark still says how the two differ) and a row per
/// difference, [what, this map's, the other map's], for the cell's hover box:
/// the object count and models, the author, the description, the settings (a
/// row for each that differs) and the spawn vehicles. The points are compared
/// in the browser on Increase detail, from both maps' files on GitHub
/// (mtasaComparePoints() in js/analysis.js; the owner's choice, 2026-11-18),
/// since only the middle of the grid and the checkpoints are stored; their
/// words and rows go first. An empty value is "", which the hover box shows as
/// none. Object positions were never imported: objects compare by count and
/// models.
function mtasaCompareMaps($map, $other) {
	$summary = [];
	$rows = [];
	$text = fn($row, $key) => trim((string)($row[$key] ?? ''));

	foreach (['objects' => 'compare.objectCount', 'top_object_models' => 'compare.objectModels', 'author' => 'compare.author', 'description' => 'compare.description'] as $key => $words) {
		if ($text($map, $key) !== $text($other, $key)) {
			$summary[] = t($words);
			$rows[] = [t('field.' . $key), $text($map, $key), $text($other, $key)];
		}
	}
	$settings = array_values(array_filter(MTASA_COMPARE_SETTINGS, fn($key) => $text($map, $key) !== $text($other, $key)));
	if ($settings) {
		$summary[] = t('compare.settings');
		foreach ($settings as $key) {
			$rows[] = [t('field.' . $key), mtasaFieldText($key, $map[$key] ?? null), mtasaFieldText($key, $other[$key] ?? null)];
		}
	}
	if ($text($map, 'start_vehicles') !== $text($other, 'start_vehicles')) {
		$summary[] = t('compare.spawnVehicles');
		/// Stored by id, so named for the hover box.
		$rows[] = [t('field.start_vehicles'), mtasaFieldText('start_vehicles', $map['start_vehicles'] ?? null), mtasaFieldText('start_vehicles', $other['start_vehicles'] ?? null)];
	}

	return ['words' => $summary, 'summary' => $summary ? implode('; ', $summary) : t('compare.identical'), 'rows' => $rows];
}

/// The hover box of a cell the site worked out, both ways: as text, a line a
/// row (`Author: "A" vs "B"`, text quoted so a long one reads as one value),
/// and as the small table tooltip.js draws instead where it runs, headed by
/// the two maps' numbers (the owner's wish: easier to read). Two maps with no
/// differences say they are identical, with no table. [text, table JSON or null].
function mtasaCompareTooltip($rows, $thisId, $otherId) {
	if (!$rows) {
		return [t('compare.identicalMaps', ['this' => $thisId, 'that' => $otherId]), null];
	}
	$shown = fn($value) => $value === '' ? t('map.unset') : (is_numeric($value) ? $value : '"' . $value . '"');
	$lines = [];
	foreach ($rows as [$what, $mine, $theirs]) {
		$lines[] = t('compare.versus', ['what' => $what, 'this' => $shown($mine), 'that' => $shown($theirs)]);
	}
	/// A missing value is null, which the table draws as `none` in italics, so a
	/// map whose own text reads "(none)" is not mistaken for one with nothing.
	$table = [
		'head' => ['', (string)$thisId, (string)$otherId],
		'none' => t('map.unset'),
		'rows' => array_map(fn($row) => [$row[0], $row[1] === '' ? null : $row[1], $row[2] === '' ? null : $row[2]], $rows),
	];

	return [implode("\n", $lines), json_encode($table)];
}
