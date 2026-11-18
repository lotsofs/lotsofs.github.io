<?php

/// Julix's "Age of Empires 1/2/3/4 Campaigns and Scenarios in Chronological
/// Order", its Individual Scenarios tab. Public, so Google serves it as CSV,
/// and as an HTML page that also carries the cell colours the CSV drops.
const GAMES_AOE2_SOURCE = 'https://docs.google.com/spreadsheets/d/1kC1OS77B4R_yrr7XIeHbK7pp_zowsBGDIzXiLUKE8wk/export?format=csv&gid=840222089';
const GAMES_AOE2_STYLED_SOURCE = 'https://docs.google.com/spreadsheets/d/1kC1OS77B4R_yrr7XIeHbK7pp_zowsBGDIzXiLUKE8wk/htmlview/sheet?headers=true&gid=840222089';
const GAMES_AOE2_SHEET = 'https://docs.google.com/spreadsheets/d/1kC1OS77B4R_yrr7XIeHbK7pp_zowsBGDIzXiLUKE8wk/edit?gid=840222089#gid=840222089';

/// How much of its sheet colour a row shows over the dark page. 45% is the most
/// at which every colour the sheet used on 2026-11-12 still takes light text
/// (the owner's choice); at 46% the pale yellow #fff2cc wants black. The view
/// hands it to the stylesheet, so the two cannot differ.
const GAMES_AOE2_ROW_ALPHA = 0.45;

const GAMES_AOE2_MAX_AGE = 600;
const GAMES_AOE2_RETRY_AFTER = 60;

/// Found by name rather than position, so a column the author adds does not
/// shift every field over.
const GAMES_AOE2_HEADERS = [
	'number' => 'scenario number',
	'name' => 'scenario',
	'year' => 'year',
	'civ' => 'civilization',
	'campaign' => 'campaign',
	'game' => 'game',
	'pack' => 'pack',
];

/// The filter lists known codes in this order; a code the sheet adds comes
/// after them, under its own name.
const GAMES_AOE2_GAME_LABELS = [
	'I' => 'AoE I',
	'II' => 'AoE II',
	'II:R' => 'AoE II: Return of Rome',
	'II:C' => 'AoE II: Chronicles',
	'III' => 'AoE III',
	'IV' => 'AoE IV',
];

/// The DLC option a downloadable scenario counts under, in place of its pack. The
/// legend has no such pack, so this is also the option's label.
const GAMES_AOE2_DOWNLOADABLE_PACK = 'Downloadable Scenarios';

/// The account's lists of folded and unfolded places on the to-scale timeline:
/// stretches folded away, each named by the scenario after it, and years of
/// several scenarios unfolded, each named by its first.
const GAMES_AOE2_FOLD_LISTS = ['collapsed', 'expanded'];

/// What an account can write about a scenario (the owner's wish), each its own
/// column in `aoe2_entry`, with the most bytes it may hold (no mbstring here).
/// The SQL column is only ever a key of this list.
const GAMES_AOE2_ENTRY_FIELDS = ['descriptor' => 100, 'rating' => 10, 'note' => 2000, 'youtube' => 200];

/// A stored YouTube link ("id" or "id&t=90", as music stores one) after this is a watch link.
const GAMES_AOE2_YOUTUBE_PREFIX = 'https://www.youtube.com/watch?v=';

/// The default view (the owner's choice): AoE II with its DLC up to this one,
/// in the legend's order, which is the order they came out.
const GAMES_AOE2_DEFAULT_UP_TO = 'II|TLK';

/// The name of the stretch from the last dated scenario to the present year,
/// when no undated scenario follows to name it. A scenario's key always holds
/// a `|`, so no scenario can be called this.
const GAMES_AOE2_PRESENT_KEY = 'present';

/// The games as the sheet's legend heads them, to its codes in the Game column.
const GAMES_AOE2_GAME_NAMES = [
	'Age of Empires' => 'I',
	'Age of Empires II' => 'II',
	'Age of Empires II: Return of Rome' => 'II:R',
	'Age of Empires II: Chronicles' => 'II:C',
	'Age of Empires III' => 'III',
	'Age of Empires IV' => 'IV',
];

/// The suite points both at fixtures.
function gamesAoe2Sources() {
	return [
		'csv' => getenv('LOTSOFS_AOE2_SOURCE') ?: GAMES_AOE2_SOURCE,
		'styled' => getenv('LOTSOFS_AOE2_STYLED_SOURCE') ?: GAMES_AOE2_STYLED_SOURCE,
	];
}

function gamesAoe2CachePath() {
	return __DATA__ . '/games_aoe2_scenarios.csv';
}

