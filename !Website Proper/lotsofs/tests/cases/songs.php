<?php

const SONG_ENDPOINT = '/modules/music/ajax/song.php';
const EDIT_ENDPOINT = '/modules/music/ajax/songEdit.php';

return [

	'a song is stored against its artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Song Owner');

		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'First Track']]);
		assertSame('ok', $response['json'][0]['status'], 'status');

		$song = $ctx->db()->query("SELECT artist_id, title FROM song WHERE title = 'First Track'")->fetch();
		assertSame($artistId, (int)$song['artist_id'], 'attached to the right artist');
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

		$count = $ctx->db()->query("SELECT COUNT(*) c FROM song WHERE title = 'Repeated Track'")->fetch()['c'];
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

		$stored = $ctx->db()->query("SELECT title FROM song WHERE artist_id = {$artistId}")->fetchAll(PDO::FETCH_COLUMN);
		foreach ($titles as $title) {
			assertTrue(in_array($title, $stored, true), "stored form of {$title}");
		}
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

		$songId = $ctx->db()->query("SELECT id FROM song WHERE title = 'Listed Track'")->fetch()['id'];

		$body = $ctx->get('/music/songs')['body'];
		assertContains('Listed Track', $body, 'song title');
		assertContains('Listed Owner', $body, 'artist name');
		assertContains(">{$songId}<", $body, 'song id');
	},

	'the songs page shows its column headings' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		foreach (['All Songs', 'ID', 'Artist', 'Title', 'Note', 'Score', 'Result'] as $heading) {
			assertContains($heading, $body, "heading {$heading}");
		}
	},

	'the result column starts hidden and is not sortable' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		assertContains('<table id="songListTable" class="hideResultColumn" data-can-edit="1">', $body, 'table starts with the column hidden');
		assertContains('<th class="songResultCell">', $body, 'result header carries no sort attributes');
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
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Before Rename'")->fetch()['id'];

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'After Rename']);
		assertSame('ok', $response['json']['status'], 'status');
		assertSame('After Rename', $response['json']['value'], 'echoed title');

		$stored = $ctx->db()->query("SELECT title FROM song WHERE id = {$songId}")->fetch()['title'];
		assertSame('After Rename', $stored, 'stored title');
	},

	'a rename trims surrounding whitespace' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Trim Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Untrimmed']]);
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Untrimmed'")->fetch()['id'];

		$ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'   Trimmed   ']);

		$stored = $ctx->db()->query("SELECT title FROM song WHERE id = {$songId}")->fetch()['title'];
		assertSame('Trimmed', $stored, 'stored title');
	},

	'a rename to an empty title is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Empty Rename Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Keeps Its Name']]);
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Keeps Its Name'")->fetch()['id'];

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'   ']);
		assertSame('error', $response['json']['status'], 'status');
		assertSame('Keeps Its Name', $response['json']['value'], 'hands back the unchanged title');

		$stored = $ctx->db()->query("SELECT title FROM song WHERE id = {$songId}")->fetch()['title'];
		assertSame('Keeps Its Name', $stored, 'stored title is untouched');
	},

	'a rename onto the same artists other song is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Clash Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Clash One']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Clash Two']]);
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Clash Two'")->fetch()['id'];

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'Clash One']);
		assertSame('duplicate', $response['json']['status'], 'status');
		assertSame('Clash Two', $response['json']['value'], 'hands back the unchanged title');

		$stored = $ctx->db()->query("SELECT title FROM song WHERE id = {$songId}")->fetch()['title'];
		assertSame('Clash Two', $stored, 'stored title is untouched');
	},

	'a rename may match another artists song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$first = $ctx->makeArtist('Rename Coverer One');
		$second = $ctx->makeArtist('Rename Coverer Two');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $first, 'title' => 'Covered Later']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $second, 'title' => 'Not Yet Covered']]);
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Not Yet Covered'")->fetch()['id'];

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'Covered Later']);
		assertSame('ok', $response['json']['status'], 'status');
	},

	'renaming a song to its own title is allowed' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Idempotent Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Unchanged Title']]);
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Unchanged Title'")->fetch()['id'];

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' =>'Unchanged Title']);
		assertSame('ok', $response['json']['status'], 'status');
	},

	'renaming an unknown song is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => 999999, 'field' => 'title', 'value' => 'Ghost Title']);
		assertSame('error', $response['json']['status'], 'status');

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song WHERE title = 'Ghost Title'")->fetch()['c'];
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
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Untouched By Bad Field'")->fetch()['id'];

		foreach (['artist_id', 'id', '', ['title']] as $field) {
			$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => $field, 'value' => 'nope']);
			assertSame(400, $response['status'], 'status');
			assertTrue(isset($response['json']['error']), 'body carries an error key');
		}

		$stored = $ctx->db()->query("SELECT title FROM song WHERE id = {$songId}")->fetch()['title'];
		assertSame('Untouched By Bad Field', $stored, 'the row is untouched');
	},

	'a note can be set and changed' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Note Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Gets A Note']]);
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Gets A Note'")->fetch()['id'];

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
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Loses Its Note'")->fetch()['id'];

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
			$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = '{$title}'")->fetch()['id'];
			$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'same note']);
			assertSame('ok', $response['json']['status'], "note on {$title}");
		}
	},

	'an edited note shows on the songs page' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Rendered Note Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Note Is Rendered']]);
		$songId = (int)$ctx->db()->query("SELECT id FROM song WHERE title = 'Note Is Rendered'")->fetch()['id'];

		$ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'shows up in the table']);

		assertContains('shows up in the table', $ctx->get('/music/songs')['body'], 'note renders');
	},

	'the songs page carries no php warnings' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		foreach (['Warning:', 'Notice:', 'Fatal error', 'Undefined variable', 'Undefined index'] as $sign) {
			assertTrue(strpos($body, $sign) === false, "page contains '{$sign}'");
		}
	},

];
