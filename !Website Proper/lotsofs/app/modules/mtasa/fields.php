<?php

/// The statuses and gamemodes the owner's sheet used. A stored value outside
/// these still shows as itself; the editor offers only these.
const MTASA_STATUSES = ['Named Available', 'Good', 'Mid', 'Bad', 'Undetermined', 'Duplicate', 'Remake', 'Error', 'Patch Due', 'Published', 'Complete', 'Outsourced'];
const MTASA_GAMEMODES = ['Race', 'Destruction Derby', 'Freeroam', 'Cops Robbers', 'Vehicle Weapon Combat', 'Hunt the Hunter', 'Teamwork', 'Versus Race', 'Misc'];

/// Map ids are positive; this keeps a typo from naming map 4 billion.
const MTASA_MAX_ID = 1000000;
const MTASA_TEXT_LIMIT = 10000;

/// Every column of `map` and `map_analysis`, in the order the map page shows
/// them (the About section first, built from the names and a copy of some of
/// the metadata: see the map view): its table, the sheet header the import reads it by, its type, the
/// section it is shown under, and whether the admin edits it. Every column
/// name that reaches SQL is a key of this, and nothing else is.
function mtasaFields() {
	static $fields = null;
	if ($fields !== null) {
		return $fields;
	}

	$owner = fn($header, $type = 'text', $section = 'owner') => ['table' => 'map', 'header' => $header, 'type' => $type, 'section' => $section, 'editable' => true];
	$ai = fn($section, $header, $type = 'text') => ['table' => 'map_analysis', 'header' => $header, 'type' => $type, 'section' => $section, 'editable' => false];

	$fields = [
		'original_name' => $owner('Original Map Name', 'text', 'about'),
		'custom_name' => $owner('New Name', 'text', 'about'),
		'hypothesized_name' => $owner('Hypothesized Name', 'text', 'about'),
		'custom_description' => $owner('Custom description', 'longtext', 'about'),
		'author' => $ai('about', 'Author'),
		'description' => $ai('about', 'Description', 'longtext'),
		'folder' => $ai('about', 'Folder'),
		'version' => $ai('about', 'Version'),

		/// The section order is this list's (the owner's): About, Problems,
		/// Settings, then Downloads (drawn by the view), Classification, My
		/// experience, Duplicates, Guesses and the rest.
		'errors' => $ai('problems', 'Errors'),
		'warnings' => $ai('problems', 'Warnings', 'longtext'),
		'notes' => $ai('problems', 'Notes', 'longtext'),

		'respawn_enabled' => $ai('settings', 'Respawn enabled', 'bool'),
		'respawn_mode' => $ai('settings', 'Respawn mode'),
		'respawn_time' => $ai('settings', 'Respawn time (s)', 'int'),
		'duration' => $ai('settings', 'Duration (s)', 'int'),
		'time_of_day' => $ai('settings', 'Time of day'),
		'weather' => $ai('settings', 'Weather', 'int'),

		'category' => $owner('Category', 'text', 'classification'),
		'gamemode' => $owner('Gamemode', 'gamemode', 'classification'),

		'video' => $owner('Vid', 'url', 'experience'),
		'finished' => $owner('Finished', 'bool', 'experience'),
		'status' => $owner('Status', 'status', 'experience'),

		'duplicate_of' => $owner('Duplicate of', 'map', 'duplicates'),

		/// Start, Route and Finish (the owner's, 2026-11-17). Vehicles and zones
		/// are stored by id (`vehicles`: "461 x16, 522"; `zones`: game zone
		/// labels, "GANTON, LAIR") and named by mtasaFieldText().
		'spawnpoints' => $ai('start', 'Spawnpoints', 'int'),
		'start_vehicles' => $ai('start', 'Start vehicles', 'vehicles'),
		'start_zone' => $ai('start', 'Start zone', 'zones'),

		'checkpoints' => $ai('route', 'Checkpoints', 'int'),
		'checkpoints_used' => $ai('route', 'Checkpoints used by race', 'int'),
		'route_length' => $ai('route', 'Route length (m)', 'km'),
		/// Worked out from the stored points (mtasaRouteLength2d()), so no table or header.
		'route_length_2d' => ['table' => 'derived', 'header' => null, 'type' => 'km', 'section' => 'route', 'editable' => false],
		'ring_checkpoints' => $ai('route', 'Ring checkpoints', 'int'),
		'checkpoint_vehicle_changes' => $ai('route', 'Checkpoint vehicle changes', 'int'),
		'repair_pickups' => $ai('route', 'Repair pickups', 'int'),
		'nitro_pickups' => $ai('route', 'Nitro pickups', 'int'),
		'vehicle_change_pickups' => $ai('route', 'Vehicle change pickups', 'int'),
		'objects' => $ai('route', 'Objects', 'int'),
		'top_object_models' => $ai('route', 'Top object models'),
		/// Worked out only, by js/analysis.js on Increase detail (the owner's
		/// choice): too much to store, and it needs the points' heights.
		'zones_on_route' => ['table' => 'derived', 'header' => null, 'type' => 'zones', 'section' => 'route', 'editable' => false],
		'vehicles_via_changes' => $ai('route', 'Vehicles via changes', 'vehicles'),

		'finish_zone' => $ai('finish', 'Finish zone', 'zones'),
		'returns_to_start' => $ai('finish', 'Returns to start', 'bool'),

		/// Classification's own rows are listed in the map view; these are its.
		/// Not stored but worked out (MTASA_RACE_MODE_SQL), so it has no table or header.
		'race_mode' => ['table' => 'derived', 'header' => null, 'type' => 'text', 'section' => 'classification', 'editable' => false],
		'vehicle_classes' => $ai('classification', 'Vehicle classes'),

		/// The script's guesses, at the very bottom (the owner's wish).
		'title' => $ai('guesses', 'Title'),
		'title_source' => $ai('guesses', 'Title source'),
		'category_guess' => $ai('guesses', 'Category guess'),
		'category_confidence' => $ai('guesses', 'Category confidence'),
		'category_reason' => $ai('guesses', 'Category reason'),

		'duplicate_of_closest' => $ai('guesses', 'Duplicate of (closest)', 'map'),
		'duplicate_confidence' => $ai('guesses', 'Duplicate confidence'),
		'duplicate_similarity' => $ai('guesses', 'Duplicate similarity', 'percent'),
		'duplicate_differences' => $ai('guesses', 'Duplicate differences'),
	];

	return $fields;
}