function gamesAoe2LoadLive() {
	return gamesAoe2Load(gamesAoe2Sources(), gamesAoe2CachePath(), GAMES_AOE2_MAX_AGE);
}

/// The sheet as it stands, from a copy at most $maxAge seconds old. A failed
/// fetch keeps serving the old copy, marked stale, and is not retried for
/// $retryAfter seconds, so an unreachable Google does not stall every page.
/// Null only when there has never been a copy. The colours come along with
/// each refresh of the CSV, but are only ever extra: without them the lanes
/// fall back to colours of their own.
function gamesAoe2Load($sources, $cachePath, $maxAge, $retryAfter = GAMES_AOE2_RETRY_AFTER) {
	$failedPath = $cachePath . '.failed';
	$coloursPath = $cachePath . '.colours.json';

	/// PHP remembers file times within a process, and both ages are read here.
	clearstatcache();

	$cached = is_file($cachePath) ? @file_get_contents($cachePath) : false;
	$cachedAt = $cached === false ? null : filemtime($cachePath);

	if ($cached !== false && time() - $cachedAt < $maxAge) {
		$scenarios = gamesAoe2Parse($cached);
		if ($scenarios) {
			return ['scenarios' => $scenarios, 'fetchedAt' => $cachedAt, 'stale' => false, 'colours' => gamesAoe2CachedColours($coloursPath), 'packs' => gamesAoe2PackNames($cached)];
		}
	}

	$recentlyFailed = is_file($failedPath) && time() - filemtime($failedPath) < $retryAfter;
	$fresh = $recentlyFailed ? null : gamesAoe2Fetch($sources['csv']);
	$scenarios = $fresh === null ? null : gamesAoe2Parse($fresh);

	if ($scenarios) {
		gamesAoe2Replace($cachePath, $fresh);
		@unlink($failedPath);

		/// Keyed by scenario, not by row, so a map from an earlier fetch stays
		/// right for every scenario it names when this one brings none.
		$html = isset($sources['styled']) ? gamesAoe2Fetch($sources['styled']) : null;
		$colours = $html === null ? [] : gamesAoe2Colours($html);
		if ($colours) {
			gamesAoe2Replace($coloursPath, json_encode($colours));
		}

		return ['scenarios' => $scenarios, 'fetchedAt' => time(), 'stale' => false, 'colours' => gamesAoe2CachedColours($coloursPath), 'packs' => gamesAoe2PackNames($fresh)];
	}

	if (!$recentlyFailed) {
		@touch($failedPath);
	}

	$old = $cached === false ? null : gamesAoe2Parse($cached);

	return $old ? ['scenarios' => $old, 'fetchedAt' => $cachedAt, 'stale' => true, 'colours' => gamesAoe2CachedColours($coloursPath), 'packs' => gamesAoe2PackNames($cached)] : null;
}

/// Written beside it and moved over, so a reader never sees half a file.
function gamesAoe2Replace($path, $text) {
	$temp = $path . '.' . getmypid() . '.tmp';
	if (@file_put_contents($temp, $text) !== false && !@rename($temp, $path)) {
		@unlink($temp);
	}
}

function gamesAoe2CachedColours($coloursPath) {
	$json = is_file($coloursPath) ? @file_get_contents($coloursPath) : false;
	$colours = $json === false ? null : json_decode($json, true);

	return is_array($colours) ? $colours : [];
}

