<?php

const AUDIT_RATING_ENDPOINT = '/music/ajax/song-rating';

function auditRowChunks($body) {
	$chunks = explode('<tr data-audit-id="', $body);
	array_shift($chunks);
	return $chunks;
}

function auditRowFor($body, $needle) {
	foreach (auditRowChunks($body) as $chunk) {
		if (strpos($chunk, $needle) !== false) {
			return $chunk;
		}
	}
	return null;
}

/// A row's place in its log and its time, read back off the page.
function auditRowKey($chunk) {
	preg_match('/data-audit-source="(\w+)"/', $chunk, $source);
	preg_match('/class="auditWhenCell">([^<]+)</', $chunk, $when);
	return ['id' => (int)$chunk, 'source' => $source[1] ?? '', 'when' => $when[1] ?? ''];
}

/// The edit log's rows about one song, album or artist, oldest first.
function auditEditsFor($ctx, $kind, $id) {
	$stmt = $ctx->db()->prepare("SELECT field, previous_value, value FROM edit_audit WHERE kind = ? AND entity_id = ? ORDER BY id");
	$stmt->execute([$kind, $id]);
	return $stmt->fetchAll();
}

function auditEdit($field, $previous, $value) {
	return ['field' => $field, 'previous_value' => $previous, 'value' => $value];
}

function auditRelation($songId, $title) {
	return json_encode(['type' => 'remaster', 'song' => $songId, 'title' => $title]);
}

