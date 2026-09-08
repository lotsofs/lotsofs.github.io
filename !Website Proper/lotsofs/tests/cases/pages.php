<?php

return [

	'every registered route serves' => function ($ctx) {
		$expected = [
			'/' => 200,
			'/contact' => 200,
			'/exchange-rates' => 200,
			'/ktane' => 302,
			'/music/add-songs' => 200,
			'/swat4/2' => 200,
			'/ss2/11' => 200,
			'/ss2/18' => 200,
		];
		foreach ($expected as $path => $status) {
			assertSame($status, $ctx->get($path)['status'], "GET {$path}");
		}
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
		$body = $ctx->get('/music/add-songs')['body'];
		assertContains('Add Songs', $body, 'heading');
		assertContains('Provided Artist Name', $body, 'artist table header');
		assertContains('Submit Songs', $body, 'song submit button');
	},

	'pages carry no php warnings' => function ($ctx) {
		foreach (['/', '/contact', '/exchange-rates', '/music/add-songs', '/ss2/11'] as $path) {
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

];