/// The page text, or null. A local path is read as a file, which is how the
/// test suite feeds its fixtures.
function gamesAoe2Fetch($source) {
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
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 8,
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

/// A scenario's year as `[first, last]`, or null. The sheet writes years as text
/// ("c.8000 BCE", "406 to 455", "850 to c.900", "-"): a single year is both, a
/// range its two ends, negative before the common era, whether the BCE is on
/// the year itself or only on the end of a range ("500 to 400 BCE").
function gamesAoe2YearSpan($year) {
	$parts = preg_split('/\s+to\s+/i', $year);
	if (!preg_match('/(\d+)/', $parts[0], $match)) {
		return null;
	}

	$startBce = stripos($parts[0], 'BCE') !== false
		|| (isset($parts[1]) && stripos($parts[0], 'CE') === false && stripos($parts[1], 'BCE') !== false);
	$start = $startBce ? -(int)$match[1] : (int)$match[1];

	if (!isset($parts[1]) || !preg_match('/(\d+)/', $parts[1], $endMatch)) {
		return [$start, $start];
	}

	$endBce = stripos($parts[1], 'BCE') !== false || ($startBce && stripos($parts[1], 'CE') === false);

	return [$start, $endBce ? -(int)$endMatch[1] : (int)$endMatch[1]];
}

/// The year the to-scale timeline places a scenario at: the first it names.
function gamesAoe2YearNumber($year) {
	$span = gamesAoe2YearSpan($year);

	return $span === null ? null : $span[0];
}

/// Which of these years, in order, are out of place: ahead of their time, a
/// year that starts after one below them is over. The sheet keeps a campaign
/// in its own order in places, so a scenario can come before earlier ones
/// (Le Loi 5, 1427, before the 1425 and 1426 scenarios and Le Loi 6), and the
/// owner wanted the one that jumps ahead marked. A range counts by its start
/// against the ends below, so The Fall of Rome (453) before Gaiseric (406 to
/// 455) is in place. Walked from the bottom, keeping the earliest end so far.
function gamesAoe2OutOfPlace($years) {
	$flags = array_fill_keys(array_keys($years), false);
	$earliest = null;

	foreach (array_reverse(array_keys($years)) as $i) {
		$span = gamesAoe2YearSpan($years[$i]);
		if ($span === null) {
			continue;
		}

		$flags[$i] = $earliest !== null && $span[0] > $earliest;
		$earliest = $earliest === null ? $span[1] : min($earliest, $span[1]);
	}

	return $flags;
}

function gamesAoe2Clean($text) {
	return trim((string)preg_replace('/[\s\x{00A0}]+/u', ' ', (string)$text));
}

/// Every scenario in the sheet's order, or null when the text has no header
/// row or no scenarios under it.
function gamesAoe2Parse($csv) {
	$stream = fopen('php://memory', 'r+');
	fwrite($stream, $csv);
	rewind($stream);

	$columns = null;
	$scenarios = [];

	while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
		if ($columns === null) {
			$columns = gamesAoe2HeaderColumns($cells);
			continue;
		}

		$row = [];
		foreach ($columns as $field => $index) {
			$row[$field] = gamesAoe2Clean($cells[$index] ?? '');
		}

		if ($row['name'] === '') {
			continue;
		}

		/// "Ascent of Egypt   1" starts a campaign, "<End>   5" ends one, and a
		/// standalone scenario carries a label with no number at all: the
		/// Historical Battles and Victors and Vanquished, which share a Campaign
		/// cell without being one campaign.
		$number = $row['number'];
		$row['end'] = strncmp($number, '<End>', 5) === 0;
		if ($row['end']) {
			$number = trim(substr($number, 5));
		}
		$row['number'] = preg_match('/(\d+)$/', $number, $match) ? (int)$match[1] : null;
		$row['standalone'] = $row['number'] === null;

		$row['campaignKey'] = $row['game'] . '|' . $row['campaign'];
		/// The campaign a line follows: the name up to its colon. AoE IV splits its
		/// campaigns into chapters ("The Normans: Conquest", "The Normans:
		/// Possession"), numbered on from one another, and Battle for Greece its
		/// three parts the same way (1 to 21), so one line runs through them all
		/// (the owner's wish). The tick key keeps the whole name.
		$row['lineKey'] = $row['game'] . '|' . explode(': ', $row['campaign'], 2)[0];
		$row['key'] = $row['campaignKey'] . '|' . $row['name'];
		/// Pack codes repeat across games ("-" is every game's base game). A
		/// downloadable scenario is filtered on its own (the owner's choice),
		/// though the sheet lists it under the pack it came with.
		$downloadable = preg_match('/^downloadable scenario/i', $row['campaign']) === 1;
		$row['packKey'] = $row['game'] . '|' . ($downloadable ? GAMES_AOE2_DOWNLOADABLE_PACK : $row['pack']);

		$scenarios[] = $row;
	}

	fclose($stream);

	return $columns !== null && $scenarios ? $scenarios : null;
}

/// Column index per field when this row holds every header, else null.
function gamesAoe2HeaderColumns($cells) {
	$found = [];
	foreach ($cells as $index => $cell) {
		$cell = strtolower(gamesAoe2Clean($cell));
		foreach (GAMES_AOE2_HEADERS as $field => $header) {
			if ($cell === $header && !isset($found[$field])) {
				$found[$field] = $index;
			}
		}
	}

	return count($found) === count(GAMES_AOE2_HEADERS) ? $found : null;
}

