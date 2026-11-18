<?php

/// The route preview on a map page: a 720 px square SVG drawn from the points
/// the owner's script found in the map (the Mapgen tab, `map_geometry`), over
/// the world map. The rules are the owner's spec (docs/mtasa.md): everything is
/// computed in output pixels, never scaled by the viewBox or a transform, so a
/// dot is the same size on every map.

const MTASA_PREVIEW_SIZE = 720;
const MTASA_PREVIEW_MARGIN = 28;
/// The smallest span framed, in metres, so a tiny map does not zoom in absurdly.
const MTASA_PREVIEW_MIN_SPAN = 80;
/// GTA's world is -3000..3000 on both axes; the image covers exactly that.
const MTASA_PREVIEW_WORLD = 3000;
const MTASA_PREVIEW_IMAGE = '/modules/mtasa/img/map_2048.png';
const MTASA_PREVIEW_SEA = '#3d6068';
/// A checkpoint with no colour set.
const MTASA_PREVIEW_DEFAULT_COLOUR = '#0000FE';
/// Where every map's own files are, public: js/preview.js fetches a map's
/// meta.xml and .map from the raw address, in the reader's browser, on
/// Increase detail, and Downloads links the folder's page, by the repository's
/// name (the owner's wish: it says whose GitHub the link goes to).
const MTASA_GITHUB_REPO = 'lotsofs/MTASA-Maps';
const MTASA_GITHUB_FOLDERS = 'server/mods/deathmatch/resources/%5Bmaps%5D/%5Bclassics%5D/';
const MTASA_GITHUB_RAW = 'https://raw.githubusercontent.com/' . MTASA_GITHUB_REPO . '/master/' . MTASA_GITHUB_FOLDERS;
const MTASA_GITHUB_TREE = 'https://github.com/' . MTASA_GITHUB_REPO . '/tree/master/' . MTASA_GITHUB_FOLDERS;
/// The preview's sliders, [min, max, step, default], in output pixels: the
/// object dot's radius (a stack's is 2 more), the spawn dot's radius (its arrow
/// grows with it), the checkpoint's radius (the finish's is 3 more), the route
/// line's width and the pickup square's side. The defaults are the spec's. The
/// form is drawn from these and js/preview.js reads the defaults back off it.
const MTASA_PREVIEW_SLIDERS = [
	'objects' => [0, 10, 0.5, 3],
	'spawnpoints' => [0, 10, 0.5, 3],
	'checkpoints' => [0, 16, 0.5, 5],
	'lines' => [0, 10, 0.5, 2],
	'pickups' => [0, 20, 1, 6],
];

/// A pixel value to one decimal, without a trailing .0 or a -0.
function mtasaPreviewNumber($value) {
	$text = rtrim(rtrim(sprintf('%.1f', round($value, 1)), '0'), '.');

	return $text === '-0' ? '0' : $text;
}

/// Black on a light fill and white on a dark one, so black and white
/// checkpoints both show.
function mtasaPreviewStroke($colour) {
	[$r, $g, $b] = sscanf($colour, '#%02x%02x%02x');

	return 0.299 * $r + 0.587 * $g + 0.114 * $b > 90 ? '#000' : '#fff';
}

/// A map's points as js/preview.js takes them, from its `map_geometry` row
/// (points.csv), or null when it has none: `start`, the middle of the spawn
/// grid as [x, y] or null; `radius`, for a map with no checkpoints, the
/// furthest spawnpoint from it, the arena the picture rings (the owner's wish),
/// or null; and the checkpoints as [x, y] in race order. That
/// is all the site stores since 2026-11-18 (the owner's choice); every
/// spawnpoint, the colours, the pickups and the heights come from GitHub on
/// Increase detail. A row written before then holds every spawnpoint, which
/// is averaged the same way, and colours, which are dropped.
function mtasaPreviewData($geometry) {
	if (!$geometry) {
		return null;
	}
	$spawns = json_decode($geometry['spawnpoints'], true) ?: [];
	$checkpoints = array_map(fn($point) => [$point[0], $point[1]], json_decode($geometry['checkpoints'], true) ?: []);
	$start = $spawns
		? [round(array_sum(array_column($spawns, 0)) / count($spawns), 1), round(array_sum(array_column($spawns, 1)) / count($spawns), 1)]
		: null;
	/// Only the one-point rows of points.csv carry a radius; an old row's third
	/// numbers are rotations.
	$radius = count($spawns) === 1 && !$checkpoints && isset($spawns[0][2]) ? (float)$spawns[0][2] : null;

	return $start || $checkpoints ? ['start' => $start, 'radius' => $radius, 'checkpoints' => $checkpoints] : null;
}

/// The route's length with height left out, in whole metres, from the stored
/// points: the middle of the spawn grid through the race's checkpoints, which
/// points.csv lists first, `$used` of them (the script's Checkpoints used by
/// race). Null where the script found no route, as for the 3D length. The
/// points are rounded to 0.1 m, so this can differ by a little from the length
/// js/analysis.js works out from the map's own files.
function mtasaRouteLength2d($data, $used) {
	if (!$data || !$data['start'] || $used < 1) {
		return null;
	}
	[$x, $y] = $data['start'];
	$length = 0.0;
	foreach (array_slice($data['checkpoints'], 0, $used) as $checkpoint) {
		$length += hypot($checkpoint[0] - $x, $checkpoint[1] - $y);
		[$x, $y] = $checkpoint;
	}

	return (int)round($length);
}

