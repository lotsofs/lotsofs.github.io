<?php

const ALBUM_ENDPOINT = '/music/ajax/album';

function withPositions($tracks) {
	$claimed = [];
	foreach ($tracks as $track) {
		if ($track['position'] !== null) {
			$claimed[$track['position']] = true;
		}
	}

	$next = 1;
	$out = [];
	foreach ($tracks as $track) {
		if ($track['position'] !== null) {
			$out[] = $track;
			continue;
		}
		while (isset($claimed[$next])) {
			$next++;
		}
		$claimed[$next] = true;
		$track['position'] = $next;
		$out[] = $track;
	}

	return $out;
}

function makeSong($ctx, $artistId, $title) {
	$response = $ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => $title]]);
	return (int)$response['json'][0]['song_id'];
}

return [

	'the song endpoint hands back an id for new and duplicate songs' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Id Returning Owner');

		$first = $ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Returns An Id']]);
		assertSame('ok', $first['json'][0]['status'], 'first status');
		assertTrue(!empty($first['json'][0]['song_id']), 'a new song reports its id');

		$second = $ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Returns An Id']]);
		assertSame('duplicate', $second['json'][0]['status'], 'second status');
		assertSame($first['json'][0]['song_id'], $second['json'][0]['song_id'], 'a duplicate reports the same id');
	},

	'the pasted rows can be joined back to the song results' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$pasted = [
			['Artist' => 'Joinable Band', 'Title' => 'Joinable One', 'Album' => 'Joinable Record', 'Track' => 1],
			['Artist' => 'Joinable Band', 'Title' => 'Joinable Two', 'Album' => 'Joinable Record', 'Track' => null],
			['Artist' => 'Joinable Band', 'Title' => 'Joinable Loose', 'Album' => '', 'Track' => null],
		];

		$artistPayload = [];
		foreach (array_unique(array_column($pasted, 'Artist')) as $name) {
			$artistPayload[] = ['artist_id' => 'new', 'group' => $name, 'og_name' => $name, 'provided_name' => $name, 'is_actual' => true];
		}
		$artistResults = $ctx->post('/music/ajax/artist-alias', $artistPayload)['json'];

		$artistIdByProvidedName = [];
		foreach ($artistResults as $result) {
			if ($result['artist_id']) {
				$artistIdByProvidedName[$result['provided_name']] = $result['artist_id'];
			}
		}
		assertSame(1, count($artistIdByProvidedName), 'the artist resolved');

		$songPayload = [];
		foreach ($pasted as $item) {
			$songPayload[] = ['artist_id' => (string)$artistIdByProvidedName[$item['Artist']], 'title' => $item['Title']];
		}
		$songResults = $ctx->post('/music/ajax/song', $songPayload)['json'];

		$songIdByKey = [];
		foreach ($songResults as $result) {
			if (!empty($result['song_id'])) {
				$songIdByKey[$result['artist_id'] . "\t" . $result['title']] = $result['song_id'];
			}
		}
		assertSame(3, count($songIdByKey), 'every song came back with an id');

		$albums = [];
		foreach ($pasted as $item) {
			if ($item['Album'] === '') {
				continue;
			}
			$artistId = $artistIdByProvidedName[$item['Artist']] ?? null;
			$songId = $artistId ? ($songIdByKey[$artistId . "\t" . $item['Title']] ?? null) : null;
			if (!$songId) {
				continue;
			}
			$albums[$item['Album']][] = ['song_id' => $songId, 'position' => $item['Track']];
		}

		assertSame(['Joinable Record'], array_keys($albums), 'one album was grouped');
		assertSame(2, count($albums['Joinable Record']), 'the album gathered both of its songs');
		assertSame([1, 2], array_column(withPositions($albums['Joinable Record']), 'position'), 'the blank track number filled in');
	},

	'an album stores its alias, artist, year and tracks' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Album Owner');
		$one = makeSong($ctx, $artistId, 'Album Track One');
		$two = makeSong($ctx, $artistId, 'Album Track Two');

		$response = $ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'A Fine Record',
			'album_id' => 'new',
			'og_name' => 'A Fine Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '1968',
			'tracks' => [['song_id' => $one, 'position' => 1], ['song_id' => $two, 'position' => 2]],
		]]);

		assertSame('ok', $response['json'][0]['status'], 'status');
		$albumId = (int)$response['json'][0]['album_id'];

		$album = $ctx->db()->query("SELECT artist_id, release_year FROM album WHERE id = {$albumId}")->fetch();
		assertSame($artistId, (int)$album['artist_id'], 'attributed to the artist');
		assertSame(1968, (int)$album['release_year'], 'year stored');

		$alias = $ctx->db()->query("SELECT name, is_actual FROM album_alias WHERE album_id = {$albumId}")->fetch();
		assertSame('A Fine Record', $alias['name'], 'alias stored');
		assertSame(1, (int)$alias['is_actual'], 'and marked actual');

		$tracks = $ctx->db()->query("SELECT song_id, position FROM album_track WHERE album_id = {$albumId} ORDER BY position")->fetchAll();
		assertSame(2, count($tracks), 'both tracks attached');
		assertSame($one, (int)$tracks[0]['song_id'], 'first track');
		assertSame(2, (int)$tracks[1]['position'], 'second position');
	},

	'a custom-named new album stores the typed name and keeps the pasted one as an alias' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Custom Album Owner');
		$songId = makeSong($ctx, $artistId, 'Custom Album Song');

		$response = $ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'C:\\music\\ost\\final',
			'album_id' => 'new',
			'og_name' => 'Frozen Synapse: Original Soundtrack',
			'is_actual' => true,
			'also_alias_provided_name' => true,
			'artist_id' => $artistId,
			'release_year' => '2012',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]]);

		assertSame('ok', $response['json'][0]['status'], 'status');
		$albumId = (int)$response['json'][0]['album_id'];

		$actual = $ctx->db()->query("SELECT name FROM album_alias WHERE album_id = {$albumId} AND is_actual = 1")->fetch()['name'];
		assertSame('Frozen Synapse: Original Soundtrack', $actual, 'the typed name is the actual one');

		$names = $ctx->db()->query("SELECT name FROM album_alias WHERE album_id = {$albumId} ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
		assertSame(['C:\\music\\ost\\final', 'Frozen Synapse: Original Soundtrack'], $names, 'the pasted string is kept as a second alias');
	},

	'a song stored under its own name reports no album alias' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Plain Track Owner');
		$response = $ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Plain Track']]);

		assertSame(null, $response['json'][0]['song_alias_id'], 'the actual name is not an album specific alias');
	},

	'a song listed differently reports the alias it was listed under' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relisted Track Owner');
		$songId = makeSong($ctx, $artistId, 'Canonical Name');

		$response = $ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Name On The Sleeve',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);

		$aliasId = (int)$response['json'][0]['song_alias_id'];
		assertTrue($aliasId > 0, 'an alias id came back');

		$alias = $ctx->db()->query("SELECT name, is_actual FROM song_alias WHERE id = {$aliasId}")->fetch();
		assertSame('Name On The Sleeve', $alias['name'], 'it points at the album specific spelling');
		assertSame(0, (int)$alias['is_actual'], 'which is never the actual name');
	},

	'a matched song whose spelling was not stored reports no alias' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Unstored Spelling Owner');
		$songId = makeSong($ctx, $artistId, 'Kept Name');

		$response = $ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Discarded Spelling',
			'song_id' => $songId,
		]]);

		assertSame(null, $response['json'][0]['song_alias_id'], 'nothing was stored so there is nothing to point at');
	},

	'a track remembers the alias the album listed it under' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Track Alias Owner');
		$songId = makeSong($ctx, $artistId, 'Studio Title');
		$aliased = $ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Sleeve Title',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);
		$aliasId = (int)$aliased['json'][0]['song_alias_id'];

		$response = $ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Record With Odd Titles',
			'album_id' => 'new',
			'og_name' => 'Record With Odd Titles',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '',
			'tracks' => [['song_id' => $songId, 'song_alias_id' => $aliasId, 'position' => 1]],
		]]);

		$albumId = (int)$response['json'][0]['album_id'];
		$track = $ctx->db()->query("SELECT song_id, song_alias_id FROM album_track WHERE album_id = {$albumId}")->fetch();

		assertSame($songId, (int)$track['song_id'], 'still points at the song');
		assertSame($aliasId, (int)$track['song_alias_id'], 'and at the name this album used');
	},

	'a track with no odd title leaves the alias column empty' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Ordinary Track Owner');
		$songId = makeSong($ctx, $artistId, 'Ordinary Title');

		$response = $ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Ordinary Record',
			'album_id' => 'new',
			'og_name' => 'Ordinary Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]]);

		$albumId = (int)$response['json'][0]['album_id'];
		$aliasId = $ctx->db()->query("SELECT song_alias_id FROM album_track WHERE album_id = {$albumId}")->fetch()['song_alias_id'];

		assertSame(null, $aliasId, 'stored as null');
	},

	'resubmitting a track corrects the alias rather than duplicating' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Corrected Track Owner');
		$songId = makeSong($ctx, $artistId, 'Correctable Title');
		$aliased = $ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Corrected Sleeve Title',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);
		$aliasId = (int)$aliased['json'][0]['song_alias_id'];

		$payload = [[
			'provided_name' => 'Correctable Record',
			'album_id' => 'new',
			'og_name' => 'Correctable Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]];

		$created = $ctx->post(ALBUM_ENDPOINT, $payload);
		$albumId = (int)$created['json'][0]['album_id'];

		$payload[0]['album_id'] = $albumId;
		$payload[0]['tracks'] = [['song_id' => $songId, 'song_alias_id' => $aliasId, 'position' => 1]];
		$ctx->post(ALBUM_ENDPOINT, $payload);

		$tracks = $ctx->db()->query("SELECT song_alias_id FROM album_track WHERE album_id = {$albumId}")->fetchAll();
		assertSame(1, count($tracks), 'still one track row');
		assertSame($aliasId, (int)$tracks[0]['song_alias_id'], 'the alias was filled in on the existing row');
	},

	'blank track numbers become one upwards in paste order' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$tracks = withPositions([
			['song_id' => 11, 'position' => null],
			['song_id' => 12, 'position' => null],
			['song_id' => 13, 'position' => null],
		]);

		assertSame([1, 2, 3], array_column($tracks, 'position'), 'numbered from one');
	},

	'explicit track numbers are kept including zero and gaps' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$tracks = withPositions([
			['song_id' => 11, 'position' => 0],
			['song_id' => 12, 'position' => 5],
			['song_id' => 13, 'position' => 9],
		]);

		assertSame([0, 5, 9], array_column($tracks, 'position'), 'left alone');
	},

	'a mixed paste gives blanks the lowest free positions' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$tracks = withPositions([
			['song_id' => 11, 'position' => 2],
			['song_id' => 12, 'position' => null],
			['song_id' => 13, 'position' => null],
			['song_id' => 14, 'position' => 4],
		]);

		$positions = array_column($tracks, 'position');
		assertSame([2, 1, 3, 4], $positions, 'blanks filled the gaps');
		assertSame(count($positions), count(array_unique($positions)), 'no collisions');
	},

	'a zero track number survives the round trip' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Zero Track Owner');
		$songId = makeSong($ctx, $artistId, 'Hidden Intro');

		$response = $ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Starts At Zero',
			'album_id' => 'new',
			'og_name' => 'Starts At Zero',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '',
			'tracks' => [['song_id' => $songId, 'position' => 0]],
		]]);

		$albumId = (int)$response['json'][0]['album_id'];
		$position = $ctx->db()->query("SELECT position FROM album_track WHERE album_id = {$albumId}")->fetch()['position'];
		assertSame(0, (int)$position, 'stored as zero, not dropped as falsy');
	},

	'an omitted year leaves the album without one' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Yearless Owner');
		$songId = makeSong($ctx, $artistId, 'Undated Song');

		$response = $ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Undated Record',
			'album_id' => 'new',
			'og_name' => 'Undated Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]]);

		$albumId = (int)$response['json'][0]['album_id'];
		$year = $ctx->db()->query("SELECT release_year FROM album WHERE id = {$albumId}")->fetch()['release_year'];
		assertSame(null, $year, 'stored as null');
	},

	'an album attributed to nobody keeps a null artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$first = $ctx->makeArtist('Compilation One');
		$second = $ctx->makeArtist('Compilation Two');
		$songOne = makeSong($ctx, $first, 'Comp Track One');
		$songTwo = makeSong($ctx, $second, 'Comp Track Two');

		$response = $ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Various Artists Vol 1',
			'album_id' => 'new',
			'og_name' => 'Various Artists Vol 1',
			'is_actual' => true,
			'artist_id' => null,
			'release_year' => '1999',
			'tracks' => [['song_id' => $songOne, 'position' => 1], ['song_id' => $songTwo, 'position' => 2]],
		]]);

		$albumId = (int)$response['json'][0]['album_id'];
		$album = $ctx->db()->query("SELECT artist_id FROM album WHERE id = {$albumId}")->fetch();
		assertSame(null, $album['artist_id'], 'no artist');

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM album_track WHERE album_id = {$albumId}")->fetch()['c'];
		assertSame(2, $count, 'tracks from two artists on one album');
	},

	'a second alias attaches to the same album' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Aliased Album Owner');
		$songId = makeSong($ctx, $artistId, 'Aliased Album Song');

		$created = $ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'The Beatles',
			'album_id' => 'new',
			'og_name' => 'The Beatles',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '1968',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]]);
		$albumId = (int)$created['json'][0]['album_id'];

		$ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'The White Album',
			'album_id' => $albumId,
			'og_name' => 'The White Album',
			'is_actual' => false,
			'artist_id' => $artistId,
			'release_year' => '1968',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]]);

		$names = $ctx->db()->query("SELECT name FROM album_alias WHERE album_id = {$albumId} ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
		assertSame(['The Beatles', 'The White Album'], $names, 'both names point at one album');

		$actual = (int)$ctx->db()->query("SELECT COUNT(*) c FROM album_alias WHERE album_id = {$albumId} AND is_actual = 1")->fetch()['c'];
		assertSame(1, $actual, 'still exactly one actual name');

		$albums = (int)$ctx->db()->query("SELECT COUNT(*) c FROM album_alias WHERE name = 'The White Album'")->fetch()['c'];
		assertSame(1, $albums, 'no second album was created');
	},

	'resubmitting the same album adds no duplicate tracks' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Resubmit Album Owner');
		$songId = makeSong($ctx, $artistId, 'Resubmitted Song');

		$payload = [[
			'provided_name' => 'Resubmitted Record',
			'album_id' => 'new',
			'og_name' => 'Resubmitted Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '2001',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]];

		$created = $ctx->post(ALBUM_ENDPOINT, $payload);
		$albumId = (int)$created['json'][0]['album_id'];

		$payload[0]['album_id'] = $albumId;
		$payload[0]['tracks'] = [['song_id' => $songId, 'position' => 7]];
		$ctx->post(ALBUM_ENDPOINT, $payload);

		$tracks = $ctx->db()->query("SELECT position FROM album_track WHERE album_id = {$albumId}")->fetchAll();
		assertSame(1, count($tracks), 'still one track row');
		assertSame(7, (int)$tracks[0]['position'], 'its position was corrected rather than duplicated');
	},

	'a skipped album stores nothing' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$before = (int)$ctx->db()->query("SELECT COUNT(*) c FROM album")->fetch()['c'];

		$response = $ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Never Stored',
			'album_id' => '',
			'og_name' => 'Never Stored',
			'tracks' => [],
		]]);

		assertSame('skipped', $response['json'][0]['status'], 'status');

		$after = (int)$ctx->db()->query("SELECT COUNT(*) c FROM album")->fetch()['c'];
		assertSame($before, $after, 'no album was created');
	},

	'the album endpoint is gated like the others' => function ($ctx) {
		$ctx->newSession();
		assertSame(401, $ctx->post(ALBUM_ENDPOINT, [])['status'], 'signed out');

		$ctx->ensureLoggedIn('album_viewer', 'test password', false);
		assertSame(403, $ctx->post(ALBUM_ENDPOINT, [])['status'], 'not an admin');

		$ctx->ensureLoggedIn();
		assertSame(403, $ctx->postWithoutCsrf(ALBUM_ENDPOINT, [])['status'], 'no csrf token');
	},

	'the add songs page carries the album table and no php warnings' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/add-songs')['body'];

		assertContains('id="albumTable"', $body, 'the album table is on the page');
		assertContains('id="albumNamesData"', $body, 'existing albums are handed to the script');
		assertContains('Attributed To', $body, 'the attribution column renders');
		assertContains('<div id="albumScrollSpace" hidden></div>', $body, 'the scroll space starts hidden');

		foreach (['Warning:', 'Notice:', 'Fatal error', 'Undefined variable', 'Undefined index'] as $sign) {
			assertTrue(strpos($body, $sign) === false, "page contains '{$sign}'");
		}
	},

];
