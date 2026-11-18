<?php

require_once __DIR__ . '/../../app/modules/mtasa/compare.php';
require_once __DIR__ . '/../../app/modules/mtasa/preview.php';

/// The helpers are local to this file: no other case file talks to the mtasa module.
const MTASA_DB = 'mtasa_test.sqlite';
const MTASA_NUMBERED_FIXTURE = __DIR__ . '/../fixtures/mtasa_numbered.csv';
const MTASA_AI_FIXTURE = __DIR__ . '/../fixtures/mtasa_ai.csv';
const MTASA_MAPGEN_FIXTURE = __DIR__ . '/../fixtures/mtasa_mapgen.csv';

function mtasaDb($ctx) {
	return $ctx->dbFor(MTASA_DB);
}

function mtasaCount($ctx, $table) {
	return (int)mtasaDb($ctx)->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

function mtasaRow($ctx, $sql, $params = []) {
	$stmt = mtasaDb($ctx)->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetch() ?: null;
}

function mtasaRegister($ctx, $name, $password, $inviteCode = '') {
	return $ctx->postForm('/mtasa/register', [
		'csrf_token' => $ctx->csrfTokenFrom('/mtasa/register'),
		'account_name' => $name,
		'password' => $password,
		'password_confirm' => $password,
		'invite_code' => $inviteCode,
	]);
}

function mtasaLogOut($ctx) {
	return $ctx->postForm('/mtasa/logout', ['csrf_token' => $ctx->csrfTokenFrom('/mtasa')]);
}

function mtasaLogIn($ctx, $name, $password = 'mtasa password') {
	mtasaLogOut($ctx);

	return $ctx->postForm('/mtasa/login', [
		'csrf_token' => $ctx->csrfTokenFrom('/mtasa/login'),
		'account_name' => $name,
		'password' => $password,
	]);
}

function mtasaMakeInvite($ctx) {
	$ctx->postForm('/mtasa/accounts', ['csrf_token' => $ctx->csrfTokenFrom('/mtasa/accounts'), 'action' => 'invite']);

	return mtasaRow($ctx, "SELECT code FROM invite WHERE used_at IS NULL AND revoked_at IS NULL ORDER BY id DESC LIMIT 1")['code'] ?? '';
}

function mtasaImportPost($ctx, $token = null) {
	return $ctx->postForm('/mtasa/import', ['csrf_token' => $token ?? $ctx->csrfTokenFrom('/mtasa')]);
}

/// The fixture's rows keyed by Number.
function mtasaFixture($path) {
	$lines = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));
	$header = array_shift($lines);
	$rows = [];
	foreach ($lines as $cells) {
		$row = array_combine($header, array_pad($cells, count($header), ''));
		$rows[(int)$row['Number']] = $row;
	}

	return $rows;
}

/// The Mapgen fixture as `id => [spawnpoints, checkpoints, pickups]`, decoded.
function mtasaMapgenFixture() {
	$stream = fopen(MTASA_MAPGEN_FIXTURE, 'r');
	$header = fgetcsv($stream, 0, ',', '"', '');
	$rows = [];
	while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
		$row = array_combine($header, $cells);
		$rows[(int)substr($row['folder'], 2, -1)] = array_map(fn($cell) => json_decode($cell, true), array_slice($row, 1));
	}
	fclose($stream);

	return $rows;
}

/// A map page's preview SVG, or null.
function mtasaPreview($ctx, $id) {
	$body = $ctx->get('/mtasa/map?id=' . $id)['body'];

	return preg_match('#<svg class="mtasaPreview".*?</svg>#s', $body, $m) ? $m[0] : null;
}

/// One layer of a preview, by its id.
function mtasaPreviewLayer($svg, $layer) {
	return preg_match('#<g id="' . $layer . '"[^>]*>(.*?)</g>(?=\n|$)#s', $svg, $m) ? $m[1] : '';
}

/// The list's rows as `id => [cell text, …]`, in page order.
function mtasaListRows($body) {
	preg_match_all('#<tr data-map-id="(\d+)">(.*?)</tr>#s', $body, $rows, PREG_SET_ORDER);
	$read = [];
	foreach ($rows as $row) {
		preg_match_all('#<td[^>]*>(.*?)</td>#s', $row[2], $cells);
		$read[(int)$row[1]] = array_map(fn($cell) => trim(html_entity_decode(strip_tags($cell))), $cells[1]);
	}

	return $read;
}

/// Every row of the list under these query parameters, every page of it.
function mtasaWholeList($ctx, $query) {
	$rows = [];
	for ($page = 1; $page < 100; $page++) {
		/// Every map, the ones Errors leaves out by default too.
		$body = $ctx->get('/mtasa?' . http_build_query($query + ['page' => $page]) . '&errors%5B%5D=')['body'];
		$rows += mtasaListRows($body);
		if (strpos($body, 'rel="next"') === false) {
			break;
		}
	}

	return $rows;
}


function mtasaListTotal($body) {
	return preg_match('#id="mtasaCount">(\d+) maps<#', $body, $m) ? (int)$m[1] : null;
}

function mtasaField($ctx, $mapId, $field, $value) {
	return $ctx->post('/mtasa/ajax/map-field', ['mapId' => $mapId, 'field' => $field, 'value' => $value]);
}

function mtasaComment($ctx, $payload) {
	return $ctx->post('/mtasa/ajax/comment', $payload);
}

function mtasaLastCommentId($ctx) {
	return (int)mtasaDb($ctx)->query("SELECT COALESCE(MAX(id), 0) FROM map_comment")->fetchColumn();
}