return [

	'the audit page lists a rating that was just written' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Page Artist');
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Audit Page Song']]);
		$songId = $ctx->songId('Audit Page Song');

		$ctx->post(AUDIT_RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '7']);

		$row = auditRowFor($ctx->get('/music/audit')['body'], 'Audit Page Song');

		assertTrue($row !== null, 'the song appears in the log');
		assertContains('test_runner', $row, 'the rater is named');
		assertContains('Audit Page Artist', $row, 'the artist is shown alongside the title');
		assertContains('>7<', $row, 'the new value is shown');
	},

	'an edit records what the value used to be' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Edit Artist');
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Audit Edit Song']]);
		$songId = $ctx->songId('Audit Edit Song');

		$ctx->post(AUDIT_RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '3']);
		$ctx->post(AUDIT_RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '9']);

		$body = $ctx->get('/music/audit')['body'];
		$newest = auditRowChunks($body)[0];

		assertContains('Audit Edit Song', $newest, 'the newest entry is the one just written');
		assertContains('>3<', $newest, 'the old value is kept');
		assertContains('>9<', $newest, 'alongside the new one');
	},

	'a first rating and a cleared rating both show a dash for the missing side' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Dash Artist');
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Audit Dash Song']]);
		$songId = $ctx->songId('Audit Dash Song');

		$ctx->post(AUDIT_RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => 'first words']);
		$first = auditRowChunks($ctx->get('/music/audit')['body'])[0];
		assertContains('—', $first, 'a first rating has nothing to show on the from side');
		assertContains('first words', $first, 'and the new note on the to side');

		$ctx->post(AUDIT_RATING_ENDPOINT, ['id' => $songId, 'field' => 'note', 'value' => '']);
		$cleared = auditRowChunks($ctx->get('/music/audit')['body'])[0];
		assertContains('first words', $cleared, 'clearing keeps the old note as history');
		assertContains('—', $cleared, 'and shows nothing on the to side');
	},

	'the newest entry comes first' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Audit Order Artist');
		foreach (['Audit Order One', 'Audit Order Two'] as $title) {
			$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => $title]]);
			$ctx->post(AUDIT_RATING_ENDPOINT, ['id' => $ctx->songId($title), 'field' => 'score', 'value' => '5']);
		}

		$keys = array_map('auditRowKey', auditRowChunks($ctx->get('/music/audit')['body']));

		assertTrue(count($keys) > 1, 'there are entries to order');

		$whens = array_column($keys, 'when');
		$sorted = $whens;
		rsort($sorted);
		assertSame($sorted, $whens, 'entries run newest to oldest');

		foreach (['rating', 'edit'] as $source) {
			$ids = array_column(array_filter($keys, fn($key) => $key['source'] === $source), 'id');
			$sorted = $ids;
			rsort($sorted);
			assertTrue(count($ids) > 0, "the page holds {$source} entries");
			assertSame($sorted, array_values($ids), "and each log's own run newest to oldest ({$source})");
		}
	},

	'a song card edit logs what it changed' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Edit Log Artist');
		$songId = makeSong($ctx, $artistId, 'Edit Log Song');

		$ctx->post('/music/ajax/song-edit', ['id' => $songId, 'field' => 'title', 'value' => 'Edit Log Renamed']);
		$ctx->post('/music/ajax/song-year', ['song_id' => $songId, 'value' => '1996']);
		$ctx->post('/music/ajax/song-duration', ['song_id' => $songId, 'value' => '3:05']);
		$ctx->post('/music/ajax/song-link', ['song_id' => $songId, 'field' => 'spotify_url', 'value' => 'https://open.spotify.com/track/4uLU6hMCjMI75M1A2tKUQC']);
		$ctx->post('/music/ajax/alias-add', ['kind' => 'song', 'id' => $songId, 'name' => 'Edit Log Other']);
		$ctx->post('/music/ajax/alias-delete', ['kind' => 'song', 'id' => $songId, 'name' => 'Edit Log Other']);

		assertSame([
			auditEdit('added', null, 'Edit Log Song'),
			auditEdit('title', 'Edit Log Song', 'Edit Log Renamed'),
			auditEdit('year', null, '1996'),
			auditEdit('duration', null, '3:05'),
			auditEdit('spotify_url', null, '4uLU6hMCjMI75M1A2tKUQC'),
			auditEdit('alias', null, 'Edit Log Other'),
			auditEdit('alias', 'Edit Log Other', null),
		], auditEditsFor($ctx, 'song', $songId), 'one row per change, with what it was and what it became');

		$accounts = $ctx->db()->query("
			SELECT DISTINCT a.account_name FROM edit_audit e JOIN account a ON a.id = e.account_id
			WHERE e.kind = 'song' AND e.entity_id = {$songId}
		")->fetchAll(PDO::FETCH_COLUMN);
		assertSame(['test_runner'], $accounts, 'each row names the admin who made it');
	},

	'an artist or album joined to a song from its card is logged, and so is its removal' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Join Log Artist');
		$guestId = $ctx->makeArtist('Join Log Guest');
		$songId = makeSong($ctx, $artistId, 'Join Log Song');
		$albumId = makeAlbum($ctx, 'Join Log Album', $artistId, []);

		$ctx->post('/music/ajax/song-artist', ['song_id' => $songId, 'artist_id' => $guestId, 'action' => 'add']);
		$ctx->post('/music/ajax/song-artist', ['song_id' => $songId, 'artist_id' => $guestId, 'action' => 'remove']);
		$ctx->post('/music/ajax/song-artist', ['song_id' => $songId, 'artist_id' => $guestId, 'action' => 'remove']);
		$ctx->post('/music/ajax/song-album', ['song_id' => $songId, 'album_id' => $albumId, 'action' => 'add']);
		$ctx->post('/music/ajax/song-album', ['song_id' => $songId, 'album_id' => $albumId, 'action' => 'remove']);

		assertSame([
			auditEdit('added', null, 'Join Log Song'),
			auditEdit('artist', null, 'Join Log Guest'),
			auditEdit('artist', 'Join Log Guest', null),
		], auditEditsFor($ctx, 'song', $songId), 'an artist is logged on the song by name, and removing one that is gone logs nothing');

		assertSame([
			auditEdit('added', null, 'Join Log Album'),
			auditEdit('artist', null, 'Join Log Artist'),
			auditEdit('track', null, 'Join Log Song'),
			auditEdit('track', 'Join Log Song', null),
		], auditEditsFor($ctx, 'album', $albumId), 'an album is logged on the album, as its track');
	},

	'the album card logs each track as the album lists it' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Track Log Artist');
		$songId = makeSong($ctx, $artistId, 'Track Log Song');
		$ctx->post('/music/ajax/alias-add', ['kind' => 'song', 'id' => $songId, 'name' => 'Track Log Alt']);
		$altId = (int)$ctx->db()->query("SELECT id FROM song_alias WHERE name = 'Track Log Alt'")->fetch()['id'];
		$albumId = makeAlbum($ctx, 'Track Log Album', $artistId, []);

		$ctx->post('/music/ajax/album-track', ['album_id' => $albumId, 'song_id' => $songId, 'action' => 'add', 'position' => '2']);
		$ctx->post('/music/ajax/album-track', ['album_id' => $albumId, 'song_id' => $songId, 'action' => 'position', 'position' => '3']);
		$ctx->post('/music/ajax/album-track', ['album_id' => $albumId, 'song_id' => $songId, 'action' => 'alias', 'song_alias_id' => $altId]);
		$ctx->post('/music/ajax/alias-delete', ['kind' => 'song', 'id' => $songId, 'name' => 'Track Log Alt', 'clear_listings' => true]);
		$ctx->post('/music/ajax/album-track', ['album_id' => $albumId, 'song_id' => $songId, 'action' => 'remove']);

		assertSame([
			auditEdit('added', null, 'Track Log Album'),
			auditEdit('artist', null, 'Track Log Artist'),
			auditEdit('track', null, '2. Track Log Song'),
			auditEdit('track', '2. Track Log Song', '3. Track Log Song'),
			auditEdit('track', '3. Track Log Song', '3. Track Log Alt'),
			auditEdit('track', '3. Track Log Alt', '3. Track Log Song'),
			auditEdit('track', '3. Track Log Song', null),
		], auditEditsFor($ctx, 'album', $albumId), 'adding, renumbering, relisting and removing, including a listing a deleted name clears');
	},

	'album and artist edits are logged on the album or artist' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Card Log Artist');
		$otherId = $ctx->makeArtist('Card Log Other');
		$albumId = makeAlbum($ctx, 'Card Log Album', $artistId, []);

		$ctx->post('/music/ajax/album-edit', ['album_id' => $albumId, 'field' => 'name', 'value' => 'Card Log Renamed']);
		$ctx->post('/music/ajax/album-edit', ['album_id' => $albumId, 'field' => 'artist', 'value' => (string)$otherId]);
		$ctx->post('/music/ajax/album-edit', ['album_id' => $albumId, 'field' => 'artist', 'value' => '']);
		$ctx->post('/music/ajax/album-edit', ['album_id' => $albumId, 'field' => 'year', 'value' => '2001']);
		$ctx->post('/music/ajax/artist-edit', ['artist_id' => $artistId, 'field' => 'name', 'value' => 'Card Log Artist Renamed']);

		assertSame([
			auditEdit('added', null, 'Card Log Album'),
			auditEdit('artist', null, 'Card Log Artist'),
			auditEdit('name', 'Card Log Album', 'Card Log Renamed'),
			auditEdit('artist', 'Card Log Artist', 'Card Log Other'),
			auditEdit('artist', 'Card Log Other', null),
			auditEdit('year', null, '2001'),
		], auditEditsFor($ctx, 'album', $albumId), 'the album card');

		assertSame([
			auditEdit('added', null, 'Card Log Artist'),
			auditEdit('name', 'Card Log Artist', 'Card Log Artist Renamed'),
		], auditEditsFor($ctx, 'artist', $artistId), 'the artist card');
	},

	'a relationship is logged on its variant, however it changes' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Log Artist');
		$originalId = makeSong($ctx, $artistId, 'Relation Log Original');
		$remasterId = makeSong($ctx, $artistId, 'Relation Log Remaster');
		$thirdId = makeSong($ctx, $artistId, 'Relation Log Third');
		$endpoint = '/music/ajax/song-relationship';

		/// From the original's card: its own end is the target.
		$ctx->post($endpoint, ['action' => 'add', 'song_id' => $originalId, 'other_song_id' => $remasterId, 'type' => 'remaster', 'role' => 'target']);
		$id = (int)$ctx->db()->query("SELECT id FROM song_relationship WHERE variant_song_id = {$remasterId}")->fetch()['id'];

		$ctx->post($endpoint, ['action' => 'update', 'id' => $id, 'song_id' => $remasterId, 'other_song_id' => $thirdId, 'type' => 'remaster', 'role' => 'variant']);
		$ctx->post($endpoint, ['action' => 'update', 'id' => $id, 'song_id' => $thirdId, 'other_song_id' => $remasterId, 'type' => 'remaster', 'role' => 'variant']);
		$ctx->post($endpoint, ['action' => 'remove', 'id' => $id]);

		assertSame([
			auditEdit('added', null, 'Relation Log Remaster'),
			auditEdit('relation', null, auditRelation($originalId, 'Relation Log Original')),
			auditEdit('relation', auditRelation($originalId, 'Relation Log Original'), auditRelation($thirdId, 'Relation Log Third')),
			auditEdit('relation', auditRelation($thirdId, 'Relation Log Third'), null),
		], auditEditsFor($ctx, 'song', $remasterId), 'added, re-pointed in place, then handed to another variant');

		assertSame([
			auditEdit('added', null, 'Relation Log Third'),
			auditEdit('relation', null, auditRelation($remasterId, 'Relation Log Remaster')),
			auditEdit('relation', auditRelation($remasterId, 'Relation Log Remaster'), null),
		], auditEditsFor($ctx, 'song', $thirdId), 'the new variant takes it up, and its removal');

		assertSame([auditEdit('added', null, 'Relation Log Original')], auditEditsFor($ctx, 'song', $originalId), 'the target end logs nothing');

		assertContains("remastered version of {$originalId}: Relation Log Original", $ctx->get('/music/audit')['body'], 'the page names it as the card does');
	},

	'the import logs what it adds' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Import Log Artist');
		$response = $ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Import Log Spelling', 'og_name' => 'Import Log Song', 'also_alias_provided_name' => true]]);
		$songId = (int)$response['json'][0]['song_id'];
		$albumId = makeAlbum($ctx, 'Import Log Album', $artistId, [['song_id' => $songId, 'position' => '1']]);

		/// The same album again, its track moved: only the move is new.
		$ctx->post('/music/ajax/album', [[
			'provided_name' => 'Import Log Album',
			'album_id' => (string)$albumId,
			'og_name' => 'Import Log Album',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '',
			'tracks' => [['song_id' => $songId, 'position' => '2']],
		]]);

		foreach ([false, true] as $isActual) {
			$ctx->post('/music/ajax/artist-alias', [['artist_id' => $artistId, 'provided_name' => 'Import Log Band', 'og_name' => 'Import Log Band', 'is_actual' => $isActual]]);
		}

		assertSame([
			auditEdit('added', null, 'Import Log Song'),
			auditEdit('alias', null, 'Import Log Spelling'),
		], auditEditsFor($ctx, 'song', $songId), 'the song, and the spelling it was pasted under');

		assertSame([
			auditEdit('added', null, 'Import Log Album'),
			auditEdit('artist', null, 'Import Log Artist'),
			auditEdit('track', null, '1. Import Log Song'),
			auditEdit('track', '1. Import Log Song', '2. Import Log Song'),
		], auditEditsFor($ctx, 'album', $albumId), 'the album and its tracks');

		assertSame([
			auditEdit('added', null, 'Import Log Artist'),
			auditEdit('alias', null, 'Import Log Band'),
			auditEdit('name', 'Import Log Artist', 'Import Log Band'),
		], auditEditsFor($ctx, 'artist', $artistId), 'the artist, another name, and that name made current');
	},

	'a write that changes nothing logs nothing' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Quiet Log Artist');
		$songId = makeSong($ctx, $artistId, 'Quiet Log Song');

		$ctx->post('/music/ajax/song-year', ['song_id' => $songId, 'value' => '1999']);
		$ctx->post('/music/ajax/song-year', ['song_id' => $songId, 'value' => 1999]);
		$ctx->post('/music/ajax/song-year', ['song_id' => $songId, 'value' => 'nineteen']);
		$ctx->post('/music/ajax/song-edit', ['id' => $songId, 'field' => 'title', 'value' => 'Quiet Log Song']);
		$ctx->post('/music/ajax/song-link', ['song_id' => $songId, 'field' => 'other_url', 'value' => '']);

		$ctx->ensureLoggedIn('audit_viewer', 'test password', false);
		$ctx->post('/music/ajax/song-year', ['song_id' => $songId, 'value' => '2000']);
		$ctx->ensureLoggedIn();

		assertSame([
			auditEdit('added', null, 'Quiet Log Song'),
			auditEdit('year', null, '1999'),
		], auditEditsFor($ctx, 'song', $songId), 'a repeat, a refused value, an unchanged name, an empty link and a non-admin write add nothing');
	},

	'the page lists edits beside ratings, under what they are about' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Page Log Artist');
		$songId = makeSong($ctx, $artistId, 'Page Log Song');
		makeAlbum($ctx, 'Page Log Album', $artistId, []);
		$ctx->post('/music/ajax/song-year', ['song_id' => $songId, 'value' => '1997']);
		$ctx->post(AUDIT_RATING_ENDPOINT, ['id' => $songId, 'field' => 'score', 'value' => '6']);

		$body = $ctx->get('/music/audit')['body'];
		$newest = auditRowChunks($body)[0];

		assertContains('data-audit-source="rating"', $newest, 'the rating, written last, is on top');
		assertContains('Page Log Song', $newest, 'for that song');

		$year = auditRowFor($body, '>1997<');
		assertTrue($year !== null, 'the year edit is listed');
		assertContains('data-audit-source="edit"', $year, 'as an edit');
		assertContains('Page Log Artist — Page Log Song', $year, 'under the song, as ratings are');
		assertContains('>Year<', $year, 'with its field named');
		assertContains('test_runner', $year, 'and who made it');

		$album = auditRowFor($body, 'Album: Page Log Artist — Page Log Album');
		assertTrue($album !== null, 'an album edit is listed under the album');
		assertContains('>Artist<', $album, 'here the artist it was created with, logged just after it');

		assertContains('>Artist: Page Log Artist<', $body, 'an artist edit is listed under the artist');
	},

	'every entry is on exactly one page, newest to oldest' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$db = $ctx->db();
		$count = fn() => (int)$db->query("SELECT (SELECT COUNT(*) FROM rating_audit) + (SELECT COUNT(*) FROM edit_audit) c")->fetch()['c'];

		/// Enough for three pages whatever ran before.
		$songId = makeSong($ctx, $ctx->makeArtist('Paging Log Artist'), 'Paging Log Song');
		for ($year = 1900; $count() <= 250; $year++) {
			$ctx->post('/music/ajax/song-year', ['song_id' => $songId, 'value' => (string)$year]);
		}

		$total = $count();
		$keys = [];
		$path = '/music/audit';
		while ($path !== null) {
			$body = $ctx->get($path)['body'];
			$keys = array_merge($keys, array_map('auditRowKey', auditRowChunks($body)));
			$path = preg_match('#href="(/music/audit\?before=[^"]+)"#', $body, $match) ? html_entity_decode($match[1]) : null;
		}

		$ids = array_map(fn($key) => "{$key['source']}-{$key['id']}", $keys);
		assertSame($total, count($ids), 'every entry is listed');
		assertSame(count($ids), count(array_unique($ids)), 'none of them twice');

		$whens = array_column($keys, 'when');
		$sorted = $whens;
		rsort($sorted);
		assertSame($sorted, $whens, 'newest to oldest across the pages');

		$paging = fn($path) => preg_match('#<p class="auditPaging">(.*?)</p>#s', $ctx->get($path)['body'], $match) ? $match[1] : '';
		assertTrue(strpos($paging('/music/audit?before=5'), 'href="/music/audit"') === false, 'a cursor in the old shape opens the first page');
	},

	'a non admin can read the log' => function ($ctx) {
		$ctx->ensureLoggedIn('audit_viewer', 'test password', false);

		$response = $ctx->get('/music/audit');

		assertSame(200, $response['status'], 'the page is not admin only');
		assertContains('auditTable', $response['body'], 'and it renders the log');

		$ctx->ensureLoggedIn();
	},

	'a signed out visitor is sent to the login page' => function ($ctx) {
		$ctx->newSession();

		$response = $ctx->get('/music/audit');

		assertSame(302, $response['status'], 'signed out callers are redirected');
		assertContains('/music/login', $response['location'], 'to the login page');

		$ctx->ensureLoggedIn();
	},

	'the log is reachable from the nav' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];
		assertContains('href="/music/audit"', $body, 'every signed in page links to it');
	},

];
