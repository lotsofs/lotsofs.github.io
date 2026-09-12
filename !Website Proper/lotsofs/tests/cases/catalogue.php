<?php

return [

	'the artists page lists an artist with its aliases' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Listed Band');

		$ctx->post('/music/ajax/artist-alias', [
			['artist_id' => $artistId, 'og_name' => 'Listed Bnad', 'provided_name' => 'Listed Bnad', 'is_actual' => false],
			['artist_id' => $artistId, 'og_name' => 'The Listed Band', 'provided_name' => 'The Listed Band', 'is_actual' => false],
		]);

		$body = $ctx->get('/music/artists')['body'];

		assertContains('Listed Band', $body, 'the actual name');
		assertContains('Listed Bnad', $body, 'the misspelling');
		assertContains('The Listed Band', $body, 'the other alias');
		assertContains(">{$artistId}<", $body, 'the id');
	},

	'the artists page keeps the actual name out of the alias column' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Solo Act');

		preg_match('/<td class="listIdCell">' . $artistId . '<\/td><td class="listNameCell"><a[^>]*>([^<]*)<\/a><\/td><td class="listAliasCell">([^<]*)<\/td>/',
			preg_replace('/\s+</', '<', $ctx->get('/music/artists')['body']), $m);

		assertSame('Solo Act', $m[1] ?? '', 'the name column holds the actual name');
		assertSame('', $m[2] ?? 'missing', 'the alias column is empty when there are none');
	},

	'the albums page lists an album with its artist, year and aliases' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Album Page Band');
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Album Page Song']]);
		$songId = $ctx->songId('Album Page Song');

		$created = $ctx->post('/music/ajax/album', [[
			'provided_name' => 'The Listed Record',
			'album_id' => 'new',
			'og_name' => 'The Listed Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '1977',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]]);
		$albumId = (int)$created['json'][0]['album_id'];

		$ctx->post('/music/ajax/album', [[
			'provided_name' => 'Self Titled',
			'album_id' => $albumId,
			'og_name' => 'Self Titled',
			'is_actual' => false,
			'artist_id' => $artistId,
			'release_year' => '1977',
			'tracks' => [],
		]]);

		$body = $ctx->get('/music/albums')['body'];

		assertContains('The Listed Record', $body, 'the actual name');
		assertContains('Self Titled', $body, 'the alias');
		assertContains('Album Page Band', $body, 'the attributed artist');
		assertContains('1977', $body, 'the release year');

		assertTrue(strpos($body, 'Album Page Song') === false, 'no track listing');
	},

	'an album with no artist reads as various artists' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$ctx->post('/music/ajax/album', [[
			'provided_name' => 'Ownerless Compilation',
			'album_id' => 'new',
			'og_name' => 'Ownerless Compilation',
			'is_actual' => true,
			'artist_id' => null,
			'release_year' => '',
			'tracks' => [],
		]]);

		$body = $ctx->get('/music/albums')['body'];

		assertContains('Ownerless Compilation', $body, 'the album is listed');
		assertContains('various artists', $body, 'and reads as a compilation');
	},

	'both pages are hidden from signed out visitors' => function ($ctx) {
		$ctx->newSession();

		foreach (['/music/artists', '/music/albums'] as $path) {
			$response = $ctx->get($path);
			assertSame(302, $response['status'], "GET {$path} while signed out");
			assertContains('/music/login', $response['location'], "{$path} redirect target");
		}
	},

	'both pages are open to a non admin' => function ($ctx) {
		$ctx->ensureLoggedIn('catalogue_viewer', 'test password', false);

		foreach (['/music/artists', '/music/albums'] as $path) {
			assertSame(200, $ctx->get($path)['status'], "GET {$path} as a non admin");
		}
	},

	'the nav links to both pages' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		assertContains('href="/music/artists"', $body, 'artists link');
		assertContains('href="/music/albums"', $body, 'albums link');
	},

	'both pages escape stored markup and carry no php warnings' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$ctx->makeArtist('<b>not bold</b>');

		foreach (['/music/artists', '/music/albums'] as $path) {
			$body = $ctx->get($path)['body'];

			foreach (['Warning:', 'Notice:', 'Fatal error', 'Undefined variable', 'Undefined index'] as $sign) {
				assertTrue(strpos($body, $sign) === false, "{$path} contains '{$sign}'");
			}
		}

		$artists = $ctx->get('/music/artists')['body'];
		assertContains('&lt;b&gt;not bold&lt;/b&gt;', $artists, 'markup is escaped');
		assertTrue(strpos($artists, '<b>not bold</b>') === false, 'raw markup is absent');
	},

	'the paste box has a usable default size' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$css = $ctx->get('/modules/music/css/styles.css')['body'];

		assertContains('#pasteInput', $css, 'the paste box is sized');
		assertContains('min-height', $css, 'with a height rather than the browser default');
	},

];
