<?php

const HNS_DB = 'hideandseek_test.sqlite';

/// Local to this file: every other case file talks to the music module, and a
/// helper only one file uses has no business in helpers.php.
function hnsRegister($ctx, $name, $password, $inviteCode = '') {
	return $ctx->postForm('/hideandseek/register', [
		'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/register'),
		'account_name' => $name,
		'password' => $password,
		'password_confirm' => $password,
		'invite_code' => $inviteCode,
	]);
}

function hnsLogOut($ctx) {
	return $ctx->postForm('/hideandseek/logout', ['csrf_token' => $ctx->csrfTokenFrom('/hideandseek')]);
}

/* Signs out first, rather than starting a new session: logging in while already
   signed in is a no-op redirect, and dropping the whole session would take the
   music login with it - which is the thing the separation test is watching. */
function hnsLogIn($ctx, $name, $password) {
	hnsLogOut($ctx);

	return $ctx->postForm('/hideandseek/login', [
		'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/login'),
		'account_name' => $name,
		'password' => $password,
	]);
}

function hnsAccountRow($ctx, $name) {
	$stmt = $ctx->dbFor(HNS_DB)->prepare("SELECT id, account_name, is_admin FROM account WHERE account_name = ?");
	$stmt->execute([$name]);

	return $stmt->fetch() ?: null;
}

function hnsCreateGameMap($ctx, $name) {
	return $ctx->postForm('/hideandseek/game-maps', [
		'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/game-maps'),
		'action' => 'create',
		'name' => $name,
	]);
}

function hnsGameMapRow($ctx, $name) {
	$stmt = $ctx->dbFor(HNS_DB)->prepare("SELECT id, name FROM game_map WHERE name = ?");
	$stmt->execute([$name]);

	return $stmt->fetch() ?: null;
}

function hnsImportPois($ctx, $mapId, $category, $pois, $style = []) {
	return hnsImportCategories($ctx, $mapId, [$style + ['name' => $category, 'pois' => $pois]]);
}

function hnsImportCategories($ctx, $mapId, $categories) {
	return $ctx->post('/hideandseek/ajax/import-pois', ['mapId' => $mapId, 'categories' => $categories]);
}

function hnsExportPois($ctx, $mapId) {
	return $ctx->post('/hideandseek/ajax/export-pois', ['mapId' => $mapId]);
}

function hnsCategoryRow($ctx, $mapId, $name) {
	$stmt = $ctx->dbFor(HNS_DB)->prepare("SELECT id, name, colour, icon FROM poi_category WHERE game_map_id = ? AND name = ? COLLATE NOCASE");
	$stmt->execute([$mapId, $name]);

	return $stmt->fetch() ?: null;
}

function hnsPoiRows($ctx, $mapId) {
	$stmt = $ctx->dbFor(HNS_DB)->prepare(
		"SELECT c.name category, p.osm_type, p.osm_id, p.name, p.lat, p.lon
		FROM poi p JOIN poi_category c ON c.id = p.poi_category_id
		WHERE c.game_map_id = ? ORDER BY p.osm_type, p.osm_id"
	);
	$stmt->execute([$mapId]);

	return $stmt->fetchAll();
}

function hnsHomeImportedPois($body) {
	if (!preg_match('#<script id="hnsImportedPois" type="application/json">(.*?)</script>#s', $body, $m)) {
		return null;
	}

	return json_decode($m[1], true);
}

function hnsPoi($type, $id, $lat, $lon, $name = null) {
	return ['osmType' => $type, 'osmId' => (string)$id, 'name' => $name, 'lat' => $lat, 'lon' => $lon];
}

/// Makes an invite as the signed-in admin and returns the code as typed.
function hnsMakeInvite($ctx) {
	$before = (int)$ctx->dbFor(HNS_DB)->query("SELECT COALESCE(MAX(id), 0) c FROM invite")->fetch()['c'];

	$ctx->postForm('/hideandseek/accounts', [
		'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/accounts'),
		'action' => 'invite',
	]);

	$stmt = $ctx->dbFor(HNS_DB)->prepare("SELECT code FROM invite WHERE id > ? ORDER BY id DESC LIMIT 1");
	$stmt->execute([$before]);
	$row = $stmt->fetch();

	return $row ? $row['code'] : '';
}

