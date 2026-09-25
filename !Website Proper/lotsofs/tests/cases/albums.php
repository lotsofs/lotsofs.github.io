<?php

const ALBUM_ENDPOINT = '/music/ajax/album';
const ALBUM_CARD_ENDPOINT = '/music/ajax/album-card';
const ALBUM_OPTIONS_ENDPOINT = '/music/ajax/album-options';
const ALBUM_EDIT_ENDPOINT = '/music/ajax/album-edit';
const ALBUM_TRACK_ENDPOINT = '/music/ajax/album-track';

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

function makeAlbum($ctx, $name, $artistId, $tracks) {
	$response = $ctx->post(ALBUM_ENDPOINT, [[
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

	// A paste with no Year column sends nothing for it, and the wizard matches
	// an existing album by name, so this is the ordinary path for "a few more
	// tracks off the same record" - not an edge case.
	'importing more tracks onto an album leaves its artist and year alone' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Reimported Artist');
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Reimported First']]);
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Reimported Second']]);

		$albumId = (int)$ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Reimported Record',
			'album_id' => 'new',
			'og_name' => 'Reimported Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '1972',
			'tracks' => [['song_id' => $ctx->songId('Reimported First'), 'position' => 1]],
		]])['json'][0]['album_id'];

		$ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Reimported Record',
			'album_id' => $albumId,
			'og_name' => 'Reimported Record',
			'is_actual' => true,
			'artist_id' => '',
			'release_year' => '',
			'tracks' => [['song_id' => $ctx->songId('Reimported Second'), 'position' => 2]],
		]]);

		$row = $ctx->db()->query("SELECT artist_id, release_year FROM album WHERE id = {$albumId}")->fetch();
		assertSame((int)$artistId, (int)$row['artist_id'], 'the artist survived a second import that named none');
		assertSame(1972, (int)$row['release_year'], 'and so did the year');

		$tracks = (int)$ctx->db()->query("SELECT COUNT(*) c FROM album_track WHERE album_id = {$albumId}")->fetch()['c'];
		assertSame(2, $tracks, 'while the new track was still attached');
	},

	'the album card lists that album with its tracks in playing order' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Card Record Artist');
		foreach (['Card Track Second', 'Card Track First'] as $title) {
			$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => $title]]);
		}

		$albumId = (int)$ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Carded Record',
			'album_id' => 'new',
			'og_name' => 'Carded Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '1998',
			'tracks' => [
				['song_id' => $ctx->songId('Card Track Second'), 'position' => 2],
				['song_id' => $ctx->songId('Card Track First'), 'position' => 1],
			],
		]])['json'][0]['album_id'];

		$response = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId]);
		assertSame('ok', $response['json']['status'], 'status');

		$html = $response['json']['html'];
		assertContains('data-album-id="' . $albumId . '"', $html, 'the card names its album');
		assertContains('Carded Record', $html, 'the album name is on the card');
		assertContains('Card Record Artist', $html, 'so is the artist');
		assertContains('1998', $html, 'so is the release year');
		assertContains('/music/songs?artist=' . $artistId . '&amp;album=' . $albumId, $html, 'the card links to the album filtered song list');

		$first = strpos($html, 'Card Track First');
		$second = strpos($html, 'Card Track Second');
		assertTrue($first !== false && $second !== false, 'both tracks are listed');
		assertTrue($first < $second, 'the tracks are listed by position, not by the order they were added');

		foreach (['Warning:', 'Notice:', 'Fatal error', 'Undefined variable', 'Undefined index'] as $sign) {
			assertTrue(strpos($response['body'], $sign) === false, "the rendered card contains '{$sign}'");
		}
	},

	'the album card titles a track the way its album lists it' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Card Sleeve Artist');
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Card Studio Title']]);
		$songId = $ctx->songId('Card Studio Title');

		$aliased = $ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Card Sleeve Title',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);

		$albumId = (int)$ctx->post(ALBUM_ENDPOINT, [[
			'provided_name' => 'Card Sleeve Record',
			'album_id' => 'new',
			'og_name' => 'Card Sleeve Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '',
			'tracks' => [[
				'song_id' => $songId,
				'song_alias_id' => (int)$aliased['json'][0]['song_alias_id'],
				'position' => 1,
			]],
		]])['json'][0]['album_id'];

		$html = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'];

		assertContains('Card Sleeve Title', $html, 'the track shows the name this release credits it under');
		assertTrue(strpos($html, 'Card Studio Title') === false, 'and not the song\'s own title');
	},

	'an album card for an album that is not there says so' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => 999999]);

		assertSame('error', $response['json']['status'], 'status');
		assertTrue(empty($response['json']['html']), 'no card comes back');
	},

	'the album card is open to raters, not just admins' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Card Viewer Artist');
		$albumId = makeAlbum($ctx, 'Viewable Record', $artistId, []);

		$ctx->ensureLoggedIn('album_card_viewer', 'test password', false);
		$response = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId]);

		assertSame('ok', $response['json']['status'], 'a non admin can open a card');
		assertContains('Viewable Record', $response['json']['html'], 'and gets the album');

		$ctx->newSession();
		assertSame(401, $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['status'], 'signed out');
	},

	'the album card works out each rater\'s spread over the tracks they rated' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$titles = ['Averaged One', 'Averaged Two', 'Averaged Three', 'Averaged Four', 'Averaged Five'];

		$tracks = [];
		foreach ($titles as $index => $title) {
			$ctx->post('/music/ajax/song', [['artist_id' => $ctx->makeArtist('Averaged Artist'), 'title' => $title]]);
			$tracks[] = ['song_id' => $ctx->songId($title), 'position' => $index + 1];
		}
		$albumId = makeAlbum($ctx, 'Averaged Record', $ctx->makeArtist('Averaged Artist'), $tracks);

		foreach (['Averaged One' => '8', 'Averaged Two' => '5', 'Averaged Three' => '8', 'Averaged Four' => '2'] as $title => $score) {
			$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId($title), 'field' => 'score', 'value' => $score]);
		}

		$ctx->ensureLoggedIn('average_other_rater', 'test password', false);
		$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId('Averaged Five'), 'field' => 'score', 'value' => '3']);

		$ctx->ensureLoggedIn();
		$html = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'];

		preg_match_all('/<tr class="albumStatsRow"[^>]*>(.*?)<\/tr>/s', $html, $matches, PREG_SET_ORDER);
		assertTrue(count($matches) > 2, 'every account gets a row, not just the ones who rated something');

		$rows = [];
		foreach ($matches as $match) {
			preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $match[1], $cells, PREG_SET_ORDER);
			$values = array_map('strip_tags', array_column($cells, 1));
			$rows[$values[0]] = [
				'average' => $values[1],
				'deviation' => $values[2],
				'median' => $values[3],
				'mode' => $values[4],
				'rated' => $values[5],
				'html' => $match[1],
			];
		}

		// 8, 5, 8 and 2 out of five tracks: mean 5.75, middle pair 5 and 8,
		// 8 twice, and a population sigma of sqrt(24.75 / 4)
		assertSame('5.75', $rows['test_runner']['average'], 'the average is over the tracks that rater scored, not over every track');
		assertSame('6.5', $rows['test_runner']['median'], 'the median is the middle pair averaged');
		assertSame('8', $rows['test_runner']['mode'], 'the mode is the score given more than once');
		assertSame('2.49', $rows['test_runner']['deviation'], 'and the spread is the standard deviation of those scores');
		assertSame('4 / 5', $rows['test_runner']['rated'], 'with how many of the tracks that was');

		assertContains('style="color: hsl(', $rows['test_runner']['html'], 'the scores are coloured on the same ramp the song list uses');

		assertSame('3', $rows['average_other_rater']['average'], 'each rater is worked out separately');
		assertSame('—', $rows['average_other_rater']['mode'], 'one score each is no mode at all, not a mode of everything');
		assertSame('0', $rows['average_other_rater']['deviation'], 'a single score has no spread');

		$never = null;
		foreach ($rows as $row) {
			if ($row['rated'] === '0 / 5') {
				$never = $row;
			}
		}
		assertTrue($never !== null, 'an account that rated nothing on this album still gets a row');
		assertSame('—', $never['average'], 'and shows a dash rather than a zero');
		assertTrue(strpos($never['html'], 'hsl(') === false, 'with nothing coloured in');
	},

	'the album card graphs every score that was given, and nothing else' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Graphed Artist');
		$titles = ['Graphed One', 'Graphed Two', 'Graphed Three'];

		$tracks = [];
		foreach ($titles as $index => $title) {
			$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => $title]]);
			$tracks[] = ['song_id' => $ctx->songId($title), 'position' => $index + 1];
		}
		$albumId = makeAlbum($ctx, 'Graphed Record', $artistId, $tracks);

		$unrated = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'];
		assertTrue(strpos($unrated, 'albumGraph') === false, 'an album nobody has scored draws no graph at all');

		$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId('Graphed One'), 'field' => 'score', 'value' => '9']);
		$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId('Graphed Three'), 'field' => 'score', 'value' => '4']);

		$html = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'];

		assertContains('<svg class="albumGraph"', $html, 'one score is enough to draw the graph');
		assertSame(2, substr_count($html, '<circle class="albumGraphDot"'), 'one dot per score given, and none for the track that was skipped');
		assertContains('<title>test_runner — Graphed One: 9</title>', $html, 'a dot names the rater, the track and the score');
		assertTrue(strpos($html, 'albumGraphLine') === false, 'no line is drawn across the unrated track in between');

		$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId('Graphed Two'), 'field' => 'score', 'value' => '6']);
		$joined = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'];
		assertContains('albumGraphLine', $joined, 'filling the gap joins the dots up');

		preg_match('/<tr class="albumStatsRow"[^>]*>(.*?)<\/tr>/s', $joined, $row);
		preg_match('/albumStatsSwatch" style="background-color: (hsl\([^)]+\))"/', $row[1], $swatch);
		assertTrue(!empty($swatch), 'the rater carries a colour swatch in the stats table, which is what makes it the graph legend');
		assertContains('fill="' . $swatch[1] . '"', $joined, 'and the dots are drawn in that same colour');
	},

	'each track carries what the raters averaged it at' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Track Average Artist');
		$titles = ['Track Average Agreed', 'Track Average Split', 'Track Average Ignored'];

		$tracks = [];
		foreach ($titles as $index => $title) {
			$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => $title]]);
			$tracks[] = ['song_id' => $ctx->songId($title), 'position' => $index + 1];
		}
		$albumId = makeAlbum($ctx, 'Track Average Record', $artistId, $tracks);

		$unrated = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'];
		assertTrue(strpos($unrated, 'albumTrackScore') === false, 'an album nobody has scored shows no score column at all');

		$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId('Track Average Agreed'), 'field' => 'score', 'value' => '8']);
		$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId('Track Average Split'), 'field' => 'score', 'value' => '9']);

		$ctx->ensureLoggedIn('track_average_other', 'test password', false);
		$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId('Track Average Agreed'), 'field' => 'score', 'value' => '8']);
		$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId('Track Average Split'), 'field' => 'score', 'value' => '4']);

		$ctx->ensureLoggedIn();
		$html = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'];

		$rows = [];
		foreach (explode('<tr class="albumTrack"', $html) as $row) {
			if (!preg_match('/albumTrackTitle">([^<]*)</', $row, $title)) {
				continue;
			}
			preg_match('/albumTrackScore[^>]*>([^<]*)</', $row, $score);
			preg_match('/albumTrackDeviation[^>]*>([^<]*)</', $row, $deviation);
			preg_match('/albumTrackRated[^>]*>([^<]*)</', $row, $rated);
			$rows[$title[1]] = [
				'score' => $score[1] ?? null,
				'deviation' => $deviation[1] ?? null,
				'rated' => explode(' / ', $rated[1] ?? '')[0],
			];
		}

		assertSame('8', $rows['Track Average Agreed']['score'], 'two raters agreeing average to what they both said');
		assertSame('0', $rows['Track Average Agreed']['deviation'], 'agreement is a spread of nothing');
		assertSame('2', $rows['Track Average Agreed']['rated'], 'and both of them are counted');

		assertSame('6.5', $rows['Track Average Split']['score'], 'a 9 against a 4 averages between them');
		assertSame('2.5', $rows['Track Average Split']['deviation'], 'and shows as a spread either side of it');
		assertSame('2', $rows['Track Average Split']['rated'], 'from the same two people');

		assertSame('—', $rows['Track Average Ignored']['score'], 'a track nobody scored shows a dash, not a zero');
		assertSame('—', $rows['Track Average Ignored']['deviation'], 'with no spread either');
		assertSame('0', $rows['Track Average Ignored']['rated'], 'and nobody counted');

		assertContains('<td class="albumTrackScore" style="color: hsl(', $html, 'the averages are coloured on the score ramp');
		assertContains('<th class="albumTrackScore">Avg</th>', $html, 'and the column says what it is');
		assertContains('<th class="albumTrackTitle">Title</th>', $html, 'alongside the other track columns');
	},

	'the graph can be reordered without disturbing the track list' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Reordered Artist');
		$titles = ['Reordered Zulu', 'Reordered Alpha', 'Reordered Mike'];

		$tracks = [];
		foreach ($titles as $index => $title) {
			$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => $title]]);
			$tracks[] = ['song_id' => $ctx->songId($title), 'position' => $index + 1];
		}
		$albumId = makeAlbum($ctx, 'Reordered Record', $artistId, $tracks);

		foreach (['Reordered Zulu' => '3', 'Reordered Alpha' => '9', 'Reordered Mike' => '6'] as $title => $score) {
			$ctx->post('/music/ajax/song-rating', ['id' => $ctx->songId($title), 'field' => 'score', 'value' => $score]);
		}

		$graphOrder = function ($html) {
			preg_match_all('/<title>[^—]*— ([^:]*):/', $html, $matches);
			return $matches[1];
		};

		$trackTableOrder = function ($html) {
			preg_match_all('/albumTrackTitle">([^<]*)</', $html, $matches);
			return array_values(array_filter($matches[1], fn($name) => strpos($name, 'Reordered') === 0));
		};

		$byAlbum = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'];
		assertSame(['Reordered Zulu', 'Reordered Alpha', 'Reordered Mike'], $graphOrder($byAlbum), 'the graph starts in the running order');

		$byScore = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId, 'sort' => 'average', 'dir' => 'desc'])['json']['html'];
		assertSame(['Reordered Alpha', 'Reordered Mike', 'Reordered Zulu'], $graphOrder($byScore), 'by average puts the best first');
		assertSame(['Reordered Zulu', 'Reordered Alpha', 'Reordered Mike'], $trackTableOrder($byScore), 'while the track table keeps the running order');

		$byScoreUp = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId, 'sort' => 'average', 'dir' => 'asc'])['json']['html'];
		assertSame(['Reordered Zulu', 'Reordered Mike', 'Reordered Alpha'], $graphOrder($byScoreUp), 'and the direction flips it');

		$byTitle = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId, 'sort' => 'title', 'dir' => 'asc'])['json']['html'];
		assertSame(['Reordered Alpha', 'Reordered Mike', 'Reordered Zulu'], $graphOrder($byTitle), 'by title is alphabetical');

		$nonsense = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId, 'sort' => 'whatever', 'dir' => 'sideways'])['json']['html'];
		assertSame(['Reordered Zulu', 'Reordered Alpha', 'Reordered Mike'], $graphOrder($nonsense), 'an order it does not offer falls back to the running order');

		assertContains('data-default-dir="desc"', $byAlbum, 'each order carries the direction it reads in naturally');
		assertContains('value="rater_', $byAlbum, 'and every rater can be sorted on individually');
	},

	'an album can be renamed from its card, keeping the old name as an alias' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Renamed Album Artist');
		$albumId = makeAlbum($ctx, 'Provisional Title', $artistId, []);

		assertSame('ok', $ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'name', 'value' => 'Settled Title'])['json']['status'], 'rename status');

		$aliases = $ctx->db()->query("SELECT name, is_actual FROM album_alias WHERE album_id = {$albumId} ORDER BY is_actual DESC")->fetchAll();
		assertSame(2, count($aliases), 'the old name is still there');
		assertSame('Settled Title', $aliases[0]['name'], 'the new name is the actual one');
		assertSame(1, (int)$aliases[0]['is_actual'], 'and it is marked as such');
		assertSame('Provisional Title', $aliases[1]['name'], 'the old name stayed behind as an alias');
		assertSame(0, (int)$aliases[1]['is_actual'], 'no longer the actual one');

		$html = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'];
		assertContains('Settled Title', $html, 'the card shows the new name');
		assertContains('Provisional Title', $html, 'and lists the old one as a name it also goes by');

		assertSame('ok', $ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'name', 'value' => 'Provisional Title'])['json']['status'], 'renaming back status');
		$back = $ctx->db()->query("SELECT name FROM album_alias WHERE album_id = {$albumId} AND is_actual = 1")->fetchAll();
		assertSame(1, count($back), 'exactly one name is actual at a time');
		assertSame('Provisional Title', $back[0]['name'], 'renaming back to an old name reuses that alias rather than duplicating it');

		assertSame('error', $ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'name', 'value' => '  '])['json']['status'], 'an empty name is refused');
	},

	'a track can be credited under one of its song\'s other names' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Credited Artist');
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Credited Studio Title']]);
		$songId = $ctx->songId('Credited Studio Title');

		$aliased = $ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Credited Sleeve Title',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);
		$aliasId = (int)$aliased['json'][0]['song_alias_id'];

		$albumId = makeAlbum($ctx, 'Credited Record', $artistId, [['song_id' => $songId, 'position' => 1]]);

		$card = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json'];
		assertContains('Credited Studio Title', $card['html'], 'the track starts out under the song\'s own name');
		assertSame(2, count($card['trackAliases'][(string)$songId]), 'the card hands the editor every name that song goes by');

		assertSame('ok', $ctx->post(ALBUM_TRACK_ENDPOINT, ['album_id' => $albumId, 'song_id' => $songId, 'action' => 'alias', 'song_alias_id' => $aliasId])['json']['status'], 'alias status');
		assertContains('Credited Sleeve Title', $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'], 'the track is now credited under the chosen name');

		assertSame('ok', $ctx->post(ALBUM_TRACK_ENDPOINT, ['album_id' => $albumId, 'song_id' => $songId, 'action' => 'alias', 'song_alias_id' => ''])['json']['status'], 'clearing status');
		assertContains('Credited Studio Title', $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json']['html'], 'clearing it falls back to the song\'s own name');

		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Credited Stranger']]);
		$stranger = $ctx->db()->query("SELECT id FROM song_alias WHERE name = 'Credited Stranger'")->fetch()['id'];
		assertSame('error', $ctx->post(ALBUM_TRACK_ENDPOINT, ['album_id' => $albumId, 'song_id' => $songId, 'action' => 'alias', 'song_alias_id' => $stranger])['json']['status'], 'an alias belonging to another song is refused');

		$added = $ctx->post(ALBUM_TRACK_ENDPOINT, ['album_id' => $albumId, 'song_id' => $ctx->songId('Credited Stranger'), 'action' => 'add', 'position' => '2'])['json'];
		assertSame(1, count($added['aliases']), 'adding a song hands back its names, so its alias dropdown can be filled without another request');
	},

	'only an admin gets the album card edit button' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Editable Album Artist');
		$albumId = makeAlbum($ctx, 'Editable Record', $artistId, []);

		$admin = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json'];
		assertContains('albumCardEditBtn', $admin['html'], 'an admin gets the edit button');
		assertTrue(isset($admin['trackAliases']), 'and the alias lists for the songs on this album');

		$ctx->ensureLoggedIn('album_card_rater', 'test password', false);
		$rater = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json'];
		assertTrue(strpos($rater['html'], 'albumCardEditBtn') === false, 'a rater does not');
		assertTrue(!isset($rater['trackAliases']), 'and is handed none of the editing data either');
	},

	// The catalogue is the same for every album and most card opens never edit
	// anything, so shipping it with the card meant re-sending every song in the
	// library on each open - by far the most expensive thing the card did.
	'the dropdown options are fetched on demand, not with every card' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Deferred Options Artist');
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Deferred Options Song']]);
		$albumId = makeAlbum($ctx, 'Deferred Options Record', $artistId, []);

		$card = $ctx->post(ALBUM_CARD_ENDPOINT, ['album_id' => $albumId])['json'];
		assertTrue(!isset($card['songs']) && !isset($card['artists']), 'the card carries no catalogue at all');

		$options = $ctx->post(ALBUM_OPTIONS_ENDPOINT, [])['json'];
		assertSame('ok', $options['status'], 'the options endpoint answers');
		assertTrue(!empty($options['artists']), 'with the artist options the dropdown needs');
		assertTrue(!empty($options['songs']), 'and the song options the track dropdowns need');

		$named = null;
		foreach ($options['songs'] as $song) {
			if ($song['name'] === 'Deferred Options Song') {
				$named = $song;
			}
		}
		assertTrue($named !== null, 'every song is offered, not just this album\'s');
		assertSame('Deferred Options Artist', $named['artist'], 'each one labelled with its artist');
	},

	'the dropdown options are admin only' => function ($ctx) {
		$ctx->newSession();
		assertSame(401, $ctx->post(ALBUM_OPTIONS_ENDPOINT, [])['status'], 'signed out');

		$ctx->ensureLoggedIn('album_options_rater', 'test password', false);
		assertSame(403, $ctx->post(ALBUM_OPTIONS_ENDPOINT, [])['status'], 'not an admin');

		$ctx->ensureLoggedIn();
		assertSame(403, $ctx->postWithoutCsrf(ALBUM_OPTIONS_ENDPOINT, [])['status'], 'no csrf token');
	},

	'the album card can be given an artist and a year' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Retagged Album Artist');
		$other = $ctx->makeArtist('Retagged Other Artist');
		$albumId = makeAlbum($ctx, 'Retagged Record', $artistId, []);

		assertSame('ok', $ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'artist', 'value' => (string)$other])['json']['status'], 'artist status');
		assertSame('ok', $ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'year', 'value' => '1994'])['json']['status'], 'year status');

		$row = $ctx->db()->query("SELECT artist_id, release_year FROM album WHERE id = {$albumId}")->fetch();
		assertSame((int)$other, (int)$row['artist_id'], 'the artist moved');
		assertSame(1994, (int)$row['release_year'], 'the year was stored');

		$ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'artist', 'value' => '']);
		$ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'year', 'value' => '']);

		$cleared = $ctx->db()->query("SELECT artist_id, release_year FROM album WHERE id = {$albumId}")->fetch();
		assertTrue($cleared['artist_id'] === null, 'an empty artist clears it');
		assertTrue($cleared['release_year'] === null, 'an empty year clears it');
	},

	'the album card refuses nonsense edits' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Refusing Album Artist');
		$albumId = makeAlbum($ctx, 'Refusing Record', $artistId, []);

		assertSame('error', $ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'year', 'value' => 'nineteen'])['json']['status'], 'a year that is not digits');
		assertSame('error', $ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'artist', 'value' => '999999'])['json']['status'], 'an artist that does not exist');
		assertSame('error', $ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => 999999, 'field' => 'year', 'value' => '1994'])['json']['status'], 'an album that does not exist');
		assertSame(400, $ctx->post(ALBUM_EDIT_ENDPOINT, ['album_id' => $albumId, 'field' => 'colour', 'value' => 'blue'])['status'], 'a field that is not a field');

		$row = $ctx->db()->query("SELECT artist_id, release_year FROM album WHERE id = {$albumId}")->fetch();
		assertSame((int)$artistId, (int)$row['artist_id'], 'the artist was left alone');
		assertTrue($row['release_year'] === null, 'and so was the year');
	},

	'tracks can be added to, renumbered on and taken off an album card' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Tracklist Artist');
		foreach (['Tracklist Keeper', 'Tracklist Newcomer'] as $title) {
			$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => $title]]);
		}
		$keeper = $ctx->songId('Tracklist Keeper');
		$newcomer = $ctx->songId('Tracklist Newcomer');

		$albumId = makeAlbum($ctx, 'Tracklist Record', $artistId, [['song_id' => $keeper, 'position' => 1]]);

		assertSame('ok', $ctx->post(ALBUM_TRACK_ENDPOINT, ['album_id' => $albumId, 'song_id' => $newcomer, 'action' => 'add', 'position' => '2'])['json']['status'], 'add status');
		assertSame('duplicate', $ctx->post(ALBUM_TRACK_ENDPOINT, ['album_id' => $albumId, 'song_id' => $newcomer, 'action' => 'add', 'position' => '2'])['json']['status'], 'adding it twice is a duplicate, not a second row');

		assertSame('ok', $ctx->post(ALBUM_TRACK_ENDPOINT, ['album_id' => $albumId, 'song_id' => $keeper, 'action' => 'position', 'position' => '7'])['json']['status'], 'renumber status');
		assertSame('error', $ctx->post(ALBUM_TRACK_ENDPOINT, ['album_id' => $albumId, 'song_id' => $keeper, 'action' => 'position', 'position' => 'first'])['json']['status'], 'a track number that is not digits');

		$rows = $ctx->db()->query("SELECT song_id, position FROM album_track WHERE album_id = {$albumId} ORDER BY song_id")->fetchAll();
		assertSame(2, count($rows), 'both songs are on the album once each');
		$positions = [];
		foreach ($rows as $row) {
			$positions[(int)$row['song_id']] = (int)$row['position'];
		}
		assertSame(7, $positions[$keeper], 'the renumbered track kept its new number');
		assertSame(2, $positions[$newcomer], 'and the added one kept the number it came in with');

		assertSame('ok', $ctx->post(ALBUM_TRACK_ENDPOINT, ['album_id' => $albumId, 'song_id' => $keeper, 'action' => 'remove'])['json']['status'], 'remove status');

		$left = $ctx->db()->query("SELECT song_id FROM album_track WHERE album_id = {$albumId}")->fetchAll();
		assertSame(1, count($left), 'one track was taken off');
		assertSame($newcomer, (int)$left[0]['song_id'], 'and it was the right one');

		$stillThere = (int)$ctx->db()->query("SELECT COUNT(*) c FROM song WHERE id = {$keeper}")->fetch()['c'];
		assertSame(1, $stillThere, 'taking a song off an album does not delete the song');
	},

	'the album editing endpoints are admin only' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Album Guard Artist');
		$albumId = makeAlbum($ctx, 'Guarded Record', $artistId, []);

		foreach ([ALBUM_EDIT_ENDPOINT, ALBUM_TRACK_ENDPOINT] as $endpoint) {
			$ctx->newSession();
			assertSame(401, $ctx->post($endpoint, ['album_id' => $albumId])['status'], "signed out: {$endpoint}");

			$ctx->ensureLoggedIn('album_edit_rater', 'test password', false);
			assertSame(403, $ctx->post($endpoint, ['album_id' => $albumId])['status'], "not an admin: {$endpoint}");

			$ctx->ensureLoggedIn();
			assertSame(403, $ctx->postWithoutCsrf($endpoint, ['album_id' => $albumId])['status'], "no csrf token: {$endpoint}");
		}
	},

	'the album list name opens that album\'s card' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Clickable Album Artist');
		$albumId = makeAlbum($ctx, 'Clickable Record', $artistId, []);

		$body = $ctx->get('/music/albums')['body'];

		assertContains('data-album-card-id="' . $albumId . '"', $body, 'the name opens the card');
		assertContains('id="albumCardModal"', $body, 'and the page carries the modal it opens into');
		assertContains('/music/songs?artist=' . $artistId . '&amp;album=' . $albumId, $body, 'while the name stays a real link to the filtered song list');
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