/// Each scenario's row colour from the sheet's HTML view, keyed as the parser
/// keys it, so the two pages never have to agree on row numbers. The colour is
/// the Scenario cell's: a campaign fills its whole row with one, except the
/// Game and Pack cells, which are coloured by game. Empty without the headers.
function gamesAoe2Colours($html) {
	$backgrounds = [];
	preg_match_all('/\.(s\d+)\{[^}]*?background-color:\s*(#[0-9a-f]{6})\b/i', $html, $rules, PREG_SET_ORDER);
	foreach ($rules as $rule) {
		$backgrounds[$rule[1]] = strtolower($rule[2]);
	}

	preg_match_all('#<tr[^>]*>(.*?)</tr>#s', $html, $rows);

	$columns = null;
	$colours = [];

	foreach ($rows[1] as $row) {
		preg_match_all('#<td([^>]*)>(.*?)</td>#s', $row, $cells, PREG_SET_ORDER);

		/// Merged cells are placed by their span; a campaign's first cell has
		/// more than one class ("s7 softmerge"), of which the first is its style.
		$texts = [];
		$classes = [];
		$column = 0;
		foreach ($cells as $cell) {
			$texts[$column] = gamesAoe2Clean(html_entity_decode(strip_tags($cell[2]), ENT_QUOTES | ENT_HTML5));
			$classes[$column] = preg_match('/class="(s\d+)/', $cell[1], $class) ? $class[1] : '';
			$column += preg_match('/colspan="(\d+)"/', $cell[1], $span) ? max(1, (int)$span[1]) : 1;
		}

		if ($columns === null) {
			$columns = gamesAoe2HeaderColumns($texts);
			continue;
		}

		$name = $texts[$columns['name']] ?? '';
		$colour = $backgrounds[$classes[$columns['name']] ?? ''] ?? null;
		if ($name === '' || $colour === null) {
			continue;
		}

		$colours[($texts[$columns['game']] ?? '') . '|' . ($texts[$columns['campaign']] ?? '') . '|' . $name] = $colour;
	}

	return $colours;
}

/// The value to store for one field of an entry, from what was typed: null
/// for nothing, a float from 0 to 10 with at most two decimals for a rating (a
/// comma reads as the point), the video id and start for a YouTube link, the
/// text for the others, line breaks and all. Throws with the message to show
/// when it will not do.
function gamesAoe2EntryValue($field, $text) {
	$text = trim(str_replace(["\r\n", "\r"], "\n", $text));
	if ($text === '') {
		return null;
	}
	if (strlen($text) > GAMES_AOE2_ENTRY_FIELDS[$field]) {
		throw new InvalidArgumentException(t('aoe2.tooLong'));
	}
	if ($field === 'youtube') {
		$link = gamesAoe2YoutubeLinkIn($text);
		if ($link === null) {
			throw new InvalidArgumentException(t('aoe2.badYoutube'));
		}
		return $link['id'] . ($link['start'] !== null ? '&t=' . $link['start'] : '');
	}
	if ($field === 'rating') {
		$text = str_replace(',', '.', $text);
		if (!preg_match('/^\d+(\.\d{1,2})?$/', $text) || (float)$text > 10) {
			throw new InvalidArgumentException(t('aoe2.badRating'));
		}
		return (float)$text;
	}
	return $text;
}

/// A YouTube link's video id and its start in whole seconds (null for none),
/// from a watch, youtu.be, embed, shorts or live link, or a bare 11-character
/// id; null when there is no id in it. The start is t= or start=, as 656, 656s
/// or 1h2m3s. Music's youtubeLinkIn() with shorts and live added and a bare id
/// held to a real id's length; a copy, as the modules share no code.
function gamesAoe2YoutubeLinkIn($value) {
	if (!preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]+)~', $value, $match)
		&& !preg_match('~^([A-Za-z0-9_-]{11})(?=[?&]|$)~', $value, $match)) {
		return null;
	}

	$start = null;
	if (preg_match('~[?&#](?:t|start)=(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?(?=[&#]|$)~', $value, $time)) {
		$seconds = (int)($time[1] ?? 0) * 3600 + (int)($time[2] ?? 0) * 60 + (int)($time[3] ?? 0);
		$start = $seconds > 0 ? $seconds : null;
	}

	return ['id' => $match[1], 'start' => $start];
}

/// A stored entry field as the page shows it: a rating without trailing zeros,
/// a YouTube link as its watch link.
function gamesAoe2EntryText($field, $value) {
	if ($value === null) {
		return '';
	}
	if ($field === 'youtube') {
		return GAMES_AOE2_YOUTUBE_PREFIX . $value;
	}
	if ($field === 'rating') {
		return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
	}
	return (string)$value;
}

/// The account's saved view of the page, one JSON row in `aoe2_filter`: the
/// ticked filters, the to-scale switch and the stretches collapsed in it.
function gamesAoe2Settings($db, $accountId) {
	$saved = $db->query("SELECT filters FROM aoe2_filter WHERE account_id = ?", [$accountId])->fetch();
	$decoded = $saved ? json_decode($saved['filters'], true) : null;

	return is_array($decoded) ? $decoded : [];
}