return [

	'signed out, an empty catalogue says so and offers no import' => function ($ctx) {
		$ctx->newSession();
		$response = $ctx->get('/mtasa');
		assertSame(200, $response['status'], 'the list is public');
		assertContains('No maps yet.', $response['body'], 'and says it is empty');
		assertTrue(strpos($response['body'], 'mtasaImportForm') === false, 'with no import button for a visitor');
		assertContains('<meta name="robots" content="noindex">', $response['body'], 'and is kept out of search results');
	},

	'the first account is admin and is offered the import' => function ($ctx) {
		$response = mtasaRegister($ctx, 'mtasa_admin', 'mtasa password');
		assertSame(302, $response['status'], 'registering signs in');
		assertSame(1, (int)mtasaRow($ctx, "SELECT is_admin FROM account WHERE account_name = 'mtasa_admin'")['is_admin'], 'the first account is an admin');
		assertContains('id="mtasaImportForm"', $ctx->get('/mtasa')['body'], 'and sees the import button');
	},

	'nobody but an admin, with the form token, can import' => function ($ctx) {
		$invite = mtasaMakeInvite($ctx);
		assertTrue($invite !== '', 'an admin makes an invite');

		mtasaImportPost($ctx, 'not the token');
		assertSame(0, mtasaCount($ctx, 'map'), 'a forged post imports nothing');

		mtasaLogOut($ctx);
		mtasaImportPost($ctx);
		assertSame(0, mtasaCount($ctx, 'map'), 'nor does a visitor');

		mtasaRegister($ctx, 'mtasa_reader', 'mtasa password', $invite);
		assertSame(0, (int)mtasaRow($ctx, "SELECT is_admin FROM account WHERE account_name = 'mtasa_reader'")['is_admin'], 'the second account is not an admin');
		assertTrue(strpos($ctx->get('/mtasa')['body'], 'mtasaImportForm') === false, 'and is offered no import');
		mtasaImportPost($ctx);
		assertSame(0, mtasaCount($ctx, 'map'), 'nor imports anything');
	},

	'a sheet missing a column imports nothing' => function ($ctx) {
		mtasaLogIn($ctx, 'mtasa_admin');
		$source = $ctx->path('mtasa_source/numbered.csv');
		$original = file_get_contents($source);
		file_put_contents($source, preg_replace('/,Status,/', ',State,', $original, 1));
		try {
			$response = mtasaImportPost($ctx);
		}
		finally {
			file_put_contents($source, $original);
		}
		assertContains('There is no &quot;Status&quot; column in the sheet&#039;s Numbered tab. Nothing was imported.', $response['body'], 'the page says which column is missing');
		assertSame(0, mtasaCount($ctx, 'map') + mtasaCount($ctx, 'map_analysis') + mtasaCount($ctx, 'map_geometry'), 'and nothing was written');
	},

	'a Mapgen cell that is not a list of points imports nothing' => function ($ctx) {
		$source = $ctx->path('mtasa_source/mapgen.csv');
		$original = file_get_contents($source);
		file_put_contents($source, preg_replace('/^"?z\(84\)"?,"\[\[/m', 'z(84),"[[""x"",', $original, 1));
		try {
			$response = mtasaImportPost($ctx);
		}
		finally {
			file_put_contents($source, $original);
		}
		assertContains('Map 84 has a spawnpoints cell in points.csv that is not a list of points. Nothing was imported.', $response['body'], 'the page names the map and the column');
		assertSame(0, mtasaCount($ctx, 'map') + mtasaCount($ctx, 'map_analysis') + mtasaCount($ctx, 'map_geometry'), 'and nothing was written');
	},

	'the import reads all three tabs into the catalogue' => function ($ctx) {
		$numbered = mtasaFixture(MTASA_NUMBERED_FIXTURE);
		$ai = mtasaFixture(MTASA_AI_FIXTURE);
		$mapgen = mtasaMapgenFixture();

		$response = mtasaImportPost($ctx);
		assertSame(303, $response['status'], 'importing redirects to the list');
		assertSame('/mtasa?imported=' . count($numbered) . '&analysed=' . count($ai) . '&drawn=' . count($mapgen), $response['location'], 'with the counts');
		assertContains('Imported ' . count($numbered) . ' maps, ' . count($ai) . ' of them with analysis and ' . count($mapgen) . ' with a preview.', $ctx->get($response['location'])['body'], 'which the list reports');
		assertSame(count($numbered), mtasaCount($ctx, 'map'), 'a row per map');
		assertSame(count($ai), mtasaCount($ctx, 'map_analysis'), 'and its analysis');
		assertSame(count($mapgen), mtasaCount($ctx, 'map_geometry'), 'and its points');
		assertSame($mapgen[84]['checkpoints'], json_decode(mtasaRow($ctx, "SELECT checkpoints FROM map_geometry WHERE map_id = 84")['checkpoints'], true), 'kept as the sheet has them');

		$map = mtasaRow($ctx, "SELECT * FROM map WHERE id = 78");
		assertSame('3Lap Dirtring', $map['original_name'], 'the owner\'s names');
		assertSame($numbered[78]['Vid'], $map['video'], 'the video');
		assertSame('Named Available', $map['status'], 'the status');
		assertSame('Race', $map['gamemode'], 'the gamemode');
		assertSame('Pre-2008', $map['category'], 'the category');
		assertSame(78, (int)$map['duplicate_of'], 'its own Duplicate of, marking it an original');

		$finished = array_key_first(array_filter($numbered, fn($row) => $row['Finished'] === 'No'));
		assertSame(0, (int)mtasaRow($ctx, "SELECT finished FROM map WHERE id = ?", [$finished])['finished'], 'Finished "No" is a false boolean');
		assertSame(null, mtasaRow($ctx, "SELECT finished FROM map WHERE id = 79")['finished'], 'and a blank one none');

		$analysis = mtasaRow($ctx, "SELECT * FROM map_analysis WHERE map_id = 78");
		assertSame('SFAIR', $analysis['start_zone'], 'the analysis, a zone stored by its game label');
		assertSame(1, (int)$analysis['returns_to_start'], '"yes" is a true boolean');
		assertSame(null, $analysis['respawn_enabled'], 'and the script\'s "null" none');
		assertSame(10, (int)$analysis['checkpoints'], 'counts are numbers');

		$copy = array_key_first(array_filter($ai, fn($row) => $row['Duplicate similarity'] !== ''));
		assertSame((int)rtrim($ai[$copy]['Duplicate similarity'], '%'), (int)mtasaRow($ctx, "SELECT duplicate_similarity FROM map_analysis WHERE map_id = ?", [$copy])['duplicate_similarity'], 'a similarity is a number without its %');

		$respawnOn = array_key_first(array_filter($ai, fn($row) => $row['Respawn enabled'] === 'true'));
		assertSame(1, (int)mtasaRow($ctx, "SELECT respawn_enabled FROM map_analysis WHERE map_id = ?", [$respawnOn])['respawn_enabled'], '"true" is a true boolean');
	},

	'the import needs no Actual No., which the sheet no longer has' => function ($ctx) {
		$numbered = mtasaFixture(MTASA_NUMBERED_FIXTURE);
		assertTrue(!array_key_exists('Actual No.', reset($numbered)), 'the fixture, like the sheet, has no Actual No. column');
		assertSame(count($numbered), count(mtasaWholeList($ctx, [])), 'and every map was imported, the list showing them all');
		assertTrue(strpos($ctx->get('/mtasa/map?id=78')['body'], 'Actual No') === false, 'the map page names no Actual No.');
	},

	'the preview is a 720 px square in the spec\'s layers' => function ($ctx) {
		$svg = mtasaPreview($ctx, 84);
		assertTrue($svg !== null, 'a map with points has a preview');
		assertContains('width="720" height="720" viewBox="0 0 720 720"', $svg, 'drawn in output pixels');
		$order = array_map(fn($id) => strpos($svg, 'id="' . $id . '"'), ['previewBackdrop', 'previewPickups', 'previewRoute', 'previewCheckpoints', 'previewSpawnpoints']);
		$sorted = $order;
		sort($sorted);
		assertTrue(!in_array(false, $order, true) && $order === $sorted, 'backdrop, pickups, route, checkpoints, spawnpoints, bottom to top');
		assertContains('<rect width="720" height="720" fill="#3d6068"/>', $svg, 'over the sea');
		assertContains('href="/modules/mtasa/img/map_2048.png', $svg, 'and the world map');
		assertSame(null, mtasaPreview($ctx, 2), 'a map the script found nothing in has none');
	},

	'the preview has settings beside it, increasing its detail from GitHub' => function ($ctx) {
		assertTrue(strpos(mtasaPreview($ctx, 84), '<title>') === false, 'the picture has no <title>, whose own tooltip would cover the hover boxes');
		$body = $ctx->get('/mtasa/map?id=84')['body'];
		assertContains('data-github="https://raw.githubusercontent.com/lotsofs/MTASA-Maps/master/server/mods/deathmatch/resources/%5Bmaps%5D/%5Bclassics%5D/z(84)/"', $body, 'the raw address of the map\'s folder');
		preg_match('#<form class="mtasaPreviewSettings" hidden>(.*?)</form>#s', $body, $form);
		assertTrue(isset($form[1]), 'the settings, which the script shows');
		assertContains('<button type="button" class="mtasaPreviewLoad">Increase detail</button>', $form[1], 'a button saying what it does');
		assertTrue(preg_match('#<p class="mtasaPreviewHint">[^<]*By clicking the button below, you consent to your browser sending your IP address to GitHub\.</p>\s*<button type="button" class="mtasaPreviewLoad">#', $form[1]) === 1, 'and above it, not in a hover box, that clicking it consents to the IP address going to GitHub');
		assertContains('<p class="mtasaPreviewSettingNote" id="mtasaColoursNote" hidden>Map colours need Increase detail: until then every checkpoint is the default blue.</p>', $form[1], 'and a note, which the script shows, for colours that wait for it');
		assertContains('<input type="checkbox" name="always">', $form[1], 'a switch to always do it');
		assertContains('<option value="map" selected>Map colours</option>', $form[1], 'checkpoints in the map\'s colours');
		assertContains('<option value="blue">Blue</option>', $form[1], 'or all blue');
		assertContains('<option value="track" selected>Track</option>', $form[1], 'scaled to the track');
		assertContains('<option value="all">All elements</option>', $form[1], 'or to everything');
		foreach (MTASA_PREVIEW_SLIDERS as $name => [$min, $max, $step, $default]) {
			assertContains('<input type="range" name="' . $name . '" min="' . $min . '" max="' . $max . '" step="' . $step . '" value="' . $default . '">', $form[1], 'a slider for ' . $name);
		}
		assertTrue(preg_match('#<section class="mtasaSection">\s*<h2>Downloads</h2>\s*<div class="mtasaField">\s*<div class="mtasaFieldLabel">GitHub</div>\s*<div class="mtasaFieldValue">\s*<span class="mtasaFieldShown"><a href="https://github.com/lotsofs/MTASA-Maps/tree/master/server/mods/deathmatch/resources/%5Bmaps%5D/%5Bclassics%5D/z\(84\)" target="_blank" rel="noopener">lotsofs/MTASA-Maps</a></span>#', $body) === 1, 'the folder\'s link in a Downloads section of its own, a GitHub row like any field\'s, naming the repository');
		assertTrue(strpos($body, '<h2>Downloads</h2>') > strpos($body, '</form>'), 'after the Map, not beside the picture');
		assertContains('/modules/mtasa/js/preview.js', $body, 'loading the script that draws it');
		assertTrue(preg_match('#<figure class="mtasaPreviewFigure".*?<div class="mtasaProfile" id="mtasaProfile" hidden></div>\s*</figure>#s', $body) === 1, 'and a place under the picture for the height profile, which only the map\'s files can give');
		assertTrue(strpos($ctx->get('/mtasa/map?id=2')['body'], 'mtasaPreviewSettings') === false, 'a map with no preview has no settings');
	},

	'the page carries the stored points for the script to draw' => function ($ctx) {
		$map = mtasaMapgenFixture()[84];
		preg_match('#<script type="application/json" id="previewData">(.*?)</script>#s', $ctx->get('/mtasa/map?id=84')['body'], $data);
		$data = json_decode($data[1] ?? '', true);
		assertSame(1, count($map['spawnpoints']), 'points.csv stores one spawn point, the middle of the grid');
		assertSame(['start' => $map['spawnpoints'][0], 'radius' => null, 'checkpoints' => $map['checkpoints']], $data, 'and the checkpoints, x and y only, as imported, with no arena for a race');
	},

	'the page carries what it compares GitHub against, and the analysis\'s files are served' => function ($ctx) {
		$ai = mtasaFixture(MTASA_AI_FIXTURE)[84];
		$body = $ctx->get('/mtasa/map?id=84')['body'];
		preg_match('#<script type="application/json" id="mapStored">(.*?)</script>#s', $body, $stored);
		$stored = json_decode($stored[1] ?? '', true);
		assertSame(['type' => 'int', 'value' => (int)$ai['Checkpoints']], $stored['checkpoints'] ?? null, 'the stored values of Route, Content and Settings, with their types');
		assertSame(['type' => 'vehicles', 'value' => $ai['Start vehicles']], $stored['start_vehicles'] ?? null, 'as the import stored them, by id');
		assertTrue(isset($stored['author']) && !isset($stored['status']) && !isset($stored['original_name']), 'every script column, and none of the owner\'s');
		assertContains('/modules/mtasa/js/analysis.js', $body, 'the analysis is loaded');
		assertSame(200, $ctx->get('/modules/mtasa/js/analysis.js')['status'], 'and served');
		preg_match('#data-zones="([^"]+)"#', $body, $zones);
		$zones = json_decode($ctx->get(html_entity_decode(preg_replace('#\?.*$#', '', $zones[1] ?? '')))['body'], true);
		assertSame([9, 369], [count($zones['counties'] ?? []), count($zones['zones'] ?? [])], 'the zones file has the 9 counties and 369 zones');
		assertSame(5, count($zones['counties'][0]), 'a county is a name and its x and y box');
		assertSame(7, count($zones['zones'][0]), 'a zone a name and its box in three dimensions');
	},

	'Route and Content lose Laps and Armed vehicles, and gain a 2D route length' => function ($ctx) {
		$body = $ctx->get('/mtasa/map?id=84')['body'];
		assertTrue(strpos($body, 'id="field-laps"') === false && strpos($body, 'id="field-armed_vehicles"') === false, 'no Laps or Armed vehicles row');
		/// The 2D length, from the middle of the spawn grid through the raced checkpoints.
		$map = mtasaMapgenFixture()[84];
		$used = (int)mtasaFixture(MTASA_AI_FIXTURE)[84]['Checkpoints used by race'];
		[$x, $y] = [array_sum(array_column($map['spawnpoints'], 0)) / count($map['spawnpoints']), array_sum(array_column($map['spawnpoints'], 1)) / count($map['spawnpoints'])];
		$length = 0.0;
		foreach (array_slice($map['checkpoints'], 0, $used) as $checkpoint) {
			$length += hypot($checkpoint[0] - $x, $checkpoint[1] - $y);
			[$x, $y] = $checkpoint;
		}
		assertTrue(preg_match('#id="field-route_length_2d"[^>]*>\s*<div class="mtasaFieldLabel">Route length 2D</div>\s*<div class="mtasaFieldValue">\s*<span class="mtasaFieldShown">' . sprintf('%.3f', round($length) / 1000) . 'km</span>#', $body) === 1, 'Route length 2D reads ' . (int)round($length));
	},

	'ring checkpoints read with their share, and vehicles stored by id read by name' => function ($ctx) {
		/// 100 has 1 ring checkpoint of 26.
		assertTrue(preg_match('#id="field-ring_checkpoints".*?<span class="mtasaFieldShown">1 \(4%\)</span>#s', $ctx->get('/mtasa/map?id=100')['body']) === 1, 'ring checkpoints as 1 (4%) of 26');
		$ai = mtasaFixture(MTASA_AI_FIXTURE)[109];
		assertTrue(preg_match('/(^|, )599( x\d+)?(, |$)/', $ai['Start vehicles']) === 1, '109 starts a 599, stored by id: ' . $ai['Start vehicles']);
		preg_match('#id="field-start_vehicles".*?<span class="mtasaFieldShown">([^<]*)</span>#s', $ctx->get('/mtasa/map?id=109')['body'], $vehicles);
		assertTrue(strpos($vehicles[1] ?? '', 'Ranger') !== false && strpos($vehicles[1] ?? '', 'Police Ranger') === false && preg_match('/\b\d{3}\b/', $vehicles[1] ?? '') === 0, 'named on the page, 599 as Ranger: ' . ($vehicles[1] ?? ''));
	},

	'the server\'s picture is the route from the middle of the grid, in the default blue' => function ($ctx) {
		/// A made-up map, taken out again at the end.
		$db = mtasaDb($ctx);
		$id = 99999;
		$db->prepare("INSERT INTO map (id) VALUES (?)")->execute([$id]);
		$db->prepare("INSERT INTO map_geometry (map_id, spawnpoints, checkpoints) VALUES (?, ?, ?)")->execute([$id, '[[0,0]]', '[[100,0],[100,100]]']);
		/// The middle of the grid and the checkpoints span 100 m round (50, 50): 6.64 px a metre.
		$svg = mtasaPreview($ctx, $id);
		preg_match('#<circle cx="([-\d.]+)" cy="([-\d.]+)"#', $svg, $first);
		assertSame(['692', '692'], [$first[1] ?? null, $first[2] ?? null], 'the track fills the picture');
		assertContains('<line x1="28" y1="692" x2="692" y2="692" stroke="#0000FE"/>', mtasaPreviewLayer($svg, 'previewRoute'), 'the line starts from the stored middle of the grid');
		assertSame('', mtasaPreviewLayer($svg, 'previewSpawnpoints') . mtasaPreviewLayer($svg, 'previewPickups'), 'with no spawnpoints or pickups, which only GitHub has');
		$db->prepare("DELETE FROM map_geometry WHERE map_id = ?")->execute([$id]);
		$db->prepare("DELETE FROM map WHERE id = ?")->execute([$id]);
	},

	'the route fades between colours only when the reader picks the gradient' => function ($ctx) {
		/// 87's checkpoints change colour on GitHub; the server, without the colours, draws no fades.
		$route = mtasaPreviewLayer(mtasaPreview($ctx, 87), 'previewRoute');
		assertTrue(strpos($route, 'Gradient') === false && strpos($route, 'url(') === false, 'the server draws no fades');
		$body = $ctx->get('/mtasa/map?id=87')['body'];
		assertContains('<option value="gradient">Map colours (gradient)</option>', $body, 'the gradient is an option of the checkpoint colours');
		assertContains('<option value="rainbow">Rainbow</option>', $body, 'and so is the rainbow');
	},

	'z(84) is a park circuit in the default blue, from the middle of its grid' => function ($ctx) {
		$map = mtasaMapgenFixture()[84];
		$svg = mtasaPreview($ctx, 84);
		preg_match_all('#<circle [^>]*fill="([^"]+)" stroke="([^"]+)"#', mtasaPreviewLayer($svg, 'previewCheckpoints'), $dots);
		assertSame(count($map['checkpoints']), count($dots[1]), 'a dot per checkpoint');
		assertSame(['#0000FE'], array_values(array_unique($dots[1])), 'none has a colour set, so all are #0000FE');
		assertSame(['#fff'], array_values(array_unique($dots[2])), 'dark, so stroked white');
		assertSame(count($map['checkpoints']), substr_count(mtasaPreviewLayer($svg, 'previewRoute'), '<line '), 'a link between each pair, and the spawn line');
		assertTrue(strpos($svg, 'rotate(') === false, 'and no spawn arrows, which wait for Increase detail');
		assertContains('r="8"', $svg, 'the finish is bigger');
	},

	'a derby or freeroam has its arena ringed, round the middle of the grid to its furthest spawnpoint' => function ($ctx) {
		/// 79 has no checkpoints: points.csv adds its radius to the spawn point.
		$spawn = mtasaMapgenFixture()[79]['spawnpoints'];
		assertTrue(count($spawn) === 1 && count($spawn[0]) === 3 && $spawn[0][2] > 0, 'points.csv stores the arena\'s radius: ' . json_encode($spawn));
		$svg = mtasaPreview($ctx, 79);
		preg_match('#<g id="previewArena"[^>]*><circle cx="([-\d.]+)" cy="([-\d.]+)" r="([\d.]+)"/></g>#', $svg, $arena);
		assertTrue(isset($arena[3]), 'the server rings it');
		/// Framed whole, round its middle: the picture spans the circle's box, or 80 m for a small one, less 2 × 28 px of margin.
		$radius = $spawn[0][2];
		assertSame(['360', '360', rtrim(rtrim(sprintf('%.1f', $radius * 664 / max(2 * $radius, 80)), '0'), '.')], [$arena[1], $arena[2], $arena[3]], 'centred, as wide as its furthest spawnpoint');
		assertSame('', mtasaPreviewLayer(mtasaPreview($ctx, 84), 'previewArena'), 'a race has none');
	},

	'z(79) is a derby arena off the south edge of the world, on plain sea' => function ($ctx) {
		$svg = mtasaPreview($ctx, 79);
		preg_match('#<image [^>]*y="([-\d.]+)" width="([\d.]+)"#', $svg, $image);
		assertTrue((float)$image[1] + (float)$image[2] < 0, 'the world map ends above the box');
		assertSame(0, substr_count(mtasaPreviewLayer($svg, 'previewCheckpoints'), '<circle'), 'no checkpoints');
		/// Its four rows of spawnpoints and its nitro come from GitHub (checked in Chrome).
		assertSame('', mtasaPreviewLayer($svg, 'previewSpawnpoints') . mtasaPreviewLayer($svg, 'previewPickups'), 'and nothing else before Increase detail');
	},

	'z(98) alternates between two spots, the finish on top' => function ($ctx) {
		$svg = mtasaPreview($ctx, 98);
		preg_match_all('#<circle cx="([^"]+)" cy="([^"]+)" r="(\d)" fill="([^"]+)" stroke="([^"]+)"/>#', mtasaPreviewLayer($svg, 'previewCheckpoints'), $dots, PREG_SET_ORDER);
		/// Its two places differ by tenths of a metre from lap to lap, so dots
		/// within 5 px of each other count as one stack.
		$spots = [];
		foreach ($dots as $dot) {
			$near = array_filter($spots, fn($spot) => abs($spot[0] - $dot[1]) < 5 && abs($spot[1] - $dot[2]) < 5);
			if (!$near) {
				$spots[] = [(float)$dot[1], (float)$dot[2]];
			}
		}
		assertSame(2, count($spots), 'two stacks of dots');
		$finish = end($dots);
		assertSame(['8', '#0000FE', '#fff'], [$finish[3], $finish[4], $finish[5]], 'the finish is r 8, in the default blue until GitHub gives its white');
	},

	'z(2296) and its 1,704 checkpoints render quickly' => function ($ctx) {
		$start = microtime(true);
		$svg = mtasaPreview($ctx, 2296);
		$seconds = microtime(true) - $start;
		assertSame(1704, substr_count(mtasaPreviewLayer($svg, 'previewCheckpoints'), '<circle'), 'every checkpoint');
		assertSame(1703 + 1, substr_count(mtasaPreviewLayer($svg, 'previewRoute'), '<line '), 'every link and the spawn line');
		assertTrue($seconds < 1, sprintf('the page in under a second (%.2f s)', $seconds));
	},

	'a second import does nothing' => function ($ctx) {
		$before = mtasaCount($ctx, 'map');
		$response = mtasaImportPost($ctx);
		assertContains('The site already holds maps, so the import does nothing.', $response['body'], 'it says why');
		assertSame($before, mtasaCount($ctx, 'map'), 'and writes nothing');
		assertTrue(strpos($ctx->get('/mtasa')['body'], 'mtasaImportForm') === false, 'and the button is gone');
	},

	'an admin reloads the analysis from classics.csv, keeping the owner\'s columns' => function ($ctx) {
		$db = mtasaDb($ctx);
		$db->exec("UPDATE map_analysis SET objects = 999, author = 'Changed' WHERE map_id = 84");
		$db->exec("UPDATE map SET custom_name = 'Kept' WHERE id = 84");
		$db->exec("DELETE FROM map_analysis WHERE map_id = 85");
		$reload = fn($token = null) => $ctx->postForm('/mtasa/import', ['csrf_token' => $token ?? $ctx->csrfTokenFrom('/mtasa'), 'action' => 'reload']);

		$ctx->newSession();
		mtasaLogIn($ctx, 'mtasa_reader');
		assertTrue(strpos($ctx->get('/mtasa')['body'], 'mtasaReloadForm') === false, 'a reader is offered no reload');
		$reload();
		assertSame(999, (int)mtasaRow($ctx, "SELECT objects FROM map_analysis WHERE map_id = 84")['objects'], 'and cannot reload');

		$ctx->newSession();
		mtasaLogIn($ctx, 'mtasa_admin');
		assertContains('id="mtasaReloadForm"', $ctx->get('/mtasa')['body'], 'an admin is offered it under the list');
		$response = $reload();
		$ai = mtasaFixture(MTASA_AI_FIXTURE);
		$mapgen = mtasaMapgenFixture();
		assertSame('/mtasa?reloaded=' . count($ai) . '&added=0&drawn=' . count($mapgen), $response['location'], 'with the counts');
		assertContains('Reloaded the analysis of ' . count($ai) . ' maps from classics.csv, 0 of them new to the site, and the points of ' . count($mapgen) . ' from points.csv.', $ctx->get($response['location'])['body'], 'which the list reports');
		assertSame([(int)$ai[84]['Objects'], $ai[84]['Author']], array_values(mtasaRow($ctx, "SELECT objects, author FROM map_analysis WHERE map_id = 84")), 'the script\'s columns are the file\'s again');
		assertTrue(mtasaRow($ctx, "SELECT map_id FROM map_analysis WHERE map_id = 85") !== null, 'a map whose analysis was gone has it back');
		assertSame('Kept', mtasaRow($ctx, "SELECT custom_name FROM map WHERE id = 84")['custom_name'], 'and the owner\'s own columns are untouched');
		$db->exec("UPDATE map SET custom_name = NULL WHERE id = 84");
	},

	'Downloads is on every map, its files to be zipped after Increase detail' => function ($ctx) {
		$section = function ($id) use ($ctx) {
			$body = $ctx->get('/mtasa/map?id=' . $id)['body'];
			$start = strpos($body, '<h2>Downloads</h2>');
			return $start === false ? '' : substr($body, $start, strpos($body, '</section>', $start) - $start);
		};
		assertTrue(preg_match('#<div class="mtasaField" id="mtasaDownloadFiles">\s*<div class="mtasaFieldLabel">Map files</div>\s*<div class="mtasaFieldValue">\s*<span class="mtasaFieldShown"><span class="mtasaAfterDetail">Press Increase detail at the map to show this</span></span>#', $section(84)) === 1, 'a map with files to fetch offers them once Increase detail has them');
		$none = $section(2);
		assertTrue($none !== '' && strpos($none, 'Map files') !== false, 'a map with no folder still has the section and the row');
		assertTrue(strpos($none, 'mtasaAfterDetail') === false && strpos($none, 'lotsofs/MTASA-Maps') === false, 'left empty');
		assertContains('/modules/mtasa/js/zip.js', $ctx->get('/mtasa/map?id=84')['body'], 'the zip writer is loaded');
	},

	'Start, Route and Finish show vehicles and zones by name and lengths in kilometres' => function ($ctx) {
		$body = $ctx->get('/mtasa/map?id=98')['body'];
		$section = fn($from, $to) => substr($body, strpos($body, '<h2>' . $from . '</h2>'), strpos($body, '<h2>' . $to . '</h2>') - strpos($body, '<h2>' . $from . '</h2>'));
		$rows = fn($html) => preg_match_all('#id="field-([a-z_0-9]+)"#', $html, $m) ? $m[1] : [];
		assertSame(['spawnpoints', 'start_vehicles', 'start_zone'], $rows($section('Start', 'Route')), 'Start');
		assertSame(['checkpoints', 'checkpoints_used', 'route_length', 'route_length_2d', 'ring_checkpoints', 'checkpoint_vehicle_changes', 'repair_pickups', 'nitro_pickups', 'vehicle_change_pickups', 'objects', 'top_object_models', 'zones_on_route', 'vehicles_via_changes'], $rows($section('Route', 'Finish')), 'Route');
		assertSame(['finish_zone', 'returns_to_start'], $rows($section('Finish', 'Guesses')), 'Finish');
		assertTrue(strpos($body, 'field-start_city') === false && strpos($body, 'field-cities_on_route') === false, 'no Start city or Cities on route');
		/// 98's spawnpoints are in Esplanade East and the county of San Fierro, stored as ESPE, SF.
		assertSame('ESPE, SF', mtasaFixture(MTASA_AI_FIXTURE)[98]['Start zone'], 'stored by label, commonest first');
		assertTrue(preg_match('#id="field-start_zone".*?<span class="mtasaFieldShown">Esplanade East, San Fierro</span>#s', $body) === 1, 'and named on the page');
		assertTrue(preg_match('#id="field-route_length".*?<span class="mtasaFieldShown">(\d+\.\d{3})km</span>#s', $body, $km) === 1 && $km[1] === sprintf('%.3f', mtasaFixture(MTASA_AI_FIXTURE)[98]['Route length (m)'] / 1000), 'the route length in kilometres');
		assertTrue(preg_match('#id="field-zones_on_route".*?<span class="mtasaFieldShown"><span class="mtasaAfterDetail">Press Increase detail at the map to show this</span></span>#s', $body) === 1, 'zones on route wait for Increase detail');
		/// 100 reaches twelve vehicles by changes: ten named, then the rest counted.
		preg_match('#id="field-vehicles_via_changes".*?<span class="mtasaFieldShown">([^<]*)</span>#s', $ctx->get('/mtasa/map?id=100')['body'], $via);
		$stored = mtasaRow($ctx, "SELECT vehicles_via_changes v FROM map_analysis WHERE map_id = 100")['v'];
		assertTrue(substr_count($stored, ', ') >= 10, 'map 100 reaches more than ten vehicles');
		assertSame(substr_count($stored, ', '), substr_count($via[1] ?? '', ', '), 'and names every one: ' . ($via[1] ?? ''));
		assertTrue(strpos($via[1] ?? '', 'more') === false, 'with no "+N more"');
	},

	'the register page says an invite code is needed, what an account is for, and who to ask' => function ($ctx) {
		mtasaLogOut($ctx);
		$body = $ctx->get('/mtasa/register')['body'];
		assertTrue(preg_match('#<input type="text" id="invite_code" name="invite_code" value="" required aria-describedby="invite_note">\s*</p>\s*<p class="formNote" id="invite_note">An invite code is required to register\. An account lets you comment on any map and its fields; looking through the catalogue needs none\. To get a code, contact the administrator\.</p>#', $body) === 1, 'under the invite code field, tied to it');
	},

	'the list and every map are public, and every page is kept out of search' => function ($ctx) {
		mtasaLogOut($ctx);
		foreach (['/mtasa', '/mtasa/map?id=78', '/mtasa/comments', '/mtasa/login', '/mtasa/register'] as $path) {
			$response = $ctx->get($path);
			assertSame(200, $response['status'], "{$path} opens signed out");
			assertContains('<meta name="robots" content="noindex">', $response['body'], "{$path} is noindex");
		}
		$map = $ctx->get('/mtasa/map?id=78')['body'];
		assertContains('<h1>3Lap Dirtring</h1>', $map, 'a map is headed by its name');
		assertContains('Easter Bay Airport', $map, 'and shows its analysis');
		assertTrue(strpos($map, 'class="mtasaEdit"') === false, 'with nothing to edit for a visitor');
		assertSame(404, $ctx->get('/mtasa/map?id=999999')['status'], 'a map that is not there is a 404');
		assertSame(404, $ctx->get('/mtasa/map?id=abc')['status'], 'and so is nonsense');
	},

	'a map is named by its custom name, else its original, else its title, else its folder' => function ($ctx) {
		$custom = mtasaRow($ctx, "SELECT id, custom_name FROM map WHERE custom_name IS NOT NULL LIMIT 1");
		if ($custom) {
			assertContains('<h1>' . htmlspecialchars($custom['custom_name']) . '</h1>', $ctx->get('/mtasa/map?id=' . $custom['id'])['body'], 'the custom name first');
		}
		$titled = mtasaRow($ctx, "SELECT m.id, a.title FROM map m JOIN map_analysis a ON a.map_id = m.id WHERE m.custom_name IS NULL AND m.original_name IS NULL AND a.title IS NOT NULL LIMIT 1");
		if ($titled) {
			assertContains('<h1>' . htmlspecialchars($titled['title']) . '</h1>', $ctx->get('/mtasa/map?id=' . $titled['id'])['body'], 'the script\'s title when the owner named it nothing');
		}
		assertContains('<h1>z(2)</h1>', $ctx->get('/mtasa/map?id=2')['body'], 'and the folder name when nothing names it');
	},

	'copies are worded as the sheet words them, past decuplicate in the same Latin' => function ($ctx) {
		assertContains('triplicate of 81', $ctx->get('/mtasa/map?id=83')['body'], 'the third of a set is a triplicate');
		assertContains('undecuplicate of 87', $ctx->get('/mtasa/map?id=264')['body'], 'the eleventh an undecuplicate');
		assertContains('duodecuplicate of 87', $ctx->get('/mtasa/map?id=489')['body'], 'the twelfth a duodecuplicate');

		$original = $ctx->get('/mtasa/map?id=87')['body'];
		assertContains('id="copies"', $original, 'an original lists its set');
		$at = strpos($original, 'id="copies"');
		assertTrue($at > strpos($original, '<h2>Duplicates</h2>') && $at < strpos($original, '<h2>Guesses</h2>'), 'under the Duplicates heading');
		assertTrue(preg_match('#<tbody>\s*<tr class="mtasaGroupSelf" aria-current="page">\s*<td><a href="/mtasa/map\?id=87">87</a></td>\s*<td>[^<]*</td>\s*<td>-</td>#', $original) === 1, 'itself first, with a dash for Duplicate of');
		assertContains('<a href="/mtasa/map?id=264">264</a>', $original, 'and each copy, linked');
		assertTrue(strpos($ctx->get('/mtasa/map?id=2')['body'], 'id="copies"') === false, 'a map with no copies lists none');
	},

	'the list remembers its filters in the browser, and Clear forgets them' => function ($ctx) {
		$body = $ctx->get('/mtasa')['body'];
		assertContains('const key = "mtasaListFilters";', $body, 'the script that keeps them is on the list');
		assertTrue(strpos($body, 'const key = "mtasaListFilters";') < strpos($body, 'id="mtasaList"'), 'before the table, so the bare list never shows first');
		assertContains('const names = ["status","gamemode","race_mode","errors","finished","duplicates","comments","q","per"];', $body, 'naming every filter, the search and the page size');
		assertContains('<a href="/mtasa" data-clear-filters>Clear</a>', $body, 'and Clear is marked to forget them');
	},

	'the author column is narrow, with the whole author in its hover box' => function ($ctx) {
		$author = mtasaRow($ctx, "SELECT map_id, author FROM map_analysis WHERE length(author) > 40 ORDER BY map_id LIMIT 1");
		assertTrue($author !== null, 'the fixture has a long author');
		$body = $ctx->get('/mtasa?q=' . $author['map_id'])['body'];
		$text = htmlspecialchars($author['author']);
		assertContains('<td class="mtasaAuthor" data-tooltip="' . $text . '">' . $text . '</td>', $body, 'the cell carries it whole');
		assertContains('.mtasaList td.mtasaAuthor {', $ctx->get('/modules/mtasa/css/styles.css')['body'], 'and the stylesheet narrows it');

		preg_match_all('#<th aria-sort="[a-z]+"><a [^>]*>([^<▲▼]+)#', $body, $m);
		assertSame(['No.', 'Name', 'Author', 'Status', 'Gamemode', 'Checkpoints', 'Finished', 'Video', 'Errors', 'Warnings', 'Copy of', 'Comments', 'Custom description'], array_map('trim', $m[1]), 'the custom description last, Finished and the video between the checkpoints and the errors, the warnings after them, and Actual No., spawnpoints and start city gone');
		$video = mtasaRow($ctx, "SELECT id, video FROM map WHERE video IS NOT NULL AND video <> '' ORDER BY id LIMIT 1");
		assertTrue($video !== null, 'the fixture has a video');
		assertContains('<td class="mtasaVideo">', $body, 'every row has a video cell');
		$link = '<a href="' . htmlspecialchars($video['video']) . '" target="_blank" rel="noopener noreferrer" aria-label="Watch the video" data-tooltip="' . htmlspecialchars($video['video']) . '">▶&#xFE0E;</a>';
		assertContains($link, $ctx->get('/mtasa?q=' . $video['id'] . '&errors%5B%5D=')['body'], 'a video is a ▶ opening it, the link only in its hover box');
		$ctx->newSession();
		mtasaLogIn($ctx, 'mtasa_admin');
		mtasaField($ctx, 79, 'custom_description', "Two lines\nof description");
		$row = $ctx->get('/mtasa?q=79')['body'];
		assertContains('<td class="mtasaDescription" data-tooltip="Two lines' . "\n" . 'of description"', $row, 'whole in its hover box');
		assertContains('>Two lines of description</td>', $row, 'and shown on one line');
		mtasaField($ctx, 79, 'custom_description', '');
	},

	'an admin edits the list in place, and the name opens the map and says where it comes from' => function ($ctx) {
		$ctx->newSession();
		$body = $ctx->get('/mtasa?q=96')['body'];
		assertContains('<td class="mtasaNumber mtasaIdCell"><a href="/mtasa/map?id=96">96</a></td>', $body, 'the number opens the map');
		assertContains('<td class="mtasaName" data-tooltip="Original map name"><a href="/mtasa/map?id=96">3Lap Dirtring</a></td>', $body, 'the name opens the map too, and says where it comes from');
		assertTrue(strpos($body, 'data-edit-field') === false && strpos($body, 'js/list.js') === false, 'a reader edits nothing');
		$titled = mtasaRow($ctx, "SELECT m.id, a.title, a.title_source FROM map m JOIN map_analysis a ON a.map_id = m.id WHERE m.custom_name IS NULL AND m.original_name IS NULL AND a.title IS NOT NULL AND a.title_source IS NOT NULL LIMIT 1");
		assertContains('data-tooltip="' . htmlspecialchars("Gathered name\nSource: " . $titled['title_source']) . '"', $ctx->get('/mtasa?q=' . $titled['id'])['body'], 'a gathered name says what it was gathered from');
		assertContains('<td class="mtasaName" data-tooltip="Folder name: nothing names this map"><a href="/mtasa/map?id=2">z(2)</a></td>', $ctx->get('/mtasa?q=2&errors%5B%5D=')['body'], 'and a folder name says so');
		assertContains('<span class="mtasaGuess">' . htmlspecialchars($titled['title']) . '</span></a></td>', $ctx->get('/mtasa?q=' . $titled['id'])['body'], 'a gathered name, a guess, shows faded');

		mtasaLogIn($ctx, 'mtasa_admin');
		$body = $ctx->get('/mtasa?q=96')['body'];
		assertContains('js/list.js', $body, 'an admin gets the editor');
		assertTrue(strpos($body, '<td class="mtasaName" data-tooltip="Original map name"><a ') !== false, 'the name is not editable here');
		assertContains('data-edit-field="status" data-type="choice"', $body, 'the status');
		assertContains('data-edit-field="gamemode" data-type="choice"', $body, 'the gamemode');
		assertContains('data-edit-field="custom_description" data-type="longtext"', $body, 'and the custom description');

		$guessed = mtasaRow($ctx, "SELECT m.id, a.category_guess FROM map m JOIN map_analysis a ON a.map_id = m.id WHERE m.gamemode IS NULL AND a.category_guess IS NOT NULL ORDER BY m.id LIMIT 1");
		$body = $ctx->get('/mtasa?q=' . $guessed['id'])['body'];
		assertContains('<span class="mtasaGuess" data-tooltip="Gamemode guess">' . $guessed['category_guess'] . '</span>', $body, 'with no gamemode the list shows the guess, faded');
		mtasaField($ctx, $guessed['id'], 'gamemode', 'Teamwork');
		$body = $ctx->get('/mtasa?q=' . $guessed['id'])['body'];
		assertContains('<span class="mtasaChoicePill" data-choice="Teamwork">Teamwork</span>', $body, 'and a set gamemode coloured');
		if ($guessed['category_guess'] !== 'Teamwork') {
			$said = htmlspecialchars('The gamemode guess differs from the gamemode, "Teamwork"');
			assertContains('<span class="mtasaListMismatch" role="img" aria-label="' . $said . '" data-tooltip="' . $said . '">!</span>', $body, 'a guess that differs from it puts a ! in Warnings');
		}
		mtasaField($ctx, $guessed['id'], 'gamemode', '');
		assertTrue(strpos($ctx->get('/mtasa?q=' . $guessed['id'])['body'], 'class="mtasaListMismatch"') === false, 'and none once it is cleared');

		$warned = mtasaRow($ctx, "SELECT map_id, warnings FROM map_analysis WHERE warnings IS NOT NULL AND warnings <> '' ORDER BY map_id LIMIT 1");
		$text = htmlspecialchars($warned['warnings']);
		assertContains('<td class="mtasaWarnings" data-tooltip="' . $text . '">', $ctx->get('/mtasa?q=' . $warned['map_id'] . '&errors%5B%5D=')['body'], 'the warnings, whole in the hover box');
	},

	'the filters narrow the list' => function ($ctx) {
		$bad = (int)mtasaDb($ctx)->query("SELECT COUNT(*) FROM map WHERE status = 'Bad'")->fetchColumn();
		$body = $ctx->get('/mtasa?status%5B%5D=Bad&errors%5B%5D=')['body'];
		assertSame($bad, mtasaListTotal($body), 'a status');
		foreach (mtasaListRows($body) as $id => $cells) {
			assertSame('Bad', $cells[3], "map {$id} has it");
		}

		$noStatus = (int)mtasaDb($ctx)->query("SELECT COUNT(*) FROM map WHERE status IS NULL")->fetchColumn();
		assertSame($noStatus, mtasaListTotal($ctx->get('/mtasa?status%5B%5D=none&errors%5B%5D=')['body']), '"none" is the maps without one');
		assertSame($bad + $noStatus, mtasaListTotal($ctx->get('/mtasa?status%5B%5D=Bad&status%5B%5D=none&errors%5B%5D=')['body']), 'and two choices are either');

		$chiliad = (int)mtasaDb($ctx)->query("SELECT COUNT(*) FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id WHERE m.original_name LIKE '%chiliad%' OR m.custom_name LIKE '%chiliad%' OR m.hypothesized_name LIKE '%chiliad%' OR m.custom_description LIKE '%chiliad%' OR a.folder LIKE '%chiliad%' OR a.title LIKE '%chiliad%' OR a.author LIKE '%chiliad%' OR a.description LIKE '%chiliad%'")->fetchColumn();
		assertTrue($chiliad > 0, 'the fixture has Chiliad maps');
		assertSame($chiliad, mtasaListTotal($ctx->get('/mtasa?q=Chiliad')['body']), 'the search, in any case');
		$percent = (int)mtasaDb($ctx)->query("SELECT COUNT(*) FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id WHERE instr(COALESCE(m.original_name, '') || COALESCE(m.custom_name, '') || COALESCE(m.hypothesized_name, '') || COALESCE(m.custom_description, '') || COALESCE(a.folder, '') || COALESCE(a.title, '') || COALESCE(a.author, '') || COALESCE(a.description, ''), '%') > 0")->fetchColumn();
		assertSame($percent, mtasaListTotal($ctx->get('/mtasa?q=%25')['body']), 'a % is searched for, not a wildcard');

		$total = mtasaCount($ctx, 'map');
		$originals = mtasaListTotal($ctx->get('/mtasa?duplicates=originals&errors%5B%5D=')['body']);
		$copies = mtasaListTotal($ctx->get('/mtasa?duplicates=copies&errors%5B%5D=')['body']);
		assertSame($total, $originals + $copies, 'every map is an original or a copy');
		assertTrue($copies > 0, 'and the fixture has copies');

		$errors = (int)mtasaDb($ctx)->query("SELECT COUNT(*) FROM map_analysis WHERE errors = 'folder missing'")->fetchColumn();
		assertSame($errors, mtasaListTotal($ctx->get('/mtasa?errors%5B%5D=folder+missing')['body']), 'an error');

		assertSame($total, mtasaListTotal($ctx->get('/mtasa?status%5B%5D=Nonsense&duplicates=sideways&errors%5B%5D=')['body']), 'an option the list does not have is ignored');

		/// Errors leaves the missing folders out until the address names it.
		assertTrue($errors > 0, 'the fixture has maps with no folder');
		$bare = $ctx->get('/mtasa?per=1000')['body'];
		assertSame($total - $errors, mtasaListTotal($bare), 'by default the maps with no folder are left out');
		assertTrue(!isset(mtasaListRows($bare)[2]), 'map 2, which has none, among them');
		assertContains('<input type="checkbox" name="errors[]" value="folder missing">', $bare, 'its box unticked');
		assertContains('<input type="checkbox" name="errors[]" value="none" checked>', $bare, 'and the rest ticked');
		assertContains('<input type="hidden" name="errors[]" value="">', $bare, 'with an empty value naming the filter');
		assertSame($total, mtasaListTotal($ctx->get('/mtasa?errors%5B%5D=')['body']), 'nothing ticked is every map');
		assertContains('href="/mtasa?errors%5B%5D=&amp;sort=author&amp;dir=asc"', $ctx->get('/mtasa?errors%5B%5D=')['body'], 'which the links keep');
		$default = [];
		foreach (mtasaDb($ctx)->query("SELECT DISTINCT errors FROM map_analysis WHERE errors IS NOT NULL AND errors <> 'folder missing'")->fetchAll() as $row) {
			$default[] = 'errors%5B%5D=' . urlencode($row['errors']);
		}
		$ticked = $ctx->get('/mtasa?errors%5B%5D=&errors%5B%5D=none&' . implode('&', $default))['body'];
		assertSame($total - $errors, mtasaListTotal($ticked), 'the default ticked by hand is the default');
		assertContains('href="/mtasa?sort=author&amp;dir=asc"', $ticked, 'and its links stay plain');

		/// The gamemode is the set one, else the script's guess, as the column shows it.
		$guess = mtasaRow($ctx, "SELECT a.category_guess g FROM map m JOIN map_analysis a ON a.map_id = m.id WHERE m.gamemode IS NULL AND a.category_guess IS NOT NULL LIMIT 1")['g'];
		$count = mtasaDb($ctx)->prepare("SELECT COUNT(*) FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id WHERE COALESCE(NULLIF(m.gamemode, ''), a.category_guess) = ? AND (a.errors IS NULL OR a.errors <> 'folder missing')");
		$count->execute([$guess]);
		$expected = (int)$count->fetchColumn();
		$guessed = mtasaListTotal($ctx->get('/mtasa?gamemode%5B%5D=' . urlencode($guess))['body']);
		assertSame($expected, $guessed, 'a gamemode takes in the maps only guessed at it');
		$neither = (int)mtasaDb($ctx)->query("SELECT COUNT(*) FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id WHERE COALESCE(NULLIF(m.gamemode, ''), a.category_guess) IS NULL AND (a.errors IS NULL OR a.errors <> 'folder missing')")->fetchColumn();
		assertSame($neither, mtasaListTotal($ctx->get('/mtasa?gamemode%5B%5D=none')['body']), 'and "none" is the maps with neither');
		assertTrue(strpos($bare, 'name="category_guess[]"') === false, 'there is no guess filter of its own');

		/// Finished is yes or no: an unmarked map is not finished.
		assertTrue(strpos($bare, 'name="finished[]" value="none"') === false, 'Finished has no "(none)"');
		$yes = (int)mtasaDb($ctx)->query("SELECT COUNT(*) FROM map WHERE finished = 1")->fetchColumn();
		assertSame($yes, mtasaListTotal($ctx->get('/mtasa?finished%5B%5D=1&errors%5B%5D=')['body']), 'Yes is the maps marked finished');
		assertSame($total - $yes, mtasaListTotal($ctx->get('/mtasa?finished%5B%5D=0&errors%5B%5D=')['body']), 'and No every other, unmarked ones included');
		assertSame($total, mtasaListTotal($ctx->get('/mtasa?finished%5B%5D=none&errors%5B%5D=')['body']), 'a "none" in the address is ignored');
	},

	'sorting keeps blanks at the bottom both ways, and pages' => function ($ctx) {
		foreach (['asc', 'desc'] as $dir) {
			$authors = array_column(mtasaWholeList($ctx, ["sort" => "author", "dir" => $dir]), 2);
			$filled = array_values(array_filter($authors, fn($a) => $a !== ''));
			assertSame($filled, array_slice($authors, 0, count($filled)), "sorted {$dir}, the blank authors come last");
			$sorted = $filled;
			usort($sorted, fn($a, $b) => $dir === 'asc' ? strcasecmp($a, $b) : strcasecmp($b, $a));
			assertSame(array_map('strtolower', $sorted), array_map('strtolower', $filled), "and the rest are in order {$dir}");
		}

		$actual = array_column(mtasaWholeList($ctx, ['sort' => 'original', 'dir' => 'desc']), 10);
		$filled = array_values(array_filter($actual, fn($a) => $a !== ''));
		assertSame($filled, array_slice($actual, 0, count($filled)), 'a derived column too');
		assertTrue((int)$filled[0] >= (int)end($filled), 'highest first');

		$second = $ctx->get('/mtasa?page=2')['body'];
		assertContains('Page 2 of 2', $second, 'a second page');
		assertContains('Page 2 of 2', $ctx->get('/mtasa?page=99')['body'], 'a page past the end shows the last');
		assertSame(100, count(mtasaListRows($ctx->get('/mtasa')['body'])), 'a hundred to a page');
		assertContains('href="/mtasa?sort=author&amp;dir=desc"', $ctx->get('/mtasa?sort=author&dir=asc')['body'], 'the sorted header flips its direction');
		assertSame(mtasaListRows($ctx->get('/mtasa')['body']), mtasaListRows($ctx->get('/mtasa?sort=nonsense&dir=sideways')['body']), 'an unknown sort is the default');
	},

	'the pager types a page, goes first and last, and sets the page size' => function ($ctx) {
		$pagers = fn($body) => preg_match_all('#<form method="get" action="/mtasa" class="mtasaPager"[^>]*>(.*?)</form>#s', $body, $m) ? $m[1] : [];
		/// The originals, a hundred and more, in pages of 25.
		$body = $ctx->get('/mtasa?per=25&page=3&sort=author&dir=desc&duplicates=originals')['body'];
		assertSame(25, count(mtasaListRows($body)), '25 to a page');
		$forms = $pagers($body);
		assertSame(2, count($forms), 'a pager above and below the table');
		$pager = $forms[0];
		$pages = (int)ceil(count(mtasaListRows($ctx->get('/mtasa?per=1000&duplicates=originals')['body'])) / 25);
		assertContains('<input type="number" name="page" min="1" max="' . $pages . '" value="3"', $pager, 'a box holding the page, up to the last');
		assertContains('<a href="/mtasa?duplicates=originals&amp;sort=author&amp;dir=desc&amp;per=25">« First</a>', $pager, 'First keeps the filters, sort and size');
		assertContains('&amp;page=' . $pages . '&amp;per=25">Last »</a>', $pager, 'Last goes to the last page');
		foreach (['<input type="hidden" name="duplicates" value="originals">', '<input type="hidden" name="sort" value="author">', '<input type="hidden" name="dir" value="desc">'] as $hidden) {
			assertContains($hidden, $pager, 'the box\'s form carries ' . $hidden);
		}
		assertContains('<option value="25" selected>25</option>', $pager, 'and the size, chosen');
		assertContains('</label><button type="submit" class="mtasaPagerGo">Go</button>', $pager, 'with a Go button beside the box, script or not');
		assertTrue(strpos($pager, '<noscript>') === false, 'which is no noscript fallback');
		assertContains('<option value="10">10</option>', $pager, 'a size of 10 is offered');
		$first = $pagers($ctx->get('/mtasa?per=25')['body'])[0];
		assertContains('<span class="mtasaPagerOff" aria-disabled="true">« First</span>', $first, 'on the first page, First is greyed');
		assertContains('<span class="mtasaPagerOff" aria-disabled="true">‹ Previous</span>', $first, 'and Previous');
		$forty = $ctx->get('/mtasa?per=40')['body'];
		assertSame(40, count(mtasaListRows($forty)), 'an address may ask for a size the pager does not offer');
		assertContains('<option value="25">25</option><option value="40" selected>40</option><option value="50">50</option>', $pagers($forty)[0], 'which the dropdown shows in its place, chosen');
		assertSame(10, count(mtasaListRows($ctx->get('/mtasa?per=10')['body'])), '10 to a page');
		foreach (['0', '99999', 'abc', '-5'] as $bad) {
			assertSame(100, count(mtasaListRows($ctx->get('/mtasa?per=' . $bad)['body'])), 'per=' . $bad . ' is the default');
		}
		assertContains('href="/mtasa?sort=author&amp;dir=asc&amp;per=25"', $ctx->get('/mtasa?per=25')['body'], 'the sort headers keep the size');
		assertContains('<input type="hidden" name="per" value="25">', $ctx->get('/mtasa?per=25')['body'], 'and so does the filter form');
		assertContains('<a href="/mtasa?per=25" data-clear-filters>', $ctx->get('/mtasa?per=25&status[]=none')['body'], 'and Clear');
		$single = $pagers($ctx->get('/mtasa?per=1000')['body'])[0] ?? '';
		assertTrue($single !== '' && substr_count($single, 'mtasaPagerOff') === 4, 'on a single page the pager stays, its four links greyed');
	},

	'only an admin edits a map, and only the owner\'s fields' => function ($ctx) {
		$ctx->newSession();
		assertSame(401, mtasaField($ctx, 78, 'status', 'Good')['status'], 'signed out is refused');

		mtasaLogIn($ctx, 'mtasa_reader');
		assertSame(403, mtasaField($ctx, 78, 'status', 'Good')['status'], 'a reader is refused');

		mtasaLogIn($ctx, 'mtasa_admin');
		assertSame(403, $ctx->postWithoutCsrf('/mtasa/ajax/map-field', ['mapId' => 78, 'field' => 'status', 'value' => 'Good'])['status'], 'a post without the token is refused');
		assertSame('error', mtasaField($ctx, 78, 'title', 'New title')['json']['status'], 'the script\'s fields are not editable');
		assertSame('error', mtasaField($ctx, 78, 'nonsense', 'x')['json']['status'], 'nor is a field that does not exist');
		assertSame('error', mtasaField($ctx, 999999, 'status', 'Good')['json']['status'], 'nor a map that does not');
		assertSame('That is not one of the choices.', mtasaField($ctx, 78, 'status', 'Nonsense')['json']['message'], 'a status must be one of the list');
		assertSame('That needs to be yes or no.', mtasaField($ctx, 78, 'finished', 'maybe')['json']['message'], 'a boolean yes or no');
		assertSame('That needs to be a map number.', mtasaField($ctx, 78, 'duplicate_of', 'abc')['json']['message'], 'a duplicate a map');
		assertSame('That needs to be a link starting with http:// or https://.', mtasaField($ctx, 78, 'video', 'javascript:alert(1)')['json']['message'], 'and a video a link');
		assertSame('Named Available', mtasaRow($ctx, "SELECT status FROM map WHERE id = 78")['status'], 'none of which changed anything');

		$saved = mtasaField($ctx, 78, 'status', 'Good')['json'];
		assertSame(['status' => 'ok', 'value' => 'Good', 'text' => 'Good'], $saved, 'an admin\'s edit is saved and answered as shown');
		assertSame('Good', mtasaRow($ctx, "SELECT status FROM map WHERE id = 78")['status'], 'and stored');
		assertTrue(mtasaRow($ctx, "SELECT updated_at FROM map WHERE id = 78")['updated_at'] !== null, 'with its time');
		assertSame('Yes', mtasaField($ctx, 78, 'finished', 'yes')['json']['text'], 'a boolean reads as Yes');
		assertSame('', mtasaField($ctx, 78, 'finished', '')['json']['value'], 'emptying a field clears it');

		$page = $ctx->get('/mtasa/map?id=78')['body'];
		assertContains('class="mtasaEdit"', $page, 'an admin gets the editors');
		assertContains('<option value="Good" data-choice="Good" selected>', $page, 'the status a dropdown holding the stored value');
		assertContains('class="mtasaChoiceSelect" data-choice="Good"', $page, 'coloured by it');
		assertContains('<option value="Bad" data-choice="Bad">', $page, 'as each option is by its own');
		assertContains('<option value="Race" data-choice="Race"', $page, 'the gamemode a coloured dropdown too');
		mtasaField($ctx, 78, 'status', 'Named Available');
	},

	'Problems shows only what the script found, else None' => function ($ctx) {
		$problems = function ($id) use ($ctx) {
			$body = $ctx->get('/mtasa/map?id=' . $id)['body'];
			$start = strpos($body, '<h2>Problems</h2>');
			return substr($body, $start, strpos($body, '</section>', $start) - $start);
		};
		/// 2's folder is missing, an error, with no warnings or notes.
		preg_match_all('#id="field-([a-z_]+)"#', $problems(2), $m);
		assertSame(['errors'], $m[1], 'a blank row is left out');
		assertTrue(strpos($problems(2), 'mtasaFieldNone') === false, 'and with a row left, there is no None');
		/// 84 has none of the three.
		preg_match_all('#id="field-([a-z_]+)"#', $problems(84), $m);
		assertSame([], $m[1], 'with all three blank, no row of them');
		assertTrue(preg_match('#<div class="mtasaField mtasaFieldNone">\s*<div class="mtasaFieldLabel">None</div>#', $problems(84)) === 1, 'but one saying None');
		/// A blank row with comments stays, so they can be read.
		$ctx->newSession();
		mtasaLogIn($ctx, 'mtasa_admin');
		assertSame('ok', mtasaComment($ctx, ['action' => 'add', 'mapId' => 84, 'field' => 'warnings', 'body' => 'Surely there is one'])['json']['status'] ?? null, 'commenting on 84\'s empty warnings');
		preg_match_all('#id="field-([a-z_]+)"#', $problems(84), $m);
		assertSame(['warnings'], $m[1], 'keeps that row');
		assertTrue(strpos($problems(84), 'mtasaFieldNone') === false, 'in place of None');
		mtasaDb($ctx)->prepare("DELETE FROM map_comment WHERE map_id = 84 AND field = 'warnings'")->execute();
	},

	'the Map and then an About section head a map, and a known original name hides the other names in it' => function ($ctx) {
		/// The opening tag of what the page draws, and whether it carries `hidden`.
		$tag = function ($body, $pattern) {
			return preg_match('#<(?:div|button) [^>]*' . $pattern . '[^>]*>#s', $body, $m) ? $m[0] : null;
		};
		$hidden = function ($tag) {
			return $tag !== null && preg_match('#\shidden\s*>$#', $tag) === 1;
		};

		$ctx->newSession();
		$differs = $ctx->get('/mtasa/map?id=96')['body'];
		$headings = preg_match_all('#<h2>([^<]+)</h2>#', $differs, $m) ? $m[1] : [];
		assertSame(['Map', 'About', 'Problems', 'Settings', 'Downloads', 'Classification', 'My experience', 'Duplicates', 'Start', 'Route', 'Finish', 'Guesses'], array_slice($headings, 0, 12), 'the Map comes first, then About, Problems, Settings, Downloads, Classification, My experience, Duplicates, Start, Route, Finish and, at the very bottom, the Guesses');
		assertTrue(strpos($differs, '<h2>Metadata</h2>') === false && strpos($differs, '<h2>Category guess</h2>') === false, 'with no Metadata or Category guess section');
		assertContains('<div class="mtasaField" id="field-author" data-field="author">', $differs, 'the author a row of its own in About, with its comments');
		$experience = substr($differs, strpos($differs, '<h2>My experience</h2>'), strpos($differs, '<h2>Duplicates</h2>') - strpos($differs, '<h2>My experience</h2>'));
		preg_match_all('#id="field-([a-z_]+)"#', $experience, $m);
		assertSame(['video', 'finished', 'status'], $m[1], 'which holds the video, Finished and the status');
		assertContains('<input type="checkbox" class="mtasaCheckField" disabled', $experience, 'Finished a checkbox only an admin can tick');
		$about = substr($differs, strpos($differs, '<h2>About</h2>'), strpos($differs, '<h2>Problems</h2>') - strpos($differs, '<h2>About</h2>'));
		preg_match_all('#<div class="mtasaField[^"]*" (?:id="field-|data-copy-of=")([a-z_]+)"#', $about, $m);
		assertSame(['original_name', 'custom_name', 'hypothesized_name', 'title', 'title_source', 'author', 'description', 'custom_description', 'folder', 'version'], $m[1], 'holding the names, a copy of some of the metadata, and the custom description under the original description');
		assertContains('<div class="mtasaFieldLabel">Gathered name</div>', $about, 'the title as the gathered name');
		assertTrue($hidden($tag($about, 'data-copy-of="title"')) && $hidden($tag($about, 'data-copy-of="title_source"')), 'which a known original name hides, with its source, even when they differ');
		assertTrue(!$hidden($tag($about, 'data-copy-of="author"')), 'but not the author');
		assertTrue(strpos($differs, 'id="field-title"') > strpos($differs, '<h2>Guesses</h2>'), 'while the title keeps its own row under Guesses');
		assertTrue($hidden($tag($differs, 'id="field-custom_name"')), 'a known original name hides the custom name');
		assertTrue($hidden($tag($differs, 'id="field-hypothesized_name"')), 'and the hypothesized one');
		$mismatch = $tag($differs, 'class="mtasaGuessMismatch" data-pair="original_name"');
		assertTrue($mismatch !== null && !$hidden($mismatch), 'a title that differs from the original name is flagged to everyone');
		$at = strpos($differs, 'class="mtasaGuessMismatch" data-pair="original_name"');
		assertTrue($at > strpos($differs, 'id="field-title"') && $at < strpos($differs, 'id="field-title_source"'), 'on the title row in the metadata');
		assertContains('data-tooltip="This differs from the original map name, &quot;3Lap Dirtring&quot;"', $mismatch, 'saying what the original name is');
		assertTrue(strpos($differs, 'class="mtasaUseGuess" data-pair="original_name"') === false, 'but only an admin can take the title over');
		assertContains('aria-disabled="true"', $tag($ctx->get('/mtasa/map?id=78')['body'], 'class="mtasaUseGuess" data-pair="original_name"') ?? '', 'while everyone sees the greyed ✓ of one already set');
		assertContains('<div id="mtasaTooltip"></div>', $differs, 'with the module hover box');

		$titleOnly = $ctx->get('/mtasa/map?id=100')['body'];
		assertTrue(!$hidden($tag($titleOnly, 'id="field-custom_name"')), 'with no original name the other names show');
		assertTrue($hidden($tag($titleOnly, 'class="mtasaGuessMismatch" data-pair="original_name"')), 'and nothing is flagged');

		mtasaLogIn($ctx, 'mtasa_admin');
		$title = mtasaRow($ctx, "SELECT title FROM map_analysis WHERE map_id = 100")['title'];
		$useTitle = $tag($ctx->get('/mtasa/map?id=100')['body'], 'class="mtasaUseGuess" data-pair="original_name"');
		assertTrue($useTitle !== null && !$hidden($useTitle), 'an admin is offered the About title as the original name');
		$adminPage = $ctx->get('/mtasa/map?id=100')['body'];
		$at = strpos($adminPage, 'class="mtasaUseGuess" data-pair="original_name"');
		assertTrue($at > strpos($adminPage, 'data-copy-of="title"') && $at < strpos($adminPage, 'data-copy-of="title_source"'), 'by a ✓ beside the gathered name, saying it is correct');
		$at = strpos($adminPage, 'class="mtasaUseGuess" data-pair="original_name"', $at + 1);
		assertTrue($at > strpos($adminPage, 'id="field-title"') && $at < strpos($adminPage, 'id="field-title_source"'), 'and beside the title in the metadata, which stays in view once the gathered name hides');
		assertTrue(!$hidden($tag($ctx->get('/mtasa/map?id=96')['body'], 'class="mtasaUseGuess" data-pair="original_name"')), 'also over one it differs from');
		$set = $tag($ctx->get('/mtasa/map?id=78')['body'], 'class="mtasaUseGuess" data-pair="original_name"');
		assertTrue($set !== null && strpos($set, 'aria-disabled="true"') !== false, 'over one it matches it is greyed out, as correct and already set');
		assertContains('data-tooltip="This is correct, and already the original map name"', $set, 'saying so');

		mtasaField($ctx, 100, 'original_name', 'Not found');
		$notFound = $ctx->get('/mtasa/map?id=100')['body'];
		assertTrue(!$hidden($tag($notFound, 'id="field-custom_name"')), 'the sheet\'s "Not found" is no original name');
		assertTrue($hidden($tag($notFound, 'class="mtasaGuessMismatch" data-pair="original_name"')), 'and is not flagged');

		assertSame($title, mtasaField($ctx, 100, 'original_name', $title)['json']['value'], 'the ✓ saves the title as the original name');
		$taken = $ctx->get('/mtasa/map?id=100')['body'];
		assertContains('aria-disabled="true"', $tag($taken, 'class="mtasaUseGuess" data-pair="original_name"') ?? '', 'after which it is greyed out');
		assertTrue($hidden($tag($taken, 'id="field-custom_name"')), 'and the other names hide');
		mtasaField($ctx, 100, 'original_name', '');
	},

	'Classification holds the gamemode, and the guess stands in for it until it is set' => function ($ctx) {
		/// The opening tag of what the page draws, and whether it carries `hidden`.
		$tag = fn($body, $pattern) => preg_match('#<(?:div|button) [^>]*' . $pattern . '[^>]*>#s', $body, $m) ? $m[0] : null;
		$hidden = fn($tag) => $tag !== null && preg_match('#\shidden\s*>$#', $tag) === 1;

		$ctx->newSession();
		$guessed = mtasaRow($ctx, "SELECT m.id, a.category_guess FROM map m JOIN map_analysis a ON a.map_id = m.id WHERE m.gamemode IS NULL AND a.category_guess IS NOT NULL ORDER BY m.id LIMIT 1");
		$body = $ctx->get('/mtasa/map?id=' . $guessed['id'])['body'];
		$section = substr($body, strpos($body, '<h2>Classification</h2>'), strpos($body, '<h2>My experience</h2>') - strpos($body, '<h2>Classification</h2>'));
		preg_match_all('#<div class="mtasaField[^"]*" (?:id="field-|data-copy-of=")([a-z_]+)"#', $section, $m);
		assertSame(['category', 'gamemode', 'category_guess', 'category_confidence', 'category_reason', 'race_mode', 'vehicle_classes'], $m[1], 'the category and gamemode, a copy of the guess, the race mode and the vehicle classes');
		assertTrue(strpos($body, 'id="field-race_mode"') < strpos($body, '<h2>Route</h2>'), 'moved there from Route');
		assertContains('<div class="mtasaFieldLabel">Gamemode guess</div>', $section, 'labelled as a gamemode guess');
		assertTrue(!$hidden($tag($section, 'data-copy-of="category_guess"')), 'shown while no gamemode is set');
		assertTrue(strpos($body, 'id="field-category_guess"') > strpos($body, '<h2>Guesses</h2>'), 'while the guess keeps its own rows under Guesses');
		$guesses = substr($body, strpos($body, '<h2>Guesses</h2>'));
		preg_match_all('#<div class="mtasaFieldLabel">([^<]+)</div>#', substr($guesses, 0, strpos($guesses, '</section>')), $m);
		assertSame(['Title', 'Title source', 'Gamemode', 'Gamemode confidence', 'Gamemode reason', 'Duplicate of', 'Duplicate confidence', 'Duplicate similarity', 'Duplicate differences'], $m[1], 'where they do not say "guess" again');

		mtasaLogIn($ctx, 'mtasa_admin');
		$body = $ctx->get('/mtasa/map?id=' . $guessed['id'])['body'];
		assertSame(2, substr_count($body, 'class="mtasaUseGuess" data-pair="gamemode"'), 'an admin can take the guess over beside both its rows');
		assertContains('data-tooltip="This is correct: make &quot;' . $guessed['category_guess'] . '&quot; the gamemode"', $body, 'saying so');

		assertSame($guessed['category_guess'], mtasaField($ctx, $guessed['id'], 'gamemode', $guessed['category_guess'])['json']['value'], 'the ✓ saves the guess as the gamemode');
		$body = $ctx->get('/mtasa/map?id=' . $guessed['id'])['body'];
		assertTrue($hidden($tag($body, 'data-copy-of="category_guess"')) && $hidden($tag($body, 'data-copy-of="category_reason"')), 'after which the guess rows hide');
		assertContains('aria-disabled="true"', $tag(substr($body, strpos($body, 'id="field-category_guess"')), 'class="mtasaUseGuess"') ?? '', 'and its ✓ greys out');

		$other = $guessed['category_guess'] === 'Freeroam' ? 'Race' : 'Freeroam';
		mtasaField($ctx, $guessed['id'], 'gamemode', $other);
		$body = $ctx->get('/mtasa/map?id=' . $guessed['id'])['body'];
		$mismatch = $tag($body, 'class="mtasaGuessMismatch" data-pair="gamemode"');
		assertTrue($mismatch !== null && !$hidden($mismatch), 'a guess that differs from the gamemode is flagged');
		assertContains('This differs from the gamemode, &quot;' . $other . '&quot;', $mismatch, 'naming the gamemode');
		$at = strpos($body, 'class="mtasaGuessMismatch" data-pair="gamemode"');
		assertTrue($at > strpos($body, 'id="field-category_guess"'), 'on the guess row under Guesses');
		mtasaField($ctx, $guessed['id'], 'gamemode', '');
	},

	'the race mode is worked out from the checkpoints and the respawn mode, not stored' => function ($ctx) {
		$columns = array_column(mtasaDb($ctx)->query("PRAGMA table_info(map_analysis)")->fetchAll(), 'name');
		assertTrue(!in_array('race_mode', $columns, true), 'map_analysis has no race mode');

		$raceMode = function ($id) use ($ctx) {
			preg_match('#id="field-race_mode".*?<span class="mtasaFieldShown">(.*?)</span>#s', $ctx->get('/mtasa/map?id=' . $id)['body'], $m);
			return html_entity_decode($m[1] ?? 'missing');
		};
		$sprint = mtasaRow($ctx, "SELECT map_id FROM map_analysis WHERE checkpoints > 0 LIMIT 1")['map_id'];
		assertSame('Sprint', $raceMode($sprint), 'a map with checkpoints is a sprint');
		$unread = mtasaRow($ctx, "SELECT m.id FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id WHERE a.checkpoints IS NULL LIMIT 1")['id'];
		assertSame('', $raceMode($unread), 'a map the script never read has none');

		/// The fixture has no checkpoint-less map of every respawn mode, so one map
		/// takes each in turn, and its own back after.
		$db = mtasaDb($ctx);
		$arena = mtasaRow($ctx, "SELECT map_id, respawn_mode FROM map_analysis WHERE checkpoints = 0 LIMIT 1");
		foreach (['none' => 'Destruction Derby', 'timelimit' => 'Freeroam', 'waves' => 'Freeroam', null => 'Destruction Derby/Freeroam'] as $mode => $want) {
			$db->prepare("UPDATE map_analysis SET respawn_mode = ? WHERE map_id = ?")->execute([$mode === '' ? null : $mode, $arena['map_id']]);
			assertSame($want, $raceMode($arena['map_id']), 'no checkpoints and respawn mode ' . ($mode === '' ? 'null' : $mode));
			if ($mode === 'timelimit') {
				$count = (int)$db->query("SELECT COUNT(*) FROM map_analysis WHERE checkpoints = 0 AND respawn_mode IN ('timelimit', 'waves')")->fetchColumn();
				assertSame($count, mtasaListTotal($ctx->get('/mtasa?race_mode%5B%5D=Freeroam')['body']), 'and the filter reads the same rule');
			}
		}
		$db->prepare("UPDATE map_analysis SET respawn_mode = ? WHERE map_id = ?")->execute([$arena['respawn_mode'], $arena['map_id']]);
	},

	'Duplicates holds Duplicate of, and the duplicate guess stands in for it until it is set' => function ($ctx) {
		$tag = fn($body, $pattern) => preg_match('#<(?:div|button) [^>]*' . $pattern . '[^>]*>#s', $body, $m) ? $m[0] : null;
		$hidden = fn($tag) => $tag !== null && preg_match('#\shidden\s*>$#', $tag) === 1;

		$ctx->newSession();
		mtasaLogIn($ctx, 'mtasa_admin');
		$guessed = mtasaRow($ctx, "SELECT m.id, a.duplicate_of_closest FROM map m JOIN map_analysis a ON a.map_id = m.id WHERE m.duplicate_of IS NULL AND a.duplicate_of_closest IS NOT NULL ORDER BY m.id LIMIT 1");
		$body = $ctx->get('/mtasa/map?id=' . $guessed['id'])['body'];
		$section = substr($body, strpos($body, '<h2>Duplicates</h2>'), strpos($body, '<h2>Start</h2>') - strpos($body, '<h2>Duplicates</h2>'));
		preg_match_all('#<div class="mtasaField[^"]*" (?:id="field-|data-copy-of=")([a-z_]+)"#', $section, $m);
		assertSame(['duplicate_of', 'duplicate_of_closest', 'duplicate_confidence', 'duplicate_similarity', 'duplicate_differences'], $m[1], 'Duplicate of, then a copy of the guess');
		assertContains('<div class="mtasaFieldLabel">Duplicate guess</div>', $section, 'labelled as a guess');
		assertTrue(!$hidden($tag($section, 'data-copy-of="duplicate_of_closest"')), 'shown while Duplicate of is unset');
		assertSame(2, substr_count($body, 'class="mtasaUseGuess" data-pair="duplicate_of"'), 'with a ✓ beside both guess rows');
		assertContains('This is correct: mark this map a duplicate of ' . $guessed['duplicate_of_closest'], $body, 'saying so');

		mtasaField($ctx, $guessed['id'], 'duplicate_of', (string)$guessed['duplicate_of_closest']);
		$body = $ctx->get('/mtasa/map?id=' . $guessed['id'])['body'];
		assertTrue($hidden($tag($body, 'data-copy-of="duplicate_of_closest"')), 'taking it over hides the guess rows');
		assertContains('aria-disabled="true"', $tag(substr($body, strpos($body, 'id="field-duplicate_of_closest"')), 'class="mtasaUseGuess"') ?? '', 'and greys the ✓');

		mtasaField($ctx, $guessed['id'], 'duplicate_of', (string)$guessed['id']);
		$body = $ctx->get('/mtasa/map?id=' . $guessed['id'])['body'];
		$mismatch = $tag($body, 'class="mtasaGuessMismatch" data-pair="duplicate_of"');
		assertTrue($mismatch !== null && !$hidden($mismatch), 'marking it an original, against the guess, is flagged');
		assertContains('This differs from Duplicate of, ' . $guessed['id'], $mismatch, 'naming the mark');
		mtasaField($ctx, $guessed['id'], 'duplicate_of', '');
	},

	'the copies table tells a confirmed duplicate from a guessed one' => function ($ctx) {
		$ctx->newSession();
		mtasaLogIn($ctx, 'mtasa_admin');
		/// Each row of a map's set: its classes and its cells.
		$rows = function ($id) use ($ctx) {
			preg_match_all('#<tr(?: class="([^"]*)")?(?: aria-current="page")?>\s*<td><a href="/mtasa/map\?id=(\d+)">.*?</tr>#s', $ctx->get('/mtasa/map?id=' . $id)['body'], $found, PREG_SET_ORDER);
			$read = [];
			foreach ($found as $row) {
				preg_match_all('#<td[^>]*>(.*?)</td>#s', $row[0], $m);
				$read[(int)$row[2]] = ['classes' => $row[1], 'cells' => array_map('trim', $m[1]), 'html' => $row[0]];
			}
			return $read;
		};
		$copy = mtasaRow($ctx, "SELECT m.id, a.duplicate_of_closest, a.duplicate_differences FROM map m JOIN map_analysis a ON a.map_id = m.id WHERE m.duplicate_of IS NULL AND a.duplicate_of_closest IS NOT NULL AND a.duplicate_of_closest <> m.id AND a.duplicate_differences LIKE '%differ%' ORDER BY m.id LIMIT 1");
		$id = (int)$copy['id'];

		$row = $rows($id)[$id];
		assertContains('mtasaGroupGuess', $row['classes'], 'an unconfirmed copy is a guess, its whole row faded');
		assertSame(1, substr_count($row['html'], 'data-tooltip'), 'with nothing to hover but its note');
		assertContains('<td data-tooltip="Duplicate guess note">', $row['html'], 'which says what it is');
		assertSame(6, count($row['cells']), 'and there is no Copy column');
		assertSame('<a href="/mtasa/map?id=' . $copy['duplicate_of_closest'] . '">' . $copy['duplicate_of_closest'] . '</a>', $row['cells'][2], 'Duplicate of the guess');
		assertSame(htmlspecialchars($copy['duplicate_differences']), $row['cells'][5], 'its differences the script\'s notes, verbatim');

		mtasaField($ctx, $id, 'duplicate_of', (string)$copy['duplicate_of_closest']);
		$row = $rows($id)[$id];
		assertTrue(strpos($row['classes'], 'mtasaGroupGuess') === false, 'a confirmed copy is not');
		assertTrue(strpos($row['html'], '%') === false || strpos($row['html'], '% same') !== false, 'and shows no similarity of its own');
		assertTrue(preg_match('#<td>(high|medium|low)</td>#', $row['html']) === 0, 'nor any confidence');
		$root = (int)mtasaDb($ctx)->query("SELECT id FROM map WHERE duplicate_of = id ORDER BY id LIMIT 1")->fetchColumn();
		mtasaField($ctx, $id, 'duplicate_of', (string)$root);
		assertTrue(isset($rows($root)[$id]), 'a mark moves it into that set');
		assertSame('<a href="/mtasa/map?id=' . $root . '">' . $root . '</a>', $rows($root)[$id]['cells'][2], 'Duplicate of the mark');
		$original = $rows($root)[$root]['cells'];
		assertSame('-', $original[2], 'the original of the set has a dash for Duplicate of');
		assertSame('', end($original), 'and no differences');
		mtasaField($ctx, $id, 'duplicate_of', '');
	},

	'the guess columns show only while a copy is still a guess' => function ($ctx) {
		$ctx->newSession();
		mtasaLogIn($ctx, 'mtasa_admin');
		/// The set of 78 is small: confirm every copy in it, and the columns go.
		$set = array_map('intval', array_column(mtasaDb($ctx)->query("SELECT m.id FROM map m JOIN map_analysis a ON a.map_id = m.id WHERE m.duplicate_of IS NULL AND a.duplicate_of_closest IS NOT NULL")->fetchAll(), 'id'));
		$header = 'Duplicate guess similarity';
		$table = fn($body) => substr($body, strpos($body, '<table class="mtasaGroupTable">'), strpos($body, '</table>', strpos($body, '<table class="mtasaGroupTable">')) - strpos($body, '<table class="mtasaGroupTable">'));
		$body = $table($ctx->get('/mtasa/map?id=78')['body']);
		$unconfirmed = [];
		preg_match_all('#<tr class="[^"]*mtasaGroupGuess[^"]*"[^>]*>\s*<td><a href="/mtasa/map\?id=(\d+)"#', $body, $m);
		$unconfirmed = array_map('intval', $m[1]);
		assertTrue($unconfirmed !== [] && strpos($body, $header) !== false, 'while a copy in the set is a guess, its similarity shows');
		$guesses = mtasaDb($ctx)->query("SELECT map_id, duplicate_of_closest FROM map_analysis WHERE map_id IN (" . implode(', ', $unconfirmed) . ")")->fetchAll(PDO::FETCH_KEY_PAIR);
		foreach ($guesses as $copy => $guess) {
			mtasaField($ctx, (int)$copy, 'duplicate_of', (string)$guess);
		}
		$body = $table($ctx->get('/mtasa/map?id=78')['body']);
		assertTrue(strpos($body, $header) === false && strpos($body, 'Duplicate guess confidence') === false, 'once every copy is confirmed, neither column shows');
		preg_match('#<tbody>\s*<tr[^>]*>(.*?)</tr>#s', $body, $first);
		assertSame(4, substr_count($first[1], '<td'), 'rows lose the two cells as well');
		foreach ($guesses as $copy => $guess) {
			mtasaField($ctx, (int)$copy, 'duplicate_of', '');
		}
	},

	'a confirmed copy has its differences from the map marked worked out by the site' => function ($ctx) {
		$ctx->newSession();
		mtasaLogIn($ctx, 'mtasa_admin');
		$differences = function ($id) use ($ctx) {
			$body = $ctx->get('/mtasa/map?id=' . $id)['body'];
			preg_match('#<tr[^>]*>\s*<td><a href="/mtasa/map\?id=' . $id . '">.*?</tr>#s', $body, $row);
			return $row[0] ?? '';
		};
		/// The cell waits for Increase detail, which compares the points from GitHub
		/// (checked in Chrome), carrying what the stored analysis says.
		$compare = function ($row) {
			preg_match('#<td class="mtasaOwnDifferences" data-compare="([^"]*)"><span class="mtasaAfterDetail">Press Increase detail at the map to show this</span></td>#', $row, $m);
			return isset($m[1]) ? json_decode(html_entity_decode($m[1]), true) : null;
		};

		/// 4675 and 4677 are both identical to 114, the script says.
		assertContains('<td data-tooltip="Duplicate guess note">identical content</td>', $differences(4675), 'the script\'s notes on 4675 against its guess, 114, verbatim');
		mtasaField($ctx, 4675, 'duplicate_of', '4677');
		$row = $differences(4675);
		assertSame(['this' => 4675, 'that' => 4677, 'words' => [], 'rows' => []], $compare($row), 'marked a duplicate of 4677, the cell waits for the points, the stored analysis finding nothing');
		assertTrue(strpos($row, '100%') === false && strpos($row, '>high<') === false, 'and the guess, its similarity and confidence, is ignored');

		/// 2670 is a 65% match for 114, with another author and description.
		mtasaField($ctx, 2670, 'duplicate_of', '87');
		$data = $compare($differences(2670));
		assertTrue($data !== null && $data['that'] === 87, 'a looser match is compared too');
		assertSame(count($data['words']), count(array_unique(array_map(fn($row) => explode(':', $row[0])[0], $data['rows']))) - (count($data['rows']) - count(array_unique(array_column($data['rows'], 0)))), 'a word for each kind of difference');
		assertTrue(strpos(json_encode($data), 'field.') === false && strpos(json_encode($data), 'compare.') === false, 'every row labelled');
		$a = mtasaRow($ctx, "SELECT author FROM map_analysis WHERE map_id = 2670")['author'];
		$b = mtasaRow($ctx, "SELECT author FROM map_analysis WHERE map_id = 87")['author'];
		if ($a !== $b) {
			assertTrue(in_array(['Author', (string)$a, (string)$b], $data['rows'], true), 'naming both authors');
		}

		mtasaField($ctx, 4675, 'duplicate_of', '');
		mtasaField($ctx, 2670, 'duplicate_of', '');

		/// 88 has no author; 87 has one.
		mtasaField($ctx, 88, 'duplicate_of', '87');
		$data = $compare($differences(88));
		$author = array_values(array_filter($data['rows'] ?? [], fn($row) => $row[0] === 'Author'))[0] ?? null;
		assertTrue($author !== null && $author[1] === '', 'a missing value goes as empty, which the hover table draws as an italic (none)');
		mtasaField($ctx, 88, 'duplicate_of', '');
	},

	'Finished is a checkbox, and the status is coloured as the sheet coloured it' => function ($ctx) {
		$ctx->newSession();
		mtasaLogIn($ctx, 'mtasa_reader');
		assertContains('<span class="mtasaChoicePill" data-choice="Named Available">Named Available</span>', $ctx->get('/mtasa/map?id=78')['body'], 'a reader sees the status as a coloured label');
		mtasaLogIn($ctx, 'mtasa_admin');
		assertSame('Yes', mtasaField($ctx, 78, 'finished', '1')['json']['text'], 'the checkbox saves a tick as 1');
		assertContains('class="mtasaCheckField" checked', $ctx->get('/mtasa/map?id=78')['body'], 'and draws it ticked');
		$list = $ctx->get('/mtasa?q=78')['body'];
		assertContains('<input type="checkbox" class="mtasaListCheck" checked', $list, 'an admin ticks it in the list too');
		assertContains('<span class="mtasaChoicePill" data-choice="Named Available">Named Available</span>', $list, 'beside the status, coloured as on the map page');
		assertSame(78, array_key_first(mtasaListRows($ctx->get('/mtasa?sort=finished&dir=desc')['body'])), 'and Finished sorts');
		mtasaLogIn($ctx, 'mtasa_reader');
		assertSame("✓\u{FE0E}", mtasaListRows($ctx->get('/mtasa?q=78')['body'])[78][6], 'a reader sees a ✓ in its Finished column');
		mtasaLogIn($ctx, 'mtasa_admin');
		assertSame('No', mtasaField($ctx, 78, 'finished', '0')['json']['text'], 'and an untick as 0');
		mtasaField($ctx, 78, 'finished', '');
		assertSame('ok', mtasaField($ctx, 78, 'status', 'Patch Due')['json']['status'], 'Patch Due, which the sheet coloured, is a status');
		mtasaField($ctx, 78, 'status', 'Named Available');
		$css = $ctx->get('/modules/mtasa/css/styles.css')['body'];
		foreach (['[data-choice="Race"] { --choice: #11734b; }', '[data-choice="Vehicle Weapon Combat"] { --choice: #473821; }', '[data-choice="Bad"] { --choice: #b7b7b7; }', '[data-choice="Error"] { --choice: #666666; }', '[data-choice*="Named"] { --choice: #ffd966; }', '[data-choice*="Outsourced"] { --choice: #8e7cc3; }'] as $rule) {
			assertContains($rule, $css, 'the stylesheet colours it: ' . $rule);
		}
	},

	'changing Duplicate of moves a map between sets' => function ($ctx) {
		/// The heading's line links a copy to its set's original.
		$head = fn() => preg_match('#<p class="mtasaMapMeta">(.*?)</p>#s', $ctx->get('/mtasa/map?id=157')['body'], $m) ? $m[1] : '';
		assertSame('ok', mtasaField($ctx, 157, 'duplicate_of', '')['json']['status'], 'clearing 157\'s own mark');
		assertContains('href="/mtasa/map?id=156"', $head(), 'leaves the script\'s grouping, a copy of 156');
		mtasaField($ctx, 157, 'duplicate_of', '157');
		assertTrue(strpos($head(), 'href="/mtasa/map?id=156"') === false, 'marking it its own original takes it out again');
	},

	'anyone signed in comments on a field or the map, and only the author edits' => function ($ctx) {
		$ctx->newSession();
		assertSame(401, mtasaComment($ctx, ['action' => 'add', 'mapId' => 78, 'field' => 'status', 'body' => 'Hi'])['status'], 'signed out cannot comment');

		mtasaLogIn($ctx, 'mtasa_reader');
		$added = mtasaComment($ctx, ['action' => 'add', 'mapId' => 78, 'field' => 'status', 'body' => "Is this <b>really</b> available?\nSecond line"])['json'];
		assertSame('ok', $added['status'], 'a reader comments on a field');
		assertSame('status', $added['field'], 'which the answer names');
		assertSame(1, $added['open'], 'with one open thread');
		assertContains('Is this &lt;b&gt;really&lt;/b&gt; available?' . "\n" . 'Second line', $added['html'], 'escaped, with its line break');
		$threadId = mtasaLastCommentId($ctx);

		assertSame('ok', mtasaComment($ctx, ['action' => 'add', 'mapId' => 78, 'field' => null, 'body' => 'A comment on the map'])['json']['status'], 'and on the map in general');
		assertSame(null, mtasaRow($ctx, "SELECT field FROM map_comment WHERE id = ?", [mtasaLastCommentId($ctx)])['field'], 'stored with no field');

		assertSame('Write something first.', mtasaComment($ctx, ['action' => 'add', 'mapId' => 78, 'field' => 'status', 'body' => '   '])['json']['message'], 'an empty comment is refused');
		assertSame('That comment is too long.', mtasaComment($ctx, ['action' => 'add', 'mapId' => 78, 'field' => 'status', 'body' => str_repeat('x', 2001)])['json']['message'], 'and one too long');
		assertSame('error', mtasaComment($ctx, ['action' => 'add', 'mapId' => 78, 'field' => 'nonsense', 'body' => 'x'])['json']['status'], 'a field a map does not have is refused');
		assertSame('error', mtasaComment($ctx, ['action' => 'add', 'mapId' => 999999, 'field' => null, 'body' => 'x'])['json']['status'], 'and a map that is not there');
		assertSame('That thread is not on this field.', mtasaComment($ctx, ['action' => 'add', 'mapId' => 78, 'field' => 'gamemode', 'parent' => $threadId, 'body' => 'x'])['json']['message'], 'a reply must be on its thread\'s field');

		assertSame('ok', mtasaComment($ctx, ['action' => 'add', 'mapId' => 78, 'field' => 'title', 'body' => 'The script got this wrong'])['json']['status'], 'the script\'s fields take comments too');

		mtasaLogIn($ctx, 'mtasa_admin');
		$reply = mtasaComment($ctx, ['action' => 'add', 'mapId' => 78, 'field' => 'status', 'parent' => $threadId, 'body' => 'Yes, checked.'])['json'];
		assertSame('ok', $reply['status'], 'the admin replies');
		$replyId = mtasaLastCommentId($ctx);
		assertSame('Only its author can change that comment.', mtasaComment($ctx, ['action' => 'edit', 'id' => $threadId, 'body' => 'Changed'])['json']['message'], 'nobody but the author edits, not even an admin');

		mtasaLogIn($ctx, 'mtasa_reader');
		assertSame('Only its author can change that comment.', mtasaComment($ctx, ['action' => 'delete', 'id' => $replyId])['json']['message'], 'a reader cannot delete another\'s comment');
		$edited = mtasaComment($ctx, ['action' => 'edit', 'id' => $threadId, 'body' => 'Is this available?'])['json'];
		assertSame('ok', $edited['status'], 'the author edits their own');
		assertContains('>edited<', $edited['html'], 'which then says so');

		$page = $ctx->get('/mtasa/map?id=78')['body'];
		assertContains('Is this available?', $page, 'the thread is on the page');
		assertContains('Yes, checked.', $page, 'with its reply');
		assertContains('<span class="mtasaBadgeCount">1</span>', $page, 'and the field has a badge');
		assertContains('A comment on the map', $page, 'the map\'s own comment is under the fields');

		$ctx->newSession();
		$visitor = $ctx->get('/mtasa/map?id=78')['body'];
		assertContains('Yes, checked.', $visitor, 'a visitor reads every comment');
		assertTrue(strpos($visitor, 'mtasaNewThread') === false, 'but has no box to write one');
		assertContains('Log in to reply', $visitor, 'and is pointed at the login');
	},

	'threads resolve and reopen, and a deleted first comment keeps its replies' => function ($ctx) {
		$threadId = (int)mtasaRow($ctx, "SELECT id FROM map_comment WHERE map_id = 78 AND field = 'status' AND parent_id IS NULL")['id'];
		$replyId = (int)mtasaRow($ctx, "SELECT id FROM map_comment WHERE parent_id = ?", [$threadId])['id'];

		mtasaLogIn($ctx, 'mtasa_admin');
		$resolved = mtasaComment($ctx, ['action' => 'resolve', 'id' => $threadId])['json'];
		assertSame(0, $resolved['open'], 'the admin resolves a thread');
		assertContains('1 resolved', $resolved['html'], 'which folds away');
		assertContains('Resolved by mtasa_admin', $resolved['html'], 'saying who');
		assertSame('error', mtasaComment($ctx, ['action' => 'resolve', 'id' => $replyId])['json']['status'], 'a reply is not a thread');
		assertSame(1, mtasaComment($ctx, ['action' => 'reopen', 'id' => $threadId])['json']['open'], 'and reopens it');

		mtasaLogIn($ctx, 'mtasa_reader');
		assertSame(0, mtasaComment($ctx, ['action' => 'resolve', 'id' => $threadId])['json']['open'], 'its author resolves it too');
		mtasaComment($ctx, ['action' => 'reopen', 'id' => $threadId]);
		$general = (int)mtasaRow($ctx, "SELECT id FROM map_comment WHERE map_id = 78 AND field IS NULL")['id'];
		mtasaLogIn($ctx, 'mtasa_admin');
		mtasaComment($ctx, ['action' => 'add', 'mapId' => 79, 'field' => null, 'body' => 'An admin thread']);
		$adminOwnId = mtasaLastCommentId($ctx);
		mtasaLogIn($ctx, 'mtasa_reader');
		assertSame('Only its author can change that comment.', mtasaComment($ctx, ['action' => 'resolve', 'id' => $adminOwnId])['json']['message'], 'but nobody else\'s');

		$deleted = mtasaComment($ctx, ['action' => 'delete', 'id' => $threadId])['json'];
		assertSame('ok', $deleted['status'], 'the author deletes their first comment');
		assertContains('Comment deleted', $deleted['html'], 'which stays, as deleted, over its reply');
		assertContains('Yes, checked.', $deleted['html'], 'which keeps its context');
		assertSame('', mtasaRow($ctx, "SELECT body FROM map_comment WHERE id = ?", [$threadId])['body'], 'its text gone');

		mtasaLogIn($ctx, 'mtasa_admin');
		mtasaComment($ctx, ['action' => 'delete', 'id' => $replyId]);
		assertSame(null, mtasaRow($ctx, "SELECT id FROM map_comment WHERE id IN (?, ?)", [$threadId, $replyId]), 'the admin deletes the reply, and the empty thread goes with it');

		assertSame('ok', mtasaComment($ctx, ['action' => 'delete', 'id' => $general])['json']['status'], 'the admin deletes anyone\'s comment');
		assertSame(null, mtasaRow($ctx, "SELECT id FROM map_comment WHERE id = ?", [$general]), 'and one with no replies just goes');
	},

	'open threads show on the list and on the Comments page' => function ($ctx) {
		$ctx->newSession();
		$open = (int)mtasaDb($ctx)->query("SELECT COUNT(DISTINCT map_id) FROM map_comment WHERE parent_id IS NULL AND resolved_at IS NULL")->fetchColumn();
		assertTrue($open > 0, 'some map has an open thread');
		$body = $ctx->get('/mtasa?comments=open')['body'];
		assertSame($open, mtasaListTotal($body), 'the list filters to them');
		assertContains('class="mtasaListComments"', $body, 'and marks them');

		$recent = $ctx->get('/mtasa/comments')['body'];
		assertContains('The script got this wrong', $recent, 'the Comments page lists open threads');
		assertContains('href="/mtasa/map?id=78#field-title"', $recent, 'each linking to its field');
		assertContains('An admin thread', $recent, 'every account\'s');
		assertContains('href="/mtasa/map?id=79#comments"', $recent, 'a thread on the map linking to the map\'s comments');
	},

	'the accounts page is for admins' => function ($ctx) {
		$ctx->newSession();
		assertSame(302, $ctx->get('/mtasa/accounts')['status'], 'signed out is sent away');
		mtasaLogIn($ctx, 'mtasa_reader');
		assertSame('/mtasa', $ctx->get('/mtasa/accounts')['location'], 'and so is a reader');
		mtasaLogIn($ctx, 'mtasa_admin');
		assertContains('mtasa_reader', $ctx->get('/mtasa/accounts')['body'], 'an admin sees every account');
	},

];
