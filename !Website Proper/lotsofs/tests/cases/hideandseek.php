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

	'it has a database of its own, with nothing but the account tables in it' => function ($ctx) {
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

	/* The map itself is javascript, which this harness does not run. What it can
	   prove is that every piece ships, that the page wires them together, and
	   that the container carries the centre the route chose. */
	'the landing page carries a map container and the library it needs' => function ($ctx) {
		$body = $ctx->get('/hideandseek')['body'];

		assertContains('id="hnsMap"', $body, 'the container is there');
		assertContains('data-lat="', $body, 'carrying the centre');
		assertContains('data-zoom="', $body, 'and the zoom, so the script hardcodes neither');

		foreach (['/modules/hideandseek/vendor/leaflet/leaflet.css', '/modules/hideandseek/vendor/leaflet/leaflet.js', '/modules/hideandseek/js/map.js'] as $asset) {
			assertTrue((bool)preg_match('#' . preg_quote($asset, '#') . '\?v=\d+#', $body), "{$asset} is linked with a cache stamp");
			assertSame(200, $ctx->get($asset)['status'], "{$asset} serves");
		}

		/// Leaflet's css asks for these by relative path, so they resolve
		/// against the vendor folder rather than the page.
		assertSame(200, $ctx->get('/modules/hideandseek/vendor/leaflet/images/marker-icon.png')['status'], 'the default pin serves');
	},

	/// 15KB of map styling has no business on the pages without a map.
	'only the map page loads the map stylesheet' => function ($ctx) {
		$check = function ($path) use ($ctx) {
			$body = $ctx->get($path)['body'];
			assertTrue(strpos($body, 'leaflet.css') === false, "{$path} does not load leaflet");
			assertTrue(strpos($body, 'id="hnsMap"') === false, "{$path} has no map container");
		};

		hnsLogIn($ctx, 'hns_first', 'test password');
		$check('/hideandseek/accounts');

		/// Signed out, or the login page redirects to the landing page, which is
		/// the one page that does load it.
		hnsLogOut($ctx);
		$check('/hideandseek/login');
	},

	/* The tiles come from a third party, so the page must not ask for them
	   until somebody does. The container ships empty and map.js builds the
	   button - if a tile url ever appears in the markup, that is gone. */
	'no tile server is named anywhere in the delivered page' => function ($ctx) {
		$body = $ctx->get('/hideandseek')['body'];

		assertTrue(strpos($body, 'tile.openstreetmap.org') === false, 'the page names no tile server');
		assertTrue((bool)preg_match('/<div\s+id="hnsMap"[^>]*>\s*<\/div>/', $body), 'and the container ships empty, for the script to fill');

		/// The url lives in the script, which is fetched but not run.
		assertContains('tile.openstreetmap.org', $ctx->get('/modules/hideandseek/js/map.js')['body'], 'the script is what knows it');
	},

	'the stylesheet carries no line comments, which css does not have' => function ($ctx) {
		$body = $ctx->get('/modules/hideandseek/css/styles.css')['body'];

		foreach (explode("\n", $body) as $number => $line) {
			$trimmed = ltrim($line);
			assertTrue(strpos($trimmed, '//') !== 0, 'styles.css line ' . ($number + 1) . ' starts a // comment');
		}
	},

];