function gamesAoe2SaveSettings($db, $accountId, $settings) {
	$db->query(
		"INSERT INTO aoe2_filter (account_id, filters, updated_at) VALUES (?, ?, ?)
		ON CONFLICT (account_id) DO UPDATE SET filters = excluded.filters, updated_at = excluded.updated_at",
		[$accountId, json_encode($settings), time()]
	);
}

/// The DLC names from the sheet's legend, `game|code => name`. The legend sits
/// right of the data under a "Game / Expansion Pack Legend" cell: in that
/// column, a row with nothing to its right heads a game, and a row with a name
/// to its right is one of that game's packs. Empty without the legend, and the
/// filter then shows the codes.
function gamesAoe2PackNames($csv) {
	$stream = fopen('php://memory', 'r+');
	fwrite($stream, $csv);
	rewind($stream);

	$column = null;
	$game = null;
	$names = [];

	while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
		if ($column === null) {
			foreach ($cells as $index => $cell) {
				/// Anchored at the end, so a campaign such as "The Legend of El
				/// Dorado" is never taken for it.
				if (preg_match('/\blegend$/i', gamesAoe2Clean($cell))) {
					$column = $index;
				}
			}
			continue;
		}

		$code = gamesAoe2Clean($cells[$column] ?? '');
		if ($code === '') {
			continue;
		}

		$name = '';
		foreach (array_slice($cells, $column + 1) as $cell) {
			$name = gamesAoe2Clean($cell);
			if ($name !== '') {
				break;
			}
		}

		if ($name === '') {
			$game = GAMES_AOE2_GAME_NAMES[$code] ?? null;
		}
		else if ($game !== null) {
			$names[$game . '|' . $code] = $name;
		}
	}

	fclose($stream);

	return $names;
}

/// Every filter the page offers. A filter keeps the scenarios whose `field` is
/// one of the ticked options; its options come from the sheet itself, so every
/// option matches something. `groups` lays a filter's options out in rows, and
/// `groupFilter` names the filter whose unticked options dim a row.
function gamesAoe2Filters($scenarios, $packNames = [], $done = []) {
	$games = [];
	$packs = [];
	foreach ($scenarios as $scenario) {
		$games[$scenario['game']] = GAMES_AOE2_GAME_LABELS[$scenario['game']] ?? $scenario['game'];
		$packs[$scenario['game']][$scenario['packKey']] = true;
	}

	$known = array_flip(array_keys(GAMES_AOE2_GAME_LABELS));
	uksort($games, fn($a, $b) => ($known[$a] ?? PHP_INT_MAX) <=> ($known[$b] ?? PHP_INT_MAX));

	/// A game's packs in the legend's order, which is the order they came out,
	/// and any the legend lacks after them. The label drops the year the legend
	/// gives, which the title keeps.
	$legendOrder = array_flip(array_keys($packNames));
	$packOptions = [];
	$packTitles = [];
	$packGroups = [];
	foreach (array_keys($games) as $game) {
		$keys = array_keys($packs[$game]);
		usort($keys, fn($a, $b) => ($legendOrder[$a] ?? PHP_INT_MAX) <=> ($legendOrder[$b] ?? PHP_INT_MAX));

		foreach ($keys as $packKey) {
			$name = $packNames[$packKey] ?? substr($packKey, strlen($game) + 1);
			$packOptions[$packKey] = trim(preg_replace('/\s*\(\d{4}\)$/', '', $name));
			$packTitles[$packKey] = $name;
		}
		$packGroups[$game] = $keys;
	}

	/// By default AoE II and its DLC up to GAMES_AOE2_DEFAULT_UP_TO (all of
	/// them, should the sheet drop that one), and every DLC holding a scenario
	/// the account has ticked, with its game, so its row is live (the owner's
	/// choice). A game off by default keeps its DLC ticked, so a link naming it,
	/// or its own box, shows all of it: the DLC only count beside their game.
	$defaultPacks = $packGroups['II'] ?? [];
	$cut = array_search(GAMES_AOE2_DEFAULT_UP_TO, $defaultPacks, true);
	if ($cut !== false) {
		$defaultPacks = array_slice($defaultPacks, 0, $cut + 1);
	}
	$defaultGames = isset($games['II']) ? ['II' => true] : [];
	$defaultPacks = array_fill_keys($defaultPacks, true);
	foreach ($scenarios as $scenario) {
		if (isset($done[$scenario['key']])) {
			$defaultPacks[$scenario['packKey']] = true;
			$defaultGames[$scenario['game']] = true;
		}
	}
	foreach ($packGroups as $game => $keys) {
		if (!isset($defaultGames[$game])) {
			$defaultPacks += array_fill_keys($keys, true);
		}
	}

	return [
		'game' => [
			'labelKey' => 'aoe2.filter.game',
			'field' => 'game',
			'options' => $games,
			'default' => array_values(array_filter(array_keys($games), fn($code) => isset($defaultGames[$code]))),
		],
		'pack' => [
			'labelKey' => 'aoe2.filter.pack',
			'field' => 'packKey',
			'options' => $packOptions,
			'titles' => $packTitles,
			'groups' => $packGroups,
			'groupFilter' => 'game',
			'default' => array_values(array_filter(array_keys($packOptions), fn($key) => isset($defaultPacks[$key]))),
		],
	];
}

