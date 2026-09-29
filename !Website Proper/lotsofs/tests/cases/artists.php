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

	'an artist with no actual spelling falls back to the one spelling it has' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(ARTIST_ENDPOINT, [[
			'artist_id' => 'new',
			'group' => 'Unmarked Band',
			'og_name' => 'Unmarked Band',
			'provided_name' => 'Unmarked Band',
			'is_actual' => false,
		]]);
		$artistId = (int)$response['json'][0]['artist_id'];

		$actualCount = (int)$ctx->db()->query("SELECT COUNT(*) c FROM artist_alias WHERE artist_id = {$artistId} AND is_actual = 1")->fetch()['c'];
		assertSame(0, $actualCount, 'the artist really has no alias marked actual');

		$body = $ctx->get('/music/artists')['body'];

		assertTrue(preg_match('#<a href="/music/songs\?artist=' . $artistId . '"[^>]*>Unmarked Band</a>#', $body) === 1, 'the name cell falls back to the only spelling there is');
		assertSame(1, substr_count($body, 'Unmarked Band'), 'the fallback name is not also repeated in its own alias column');
	},

	'an album with no actual spelling falls back the same way' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Unmarked Album Owner');

		$response = $ctx->post('/music/ajax/album', [[
			'provided_name' => 'Unmarked Record',
			'album_id' => 'new',
			'og_name' => 'Unmarked Record',
			'is_actual' => false,
			'artist_id' => $artistId,
			'release_year' => '',
			'tracks' => [],
		]]);
		$albumId = (int)$response['json'][0]['album_id'];

		$actualCount = (int)$ctx->db()->query("SELECT COUNT(*) c FROM album_alias WHERE album_id = {$albumId} AND is_actual = 1")->fetch()['c'];
		assertSame(0, $actualCount, 'the album really has no alias marked actual');

		$body = $ctx->get('/music/albums')['body'];

		assertContains('>Unmarked Record</a>', $body, 'the name cell falls back to the only spelling there is');
		assertSame(1, substr_count($body, 'Unmarked Record'), 'the fallback name is not also repeated in its own alias column');
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

	'the artist list counts a catalogue and reports its statistics' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Catalogued Band');

		$tracks = [];
		foreach (['Catalogued One', 'Catalogued Two', 'Catalogued Three'] as $index => $title) {
			$tracks[] = ['song_id' => makeSong($ctx, $artistId, $title), 'position' => $index + 1];
		}
		makeAlbum($ctx, 'Catalogued Record', $artistId, $tracks);

		foreach (['Catalogued One' => '8', 'Catalogued Two' => '4', 'Catalogued Three' => '8'] as $title => $score) {
			$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId($title), 'field' => 'score', 'value' => $score]);
		}

		$row = listRowFor($ctx->get('/music/artists')['body'], 'data-artist-card-id', $artistId);
		assertTrue($row !== null, 'the artist has a row');

		assertSame(['3', '1'], listCells($row, 'listCountCell'), 'three songs across one album');

		// 8, 4 and 8: mean 6.67, sigma 1.89, median 8, mode 8.
		assertSame(
			['6.67', '1.89', '8', '8', '8', '4'],
			listCells($row, 'albumStatsScoreCell'),
			'average, deviation, median, mode, highest and lowest over every score on the artist'
		);

		$accounts = (int)$ctx->db()->query("SELECT COUNT(*) AS n FROM account")->fetch()['n'];
		assertSame('3 / ' . (3 * $accounts), listCells($row, 'albumStatsRatedCell')[0], 'out of every song for every account');

		assertContains('style="color: hsl(', $row, 'and the scores are coloured on the ramp the cards use');
	},

	'an artist nobody has rated still gets every column' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Unlistened Band');

		$row = listRowFor($ctx->get('/music/artists')['body'], 'data-artist-card-id', $artistId);

		assertSame(['0', '0'], listCells($row, 'listCountCell'), 'no songs and no albums');
		assertSame(6, count(listCells($row, 'albumStatsScoreCell')), 'the statistics columns are still there');
		assertClasses(['albumStatsEmpty'], $row, '/<td class="([^"]*albumStatsEmpty[^"]*)"/', 'reading as empty rather than as zero');
		assertSame('0 / 0', listCells($row, 'albumStatsRatedCell')[0], 'with nothing rated out of nothing');
	},

	'the artist list sorts by a statistic, with unrated artists last either way' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$loud = $ctx->makeArtist('Sorted Loud Band');
		$quiet = $ctx->makeArtist('Sorted Quiet Band');
		$silent = $ctx->makeArtist('Sorted Silent Band');

		foreach ([[$loud, 'Sorted Loud Song', '9'], [$quiet, 'Sorted Quiet Song', '3']] as [$artistId, $title, $score]) {
			$songId = makeSong($ctx, $artistId, $title);
			$ctx->post('/music/ajax/song-rating', ['id' => $songId, 'field' => 'score', 'value' => $score]);
		}
		makeSong($ctx, $silent, 'Sorted Silent Song');

		$mine = [$loud, $quiet, $silent];

		assertSame(
			[$loud, $quiet, $silent],
			listOrderOf($ctx->get('/music/artists?sort=average&dir=desc')['body'], 'data-artist-card-id', $mine),
			'the highest average first'
		);

		assertSame(
			[$quiet, $loud, $silent],
			listOrderOf($ctx->get('/music/artists?sort=average&dir=asc')['body'], 'data-artist-card-id', $mine),
			'and the lowest first, with the unrated artist still last rather than leading'
		);
	},

	'a sorted column heads the list with the way to reverse it' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = preg_replace('/\s+</', '<', $ctx->get('/music/artists?sort=songs&dir=desc')['body']);

		assertContains('<th class="listCountCell" data-sort-key="songs"><a href="?sort=songs&amp;dir=asc"', $body, 'the column in use links back the other way');
		assertContains('▼', $body, 'and carries the direction it is in');
		assertContains('<a href="?sort=name&amp;dir=asc"', $body, 'while a column not in use starts ascending');
	},

];
