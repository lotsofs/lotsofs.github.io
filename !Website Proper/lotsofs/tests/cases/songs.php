<?php

const SONG_ENDPOINT = '/music/ajax/song';
const EDIT_ENDPOINT = '/music/ajax/song-edit';
const RATING_ENDPOINT = '/music/ajax/song-rating';
const RATING_POLL_ENDPOINT = '/music/ajax/song-rating-poll';
const SONG_ARTIST_ENDPOINT = '/music/ajax/song-artist';
const SONG_ALBUM_ENDPOINT = '/music/ajax/song-album';
const SONG_LINK_ENDPOINT = '/music/ajax/song-link';
const SONG_YEAR_ENDPOINT = '/music/ajax/song-year';
const SONG_DURATION_ENDPOINT = '/music/ajax/song-duration';

function songsMakeAlbum($ctx, $name, $artistId, $tracks) {
	$response = $ctx->post('/music/ajax/album', [[
		'provided_name' => $name,
		'album_id' => 'new',
		'og_name' => $name,
		'is_actual' => true,
		'artist_id' => $artistId,
		'release_year' => '',
		'tracks' => $tracks,
	]]);
	return (int)$response['json'][0]['album_id'];
}

// each chunk starts right after the row's data-song-id value, so the song id
// is the leading digits and the rest runs up to the next row
function songsRowChunks($body) {
	$chunks = explode('<tr data-song-id="', $body);
	array_shift($chunks);
	return $chunks;
}

function songsRowFor($body, $songId) {
	foreach (songsRowChunks($body) as $chunk) {
		if (strpos($chunk, (int)$songId . '"') === 0) {
			return $chunk;
		}
	}
	return null;
}

// works for a <td data-field="x">value</td> cell, for a note cell's
// <td data-field="x" ...><span class="ratingNoteText">value</span></td> and for
// the album cell, whose names are links to each album's card
function songsCellValue($chunk, $field) {
	$pattern = '/data-field="' . preg_quote($field, '/') . '"[^>]*>(.*?)<\/(?:td|dd)>/s';
	return preg_match($pattern, $chunk, $m) ? strip_tags($m[1]) : null;
}

function songsValuesInOrder($body, $field) {
	$values = [];
	foreach (songsRowChunks($body) as $chunk) {
		$values[] = songsCellValue($chunk, $field);
	}
	return $values;
}

function songsTitleCellFor($body, $songId) {
	$chunk = songsRowFor($body, $songId);
	if ($chunk === null) {
		return null;
	}

	$pattern = '/<td class="songTitleCell([^"]*)" data-field="title" data-canonical-title="([^"]*)"( title="([^"]*)")?><span class="songCellText">([^<]*)<\/span><\/td>/';

	// Throwing rather than returning null: every caller reads a key straight
	// off the result, so a null turns a markup change into "array offset on
	// null" three lines later instead of saying which pattern stopped matching.
	if (!preg_match($pattern, $chunk, $m)) {
		throw new Exception("no title cell matched for song {$songId} in: " . substr($chunk, 0, 300));
	}

	return [
		'classes' => trim($m[1]),
		'canonical' => $m[2],
		'tooltip' => $m[3] === '' ? null : $m[4],
		'text' => $m[5],
	];
}

function songsAlbumCellFor($body, $songId) {
	$chunk = songsRowFor($body, $songId);
	return $chunk === null ? null : songsCellValue($chunk, 'album');
}

// smoke-check helper for the separate #songCards tree: the table is the
// primary thing tested throughout this file, cards are checked only lightly
function songsCardFor($body, $songId) {
	$chunks = preg_split('/<dl class="songCard[^"]*" data-song-id="/', $body);
	array_shift($chunks);
	foreach ($chunks as $chunk) {
		if (strpos($chunk, (int)$songId . '"') === 0) {
			return $chunk;
		}
	}
	return null;
}

// the poll hands back at most 50 audit rows per request, so a test that wants
// to see its own write has to start from where the log stood before it, not
// from 0 - otherwise it only passes while the whole suite has written fewer
// than 50 ratings before this point
function songsAuditCursor($ctx) {
	return (int)$ctx->db()->query("SELECT COALESCE(MAX(id), 0) c FROM rating_audit")->fetch()['c'];
}

function songsVisibleTitles($body) {
	$visible = [];
	foreach (songsRowChunks($body) as $chunk) {
		$attributes = substr($chunk, 0, strpos($chunk, '>'));
		if (strpos($attributes, ' hidden') === false) {
			$visible[] = songsCellValue($chunk, 'title');
		}
	}
	return $visible;
}