/// The options ticked per filter. A filter missing from the query takes its
/// defaults; one that is there takes what was sent and nothing else. The form
/// sends an empty value with every filter, so ticking nothing still arrives as
/// "none" rather than as "missing", and a link naming one filter leaves the
/// others at their defaults.
function gamesAoe2Selection($filters, $query) {
	$selection = [];

	foreach ($filters as $key => $filter) {
		if (!array_key_exists($key, $query)) {
			$selection[$key] = $filter['default'];
			continue;
		}

		$picked = is_array($query[$key]) ? array_filter($query[$key], 'is_string') : [];
		$selection[$key] = array_values(array_intersect(array_map('strval', array_keys($filter['options'])), $picked));
	}

	return $selection;
}

function gamesAoe2Filtered($scenarios, $filters, $selection) {
	return array_values(array_filter($scenarios, function ($scenario) use ($filters, $selection) {
		foreach ($filters as $key => $filter) {
			if (!in_array($scenario[$filter['field']], $selection[$key], true)) {
				return false;
			}
		}
		return true;
	}));
}

/// A colour for a scenario the sheet's colours do not cover: one hue per
/// campaign, by its first appearance in the whole sheet, so a campaign keeps
/// its colour whatever the filters leave out.
function gamesAoe2Hues($scenarios) {
	$hues = [];
	foreach ($scenarios as $scenario) {
		if (!isset($hues[$scenario['campaignKey']])) {
			$hues[$scenario['campaignKey']] = (int)round(fmod(count($hues) * 137.508, 360));
		}
	}

	return $hues;
}

/// A pale row colour for such a campaign, in the sheet's manner: HSV(hue, 25%, 95%).
function gamesAoe2FallbackColour($hue) {
	$value = 0.95;
	$min = $value * 0.75;
	$h = fmod($hue, 360) / 60;
	$x = $min + ($value - $min) * (1 - abs(fmod($h, 2) - 1));
	$rgb = [
		[$value, $x, $min], [$x, $value, $min], [$min, $value, $x],
		[$min, $x, $value], [$x, $min, $value], [$value, $min, $x],
	][(int)$h % 6];

	return vsprintf('#%02x%02x%02x', array_map(fn($channel) => (int)round($channel * 255), $rgb));
}

/// 'dark' or 'light' text for a row of colour $hex shown at $alpha over the
/// near-black page, whichever has more contrast: at half strength a pale yellow
/// still wants black, while a blue or a brown needs the page's own light ink.
function gamesAoe2RowInk($hex, $alpha = GAMES_AOE2_ROW_ALPHA) {
	$luminance = gamesAoe2Luminance($hex, $alpha);

	/// #e6e6e6, the page's --ink, has a luminance of 0.791.
	$againstBlack = ($luminance + 0.05) / 0.05;
	$againstInk = (0.791 + 0.05) / ($luminance + 0.05);

	return $againstBlack >= $againstInk ? 'dark' : 'light';
}

/// The thin edge along a timeline line of colour $hex: black or white, whichever
/// stands out more against it (the owner's wish), so a light line gets a dark
/// edge and a deep blue a light one.
function gamesAoe2EdgeColour($hex) {
	$luminance = gamesAoe2Luminance($hex);

	return (1.05 / ($luminance + 0.05)) > (($luminance + 0.05) / 0.05) ? '#ffffff' : '#000000';
}

/// WCAG relative luminance of $hex, its channels first scaled by $alpha as if
/// shown at that strength over black.
function gamesAoe2Luminance($hex, $alpha = 1) {
	$weights = [0.2126, 0.7152, 0.0722];
	$luminance = 0;
	foreach (sscanf($hex, '#%02x%02x%02x') as $i => $channel) {
		$v = $channel * $alpha / 255;
		$luminance += $weights[$i] * ($v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4);
	}

	return $luminance;
}

