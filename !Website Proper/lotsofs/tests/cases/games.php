<?php

require_once __DIR__ . '/../../app/modules/games/aoe2.php';
require_once __DIR__ . '/../../app/modules/games/sims2.php';

/// The helpers are local to this file: no other case file talks to the games module.
const GAMES_DB = 'games_test.sqlite';

/// What run.php points the Age of Empires page at, in place of the Google sheet.
const GAMES_AOE2_FIXTURE = __DIR__ . '/../fixtures/aoe2_scenarios.csv';
const GAMES_AOE2_STYLED_FIXTURE = __DIR__ . '/../fixtures/aoe2_scenarios.html';

/// Followed rather than read off one page: the landing page carries the token
/// when signed in, and redirects to the login form, which carries it, when not.
function gamesCsrf($ctx) {
	preg_match('/name="csrf_token" value="([^"]+)"/', $ctx->get('/games', true)['body'], $m);

	return $m[1] ?? '';
}

function gamesRegister($ctx, $name, $password, $inviteCode = '') {
	return $ctx->postForm('/games/register', [
		'csrf_token' => $ctx->csrfTokenFrom('/games/register'),
		'account_name' => $name,
		'password' => $password,
		'password_confirm' => $password,
		'invite_code' => $inviteCode,
	]);
}

function gamesLogOut($ctx) {
	return $ctx->postForm('/games/logout', ['csrf_token' => gamesCsrf($ctx)]);
}

/* Signs out first, rather than starting a new session: logging in while already
   signed in is a no-op redirect, and dropping the whole session would take the
   music login with it - which is the thing the separation test is watching. */
function gamesLogIn($ctx, $name, $password) {
	gamesLogOut($ctx);

	return $ctx->postForm('/games/login', [
		'csrf_token' => $ctx->csrfTokenFrom('/games/login'),
		'account_name' => $name,
		'password' => $password,
	]);
}

function gamesAccountRow($ctx, $name) {
	$stmt = $ctx->dbFor(GAMES_DB)->prepare("SELECT id, account_name, is_admin FROM account WHERE account_name = ?");
	$stmt->execute([$name]);

	return $stmt->fetch() ?: null;
}

function gamesMakeInvite($ctx) {
	$before = (int)$ctx->dbFor(GAMES_DB)->query("SELECT COALESCE(MAX(id), 0) c FROM invite")->fetch()['c'];

	$ctx->postForm('/games/accounts', [
		'csrf_token' => $ctx->csrfTokenFrom('/games/accounts'),
		'action' => 'invite',
	]);

	$stmt = $ctx->dbFor(GAMES_DB)->prepare("SELECT code FROM invite WHERE id > ? ORDER BY id DESC LIMIT 1");
	$stmt->execute([$before]);
	$row = $stmt->fetch();

	return $row ? $row['code'] : '';
}

function gamesNav($body) {
	$start = strpos($body, '<nav');

	return substr($body, $start, strpos($body, '</nav>') - $start);
}