/// The SQL for a field, qualified by the alias its table has in every query
/// here (`m` for map, `a` for map_analysis). Only ever given a registry key.
function mtasaColumn($key) {
	$field = mtasaFields()[$key];

	return ($field['table'] === 'map' ? 'm.' : 'a.') . $key;
}

/// The choices an editor offers, or null for a field typed freely.
function mtasaFieldChoices($key) {
	$type = mtasaFields()[$key]['type'];
	if ($type === 'status') {
		return MTASA_STATUSES;
	}
	if ($type === 'gamemode') {
		return MTASA_GAMEMODES;
	}

	return null;
}

/// Reads one value as the sheet or the editor writes it: `[$value, $error]`,
/// the value being what is stored (NULL for empty). `$strict` is the editor's
/// rule, holding a status or gamemode to its list; the import keeps whatever
/// the sheet says, since that is the owner's record.
function mtasaFieldValue($key, $raw, $strict) {
	$type = mtasaFields()[$key]['type'];
	$text = trim(is_string($raw) ? $raw : (is_int($raw) ? (string)$raw : ''));

	if ($text === '') {
		return [null, null];
	}

	if ($type === 'map') {
		if (!preg_match('/^\d{1,7}$/', $text) || (int)$text < 1 || (int)$text > MTASA_MAX_ID) {
			return [null, 'notMap'];
		}

		return [(int)$text, null];
	}

	/// A length (`km`) is whole metres, shown in kilometres.
	if ($type === 'int' || $type === 'km') {
		if (!preg_match('/^-?\d+$/', $text)) {
			return [null, 'notNumber'];
		}
		/// The script measured one broken map's route as 38 digits long. Past
		/// 64 bits SQLite keeps it as an approximate number, which is all that
		/// one is worth; refusing it would refuse the whole import.
		return [strlen(ltrim($text, '-')) <= 18 ? (int)$text : $text, null];
	}

	if ($type === 'percent') {
		if (!preg_match('/^(\d{1,3})\s*%?$/', $text, $match) || (int)$match[1] > 100) {
			return [null, 'notPercent'];
		}

		return [(int)$match[1], null];
	}

	if ($type === 'bool') {
		$lower = strtolower($text);
		if (in_array($lower, ['true', 'yes', '1'], true)) {
			return [1, null];
		}
		if (in_array($lower, ['false', 'no', '0'], true)) {
			return [0, null];
		}
		/// The script writes `null` where a map leaves the setting out.
		if ($lower === 'null') {
			return [null, null];
		}

		return [null, 'notBool'];
	}

	if ($type === 'url') {
		if (!preg_match('#^https?://\S+$#i', $text)) {
			return [null, 'notUrl'];
		}
	}

	if ($strict && ($choices = mtasaFieldChoices($key)) !== null && !in_array($text, $choices, true)) {
		return [null, 'notChoice'];
	}

	if (strlen($text) > MTASA_TEXT_LIMIT) {
		return [null, 'tooLong'];
	}

	/// Line breaks as \n, whatever the sheet or the browser sent.
	return [str_replace(["\r\n", "\r"], "\n", $text), null];
}