/// The dots and lines on a row of colour $hex: its hue at full saturation and
/// value, or white when its saturation is under 5%, a grey with no hue to show
/// (the owner's rule; the threshold was 20%, then 10%, and the colour black).
/// The test is on whole channel values, 20 × (max − min) < max, so no rounding
/// decides which side of 5% a colour falls.
function gamesAoe2MarkerColour($hex) {
	$channels = sscanf($hex, '#%02x%02x%02x');
	$max = max($channels);
	$min = min($channels);

	if (20 * ($max - $min) < $max || $max === $min) {
		return '#ffffff';
	}

	return vsprintf('#%02x%02x%02x', array_map(fn($channel) => (int)round(($channel - $min) / ($max - $min) * 255), $channels));
}

/// To scale, the undated scenarios come after the timeline has reached the
/// present (the owner's wish), each part in the sheet's order.
function gamesAoe2UndatedLast($rows) {
	$dated = array_filter($rows, fn($row) => gamesAoe2YearNumber($row['year'] ?? '') !== null);

	return array_merge(array_values($dated), array_values(array_diff_key($rows, $dated)));
}

/// The first of the steps after which there is nothing dated, where the year
/// axis reaches the present: null when the last step is dated (then the axis
/// runs on past it) or when nothing is.
function gamesAoe2PresentStep($rows, $steps) {
	$present = null;
	for ($step = count($steps) - 1; $step >= 0; $step--) {
		if (gamesAoe2YearNumber($rows[$steps[$step][0]]['year'] ?? '') !== null) {
			return $present;
		}
		$present = $step;
	}

	return null;
}

/// To scale, one row is one year: consecutive rows whose year starts in the
/// same year (`c.1426` and `1426`, `406 to 455` and `406`) are one step, as a
/// list of row indices; a row with no year is always a step of its own.
function gamesAoe2YearSteps($rows) {
	$steps = [];
	$previous = null;
	foreach (array_values($rows) as $i => $row) {
		$year = gamesAoe2YearNumber($row['year'] ?? '');
		if ($year !== null && $year === $previous) {
			$steps[count($steps) - 1][] = $i;
		}
		else {
			$steps[] = [$i];
		}
		$previous = $year;
	}

	return $steps;
}