return [

	'a song is stored against its artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Song Owner');

		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'First Track']]);
		assertSame('ok', $response['json'][0]['status'], 'status');

		$songId = $ctx->songId('First Track');
		assertTrue($songId > 0, 'the song exists');

		assertSame($artistId, $ctx->songArtistId($songId), 'attached to the right artist');
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
			FROM song s
			JOIN song_alias sa ON sa.song_id = s.id
			JOIN song_artist art ON art.song_id = s.id
			WHERE art.artist_id = {$artistId}
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

		$songCount = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song_artist WHERE artist_id = {$artistId}")->fetch()['c'];
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
			'song_id' => 'new',
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

		$response = $ctx->post(SONG_ENDPOINT, [
			['artist_id' => $artistId, 'title' => 'Plain New.ogg'],
			['artist_id' => $artistId, 'title' => 'Custom Source.ogg', 'song_id' => 'new', 'og_name' => 'Custom Stored'],
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

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song_artist WHERE artist_id = {$artistId}")->fetch()['c'];
		assertSame(1, $count, 'the check that replaced idx_song_unique still holds');
	},

	'the artist endpoint hands back that artists songs for the dropdown' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Dropdown Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Dropdown Song One']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Dropdown Song Two']]);

		$response = $ctx->post('/music/ajax/artist-alias', [[
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

		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'A Functioning God.ogg', 'song_id' => $songId, 'also_alias_provided_name' => true]]);

		$response = $ctx->post('/music/ajax/artist-alias', [[
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

		$card = songsCardFor($body, $songId);
		assertTrue($card !== null, 'the card list has a matching card');
		assertSame('Listed Track', songsCellValue($card, 'title'), 'the card shows the same title');
		assertSame('Listed Owner', songsCellValue($card, 'artist'), 'the card shows the same artist');
	},

	'the songs page shows its column headings' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		foreach (['All Songs', 'ID', 'Artist', 'Title', 'Album', 'Note', 'test_runner', 'Result'] as $heading) {
			assertContains($heading, $body, "heading {$heading}");
		}
	},

	'the result column starts hidden and is not sortable' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		assertClasses(['hideResultColumn'], $body, '/<table id="songListTable"[^>]*class="([^"]*)"/', 'table starts with the column hidden');
		assertContains('songCardEditBtn', $body, 'an admin does get the edit button, so the non admin check has something to miss');
		assertTrue(preg_match('/<th rowspan="2" class="songResultCell">/', $body) === 1, 'result header carries no sort attributes');
		assertTrue(strpos($body, 'data-sort-key="result"') === false, 'the result header is not sortable');
		assertContains('<td class="songResultCell" data-field="result"></td>', $body, 'rows carry a result cell');
		assertTrue(strpos($body, 'sort=result') === false, 'nothing links to sorting by result');
	},

	// The clipped cells have carried their full value in a title attribute all
	// along; opting the containers in is what turns those into the hover box
	// without touching a cell. The attribute stays server-side, so the value is
	// still reachable with no javascript at all.
	'the clipped cells opt in to the hover box' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Hover Table Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Hover Table Song']]);
		$songId = $ctx->songId('Hover Table Song');
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'a note far too long to fit inside the column it lives in']);

		$body = $ctx->get('/music/songs')['body'];

		foreach (['songListTable', 'songCards', 'songCardModal'] as $region) {
			assertTrue(preg_match('/id="' . $region . '"[^>]*data-tooltip-titles/', $body) === 1, "{$region} opts its titles in");
		}

		$row = songsRowFor($body, $songId);
		assertContains('title="Hover Table Artist"', $row, 'the artist cell still carries its full value');
		assertContains('title="a note far too long to fit inside the column it lives in"', $row, 'and so does the note cell');

		assertContains('id="musicTooltip"', $body, 'the page carries the box they open into');
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
			$titles = songsValuesInOrder($ctx->get($path)['body'], 'title');
			return array_values(array_filter($titles, fn($t) => in_array($t, $mine, true)));
		};

		$ascending = $titlesInOrder('/music/songs?sort=title&dir=asc');
		assertSame(['Aaa Sortable', 'Mmm Sortable', 'Zzz Sortable'], $ascending, 'ascending by title');

		$descending = $titlesInOrder('/music/songs?sort=title&dir=desc');
		assertSame(['Zzz Sortable', 'Mmm Sortable', 'Aaa Sortable'], $descending, 'descending by title');
	},

	'the songs page defaults to id order' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$ids = array_map('intval', songsValuesInOrder($ctx->get('/music/songs')['body'], 'id'));
		assertTrue(count($ids) > 1, 'there are rows to order');

		$sorted = $ids;
		sort($sorted);
		assertSame($sorted, $ids, 'rows come back in id order');
	},

	'an unknown sort column falls back to id' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->get('/music/songs?sort=nonsense&dir=sideways');
		assertSame(200, $response['status'], 'page still renders');

		$ids = array_map('intval', songsValuesInOrder($response['body'], 'id'));
		assertTrue(count($ids) > 1, 'there are rows to order');

		$sorted = $ids;
		sort($sorted);
		assertSame($sorted, $ids, 'fell back to id order');
	},

	'an array shaped sort value falls back to id' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->get('/music/songs?sort[]=id&dir[]=desc');
		assertSame(200, $response['status'], 'page still renders');
		assertTrue(strpos($response['body'], 'TypeError') === false, 'no type error leaked');

		$ids = array_map('intval', songsValuesInOrder($response['body'], 'id'));
		assertTrue(count($ids) > 1, 'there are rows to order');

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
		assertSame('error', $response['json']['status'], 'a refused write is an error, not a duplicate');
		assertContains('❌', $response['json']['message'], 'and it reads as a failure, not a green tick');
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

	// Songs accumulate alternate names from the importer and from the album
	// card's "listed as" feature, so renaming onto one of them is an ordinary
	// admin action. Renaming the actual row onto that name would collide with
	// idx_song_alias_unique and come back as a 500 carrying the raw SQL.
	'renaming a song to a name it already answers to promotes that name' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Promoted Alias Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Promoted Studio Title']]);
		$songId = $ctx->songId('Promoted Studio Title');

		$ctx->post(SONG_ENDPOINT, [[
			'artist_id' => $artistId,
			'title' => 'Promoted Sleeve Title',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);

		$response = $ctx->post(EDIT_ENDPOINT, ['id' => $songId, 'field' => 'title', 'value' => 'Promoted Sleeve Title']);
		assertSame(200, $response['status'], 'the rename does not blow up on the unique index');
		assertSame('ok', $response['json']['status'], 'status');

		$aliases = $ctx->db()->query("SELECT name, is_actual FROM song_alias WHERE song_id = {$songId} ORDER BY is_actual DESC")->fetchAll();
		assertSame(2, count($aliases), 'both names are still there');
		assertSame('Promoted Sleeve Title', $aliases[0]['name'], 'the chosen name is now the actual one');
		assertSame(1, (int)$aliases[0]['is_actual'], 'and is marked as such');
		assertSame(0, (int)$aliases[1]['is_actual'], 'leaving exactly one actual name');
		assertSame('Promoted Studio Title', $aliases[1]['name'], 'and the old title stayed behind as an alias');
		assertSame('Promoted Sleeve Title', $ctx->songTitle($songId), 'the song now goes by the new title');
	},

	// The browser always sends strings, so these guard the endpoints against a
	// caller that doesn't - where "set it to 1990" would otherwise arrive as
	// "clear it" and be answered with status ok.
	'a numeric field sent as a number is set, not cleared' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Typed Payload Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Typed Payload Song']]);
		$songId = $ctx->songId('Typed Payload Song');

		assertSame('ok', $ctx->post(SONG_YEAR_ENDPOINT, ['song_id' => $songId, 'value' => 1990])['json']['status'], 'year status');
		assertSame(1990, (int)$ctx->db()->query("SELECT year FROM song WHERE id = {$songId}")->fetch()['year'], 'an integer year is stored, not wiped');

		assertSame('ok', $ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => $songId, 'value' => 215])['json']['status'], 'duration status');
		assertSame(215, (int)$ctx->db()->query("SELECT duration FROM song WHERE id = {$songId}")->fetch()['duration'], 'an integer duration too');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => 7.5]);
		$score = $ctx->db()->query("SELECT score FROM account_song WHERE song_id = {$songId}")->fetch()['score'];
		assertSame(7.5, (float)$score, 'and a fractional score arrives as a float, which is the case the int-only check missed');
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

		$chunk = songsRowFor($ctx->get('/music/songs')['body'], $songId);
		assertSame('8.5', songsCellValue($chunk, "score_{$accountId}"), 'your score has its own cell');
		assertSame('grower', songsCellValue($chunk, "note_{$accountId}"), 'your note has its own cell');
		assertTrue(preg_match('/data-field="score_' . $accountId . '"[^>]*>/', $chunk) === 1, 'the score cell is addressable by field');
		assertContains('songMyScoreCell', $chunk, 'your score cell is marked as yours');
		assertContains('songMyNoteCell', $chunk, 'your note cell is marked as yours');
	},

	'a long note is clipped in the cell but readable on hover' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Long Note Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Has A Long Note']]);
		$songId = $ctx->songId('Has A Long Note');

		$long = 'this note goes on well past the width of the column and should be chopped off';
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => $long]);

		$body = $ctx->get('/music/songs')['body'];

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

	'clearing both halves empties the rating but keeps the row' => function ($ctx) {
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

		$row = $ctx->db()->query("SELECT score, subjective_note FROM account_song WHERE song_id = {$songId}")->fetch();
		assertTrue($row !== false, 'the row survives so the clear can reach other viewers');
		assertSame(null, $row['score'], 'score is emptied');
		assertSame(null, $row['subjective_note'], 'note is emptied');

		$accountId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];
		$chunk = songsRowFor($ctx->get('/music/songs')['body'], $songId);

		assertSame('', songsCellValue($chunk, "score_{$accountId}"), 'an emptied rating renders exactly like no rating');
		assertTrue(preg_match('/<td class="[^"]*songCellEmpty"[^>]*data-field="score_' . $accountId . '"/', $chunk) === 1, 'the emptied cell is marked empty');
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

	'a score typed with a comma decimal is accepted' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Comma Score Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Comma Scored Song']]);
		$songId = $ctx->songId('Comma Scored Song');
		$accountId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		$response = $ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '8,5']);
		assertSame('ok', $response['json']['status'], 'status');
		assertSame(8.5, (float)$response['json']['value'], 'the comma is read as a decimal point');

		$row = $ctx->db()->query("SELECT score FROM account_song WHERE song_id = {$songId}")->fetch();
		assertSame(8.5, (float)$row['score'], 'and that is what gets stored');

		$chunk = songsRowFor($ctx->get('/music/songs')['body'], $songId);
		assertSame('8.5', songsCellValue($chunk, "score_{$accountId}"), 'the cell shows the canonical dot form');
	},

	'a note keeps its commas' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Comma Note Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Comma Noted Song']]);
		$songId = $ctx->songId('Comma Noted Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'slow, then loud, then slow']);

		$row = $ctx->db()->query("SELECT subjective_note FROM account_song WHERE song_id = {$songId}")->fetch();
		assertSame('slow, then loud, then slow', $row['subjective_note'], 'only scores swap commas for dots');
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

	'a column header names itself on hover instead of explaining the sort' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$mine = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];
		$body = $ctx->get('/music/songs')['body'];

		assertTrue(preg_match('/data-sort-key="title"[^>]*>\s*<a [^>]*title="Title \(click to sort\)"/', $body) === 1, 'a fixed column hovers as its own name');
		assertTrue(preg_match('/data-sort-key="score_' . $mine . '"[^>]*>\s*<a [^>]*title="test_runner score \(click to sort\)"/', $body) === 1, 'a rater column names the rater, which the abbreviated header cannot');

		assertTrue(strpos($body, 'title="Sort ascending"') === false, 'no header explains the sort direction any more');
		assertContains('<option value="asc">Sort ascending</option>', $body, 'the mobile sort dropdown still says it, which is why the strings stay');
	},

	'rater columns run in account id order, with your own pulled to the front' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$mine = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		$expected = array_map('intval', $ctx->db()->query("
			SELECT id FROM account ORDER BY id = {$mine} DESC, id
		")->fetchAll(PDO::FETCH_COLUMN));

		assertTrue(count($expected) > 2, 'there are enough accounts for the order to be meaningful');

		preg_match_all('/data-sort-key="score_(\d+)"/', $ctx->get('/music/songs')['body'], $m);
		$shown = array_values(array_unique(array_map('intval', $m[1])));

		assertSame($expected, $shown, 'columns follow account id, not account name');
		assertSame($mine, $shown[0], 'your own column still comes first');
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

		// Matched on the class list rather than the exact attribute string:
		// CLAUDE.md's rule for this markup is "add to a class list, never
		// replace one", so pinning the literal would fail on a legal change
		// while still not catching a class that went missing.
		assertClasses(
			['songRaterGroup', 'songMineCell', 'songMineGroup'],
			$body,
			'/<th colspan="2" class="([^"]*)">test_runner<\/th>/',
			'your name spans both of your columns'
		);

		$others = $ctx->db()->query("SELECT account_name FROM account WHERE account_name != 'test_runner'")->fetchAll(PDO::FETCH_COLUMN);
		foreach ($others as $name) {
			assertClasses(['songRaterGroup'], $body, '/<th colspan="2" class="([^"]*)">' . preg_quote($name, '/') . '<\/th>/', "{$name} is grouped too");
			assertTrue(preg_match('/<th colspan="2" class="[^"]*songMineGroup[^"]*">' . preg_quote($name, '/') . '<\/th>/', $body) === 0, "{$name} is not highlighted as yours");
		}
	},

	'every header appears once, in the right order, and matches a real cell' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];
		$mine = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		preg_match_all('/data-sort-key="([^"]+)" data-sort-type="[^"]+">/', $body, $m);
		$keys = $m[1];

		assertSame(count($keys), count(array_unique($keys)), 'every sort key appears exactly once');
		assertTrue(!in_array('note', $keys, true), 'the shared note column is gone');

		$positions = array_flip($keys);
		assertTrue(
			$positions['id'] < $positions['artist']
				&& $positions['artist'] < $positions['title']
				&& $positions['title'] < $positions['album']
				&& $positions['album'] < $positions["score_{$mine}"],
			'fixed columns precede the raters, in order'
		);
		assertSame($positions["score_{$mine}"] + 1, $positions["note_{$mine}"], 'your note column sits right beside your score column');

		$chunk = songsRowChunks($body)[0];
		foreach ($keys as $key) {
			assertTrue(songsCellValue($chunk, $key) !== null, "rows carry a {$key} cell");
		}
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

	'the artist filter hides other artists rows' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$one = $ctx->makeArtist('Filter Artist One');
		$two = $ctx->makeArtist('Filter Artist Two');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $one, 'title' => 'Kept By Filter']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $two, 'title' => 'Dropped By Filter']]);

		$visible = songsVisibleTitles($ctx->get("/music/songs?artist={$one}")['body']);
		assertTrue(in_array('Kept By Filter', $visible, true), 'the chosen artists song shows');
		assertTrue(!in_array('Dropped By Filter', $visible, true), 'the other artists song is hidden');
	},

	'the artist filter matches a song by any of its linked artists, not just the first' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$primary = $ctx->makeArtist('Filter Primary Artist');
		$featured = $ctx->makeArtist('Filter Featured Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $primary, 'title' => 'Featuring Song']]);
		$songId = $ctx->songId('Featuring Song');
		$ctx->post(SONG_ARTIST_ENDPOINT, ['song_id' => $songId, 'artist_id' => $featured, 'action' => 'add']);

		$visibleByPrimary = songsVisibleTitles($ctx->get("/music/songs?artist={$primary}")['body']);
		assertTrue(in_array('Featuring Song', $visibleByPrimary, true), 'shows when filtered by the primary artist');

		$visibleByFeatured = songsVisibleTitles($ctx->get("/music/songs?artist={$featured}")['body']);
		assertTrue(in_array('Featuring Song', $visibleByFeatured, true), 'also shows when filtered by the featured artist');
	},

	'a compilation album filters the list to its tracklist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Compilation Contributor');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'On The Comp']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Off The Comp']]);
		$albumId = songsMakeAlbum($ctx, 'Various Artists Collection', null, [
			['song_id' => $ctx->songId('On The Comp'), 'position' => 1],
		]);

		$visible = songsVisibleTitles($ctx->get("/music/songs?album={$albumId}")['body']);
		assertSame(['On The Comp'], $visible, 'only the album track shows');
	},

	'selecting an album overrides the artist filter' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$main = $ctx->makeArtist('Album Artist Main');
		$guest = $ctx->makeArtist('Album Guest');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $main, 'title' => 'Main Track']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $guest, 'title' => 'Guest Track']]);
		$albumId = songsMakeAlbum($ctx, 'Split Record', $main, [
			['song_id' => $ctx->songId('Main Track'), 'position' => 1],
			['song_id' => $ctx->songId('Guest Track'), 'position' => 2],
		]);

		$visible = songsVisibleTitles($ctx->get("/music/songs?artist={$main}&album={$albumId}")['body']);
		assertTrue(in_array('Guest Track', $visible, true), 'the guest track shows even though it is not by the chosen artist');
		assertTrue(in_array('Main Track', $visible, true), 'the album artists track shows');
	},

	'the album dropdown only offers the chosen artists albums' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$one = $ctx->makeArtist('Dropdown Artist One');
		$two = $ctx->makeArtist('Dropdown Artist Two');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $one, 'title' => 'Dropdown One Track']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $two, 'title' => 'Dropdown Two Track']]);
		songsMakeAlbum($ctx, 'Record By Artist One', $one, [['song_id' => $ctx->songId('Dropdown One Track'), 'position' => 1]]);
		songsMakeAlbum($ctx, 'Record By Artist Two', $two, [['song_id' => $ctx->songId('Dropdown Two Track'), 'position' => 1]]);

		preg_match('/<select id="filterAlbum".*?<\/select>/s', $ctx->get("/music/songs?artist={$one}")['body'], $m);
		assertContains('Record By Artist One', $m[0], 'the chosen artists album is offered');
		assertTrue(strpos($m[0], 'Record By Artist Two') === false, 'the other artists album is not offered');
	},

	'the album dropdown offers compilations when no artist is chosen' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Owned Album Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Owned Album Track']]);
		songsMakeAlbum($ctx, 'An Attributed Album', $artistId, [['song_id' => $ctx->songId('Owned Album Track'), 'position' => 1]]);
		songsMakeAlbum($ctx, 'A Loose Compilation', null, [['song_id' => $ctx->songId('Owned Album Track'), 'position' => 1]]);

		preg_match('/<select id="filterAlbum".*?<\/select>/s', $ctx->get('/music/songs')['body'], $m);
		assertContains('A Loose Compilation', $m[0], 'the compilation is offered');
		assertTrue(strpos($m[0], 'An Attributed Album') === false, 'the attributed album is not offered');
	},

	'unknown or malformed filter ids show every song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Fallback Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Fallback Track']]);

		$before = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song")->fetch()['c'];

		foreach (['/music/songs?artist=999999', '/music/songs?artist[]=1', '/music/songs?artist=' . urlencode('1;DROP TABLE song')] as $path) {
			$response = $ctx->get($path);
			assertSame(200, $response['status'], "renders: {$path}");
			assertTrue(in_array('Fallback Track', songsVisibleTitles($response['body']), true), "song still shows for {$path}");
		}

		$after = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song")->fetch()['c'];
		assertSame($before, $after, 'the song table survived');
	},

	'sorting keeps the active filter' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Sorted Filter Artist');
		$other = $ctx->makeArtist('Sorted Filter Other');
		foreach (['Ccc Filtered', 'Aaa Filtered', 'Bbb Filtered'] as $title) {
			$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => $title]]);
		}
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $other, 'title' => 'Zzz Elsewhere']]);

		$body = $ctx->get("/music/songs?artist={$artistId}&sort=title&dir=desc")['body'];
		$visible = songsVisibleTitles($body);
		assertSame(['Ccc Filtered', 'Bbb Filtered', 'Aaa Filtered'], $visible, 'filtered rows stay sorted descending');
		assertTrue(!in_array('Zzz Elsewhere', $visible, true), 'the other artist stays hidden');
		assertContains("&amp;artist={$artistId}", $body, 'the sort links carry the filter');
	},

	'the songs page shows which albums a song is on' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Album Column Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'On Two Records']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'On No Record']]);
		$songId = $ctx->songId('On Two Records');

		songsMakeAlbum($ctx, 'Aaa Column Record', $artistId, [['song_id' => $songId, 'position' => 1]]);
		songsMakeAlbum($ctx, 'Zzz Column Record', $artistId, [['song_id' => $songId, 'position' => 1]]);

		$body = $ctx->get('/music/songs')['body'];

		$albumsByTitle = [];
		foreach (songsRowChunks($body) as $chunk) {
			$albumsByTitle[songsCellValue($chunk, 'title')] = songsCellValue($chunk, 'album');
		}

		assertSame('Aaa Column Record, Zzz Column Record', $albumsByTitle['On Two Records'] ?? '', 'both albums are listed');
		assertSame('', $albumsByTitle['On No Record'] ?? 'missing', 'a song on no album has an empty cell');
	},

	'filtering to an album retitles its tracks as that release lists them' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Odd Listing Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Studio Version']]);
		$songId = $ctx->songId('Studio Version');

		$aliased = $ctx->post(SONG_ENDPOINT, [[
			'artist_id' => $artistId,
			'title' => 'Sleeve Version',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);
		$aliasId = (int)$aliased['json'][0]['song_alias_id'];

		$odd = songsMakeAlbum($ctx, 'Oddly Listed Record', $artistId, [
			['song_id' => $songId, 'song_alias_id' => $aliasId, 'position' => 1],
		]);
		$plain = songsMakeAlbum($ctx, 'Plainly Listed Record', $artistId, [
			['song_id' => $songId, 'position' => 2],
		]);

		$cell = fn($path) => songsTitleCellFor($ctx->get($path)['body'], $songId);

		$plainly = $cell("/music/songs?artist={$artistId}");
		assertSame('Studio Version', $plainly['text'], 'unfiltered shows the actual title');
		assertSame('', $plainly['classes'], 'and is not marked as retitled');

		$retitled = $cell("/music/songs?artist={$artistId}&album={$odd}");
		assertSame('Sleeve Version', $retitled['text'], 'the odd release retitles it');
		assertSame('songTitleAliased', $retitled['classes'], 'and the cell is marked as showing an alias');
		assertSame('Studio Version', $retitled['canonical'], 'the real title is kept on the cell');

		$ordinary = $cell("/music/songs?artist={$artistId}&album={$plain}");
		assertSame('Studio Version', $ordinary['text'], 'a release with no odd title leaves it alone');
		assertSame('', $ordinary['classes'], 'and is not marked as an alias');
	},

	'each album name in the song list opens that album\'s card' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Clickable Song Album Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Clickable From Two Records']]);
		$songId = $ctx->songId('Clickable From Two Records');

		$first = songsMakeAlbum($ctx, 'Aaa Clickable Record', $artistId, [['song_id' => $songId, 'position' => 1]]);
		$second = songsMakeAlbum($ctx, 'Zzz Clickable Record', $artistId, [['song_id' => $songId, 'position' => 1]]);

		$body = $ctx->get('/music/songs')['body'];
		assertContains('id="albumCardModal"', $body, 'the page carries the modal the card opens into');

		foreach (['row' => songsRowFor($body, $songId), 'card' => songsCardFor($body, $songId)] as $where => $chunk) {
			foreach ([$first, $second] as $albumId) {
				assertContains('data-album-card-id="' . $albumId . '"', $chunk, "the {$where} links album {$albumId} to its own card");
			}
		}

		assertSame('Aaa Clickable Record, Zzz Clickable Record', songsAlbumCellFor($body, $songId), 'and the cell still reads as a plain list of names');
	},

	'the album column lists album names only' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Plain Album Column Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Column Studio Title']]);
		$songId = $ctx->songId('Column Studio Title');

		$aliased = $ctx->post(SONG_ENDPOINT, [[
			'artist_id' => $artistId,
			'title' => 'Column Sleeve Title',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);

		songsMakeAlbum($ctx, 'Column Record', $artistId, [
			['song_id' => $songId, 'song_alias_id' => (int)$aliased['json'][0]['song_alias_id'], 'position' => 1],
		]);

		$body = $ctx->get("/music/songs?artist={$artistId}")['body'];

		assertSame('Column Record', songsAlbumCellFor($body, $songId), 'no listed-as annotation');
		assertTrue(strpos($body, '(as &quot;') === false, 'the old annotation is gone everywhere');
	},

	'hovering a title reveals every name the song goes by' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Tooltip Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Tooltip Actual']]);
		$songId = $ctx->songId('Tooltip Actual');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Tooltip Other', 'song_id' => $songId, 'also_alias_provided_name' => true]]);

		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Tooltip Lonely']]);
		$lonelyId = $ctx->songId('Tooltip Lonely');

		$body = $ctx->get("/music/songs?artist={$artistId}")['body'];

		assertSame('Tooltip Actual, Tooltip Other', songsTitleCellFor($body, $songId)['tooltip'], 'actual name first, then aliases');
		assertSame('Tooltip Lonely', songsTitleCellFor($body, $lonelyId)['tooltip'], 'a song with one name still gets a tooltip, so a clipped title is always readable');
	},

	'the songs page sorts by album' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Album Sort Artist');
		foreach (['Sorted By Mmm', 'Sorted By Aaa', 'Sorted By Zzz'] as $title) {
			$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => $title]]);
		}
		songsMakeAlbum($ctx, 'Mmm Sorting Album', $artistId, [['song_id' => $ctx->songId('Sorted By Mmm'), 'position' => 1]]);
		songsMakeAlbum($ctx, 'Aaa Sorting Album', $artistId, [['song_id' => $ctx->songId('Sorted By Aaa'), 'position' => 1]]);
		songsMakeAlbum($ctx, 'Zzz Sorting Album', $artistId, [['song_id' => $ctx->songId('Sorted By Zzz'), 'position' => 1]]);

		$body = $ctx->get("/music/songs?artist={$artistId}&sort=album&dir=asc")['body'];
		$albums = songsValuesInOrder($body, 'album');
		$mine = array_values(array_filter($albums, fn($a) => $a !== null && strpos($a, 'Sorting Album') !== false));

		assertSame(['Aaa Sorting Album', 'Mmm Sorting Album', 'Zzz Sorting Album'], $mine, 'ascending by album name');
	},

	'the artists page links each artist to its filtered songs' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Linked From Artists Page');

		$body = $ctx->get('/music/artists')['body'];
		assertContains("<a href=\"/music/songs?artist={$artistId}\">Linked From Artists Page</a>", $body, 'the name links to the artist filter');
	},

	'the albums page links an album to its filtered songs' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Linked From Albums Page');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Linked Album Song']]);
		$songId = $ctx->songId('Linked Album Song');

		$owned = songsMakeAlbum($ctx, 'An Attributed Linked Album', $artistId, [['song_id' => $songId, 'position' => 1]]);
		$loose = songsMakeAlbum($ctx, 'A Compilation Linked Album', null, [['song_id' => $songId, 'position' => 1]]);

		$body = $ctx->get('/music/albums')['body'];
		assertContains("/music/songs?artist={$artistId}&amp;album={$owned}", $body, 'an attributed album carries its artist so the filter is in scope');
		assertContains("\"/music/songs?album={$loose}\"", $body, 'a compilation links by album alone');
	},

	'an album link from the albums page actually filters' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Round Trip Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Round Trip On Album']]);
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Round Trip Off Album']]);
		$albumId = songsMakeAlbum($ctx, 'Round Trip Record', $artistId, [
			['song_id' => $ctx->songId('Round Trip On Album'), 'position' => 1],
		]);

		$visible = songsVisibleTitles($ctx->get("/music/songs?artist={$artistId}&album={$albumId}")['body']);
		assertSame(['Round Trip On Album'], $visible, 'following the link lands on a filtered list');
	},

	'the heading names whatever the list is showing' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Heading Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Heading Song']]);
		$songId = $ctx->songId('Heading Song');
		$owned = songsMakeAlbum($ctx, 'Heading Record', $artistId, [['song_id' => $songId, 'position' => 1]]);
		$loose = songsMakeAlbum($ctx, 'Heading Compilation', null, [['song_id' => $songId, 'position' => 1]]);

		$heading = function ($path) use ($ctx) {
			preg_match('/<h1 id="songListHeading">\s*(.*?)\s*<\/h1>/s', $ctx->get($path)['body'], $m);
			return $m[1] ?? '';
		};

		assertSame('All Songs', $heading('/music/songs'), 'unfiltered');
		assertSame('Heading Artist', $heading("/music/songs?artist={$artistId}"), 'filtered to an artist');
		assertSame('Heading Artist — Heading Record', $heading("/music/songs?artist={$artistId}&album={$owned}"), 'filtered to an attributed album');
		assertSame('Heading Compilation', $heading("/music/songs?album={$loose}"), 'filtered to a compilation');
	},

	'the nav calls the songs page Songs' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];
		assertContains('<a href="/music/songs" class="navCurrent">Songs</a>', $body, 'the nav entry is Songs');
		assertContains('<h1 id="songListHeading">', $body, 'the page still has its own heading');
	},

	'a rating stamps updated_at when it is first stored' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Stamped Rating Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Stamped Song']]);
		$songId = $ctx->songId('Stamped Song');

		$before = time();
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '4']);

		$stamp = (int)$ctx->db()->query("SELECT updated_at FROM account_song WHERE song_id = {$songId}")->fetch()['updated_at'];
		assertTrue($stamp >= $before, 'stamped at write time');
	},

	'a later edit moves updated_at forward' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Restamped Rating Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Restamped Song']]);
		$songId = $ctx->songId('Restamped Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '4']);

		$ctx->db()->exec("UPDATE account_song SET updated_at = 0 WHERE song_id = {$songId}");

		$before = time();
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '5']);

		$stamp = (int)$ctx->db()->query("SELECT updated_at FROM account_song WHERE song_id = {$songId}")->fetch()['updated_at'];
		assertTrue($stamp >= $before, 'the update path stamps too, not just the insert');
	},

	'the poll hands back everything when asked from scratch' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Snapshot Rating Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Snapshot Song']]);
		$songId = $ctx->songId('Snapshot Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '8.5']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'grower']);

		$response = $ctx->post(RATING_POLL_ENDPOINT, ['since' => 0]);
		assertSame(200, $response['status'], 'status');

		$mine = null;
		foreach ($response['json']['changes'] as $change) {
			if ($change['song'] === $songId) {
				$mine = $change;
			}
		}

		assertTrue($mine !== null, 'the rating came back');
		assertSame('8.5', $mine['score'], 'the score is preformatted the way the page renders it');
		assertSame('grower', $mine['note'], 'the note comes back');
		assertTrue($response['json']['cursor'] > 0, 'a cursor came back');
	},

	'the poll cursor is inclusive so a same second write cannot slip through' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Cursor Rating Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Cursor Song']]);
		$songId = $ctx->songId('Cursor Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '6']);
		$stamp = (int)$ctx->db()->query("SELECT updated_at FROM account_song WHERE song_id = {$songId}")->fetch()['updated_at'];

		$songsIn = function ($since) use ($ctx, $songId) {
			$changes = $ctx->post(RATING_POLL_ENDPOINT, ['since' => $since])['json']['changes'];
			return count(array_filter($changes, fn($change) => $change['song'] === $songId));
		};

		assertSame(1, $songsIn(0), 'a full snapshot includes it');
		assertSame(1, $songsIn($stamp), 'asking from its own second still returns it');
		assertSame(0, $songsIn($stamp + 1), 'asking from later excludes it');
	},

	'one accounts rating reaches another accounts poll' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Shared Rating Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Shared Rating Song']]);
		$songId = $ctx->songId('Shared Rating Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '9']);
		$writer = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		$ctx->ensureLoggedIn('polling_rater', 'test password', false);
		$changes = $ctx->post(RATING_POLL_ENDPOINT, ['since' => 0])['json']['changes'];

		$seen = null;
		foreach ($changes as $change) {
			if ($change['song'] === $songId) {
				$seen = $change;
			}
		}

		assertTrue($seen !== null, 'the other accounts rating is visible');
		assertSame($writer, $seen['account'], 'attributed to whoever wrote it');
		assertSame('9', $seen['score'], 'with their score');
	},

	'a cleared rating reaches the poll as empty rather than vanishing' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Cleared Poll Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Cleared Poll Song']]);
		$songId = $ctx->songId('Cleared Poll Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '3']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'meh']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => '']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '']);

		$changes = $ctx->post(RATING_POLL_ENDPOINT, ['since' => 0])['json']['changes'];

		$seen = null;
		foreach ($changes as $change) {
			if ($change['song'] === $songId) {
				$seen = $change;
			}
		}

		assertTrue($seen !== null, 'the clear is reported instead of disappearing');
		assertSame('', $seen['score'], 'score reads as empty');
		assertSame('', $seen['note'], 'note reads as empty');
	},

	'the songs page hands the browser a starting cursor' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Cursor Page Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Cursor Page Song']]);
		$ctx->post(RATING_ENDPOINT, ['id' => $ctx->songId('Cursor Page Song'), 'field' => 'score', 'value' => '2']);

		$max = (int)$ctx->db()->query("SELECT COALESCE(MAX(updated_at), 0) c FROM account_song")->fetch()['c'];

		$body = $ctx->get('/music/songs')['body'];
		assertContains('<script id="songRatingCursor" type="application/json">' . $max . '</script>', $body, 'the current cursor is on the page');
	},

	'the filtered songs page carries no php warnings' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Clean Filter Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Clean Track']]);
		$albumId = songsMakeAlbum($ctx, 'Clean Album', $artistId, [['song_id' => $ctx->songId('Clean Track'), 'position' => 1]]);

		$body = $ctx->get("/music/songs?artist={$artistId}&album={$albumId}")['body'];
		foreach (['Warning:', 'Notice:', 'Fatal error', 'Undefined variable', 'Undefined index'] as $sign) {
			assertTrue(strpos($body, $sign) === false, "page contains '{$sign}'");
		}
	},

	'a song can gain a second artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$first = $ctx->makeArtist('Aaa Second Artist Owner');
		$second = $ctx->makeArtist('Zzz Second Artist Guest');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $first, 'title' => 'Two Artist Song']]);
		$songId = $ctx->songId('Two Artist Song');

		$response = $ctx->post(SONG_ARTIST_ENDPOINT, ['song_id' => $songId, 'artist_id' => $second, 'action' => 'add']);
		assertSame('ok', $response['json']['status'], 'status');

		$linked = $ctx->db()->query("SELECT artist_id FROM song_artist WHERE song_id = {$songId} ORDER BY artist_id")->fetchAll(PDO::FETCH_COLUMN);
		assertSame([$first, $second], array_map('intval', $linked), 'both artists are linked');

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertSame('Aaa Second Artist Owner, Zzz Second Artist Guest', songsCellValue($card, 'artist'), 'the display shows both artists');
	},

	'adding the same artist twice is a duplicate' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Repeat Artist Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Repeat Artist Song']]);
		$songId = $ctx->songId('Repeat Artist Song');

		$response = $ctx->post(SONG_ARTIST_ENDPOINT, ['song_id' => $songId, 'artist_id' => $artistId, 'action' => 'add']);
		assertSame('duplicate', $response['json']['status'], 'status');

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song_artist WHERE song_id = {$songId}")->fetch()['c'];
		assertSame(1, $count, 'no second row was created');
	},

	'linking a nonexistent artist is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bad Artist Link Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Bad Artist Link Song']]);
		$songId = $ctx->songId('Bad Artist Link Song');

		$response = $ctx->post(SONG_ARTIST_ENDPOINT, ['song_id' => $songId, 'artist_id' => 999999, 'action' => 'add']);
		assertSame('error', $response['json']['status'], 'status');
	},

	'linking to an unknown song is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Orphan Link Artist');
		$response = $ctx->post(SONG_ARTIST_ENDPOINT, ['song_id' => 999999, 'artist_id' => $artistId, 'action' => 'add']);
		assertSame('error', $response['json']['status'], 'status');
	},

	'an artist can be removed from a song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$first = $ctx->makeArtist('Stays Linked Artist');
		$second = $ctx->makeArtist('Gets Unlinked Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $first, 'title' => 'Unlink Artist Song']]);
		$songId = $ctx->songId('Unlink Artist Song');
		$ctx->post(SONG_ARTIST_ENDPOINT, ['song_id' => $songId, 'artist_id' => $second, 'action' => 'add']);

		$response = $ctx->post(SONG_ARTIST_ENDPOINT, ['song_id' => $songId, 'artist_id' => $second, 'action' => 'remove']);
		assertSame('ok', $response['json']['status'], 'status');

		$linked = $ctx->db()->query("SELECT artist_id FROM song_artist WHERE song_id = {$songId}")->fetchAll(PDO::FETCH_COLUMN);
		assertSame([$first], array_map('intval', $linked), 'only the first artist remains');
	},

	'a song can be added to a second album' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Second Album Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Two Album Song']]);
		$songId = $ctx->songId('Two Album Song');
		$firstAlbum = songsMakeAlbum($ctx, 'First Linked Album', $artistId, [['song_id' => $songId, 'position' => 1]]);
		$secondAlbum = songsMakeAlbum($ctx, 'Second Linked Album', $artistId, []);

		$response = $ctx->post(SONG_ALBUM_ENDPOINT, ['song_id' => $songId, 'album_id' => $secondAlbum, 'action' => 'add']);
		assertSame('ok', $response['json']['status'], 'status');

		$albums = $ctx->db()->query("SELECT album_id FROM album_track WHERE song_id = {$songId} ORDER BY album_id")->fetchAll(PDO::FETCH_COLUMN);
		assertSame([$firstAlbum, $secondAlbum], array_map('intval', $albums), 'the song is on both albums');
	},

	'adding the same album twice is a duplicate' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Repeat Album Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Repeat Album Song']]);
		$songId = $ctx->songId('Repeat Album Song');
		$albumId = songsMakeAlbum($ctx, 'Repeat Album', $artistId, [['song_id' => $songId, 'position' => 1]]);

		$response = $ctx->post(SONG_ALBUM_ENDPOINT, ['song_id' => $songId, 'album_id' => $albumId, 'action' => 'add']);
		assertSame('duplicate', $response['json']['status'], 'status');
	},

	'adding a song to a nonexistent album is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bad Album Link Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Bad Album Link Song']]);
		$songId = $ctx->songId('Bad Album Link Song');

		$response = $ctx->post(SONG_ALBUM_ENDPOINT, ['song_id' => $songId, 'album_id' => 999999, 'action' => 'add']);
		assertSame('error', $response['json']['status'], 'status');
	},

	'a song can be removed from an album' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Unlink Album Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Unlink Album Song']]);
		$songId = $ctx->songId('Unlink Album Song');
		$albumId = songsMakeAlbum($ctx, 'Unlink Album', $artistId, [['song_id' => $songId, 'position' => 1]]);

		$response = $ctx->post(SONG_ALBUM_ENDPOINT, ['song_id' => $songId, 'album_id' => $albumId, 'action' => 'remove']);
		assertSame('ok', $response['json']['status'], 'status');

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM album_track WHERE song_id = {$songId} AND album_id = {$albumId}")->fetch()['c'];
		assertSame(0, $count, 'the track row is gone');
	},

	'a song link field can be set' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Link Set Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Link Set Song']]);
		$songId = $ctx->songId('Link Set Song');

		$response = $ctx->post(SONG_LINK_ENDPOINT, [
			'song_id' => $songId, 'field' => 'spotify_url',
			'value' => 'https://open.spotify.com/track/aaaaaaaaaaaaaaaaaaaaaa',
		]);
		assertSame('ok', $response['json']['status'], 'status');
		assertSame('aaaaaaaaaaaaaaaaaaaaaa', $response['json']['value'], 'the response echoes the stripped id');

		$stored = $ctx->db()->query("SELECT spotify_url FROM song_link WHERE song_id = {$songId}")->fetch();
		assertSame('aaaaaaaaaaaaaaaaaaaaaa', $stored['spotify_url'], 'only the id is stored, not the full url');
	},

	'setting a second field on the same song fills in the other column' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Link Second Field Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Link Second Field Song']]);
		$songId = $ctx->songId('Link Second Field Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'spotify_url', 'value' => 'https://open.spotify.com/track/bbbbbbbbbbbbbbbbbbbbbb']);

		$response = $ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'youtube_url', 'value' => 'https://youtube.com/watch?v=xyz']);
		assertSame('ok', $response['json']['status'], 'status');

		$stored = $ctx->db()->query("SELECT spotify_url, youtube_url FROM song_link WHERE song_id = {$songId}")->fetch();
		assertSame('bbbbbbbbbbbbbbbbbbbbbb', $stored['spotify_url'], 'spotify_url untouched');
		assertSame('xyz', $stored['youtube_url'], 'youtube_url stored on the same row, stripped to its id');
	},

	'a link field can be updated in place' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Link Update Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Link Update Song']]);
		$songId = $ctx->songId('Link Update Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'other_url', 'value' => 'https://example.com/old']);

		$response = $ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'other_url', 'value' => 'https://example.com/new']);
		assertSame('ok', $response['json']['status'], 'status');

		$stored = $ctx->db()->query("SELECT other_url FROM song_link WHERE song_id = {$songId}")->fetch();
		assertSame('https://example.com/new', $stored['other_url'], 'other_url updated');
	},

	'a link field can be cleared' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Link Clear Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Link Clear Song']]);
		$songId = $ctx->songId('Link Clear Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'filepath', 'value' => 'D:\\Music\\song.mp3']);

		$response = $ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'filepath', 'value' => '']);
		assertSame('ok', $response['json']['status'], 'status');

		$stored = $ctx->db()->query("SELECT filepath FROM song_link WHERE song_id = {$songId}")->fetch();
		assertSame(null, $stored['filepath'], 'filepath cleared back to null');
	},

	'setting an unknown link field is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Link Bad Field Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Link Bad Field Song']]);
		$songId = $ctx->songId('Link Bad Field Song');

		foreach (['url', 'label', 'apple_music_url', '', ['spotify_url']] as $field) {
			$response = $ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => $field, 'value' => 'https://example.com']);
			assertSame(400, $response['status'], 'status');
			assertTrue(isset($response['json']['error']), 'body carries an error key');
		}
	},

	'setting a link field on an unknown song is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(SONG_LINK_ENDPOINT, ['song_id' => 999999, 'field' => 'spotify_url', 'value' => 'https://open.spotify.com/track/cccccccccccccccccccccc']);
		assertSame('error', $response['json']['status'], 'status');
	},

	'the songs page carries a song\'s links as json for the edit card' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Data Links Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Data Links Song']]);
		$songId = $ctx->songId('Data Links Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'other_url', 'value' => 'https://example.com/data-link']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertTrue(preg_match('/data-links="([^"]*)"/', $card, $m) === 1, 'the card carries a data-links attribute');

		$links = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
		assertSame([
			'spotify_url' => null,
			'youtube_url' => null,
			'soundcloud_url' => null,
			'bandcamp_url' => null,
			'filepath' => null,
			'other_url' => 'https://example.com/data-link',
		], $links, 'the attribute decodes to the stored links, fixed columns included');
	},

	'a spotify link renders as an embed on the card' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Embed Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Embed Song']]);
		$songId = $ctx->songId('Embed Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'spotify_url', 'value' => 'https://open.spotify.com/track/ddddddddddddddddddddddd']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertContains('songSpotifyEmbed', $card, 'the embed container is present');
		assertContains('/embed/track/ddddddddddddddddddddddd', $card, 'the iframe points at the right track');
	},

	'a bare spotify track id embeds without a full url' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bare Spotify Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Bare Spotify Song']]);
		$songId = $ctx->songId('Bare Spotify Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'spotify_url', 'value' => '4iV5W9uYEdYUVa79Axb7Rh']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertContains('songSpotifyEmbed', $card, 'the embed container is present');
		assertContains('/embed/track/4iV5W9uYEdYUVa79Axb7Rh', $card, 'the iframe points at the pasted id');

		$stored = $ctx->db()->query("SELECT spotify_url FROM song_link WHERE song_id = {$songId}")->fetch();
		assertSame('4iV5W9uYEdYUVa79Axb7Rh', $stored['spotify_url'], 'the bare id is stored as-is, not expanded into a url');
	},

	'a bare youtube video id embeds without a full url' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bare Youtube Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Bare Youtube Song']]);
		$songId = $ctx->songId('Bare Youtube Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'youtube_url', 'value' => 'dQw4w9WgXcQ']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertContains('songYoutubeEmbed', $card, 'the embed container is present');
		assertContains('/embed/dQw4w9WgXcQ', $card, 'the iframe points at the pasted id');

		$stored = $ctx->db()->query("SELECT youtube_url FROM song_link WHERE song_id = {$songId}")->fetch();
		assertSame('dQw4w9WgXcQ', $stored['youtube_url'], 'the bare id is stored as-is, not expanded into a url');
	},

	'a youtube link renders as an embed too' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Youtube Embed Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Youtube Embed Song']]);
		$songId = $ctx->songId('Youtube Embed Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'youtube_url', 'value' => 'https://youtube.com/watch?v=86cl_p3uw5E']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertContains('songYoutubeEmbed', $card, 'the embed container is present');
		assertContains('/embed/86cl_p3uw5E', $card, 'the iframe points at the right video');
		assertContains('width="280"', $card, 'the youtube embed matches the other embeds\' width');
	},

	'a soundcloud link renders as an embed too' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Soundcloud Embed Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Soundcloud Embed Song']]);
		$songId = $ctx->songId('Soundcloud Embed Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'soundcloud_url', 'value' => 'https://soundcloud.com/artist/track']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertContains('songSoundcloudEmbed', $card, 'the embed container is present');
		assertContains('w.soundcloud.com/player/?url=' . urlencode('https://soundcloud.com/artist/track'), $card, 'the iframe points at the right track');
	},

	'an other link renders as a plain chip, not an embed' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Chip Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Chip Song']]);
		$songId = $ctx->songId('Chip Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'other_url', 'value' => 'https://example.com/track']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertContains('href="https://example.com/track"', $card, 'the chip links straight to the stored url');
		assertContains('>https://example.com/track<', $card, 'the chip text is the raw link, not the "Other" label');
		assertTrue(strpos($card, 'songSpotifyEmbed') === false, 'no spotify embed is built');
		assertTrue(strpos($card, 'songYoutubeEmbed') === false, 'no youtube embed is built');
		assertTrue(strpos($card, 'songSoundcloudEmbed') === false, 'no soundcloud embed is built');
	},

	'a bandcamp link renders as a plain, labelled chip' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bandcamp Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Bandcamp Song']]);
		$songId = $ctx->songId('Bandcamp Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'bandcamp_url', 'value' => 'https://example.bandcamp.com/album/example']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertContains('href="https://example.bandcamp.com/album/example"', $card, 'the chip links straight to the stored url');
		assertContains('>Bandcamp<', $card, 'the chip text is the field label, not the raw link');
		assertTrue(strpos($card, 'songSpotifyEmbed') === false, 'no spotify embed is built');
		assertTrue(strpos($card, 'songYoutubeEmbed') === false, 'no youtube embed is built');
		assertTrue(strpos($card, 'songSoundcloudEmbed') === false, 'no soundcloud embed is built');
	},

	'a link in the table points at the platform, not back at this site' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Outward Link Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Outward Link Song']]);
		$songId = $ctx->songId('Outward Link Song');

		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'spotify_url', 'value' => '4cOdK2wGLETKBW3PvgPWqT']);
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'youtube_url', 'value' => 'dQw4w9WgXcQ']);
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'bandcamp_url', 'value' => 'https://band.example/track/y']);
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'other_url', 'value' => 'example.com/thing']);

		$row = songsRowFor($ctx->get('/music/songs')['body'], $songId);

		assertContains('href="https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT"', $row, 'a bare spotify id becomes a spotify url');
		assertContains('href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"', $row, 'a bare youtube id becomes a youtube url');
		assertContains('href="https://band.example/track/y"', $row, 'a stored url is left as it is');
		assertContains('href="https://example.com/thing"', $row, 'a url with no scheme gets one rather than going relative');

		assertTrue(strpos($row, 'href="dQw4w9WgXcQ"') === false, 'the bare id is never used as the href, which would resolve against /music/');
	},

	'a filepath link shows its value on the card, not just a bare label' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Filepath Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Filepath Song']]);
		$songId = $ctx->songId('Filepath Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'filepath', 'value' => 'blah/foo/bar']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $songId);
		assertContains('>File path: blah/foo/bar<', $card, 'the chip text carries the actual path, not just the label');
	},

	'the filepath carries the hooks that open and flash its card' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Path Hook Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Path Hook Song']]);
		$songId = $ctx->songId('Path Hook Song');
		$ctx->post(SONG_LINK_ENDPOINT, ['song_id' => $songId, 'field' => 'filepath', 'value' => 'blah/hook/path']);

		$body = $ctx->get('/music/songs')['body'];

		$row = songsRowFor($body, $songId);
		assertTrue(preg_match('/<abbr class="songLinkAbbr" data-path="blah\/hook\/path"/', $row) === 1, 'the table abbr is the click target the js looks for');

		$card = songsCardFor($body, $songId);
		assertContains('songLinkChipWrap', $card, 'the card chip is the element the flash lands on');
	},

	'a song year can be set' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Year Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Year Song']]);
		$songId = $ctx->songId('Year Song');

		$response = $ctx->post(SONG_YEAR_ENDPOINT, ['song_id' => $songId, 'value' => '1995']);
		assertSame('ok', $response['json']['status'], 'status');
		assertSame(1995, $response['json']['value'], 'the response echoes the stored year');

		$stored = $ctx->db()->query("SELECT year FROM song WHERE id = {$songId}")->fetch();
		assertSame(1995, (int)$stored['year'], 'stored year');
	},

	'a song year can be cleared' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Year Clear Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Year Clear Song']]);
		$songId = $ctx->songId('Year Clear Song');
		$ctx->post(SONG_YEAR_ENDPOINT, ['song_id' => $songId, 'value' => '2001']);

		$response = $ctx->post(SONG_YEAR_ENDPOINT, ['song_id' => $songId, 'value' => '']);
		assertSame('ok', $response['json']['status'], 'status');

		$stored = $ctx->db()->query("SELECT year FROM song WHERE id = {$songId}")->fetch();
		assertSame(null, $stored['year'], 'year cleared back to null');
	},

	'a non-numeric song year is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bad Year Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Bad Year Song']]);
		$songId = $ctx->songId('Bad Year Song');

		$response = $ctx->post(SONG_YEAR_ENDPOINT, ['song_id' => $songId, 'value' => 'nineteen-ninety-five']);
		assertSame('error', $response['json']['status'], 'status');

		$stored = $ctx->db()->query("SELECT year FROM song WHERE id = {$songId}")->fetch();
		assertSame(null, $stored['year'], 'nothing was stored');
	},

	'setting a year on an unknown song is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(SONG_YEAR_ENDPOINT, ['song_id' => 999999, 'value' => '2000']);
		assertSame('error', $response['json']['status'], 'status');
	},

	'writing a score logs an audit row' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Audit Song']]);
		$songId = $ctx->songId('Audit Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '7']);

		$row = $ctx->db()->query("SELECT * FROM rating_audit WHERE song_id = {$songId}")->fetch();
		assertTrue($row !== false, 'an audit row was written');
		assertSame('score', $row['field'], 'the field is recorded');
		assertSame('7', $row['value'], 'the new value is recorded');
		assertSame(null, $row['previous_value'], 'there was nothing there before');
		assertTrue((int)$row['created_at'] > 0, 'stamped with a time');
	},

	'editing a rating keeps the earlier value as history' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit History Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Audit History Song']]);
		$songId = $ctx->songId('Audit History Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '4']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '9']);

		$rows = $ctx->db()->query("SELECT value, previous_value FROM rating_audit WHERE song_id = {$songId} ORDER BY id")->fetchAll();
		assertSame(2, count($rows), 'the edit is a second row, not an overwrite');
		assertSame('9', $rows[1]['value'], 'the newer value');
		assertSame('4', $rows[1]['previous_value'], 'and what it replaced');
	},

	'rewriting the same value logs nothing' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Repeat Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Audit Repeat Song']]);
		$songId = $ctx->songId('Audit Repeat Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'same']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'same']);

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM rating_audit WHERE song_id = {$songId}")->fetch()['c'];
		assertSame(1, $count, 'a resubmitted identical value is not an event');
	},

	'clearing a rating logs an empty value' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Clear Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Audit Clear Song']]);
		$songId = $ctx->songId('Audit Clear Song');

		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'temporary']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => '']);

		$row = $ctx->db()->query("SELECT value, previous_value FROM rating_audit WHERE song_id = {$songId} ORDER BY id DESC LIMIT 1")->fetch();
		assertSame(null, $row['value'], 'the clear is recorded as empty');
		assertSame('temporary', $row['previous_value'], 'with what was wiped');
	},

	'the poll hands back each audit event exactly once' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Poll Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Audit Poll Song']]);
		$songId = $ctx->songId('Audit Poll Song');

		$auditFrom = songsAuditCursor($ctx);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '5']);

		$eventsFor = function ($response) use ($songId) {
			return array_values(array_filter($response['json']['events'], fn($event) => $event['songId'] === $songId));
		};

		$first = $ctx->post(RATING_POLL_ENDPOINT, ['since' => 0, 'sinceAudit' => $auditFrom]);
		assertSame(1, count($eventsFor($first)), 'the event arrives');

		$second = $ctx->post(RATING_POLL_ENDPOINT, ['since' => 0, 'sinceAudit' => $first['json']['auditCursor']]);
		assertSame(0, count($eventsFor($second)), 'and is not delivered again');
	},

	'an audit event names the rater and the song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Label Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Audit Label Song']]);
		$songId = $ctx->songId('Audit Label Song');

		$auditFrom = songsAuditCursor($ctx);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '8']);

		$ctx->ensureLoggedIn('audit_watcher', 'test password', false);
		$events = $ctx->post(RATING_POLL_ENDPOINT, ['since' => 0, 'sinceAudit' => $auditFrom])['json']['events'];

		$seen = null;
		foreach ($events as $event) {
			if ($event['songId'] === $songId) {
				$seen = $event;
			}
		}

		assertTrue($seen !== null, 'the other accounts write is visible');
		assertSame('test_runner', $seen['name'], 'attributed by name');
		assertSame('Audit Label Artist — Audit Label Song', $seen['songLabel'], 'labelled artist and title');
		assertSame('8', $seen['value'], 'with the value written');
		assertSame(false, $seen['mine'], 'and marked as somebody elses');
	},

	'an edit event carries what the value used to be' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Previous Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Audit Previous Song']]);
		$songId = $ctx->songId('Audit Previous Song');

		$auditFrom = songsAuditCursor($ctx);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '2']);
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '6']);

		$events = $ctx->post(RATING_POLL_ENDPOINT, ['since' => 0, 'sinceAudit' => $auditFrom])['json']['events'];
		$mine = array_values(array_filter($events, fn($event) => $event['songId'] === $songId));

		assertSame(2, count($mine), 'both writes came back');
		assertSame(null, $mine[0]['previousValue'], 'the first write replaced nothing');
		assertSame('2', $mine[1]['previousValue'], 'the second carries what it replaced');
		assertSame('6', $mine[1]['value'], 'alongside the new value');
	},

	'the songs page seeds the audit cursor' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Seed Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Audit Seed Song']]);
		$songId = $ctx->songId('Audit Seed Song');
		$ctx->post(RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '3']);

		$latest = (int)$ctx->db()->query("SELECT MAX(id) m FROM rating_audit")->fetch()['m'];
		$body = $ctx->get('/music/songs')['body'];

		assertContains('<script id="songAuditCursor" type="application/json">' . $latest . '</script>', $body, 'the page starts from the newest event');
	},

	'a duration given as mm:ss is stored as seconds' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Duration Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Duration Song']]);
		$songId = $ctx->songId('Duration Song');

		$response = $ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => $songId, 'value' => '3:45']);
		assertSame('ok', $response['json']['status'], 'status');
		assertSame(225, $response['json']['value'], 'the response echoes the stored seconds');

		$stored = $ctx->db()->query("SELECT duration FROM song WHERE id = {$songId}")->fetch();
		assertSame(225, (int)$stored['duration'], 'stored duration');
	},

	'a duration can be given as bare seconds past a minute' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Seconds Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Seconds Song']]);
		$songId = $ctx->songId('Seconds Song');

		$response = $ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => $songId, 'value' => '90']);
		assertSame('ok', $response['json']['status'], 'status');
		assertSame(90, $response['json']['value'], 'ninety seconds is ninety seconds, not ninety minutes');
	},

	'a song duration can be cleared' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Duration Clear Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Duration Clear Song']]);
		$songId = $ctx->songId('Duration Clear Song');
		$ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => $songId, 'value' => '2:00']);

		$response = $ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => $songId, 'value' => '']);
		assertSame('ok', $response['json']['status'], 'status');

		$stored = $ctx->db()->query("SELECT duration FROM song WHERE id = {$songId}")->fetch();
		assertSame(null, $stored['duration'], 'duration cleared back to null');
	},

	'a malformed duration is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Bad Duration Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Bad Duration Song']]);
		$songId = $ctx->songId('Bad Duration Song');

		foreach (['three minutes', '3:7', '3:60', '3:45:10'] as $bad) {
			$response = $ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => $songId, 'value' => $bad]);
			assertSame('error', $response['json']['status'], "refused {$bad}");
		}

		$stored = $ctx->db()->query("SELECT duration FROM song WHERE id = {$songId}")->fetch();
		assertSame(null, $stored['duration'], 'nothing was stored');
	},

	'setting a duration on an unknown song is refused' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => 999999, 'value' => '3:45']);
		assertSame('error', $response['json']['status'], 'status');
	},

	'the songs page shows a duration as mm:ss' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Shown Duration Artist');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Shown Duration Song']]);
		$songId = $ctx->songId('Shown Duration Song');
		$ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => $songId, 'value' => '605']);

		$body = $ctx->get('/music/songs')['body'];
		assertSame('10:05', songsCellValue(songsRowFor($body, $songId), 'duration'), 'the table pads the seconds');
		assertContains('>10:05<', songsCardFor($body, $songId), 'the card shows it too');
	},

	'the card modal carries previous and next buttons alongside close' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		assertContains('id="songCardModalPrev"', $body, 'the modal offers a previous button');
		assertContains('id="songCardModalNext"', $body, 'the modal offers a next button');
		assertContains('id="songCardModalClose"', $body, 'and still offers close');

		$actions = substr($body, strpos($body, 'songCardModalActions'));
		$prev = strpos($actions, 'songCardModalPrev');
		$next = strpos($actions, 'songCardModalNext');
		$close = strpos($actions, 'songCardModalClose');
		assertTrue($prev < $next && $next < $close, 'they read previous, next, close');
	},

	'sorting by a rater score puts unrated and cleared songs first ascending and last descending' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Score Sort Owner');
		$mine = ['Score Sort High', 'Score Sort Low', 'Score Sort Never', 'Score Sort Cleared'];
		foreach ($mine as $title) {
			$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => $title]]);
		}

		$ctx->post(RATING_ENDPOINT, ['id' => $ctx->songId('Score Sort High'), 'field' => 'score', 'value' => '9']);
		$ctx->post(RATING_ENDPOINT, ['id' => $ctx->songId('Score Sort Low'), 'field' => 'score', 'value' => '2']);

		$clearedId = $ctx->songId('Score Sort Cleared');
		$ctx->post(RATING_ENDPOINT, ['id' => $clearedId, 'field' => 'score', 'value' => '7']);
		$ctx->post(RATING_ENDPOINT, ['id' => $clearedId, 'field' => 'score', 'value' => '']);

		$neverId = $ctx->songId('Score Sort Never');
		$hasRow = (int)$ctx->db()->query("SELECT COUNT(*) c FROM account_song WHERE song_id = {$neverId}")->fetch()['c'];
		assertSame(0, $hasRow, 'the unrated song has no account_song row at all');
		$clearedRow = $ctx->db()->query("SELECT score FROM account_song WHERE song_id = {$clearedId}")->fetch();
		assertSame(null, $clearedRow['score'], 'the cleared song has a row with a null score');

		$accountId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		$ordered = function ($dir) use ($ctx, $mine, $accountId) {
			$titles = songsValuesInOrder($ctx->get("/music/songs?sort=score_{$accountId}&dir={$dir}")['body'], 'title');
			return array_values(array_filter($titles, fn($t) => in_array($t, $mine, true)));
		};

		assertSame(
			['Score Sort Never', 'Score Sort Cleared', 'Score Sort Low', 'Score Sort High'],
			$ordered('asc'),
			'ascending puts both kinds of empty first, in id order, then the scores'
		);

		assertSame(
			['Score Sort High', 'Score Sort Low', 'Score Sort Never', 'Score Sort Cleared'],
			$ordered('desc'),
			'descending reverses the scores but the empty block stays in ascending id order'
		);
	},

	'sorting by a rater note ignores case and puts songs without a note first' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Note Sort Owner');
		$mine = ['Note Sort Banana', 'Note Sort Apple', 'Note Sort None'];
		foreach ($mine as $title) {
			$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => $title]]);
		}

		$ctx->post(RATING_ENDPOINT, ['id' => $ctx->songId('Note Sort Banana'), 'field' => 'note', 'value' => 'Banana']);
		$ctx->post(RATING_ENDPOINT, ['id' => $ctx->songId('Note Sort Apple'), 'field' => 'note', 'value' => 'apple']);

		$accountId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		$ordered = function ($dir) use ($ctx, $mine, $accountId) {
			$titles = songsValuesInOrder($ctx->get("/music/songs?sort=note_{$accountId}&dir={$dir}")['body'], 'title');
			return array_values(array_filter($titles, fn($t) => in_array($t, $mine, true)));
		};

		assertSame(
			['Note Sort None', 'Note Sort Apple', 'Note Sort Banana'],
			$ordered('asc'),
			'lowercase apple sorts before uppercase Banana, so the sort is case insensitive'
		);

		assertSame(
			['Note Sort Banana', 'Note Sort Apple', 'Note Sort None'],
			$ordered('desc'),
			'descending reverses it and leaves the unnoted song last'
		);
	},

	'each rater score column sorts by that rater only' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Two Rater Owner');
		$mine = ['Two Rater First', 'Two Rater Second'];
		foreach ($mine as $title) {
			$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => $title]]);
		}

		$firstId = $ctx->songId('Two Rater First');
		$secondId = $ctx->songId('Two Rater Second');

		$ctx->post(RATING_ENDPOINT, ['id' => $firstId, 'field' => 'score', 'value' => '1']);
		$ctx->post(RATING_ENDPOINT, ['id' => $secondId, 'field' => 'score', 'value' => '9']);

		$ctx->ensureLoggedIn('second_rater', 'test password', false);
		$ctx->post(RATING_ENDPOINT, ['id' => $firstId, 'field' => 'score', 'value' => '9']);
		$ctx->post(RATING_ENDPOINT, ['id' => $secondId, 'field' => 'score', 'value' => '1']);

		$ctx->ensureLoggedIn();

		$ids = $ctx->db()->query("SELECT id, account_name FROM account WHERE account_name IN ('test_runner', 'second_rater')")->fetchAll();
		$byName = [];
		foreach ($ids as $row) {
			$byName[$row['account_name']] = (int)$row['id'];
		}

		$ordered = function ($accountId) use ($ctx, $mine) {
			$titles = songsValuesInOrder($ctx->get("/music/songs?sort=score_{$accountId}&dir=asc")['body'], 'title');
			return array_values(array_filter($titles, fn($t) => in_array($t, $mine, true)));
		};

		assertSame(['Two Rater First', 'Two Rater Second'], $ordered($byName['test_runner']), 'your column sorts by your scores');
		assertSame(['Two Rater Second', 'Two Rater First'], $ordered($byName['second_rater']), 'their column sorts by theirs, the other way round');

		$chunk = songsRowFor($ctx->get('/music/songs')['body'], $firstId);
		assertSame('1', songsCellValue($chunk, "score_{$byName['test_runner']}"), 'your cell holds your score');
		assertSame('9', songsCellValue($chunk, "score_{$byName['second_rater']}"), 'their cell holds theirs, not yours');
	},

	'the songs page renders exactly one row per song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$songs = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song")->fetch()['c'];
		assertTrue($songs > 1, 'there are songs to count');

		$rows = count(songsRowChunks($ctx->get('/music/songs')['body']));
		assertSame($songs, $rows, 'one row per song, so no join multiplies a song into several');
	},

	'sorting by a rater column keeps every song on the page' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$accountId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		$byId = array_map('intval', songsValuesInOrder($ctx->get('/music/songs?sort=id&dir=asc')['body'], 'id'));
		$byScore = array_map('intval', songsValuesInOrder($ctx->get("/music/songs?sort=score_{$accountId}&dir=asc")['body'], 'id'));

		assertTrue(count($byId) > 1, 'there are rows to compare');

		sort($byId);
		sort($byScore);
		assertSame($byId, $byScore, 'sorting by a rater score drops no song, so the join stays a left join');
	},

	'a song nobody has rated still carries an empty cell for every rater' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Unrated Owner');
		$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Unrated By Anyone']]);
		$songId = $ctx->songId('Unrated By Anyone');

		$chunk = songsRowFor($ctx->get('/music/songs')['body'], $songId);
		assertTrue($chunk !== null, 'the song has a row');

		$accounts = $ctx->db()->query("SELECT id FROM account")->fetchAll(PDO::FETCH_COLUMN);
		assertTrue(count($accounts) > 1, 'there is more than one rater to check');

		foreach ($accounts as $accountId) {
			assertSame('', songsCellValue($chunk, "score_{$accountId}"), "account {$accountId} has an empty score cell");
			assertSame('', songsCellValue($chunk, "note_{$accountId}"), "account {$accountId} has an empty note cell");
		}
	},

	'the songs page sorts by year and by duration' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Year Sort Owner');
		$mine = ['Year Sort Late', 'Year Sort Early', 'Year Sort None'];
		foreach ($mine as $title) {
			$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => $title]]);
		}

		$ctx->post(SONG_YEAR_ENDPOINT, ['song_id' => $ctx->songId('Year Sort Late'), 'value' => '2010']);
		$ctx->post(SONG_YEAR_ENDPOINT, ['song_id' => $ctx->songId('Year Sort Early'), 'value' => '1990']);

		$ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => $ctx->songId('Year Sort Late'), 'value' => '5:00']);
		$ctx->post(SONG_DURATION_ENDPOINT, ['song_id' => $ctx->songId('Year Sort Early'), 'value' => '1:00']);

		$ordered = function ($path) use ($ctx, $mine) {
			$titles = songsValuesInOrder($ctx->get($path)['body'], 'title');
			return array_values(array_filter($titles, fn($t) => in_array($t, $mine, true)));
		};

		assertSame(
			['Year Sort None', 'Year Sort Early', 'Year Sort Late'],
			$ordered('/music/songs?sort=year&dir=asc'),
			'ascending by year puts the song with no year first'
		);

		assertSame(
			['Year Sort Late', 'Year Sort Early', 'Year Sort None'],
			$ordered('/music/songs?sort=year&dir=desc'),
			'descending by year reverses it'
		);

		assertSame(
			['Year Sort None', 'Year Sort Early', 'Year Sort Late'],
			$ordered('/music/songs?sort=duration&dir=asc'),
			'ascending by duration puts the song with no duration first'
		);
	},

];