return [

	'the module answers on its own paths' => function ($ctx) {
		$ctx->newSession();

		foreach (['/hideandseek', '/hideandseek/login', '/hideandseek/register'] as $path) {
			assertSame(200, $ctx->get($path)['status'], "GET {$path}");
		}

		assertContains('Hide and Seek', $ctx->get('/hideandseek')['body'], 'the landing page names the module');
	},

	'it has a database of its own, with none of the music module in it' => function ($ctx) {
		$ctx->get('/hideandseek');

		$tables = array_column(
			$ctx->dbFor(HNS_DB)->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(),
			'name'
		);

		foreach (['account', 'invite', 'login_attempt', 'schema_migrations'] as $table) {
			assertTrue(in_array($table, $tables, true), "the schema has a {$table} table");
		}

		assertTrue(!in_array('song', $tables, true), 'and nothing of the music module, which keeps its own file');
	},

	'the first account needs no invite and is made an admin' => function ($ctx) {
		$ctx->newSession();

		assertSame(0, (int)$ctx->dbFor(HNS_DB)->query("SELECT COUNT(*) c FROM account")->fetch()['c'], 'no accounts yet');
		assertContains('no invite code', $ctx->get('/hideandseek/register')['body'], 'and the form says so');

		$response = hnsRegister($ctx, 'hns_first', 'test password');
		assertSame(302, $response['status'], 'registering lands somewhere');

		$account = hnsAccountRow($ctx, 'hns_first');
		assertTrue($account !== null, 'the account exists');
		assertSame(1, (int)$account['is_admin'], 'and is an admin, since somebody has to be able to invite');
	},

	'a second account needs an invite, and each code is good once' => function ($ctx) {
		hnsLogIn($ctx, 'hns_first', 'test password');

		$code = hnsMakeInvite($ctx);
		assertTrue($code !== '', 'an admin can make an invite');

		$ctx->newSession();
		assertContains('formError', hnsRegister($ctx, 'hns_uninvited', 'test password')['body'], 'registering without a code is refused');
		assertTrue(hnsAccountRow($ctx, 'hns_uninvited') === null, 'and creates nothing');

		$ctx->newSession();
		assertSame(302, hnsRegister($ctx, 'hns_second', 'test password', $code)['status'], 'the code lets one person in');
		assertSame(0, (int)hnsAccountRow($ctx, 'hns_second')['is_admin'], 'who is not an admin');

		$ctx->newSession();
		hnsRegister($ctx, 'hns_third', 'test password', $code);
		assertTrue(hnsAccountRow($ctx, 'hns_third') === null, 'the same code does not let a second one in');
	},

	'logging in and out works, and the admin pages are gated' => function ($ctx) {
		$ctx->newSession();
		assertContains('formError', hnsLogIn($ctx, 'hns_first', 'wrong password')['body'], 'a bad password is refused');

		assertSame(302, hnsLogIn($ctx, 'hns_first', 'test password')['status'], 'the right one is accepted');
		assertContains('hns_first', $ctx->get('/hideandseek')['body'], 'and the page knows who is there');

		assertSame(200, $ctx->get('/hideandseek/accounts')['status'], 'an admin reaches the accounts page');

		hnsLogIn($ctx, 'hns_second', 'test password');
		$response = $ctx->get('/hideandseek/accounts');
		assertSame(302, $response['status'], 'a plain account is turned away from it');
		assertContains('/hideandseek', $response['location'], 'back to the landing page');

		hnsLogOut($ctx);
		assertContains('Log In', $ctx->get('/hideandseek')['body'], 'logging out puts the login link back');
	},

	/* The game map creator. Placed after the login case because every one of
	   these needs an account to act as, and case files share one database in
	   file order. */
	'a game map can be created, and is open as soon as it exists' => function ($ctx) {
		hnsLogIn($ctx, 'hns_first', 'test password');

		assertSame(200, $ctx->get('/hideandseek/game-maps')['status'], 'the page is reachable signed in');

		$response = hnsCreateGameMap($ctx, 'Groningen Centre');
		assertSame(302, $response['status'], 'creating redirects rather than re-rendering');
		assertContains('?map=', $response['location'], 'and the new map is named in the address');

		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		assertTrue($map !== null, 'the row is there');

		$body = $ctx->get('/hideandseek/game-maps')['body'];
		assertContains('Groningen Centre', $body, 'the page lists it');
		assertContains('Loaded: Groningen Centre', $body, 'and it is already open, with no second step');
	},

	/* The unique index collates NOCASE and so does the check in front of it. If
	   they disagreed the message would be skipped and the insert would 500. */
	'a game map name is taken once, whatever its casing' => function ($ctx) {
		$before = (int)$ctx->dbFor(HNS_DB)->query("SELECT COUNT(*) c FROM game_map")->fetch()['c'];

		$body = hnsCreateGameMap($ctx, 'groningen centre')['body'];
		assertContains('formError', $body, 'a clash is refused');
		assertContains('already a game map', $body, 'and says why');

		assertContains('formError', hnsCreateGameMap($ctx, '   ')['body'], 'so is a name that is only spaces');

		$after = (int)$ctx->dbFor(HNS_DB)->query("SELECT COUNT(*) c FROM game_map")->fetch()['c'];
		assertSame($before, $after, 'and neither wrote a row');
	},

	/* ?map= wins and is remembered; the session answers when it is absent. The
	   second half is the only part a URL cannot demonstrate on its own. */
	'loading a game map survives a request with no query string' => function ($ctx) {
		hnsCreateGameMap($ctx, 'Hoogkerk');

		$first = hnsGameMapRow($ctx, 'Groningen Centre');

		assertContains('Loaded: Hoogkerk', $ctx->get('/hideandseek/game-maps')['body'], 'the newest is open');

		$body = $ctx->get('/hideandseek/game-maps?map=' . (int)$first['id'])['body'];
		assertContains('Loaded: Groningen Centre', $body, 'the query string overrides it');

		$body = $ctx->get('/hideandseek/game-maps')['body'];
		assertContains('Loaded: Groningen Centre', $body, 'and is remembered without it');

		assertContains('Game maps', $ctx->get('/hideandseek')['body'], 'the nav offers the page on every signed-in page');
	},

	'a game map can be renamed, keeping its id' => function ($ctx) {
		$before = hnsGameMapRow($ctx, 'Hoogkerk');

		$response = $ctx->postForm('/hideandseek/game-maps', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/game-maps'),
			'action' => 'rename',
			'map_id' => (int)$before['id'],
			'name' => 'Hoogkerk West',
		]);
		assertSame(302, $response['status'], 'renaming redirects');

		$after = hnsGameMapRow($ctx, 'Hoogkerk West');
		assertTrue($after !== null, 'the new name is there');
		assertSame((int)$before['id'], (int)$after['id'], 'on the same row');
		assertTrue(hnsGameMapRow($ctx, 'Hoogkerk') === null, 'and the old name is gone');
	},

	/* The session still holds the id of a deleted map, so the page has to notice
	   the row is missing rather than naming it for the rest of the session. */
	'deleting the loaded game map unloads it' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Hoogkerk West');

		$ctx->get('/hideandseek/game-maps?map=' . (int)$map['id']);
		assertContains('Loaded: Hoogkerk West', $ctx->get('/hideandseek/game-maps')['body'], 'it is open first');

		$ctx->postForm('/hideandseek/game-maps', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/game-maps'),
			'action' => 'delete',
			'map_id' => (int)$map['id'],
		]);

		assertTrue(hnsGameMapRow($ctx, 'Hoogkerk West') === null, 'the row is gone');

		$body = $ctx->get('/hideandseek/game-maps')['body'];
		assertContains('No game map loaded', $body, 'and nothing is loaded any more');
		assertTrue(strpos($body, 'Hoogkerk West') === false, 'with the name nowhere on the page');
	},

	'an unrecognised action on the game map page changes nothing' => function ($ctx) {
		$before = $ctx->dbFor(HNS_DB)->query("SELECT id, name FROM game_map ORDER BY id")->fetchAll();

		$ctx->postForm('/hideandseek/game-maps', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/game-maps'),
			'action' => 'obliterate',
			'map_id' => (int)($before[0]['id'] ?? 0),
			'name' => 'nonsense',
		]);

		$after = $ctx->dbFor(HNS_DB)->query("SELECT id, name FROM game_map ORDER BY id")->fetchAll();
		assertSame($before, $after, 'the table is untouched');
	},

	'importing POIs needs a loaded game map' => function ($ctx) {
		$ctx->postForm('/hideandseek/game-maps', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/game-maps'),
			'action' => 'close',
		]);

		$response = $ctx->get('/hideandseek/import-pois');
		assertSame(200, $response['status'], 'the page is reachable signed in');
		assertContains('none is loaded', $response['body'], 'and says a map has to be loaded first');
		assertTrue(strpos($response['body'], 'poiImportForm') === false, 'with no form to paste into');
		assertContains('Import POIs', $ctx->get('/hideandseek')['body'], 'the nav offers the page');
	},

	'the page parses in the browser and hands the endpoint finished POIs' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		$body = $ctx->get('/hideandseek/import-pois?map=' . (int)$map['id'])['body'];

		assertContains('id="poiImportForm"', $body, 'the form is there');
		assertContains('data-map-id="' . (int)$map['id'] . '"', $body, 'naming the map it imports onto');
		assertContains('/modules/hideandseek/js/poiImport.js', $body, 'with the script that parses the paste');

		$script = $ctx->get('/modules/hideandseek/js/poiImport.js')['body'];
		assertContains('/hideandseek/ajax/import-pois', $script, 'which posts to the endpoint');
	},

	'imported POIs land on the map the request names' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');

		$response = hnsImportPois($ctx, (int)$map['id'], 'hospitals', [
			hnsPoi('node', 671690479, 52.0453777, 4.2095193, 'Parnassia'),
			hnsPoi('node', 2610213651, 51.9157861, 4.4801515),
			hnsPoi('way', 1299806696, 52.0930710, 4.3836918, 'HMC Antoniushove'),
			hnsPoi('relation', 3603304, 51.8264992, 4.6293176, '  Albert Schweitzer  '),
		]);
		assertSame(200, $response['status'], 'the import is accepted');
		assertSame(4, $response['json']['imported'], 'and counts what it saved');

		$rows = hnsPoiRows($ctx, (int)$map['id']);
		assertSame(4, count($rows), 'every POI is saved');
		assertSame(['hospitals'], array_values(array_unique(array_column($rows, 'category'))), 'under the category typed in');

		$byKey = [];
		foreach ($rows as $row) {
			$byKey[$row['osm_type'] . '/' . $row['osm_id']] = $row;
		}

		assertSame(52.0930710, (float)$byKey['way/1299806696']['lat'], 'at the position sent');
		assertSame('Albert Schweitzer', $byKey['relation/3603304']['name'], 'with the name trimmed');
		assertTrue($byKey['node/2610213651']['name'] === null, 'and an unnamed one stays unnamed');

		$body = $ctx->get('/hideandseek/import-pois?' . http_build_query([
			'map' => (int)$map['id'],
			'category' => $response['json']['categories'][0]['id'],
			'imported' => 4,
			'skipped' => 1,
		]))['body'];
		assertContains('Imported 4 POIs into hospitals', $body, 'the page reports what came in');
		assertContains('1 had no position', $body, 'and what the browser left out');
	},

	'importing into an existing category again merges rather than duplicating' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');

		hnsImportPois($ctx, (int)$map['id'], 'Hospitals', [
			hnsPoi('node', 671690479, 52.5, 4.5, 'Parnassia moved'),
			hnsPoi('node', 999, 53, 6, 'Martini'),
			hnsPoi('node', 999, 53, 6, 'Martini'),
		]);

		$rows = hnsPoiRows($ctx, (int)$map['id']);
		assertSame(5, count($rows), 'one new POI, one updated in place, a repeat within the paste counted once');
		assertSame(1, (int)$ctx->dbFor(HNS_DB)->query("SELECT COUNT(*) c FROM poi_category WHERE game_map_id = " . (int)$map['id'])->fetch()['c'], 'in the same category whatever its casing');

		$moved = array_values(array_filter($rows, function ($row) {
			return (int)$row['osm_id'] === 671690479;
		}))[0];
		assertSame(52.5, (float)$moved['lat'], 'the re-imported POI takes its new position');
		assertSame('Parnassia moved', $moved['name'], 'and its new name');
	},

	'the endpoint checks every POI rather than trusting the page' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		$mapId = (int)$map['id'];
		$good = hnsPoi('node', 5, 53.1, 6.1, 'X');
		$before = count(hnsPoiRows($ctx, $mapId));

		$refusals = [
			'a missing category' => hnsImportPois($ctx, $mapId, '  ', [$good]),
			'no POIs at all' => hnsImportPois($ctx, $mapId, 'pharmacies', []),
			'a map that does not exist' => hnsImportPois($ctx, 999999, 'pharmacies', [$good]),
			'an unknown element type' => hnsImportPois($ctx, $mapId, 'pharmacies', [$good, hnsPoi('area', 6, 53, 6)]),
			'an id that is not a number' => hnsImportPois($ctx, $mapId, 'pharmacies', [$good, hnsPoi('node', 'abc', 53, 6)]),
			'a latitude off the globe' => hnsImportPois($ctx, $mapId, 'pharmacies', [$good, hnsPoi('node', 7, 91, 6)]),
			'a coordinate sent as text' => hnsImportPois($ctx, $mapId, 'pharmacies', [$good, hnsPoi('node', 8, '53', 6)]),
			'a name that is not text' => hnsImportPois($ctx, $mapId, 'pharmacies', [$good, hnsPoi('node', 9, 53, 6, ['x'])]),
			'an object where a list belongs' => hnsImportPois($ctx, $mapId, 'pharmacies', ['a' => $good]),
		];

		foreach ($refusals as $what => $response) {
			assertSame(400, $response['status'], "{$what} is refused");
			assertTrue(($response['json']['error'] ?? '') !== '', "{$what} says why");
		}

		assertContains('needs a category name', $refusals['a missing category']['json']['error'], 'in words');
		assertSame($before, count(hnsPoiRows($ctx, $mapId)), 'and none of them wrote anything');
		assertSame(0, (int)$ctx->dbFor(HNS_DB)->query("SELECT COUNT(*) c FROM poi_category WHERE name = 'pharmacies'")->fetch()['c'], 'not even the category');
	},

	'a category carries a colour and a one-character icon' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		$mapId = (int)$map['id'];
		$poi = [hnsPoi('node', 41, 53.2, 6.5, 'Pharmacy')];

		$response = hnsImportPois($ctx, $mapId, 'pharmacies', $poi, ['colour' => '#0A7A57', 'icon' => ' 💊 ']);
		assertSame(200, $response['status'], 'an import with a colour and an icon is accepted');

		$row = hnsCategoryRow($ctx, $mapId, 'pharmacies');
		assertSame('#0a7a57', $row['colour'], 'the colour is stored lowercase');
		assertSame('💊', $row['icon'], 'and the icon trimmed');

		foreach (['👩‍⚕️', '🇳🇱', '1️⃣', '👍🏽', 'P'] as $icon) {
			assertSame(200, hnsImportPois($ctx, $mapId, 'pharmacies', $poi, ['colour' => '#0a7a57', 'icon' => $icon])['status'], json_encode($icon) . ' is one character');
		}

		hnsImportPois($ctx, $mapId, 'Pharmacies', $poi, ['colour' => '#ff0000', 'icon' => '']);
		$row = hnsCategoryRow($ctx, $mapId, 'pharmacies');
		assertSame('#ff0000', $row['colour'], 'importing into the category again restyles it');
		assertTrue($row['icon'] === null, 'and an empty icon clears it');

		$refusals = [
			'a colour name' => ['colour' => 'red', 'icon' => ''],
			'a short hex' => ['colour' => '#f00', 'icon' => ''],
			'a colour that is not text' => ['colour' => 255, 'icon' => ''],
			'two characters' => ['colour' => '#ff0000', 'icon' => 'ab'],
			'two emoji' => ['colour' => '#ff0000', 'icon' => '💊💊'],
			'an icon that is not text' => ['colour' => '#ff0000', 'icon' => ['💊']],
		];

		foreach ($refusals as $what => $style) {
			assertSame(400, hnsImportPois($ctx, $mapId, 'pharmacies', $poi, $style)['status'], "{$what} is refused");
		}

		assertContains('hex code', hnsImportPois($ctx, $mapId, 'pharmacies', $poi, $refusals['a colour name'])['json']['error'], 'a bad colour says why');
		assertContains('one character', hnsImportPois($ctx, $mapId, 'pharmacies', $poi, $refusals['two emoji'])['json']['error'], 'and so does a bad icon');
		assertSame('#ff0000', hnsCategoryRow($ctx, $mapId, 'pharmacies')['colour'], 'none of them changed the category');
	},

	'a category can be restyled from the import page' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		$mapId = (int)$map['id'];
		$category = hnsCategoryRow($ctx, $mapId, 'pharmacies');

		$body = $ctx->get('/hideandseek/import-pois?map=' . $mapId)['body'];
		assertContains('id="poiColour"', $body, 'the import form asks for a colour');
		assertContains('id="poiIcon"', $body, 'and an icon');
		assertContains('name="categories[' . (int)$category['id'] . '][colour]"', $body, 'and each category row edits its own');
		assertContains('data-colour="#ff0000"', $body, 'the category names carry their style, for the form to pick up');

		$restyle = function ($categoryId, $colour, $icon, $onMap = null) use ($ctx, $mapId) {
			return $ctx->postForm('/hideandseek/import-pois', [
				'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/import-pois'),
				'action' => 'styles',
				'map_id' => $onMap ?? $mapId,
				'only' => $categoryId,
				'categories' => [$categoryId => ['name' => 'pharmacies', 'colour' => $colour, 'icon' => $icon]],
			]);
		};

		assertSame(302, $restyle((int)$category['id'], '#7B3FC4', '⚕️')['status'], 'saving redirects');
		$row = hnsCategoryRow($ctx, $mapId, 'pharmacies');
		assertSame('#7b3fc4', $row['colour'], 'the colour is saved');
		assertSame('⚕️', $row['icon'], 'and the icon');

		$body = $restyle((int)$category['id'], '#7b3fc4', 'xyz')['body'];
		assertContains('formError', $body, 'a bad icon is refused on the page');
		assertContains('one character', $body, 'and says why');
		assertSame('⚕️', hnsCategoryRow($ctx, $mapId, 'pharmacies')['icon'], 'leaving the category as it was');

		$restyle((int)$category['id'], '#000000', '', 999999);
		assertSame('#7b3fc4', hnsCategoryRow($ctx, $mapId, 'pharmacies')['colour'], 'a category is only restyled through the map it belongs to');

		$ctx->postForm('/hideandseek/import-pois', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/import-pois'),
			'action' => 'delete',
			'map_id' => $mapId,
			'category_id' => (int)$category['id'],
		]);
	},

	'a category can be renamed on the import page, once per map and never to nothing' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		$mapId = (int)$map['id'];

		hnsImportCategories($ctx, $mapId, [
			['name' => 'trams', 'colour' => '#0369a1', 'icon' => 'T', 'pois' => [hnsPoi('node', 71, 53.21, 6.56, 'Stop A'), hnsPoi('node', 72, 53.22, 6.57, 'Stop B')]],
			['name' => 'buses', 'colour' => '#b45309', 'icon' => '', 'pois' => [hnsPoi('node', 73, 53.23, 6.58, 'Stop C')]],
		]);
		$trams = hnsCategoryRow($ctx, $mapId, 'trams');

		$body = $ctx->get('/hideandseek/import-pois?map=' . $mapId)['body'];
		assertTrue((bool)preg_match('#<input type="text" name="categories\[' . (int)$trams['id'] . '\]\[name\]"[^>]*value="trams"#', $body), 'each category row has its name as a field');

		$rename = function ($name, $onMap = null) use ($ctx, $mapId, $trams) {
			return $ctx->postForm('/hideandseek/import-pois', [
				'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/import-pois?map=' . $mapId),
				'action' => 'styles',
				'map_id' => $onMap ?? $mapId,
				'only' => (int)$trams['id'],
				'categories' => [(int)$trams['id'] => ['name' => $name, 'colour' => '#0369a1', 'icon' => 'T']],
			]);
		};

		assertSame(302, $rename('  Tram stops ')['status'], 'renaming redirects');
		$renamed = hnsCategoryRow($ctx, $mapId, 'Tram stops');
		assertTrue($renamed !== null, 'the new name is saved, trimmed');
		assertSame((int)$trams['id'], (int)$renamed['id'], 'on the same category');
		assertSame(2, count(array_filter(hnsPoiRows($ctx, $mapId), function ($row) {
			return $row['category'] === 'Tram stops';
		})), 'which keeps its POIs');

		$body = $rename('BUSES')['body'];
		assertContains('would both be called', $body, 'a name another category has is refused, whatever its casing');
		assertTrue(hnsCategoryRow($ctx, $mapId, 'Tram stops') !== null, 'leaving the name as it was');

		assertContains('needs a name', $rename('   ')['body'], 'so is an empty name');

		assertSame(302, $rename('tram stops')['status'], 'changing only the casing of its own name is fine');
		assertSame('tram stops', hnsCategoryRow($ctx, $mapId, 'tram stops')['name'], 'and is saved');

		$rename('Hijacked', 999999);
		assertTrue(hnsCategoryRow($ctx, $mapId, 'Hijacked') === null, 'a category is only renamed through the map it belongs to');

		foreach (['tram stops', 'buses'] as $name) {
			$ctx->postForm('/hideandseek/import-pois', [
				'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/import-pois?map=' . $mapId),
				'action' => 'delete',
				'map_id' => $mapId,
				'category_id' => (int)hnsCategoryRow($ctx, $mapId, $name)['id'],
			]);
		}
	},

	'save all writes every category row at once, names checked as a set' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		$mapId = (int)$map['id'];

		hnsImportCategories($ctx, $mapId, [
			['name' => 'left', 'colour' => '#111111', 'icon' => 'L', 'pois' => [hnsPoi('node', 81, 53.21, 6.56, 'L1')]],
			['name' => 'right', 'colour' => '#222222', 'icon' => 'R', 'pois' => [hnsPoi('node', 82, 53.22, 6.57, 'R1')]],
		]);
		$left = (int)hnsCategoryRow($ctx, $mapId, 'left')['id'];
		$right = (int)hnsCategoryRow($ctx, $mapId, 'right')['id'];
		$hospitals = hnsCategoryRow($ctx, $mapId, 'hospitals');

		$body = $ctx->get('/hideandseek/import-pois?map=' . $mapId)['body'];
		assertTrue((bool)preg_match('#<form id="poiCategoriesForm"[^>]*>(?:(?!</form>).)*<button type="submit">Save all</button>#s', $body), 'the page has a save all button in the shared form');
		assertTrue(strpos($body, 'id="poiCategoriesForm"') < strpos($body, 'name="only"'), 'ahead of every row button, so Enter in a field saves everything');

		$save = function ($categories, $only = null) use ($ctx, $mapId) {
			$fields = [
				'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/import-pois?map=' . $mapId),
				'action' => 'styles',
				'map_id' => $mapId,
				'categories' => $categories,
			];

			if ($only !== null) {
				$fields['only'] = $only;
			}

			return $ctx->postForm('/hideandseek/import-pois', $fields);
		};

		$swap = [
			$left => ['name' => 'right', 'colour' => '#333333', 'icon' => 'R'],
			$right => ['name' => 'left', 'colour' => '#444444', 'icon' => 'L'],
			(int)$hospitals['id'] => ['name' => $hospitals['name'], 'colour' => '', 'icon' => ''],
		];
		assertSame(302, $save($swap)['status'], 'two names swapped in one save are accepted');
		assertSame($left, (int)hnsCategoryRow($ctx, $mapId, 'right')['id'], 'the first category took the second name');
		assertSame($right, (int)hnsCategoryRow($ctx, $mapId, 'left')['id'], 'and the second the first');
		assertSame('#333333', hnsCategoryRow($ctx, $mapId, 'right')['colour'], 'with the colours saved alongside');

		$clash = [
			$left => ['name' => 'Hospitals', 'colour' => '#555555', 'icon' => ''],
			$right => ['name' => 'left', 'colour' => '#444444', 'icon' => 'L'],
		];
		$body = $save($clash)['body'];
		assertContains('both be called', $body, 'a name clashing with a category not being renamed is refused');
		assertContains('value="#555555"', $body, 'and the page keeps what was typed');
		assertSame('#333333', hnsCategoryRow($ctx, $mapId, 'right')['colour'], 'while nothing was written');

		$both = [
			$left => ['name' => 'right', 'colour' => '#666666', 'icon' => 'R'],
			$right => ['name' => 'left', 'colour' => '#777777', 'icon' => 'L'],
		];
		assertSame(302, $save($both, $right)['status'], 'a row\'s own save button saves');
		assertSame('#777777', hnsCategoryRow($ctx, $mapId, 'left')['colour'], 'its row');
		assertSame('#333333', hnsCategoryRow($ctx, $mapId, 'right')['colour'], 'and only its row');

		foreach (['left', 'right'] as $name) {
			$ctx->postForm('/hideandseek/import-pois', [
				'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/import-pois?map=' . $mapId),
				'action' => 'delete',
				'map_id' => $mapId,
				'category_id' => (int)hnsCategoryRow($ctx, $mapId, $name)['id'],
			]);
		}
	},

	'several categories import in one request, or none of them do' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		$mapId = (int)$map['id'];
		$before = count(hnsPoiRows($ctx, $mapId));

		$batch = [
			['name' => 'bakeries', 'colour' => '#b45309', 'icon' => '🥐', 'pois' => [hnsPoi('node', 51, 53.21, 6.56, 'Bakker')]],
			['name' => 'ferries', 'colour' => '#0369a1', 'icon' => '', 'pois' => [hnsPoi('node', 52, 53.22, 6.57), hnsPoi('way', 53, 53.23, 6.58, 'Pont')]],
		];

		$bad = $batch;
		$bad[1]['colour'] = 'blue';

		assertSame(400, hnsImportCategories($ctx, $mapId, $bad)['status'], 'one bad category refuses the batch');
		assertSame($before, count(hnsPoiRows($ctx, $mapId)), 'and writes nothing, not even the good one');
		assertTrue(hnsCategoryRow($ctx, $mapId, 'bakeries') === null, 'not even its category');

		$response = hnsImportCategories($ctx, $mapId, $batch);
		assertSame(200, $response['status'], 'a good batch is accepted');
		assertSame(3, $response['json']['imported'], 'counting every POI in it');
		assertSame(['bakeries', 'ferries'], array_column($response['json']['categories'], 'name'), 'and naming each category');
		assertSame('🥐', hnsCategoryRow($ctx, $mapId, 'bakeries')['icon'], 'each with its own style');

		$body = $ctx->get('/hideandseek/import-pois?map=' . $mapId . '&imported=3&categories=2')['body'];
		assertContains('Imported 3 POIs into 2 categories', $body, 'the page reports a batch as a batch');
	},

	'the export holds every category with its style and imports back unchanged' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		$mapId = (int)$map['id'];

		$body = $ctx->get('/hideandseek/import-pois?map=' . $mapId)['body'];
		assertContains('id="poiExportButton"', $body, 'the page offers an export');
		$script = $ctx->get('/modules/hideandseek/js/poiImport.js')['body'];
		assertContains('/hideandseek/ajax/export-pois', $script, 'which the script fetches');
		assertContains('"hideandseek-pois"', $script, 'and whose format the import recognises');

		$response = hnsExportPois($ctx, $mapId);
		assertSame(200, $response['status'], 'the export answers');
		$export = $response['json']['export'];

		assertSame('hideandseek-pois', $export['format'], 'naming its format');
		assertSame(1, $export['version'], 'and version');
		assertSame('Groningen Centre', $export['map'], 'and the map it came from');
		assertSame(['bakeries', 'ferries', 'hospitals'], array_column($export['categories'], 'name'), 'every category, by name');
		assertSame(count(hnsPoiRows($ctx, $mapId)), array_sum(array_map('count', array_column($export['categories'], 'pois'))), 'with every POI');

		$bakeries = $export['categories'][0];
		assertSame('#b45309', $bakeries['colour'], 'a category carries its colour');
		assertSame('🥐', $bakeries['icon'], 'and its icon');
		assertSame(['osmType' => 'node', 'osmId' => 51, 'name' => 'Bakker', 'lat' => 53.21, 'lon' => 6.56], $bakeries['pois'][0], 'and a POI everything the import needs');
		assertSame('', $export['categories'][2]['colour'], 'a category with no colour exports an empty one');

		hnsCreateGameMap($ctx, 'Export Copy');
		$copy = hnsGameMapRow($ctx, 'Export Copy');

		assertSame(200, hnsImportCategories($ctx, (int)$copy['id'], $export['categories'])['status'], 'the export imports as it is into another map');

		$again = hnsExportPois($ctx, (int)$copy['id'])['json']['export'];
		assertSame($export['categories'], $again['categories'], 'and exports back identically');

		assertSame(400, hnsExportPois($ctx, 999999)['status'], 'a map that does not exist has no export');

		$ctx->postForm('/hideandseek/game-maps', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/game-maps'),
			'action' => 'delete',
			'map_id' => (int)$copy['id'],
		]);

		foreach (['bakeries', 'ferries'] as $name) {
			$ctx->postForm('/hideandseek/import-pois', [
				'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/import-pois?map=' . $mapId),
				'action' => 'delete',
				'map_id' => $mapId,
				'category_id' => (int)hnsCategoryRow($ctx, $mapId, $name)['id'],
			]);
		}
	},

	'the homepage carries the loaded game map\'s POIs for the map and the list' => function ($ctx) {
		hnsCreateGameMap($ctx, 'Homepage Test');
		$map = hnsGameMapRow($ctx, 'Homepage Test');

		hnsImportPois($ctx, (int)$map['id'], 'stations', [
			hnsPoi('node', 31, 52.37, 4.89, 'Amsterdam </script><b>Centraal'),
			hnsPoi('way', 32, 53.21, 6.56),
		], ['colour' => '#1d4ed8', 'icon' => '🚉']);
		hnsImportPois($ctx, (int)$map['id'], 'airports', [hnsPoi('node', 33, 52.31, 4.76, 'Schiphol')]);

		$stations = (int)hnsCategoryRow($ctx, (int)$map['id'], 'stations')['id'];
		$airports = (int)hnsCategoryRow($ctx, (int)$map['id'], 'airports')['id'];

		$body = $ctx->get('/hideandseek')['body'];

		assertContains('id="hnsImportedPoiTable"', $body, 'the list has a container of its own');
		assertSame([
			'categories' => [
				['id' => $airports, 'name' => 'airports', 'colour' => null, 'icon' => null],
				['id' => $stations, 'name' => 'stations', 'colour' => '#1d4ed8', 'icon' => '🚉'],
			],
			'pois' => [
				['category' => $stations, 'name' => '', 'lat' => 53.21, 'lng' => 6.56],
				['category' => $stations, 'name' => 'Amsterdam </script><b>Centraal', 'lat' => 52.37, 'lng' => 4.89],
				['category' => $airports, 'name' => 'Schiphol', 'lat' => 52.31, 'lng' => 4.76],
			],
		], hnsHomeImportedPois($body), 'every POI on the loaded map is handed over with its category style, wherever it is');
		assertTrue(strpos($body, '</script><b>') === false, 'and a name cannot close the script tag carrying it');

		$script = $ctx->get('/modules/hideandseek/js/map.js')['body'];
		assertContains('hnsImportedPois', $script, 'the map script reads them');

		hnsLogOut($ctx);
		assertSame(['categories' => [], 'pois' => []], hnsHomeImportedPois($ctx->get('/hideandseek?map=' . (int)$map['id'])['body']), 'signed out, nothing is handed over, even asked for by id');

		hnsLogIn($ctx, 'hns_first', 'test password');
		$ctx->postForm('/hideandseek/game-maps', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/game-maps'),
			'action' => 'delete',
			'map_id' => (int)$map['id'],
		]);
		assertSame(['categories' => [], 'pois' => []], hnsHomeImportedPois($ctx->get('/hideandseek')['body']), 'and with no map loaded the list is empty');
	},

	'a category can be deleted, and deleting the game map takes its POIs with it' => function ($ctx) {
		$map = hnsGameMapRow($ctx, 'Groningen Centre');
		hnsImportPois($ctx, (int)$map['id'], 'schools', [hnsPoi('node', 11, 53.21, 6.56, 'Nassauschool')]);
		$schools = $ctx->dbFor(HNS_DB)->query("SELECT id FROM poi_category WHERE name = 'schools'")->fetch();

		assertContains('poiCategoryTable', $ctx->get('/hideandseek/import-pois?map=' . (int)$map['id'])['body'], 'the categories are listed');

		$ctx->postForm('/hideandseek/import-pois', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/import-pois'),
			'action' => 'delete',
			'map_id' => (int)$map['id'],
			'category_id' => (int)$schools['id'],
		]);

		$categories = array_unique(array_column(hnsPoiRows($ctx, (int)$map['id']), 'category'));
		assertSame(['hospitals'], array_values($categories), 'the category and its POIs are gone, the other is not');

		hnsCreateGameMap($ctx, 'Throwaway');
		$throwaway = hnsGameMapRow($ctx, 'Throwaway');
		hnsImportPois($ctx, (int)$throwaway['id'], 'stations', [hnsPoi('node', 7, 53.2, 6.5)]);
		assertSame(1, count(hnsPoiRows($ctx, (int)$throwaway['id'])), 'a second map has its own POIs');

		$ctx->postForm('/hideandseek/game-maps', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/game-maps'),
			'action' => 'delete',
			'map_id' => (int)$throwaway['id'],
		]);

		assertSame(0, (int)$ctx->dbFor(HNS_DB)->query("SELECT COUNT(*) c FROM poi WHERE osm_id = 7")->fetch()['c'], 'deleting the map deleted them');
		assertSame(5, count(hnsPoiRows($ctx, (int)$map['id'])), 'and left the first map alone');
	},

	'the game map page is behind the account system' => function ($ctx) {
		hnsLogOut($ctx);

		foreach (['/hideandseek/game-maps', '/hideandseek/import-pois'] as $path) {
			$response = $ctx->get($path);
			assertSame(302, $response['status'], "signed out, {$path} redirects");
			assertContains('/hideandseek/login', $response['location'], 'to the login page');
		}

		assertSame(401, hnsImportPois($ctx, 1, 'anything', [hnsPoi('node', 1, 53, 6)])['status'], 'and the import endpoint answers 401');
		assertSame(401, hnsExportPois($ctx, 1)['status'], 'and so does the export');
	},

	/* The whole reason sessionScope() exists: two modules, two sets of accounts,
	   one browser. Being signed in to one says nothing about the other. */
	'its accounts are separate from the music module\'s' => function ($ctx) {
		$ctx->ensureLoggedIn();

		assertContains('/hideandseek/login', $ctx->get('/hideandseek')['body'], 'a music session is signed out here');

		hnsLogIn($ctx, 'hns_first', 'test password');

		assertContains('hns_first', $ctx->get('/hideandseek')['body'], 'signed in here');
		assertContains('test_runner', $ctx->get('/music/songs')['body'], 'and still signed in there, in the same browser');
	},

	/* This module has no colour picker - one fixed theme - so the only
	   preference it keeps is the language. It bites once there is a second
	   locale to pick: with one, a locale chosen in music is rejected here
	   anyway and falls through to the same default. */
	'its language is separate from the music module\'s' => function ($ctx) {
		/* A music account of its own: switching language writes through to the
		   account row, and every later case file would inherit a test_runner
		   left in German. */
		$ctx->ensureLoggedIn('hns_preference_rater', 'test password', false);
		hnsLogIn($ctx, 'hns_first', 'test password');

		$ctx->postForm('/music/language', [
			'csrf_token' => $ctx->csrfTokenFrom('/music/songs'),
			'lang' => 'de',
			'return' => '/music/songs',
		]);

		assertContains('lang="de"', $ctx->get('/music/songs')['body'], 'music switches language');
		assertContains('lang="en"', $ctx->get('/hideandseek')['body'], 'hide and seek does not follow it');
	},

	'an admin can promote and demote, but never themselves' => function ($ctx) {
		hnsLogIn($ctx, 'hns_first', 'test password');

		$second = hnsAccountRow($ctx, 'hns_second');
		$first = hnsAccountRow($ctx, 'hns_first');

		$promote = function ($accountId, $action) use ($ctx) {
			$ctx->postForm('/hideandseek/accounts', [
				'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/accounts'),
				'account_id' => $accountId,
				'action' => $action,
			]);
		};

		$promote($second['id'], 'promote');
		assertSame(1, (int)hnsAccountRow($ctx, 'hns_second')['is_admin'], 'a plain account can be made an admin');

		$promote($second['id'], 'demote');
		assertSame(0, (int)hnsAccountRow($ctx, 'hns_second')['is_admin'], 'and demoted again');

		/// Otherwise the last admin could lock everybody out of the admin pages.
		$promote($first['id'], 'demote');
		assertSame(1, (int)hnsAccountRow($ctx, 'hns_first')['is_admin'], 'but an admin cannot demote themselves');
	},

	/* Invites are made and revoked from the accounts page rather than a page of
	   their own: both answer "who is allowed in here", and one of them was a
	   button and a list. */
	'invites live on the accounts page, not a page of their own' => function ($ctx) {
		hnsLogIn($ctx, 'hns_first', 'test password');

		assertSame(404, $ctx->get('/hideandseek/invites')['status'], 'the old page is gone');

		$body = $ctx->get('/hideandseek/accounts')['body'];
		assertContains('id="accountTable"', $body, 'the accounts table is there');
		assertContains('id="invites"', $body, 'and the invites section under it');
		assertContains('value="invite"', $body, 'with the button that makes one');
		assertTrue(strpos($body, '/hideandseek/invites') === false, 'and nothing still pointing at the old page');

		/// Scoped to the nav: head.php ships the whole catalogue as JSON, so the
		/// word is on every page whether or not anything links to it.
		$home = $ctx->get('/hideandseek')['body'];
		$nav = substr($home, strpos($home, '<nav'), strpos($home, '</nav>') - strpos($home, '<nav'));
		assertTrue(strpos($nav, 'Invites') === false, 'the bar lost its Invites button');
	},

	/// One handler, so an action it does not know must do nothing rather than
	/// fall through to whichever branch happens to be last.
	'an unrecognised action on the accounts page changes nothing' => function ($ctx) {
		hnsLogIn($ctx, 'hns_first', 'test password');

		$second = hnsAccountRow($ctx, 'hns_second');
		$before = (int)$ctx->dbFor(HNS_DB)->query("SELECT COUNT(*) c FROM invite")->fetch()['c'];

		$ctx->postForm('/hideandseek/accounts', [
			'csrf_token' => $ctx->csrfTokenFrom('/hideandseek/accounts'),
			'account_id' => $second['id'],
			'invite_id' => '1',
			'action' => 'something-else',
		]);

		assertSame((int)$second['is_admin'], (int)hnsAccountRow($ctx, 'hns_second')['is_admin'], 'nobody was promoted or demoted');
		assertSame($before, (int)$ctx->dbFor(HNS_DB)->query("SELECT COUNT(*) c FROM invite")->fetch()['c'], 'and no invite was made');
	},

	'the ajax endpoint is guarded the same way the music ones are' => function ($ctx) {
		$ctx->newSession();
		assertSame(401, $ctx->post('/hideandseek/ajax/whoami', [])['status'], 'signed out');

		hnsLogIn($ctx, 'hns_first', 'test password');

		assertSame(405, $ctx->get('/hideandseek/ajax/whoami')['status'], 'a GET is refused');
		assertSame(403, $ctx->postWithoutCsrf('/hideandseek/ajax/whoami', [])['status'], 'a missing token is refused');
		assertSame(400, $ctx->post('/hideandseek/ajax/whoami', 'null')['status'], 'a malformed body is refused');

		$response = $ctx->post('/hideandseek/ajax/whoami', []);
		assertSame('ok', $response['json']['status'], 'a good call answers');
		assertSame('hns_first', $response['json']['name'], 'as the signed-in account');
		assertTrue($response['json']['isAdmin'], 'and says it is an admin');
	},

	/* One Account button either way, rather than Log In and Register competing
	   for a slot in the bar: signed out it holds the two ways in, signed in the
	   way out. */
	'log in and register live under one account button' => function ($ctx) {
		$ctx->newSession();
		$body = $ctx->get('/hideandseek')['body'];

		assertContains('hnsNavAccount', $body, 'signed out, the bar carries the account menu');
		assertContains('>Account<', $body, 'labelled as such rather than as either half');
		assertContains('/hideandseek/login', $body, 'holding the way in');
		assertContains('/hideandseek/register', $body, 'and the way to make one');

		$nav = substr($body, strpos($body, '<nav'), strpos($body, '</nav>') - strpos($body, '<nav'));
		assertSame(1, substr_count($nav, 'hnsNavMenuButton'), 'one top level button, not two');

		hnsLogIn($ctx, 'hns_first', 'test password');
		$body = $ctx->get('/hideandseek')['body'];

		assertContains('>hns_first<', $body, 'signed in, the same button is the account name');
		assertContains('/hideandseek/logout', $body, 'and holds the way out');
		assertTrue(strpos($body, '/hideandseek/register') === false, 'with no way to register while signed in');
	},

	/* The nav is part of the shell rather than something each view remembers to
	   include, so a new view cannot ship without it. */
	'the shell wraps every page in the layout and the nav' => function ($ctx) {
		hnsLogIn($ctx, 'hns_first', 'test password');

		foreach (['/hideandseek', '/hideandseek/accounts', '/hideandseek/login'] as $path) {
			$body = $ctx->get($path, true)['body'];

			assertContains('<div class="hnsLayout">', $body, "{$path} opens the layout");
			assertContains('<nav class="hnsNav">', $body, "{$path} carries the nav");
			assertContains('<main class="hnsMain">', $body, "{$path} puts its content in main");
			assertSame(1, substr_count($body, '<nav class="hnsNav">'), "{$path} carries exactly one");
			assertContains('</main>', $body, "{$path} closes it");
		}
	},

	/* A fixed theme: no picker, no per-account hue, and nothing left behind that
	   would render half the palette from a value nobody can set. */
	'there is no colour picker anywhere in it' => function ($ctx) {
		hnsLogIn($ctx, 'hns_first', 'test password');

		assertSame(404, $ctx->get('/hideandseek/colour')['status'], 'the route is gone');
		assertSame(404, $ctx->get('/modules/hideandseek/js/colour.js')['status'], 'and so is its script');

		$body = $ctx->get('/hideandseek')['body'];
		assertTrue(strpos($body, '--hue') === false, 'no page declares a hue');
		assertTrue(strpos($body, 'navColour') === false, 'and the bar has no swatch');

		$columns = array_column(
			$ctx->dbFor(HNS_DB)->query("PRAGMA table_info(account)")->fetchAll(),
			'name'
		);
		assertTrue(!in_array('hue', $columns, true), 'and the account table has no hue to store');
	},

	'every page carries the tooltip box, its script and the stylesheet' => function ($ctx) {
		hnsLogIn($ctx, 'hns_first', 'test password');

		foreach (['/hideandseek', '/hideandseek/accounts'] as $path) {
			$body = $ctx->get($path)['body'];

			assertContains('id="hnsTooltip"', $body, "{$path} ships the tooltip box");
			assertContains('/modules/hideandseek/js/tooltip.js', $body, "{$path} ships its script");
			assertContains('/modules/hideandseek/css/styles.css', $body, "{$path} ships the stylesheet");
		}

		assertSame(200, $ctx->get('/modules/hideandseek/js/tooltip.js')['status'], 'the script serves');
		assertSame(200, $ctx->get('/modules/hideandseek/css/styles.css')['status'], 'and so does the stylesheet');
	},

	/* The map itself is javascript and WebGL, neither of which this harness
	   runs. What it can prove is that every piece ships, that the page wires
	   them, and that the container carries the centre the route chose. */
	'the landing page carries a map container and the script that fills it' => function ($ctx) {
		$body = $ctx->get('/hideandseek')['body'];

		assertContains('id="hnsMap"', $body, 'the container is there');
		assertContains('data-lat="', $body, 'carrying the centre');
		assertContains('data-zoom="', $body, 'and the zoom, so the script hardcodes neither');

		assertTrue((bool)preg_match('#/modules/hideandseek/js/map\.js\?v=\d+#', $body), 'map.js is linked with a cache stamp');

		foreach (['/modules/hideandseek/js/map.js', '/modules/hideandseek/vendor/maplibre/maplibre-gl.js', '/modules/hideandseek/vendor/maplibre/maplibre-gl.css', '/modules/hideandseek/json/mapStyle.json'] as $asset) {
			assertSame(200, $ctx->get($asset)['status'], "{$asset} serves");
		}
	},

	/* MapLibre is 803KB and needs WebGL, so the page ships neither it nor its
	   stylesheet - map.js injects both only once the button is pressed. */
	'the map library is injected on demand, not shipped with the page' => function ($ctx) {
		$body = $ctx->get('/hideandseek')['body'];

		assertTrue(strpos($body, 'maplibre-gl.js') === false, 'the page does not ship the library');
		assertTrue(strpos($body, 'maplibre-gl.css') === false, 'nor its stylesheet');

		$script = $ctx->get('/modules/hideandseek/js/map.js')['body'];
		assertContains('maplibre-gl.js', $script, 'the script is what pulls it in');
		assertContains('map.load', $script, 'behind the load button');
	},

	/* The tiles come from a third party, so the page must not ask for them until
	   somebody does. The container ships empty for the script to fill. */
	'no tile server is named anywhere in the delivered page' => function ($ctx) {
		$body = $ctx->get('/hideandseek')['body'];

		assertTrue(strpos($body, 'openfreemap.org') === false, 'the page names no tile server');
		assertTrue((bool)preg_match('/<div\s+id="hnsMap"[^>]*>\s*<\/div>/', $body), 'and the container ships empty');

		/// The style file names it, and that is fetched by the script rather
		/// than by the page.
		assertContains('openfreemap.org', $ctx->get('/modules/hideandseek/json/mapStyle.json')['body'], 'the style is what knows the source');
	},

	/// The style is the whole point of the vector option, so a typo in it must
	/// fail here rather than as a blank map in somebody's browser.
	/* The style has twice been replaced wholesale by a foreign one pasted out of
	   Maputnik's gallery - once AWS Location, once MapTiler's OSM Bright - each
	   time carrying somebody else's API key and rendering nothing. Pinning the
	   layer list would be useless against 128 layers and would not have caught
	   either. These two properties would have caught both. */
	'the map style points only at hosts we chose' => function ($ctx) {
		$body = $ctx->get('/modules/hideandseek/json/mapStyle.json')['body'];
		$style = json_decode($body, true);

		assertTrue(is_array($style), 'the style parses');
		assertSame(8, $style['version'], 'and is a version 8 style');
		assertTrue(isset($style['sources']['openmaptiles']), 'reading the OpenMapTiles schema');

		/// Every tile and glyph URL is ours. The sprite is the one exception and
		/// is named here so it reads as a decision rather than a gap - see the
		/// loose end in docs/hideandseek.md.
		$urls = [$style['glyphs']];
		foreach ($style['sources'] as $source) {
			$urls = array_merge($urls, isset($source['url']) ? [$source['url']] : ($source['tiles'] ?? []));
		}

		foreach ($urls as $url) {
			assertContains('tiles.openfreemap.org', $url, "{$url} is served from the host we picked");
		}

		assertContains('openmaptiles.github.io', $style['sprite'], 'the sprite is the known exception');

		/// A style that needs a credential is a style pointing somewhere we did
		/// not choose. MapTiler's placeholder literally reads get_your_own_...
		foreach (['key=', 'access_token', 'api_key', 'get_your_own'] as $secret) {
			assertTrue(strpos($body, $secret) === false, "the style embeds no {$secret}");
		}
	},

	'the base map writes no labels, and only imported places are listed' => function ($ctx) {
		$body = $ctx->get('/hideandseek')['body'];

		assertContains('id="hnsImportedPoiTable"', $body, 'the list of imported places is there');
		assertTrue(strpos($body, 'id="hnsPoiTable"') === false, 'and the list of what the base map drew is gone');

		$script = $ctx->get('/modules/hideandseek/js/map.js')['body'];

		assertTrue(strpos($script, 'queryRenderedFeatures') === false, 'nothing reads the drawn base map any more');
		assertTrue(strpos($script, '"idle"') === false, 'or rebuilds on idle');

		foreach (['HNS_LABEL_SOURCE_LAYERS', 'HNS_KEPT_CLASSES', 'HNS_KEPT_SOURCE_LAYERS', 'HNS_KEPT_SOURCE_CLASSES', 'hnsOpenPoiClasses'] as $retired) {
			assertTrue(strpos($script, $retired) === false, "{$retired} is gone, not left dead");
		}

		assertContains('"visibility", "none"', $script, 'label layers are switched off');
		assertContains('"text-field"', $script, 'only the symbol layers that write text');
		assertContains('createElement("details")', $script, 'imported categories are native details elements');
		assertContains('hnsPoiRing', $script, 'and a clicked place is ringed');
		assertContains('.hnsPoiRing', $ctx->get('/modules/hideandseek/css/styles.css')['body'], 'with the ring styled');

		$layers = json_decode($ctx->get('/modules/hideandseek/json/mapStyle.json')['body'], true)['layers'];

		foreach (['poi', 'aerodrome_label'] as $labelled) {
			$texts = array_filter($layers, function ($layer) use ($labelled) {
				return ($layer['source-layer'] ?? '') === $labelled && isset($layer['layout']['text-field']);
			});

			assertTrue(count($texts) > 0, "{$labelled} writes text, so the blanket rule hides it");
		}

		foreach (['waterway', 'transportation'] as $mixed) {
			$types = [];

			foreach ($layers as $layer) {
				if (($layer['source-layer'] ?? '') === $mixed) {
					$types[$layer['type']] = true;
				}
			}

			assertTrue(isset($types['symbol']), "{$mixed} carries labels");
			assertTrue(count($types) > 1, "{$mixed} carries geometry too, so it must not be hidden by source layer");
		}

		$arrows = 0;

		foreach ($layers as $layer) {
			if (strpos($layer['id'], 'road_oneway') !== 0) {
				continue;
			}

			$arrows++;
			assertTrue(!isset($layer['layout']['text-field']), "{$layer['id']} draws an icon, not a label");
			assertContains('oneway', $layer['layout']['icon-image'], "{$layer['id']} draws the arrow");
		}

		assertSame(2, $arrows, 'both one-way arrow layers are there to keep');
	},

	'imported categories and unnamed places can be switched off, unnamed ones starting off' => function ($ctx) {
		$script = $ctx->get('/modules/hideandseek/js/map.js')['body'];

		assertContains('hnsApplyImportedFilter', $script, 'one filter on the imported layer does the hiding');
		assertContains('["get", "category"]', $script, 'by category');
		assertContains('["get", "named"]', $script, 'and by having a name');
		assertContains('hnsReadSetting("hnsShowUnnamed") === "1"', $script, 'unnamed places are shown only once somebody asks');
		assertContains('localStorage', $script, 'and the choices are remembered per viewer');
		assertContains('hnsAllToggleEl', $script, 'one button hides or shows every category at once');
		assertContains('ids.some(id => !hnsHiddenCategories.has(id))', $script, 'hiding all while any is shown, showing all once none is');

		$strings = json_decode((string)preg_replace('#^.*<script id="langStrings" type="application/json">(.*?)</script>.*$#s', '$1', $ctx->get('/hideandseek')['body']), true);
		assertSame('Show places without a name', $strings['poi.showUnnamed'] ?? null, 'the unnamed toggle has its label');
		assertContains('{class}', $strings['poi.showCategory'] ?? '', 'and each category toggle names its category');
	},

	'one category at a time can draw the borders of the areas nearest to each of its places' => function ($ctx) {
		$library = $ctx->get('/modules/hideandseek/vendor/d3-delaunay/d3-delaunay.min.js');
		assertSame(200, $library['status'], 'the Voronoi library is vendored and serves');
		assertContains('d3-delaunay v6.0.4 Copyright', $library['body'], 'with its copyright header intact');

		assertTrue(strpos($ctx->get('/hideandseek')['body'], 'd3-delaunay') === false, 'the page does not ship it');

		$script = $ctx->get('/modules/hideandseek/js/map.js')['body'];
		assertContains('HNS_DELAUNAY_URL', $script, 'the script injects it when needed');
		assertContains('d3.Delaunay.from(projected)', $script, 'and triangulates a stereographic projection with it');
		assertContains('hnsCircumcentre', $script, 'placing the corners at spherical circumcentres');
		assertTrue(strpos($script, '.voronoi(') === false, 'rather than using the flat Voronoi, which ignores curvature');
		assertContains('"hnsOverlayLines"', $script, 'drawn on the shared overlay line layer');
		assertContains('pois[i].name !== "" && pois[i].name === pois[j].name', $script, 'with no border between two places of the same name, unnamed ones never counting as the same');

		$strings = json_decode((string)preg_replace('#^.*<script id="langStrings" type="application/json">(.*?)</script>.*$#s', '$1', $ctx->get('/hideandseek')['body']), true);
		assertContains('{class}', $strings['poi.nearest'] ?? '', 'the toggle names its category');
	},

	'one overlay at a time: borders, or circles at the latest pin\'s distance to the nearest place' => function ($ctx) {
		$script = $ctx->get('/modules/hideandseek/js/map.js')['body'];

		assertContains('hnsSetOverlay', $script, 'both kinds go through one switch');
		assertContains('let hnsOverlay = null;', $script, 'every visit starts with no overlay');
		assertTrue(strpos($script, '"hnsOverlay", JSON') === false, 'because the choice is never stored');
		assertContains('hnsOverlay.kind === kind && hnsOverlay.category === id ? null', $script, 'pressing the active one switches it off, anything else replaces it');
		assertContains('hnsOverlayButton("nearest"', $script, 'each category has a borders button');
		assertContains('hnsOverlayButton("circles"', $script, 'and a circles button');
		assertContains('hnsMarkers[hnsMarkers.length - 1]', $script, 'the circles measure from the latest pin');
		assertContains('hnsCircleLines', $script, 'and draw only the outline of their union');
		assertContains('hnsDot(point, other) > cosRho', $script, 'a stretch of circle is cut where another circle covers it');
		assertTrue(strpos($script, 'hnsNearestCategory') === false, 'the single-kind state is gone, not left dead');

		$strings = json_decode((string)preg_replace('#^.*<script id="langStrings" type="application/json">(.*?)</script>.*$#s', '$1', $ctx->get('/hideandseek')['body']), true);
		assertContains('{class}', $strings['poi.circles'] ?? '', 'the circles button names its category');
		assertSame('{n} km', $strings['poi.kilometres'] ?? null, 'and the distance has its units');
	},

	'the stylesheet carries no line comments, which css does not have' => function ($ctx) {
		$body = $ctx->get('/modules/hideandseek/css/styles.css')['body'];

		foreach (explode("\n", $body) as $number => $line) {
			$trimmed = ltrim($line);
			assertTrue(strpos($trimmed, '//') !== 0, 'styles.css line ' . ($number + 1) . ' starts a // comment');
		}
	},

];