/// A stored value as the page writes it, '' for none.
function mtasaFieldText($key, $value) {
	if ($value === null || $value === '') {
		return '';
	}

	$type = mtasaFields()[$key]['type'];
	if ($type === 'bool') {
		return t((int)$value === 1 ? 'value.yes' : 'value.no');
	}
	if ($type === 'percent') {
		return $value . '%';
	}
	if ($type === 'km') {
		return sprintf('%.3fkm', (float)$value / 1000);
	}
	if ($type === 'vehicles') {
		return mtasaVehicleText((string)$value);
	}
	if ($type === 'zones') {
		return mtasaZoneText((string)$value);
	}

	return (string)$value;
}

/// GTA's vehicle names by model id, 400 to 611: MTA's, as the script's
/// were, but 599 Ranger (the owner's). The file is the one list; the map
/// page hands it to js/preview.js.
function mtasaVehicleNames() {
	static $names = null;

	return $names ??= json_decode(file_get_contents(__MODULES__ . '/mtasa/data/vehicles.json'), true);
}

/// The zone names by game label, written by tools/mtasa/site_zones.py.
function mtasaZoneNames() {
	static $names = null;

	return $names ??= json_decode(file_get_contents(__MODULES__ . '/mtasa/data/zone_names.json'), true);
}

function mtasaVehicleName($model) {
	return mtasaVehicleNames()[$model - 400] ?? t('map.github.unknownVehicle', ['model' => $model]);
}

/// Stored vehicle ids ("461 x16, 522") as names ("PCJ-600 x16, NRG-500").
function mtasaVehicleText($ids) {
	$parts = array_map(function ($part) {
		[$model, $count] = array_pad(explode(' x', $part, 2), 2, null);
		return mtasaVehicleName((int)$model) . ($count !== null ? ' x' . $count : '');
	}, explode(', ', $ids));
	return implode(', ', $parts);
}

/// Stored zone labels as their names, each name once (Linden Station has two
/// labels); a label the file does not know shows as itself.
function mtasaZoneText($labels) {
	return implode(', ', array_unique(array_map(fn($label) => mtasaZoneNames()[$label] ?? $label, explode(', ', $labels))));
}
