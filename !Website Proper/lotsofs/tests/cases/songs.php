<?php

const SONG_ENDPOINT = '/modules/music/ajax/song.php';
const EDIT_ENDPOINT = '/modules/music/ajax/songEdit.php';
const RATING_ENDPOINT = '/modules/music/ajax/songRating.php';

return [

	'a song is stored against its artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Song Owner');

		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'First Track']]);
		assertSame('ok', $response['json'][0]['status'], 'status');

		$songId = $ctx->songId('First Track');
		assertTrue($songId > 0, 'the song exists');

		$song = $ctx->db()->query("SELECT artist_id FROM song WHERE id = {$songId}")->fetch();
		assertSame($artistId, (int)$song['artist_id'], 'attached to the right artist');
		assertSame('First Track', $ctx->songTitle($songId), 'its actual name is the pasted title');
	},

	'the added message names the artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Named In Message');

		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Some Song']]);
		assertContains('Some Song', $response['json'][0]['message'], 'message names the song');
		assertContains('Named In Message', $response['json'][0]['message'], 'message names the artist');
	},

	'resubmitting a song reports a duplicate' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Repeat Owner');
		$payload = [['artist_id' => $artistId, 'title' => 'Repeated Track']];

		$ctx->post(SONG_ENDPOINT, $payload);
		$response = $ctx->post(SONG_ENDPOINT, $payload);

		assertSame('duplicate', $response['json'][0]['status'], 'status');

		$count = $ctx->songCount('Repeated Track');
		assertSame(1, (int)$count, 'only one row exists');
	},

	'the same title can belong to two artists' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$first = $ctx->makeArtist('Coverer One');
		$second = $ctx->makeArtist('Coverer Two');

		$ctx->post(SONG_ENDPOINT, [['artist_id' => $first, 'title' => 'Shared Title']]);
		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $second, 'title' => 'Shared Title']]);

		assertSame('ok', $response['json'][0]['status'], 'second artist may reuse the title');
	},

	'a titleless row is rejected' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('No Title Owner');

		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => '']]);
		assertSame('error', $response['json'][0]['status'], 'status');
	},

	'punctuation in titles survives the round trip' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Punctuation Owner');
		$titles = ["Ain't Talkin' 'Bout Love", 'Salt & Pepper', '<script>alert(1)</script>', 'Voilà'];

		foreach ($titles as $title) {
			$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => $title]]);
		}

		$stored = $ctx->db()->query("
			SELECT sa.name
			FROM song s JOIN song_alias sa ON sa.song_id = s.id
			WHERE s.artist_id = {$artistId}
		")->fetchAll(PDO::FETCH_COLUMN);
		foreach ($titles as $title) {
			assertTrue(in_array($title, $stored, true), "stored form of {$title}");
		}
	},

	'a title can be filed as an alias of an existing song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Song Alias Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Bohemian Rhapsody']]);
		$songId = $ctx->songId('Bohemian Rhapsody');

		$response = $ctx->post(SONG_ENDPOINT, [[
			'artist_id' => $artistId,
			'title' => 'Bohemian Rapsody',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);

		assertSame('ok', $response['json'][0]['status'], 'status');
		assertSame($songId, (int)$response['json'][0]['song_id'], 'points at the same song');

		assertSame($songId, $ctx->songId('Bohemian Rapsody'), 'the misspelling resolves to it too');
		assertSame('Bohemian Rhapsody', $ctx->songTitle($songId), 'the actual name is unchanged');

		$names = $ctx->db()->query("SELECT name FROM song_alias WHERE song_id = {$songId} ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
		assertSame(['Bohemian Rapsody', 'Bohemian Rhapsody'], $names, 'both spellings hang off one song');

		$songCount = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song WHERE artist_id = {$artistId}")->fetch()['c'];
		assertSame(1, $songCount, 'no second song was created');
	},

	'unticking the alias box matches the song without storing the spelling' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Untick Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Kept Clean']]);
		$songId = $ctx->songId('Kept Clean');

		$response = $ctx->post(SONG_ENDPOINT, [[
			'artist_id' => $artistId,
			'title' => 'Kept Clean.ogg',
			'song_id' => $songId,
			'also_alias_provided_name' => false,
		]]);

		assertSame('duplicate', $response['json'][0]['status'], 'the row is resolved, not created');

		// the album step still needs to know which song the row landed on
		assertSame($songId, (int)$response['json'][0]['song_id'], 'the song id still comes back');

		assertSame(0, $ctx->songId('Kept Clean.ogg'), 'the spelling was not stored');

		$names = $ctx->db()->query("SELECT name FROM song_alias WHERE song_id = {$songId}")->fetchAll(PDO::FETCH_COLUMN);
		assertSame(['Kept Clean'], $names, 'the song keeps only its own name');
	},

	'ticking the alias box does store the spelling' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Tick Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Gains A Spelling']]);
		$songId = $ctx->songId('Gains A Spelling');

		$response = $ctx->post(SONG_ENDPOINT, [[
			'artist_id' => $artistId,
			'title' => 'Gains A Spelling.ogg',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);

		assertSame('ok', $response['json'][0]['status'], 'status');
		assertSame($songId, $ctx->songId('Gains A Spelling.ogg'), 'the spelling resolves to the song');
	},

	'aliasing the same spelling twice is a duplicate' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Repeat Alias Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Original Spelling']]);
		$songId = $ctx->songId('Original Spelling');

		$payload = [['artist_id' => $artistId, 'title' => 'Other Spelling', 'song_id' => $songId, 'also_alias_provided_name' => true]];
		$ctx->post(SONG_ENDPOINT, $payload);
		$response = $ctx->post(SONG_ENDPOINT, $payload);

		assertSame('duplicate', $response['json'][0]['status'], 'status');

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song_alias WHERE song_id = {$songId}")->fetch()['c'];
		assertSame(2, $count, 'still just the two names');
	},

	'a song cannot be aliased onto another artists song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$mine = $ctx->makeArtist('Alias Scope Mine');
		$theirs = $ctx->makeArtist('Alias Scope Theirs');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $theirs, 'title' => 'Their Song']]);
		$theirSongId = $ctx->songId('Their Song');

		$response = $ctx->post(SONG_ENDPOINT, [[
			'artist_id' => $mine,
			'title' => 'My Cover',
			'song_id' => $theirSongId,
			'also_alias_provided_name' => true,
		]]);

		assertSame('error', $response['json'][0]['status'], 'refused');
		assertSame(0, $ctx->songId('My Cover'), 'nothing was stored');
	},

	'a custom name stores the pasted spelling alongside it' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Custom Title Owner');

		$response = $ctx->post(SONG_ENDPOINT, [[
			'artist_id' => $artistId,
			'title' => 'bohemian rhapsody (remaster)',
			'song_id' => 'custom',
			'og_name' => 'Bohemian Rhapsody',
			'also_alias_provided_name' => true,
		]]);

		assertSame('ok', $response['json'][0]['status'], 'status');
		$songId = (int)$response['json'][0]['song_id'];

		assertSame('Bohemian Rhapsody', $ctx->songTitle($songId), 'the typed name became the actual one');
		assertSame($songId, $ctx->songId('bohemian rhapsody (remaster)'), 'the pasted spelling is an alias');
	},

	'every result carries the pasted spelling so the page can match its row' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Reporting Owner');
		$existingId = 0;
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Already Here']]);
		$existingId = $ctx->songId('Already Here');

		// one of each outcome, all pasted under names that differ from what gets stored
		$response = $ctx->post(SONG_ENDPOINT, [
			['artist_id' => $artistId, 'title' => 'Plain New.ogg'],
			['artist_id' => $artistId, 'title' => 'Custom Source.ogg', 'song_id' => 'custom', 'og_name' => 'Custom Stored'],
			['artist_id' => $artistId, 'title' => 'Already Here'],
			['artist_id' => $artistId, 'title' => 'Aliased Onto.ogg', 'song_id' => $existingId, 'also_alias_provided_name' => true],
			['artist_id' => $artistId, 'title' => 'Skipped One.ogg', 'song_id' => 'skip'],
		]);

		$pasted = ['Plain New.ogg', 'Custom Source.ogg', 'Already Here', 'Aliased Onto.ogg', 'Skipped One.ogg'];
		assertSame($pasted, array_column($response['json'], 'provided_name'), 'every row reports under the name it was pasted as');

		assertSame(['ok', 'ok', 'duplicate', 'ok', 'skipped'], array_column($response['json'], 'status'), 'each outcome');

		foreach ($response['json'] as $result) {
			assertTrue(($result['message'] ?? '') !== '', "a message for {$result['provided_name']}");
		}

		// the custom row is the one that used to go unreported, because its stored title differs
		$custom = $response['json'][1];
		assertSame('Custom Source.ogg', $custom['provided_name'], 'reported under the pasted name');
		assertSame('Custom Stored', $custom['title'], 'while the stored title is the typed one');
	},

	'a skipped song stores nothing' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Skipped Song Owner');

		$response = $ctx->post(SONG_ENDPOINT, [[
			'artist_id' => $artistId,
			'title' => 'Never Added',
			'song_id' => 'skip',
		]]);

		assertSame('skipped', $response['json'][0]['status'], 'status');
		assertSame(0, $ctx->songId('Never Added'), 'nothing was stored');
	},

	'an artist can still not hold two songs with the same title' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('No Dupes Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Only Once']]);
		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Only Once']]);

		assertSame('duplicate', $response['json'][0]['status'], 'the second attempt is refused');

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song WHERE artist_id = {$artistId}")->fetch()['c'];
		assertSame(1, $count, 'the check that replaced idx_song_unique still holds');
	},

	'the artist endpoint hands back that artists songs for the dropdown' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Dropdown Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Dropdown Song One']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Dropdown Song Two']]);

		$response = $ctx->post('/modules/music/ajax/artistAlias.php', [[
			'artist_id' => $artistId,
			'og_name' => 'Dropdown Owner',
			'provided_name' => 'Dropdown Owner',
		]]);

		$songs = $response['json'][0]['songs'];
		assertSame(2, count($songs), 'both songs came back');
		assertSame(['Dropdown Song One', 'Dropdown Song Two'], array_column($songs, 'name'), 'named and ordered');
	},

	'the dropdown data carries every alias so a pasted spelling can be matched' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Rematch Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'A Functioning God']]);
		$songId = $ctx->songId('A Functioning God');

		// the shape of a second paste of the same file listing
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'A Functioning God.ogg', 'song_id' => $songId, 'also_alias_provided_name' => true]]);

		$response = $ctx->post('/modules/music/ajax/artistAlias.php', [[
			'artist_id' => $artistId,
			'og_name' => 'Rematch Owner',
			'provided_name' => 'Rematch Owner',
		]]);

		$songs = $response['json'][0]['songs'];
		assertSame(2, count($songs), 'both spellings come back, not just the actual name');

		$byName = [];
		foreach ($songs as $song) {
			$byName[$song['name']] = $song;
		}

		assertSame($songId, (int)$byName['A Functioning God.ogg']['id'], 'the pasted spelling points at the song');
		assertSame(0, (int)$byName['A Functioning God.ogg']['is_actual'], 'and is marked as the alias');
		assertSame(1, (int)$byName['A Functioning God']['is_actual'], 'while the clean name stays actual');
	},

	'a database error comes back as json, not html' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => 999999, 'title' => 'Orphan Track']]);

		assertSame(500, $response['status'], 'status');
		assertTrue(is_array($response['json']), 'body parses as json');
		assertTrue(isset($response['json']['error']), 'body carries an error key');
		assertTrue(strpos($response['body'], '<pre>') === false, 'body contains no html');
	},

	'endpoints reject non post requests' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->get(SONG_ENDPOINT);

		assertSame(405, $response['status'], 'status');
		assertTrue(isset($response['json']['error']), 'body carries an error key');
	},

	'endpoints reject bodies that are not arrays' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(SONG_ENDPOINT, 'null');

		assertSame(400, $response['status'], 'status');
		assertTrue(isset($response['json']['error']), 'body carries an error key');
	},

	'the songs page lists a song with its id and artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Listed Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Listed Track']]);

		$songId = $ctx->songId('Listed Track');

		$body = $ctx->get('/music/songs')['body'];
		assertContains('Listed Track', $body, 'song title');
		assertContains('Listed Owner', $body, 'artist name');
		assertContains(">{$songId}<", $body, 'song id');
	},

	'the songs page shows its column headings' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		foreach (['All Songs', 'ID', 'Artist', 'Title', 'Note', 'test_runner', 'Result'] as $heading) {
			assertContains($heading, $body, "heading {$heading}");
		}
	},

	'the result column starts hidden and is not sortable' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		assertContains('<table id="songListTable" class="hideResultColumn" data-can-edit="1">', $body, 'table starts with the column hidden');
		assertContains('<th rowspan="2" class="songResultCell">', $body, 'result header carries no sort attributes');
		assertContains('<td class="songResultCell">', $body, 'rows carry a result cell');
		assertTrue(strpos($body, 'sort=result') === false, 'nothing links to sorting by result');
	},

	'the songs page escapes stored markup' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Escaping Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => '<b>not bold</b>']]);

		$body = $ctx->get('/music/songs')['body'];
		assertContains('&lt;b&gt;not bold&lt;/b&gt;', $body, 'markup is escaped');
		assertTrue(strpos($body, '<b>not bold</b>') === false, 'raw markup is absent');
	},

	'the songs page sorts by the requested column' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Sorting Owner');
		$mine = ['Zzz Sortable', 'Aaa Sortable', 'Mmm Sortable'];
		foreach ($mine as $title) {
			$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => $title]]);
		}

		$titlesInOrder = function ($path) use ($ctx, $mine) {
			preg_match_all('/<td class="songTitleCell">([^<]*)<\/td>/', $ctx->get($path)['body'], $m);
			return array_values(array_filter($m[1], fn($t) => in_array($t, $mine, true)));
		};

		$ascending = $titlesInOrder('/music/songs?sort=title&dir=asc');
		assertSame(['Aaa Sortable', 'Mmm Sortable', 'Zzz Sortable'], $ascending, 'ascending by title');

		$descending = $titlesInOrder('/music/songs?sort=title&dir=desc');
		assertSame(['Zzz Sortable', 'Mmm Sortable', 'Aaa Sortable'], $descending, 'descending by title');
	},

	'the songs page defaults to id order' => function ($ctx) {
		$ctx->ensureLoggedIn();

		preg_match_all('/<td class="songIdCell">([0-9]+)<\/td>/', $ctx->get('/music/songs')['body'], $m);
		$ids = array_map('intval', $m[1]);

		$sorted = $ids;
		sort($sorted);
		assertSame($sorted, $ids, 'rows come back in id order');
	},

	'an unknown sort column falls back to id' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->get('/music/songs?sort=nonsense&dir=sideways');
		assertSame(200, $response['status'], 'page still renders');

		preg_match_all('/<td class="songIdCell">([0-9]+)<\/td>/', $response['body'], $m);
		$ids = array_map('intval', $m[1]);
		$sorted = $ids;
		sort($sorted);
		assertSame($sorted, $ids, 'fell back to id order');
	},

	'an array shaped sort value falls back to id' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->get('/music/songs?sort[]=id&dir[]=desc');
		assertSame(200, $response['status'], 'page still renders');
		assertTrue(strpos($response['body'], 'TypeError') === false, 'no type error leaked');

		preg_match_all('/<td class="songIdCell">([0-9]+)<\/td>/', $response['body'], $m);
		$ids = array_map('intval', $m[1]);
		$sorted = $ids;
		sort($sorted);
		assertSame($sorted, $ids, 'fell back to id order');
	},

	'a sort column cannot inject sql' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$before = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song")->fetch()['c'];
		assertTrue($before > 0, 'there are songs to lose');

		$response = $ctx->get('/music/songs?' . http_build_query(['sort' => 'id; DROP TABLE song', 'dir' => 'asc']));
		assertSame(200, $response['status'], 'page still renders');

		$after = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song")->fetch()['c'];
		assertSame($before, $after, 'the song table survived');
	},

	'the sorted column shows an indicator and offers the reverse' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs?sort=title&dir=asc')['body'];

		assertContains('▲', $body, 'ascending indicator');
		assertContains('?sort=title&amp;dir=desc', $body, 'active column links to the reverse');
		assertContains('?sort=artist&amp;dir=asc', $body, 'other columns link to ascending');
	},

	'a title can be renamed' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Rename Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Before Rename']]);
		$songId = $ctx->songId('Before Rename');

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'After Rename']);
		assertSame('ok', $response['json']['status'], 'status');
		assertSame('After Rename', $response['json']['value'], 'echoed title');

		$stored = $ctx->songTitle($songId);
		assertSame('After Rename', $stored, 'stored title');
	},

	'a rename trims surrounding whitespace' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Trim Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Untrimmed']]);
		$songId = $ctx->songId('Untrimmed');

		$ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'   Trimmed   ']);

		$stored = $ctx->songTitle($songId);
		assertSame('Trimmed', $stored, 'stored title');
	},

	'a rename to an empty title is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Empty Rename Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Keeps Its Name']]);
		$songId = $ctx->songId('Keeps Its Name');

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'   ']);
		assertSame('error', $response['json']['status'], 'status');
		assertSame('Keeps Its Name', $response['json']['value'], 'hands back the unchanged title');

		$stored = $ctx->songTitle($songId);
		assertSame('Keeps Its Name', $stored, 'stored title is untouched');
	},

	'a rename onto the same artists other song is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Clash Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Clash One']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Clash Two']]);
		$songId = $ctx->songId('Clash Two');

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'Clash One']);
		assertSame('duplicate', $response['json']['status'], 'status');
		assertSame('Clash Two', $response['json']['value'], 'hands back the unchanged title');

		$stored = $ctx->songTitle($songId);
		assertSame('Clash Two', $stored, 'stored title is untouched');
	},

	'a rename may match another artists song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$first = $ctx->makeArtist('Rename Coverer One');
		$second = $ctx->makeArtist('Rename Coverer Two');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $first, 'title' => 'Covered Later']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $second, 'title' => 'Not Yet Covered']]);
		$songId = $ctx->songId('Not Yet Covered');

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'Covered Later']);
		assertSame('ok', $response['json']['status'], 'status');
	},

	'renaming a song to its own title is allowed' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Idempotent Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Unchanged Title']]);
		$songId = $ctx->songId('Unchanged Title');

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'Unchanged Title']);
		assertSame('ok', $response['json']['status'], 'status');
	},

	'renaming an unknown song is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => 999999, 'field' => 'title', 'value' => 'Ghost Title']);
		assertSame('error', $response['json']['status'], 'status');

		$count = $ctx->songCount('Ghost Title');
		assertSame(0, $count, 'nothing was created');
	},

	'a rename id that is not a number is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		foreach (['abc', ['1'], null] as $id) {
			$response = $ctx->post(EDIT_ENDPOINT, ['id' => $id, 'field' => 'title', 'value' => 'Bad Id Title']);
			assertSame(200, $response['status'], 'endpoint still answers');
			assertSame('error', $response['json']['status'], 'status');
		}
	},

	'an unknown field is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bad Field Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Untouched By Bad Field']]);
		$songId = $ctx->songId('Untouched By Bad Field');

		foreach (['artist_id', 'id', '', ['title']] as $field) {
			$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => $field, 'value' => 'nope']);
			assertSame(400, $response['status'], 'status');
			assertTrue(isset($response['json']['error']), 'body carries an error key');
		}

		$stored = $ctx->songTitle($songId);
		assertSame('Untouched By Bad Field', $stored, 'the row is untouched');
	},

	'a note can be set and changed' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Note Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Gets A Note']]);
		$songId = $ctx->songId('Gets A Note');

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'first thoughts']);
		assertSame('ok', $response['json']['status'], 'status');
		assertSame('first thoughts', $response['json']['value'], 'echoed note');

		$ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'second thoughts']);

		$stored = $ctx->db()->query("SELECT objective_note FROM song WHERE id = {$songId}")->fetch()['objective_note'];
		assertSame('second thoughts', $stored, 'stored note');
	},

	'an emptied note is stored as null' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Cleared Note Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Loses Its Note']]);
		$songId = $ctx->songId('Loses Its Note');

		$ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'to be removed']);
		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => '   ']);
		assertSame('ok', $response['json']['status'], 'status');

		$stored = $ctx->db()->query("SELECT objective_note FROM song WHERE id = {$songId}")->fetch()['objective_note'];
		assertSame(null, $stored, 'stored as null rather than an empty string');
	},

	'two songs may share a note' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Shared Note Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Shares A Note One']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Shares A Note Two']]);

		foreach (['Shares A Note One', 'Shares A Note Two'] as $title) {
			$songId = $ctx->songId($title);
			$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'same note']);
			assertSame('ok', $response['json']['status'], "note on {$title}");
		}
	},

	'the shared note is still stored while its column is hidden' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Rendered Note Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Note Is Rendered']]);
		$songId = $ctx->songId('Note Is Rendered');

		$ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'kept out of sight']);

		$stored = $ctx->db()->query("SELECT objective_note FROM song WHERE id = {$songId}")->fetch()['objective_note'];
		assertSame('kept out of sight', $stored, 'the note is still saved');

		$body = $ctx->get('/music/songs')['body'];
		assertTrue(strpos($body, 'kept out of sight') === false, 'but it is not rendered');
		assertTrue(strpos($body, 'songNoteCell') === false, 'and the column is gone');
	},

	'a score and subjective note are stored against the rater' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Rating Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Gets A Rating']]);
		$songId = $ctx->songId('Gets A Rating');
		$accountId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		$response = $ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '8.5']);
		assertSame('ok', $response['json']['status'], 'status');
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'grower']);

		$row = $ctx->db()->query("SELECT account_id, score, subjective_note FROM account_song WHERE song_id = {$songId}")->fetch();
		assertSame($accountId, (int)$row['account_id'], 'stored against the signed in account');
		assertSame(8.5, (float)$row['score'], 'stored score');
		assertSame('grower', $row['subjective_note'], 'stored note');

		$body = $ctx->get('/music/songs')['body'];
		assertContains('<td class="songRatingCell songRatingScoreCell songMineCell songMyScoreCell">8.5</td>', $body, 'your score has its own cell');
		assertContains('<td class="songRatingCell songRatingNoteCell songMineCell songMyNoteCell" title="grower"><span class="ratingNoteText">grower</span></td>', $body, 'your note has its own cell');
	},

	'a long note is clipped in the cell but readable on hover' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Long Note Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Has A Long Note']]);
		$songId = $ctx->songId('Has A Long Note');

		$long = 'this note goes on well past the width of the column and should be chopped off';
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => $long]);

		$body = $ctx->get('/music/songs')['body'];

		// the full text is in the tooltip, the visible run is clipped by css
		assertContains('title="' . $long . '"', $body, 'the whole note is available on hover');
		assertContains('<span class="ratingNoteText">' . $long . '</span>', $body, 'the text sits in the clipping block');
	},

	'editing one half of a rating leaves the other alone' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Half Edit Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Half Edited']]);
		$songId = $ctx->songId('Half Edited');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '5']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'keeps this']);

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '9']);
		$row = $ctx->db()->query("SELECT score, subjective_note FROM account_song WHERE song_id = {$songId}")->fetch();
		assertSame(9.0, (float)$row['score'], 'score changed');
		assertSame('keeps this', $row['subjective_note'], 'note survived a score edit');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'new words']);
		$row = $ctx->db()->query("SELECT score, subjective_note FROM account_song WHERE song_id = {$songId}")->fetch();
		assertSame(9.0, (float)$row['score'], 'score survived a note edit');
		assertSame('new words', $row['subjective_note'], 'note changed');
	},

	'rating the same song twice updates rather than duplicates' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Rerating Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Rated Twice']]);
		$songId = $ctx->songId('Rated Twice');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '4']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '9']);

		$rows = $ctx->db()->query("SELECT score FROM account_song WHERE song_id = {$songId}")->fetchAll();
		assertSame(1, count($rows), 'only one row');
		assertSame(9.0, (float)$rows[0]['score'], 'updated score');
	},

	'clearing both halves removes the rating' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Cleared Rating Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Rating Removed']]);
		$songId = $ctx->songId('Rating Removed');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '7']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'fine']);

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => '  ']);
		$stillThere = (int)$ctx->db()->query("SELECT COUNT(*) c FROM account_song WHERE song_id = {$songId}")->fetch()['c'];
		assertSame(1, $stillThere, 'a scored row survives losing its note');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '  ']);
		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM account_song WHERE song_id = {$songId}")->fetch()['c'];
		assertSame(0, $count, 'the row goes once neither half is left');
	},

	'a note without a score is allowed' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Noteonly Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Note But No Score']]);
		$songId = $ctx->songId('Note But No Score');

		$response = $ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'not scored yet']);
		assertSame('ok', $response['json']['status'], 'status');

		$row = $ctx->db()->query("SELECT score, subjective_note FROM account_song WHERE song_id = {$songId}")->fetch();
		assertSame(null, $row['score'], 'score stays null');
		assertSame('not scored yet', $row['subjective_note'], 'note is stored');
	},

	'a score that is not a number is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bad Score Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Keeps Its Rating']]);
		$songId = $ctx->songId('Keeps Its Rating');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '6']);
		$response = $ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => 'banger']);

		assertSame('error', $response['json']['status'], 'status');
		assertSame(6.0, (float)$response['json']['value'], 'hands back the stored score');

		$row = $ctx->db()->query("SELECT score FROM account_song WHERE song_id = {$songId}")->fetch();
		assertSame(6.0, (float)$row['score'], 'stored score is untouched');
	},

	'an unknown rating field is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bad Rating Field Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Bad Rating Field']]);
		$songId = $ctx->songId('Bad Rating Field');

		foreach (['account_id', 'subjective_note', ''] as $field) {
			$response = $ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => $field, 'value' => '1']);
			assertSame(400, $response['status'], 'status');
		}

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM account_song WHERE song_id = {$songId}")->fetch()['c'];
		assertSame(0, $count, 'nothing was stored');
	},

	'rating an unknown song is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(RATING_ENDPOINT, ['id' => 999999, 'field' => 'score', 'value' => '5']);
		assertSame('error', $response['json']['status'], 'status');

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM account_song WHERE song_id = 999999")->fetch()['c'];
		assertSame(0, $count, 'nothing was stored');
	},

	'a rating belongs to its rater and cannot be overwritten by another account' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Shared Rating Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Rated By Two']]);
		$songId = $ctx->songId('Rated By Two');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '3']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'mine']);

		$ctx->ensureLoggedIn('second_rater', 'test password', false);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '10']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'theirs']);

		$rows = $ctx->db()->query("
			SELECT a.account_name, r.score, r.subjective_note
			FROM account_song r JOIN account a ON a.id = r.account_id
			WHERE r.song_id = {$songId}
			ORDER BY a.account_name
		")->fetchAll();

		assertSame(2, count($rows), 'two separate ratings, not one overwritten');
		assertSame('second_rater', $rows[0]['account_name'], 'the second rater has their own row');
		assertSame(10.0, (float)$rows[0]['score'], 'their score');
		assertSame('test_runner', $rows[1]['account_name'], 'the first rater still has theirs');
		assertSame(3.0, (float)$rows[1]['score'], 'the original score is untouched');
		assertSame('mine', $rows[1]['subjective_note'], 'the original note is untouched');
	},

	'the songs page shows a column per account but marks only your own editable' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		$accounts = $ctx->db()->query("SELECT account_name FROM account")->fetchAll(PDO::FETCH_COLUMN);
		foreach ($accounts as $name) {
			assertContains('>' . $name, $body, "column header for {$name}");
		}

		assertSame(1, preg_match_all('/<th class="[^"]*songMyScoreCell"/', $body), 'exactly one score column is yours');
		assertSame(1, preg_match_all('/<th class="[^"]*songMyNoteCell"/', $body), 'exactly one note column is yours');
		assertTrue(strpos($body, 'songMineCell') !== false, 'your columns are marked');

		assertContains('<th colspan="2" class="songRaterGroup songMineCell songMineGroup">test_runner</th>', $body, 'your name spans both of your columns');

		$others = $ctx->db()->query("SELECT account_name FROM account WHERE account_name != 'test_runner'")->fetchAll(PDO::FETCH_COLUMN);
		foreach ($others as $name) {
			assertContains('<th colspan="2" class="songRaterGroup">' . $name . '</th>', $body, "{$name} is grouped too but not highlighted");
		}
	},

	'the sort index is explicit and matches the cell order' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		// the grouped header breaks document order, so the js reads this rather than counting
		preg_match_all('/data-sort-key="([^"]+)" data-sort-type="[^"]+" data-sort-index="(\d+)"/', $body, $m, PREG_SET_ORDER);

		$byKey = [];
		foreach ($m as $match) {
			$byKey[$match[1]] = (int)$match[2];
		}

		$mine = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		assertSame(0, $byKey['id'], 'id is the first cell');
		assertSame(1, $byKey['artist'], 'artist is the second');
		assertSame(2, $byKey['title'], 'title is the third');
		assertTrue(!isset($byKey['note']), 'the shared note column is hidden');
		assertSame(3, $byKey["score_{$mine}"], 'your score column comes first among the raters');
		assertSame(4, $byKey["note_{$mine}"], 'your note column sits beside it');

		$indexes = array_values($byKey);
		assertSame(count($indexes), count(array_unique($indexes)), 'every column has its own index');
	},

	'an account with no ratings still gets a column' => function ($ctx) {
		$ctx->ensureLoggedIn('never_rates', 'test password', false);
		$ctx->ensureLoggedIn();

		$unrated = (int)$ctx->db()->query("
			SELECT COUNT(*) c FROM account_song
			WHERE account_id = (SELECT id FROM account WHERE account_name = 'never_rates')
		")->fetch()['c'];
		assertSame(0, $unrated, 'the account really has rated nothing');

		assertContains('>never_rates', $ctx->get('/music/songs')['body'], 'they get a column anyway');
	},

	'the songs page carries no php warnings' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		foreach (['Warning:', 'Notice:', 'Fatal error', 'Undefined variable', 'Undefined index'] as $sign) {
			assertTrue(strpos($body, $sign) === false, "page contains '{$sign}'");
		}
	},

];