return [

	'signed out, only the way in is open' => function ($ctx) {
		$ctx->newSession();

		foreach (['/games', '/games/accounts', '/games/sims2'] as $path) {
			$response = $ctx->get($path);
			assertSame(302, $response['status'], "signed out, {$path} redirects");
			assertContains('/games/login', $response['location'], 'to the login page');
		}

		foreach (['/games/login', '/games/register'] as $path) {
			assertSame(200, $ctx->get($path)['status'], "GET {$path}");
		}

		assertContains('Games', $ctx->get('/games/login')['body'], 'the login page names the module');
	},

	'it has a database of its own, with no other module in it' => function ($ctx) {
		$ctx->get('/games/login');

		$db = $ctx->dbFor(GAMES_DB);
		$tables = array_column($db->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(), 'name');

		foreach (['account', 'invite', 'login_attempt', 'schema_migrations'] as $table) {
			assertTrue(in_array($table, $tables, true), "the schema has a {$table} table");
		}

		assertTrue(!in_array('song', $tables, true), 'and nothing of the music module');
		assertTrue(!in_array('game_map', $tables, true), 'or of hide and seek');

		/// The site rule: no table refuses or cascades a delete.
		foreach ($db->query("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND sql IS NOT NULL")->fetchAll() as $row) {
			assertTrue(stripos($row['sql'], 'REFERENCES') === false, "{$row['name']} declares no foreign key");
		}
	},

	'the first account needs no invite and is made an admin' => function ($ctx) {
		$ctx->newSession();

		assertSame(0, (int)$ctx->dbFor(GAMES_DB)->query("SELECT COUNT(*) c FROM account")->fetch()['c'], 'no accounts yet');
		assertContains('no invite code', $ctx->get('/games/register')['body'], 'and the form says so');

		$response = gamesRegister($ctx, 'games_first', 'test password');
		assertSame(302, $response['status'], 'registering lands somewhere');

		$account = gamesAccountRow($ctx, 'games_first');
		assertTrue($account !== null, 'the account exists');
		assertSame(1, (int)$account['is_admin'], 'and is an admin, since somebody has to be able to invite');
	},

	'a second account needs an invite, and each code is good once' => function ($ctx) {
		gamesLogIn($ctx, 'games_first', 'test password');

		$code = gamesMakeInvite($ctx);
		assertTrue($code !== '', 'an admin can make an invite');

		$ctx->newSession();
		assertContains('formError', gamesRegister($ctx, 'games_uninvited', 'test password')['body'], 'registering without a code is refused');
		assertTrue(gamesAccountRow($ctx, 'games_uninvited') === null, 'and creates nothing');

		$ctx->newSession();
		assertSame(302, gamesRegister($ctx, 'games_second', 'test password', $code)['status'], 'the code lets one person in');
		assertSame(0, (int)gamesAccountRow($ctx, 'games_second')['is_admin'], 'who is not an admin');

		$ctx->newSession();
		gamesRegister($ctx, 'games_third', 'test password', $code);
		assertTrue(gamesAccountRow($ctx, 'games_third') === null, 'the same code does not let a second one in');
	},

	'logging in and out works, and the accounts page is for admins' => function ($ctx) {
		$ctx->newSession();
		assertContains('formError', gamesLogIn($ctx, 'games_first', 'wrong password')['body'], 'a bad password is refused');

		assertSame(302, gamesLogIn($ctx, 'games_first', 'test password')['status'], 'the right one is accepted');
		$body = $ctx->get('/games')['body'];
		assertContains('Signed in as games_first', $body, 'and the page knows who is there');
		assertContains('/games/accounts', gamesNav($body), 'an admin has the accounts page in the bar');
		assertSame(200, $ctx->get('/games/accounts')['status'], 'and reaches it');

		gamesLogIn($ctx, 'games_second', 'test password');
		assertTrue(strpos(gamesNav($ctx->get('/games')['body']), '/games/accounts') === false, 'a plain account has no such button');
		$response = $ctx->get('/games/accounts');
		assertSame(302, $response['status'], 'and is turned away from the page');
		assertSame('/games', $response['location'], 'back to the landing page');

		$response = gamesLogOut($ctx);
		assertContains('/games/login', $response['location'], 'logging out lands on the login page');
		assertSame(302, $ctx->get('/games')['status'], 'and the landing page is locked again');
	},

	'an admin can promote and demote, but never themselves' => function ($ctx) {
		gamesLogIn($ctx, 'games_first', 'test password');

		$second = gamesAccountRow($ctx, 'games_second');
		$first = gamesAccountRow($ctx, 'games_first');

		$promote = function ($accountId, $action) use ($ctx) {
			$ctx->postForm('/games/accounts', [
				'csrf_token' => $ctx->csrfTokenFrom('/games/accounts'),
				'account_id' => $accountId,
				'action' => $action,
			]);
		};

		$promote($second['id'], 'promote');
		assertSame(1, (int)gamesAccountRow($ctx, 'games_second')['is_admin'], 'a plain account can be made an admin');

		$promote($second['id'], 'demote');
		assertSame(0, (int)gamesAccountRow($ctx, 'games_second')['is_admin'], 'and demoted again');

		/// Otherwise the last admin could lock everybody out of the accounts page.
		$promote($first['id'], 'demote');
		assertSame(1, (int)gamesAccountRow($ctx, 'games_first')['is_admin'], 'but an admin cannot demote themselves');
	},

	'an unrecognised action on the accounts page changes nothing' => function ($ctx) {
		gamesLogIn($ctx, 'games_first', 'test password');

		$second = gamesAccountRow($ctx, 'games_second');
		$before = (int)$ctx->dbFor(GAMES_DB)->query("SELECT COUNT(*) c FROM invite")->fetch()['c'];

		$ctx->postForm('/games/accounts', [
			'csrf_token' => $ctx->csrfTokenFrom('/games/accounts'),
			'account_id' => $second['id'],
			'invite_id' => '1',
			'action' => 'something-else',
		]);

		assertSame((int)$second['is_admin'], (int)gamesAccountRow($ctx, 'games_second')['is_admin'], 'nobody was promoted or demoted');
		assertSame($before, (int)$ctx->dbFor(GAMES_DB)->query("SELECT COUNT(*) c FROM invite")->fetch()['c'], 'and no invite was made');
	},

	/* Two modules, two sets of accounts, one browser. Being signed in to one
	   says nothing about the other. */
	'its accounts are separate from the music module\'s' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->get('/games');
		assertSame(302, $response['status'], 'a music session is signed out here');
		assertContains('/games/login', $response['location'], 'and sent to log in');

		gamesLogIn($ctx, 'games_first', 'test password');

		assertContains('games_first', $ctx->get('/games')['body'], 'signed in here');
		assertContains('test_runner', $ctx->get('/music')['body'], 'and still signed in there, in the same browser');
	},

	'log in and register live under one account button' => function ($ctx) {
		$ctx->newSession();
		$nav = gamesNav($ctx->get('/games/login')['body']);

		assertContains('>Account<', $nav, 'signed out, the button is labelled as the menu rather than as either half');
		assertContains('/games/login', $nav, 'holding the way in');
		assertContains('/games/register', $nav, 'and the way to make one');
		assertSame(1, substr_count($nav, 'gamesNavMenuButton'), 'one top level button, not two');

		gamesLogIn($ctx, 'games_first', 'test password');
		$nav = gamesNav($ctx->get('/games')['body']);

		assertContains('>games_first<', $nav, 'signed in, the same button is the account name');
		assertContains('/games/logout', $nav, 'and holds the way out');
		assertTrue(strpos($nav, '/games/register') === false, 'with no way to register while signed in');
	},

	/* The nav is part of the shell rather than something each view remembers to
	   include, so a new view cannot ship without it. */
	'the shell wraps every page in the layout and the nav' => function ($ctx) {
		$check = function ($path) use ($ctx) {
			$body = $ctx->get($path)['body'];

			assertContains('<div class="gamesLayout">', $body, "{$path} opens the layout");
			assertSame(1, substr_count($body, '<nav class="gamesNav">'), "{$path} carries exactly one nav");
			assertContains('<main class="gamesMain">', $body, "{$path} puts its content in main");
			assertContains('</main>', $body, "{$path} closes it");
		};

		gamesLogIn($ctx, 'games_first', 'test password');
		$check('/games');
		$check('/games/accounts');

		gamesLogOut($ctx);
		$check('/games/login');
		$check('/games/register');
	},

	'by default AoE II up to The Last Khans, and whatever holds a ticked scenario' => function ($ctx) {
		$scenario = fn($game, $pack, $name) => ['game' => $game, 'packKey' => "$game|$pack", 'key' => "$game|C|$name"];
		$scenarios = [$scenario('II', '-', 'a'), $scenario('II', 'TLK', 'b'), $scenario('II', 'LotW', 'c'), $scenario('II:R', 'ReoR', 'd'), $scenario('III', '-', 'e'), $scenario('III', 'TWC', 'f')];
		$legend = ['II|-' => 'Base game', 'II|TLK' => 'The Last Khans', 'II|LotW' => 'Lords of the West', 'II:R|ReoR' => 'Return of Rome', 'III|-' => 'Base game', 'III|TWC' => 'The WarChiefs'];

		$filters = gamesAoe2Filters($scenarios, $legend);
		assertSame(['II'], $filters['game']['default'], 'AoE II alone');
		assertSame(['II|-', 'II|TLK', 'II:R|ReoR', 'III|-', 'III|TWC'], $filters['pack']['default'], 'with its DLC up to The Last Khans, and every DLC of a game off by default, so naming that game shows all of it');

		$filters = gamesAoe2Filters($scenarios, $legend, ['II|C|c' => true, 'III|C|f' => true]);
		assertSame(['II', 'III'], $filters['game']['default'], 'a ticked scenario brings its game');
		assertSame(['II|-', 'II|TLK', 'II|LotW', 'II:R|ReoR', 'III|TWC'], $filters['pack']['default'], 'and its DLC, and only that one of its game');

		$filters = gamesAoe2Filters([$scenario('II', '-', 'a'), $scenario('II', 'LotW', 'c')], ['II|-' => 'Base game', 'II|LotW' => 'Lords of the West']);
		assertSame(['II|-', 'II|LotW'], $filters['pack']['default'], 'with no Last Khans in the sheet, all of AoE II');
	},

	'the scenario sheet parses by its headers, wherever they are' => function ($ctx) {
		$csv = file_get_contents(GAMES_AOE2_FIXTURE);
		$scenarios = gamesAoe2Parse($csv);

		assertSame(40, count($scenarios), 'every scenario in the fixture, and none of its blank or note rows');
		assertSame([
			'number' => 1,
			'name' => 'Hunting',
			'year' => 'c.8000 BCE',
			'civ' => 'Egyptians',
			'campaign' => 'Ascent of Egypt',
			'game' => 'I',
			'pack' => '-',
			'end' => false,
			'standalone' => false,
			'campaignKey' => 'I|Ascent of Egypt',
			'lineKey' => 'I|Ascent of Egypt',
			'key' => 'I|Ascent of Egypt|Hunting',
			'packKey' => 'I|-',
		], $scenarios[0], 'a campaign\'s first scenario, its name taken off the number');

		$byName = array_column($scenarios, null, 'name');
		assertSame(5, $byName['Subartu']['number'], '<End> is not part of the number');
		assertTrue($byName['Subartu']['end'], 'but marks the campaign\'s last scenario');
		assertTrue(isset($byName['The Battle of Agrigentum']), 'trailing spaces go');
		assertSame('Yamato, Empire of the Rising Sun', $byName['The Assassins']['campaign'], 'a quoted comma stays in its cell');
		assertSame('"Normans" (Sicilians)', $byName['Guiscard Arrives']['civ'], 'and so do doubled quotes');
		assertSame(null, $byName['Xie An']['number'], 'a standalone scenario has no number');
		assertTrue($byName['Xie An']['standalone'] && $byName['Agincourt']['standalone'], 'which marks Victors and Vanquished and the Historical Battles as such');
		assertTrue(!$byName['Hunting']['standalone'], 'and nothing in a campaign');
		assertSame('IV|The Normans', $byName['The Battle of Hastings']['lineKey'], 'a chapter\'s line follows its campaign, the name up to its colon');
		assertSame('IV|The Normans: Conquest|The Battle of Hastings', $byName['The Battle of Hastings']['key'], 'while its tick keeps the whole name');

		$keys = array_column($scenarios, 'key');
		assertSame(count($keys), count(array_unique($keys)), 'every key is unique');
		assertTrue(
			in_array('II|Historical Battles|Agincourt', $keys, true) && in_array('IV|Historical Battles|Agincourt', $keys, true),
			'the game is in the key, which tells the two Agincourts apart'
		);

		$shifted = implode("\n", array_map(fn($line) => 'x,' . $line, explode("\n", $csv)));
		assertSame($scenarios, gamesAoe2Parse($shifted), 'a column the author adds in front changes nothing');

		assertSame(null, gamesAoe2Parse("not,a,sheet\n1,2,3"), 'text without the headers is no sheet');
		assertSame(null, gamesAoe2Parse(''), 'and nor is nothing');
	},

	'each scenario takes its row colour from the sheet\'s html view' => function ($ctx) {
		$colours = gamesAoe2Colours(file_get_contents(GAMES_AOE2_STYLED_FIXTURE));

		assertSame('#fff2cc', $colours['I|Ascent of Egypt|Hunting'] ?? null, 'a campaign\'s first scenario, whose number cell is merged sideways');
		assertSame('#fff2cc', $colours['I|Ascent of Egypt|Foraging'] ?? null, 'and a plain one, the same');
		assertSame('#c5976e', $colours['II:R|Sargon of Akkad|The Chosen One'] ?? null, 'another campaign its own');

		$keys = array_column(gamesAoe2Parse(file_get_contents(GAMES_AOE2_FIXTURE)), 'key');
		assertSame([], array_values(array_diff($keys, array_keys($colours))), 'every scenario the csv has, the html colours, by the same key');

		assertSame([], gamesAoe2Colours('<table><tr><td class="s1">x</td></tr></table>'), 'a page without the headers colours nothing');
	},

	'the dlc names come from the sheet\'s legend' => function ($ctx) {
		$csv = implode("\n", [
			',,The Legend of El Dorado',
			',,,,Game / Expansion Pack Legend,,',
			',,,,Age of Empires II,,',
			',,,,-,,Base game (1999)',
			',,,,TC,,The Conquerors (2000)',
			',,,,Age of Mythology,,',
			',,,,TT,,The Titans (2003)',
			',,,,Age of Empires III,,',
			',,,,DE,,Definitive Edition (2020)',
		]);

		assertSame([
			'II|-' => 'Base game (1999)',
			'II|TC' => 'The Conquerors (2000)',
			'III|DE' => 'Definitive Edition (2020)',
		], gamesAoe2PackNames($csv), 'each pack under its game, a game the page does not know skipped, and a campaign with "legend" in its name not taken for it');

		assertSame([], gamesAoe2PackNames("a,b\n1,2"), 'without the legend there are no names');
		assertSame(['II:R|ReoR' => 'Return of Rome (2023)'], gamesAoe2PackNames(file_get_contents(GAMES_AOE2_FIXTURE)), 'the fixture holds only that much of it');
	},

	'the dlc filter lists each game\'s packs, and a filter not in the address keeps its defaults' => function ($ctx) {
		$scenarios = gamesAoe2Parse(file_get_contents(GAMES_AOE2_FIXTURE));
		$filters = gamesAoe2Filters($scenarios, ['I|RoR' => 'Rise of Rome (1998)', 'I|-' => 'Base game (1997)', 'I|DE' => 'Definitive Edition (2018)']);
		$pack = $filters['pack'];

		assertSame(['I', 'II', 'II:R', 'II:C', 'III', 'IV'], array_keys($pack['groups']), 'grouped by game, in the game filter\'s order');
		assertSame(['I|RoR', 'I|-', 'I|DE'], $pack['groups']['I'], 'a game\'s packs in the legend\'s order, not the order they turn up in');
		assertSame('Rise of Rome', $pack['options']['I|RoR'], 'labelled by the legend, without its year');
		assertSame('Rise of Rome (1998)', $pack['titles']['I|RoR'], 'which the title keeps');
		assertSame('TC', $pack['options']['II|TC'], 'and by its code where the legend has no name');
		assertSame(array_keys($pack['options']), $pack['default'], 'with no Last Khans in this legend, every pack is ticked to begin with');

		assertSame($pack['default'], gamesAoe2Selection($filters, ['game' => ['I']])['pack'], 'a link naming only the games keeps every pack');
		assertSame([], gamesAoe2Selection($filters, ['game' => ['']])['game'], 'the empty value the form sends alone means none');
		assertSame(['II|TC'], gamesAoe2Selection($filters, ['pack' => ['', 'II|TC', 'nonsense', ['II|VaV']]])['pack'], 'and only real packs count');

		$downloadable = gamesAoe2Parse(implode("\n", [
			',,Scenario Number,Scenario,Year,,Civilization,,Campaign,Game,Pack',
			',,Downloadable Scenario (AoE II),Ayn Jalut,1260,,Saracens,-,Downloadable Scenario (AoE II),II,DE',
			',,Historical Battle (AoE II),Bukhara,557,,Persians,,Historical Battles,II,DE',
		]));
		assertSame('II|Downloadable Scenarios', $downloadable[0]['packKey'], 'a downloadable scenario is its own dlc, whatever pack the sheet gives it');
		assertSame('DE', $downloadable[0]['pack'], 'though it still shows that pack');
		assertSame('II|DE', $downloadable[1]['packKey'], 'and the pack keeps everything else');
		assertSame('Downloadable Scenarios', gamesAoe2Filters($downloadable)['pack']['options']['II|Downloadable Scenarios'], 'under that name');
	},

	'the sheet is kept as a copy, served stale while the source cannot be read' => function ($ctx) {
		$dir = sys_get_temp_dir() . '/lotsofs_aoe2_' . getmypid();
		@mkdir($dir);
		$cache = $dir . '/cache.csv';
		$missing = $dir . '/missing.csv';
		$garbage = $dir . '/garbage.csv';
		file_put_contents($garbage, "nothing,like,the,sheet\n");

		$good = ['csv' => GAMES_AOE2_FIXTURE, 'styled' => GAMES_AOE2_STYLED_FIXTURE];
		$none = ['csv' => $missing, 'styled' => $missing];

		try {
			$loaded = gamesAoe2Load($good, $cache, 600, 60);
			assertSame(40, count($loaded['scenarios']), 'the first load reads the source');
			assertTrue(!$loaded['stale'], 'and is fresh');
			assertSame(file_get_contents(GAMES_AOE2_FIXTURE), file_get_contents($cache), 'and keeps a copy');
			assertSame(40, count($loaded['colours']), 'the colours come with it');
			assertTrue(is_file($cache . '.colours.json'), 'and are kept too');

			$loaded = gamesAoe2Load($none, $cache, 600, 60);
			assertTrue(!$loaded['stale'] && count($loaded['scenarios']) === 40, 'a young copy is served without asking the source');
			assertSame(40, count($loaded['colours']), 'with its colours');

			touch($cache, time() - 3600);
			$loaded = gamesAoe2Load($none, $cache, 600, 60);
			assertTrue($loaded['stale'], 'an old copy whose source cannot be read is served, marked stale');
			assertSame(40, count($loaded['scenarios']), 'and whole');

			$loaded = gamesAoe2Load($good, $cache, 600, 60);
			assertTrue($loaded['stale'], 'after a failure the source is left alone for a while, even once it is back');

			unlink($cache . '.failed');
			$loaded = gamesAoe2Load(['csv' => $garbage] + $good, $cache, 600, 60);
			assertTrue($loaded['stale'], 'a source that is not the sheet counts as a failure');
			assertSame(file_get_contents(GAMES_AOE2_FIXTURE), file_get_contents($cache), 'and never replaces the copy');

			unlink($cache . '.failed');
			foreach ([$missing, $garbage] as $styled) {
				touch($cache, time() - 3600);
				$loaded = gamesAoe2Load(['styled' => $styled] + $good, $cache, 600, 60);
				assertTrue(!$loaded['stale'], 'the csv alone makes a fresh copy');
				assertSame(40, count($loaded['colours']), 'and colours that cannot be read leave the last ones in place');
			}

			unlink($cache . '.colours.json');
			assertSame([], gamesAoe2Load($good, $cache, 600, 60)['colours'], 'with none kept and a young copy, there are none');

			assertSame(null, gamesAoe2Load($none, $dir . '/none.csv', 600, 60), 'with no copy and no source there is nothing to show');
		}
		finally {
			array_map('unlink', glob($dir . '/*'));
			@rmdir($dir);
		}
	},

	'each campaign gets a lane, free again one row after it ends' => function ($ctx) {
		$rows = array_map(fn($campaign) => ['campaignKey' => $campaign], ['a', 'b', 'a', 'c', 'b', 'd', 'e']);
		$lanes = gamesAoe2Lanes($rows);

		assertSame(3, $lanes['width'], 'three campaigns run at once at most');
		assertSame([0, 1, 0, 2, 1, 0, 1], array_column($lanes['rows'], 'dot'), 'each takes the lowest lane that has been free for a row');
		assertSame([['lane' => 0, 'campaignKey' => 'a', 'colour' => null, 'start' => true, 'end' => false]], $lanes['rows'][0]['lines'], 'a line starts at its first scenario');
		assertSame([
			['lane' => 0, 'campaignKey' => 'a', 'colour' => null, 'start' => false, 'end' => true],
			['lane' => 1, 'campaignKey' => 'b', 'colour' => null, 'start' => false, 'end' => false],
		], $lanes['rows'][2]['lines'], 'ends at its last, and runs through the rows between');
		assertSame(['b'], array_column($lanes['rows'][3]['lines'], 'campaignKey'), 'a campaign shown on one row is a dot with no line');

		$chapters = [
			['campaignKey' => 'IV|N: A', 'lineKey' => 'IV|N', 'key' => 'a'],
			['campaignKey' => 'IV|X', 'lineKey' => 'IV|X', 'key' => 'x'],
			['campaignKey' => 'IV|N: B', 'lineKey' => 'IV|N', 'key' => 'b'],
		];
		$joined = gamesAoe2Lanes($chapters);
		assertSame([0, 1, 0], array_column($joined['rows'], 'dot'), 'two chapters of one campaign share its lane');
		assertSame(['IV|N'], array_column($joined['rows'][1]['lines'], 'campaignKey'), 'and one line runs on between them, past the scenario between');

		$battles = array_map(fn($name) => ['campaignKey' => 'battles', 'key' => $name, 'standalone' => true], ['x', 'y', 'z']);
		$lanes = gamesAoe2Lanes($battles);
		assertSame([[], [], []], array_column($lanes['rows'], 'lines'), 'standalone scenarios sharing a campaign cell are dots, never joined');
		assertSame([0, 0, 0], array_column($lanes['rows'], 'dot'), 'a run of one category stacks in one lane');

		$mixed = [
			['campaignKey' => 'battles', 'key' => 'a', 'standalone' => true],
			['campaignKey' => 'others', 'key' => 'b', 'standalone' => true],
			['campaignKey' => 'others', 'key' => 'c', 'standalone' => true],
			['campaignKey' => 'battles', 'key' => 'd', 'standalone' => true],
			['campaignKey' => 'a campaign', 'key' => 'e'],
		];
		assertSame([0, 1, 1, 0, 1], array_column(gamesAoe2Lanes($mixed)['rows'], 'dot'), 'but never a dot straight under one of another category or campaign');
	},

	'to scale, the undated scenarios come last, after the stretch to the present' => function ($ctx) {
		$rows = array_map(fn($year, $name) => ['year' => $year, 'name' => $name], ['-', '1200', '-', '1100', '1150'], ['a', 'b', 'c', 'd', 'e']);
		assertSame(['b', 'd', 'e', 'a', 'c'], array_column(gamesAoe2UndatedLast($rows), 'name'), 'each part in the sheet\'s order');

		$sorted = gamesAoe2UndatedLast($rows);
		assertSame(3, gamesAoe2PresentStep($sorted, [[0], [1], [2], [3], [4]]), 'the present comes before the first of the undated ones');
		assertSame(null, gamesAoe2PresentStep(array_slice($sorted, 0, 3), [[0], [1], [2]]), 'and after the last step when it is dated');
		assertSame(null, gamesAoe2PresentStep([['year' => '-']], [[0]]), 'and nowhere with nothing dated');
	},

	'to scale, consecutive rows starting in the same year are one step' => function ($ctx) {
		$rows = array_map(fn($year) => ['year' => $year], ['c.2334 BCE', 'c.2334 BCE', '1426', 'c.1426', '1427', '1426', '406 to 455', '406', '-', '-', '460']);
		assertSame(
			[[0, 1], [2, 3], [4], [5], [6, 7], [8], [9], [10]],
			gamesAoe2YearSteps($rows),
			'however the year is written, but never across a row in between, nor for rows with no year'
		);
	},

	'to scale, a year holds each campaign in a lane of its own, and a campaign\'s scenarios in it the lanes to its right' => function ($ctx) {
		$row = fn($campaign, $key, $standalone = false) => ['campaignKey' => $campaign, 'key' => $key, 'campaign' => strtoupper($campaign), 'marker' => $key, 'standalone' => $standalone];
		$rows = [$row('a', 'a1'), $row('a', 'a2'), $row('b', 'b1'), $row('a', 'a3'), $row('b', 'b2'), $row('c', 'c1'), $row('d', 'd1')];
		$lanes = gamesAoe2Lanes($rows, [[0], [1, 2, 3], [4, 5], [6]]);

		assertSame([0, 0, 2, 0, 2, 1, 0], array_column($lanes['rows'], 'dot'), 'a campaign\'s second scenario of a year takes the lane to its right, which a campaign in that year goes past, and a lane is free again one year after its campaign ends');
		assertSame([1, 2], array_keys($lanes['steps']), 'only a year of several rows folds');
		assertSame([
			['lane' => 0, 'colour' => 'a2', 'campaign' => 'A', 'key' => 'a2'],
			['lane' => 2, 'colour' => 'b1', 'campaign' => 'B', 'key' => 'b1'],
			['lane' => 1, 'colour' => 'a3', 'campaign' => 'A', 'key' => 'a3'],
		], $lanes['steps'][1]['dots'], 'a dot per scenario, a campaign\'s first of the year in its lane and the rest beside it');
		assertSame(3, $lanes['width'], 'the lanes are as wide as those places need');
		assertSame([
			['lane' => 0, 'campaignKey' => 'a', 'colour' => 'a3', 'start' => false, 'end' => true],
			['lane' => 2, 'campaignKey' => 'b', 'colour' => 'b1', 'start' => true, 'end' => false],
		], $lanes['steps'][1]['lines'], 'folded, a line starts and ends at the year it starts and ends in');
		assertSame([['lane' => 0, 'campaignKey' => 'a', 'colour' => 'a1']], $lanes['steps'][1]['through'], 'unfolded, the header carries the lines arriving from above, in the colour they arrive in');
		assertSame(['b'], array_column($lanes['steps'][2]['lines'], 'campaignKey'), 'a campaign shown in one year only is a dot with no line');
		assertSame([0, 0, 1, 0, 1, 0, 1], array_column(gamesAoe2Lanes($rows)['rows'], 'dot'), 'one row per step lays them out row by row, as the list does');

		$three = gamesAoe2Lanes([$row('a', 'a1'), $row('a', 'a2'), $row('a', 'a3'), $row('a', 'a4'), $row('c', 'c1')], [[0], [1, 2, 3, 4]]);
		assertSame([0, 0, 0, 0, 3], array_column($three['rows'], 'dot'), 'three scenarios of one campaign in a year take three lanes, and the next campaign goes past them');
		assertSame([0, 1, 2, 3], array_column($three['steps'][1]['dots'], 'lane'), 'so the year\'s dots run on across the lanes');
		$after = gamesAoe2Lanes([$row('b', 'b1'), $row('b', 'b2'), $row('a', 'a1'), $row('a', 'a2'), $row('a', 'a3')], [[0], [1, 2, 3, 4]]);
		assertSame([0, 1, 2, 3], array_column($after['steps'][1]['dots'], 'lane'), 'a campaign arriving in a year beside a dot already there lines up after it');
		assertSame([0, 0, 1], array_column(gamesAoe2Lanes([$row('a', 'a1'), $row('a', 'a2'), $row('c', 'c1')], [[0, 1], [2]])['rows'], 'dot'), 'a lump reads as one, so another campaign may go straight under one of its dots the year after');
		assertSame([0, 1], array_column(gamesAoe2Lanes([$row('a', 'a1'), $row('c', 'c1')], [[0], [1]])['rows'], 'dot'), 'which a dot on its own still forbids, its lane sitting out the year after its campaign ends');
		$left = gamesAoe2Lanes([$row('p', 'p1'), $row('q', 'q1'), $row('r', 'r1'), $row('r', 'r2'), $row('r', 'r3'), $row('r', 'r4'), $row('s', 's1'), $row('p', 'p2'), $row('q', 'q2')], [[0, 1], [2], [3, 4, 5], [6], [7, 8]]);
		assertSame([0, 1, 2], array_column($left['steps'][2]['dots'], 'lane'), 'a year\'s dots run left over lanes whose lines pass by with no dot there, to keep the lanes narrow');

		$battles = array_map(fn($key) => $row('battles', $key, true), ['x', 'y', 'z', 'w']);
		assertSame([0, 0, 1, 1], array_column(gamesAoe2Lanes($battles, [[0], [1, 2], [3]])['rows'], 'dot'), 'two standalone scenarios of a category in one year sit side by side, and the next stacks under the last');
	},

	'a year reads as the first year it names, before the common era negative' => function ($ctx) {
		assertSame(-8000, gamesAoe2YearNumber('c.8000 BCE'), 'about, and before the common era');
		assertSame(-1878, gamesAoe2YearNumber('1878 BCE'), 'a plain year before');
		assertSame(830, gamesAoe2YearNumber('c. 830'), 'about, with a space');
		assertSame(406, gamesAoe2YearNumber('406 to 455'), 'a range by its start');
		assertSame(850, gamesAoe2YearNumber('850 to c.900'), 'whatever its end looks like');
		assertSame(-500, gamesAoe2YearNumber('500 to 400 BCE'), 'and before the common era when only its end says so');
		assertSame(null, gamesAoe2YearNumber('-'), 'no year is no number');

		assertSame([406, 455], gamesAoe2YearSpan('406 to 455'), 'a range has both ends');
		assertSame([-500, -400], gamesAoe2YearSpan('500 to 400 BCE'), 'before the common era at both');
		assertSame([1426, 1426], gamesAoe2YearSpan('1426'), 'and a single year is both');
	},

	'a year later than one below it is out of place, and a range counts by its start' => function ($ctx) {
		assertSame(
			[false, false, true, false, false, false],
			gamesAoe2OutOfPlace(['1425', '1426', '1427', '1426', '1426', '1429']),
			'Le Loi 5 (1427) before Sforza 1 and Le Loi 6 (1426), as the sheet has them, and only it'
		);
		assertSame(
			[false, false, false],
			gamesAoe2OutOfPlace(['453', '406 to 455', '460']),
			'The Fall of Rome (453) before Gaiseric (406 to 455) is not: the range reaches past it'
		);
		assertSame(
			[false, false, true, false],
			gamesAoe2OutOfPlace(['c.8000 BCE', '-', '1878 BCE', '2000 BCE']),
			'a row with no year takes no part, and before the common era counts backwards'
		);
	},

	'markers are the row colour at full saturation and value, or black when it is nearly grey' => function ($ctx) {
		assertSame('#ffbe00', gamesAoe2MarkerColour('#fff2cc'), 'a pale yellow at 20% saturation is a colour');
		assertSame('#ff7800', gamesAoe2MarkerColour('#c5976e'), 'a brown becomes its orange');
		assertSame('#00d2ff', gamesAoe2MarkerColour('#76a5af'), 'a grey teal its cyan');
		assertSame('#ff0000', gamesAoe2MarkerColour('#f4cccc'), 'and so is a pink at 16%');
		assertSame('#43ff00', gamesAoe2MarkerColour('#d9ead3'), 'and a green at 9.8%');
		assertSame('#ff0000', gamesAoe2MarkerColour('#f0e4e4'), 'and one at exactly 5%');
		assertSame('#ffffff', gamesAoe2MarkerColour('#f5f2f2'), 'under 5% saturation is white');
		assertSame('#ffffff', gamesAoe2MarkerColour('#d9d9d9'), 'and so is a grey');
		assertSame('#00ff00', gamesAoe2MarkerColour(gamesAoe2FallbackColour(120)), 'a colour made up for a campaign the sheet does not colour follows the same rule');

		assertSame('#000000', gamesAoe2EdgeColour('#ffbe00'), 'an amber line is edged in black');
		assertSame('#000000', gamesAoe2EdgeColour('#ffffff'), 'and so is a white one');
		assertSame('#ffffff', gamesAoe2EdgeColour('#0022ff'), 'but a deep blue in white, which stands out more against it');
	},

	'a row\'s text is dark or light, whichever reads better on its colour over the page' => function ($ctx) {
		assertSame('light', gamesAoe2RowInk('#fff2cc'), 'at the page\'s strength even the palest yellow takes light text');
		assertSame('dark', gamesAoe2RowInk('#fff2cc', 0.46), 'which it would not one percent stronger');
		assertSame('light', gamesAoe2RowInk('#c5976e', 0.5), 'a brown takes light text at half strength');
		assertSame('dark', gamesAoe2RowInk('#c5976e', 1), 'and dark text at full strength');
	},

	'a lane takes the colour of its campaign\'s latest scenario' => function ($ctx) {
		$rows = [
			['campaignKey' => 'a', 'marker' => '#111111'],
			['campaignKey' => 'b', 'marker' => '#999999'],
			['campaignKey' => 'a', 'marker' => '#222222'],
			['campaignKey' => 'b', 'marker' => '#999999'],
			['campaignKey' => 'a', 'marker' => '#222222'],
		];
		$lanes = gamesAoe2Lanes($rows);
		$aLine = fn($i) => array_column($lanes['rows'][$i]['lines'], 'colour', 'campaignKey')['a'];

		assertSame(['#111111', '#999999', '#222222', '#999999', '#222222'], array_column($lanes['rows'], 'colour'), 'each dot is its own row\'s colour');
		assertSame('#111111', $aLine(1), 'a line keeps its colour through other campaigns\' rows');
		assertSame('#222222', $aLine(2), 'and changes where its campaign\'s does');
		assertSame('#222222', $aLine(3), 'carrying the new one on');
	},

	'the age of empires page shows aoe ii by default, and the filter changes that' => function ($ctx) {
		$ctx->newSession();
		$response = $ctx->get('/games/aoe2');
		assertSame(302, $response['status'], 'signed out, it is locked');
		assertContains('/games/login', $response['location'], 'like the rest of the module');

		gamesLogIn($ctx, 'games_first', 'test password');
		$body = $ctx->get('/games/aoe2')['body'];

		assertContains('>Guiscard Arrives<', $body, 'AoE II is shown');
		assertTrue(strpos($body, '>The Chosen One<') === false, 'Return of Rome only once something in it is ticked');
		assertTrue(strpos($body, '>The Ionian Revolt<') === false, 'and so the Chronicles');
		assertTrue(strpos($body, '>Hunting<') === false, 'AoE I is left out by default');
		assertTrue(strpos($body, 'data-game="IV"') === false, 'and so is AoE IV');
		assertContains('value="II" checked', $body, 'the filter shows what is ticked');
		assertContains('value="II:R">', $body, 'and what is not');
		assertContains('0 of 11 done', $body, 'and the count is over what is shown');
		assertContains('href="' . htmlspecialchars(GAMES_AOE2_SHEET) . '"', $body, 'the sheet is credited and linked');
		assertContains('aoeLineStart', $body, 'campaigns are drawn as lanes');
		assertContains('--colour: #43ff00" title="Attila the Hun"', $body, 'a campaign whose rows are only faintly coloured still gets their colour');
		assertContains('--colour: #ff0000" title="Victors and Vanquished"', $body, 'a standalone scenario takes its dot from its colour like any other: red for Victors and Vanquished');
		assertContains('--colour: #ffffff" title="Historical Battles"', $body, 'and white for the grey Historical Battles');
		assertContains('--rowAlpha: 45%', $body, 'rows show their colour at 45% over the page');
		preg_match_all('/<li class="([^"]*)" data-key=/', $body, $rowClasses);
		assertSame([], array_values(array_filter($rowClasses[1], fn($classes) => !in_array('aoeRowLight', preg_split('/\s+/', $classes), true))), 'and every one takes light text');
		assertSame(1, substr_count($ctx->get('/games/aoe2?game[]=II:R')['body'], '>c.2334 BCE<'), 'a year shows once however many scenarios in a row share it');

		$body = $ctx->get('/games/aoe2?game[]=I')['body'];
		assertContains('>Hunting<', $body, 'ticking AoE I alone shows it');
		assertTrue(strpos($body, '>The Chosen One<') === false, 'and nothing else');
		assertContains('data-key="I|Ascent of Egypt|Hunting" data-game="I" data-year="-8000" style="--rowColour: #fff2cc"', $body, 'a row is the colour the sheet gives it, and carries its year as a number');
		assertTrue(
			(bool)preg_match('#data-key="I\|Ascent of Egypt\|Hunting".*?</li>\s*<li class="aoeGap" role="none" data-next="I\|Ascent of Egypt\|Foraging">\s*<span class="aoeLanes" aria-hidden="true">\s*<i class="aoeLine" style="--lane: \d+; --colour: \#ffbe00;#s', $body),
			'and is followed by the room the to-scale layout opens, named by the row after it and carrying its campaign\'s line on'
		);
		assertTrue(
			(bool)preg_match('#data-next="I\|Ascent of Egypt\|Foraging">.*?<button type="button" class="aoeAxis" disabled aria-hidden="true"><svg class="aoeKink"[^>]*><polyline[^>]*/></svg></button>\s*</li>#s', $body),
			'with the year axis over it, a button the script enables, carrying the kink a fold shows'
		);
		assertTrue(strpos($body, 'data-collapsed') === false, 'nothing is folded to begin with');
		assertClasses(['aoeRow', 'aoeRowLight'], $body, '/<li class="([^"]*)" data-key="I\|Ascent of Egypt\|Hunting"/', 'the palest yellow row, light text too');
		assertContains('--colour: #ffbe00" title="Ascent of Egypt"', $body, 'and its dot the sheet colour at full saturation');
		assertTrue((bool)preg_match('/class="aoeLine[^"]*" style="--lane: \d+; --colour: #ffbe00; --edge: #000000"/', $body), 'and its line too, edged in whichever of black or white stands out against it');

		preg_match_all('/--rowColour: (#[0-9a-f]{6})/', $body, $rowColours);
		$sheetColours = gamesAoe2Colours(file_get_contents(GAMES_AOE2_STYLED_FIXTURE));
		assertSame([], array_values(array_diff($rowColours[1], $sheetColours)), 'every row takes its colour from the sheet');

		assertContains('No scenarios match', $ctx->get('/games/aoe2?game[]=')['body'], 'with nothing ticked nothing shows, rather than the defaults');

		$response = $ctx->get('/games/aoe2?game[]=I&game[]=nonsense&game[x][]=1');
		assertSame(200, $response['status'], 'options it does not have are ignored');
		assertContains('>Hunting<', $response['body'], 'and the real one still applies');

		$body = $ctx->get('/games/aoe2')['body'];
		assertContains('value="II|TC" checked', $body, 'every dlc is ticked to begin with');
		assertTrue(
			(bool)preg_match('#<div class="aoeFilterGroup aoeFilterGroupOff">\s*<label class="aoeFilterOption aoeFilterGroupName">\s*<input type="checkbox" name="game\[\]" value="I">#', $body),
			'a game\'s own box heads its row of dlc, and a game left unticked has its dlc dimmed'
		);
		assertTrue(
			(bool)preg_match('#<div class="aoeFilterGroup">\s*<label class="aoeFilterOption aoeFilterGroupName">\s*<input type="checkbox" name="game\[\]" value="II" checked>#', $body),
			'while a ticked one does not'
		);
		assertTrue(strpos($body, '<legend>Games</legend>') === false, 'and the games have no box of their own any more');
		assertSame(1, substr_count($body, 'name="game[]" value=""'), 'the games\' empty value is still sent, once');

		$body = $ctx->get('/games/aoe2?game[]=II&pack[]=' . rawurlencode('II|TC'))['body'];
		assertContains('>The Scourge of God<', $body, 'ticking one dlc shows its scenarios');
		assertTrue(strpos($body, '>Guiscard Arrives<') === false, 'and not another\'s of the same game');
	},

	'filters are saved to the account, and a link naming filters shows those without saving them' => function ($ctx) {
		$saved = function ($name) use ($ctx) {
			$stmt = $ctx->dbFor(GAMES_DB)->prepare("SELECT f.filters FROM aoe2_filter f JOIN account a ON a.id = f.account_id WHERE a.account_name = ?");
			$stmt->execute([$name]);
			$row = $stmt->fetch();
			return $row ? json_decode($row['filters'], true) : null;
		};
		$post = fn($fields) => $ctx->postForm('/games/aoe2', ['csrf_token' => $ctx->csrfTokenFrom('/games/aoe2')] + $fields);
		$mine = ['game' => ['I'], 'pack' => ['I|-'], 'scale' => false, 'collapsed' => [], 'expanded' => []];

		gamesLogIn($ctx, 'games_first', 'test password');

		$response = $post(['game' => ['', 'I'], 'pack' => ['', 'I|-', 'nonsense']]);
		assertSame(303, $response['status'], 'changing the filters posts them');
		assertSame('/games/aoe2', $response['location'], 'and comes back to the page');
		assertSame($mine, $saved('games_first'), 'saving what was ticked, and nothing the sheet does not offer');

		$body = $ctx->get('/games/aoe2')['body'];
		assertContains('>Hunting<', $body, 'the page shows the saved choice');
		assertTrue(strpos($body, '>The Battle of Agrigentum<') === false, 'leaving out a dlc that was unticked');
		assertContains('value="I" checked', $body, 'with its boxes ticked');

		$body = $ctx->get('/games/aoe2?game[]=II')['body'];
		assertContains('>Guiscard Arrives<', $body, 'an address naming filters shows those instead');
		assertSame($mine, $saved('games_first'), 'without saving them');

		$ctx->postForm('/games/aoe2', ['csrf_token' => 'forged', 'game' => ['', 'III']]);
		assertSame($mine, $saved('games_first'), 'a post without the form\'s token saves nothing');

		$post(['game' => [''], 'pack' => ['']]);
		assertContains('No scenarios match', $ctx->get('/games/aoe2')['body'], 'nothing ticked is saved as nothing, not as the defaults');

		$body = $ctx->get('/games/aoe2?game[]=II')['body'];
		assertContains('name="scale" value="1">', $body, 'the to-scale switch starts off');
		assertTrue(strpos($body, 'data-scale') === false, 'and the timeline with it');

		$post(['game' => ['', 'II'], 'pack' => ['', 'II|TC'], 'scale' => '1']);
		assertSame(true, $saved('games_first')['scale'], 'switching it on is saved with the filters');
		$body = $ctx->get('/games/aoe2')['body'];
		assertContains('name="scale" value="1" checked', $body, 'and shown switched on');
		assertContains('<ol class="aoeTimeline" data-scale="1"', $body, 'with the timeline laid out to scale');
		assertContains('data-scale="1"', $ctx->get('/games/aoe2?game[]=I')['body'], 'which an address naming filters keeps');

		$ctx->post('/games/ajax/aoe2-fold', ['list' => 'collapsed', 'key' => 'I|Ascent of Egypt|Foraging', 'on' => true]);
		$post(['game' => ['', 'I'], 'pack' => ['', 'I|-'], 'scale' => '1']);
		assertSame(['I|Ascent of Egypt|Foraging'], $saved('games_first')['collapsed'], 'a folded stretch survives changing the filters, which the form does not send');
		assertContains('data-next="I|Ascent of Egypt|Foraging" data-collapsed>', $ctx->get('/games/aoe2')['body'], 'and is shown folded');

		gamesLogIn($ctx, 'games_second', 'test password');
		assertContains('value="II" checked', $ctx->get('/games/aoe2')['body'], 'another account still has the defaults');

		/// The cases after this one read the default view.
		$ctx->dbFor(GAMES_DB)->exec("DELETE FROM aoe2_filter");
	},

	'ticks are saved per account, and only for scenarios the sheet has' => function ($ctx) {
		$key = 'II:R|Sargon of Akkad|The Chosen One';
		$ticked = 'value="' . htmlspecialchars($key) . '" checked';
		$rows = function () use ($ctx, $key) {
			$stmt = $ctx->dbFor(GAMES_DB)->prepare("SELECT account_id FROM aoe2_check WHERE scenario_key = ?");
			$stmt->execute([$key]);
			return $stmt->fetchAll();
		};

		gamesLogIn($ctx, 'games_first', 'test password');

		assertSame('ok', $ctx->post('/games/ajax/aoe2-check', ['key' => $key, 'done' => true])['json']['status'], 'a tick saves');
		$body = $ctx->get('/games/aoe2')['body'];
		assertContains($ticked, $body, 'and shows on the page');
		assertContains('1 of 16 done', $body, 'in the count too, Return of Rome now shown by default for holding a tick');

		$ctx->post('/games/ajax/aoe2-check', ['key' => $key, 'done' => true]);
		assertSame(1, count($rows()), 'ticking it again stores nothing more');

		gamesLogIn($ctx, 'games_second', 'test password');
		assertTrue(strpos($ctx->get('/games/aoe2')['body'], $ticked) === false, 'another account does not see it');

		gamesLogIn($ctx, 'games_first', 'test password');
		assertSame('ok', $ctx->post('/games/ajax/aoe2-check', ['key' => $key, 'done' => false])['json']['status'], 'unticking saves');
		assertSame(0, count($rows()), 'and removes the row');

		$response = $ctx->post('/games/ajax/aoe2-check', ['key' => 'II|Made Up|Nowhere', 'done' => true]);
		assertSame('error', $response['json']['status'], 'a scenario the sheet does not have is refused');
		assertSame(0, (int)$ctx->dbFor(GAMES_DB)->query("SELECT COUNT(*) c FROM aoe2_check WHERE scenario_key = 'II|Made Up|Nowhere'")->fetch()['c'], 'and stores nothing');

		assertSame(400, $ctx->post('/games/ajax/aoe2-check', ['key' => $key, 'done' => 'yes'])['status'], 'done has to be true or false');
	},

	'a descriptor, rating and note are saved per account, shown in the row and cleared to nothing' => function ($ctx) {
		$key = 'II|Attila the Hun|The Scourge of God';
		$save = fn($field, $value) => $ctx->post('/games/ajax/aoe2-entry', ['key' => $key, 'field' => $field, 'value' => $value])['json'];
		$row = function () use ($ctx, $key) {
			$stmt = $ctx->dbFor(GAMES_DB)->prepare("SELECT e.descriptor, e.rating, e.note FROM aoe2_entry e JOIN account a ON a.id = e.account_id WHERE a.account_name = 'games_first' AND e.scenario_key = ?");
			$stmt->execute([$key]);
			return $stmt->fetch() ?: null;
		};
		$rowHtml = function ($body) use ($key) {
			$start = strpos($body, 'data-key="' . htmlspecialchars($key) . '"');
			assertTrue($start !== false, 'the scenario is in the default view');
			return substr($body, $start, strpos($body, '</li>', $start) - $start);
		};

		gamesLogIn($ctx, 'games_first', 'test password');

		$head = $ctx->get('/games/aoe2')['body'];
		foreach (['Descriptor', 'Rating', 'Note', 'YouTube'] as $heading) {
			assertContains("<span>$heading</span>", $head, "the $heading heading is there");
		}
		assertSame(4, substr_count($rowHtml($head), 'class="aoeField"'), 'every row has the four cells');
		assertTrue(strpos($rowHtml($head), 'aoeFieldLink') === false, 'with no YouTube link to open until one is set');

		assertSame(['status' => 'ok', 'value' => 'Classic'], $save('descriptor', '  Classic '), 'a descriptor saves, trimmed');
		assertSame(['status' => 'ok', 'value' => '7.5'], $save('rating', '7,5'), 'a comma is a decimal point');
		assertSame(['status' => 'ok', 'value' => '<b>Hard</b> & long'], $save('note', '<b>Hard</b> & long'), 'a note saves');
		assertSame(['descriptor' => 'Classic', 'rating' => 7.5, 'note' => '<b>Hard</b> & long'], $row(), 'one row holds all three');

		$html = $rowHtml($ctx->get('/games/aoe2')['body']);
		assertContains('>Classic</button>', $html, 'the descriptor shows in the row');
		assertContains('>7.5</button>', $html, 'and the rating');
		assertContains('>&lt;b&gt;Hard&lt;/b&gt; &amp; long</button>', $html, 'and the note, escaped');
		assertContains('aria-label="Note for The Scourge of God: &lt;b&gt;Hard&lt;/b&gt; &amp; long"', $html, 'the cell is labelled with its column, scenario and value');

		assertContains('data-tooltip="&lt;b&gt;Hard&lt;/b&gt; &amp; long"', $html, 'a note carries its whole text for the hover box');
		assertContains('data-tooltip="Classic"', $html, 'and so does a descriptor');
		assertTrue(preg_match('/data-field="rating"><button[^>]*data-tooltip/', $html) === 0, 'a rating has none');
		assertContains('<div id="gamesTooltip"></div>', $ctx->get('/games/aoe2')['body'], 'the page has the hover box');

		assertSame("Line one\nLine two", $save('note', "Line one\r\nLine two\n")['value'], 'a note keeps its line breaks, as \n, trimmed at the ends');
		assertContains("data-tooltip=\"Line one\nLine two\"", $rowHtml($ctx->get('/games/aoe2')['body']), 'and its hover box has them');
		assertSame("Two\nlines", $save('descriptor', "Two\nlines")['value'], 'so does a descriptor');
		$save('descriptor', 'Classic');
		$save('note', '<b>Hard</b> & long');

		assertSame('8', $save('rating', '8.00')['value'], 'a rating drops its trailing zeros');
		assertSame('0', $save('rating', '0')['value'], 'zero is a rating');
		assertSame('10', $save('rating', '10')['value'], 'and so is ten');
		foreach (['11', '-1', 'abc', '7.555', '10.01', '1e1'] as $bad) {
			$response = $save('rating', $bad);
			assertSame('error', $response['status'], "a rating of $bad is refused");
			assertSame('A rating is a number from 0 to 10, with at most two decimals.', $response['message'], "with a reason ($bad)");
		}
		assertSame(10.0, (float)$row()['rating'], 'and leaves the rating as it was');
		assertSame('error', $save('descriptor', str_repeat('x', 101))['status'], 'a descriptor over 100 bytes is refused');
		assertSame('error', $save('note', str_repeat('x', 2001))['status'], 'a note over 2000 bytes is refused');

		$youtube = function () use ($ctx, $key) {
			$stmt = $ctx->dbFor(GAMES_DB)->prepare("SELECT e.youtube FROM aoe2_entry e JOIN account a ON a.id = e.account_id WHERE a.account_name = 'games_first' AND e.scenario_key = ?");
			$stmt->execute([$key]);
			return $stmt->fetchColumn();
		};
		$watch = 'https://www.youtube.com/watch?v=';
		foreach ([
			'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'dQw4w9WgXcQ',
			'https://www.youtube.com/watch?list=PL123&v=dQw4w9WgXcQ&t=1m30s' => 'dQw4w9WgXcQ&t=90',
			'https://youtu.be/dQw4w9WgXcQ?t=656' => 'dQw4w9WgXcQ&t=656',
			'https://www.youtube.com/shorts/dQw4w9WgXcQ' => 'dQw4w9WgXcQ',
			'https://www.youtube.com/live/dQw4w9WgXcQ?si=abc' => 'dQw4w9WgXcQ',
			'dQw4w9WgXcQ' => 'dQw4w9WgXcQ',
		] as $pasted => $stored) {
			assertSame(['status' => 'ok', 'value' => $watch . $stored], $save('youtube', $pasted), "a YouTube link saves from $pasted");
			assertSame($stored, $youtube(), "as its id and start ($pasted)");
		}
		foreach (['not a link', 'abc', 'https://example.com/watch?v=dQw4w9WgXcQ'] as $bad) {
			$response = $save('youtube', $bad);
			assertSame('That is not a YouTube video link.', $response['message'] ?? null, "$bad is refused");
		}
		assertSame('dQw4w9WgXcQ', $youtube(), 'and leaves the link as it was');
		$html = $rowHtml($ctx->get('/games/aoe2')['body']);
		assertContains('<a class="aoeFieldLink" href="' . $watch . 'dQw4w9WgXcQ" target="_blank" rel="noopener"', $html, 'the row links to the video, in a new tab');
		assertContains('data-value="' . $watch . 'dQw4w9WgXcQ"', $html, 'and its pencil holds the link to edit');
		assertSame(['status' => 'ok', 'value' => ''], $save('youtube', ''), 'a link clears');
		assertTrue(strpos($rowHtml($ctx->get('/games/aoe2')['body']), 'aoeFieldLink') === false, 'and the row stops linking');

		assertSame(400, $ctx->post('/games/ajax/aoe2-entry', ['key' => $key, 'field' => 'account_id', 'value' => '1'])['status'], 'a field that is not one of the four is refused');
		assertSame(400, $ctx->post('/games/ajax/aoe2-entry', ['key' => $key, 'field' => 'note', 'value' => 5])['status'], 'a value has to be text');
		$response = $ctx->post('/games/ajax/aoe2-entry', ['key' => 'II|Made Up|Nowhere', 'field' => 'note', 'value' => 'x']);
		assertSame('error', $response['json']['status'], 'a scenario the sheet does not have is refused');
		assertSame(0, (int)$ctx->dbFor(GAMES_DB)->query("SELECT COUNT(*) c FROM aoe2_entry WHERE scenario_key = 'II|Made Up|Nowhere'")->fetch()['c'], 'and stores nothing');

		gamesLogIn($ctx, 'games_second', 'test password');
		assertTrue(strpos($rowHtml($ctx->get('/games/aoe2')['body']), 'Classic') === false, 'another account does not see it');

		gamesLogIn($ctx, 'games_first', 'test password');
		$save('descriptor', '');
		$save('rating', ' ');
		assertSame(['descriptor' => null, 'rating' => null, 'note' => '<b>Hard</b> & long'], $row(), 'an empty value clears its field alone');
		assertSame(['status' => 'ok', 'value' => ''], $save('note', ''), 'clearing the last field saves');
		assertSame(null, $row(), 'and removes the row');
	},

	'the Sims 2 lists hold unique keys, and none is empty' => function ($ctx) {
		$lists = gamesSims2Lists();
		$keys = [];
		foreach ($lists as $list) {
			assertTrue(strpos($list['key'], '|') === false, "the list key {$list['key']} has no |");
			$listKeys = gamesSims2ListKeys($list);
			/// The vehicles are rows, filled in on the page, and there may be none.
			if (empty($list['vehicles'])) {
				assertTrue(count($listKeys) > 0, "{$list['title']} has items");
			}
			$keys = array_merge($keys, $listKeys);
			foreach ($list['columns'] ?? [] as $column) {
				assertTrue(strpos($column['key'], '|') === false, "the column key {$column['key']} has no |");
			}
			foreach (array_keys($list['limits'] ?? []) as $group) {
				assertTrue(isset($list['groups'][$group]), "{$list['title']} limits a group it has ($group)");
			}
		}
		assertSame(count($keys), count(array_unique($keys)), 'no two items or boxes share a key');
		assertSame(count(array_unique(array_column($lists, 'key'))), count($lists), 'no two lists share a key');
	},

	'the Sims 2 page shows every list and item, and ticks are saved per account' => function ($ctx) {
		$key = 'careers|Paranormal|level|3';
		$ticked = 'value="careers|Paranormal|level|3" aria-label="Paranormal: Levels reached 3" checked';
		$careerKeys = gamesSims2ListKeys(gamesSims2Lists()[array_search('careers', array_column(gamesSims2Lists(), 'key'), true)]);
		$rows = function () use ($ctx, $key) {
			$stmt = $ctx->dbFor(GAMES_DB)->prepare("SELECT account_id FROM sims2_check WHERE item_key = ?");
			$stmt->execute([$key]);
			return $stmt->fetchAll();
		};
		$careers = function ($body) {
			$start = strpos($body, 'data-list="careers"');
			assertTrue($start !== false, 'the careers list is on the page');
			return substr($body, $start, strpos($body, '</section>', $start) - $start);
		};

		gamesLogIn($ctx, 'games_first', 'test password');

		$response = $ctx->get('/games/sims2');
		assertSame(200, $response['status'], 'the page loads');
		$body = $response['body'];
		assertContains('href="/games/sims2"', gamesNav($body), 'the nav links to it');
		foreach (gamesSims2Lists() as $list) {
			assertContains(htmlspecialchars($list['title']), $body, "{$list['title']} is on the page");
			foreach (gamesSims2ListKeys($list) as $listKey) {
				assertContains('value="' . htmlspecialchars($listKey) . '"', $body, "{$listKey} has its box");
			}
		}
		assertContains('href="/games/sims2"', $ctx->get('/games')['body'], 'the home page links to it');

		/// The grids: careers and hobbies have a box per level and card, the pets' careers only five levels.
		$careerGrid = $careers($body);
		assertContains('<table class="simsGrid">', $careerGrid, 'careers are a grid');
		assertContains('<th colspan="10" class="simsGridSet">Levels reached</th>', $careerGrid, 'with ten levels');
		assertContains('<th colspan="10" class="simsGridSet">Chance cards seen</th>', $careerGrid, 'ten chance cards');
		assertContains('<th colspan="3" class="simsGridSet">T/E levels reached</th>', $careerGrid, 'and three teen and elder levels');
		assertSame(25 * 23 + 3 * 5, count($careerKeys), 'every career a box each, the pets five');
		$petRow = substr($careerGrid, strpos($careerGrid, '<th scope="row">Pet Security</th>'));
		$petRow = substr($petRow, 0, strpos($petRow, '</tr>'));
		assertSame(5, substr_count($petRow, 'class="simsCheck"'), 'a pet career has levels 1 to 5');
		assertSame(18, substr_count($petRow, 'simsGridNone'), 'and nothing past them');
		assertContains('value="hobbies|Nature|secret|1" aria-label="Nature: Secret lot visited"', $body, 'a hobby has its secret lot');
		assertContains('value="hobbies|Nature|chance|6"', $body, 'and six chance cards');
		assertTrue(strpos($body, 'value="hobbies|Nature|chance|7"') === false, 'not seven');

		assertSame('ok', $ctx->post('/games/ajax/sims2-check', ['key' => $key, 'done' => true])['json']['status'], 'a tick saves');
		$list = $careers($ctx->get('/games/sims2')['body']);
		assertContains($ticked, $list, 'and shows on the page');
		assertContains('<span class="simsProgress">1 of ' . count($careerKeys) . '</span>', $list, 'and in the count of its list');

		$ctx->post('/games/ajax/sims2-check', ['key' => $key, 'done' => true]);
		assertSame(1, count($rows()), 'ticking it again stores nothing more');

		gamesLogIn($ctx, 'games_second', 'test password');
		assertTrue(strpos($ctx->get('/games/sims2')['body'], $ticked) === false, 'another account does not see it');

		gamesLogIn($ctx, 'games_first', 'test password');
		assertSame('ok', $ctx->post('/games/ajax/sims2-check', ['key' => $key, 'done' => false])['json']['status'], 'unticking saves');
		assertSame(0, count($rows()), 'and removes the row');

		foreach (['careers|Astronaut|level|1', 'careers|Paranormal', 'careers|Pet Security|level|6', 'careers|Pet Security|te|1', 'hobbies|Nature|chance|7', 'careers|Paranormal|level|11'] as $missing) {
			$response = $ctx->post('/games/ajax/sims2-check', ['key' => $missing, 'done' => true]);
			assertSame('That item is not on any list.', $response['json']['message'] ?? null, "$missing is refused");
		}
		assertSame('ok', $ctx->post('/games/ajax/sims2-check', ['key' => 'hobbies|Nature|secret|1', 'done' => false])['json']['status'], 'a secret lot box is a box');
		$response = $ctx->post('/games/ajax/sims2-check', ['key' => 'careers|Astronaut', 'done' => true]);
		assertSame('That item is not on any list.', $response['json']['message'] ?? null, 'an item no list has is refused');
		assertSame(0, (int)$ctx->dbFor(GAMES_DB)->query("SELECT COUNT(*) c FROM sims2_check WHERE item_key = 'careers|Astronaut'")->fetch()['c'], 'and stores nothing');
		assertSame(400, $ctx->post('/games/ajax/sims2-check', ['key' => $key, 'done' => 'yes'])['status'], 'done has to be true or false');
	},

	'admins write up the vehicles, which are the Vehicle Encounters list, with ticks that survive a rename' => function ($ctx) {
		$db = $ctx->dbFor(GAMES_DB);
		$vehicle = function ($name) use ($db) {
			$stmt = $db->prepare("SELECT id, name, occasions FROM sims2_vehicle WHERE name = ?");
			$stmt->execute([$name]);
			$row = $stmt->fetch();
			return $row ? ['id' => (int)$row['id'], 'name' => $row['name'], 'occasions' => json_decode($row['occasions'], true)] : null;
		};
		$card = function ($body) {
			$start = strpos($body, 'data-list="vehicleEncounters"');
			assertTrue($start !== false, 'the vehicles card is on the page');
			return substr($body, $start, strpos($body, '</section>', $start) - $start);
		};
		$send = function ($body) use ($ctx) {
			return $ctx->post('/games/ajax/sims2-vehicle', $body)['json'];
		};

		gamesLogIn($ctx, 'games_first', 'test password');
		$body = $card($ctx->get('/games/sims2')['body']);
		assertSame(0, (int)$db->query("SELECT COUNT(*) c FROM sims2_vehicle")->fetch()['c'], 'a fresh database has no vehicles');
		assertContains('No vehicles written up yet.', $body, 'and the card says so');
		assertContains('class="simsVehicleAdd simsPlus"', $body, 'with a + for an admin');

		$result = $send(['action' => 'add', 'name' => '  Ice cream van ']);
		assertSame('ok', $result['status'], 'adding a vehicle saves');
		$row = $vehicle('Ice cream van');
		assertTrue($row !== null, 'trimmed');
		assertContains('value="vehicleEncounters|Ice cream van"', $result['html'], 'and answers with its row, box and all');
		assertContains('class="simsOccasionAdd simsPlus"', $result['html'], 'with a + for its occasions');

		$result = $send(['action' => 'occasions', 'id' => $row['id'], 'occasions' => ['Summer afternoons', ' ', "  A kid's birthday "]]);
		assertSame(['Summer afternoons', "A kid's birthday"], $vehicle('Ice cream van')['occasions'], 'occasions save trimmed, an emptied one dropped');
		assertContains('<span class="simsOccasionText" title="Double-click to edit">A kid&#039;s birthday</span>', $result['html'], 'and are in the row');
		assertSame(400, $ctx->post('/games/ajax/sims2-vehicle', ['action' => 'occasions', 'id' => $row['id'], 'occasions' => [1, 2]])['status'], 'occasions have to be text');
		$result = $send(['action' => 'occasions', 'id' => $row['id'], 'occasions' => [str_repeat('x', 201)]]);
		assertSame('An occasion can be at most 200 characters.', $result['message'] ?? null, 'and not too long');

		$body = $card($ctx->get('/games/sims2')['body']);
		assertContains('value="vehicleEncounters|Ice cream van"', $body, 'the page lists it');
		assertContains('A kid&#039;s birthday</span>', $body, 'with its occasions');

		assertSame('There is already a vehicle called ICE CREAM VAN.', $send(['action' => 'add', 'name' => 'ICE CREAM VAN'])['message'] ?? null, 'a name another vehicle has, in any case, is refused');
		assertSame('A vehicle needs a name.', $send(['action' => 'add', 'name' => ' '])['message'] ?? null, 'and an empty one');
		assertSame(1, (int)$db->query("SELECT COUNT(*) c FROM sims2_vehicle")->fetch()['c'], 'neither stored anything');

		$key = 'vehicleEncounters|Ice cream van';
		assertSame('ok', $ctx->post('/games/ajax/sims2-check', ['key' => $key, 'done' => true])['json']['status'], 'its box ticks');
		$result = $send(['action' => 'rename', 'id' => $row['id'], 'name' => 'Ice-cream van']);
		assertSame(null, $vehicle('Ice cream van'), 'a rename saves');
		assertSame(['Summer afternoons', "A kid's birthday"], $vehicle('Ice-cream van')['occasions'], 'keeping its occasions');
		assertContains('value="vehicleEncounters|Ice-cream van" checked', $result['html'], 'and the tick comes along');
		assertSame('ok', $send(['action' => 'rename', 'id' => $row['id'], 'name' => 'ice-cream VAN'])['status'], 'a vehicle may change the case of its own name');

		gamesLogIn($ctx, 'games_second', 'test password');
		$body = $card($ctx->get('/games/sims2')['body']);
		assertContains('value="vehicleEncounters|ice-cream VAN"', $body, 'everyone sees the vehicles');
		assertContains('A kid&#039;s birthday</span>', $body, 'and their occasions');
		assertTrue(strpos($body, 'value="vehicleEncounters|ice-cream VAN" checked') === false, 'but not each other\'s ticks');
		assertTrue(strpos($body, 'simsPlus') === false && strpos($body, 'simsMinus') === false && strpos($body, 'data-editable') === false, 'and only admins can edit');
		$response = $ctx->post('/games/ajax/sims2-vehicle', ['action' => 'delete', 'id' => $row['id']]);
		assertSame(403, $response['status'], 'nor can anybody else post a change');
		assertSame('Only admins can change the vehicles.', $response['json']['message'] ?? null, 'which says why');
		assertTrue($vehicle('ice-cream VAN') !== null, 'and changes nothing');

		gamesLogIn($ctx, 'games_first', 'test password');
		assertSame(['status' => 'ok'], $send(['action' => 'delete', 'id' => $row['id']]), 'an admin can delete one');
		assertSame(null, $vehicle('ice-cream VAN'), 'which deletes it');
		assertTrue(strpos($ctx->get('/games/sims2')['body'], 'ice-cream VAN') === false, 'and takes it off the page');
		assertSame('That vehicle is gone. Reload the page.', $send(['action' => 'rename', 'id' => $row['id'], 'name' => 'Tank'])['message'] ?? null, 'a vehicle that is gone cannot be changed');
		$response = $ctx->post('/games/ajax/sims2-check', ['key' => 'vehicleEncounters|ice-cream VAN', 'done' => false]);
		assertSame('That item is not on any list.', $response['json']['message'] ?? null, 'and its box is no longer one');
		assertSame(400, $ctx->post('/games/ajax/sims2-vehicle', ['action' => 'paint', 'name' => 'Tank'])['status'], 'an action it does not know is refused');
	},

	'the tick, fold and entry endpoints are guarded the same way the music ones are' => function ($ctx) {
		foreach (['/games/ajax/aoe2-check', '/games/ajax/aoe2-fold', '/games/ajax/aoe2-entry', '/games/ajax/sims2-check', '/games/ajax/sims2-vehicle'] as $endpoint) {
			$ctx->newSession();
			assertSame(401, $ctx->post($endpoint, [])['status'], "$endpoint: signed out");

			gamesLogIn($ctx, 'games_first', 'test password');

			assertSame(405, $ctx->get($endpoint)['status'], "$endpoint: a GET is refused");
			assertSame(403, $ctx->postWithoutCsrf($endpoint, [])['status'], "$endpoint: a missing token is refused");
			assertSame(400, $ctx->post($endpoint, 'null')['status'], "$endpoint: a malformed body is refused");
		}
	},

	'folded stretches are saved per account, only for scenarios the sheet has, beside the filters' => function ($ctx) {
		/// The stretch before the second year, so there is one under any filter showing it.
		$key = 'II:R|Sargon of Akkad|The Prophecy';
		$settings = function ($name) use ($ctx) {
			$stmt = $ctx->dbFor(GAMES_DB)->prepare("SELECT f.filters FROM aoe2_filter f JOIN account a ON a.id = f.account_id WHERE a.account_name = ?");
			$stmt->execute([$name]);
			$row = $stmt->fetch();
			return $row ? json_decode($row['filters'], true) : null;
		};
		$fold = fn($folded, $which = null) => $ctx->post('/games/ajax/aoe2-fold', ['list' => 'collapsed', 'key' => $which ?? $key, 'on' => $folded]);

		gamesLogIn($ctx, 'games_first', 'test password');
		$ctx->postForm('/games/aoe2', ['csrf_token' => $ctx->csrfTokenFrom('/games/aoe2'), 'game' => ['', 'II'], 'pack' => ['', 'II|TC'], 'scale' => '1']);

		assertSame('ok', $fold(true)['json']['status'], 'folding a stretch saves');
		$fold(true);
		assertSame([$key], $settings('games_first')['collapsed'], 'once, however often it is folded');
		assertSame(['game' => ['II'], 'pack' => ['II|TC'], 'scale' => true], array_diff_key($settings('games_first'), ['collapsed' => true, 'expanded' => true]), 'leaving the filters and the switch as they were');
		assertContains('data-next="' . htmlspecialchars($key) . '" data-collapsed>', $ctx->get('/games/aoe2?game[]=II:R')['body'], 'and the page shows it folded');

		gamesLogIn($ctx, 'games_second', 'test password');
		assertTrue(strpos($ctx->get('/games/aoe2?game[]=II:R')['body'], 'data-collapsed') === false, 'another account does not see it');

		gamesLogIn($ctx, 'games_first', 'test password');
		assertSame('ok', $fold(false)['json']['status'], 'unfolding saves');
		assertSame([], $settings('games_first')['collapsed'], 'and takes it off the list');

		$response = $fold(true, 'II|Made Up|Nowhere');
		assertSame('error', $response['json']['status'], 'a scenario the sheet does not have is refused');
		assertSame([], $settings('games_first')['collapsed'], 'and stores nothing');

		assertSame(400, $fold('yes')['status'], 'on has to be true or false');
		assertSame(400, $ctx->post('/games/ajax/aoe2-fold', ['list' => 'filters', 'key' => $key, 'on' => true])['status'], 'and the list one of the two');

		/// The cases after this one read the default view.
		$ctx->dbFor(GAMES_DB)->exec("DELETE FROM aoe2_filter");
	},

	'to scale, a year of several scenarios is one row, which unfolds them and is remembered' => function ($ctx) {
		$first = 'II:R|Sargon of Akkad|The Chosen One';
		$second = 'II:R|Sargon of Akkad|Divine Will';
		$header = function ($body) use ($first) {
			preg_match('#<li class="aoeGroup[^"]*" data-group="' . preg_quote($first, '#') . '"[^>]*>.*?</li>#s', $body, $match);
			return $match[0] ?? '';
		};
		$settings = function () use ($ctx) {
			$row = $ctx->dbFor(GAMES_DB)->query("SELECT f.filters FROM aoe2_filter f JOIN account a ON a.id = f.account_id WHERE a.account_name = 'games_first'")->fetch();
			return json_decode($row['filters'], true);
		};
		$filters = ['game' => ['', 'II:R'], 'pack' => ['', 'II:R|ReoR']];

		gamesLogIn($ctx, 'games_first', 'test password');
		$off = $ctx->get('/games/aoe2?game[]=II:R')['body'];
		assertTrue(strpos($off, 'class="aoeGroup') === false, 'with the switch off, every scenario has a row of its own');
		assertTrue(strpos($off, 'class="aoeFoldAll"') === false, 'and there is nothing to fold all of');

		$ctx->postForm('/games/aoe2', ['csrf_token' => $ctx->csrfTokenFrom('/games/aoe2'), 'scale' => '1'] + $filters);
		$body = $ctx->get('/games/aoe2')['body'];
		assertContains('<li class="aoeGroup aoeRowLight" data-group="' . $first . '" data-year="-2334">', $body, 'to scale, the two scenarios of c.2334 BCE share one row, named by the first');
		assertContains('aria-expanded="false">2 scenarios</button>', $body, 'which reads how many it holds');
		assertSame(2, substr_count($header($body), 'class="aoeDot aoeFoldedOnly'), 'with a dot for each, both being Sargon of Akkad');
		assertTrue((bool)preg_match('#class="aoeDot aoeFoldedOnly aoeDotUp" style="--lane: 1; [^"]*" data-key="' . preg_quote($second, '#') . '"#', $header($body)), 'the second in the next lane, raised as every odd lane\'s is, so the two zigzag');
		assertTrue((bool)preg_match('#data-key="' . preg_quote($second, '#') . '"[^>]*data-group="' . preg_quote($first, '#') . '" hidden="until-found">#', $body), 'the scenarios are folded away under it, hidden until found so the browser\'s find still reaches them');
		assertTrue((bool)preg_match('#data-key="' . preg_quote($first, '#') . '".*?</li>\s*<li class="aoeRow#s', $body), 'with no room between them');
		assertTrue((bool)preg_match('#data-key="' . preg_quote($second, '#') . '".*?</li>\s*<li class="aoeGap" role="none" data-next="II:R\|Sargon of Akkad\|The Prophecy">#s', $body), 'and the stretch after the year named by the next year\'s first');
		assertTrue((bool)preg_match('#data-key="II:R\|Sargon of Akkad\|Subartu"[^>]*style="[^"]*">#', $body), 'a year with one scenario keeps its row');

		$ctx->post('/games/ajax/aoe2-check', ['key' => $first, 'done' => true]);
		$folded = $header($ctx->get('/games/aoe2')['body']);
		assertTrue((bool)preg_match('#class="aoeDot aoeFoldedOnly aoeDotDone"[^>]*data-key="' . preg_quote($first, '#') . '"#', $folded), 'a ticked scenario\'s dot is filled');
		assertTrue((bool)preg_match('#class="aoeDot aoeFoldedOnly aoeDotUp"[^>]*data-key="' . preg_quote($second, '#') . '"#', $folded), 'and the other stays hollow');
		assertTrue(strpos($folded, 'aoeGroupDone') === false, 'the year is not done while one of its scenarios is not');
		$ctx->post('/games/ajax/aoe2-check', ['key' => $second, 'done' => true]);
		assertContains('<li class="aoeGroup aoeRowLight aoeGroupDone"', $header($ctx->get('/games/aoe2')['body']), 'and greys out once all are');
		$ctx->post('/games/ajax/aoe2-check', ['key' => $first, 'done' => false]);
		$ctx->post('/games/ajax/aoe2-check', ['key' => $second, 'done' => false]);

		assertSame('ok', $ctx->post('/games/ajax/aoe2-fold', ['list' => 'expanded', 'key' => $first, 'on' => true])['json']['status'], 'unfolding a year saves');
		assertSame([$first], $settings()['expanded'], 'on the account');
		$ctx->postForm('/games/aoe2', ['csrf_token' => $ctx->csrfTokenFrom('/games/aoe2'), 'scale' => '1'] + $filters);
		assertSame([$first], $settings()['expanded'], 'where changing the filters leaves it');
		$body = $ctx->get('/games/aoe2')['body'];
		assertContains('data-group="' . $first . '" data-year="-2334" data-expanded>', $body, 'and the page shows it unfolded');
		assertContains('aria-expanded="true">2 scenarios</button>', $body, 'saying so to a screen reader');
		assertTrue((bool)preg_match('#data-key="' . preg_quote($second, '#') . '"[^>]*data-group="' . preg_quote($first, '#') . '">#', $body), 'with its scenarios showing');

		$ctx->post('/games/ajax/aoe2-fold', ['list' => 'expanded', 'key' => $first, 'on' => false]);
		assertSame([], $settings()['expanded'], 'folding it again takes it off the list');

		$body = $ctx->get('/games/aoe2')['body'];
		foreach (['expanded" data-on="0">Fold all years<', 'expanded" data-on="1">Unfold all years<', 'collapsed" data-on="1">Fold all stretches<', 'collapsed" data-on="0">Unfold all stretches<'] as $button) {
			assertContains('<button type="button" data-list="' . $button, $body, 'to scale, buttons fold or unfold every year or every stretch at once');
		}
		$third = 'II:R|Sargon of Akkad|The Prophecy';
		assertSame('ok', $ctx->post('/games/ajax/aoe2-fold', ['list' => 'expanded', 'keys' => [$first, $third], 'on' => true])['json']['status'], 'they send every place they change in one request');
		assertSame([$first, $third], $settings()['expanded'], 'which saves them all');
		assertSame('error', $ctx->post('/games/ajax/aoe2-fold', ['list' => 'expanded', 'keys' => [$first, 'II|Made Up|Nowhere'], 'on' => false])['json']['status'], 'one name the sheet does not have refuses them all');
		assertSame([$first, $third], $settings()['expanded'], 'changing nothing');
		assertSame(400, $ctx->post('/games/ajax/aoe2-fold', ['list' => 'expanded', 'keys' => [], 'on' => true])['status'], 'an empty list is refused');
		assertSame(400, $ctx->post('/games/ajax/aoe2-fold', ['list' => 'expanded', 'keys' => $first, 'on' => true])['status'], 'and a list that is not one');
		assertSame(400, $ctx->post('/games/ajax/aoe2-fold', ['list' => 'expanded', 'keys' => [$first, 3], 'on' => true])['status'], 'and one with anything but names in it');
		$ctx->post('/games/ajax/aoe2-fold', ['list' => 'expanded', 'keys' => [$first, $third], 'on' => false]);
		assertSame([], $settings()['expanded'], 'unfolding them all takes them all off');
		assertSame('ok', $ctx->post('/games/ajax/aoe2-fold', ['list' => 'collapsed', 'keys' => ['present', $third], 'on' => true])['json']['status'], 'the stretch to the present goes in a list too');
		$ctx->post('/games/ajax/aoe2-fold', ['list' => 'collapsed', 'keys' => ['present', $third], 'on' => false]);
		assertTrue(strpos($ctx->get('/games/aoe2?game[]=II:R')['body'], 'class="aoeFoldAll"') !== false, 'the buttons follow the saved switch');

		/// The cases after this one read the default view.
		$ctx->dbFor(GAMES_DB)->exec("DELETE FROM aoe2_filter");
	},

	'to scale, the year axis runs to the present year, before the undated scenarios' => function ($ctx) {
		$settings = function () use ($ctx) {
			$row = $ctx->dbFor(GAMES_DB)->query("SELECT f.filters FROM aoe2_filter f JOIN account a ON a.id = f.account_id WHERE a.account_name = 'games_first'")->fetch();
			return json_decode($row['filters'], true);
		};

		gamesLogIn($ctx, 'games_first', 'test password');
		$ctx->postForm('/games/aoe2', ['csrf_token' => $ctx->csrfTokenFrom('/games/aoe2'), 'scale' => '1']);
		$body = $ctx->get('/games/aoe2')['body'];
		assertContains('data-present-year="' . date('Y') . '"', $body, 'the timeline knows the present year');
		assertTrue((bool)preg_match('#</li>\s*<li class="aoeGap" role="none" data-next="II\|The Art of War \(AoE II\)\|Booming" data-present>\s*<span class="aoeLanes"#', $body), 'the stretch before the undated Art of War runs to it');
		assertSame(1, substr_count($body, 'data-present>'), 'and only that one');

		$body = $ctx->get('/games/aoe2?game[]=II:R')['body'];
		assertTrue((bool)preg_match('#data-key="II:R\|Sargon of Akkad\|Subartu".*?</li>\s*<li class="aoeGap" role="none" data-next="present" data-present>.*?</li>\s*</ol>#s', $body), 'with nothing undated, a stretch after the last scenario runs to it');

		assertSame('ok', $ctx->post('/games/ajax/aoe2-fold', ['list' => 'collapsed', 'key' => 'present', 'on' => true])['json']['status'], 'and folds by that name');
		assertContains('data-next="present" data-present data-collapsed>', $ctx->get('/games/aoe2?game[]=II:R')['body'], 'shown folded');
		assertSame(['present'], $settings()['collapsed'], 'saved as such');
		assertSame('error', $ctx->post('/games/ajax/aoe2-fold', ['list' => 'expanded', 'key' => 'present', 'on' => true])['json']['status'], 'no year is called that');

		/// The cases after this one read the default view.
		$ctx->dbFor(GAMES_DB)->exec("DELETE FROM aoe2_filter");
	},

	'the stylesheet carries no line comments, which css does not have' => function ($ctx) {
		$body = $ctx->get('/modules/games/css/styles.css')['body'];

		assertContains('.gamesLayout', $body, 'the stylesheet serves');

		foreach (explode("\n", $body) as $number => $line) {
			assertTrue(strpos(ltrim($line), '//') !== 0, 'styles.css line ' . ($number + 1) . ' starts a // comment');
		}
	},

];