/// The lanes beside the list: a campaign holds one from its first shown step to
/// its last, taking the lowest free one. A step is one row, or to scale every
/// row of one year (gamesAoe2YearSteps()), so no two campaigns share a lane in
/// a year. A lane stays empty for one step after its campaign ends, so the next
/// campaign in it never reads as a continuation. A dot is its row's marker
/// colour, and a line its campaign's latest row's, so a campaign the sheet
/// colours act by act changes colour along its line. A standalone scenario is
/// a campaign of its own, so a dot and never a line.
///
/// `rows` draws each row on its own, as the list and an unfolded year show
/// them; `steps` draws each year of two or more rows folded into one, with a
/// dot per scenario and the lines that arrive from above for its header. A
/// campaign with several scenarios in a year shows them there side by side,
/// in a run of lanes that takes in its own, which its lane is chosen to leave
/// room for.
function gamesAoe2Lanes($rows, $steps = null) {
	$steps ??= array_map(fn($i) => [$i], array_keys($rows));
	$keys = array_map(fn($row) => empty($row['standalone']) ? ($row['lineKey'] ?? $row['campaignKey']) : $row['key'], $rows);

	$stepOf = [];
	foreach ($steps as $step => $members) {
		foreach ($members as $i) {
			$stepOf[$i] = $step;
		}
	}

	$first = [];
	$last = [];
	foreach ($keys as $i => $key) {
		$first[$key] ??= $i;
		$last[$key] = $i;
	}

	/// How many scenarios each campaign has in each step it is in.
	$presence = [];
	foreach ($keys as $i => $campaign) {
		$presence[$campaign][$stepOf[$i]] = ($presence[$campaign][$stepOf[$i]] ?? 0) + 1;
	}

	/// A folded year shows a campaign's scenarios side by side, in a run of
	/// lanes that takes in its own: the leftmost run free of other dots that
	/// year, which keeps the lanes narrow; another campaign's line may pass
	/// under it. `$runs` gives where each step's run starts, or null when the
	/// lane has no room for one somewhere.
	///
	/// The owner's rule, never a dot straight under another campaign's dot,
	/// holds for dots on their own through the lane sitting out a step after
	/// its campaign ends. A run of several zigzags and reads as one lump, not as
	/// a stop on a line, so it is free of the rule; held to it, the lumps of
	/// 1429 and c.960 doubled the lanes. Nothing can overlap there either: a
	/// zigzagging dot is at least 23px from the middle of one in the row beside,
	/// where two dots need 20.
	$dotAt = [];
	$runs = function ($campaign, $lane) use (&$dotAt, $presence) {
		$starts = [];
		foreach ($presence[$campaign] as $step => $count) {
			for ($start = max(0, $lane - $count + 1); $start <= $lane; $start++) {
				for ($place = $start; $place < $start + $count; $place++) {
					if (isset($dotAt[$step][$place])) {
						continue 2;
					}
				}
				$starts[$step] = $start;
				continue 2;
			}
			return null;
		}
		return $starts;
	};
	$runStart = [];
	$claim = function ($campaign, $starts) use (&$dotAt, $presence) {
		foreach ($presence[$campaign] as $step => $count) {
			for ($place = $starts[$step]; $place < $starts[$step] + $count; $place++) {
				$dotAt[$step][$place] = true;
			}
		}
	};

	$laneOf = [];
	$freeFrom = [];
	foreach ($keys as $i => $campaign) {
		if ($first[$campaign] !== $i) {
			continue;
		}
		$step = $stepOf[$i];

		/// Never a dot straight under another campaign's dot (the owner's rule),
		/// except that a standalone scenario right after one of its own category
		/// (two Historical Battles) goes straight under it, so a run of them
		/// stacks in one lane while different categories alternate; not when
		/// another of this step has stacked there already.
		$lane = null;
		if (!empty($rows[$i]['standalone']) && $step > 0) {
			foreach (array_reverse($steps[$step - 1]) as $previous) {
				if (!empty($rows[$previous]['standalone']) && $rows[$previous]['campaignKey'] === $rows[$i]['campaignKey']
						&& $runs($campaign, $laneOf[$keys[$previous]]) !== null) {
					$lane = $laneOf[$keys[$previous]];
					break;
				}
			}
		}
		if ($lane === null) {
			$lane = 0;
			while ((isset($freeFrom[$lane]) && $freeFrom[$lane] > $step) || $runs($campaign, $lane) === null) {
				$lane++;
			}
		}

		$laneOf[$campaign] = $lane;
		$freeFrom[$lane] = $stepOf[$last[$campaign]] + 2;
		$runStart[$campaign] = $runs($campaign, $lane);
		$claim($campaign, $runStart[$campaign]);
	}

	$layout = [];
	$latest = [];
	foreach ($rows as $i => $row) {
		$latest[$keys[$i]] = $row['marker'] ?? null;

		$lines = [];
		foreach ($laneOf as $campaign => $lane) {
			$starts = $first[$campaign] === $i;
			$ends = $last[$campaign] === $i;

			/// A campaign shown on one row only is a dot with no line.
			if ($first[$campaign] <= $i && $last[$campaign] >= $i && !($starts && $ends)) {
				$lines[] = ['lane' => $lane, 'campaignKey' => $campaign, 'colour' => $latest[$campaign], 'start' => $starts, 'end' => $ends];
			}
		}

		$layout[$i] = ['dot' => $laneOf[$keys[$i]], 'colour' => $row['marker'] ?? null, 'lines' => $lines];
	}

	$folded = [];
	$latest = [];
	foreach ($steps as $step => $members) {
		$arriving = $latest;
		foreach ($members as $i) {
			$latest[$keys[$i]] = $rows[$i]['marker'] ?? null;
		}
		if (count($members) < 2) {
			continue;
		}

		$lines = [];
		$through = [];
		foreach ($laneOf as $campaign => $lane) {
			$from = $stepOf[$first[$campaign]];
			$to = $stepOf[$last[$campaign]];
			if ($from <= $step && $to >= $step && $from !== $to) {
				$lines[] = ['lane' => $lane, 'campaignKey' => $campaign, 'colour' => $latest[$campaign], 'start' => $from === $step, 'end' => $to === $step];
			}
			if ($from < $step && $to >= $step) {
				$through[] = ['lane' => $lane, 'campaignKey' => $campaign, 'colour' => $arriving[$campaign]];
			}
		}

		/// A dot per scenario, a campaign's in the run of lanes kept for them.
		$dots = [];
		$seen = [];
		foreach ($members as $i) {
			$campaign = $keys[$i];
			$seen[$campaign] = ($seen[$campaign] ?? -1) + 1;
			$dots[] = [
				'lane' => $runStart[$campaign][$step] + $seen[$campaign],
				'colour' => $rows[$i]['marker'] ?? null,
				'campaign' => $rows[$i]['campaign'] ?? $campaign,
				'key' => $rows[$i]['key'] ?? $campaign,
			];
		}

		$folded[$step] = ['lines' => $lines, 'through' => $through, 'dots' => $dots];
	}

	$width = $laneOf ? max($laneOf) + 1 : 0;
	foreach ($dotAt as $places) {
		$width = max($width, max(array_keys($places)) + 1);
	}

	return ['width' => $width, 'rows' => $layout, 'steps' => $folded];
}
