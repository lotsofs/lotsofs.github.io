<?php

const ARTIST_ENDPOINT = '/music/ajax/artist-alias';

return [

	'a new artist is created with its actual name' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(ARTIST_ENDPOINT, [[
			'artist_id' => 'new',
			'group' => 'Fresh Band',
			'og_name' => 'Fresh Band',
			'provided_name' => 'Fresh Band',
			'is_actual' => true,
		]]);

		assertSame('ok', $response['json'][0]['status'], 'status');
		$artistId = $response['json'][0]['artist_id'];

		$alias = $ctx->db()->query("SELECT name, is_actual FROM artist_alias WHERE artist_id = {$artistId}")->fetchAll();
		assertSame(1, count($alias), 'alias count');
		assertSame('Fresh Band', $alias[0]['name'], 'stored name');
		assertSame(1, (int)$alias[0]['is_actual'], 'is_actual');
	},

	'rows sharing a group become one artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(ARTIST_ENDPOINT, [
			['artist_id' => 'new', 'group' => 'Grouped', 'og_name' => 'Grouped', 'provided_name' => 'Grouped', 'is_actual' => true],
			['artist_id' => 'new', 'group' => 'Grouped', 'og_name' => 'Gruped', 'provided_name' => 'Gruped', 'is_actual' => false],
			['artist_id' => 'new', 'group' => 'Grouped', 'og_name' => 'The Grouped', 'provided_name' => 'The Grouped', 'is_actual' => false],
		]);

		$ids = array_unique(array_column($response['json'], 'artist_id'));
		assertSame(1, count($ids), 'all three rows share one artist id');

		$artistId = $ids[0];
		$aliases = $ctx->db()->query("SELECT name, is_actual FROM artist_alias WHERE artist_id = {$artistId}")->fetchAll();
		assertSame(3, count($aliases), 'alias count');

		$actual = array_filter($aliases, fn($a) => (int)$a['is_actual'] === 1);
		assertSame(1, count($actual), 'exactly one actual name');
		assertSame('Grouped', array_values($actual)[0]['name'], 'the actual name');
	},

	'a result message names the artist the row landed on' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Message Target');

		$joined = $ctx->post('/music/ajax/artist-alias', [[
			'artist_id' => $artistId,
			'og_name' => 'asdfasdf',
			'provided_name' => 'asdfasdf',
			'is_actual' => false,
		]]);

		assertContains('asdfasdf', $joined['json'][0]['message'], 'the message names the spelling');
		assertContains('Message Target', $joined['json'][0]['message'], 'and the artist it joined');

		$created = $ctx->post('/music/ajax/artist-alias', [[
			'artist_id' => 'new',
			'group' => 'Brand New Artist',
			'og_name' => 'Brand New Artist',
			'provided_name' => 'Brand New Artist',
			'is_actual' => true,
		]]);

		assertContains('Created artist', $created['json'][0]['message'], 'a new artist says so instead');
	},

	'declining to store a spelling still resolves the artist for the song step' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post('/music/ajax/artist-alias', [
			['artist_id' => 'new', 'group' => 'g', 'og_name' => 'g', 'provided_name' => 'g', 'is_actual' => true],
			['artist_id' => 'new', 'group' => 'g', 'og_name' => 'h', 'provided_name' => 'h', 'is_actual' => false],
			['artist_id' => 'new', 'group' => 'g', 'og_name' => 'i', 'provided_name' => 'i', 'is_actual' => false, 'store_name' => false],
		]);

		$artistId = (int)$response['json'][0]['artist_id'];

		assertSame($artistId, (int)$response['json'][2]['artist_id'], 'i resolved to the same artist');
		assertSame('duplicate', $response['json'][2]['status'], 'but nothing was created for it');

		$names = $ctx->db()->query("SELECT name FROM artist_alias WHERE artist_id = {$artistId} ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
		assertSame(['g', 'h'], $names, 'the declined spelling was not stored');
	},

	'declining to store a spelling for an existing artist works the same way' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Existing Target');

		$response = $ctx->post('/music/ajax/artist-alias', [[
			'artist_id' => $artistId,
			'og_name' => 'f',
			'provided_name' => 'f',
			'is_actual' => false,
			'store_name' => false,
		]]);

		assertSame($artistId, (int)$response['json'][0]['artist_id'], 'f resolved to the existing artist');
		assertContains('Existing Target', $response['json'][0]['message'], 'the message names it');

		$names = $ctx->db()->query("SELECT name FROM artist_alias WHERE artist_id = {$artistId}")->fetchAll(PDO::FETCH_COLUMN);
		assertSame(['Existing Target'], $names, 'f was not stored as an alias');
	},

	'a new artist is always named even if the row says not to store it' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post('/music/ajax/artist-alias', [[
			'artist_id' => 'new',
			'group' => 'Unnameable',
			'og_name' => 'Unnameable',
			'provided_name' => 'Unnameable',
			'is_actual' => true,
			'store_name' => false,
		]]);

		$artistId = (int)$response['json'][0]['artist_id'];
		$names = $ctx->db()->query("SELECT name FROM artist_alias WHERE artist_id = {$artistId}")->fetchAll(PDO::FETCH_COLUMN);
		assertSame(['Unnameable'], $names, 'a brand new artist cannot be left nameless');
	},

	'every result reports the artist name it resolved to' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post('/music/ajax/artist-alias', [
			['artist_id' => 'new', 'group' => 'gg', 'og_name' => 'gg', 'provided_name' => 'gg', 'is_actual' => true],
			['artist_id' => 'new', 'group' => 'gg', 'og_name' => 'hh', 'provided_name' => 'hh', 'is_actual' => false],
			['artist_id' => 'new', 'group' => 'gg', 'og_name' => 'ii', 'provided_name' => 'ii', 'is_actual' => false, 'store_name' => false],
		]);

		foreach ($response['json'] as $result) {
			assertSame('gg', $result['artist_name'], "{$result['provided_name']} reports the artist it joined");
		}
	},

	'result and preview strings all carry an icon' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$catalogue = $ctx->get('/music/add-songs')['body'];

		foreach (['Created artist', 'as an alias of', 'spelling not stored', 'Joins new artist', 'Skipped'] as $phrase) {
			assertContains($phrase, $catalogue, "the catalogue carries {$phrase}");
		}

		assertTrue(strpos($catalogue, 'RESULT_ICONS') === false, 'no icon map is shipped to the page');
	},

	'an alias can be added to an existing artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Existing Band');

		$response = $ctx->post(ARTIST_ENDPOINT, [[
			'artist_id' => $artistId,
			'og_name' => 'Existing Band, The',
			'provided_name' => 'Existing Band, The',
			'is_actual' => false,
		]]);

		assertSame('ok', $response['json'][0]['status'], 'status');
		assertSame($artistId, $response['json'][0]['artist_id'], 'attached to the same artist');

		$count = $ctx->db()->query("SELECT COUNT(*) c FROM artist_alias WHERE artist_id = {$artistId}")->fetch()['c'];
		assertSame(2, (int)$count, 'artist now has two aliases');
	},

	'resubmitting an alias reports a duplicate' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Twice Band');

		$response = $ctx->post(ARTIST_ENDPOINT, [[
			'artist_id' => $artistId,
			'og_name' => 'Twice Band',
			'provided_name' => 'Twice Band',
			'is_actual' => false,
		]]);

		assertSame('duplicate', $response['json'][0]['status'], 'status');

		$count = $ctx->db()->query("SELECT COUNT(*) c FROM artist_alias WHERE artist_id = {$artistId}")->fetch()['c'];
		assertSame(1, (int)$count, 'no second row was written');
	},

	'a custom name can keep the pasted spelling too' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(ARTIST_ENDPOINT, [[
			'artist_id' => 'new',
			'group' => 'Fooo Bar',
			'og_name' => 'Foo Bar',
			'provided_name' => 'Fooo Bar',
			'is_actual' => true,
			'also_alias_provided_name' => true,
		]]);

		$artistId = $response['json'][0]['artist_id'];
		$aliases = $ctx->db()->query("SELECT name, is_actual FROM artist_alias WHERE artist_id = {$artistId} ORDER BY is_actual DESC")->fetchAll();

		assertSame(2, count($aliases), 'both names stored');
		assertSame('Foo Bar', $aliases[0]['name'], 'typed name is the actual one');
		assertSame('Fooo Bar', $aliases[1]['name'], 'pasted name kept as an alias');
		assertSame(0, (int)$aliases[1]['is_actual'], 'pasted name is not actual');
	},

	'the pasted spelling is dropped when not asked for' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(ARTIST_ENDPOINT, [[
			'artist_id' => 'new',
			'group' => 'Wrong Name',
			'og_name' => 'Right Name',
			'provided_name' => 'Wrong Name',
			'is_actual' => true,
		]]);

		$artistId = $response['json'][0]['artist_id'];
		$aliases = $ctx->db()->query("SELECT name FROM artist_alias WHERE artist_id = {$artistId}")->fetchAll();

		assertSame(1, count($aliases), 'only the typed name stored');
		assertSame('Right Name', $aliases[0]['name'], 'stored name');
	},

	'only one alias per artist stays actual' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Shifting Band');

		$ctx->post(ARTIST_ENDPOINT, [[
			'artist_id' => $artistId,
			'og_name' => 'Shifting Band Renamed',
			'provided_name' => 'Shifting Band Renamed',
			'is_actual' => true,
		]]);

		$actual = $ctx->db()->query("SELECT name FROM artist_alias WHERE artist_id = {$artistId} AND is_actual = 1")->fetchAll();
		assertSame(1, count($actual), 'exactly one actual name remains');
		assertSame('Shifting Band Renamed', $actual[0]['name'], 'the newer name won');
	},

	'a nameless row is rejected' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(ARTIST_ENDPOINT, [[
			'artist_id' => 'new',
			'og_name' => '',
			'provided_name' => '',
			'is_actual' => true,
		]]);

		assertSame('error', $response['json'][0]['status'], 'status');
	},

	'punctuation in names survives the round trip' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$names = ["Guns N' Roses", 'Simon & Garfunkel', 'AC/DC', 'Sigur Rós', '"Weird Al" Yankovic'];

		foreach ($names as $name) {
			$artistId = $ctx->makeArtist($name);
			$stored = $ctx->db()->query("SELECT name FROM artist_alias WHERE artist_id = {$artistId}")->fetch()['name'];
			assertSame($name, $stored, "stored form of {$name}");
		}
	},

];
