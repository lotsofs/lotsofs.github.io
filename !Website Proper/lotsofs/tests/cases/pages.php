<?php

const MUSIC_AJAX_ENDPOINTS = [
	'/music/ajax/artist-alias',
	'/music/ajax/song',
	'/music/ajax/song-edit',
	'/music/ajax/song-rating',
	'/music/ajax/song-rating-poll',
	'/music/ajax/album',
];

return [

	'every registered route serves' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$expected = [
			'/' => 200,
			'/contact' => 200,
			'/exchange-rates' => 200,
			'/ktane' => 302,
			'/music/add-songs' => 200,
			'/music/songs' => 200,
			'/music/songs?artist=1&album=1' => 200,
			'/music/artists' => 200,
			'/music/albums' => 200,
			'/music/accounts' => 200,
			'/swat4/2' => 200,
			'/ss2/11' => 200,
			'/ss2/18' => 200,
		];
		foreach ($expected as $path => $status) {
			assertSame($status, $ctx->get($path)['status'], "GET {$path}");
		}
	},

	'the music pages are hidden from signed out visitors' => function ($ctx) {
		$ctx->newSession();

		foreach (['/music/add-songs', '/music/songs', '/music/invites'] as $path) {
			$response = $ctx->get($path);
			assertSame(302, $response['status'], "GET {$path} while signed out");
			assertContains('/music/login', $response['location'], "{$path} redirect target");
		}
	},

	'the music endpoints reject signed out callers' => function ($ctx) {
		$ctx->newSession();

		foreach (MUSIC_AJAX_ENDPOINTS as $path) {
			$response = $ctx->post($path, []);
			assertSame(401, $response['status'], "POST {$path} while signed out");
			assertTrue(isset($response['json']['error']), "{$path} returns a json error");
		}
	},

	'a signed out caller is told to log in before anything else is judged' => function ($ctx) {
		$ctx->newSession();

		$wrongMethod = $ctx->get(MUSIC_AJAX_ENDPOINTS[0]);
		assertSame(401, $wrongMethod['status'], 'a GET while signed out is a 401, not a 405');

		$ctx->newSession();
		$badBody = $ctx->post(MUSIC_AJAX_ENDPOINTS[0], 'null');
		assertSame(401, $badBody['status'], 'a malformed body while signed out is a 401, not a 400');

		$ctx->newSession();
		$noCsrf = $ctx->postWithoutCsrf(MUSIC_AJAX_ENDPOINTS[0], []);
		assertSame(401, $noCsrf['status'], 'a missing token while signed out is a 401, not a 403');
	},

	'a signed in caller still gets the specific complaint' => function ($ctx) {
		$ctx->ensureLoggedIn();
		assertSame(405, $ctx->get(MUSIC_AJAX_ENDPOINTS[0])['status'], 'a GET is a 405 once you are known');

		$ctx->ensureLoggedIn();
		assertSame(403, $ctx->postWithoutCsrf(MUSIC_AJAX_ENDPOINTS[0], [])['status'], 'a missing token is a 403');

		$ctx->ensureLoggedIn();
		assertSame(400, $ctx->post(MUSIC_AJAX_ENDPOINTS[0], 'null')['status'], 'a malformed body is a 400');
	},

	'the music endpoints reject posts without a csrf token' => function ($ctx) {
		$ctx->ensureLoggedIn();

		foreach (MUSIC_AJAX_ENDPOINTS as $path) {
			$response = $ctx->postWithoutCsrf($path, []);
			assertSame(403, $response['status'], "POST {$path} without a token");
			assertTrue(isset($response['json']['error']), "{$path} returns a json error");
		}
	},

	'the music landing, login and register pages stay public' => function ($ctx) {
		$ctx->newSession();

		assertSame(200, $ctx->get('/music')['status'], 'GET /music');
		assertSame(200, $ctx->get('/music/login')['status'], 'GET /music/login');
		assertSame(200, $ctx->get('/music/register')['status'], 'GET /music/register');
	},

	'the music landing page links to login' => function ($ctx) {
		$ctx->newSession();

		assertContains('href="/music/login"', $ctx->get('/music')['body'], 'login link');
	},

	'unknown paths 404' => function ($ctx) {
		assertSame(404, $ctx->get('/no-such-page')['status'], 'GET /no-such-page');
	},

	'every ss2 level page serves' => function ($ctx) {
		foreach (range(11, 18) as $level) {
			assertSame(200, $ctx->get("/ss2/{$level}")['status'], "GET /ss2/{$level}");
		}
	},

	'uppercase urls redirect to lowercase' => function ($ctx) {
		$response = $ctx->get('/Contact');
		assertSame(301, $response['status'], 'GET /Contact status');
		assertContains('/contact', $response['location'], 'redirect target');
	},

	'trailing slashes are stripped' => function ($ctx) {
		$response = $ctx->get('/contact/');
		assertSame(301, $response['status'], 'GET /contact/ status');
		assertContains('/contact', $response['location'], 'redirect target');
	},

	'redirects keep the query string' => function ($ctx) {
		$response = $ctx->get('/Contact?foo=bar&baz=1');
		assertContains('foo=bar&baz=1', $response['location'], 'query string');
	},

	'redirects land somewhere real' => function ($ctx) {
		assertSame(200, $ctx->get('/Exchange-Rates/', true)['status'], 'following /Exchange-Rates/');
	},

	'pages render their own strings, not catalogue keys' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/add-songs')['body'];
		assertContains('Add Songs', $body, 'heading');
		assertContains('Provided Artist Name', $body, 'artist table header');
		assertContains('Submit Songs', $body, 'song submit button');
	},

	'pages carry no php warnings' => function ($ctx) {
		$ctx->ensureLoggedIn();

		foreach (['/', '/contact', '/exchange-rates', '/music/add-songs', '/music/songs', '/ss2/11'] as $path) {
			$body = $ctx->get($path)['body'];
			foreach (['Warning:', 'Notice:', 'Fatal error', 'Undefined variable'] as $sign) {
				assertTrue(strpos($body, $sign) === false, "{$path} contains '{$sign}'");
			}
		}
	},

	'static assets are served' => function ($ctx) {
		assertSame(200, $ctx->get('/js/util.js')['status'], 'shared util.js');
		assertSame(200, $ctx->get('/modules/music/css/styles.css')['status'], 'music styles');
		assertSame(200, $ctx->get('/modules/music/js/addSongs.js')['status'], 'music script');
	},

	'assets are referenced with a cache busting stamp that still serves' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		$patterns = [
			'the stylesheet' => '#href="(/modules/music/css/styles\.css\?v=\d+)"#',
			'the page script' => '#src="(/modules/music/js/songs\.js\?v=\d+)"#',
			'the shared script' => '#src="(/js/util\.js\?v=\d+)"#',
		];

		foreach ($patterns as $what => $pattern) {
			assertTrue(preg_match($pattern, $body, $match) === 1, "{$what} carries a version stamp");
			assertSame(200, $ctx->get($match[1])['status'], "{$what} still serves at its stamped url");
		}
	},

	'the html is never cached, so a new asset stamp is always seen' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$headers = $ctx->get('/music/songs')['headers'] ?? '';
		assertTrue(stripos($headers, 'no-cache') !== false, 'pages send a no-cache header, which is what makes the stamp reach the browser');
	},

];