/// The SVG for one map's points (mtasaPreviewData()), or null when it has
/// none. It is what the page shows first and without JavaScript; with it,
/// js/preview.js draws the picture again under the reader's settings, and
/// again with everything once Increase detail has fetched the map's files.
/// From the stored points alone it draws the route in the default colour,
/// from the middle of the spawn grid, with no spawnpoints or pickups (the
/// owner's choice), their layers left empty.
function mtasaPreviewSvg($data, $mapId) {
	if (!$data) {
		return null;
	}

	$checkpoints = $data['checkpoints'];
	$framed = $data['start'] ? array_merge([$data['start']], $checkpoints) : $checkpoints;
	/// A derby's or freeroam's arena is framed whole.
	$radius = $data['radius'] ?? null;
	if ($radius !== null && $data['start']) {
		[$x, $y] = $data['start'];
		array_push($framed, [$x - $radius, $y - $radius], [$x + $radius, $y + $radius]);
	}
	$xs = array_column($framed, 0);
	$ys = array_column($framed, 1);
	$cx = (min($xs) + max($xs)) / 2;
	$cy = (min($ys) + max($ys)) / 2;
	$span = max(max($xs) - min($xs), max($ys) - min($ys), MTASA_PREVIEW_MIN_SPAN);
	$scale = (MTASA_PREVIEW_SIZE - 2 * MTASA_PREVIEW_MARGIN) / $span;
	$half = MTASA_PREVIEW_SIZE / 2;

	/// North is up, so y flips.
	$px = fn($x) => mtasaPreviewNumber($half + ($x - $cx) * $scale);
	$py = fn($y) => mtasaPreviewNumber($half - ($y - $cy) * $scale);
	$colour = MTASA_PREVIEW_DEFAULT_COLOUR;

	$size = MTASA_PREVIEW_SIZE;
	$label = htmlspecialchars(t('map.previewLabel', ['folder' => 'z(' . (int)$mapId . ')']));
	$svg = [];
	/// No <title>: the browser's own tooltip would show over the hover boxes.
	$svg[] = "<svg class=\"mtasaPreview\" xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 {$size} {$size}\" role=\"img\" aria-label=\"{$label}\">";

	$image = mtasaPreviewNumber(2 * MTASA_PREVIEW_WORLD * $scale);
	$svg[] = '<g id="previewBackdrop">'
		. "<rect width=\"{$size}\" height=\"{$size}\" fill=\"" . MTASA_PREVIEW_SEA . '"/>'
		. '<image href="' . htmlspecialchars(asset(MTASA_PREVIEW_IMAGE)) . '" x="' . $px(-MTASA_PREVIEW_WORLD) . '" y="' . $py(MTASA_PREVIEW_WORLD) . "\" width=\"{$image}\" height=\"{$image}\"/>"
		. '</g>';

	/// A map with no checkpoints, a derby or freeroam, has its arena ringed
	/// (the owner's wish): round the middle of the spawn grid, as far as its
	/// furthest spawnpoint, white so it reads on land and sea.
	$arena = $radius !== null && $data['start']
		? '<circle cx="' . $px($data['start'][0]) . '" cy="' . $py($data['start'][1]) . '" r="' . mtasaPreviewNumber($radius * $scale) . '"/>'
		: '';
	$svg[] = '<g id="previewArena" fill="#ffffff" fill-opacity="0.1" stroke="#ffffff" stroke-opacity="0.7" stroke-width="2">' . $arena . '</g>';

	/// The route from the middle of the spawn grid through the checkpoints.
	$route = [];
	$from = $data['start'];
	foreach ($checkpoints as $to) {
		if ($from) {
			$route[] = '<line x1="' . $px($from[0]) . '" y1="' . $py($from[1]) . '" x2="' . $px($to[0]) . '" y2="' . $py($to[1]) . "\" stroke=\"{$colour}\"/>";
		}
		$from = $to;
	}
	/// The route over the pickups (the owner's wish), under the checkpoints.
	$svg[] = '<g id="previewPickups" stroke="#000" stroke-width="1"></g>';
	$svg[] = '<g id="previewRoute" stroke-width="2" stroke-linecap="round" fill="none">' . implode('', $route) . '</g>';

	$dots = [];
	$last = count($checkpoints) - 1;
	foreach ($checkpoints as $i => $checkpoint) {
		$dots[] = '<circle cx="' . $px($checkpoint[0]) . '" cy="' . $py($checkpoint[1]) . '" r="' . ($i === $last ? 8 : 5) . "\" fill=\"{$colour}\" stroke=\"" . mtasaPreviewStroke($colour) . '"/>';
	}
	$svg[] = '<g id="previewCheckpoints" stroke-width="1.2">' . implode('', $dots) . '</g>';
	$svg[] = '<g id="previewSpawnpoints" fill="#fff" stroke="#000" stroke-width="1" stroke-linejoin="round"></g>';

	$svg[] = '</svg>';

	return implode("\n", $svg);
}
