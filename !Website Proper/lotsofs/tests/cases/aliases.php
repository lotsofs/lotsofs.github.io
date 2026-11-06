<?php

/* Writing in another name, deleting one, and making an old one current again,
   from the song, album and artist cards. Making one current is a rename to it,
   through each kind's own rename endpoint; adding and deleting are
   /music/ajax/alias-add and /music/ajax/alias-delete. */

const ALIAS_ADD_ENDPOINT = '/music/ajax/alias-add';
const ALIAS_DELETE_ENDPOINT = '/music/ajax/alias-delete';

function aliasesAdd($ctx, $kind, $id, $name) {
	return $ctx->post(ALIAS_ADD_ENDPOINT, ['kind' => $kind, 'id' => $id, 'name' => $name]);
}

function aliasesDelete($ctx, $kind, $id, $name, $clearListings = null) {
	$body = ['kind' => $kind, 'id' => $id, 'name' => $name];
	if ($clearListings !== null) {
		$body['clear_listings'] = $clearListings;
	}

	return $ctx->post(ALIAS_DELETE_ENDPOINT, $body);
}

/// Every name in the table, current first, the rest by name.
function aliasesStored($ctx, $kind, $id) {
	$stmt = $ctx->db()->prepare("SELECT name FROM {$kind}_alias WHERE {$kind}_id = ? ORDER BY is_actual DESC, name COLLATE NOCASE");
	$stmt->execute([$id]);

	return array_column($stmt->fetchAll(), 'name');
}

function aliasesTrackListing($ctx, $albumId, $songId) {
	$stmt = $ctx->db()->prepare("SELECT song_alias_id FROM album_track WHERE album_id = ? AND song_id = ?");
	$stmt->execute([$albumId, $songId]);

	$value = $stmt->fetch()['song_alias_id'];

	return $value === null ? null : (int)$value;
}

return [

	'an album keeps its old name on a rename, and it can then be deleted' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$albumId = makeAlbum($ctx, 'Abslution', $ctx->makeArtist('Alias Album Artist'), []);

		$renamed = $ctx->post('/music/ajax/album-edit', ['album_id' => $albumId, 'field' => 'name', 'value' => 'Absolution']);
		assertSame(['Absolution', 'Abslution'], $renamed['json']['names'], 'a rename keeps the old name, and says so');

		$card = $ctx->post('/music/ajax/album-card', ['album_id' => $albumId])['json']['html'];
		assertContains('data-alias-names="' . htmlspecialchars(json_encode(['Absolution', 'Abslution'])) . '"', $card, 'an admin\'s card carries every name for edit mode');
		assertContains('<dd class="albumAliasCell" data-field="aliases">Abslution</dd>', $card, 'and lists the old one');

		$current = aliasesDelete($ctx, 'album', $albumId, 'Absolution');
		assertSame('error', $current['json']['status'], 'the current name cannot be deleted');
		assertContains('current name', $current['json']['message'], 'and it says why');
		assertSame('error', aliasesDelete($ctx, 'album', $albumId, 'Never Called This')['json']['status'], 'nor a name it never had');
		assertSame(['Absolution', 'Abslution'], aliasesStored($ctx, 'album', $albumId), 'so both are still there');

		$deleted = aliasesDelete($ctx, 'album', $albumId, 'Abslution');
		assertSame('ok', $deleted['json']['status'], 'the old name can go');
		assertSame(['Absolution'], $deleted['json']['names'], 'and the answer lists what is left');
		assertSame(['Absolution'], aliasesStored($ctx, 'album', $albumId), 'which is what is stored');

		$card = $ctx->post('/music/ajax/album-card', ['album_id' => $albumId])['json']['html'];
		assertTrue(strpos($card, 'albumAliasCell') === false, 'and the card has no other names to list');
	},

	'an album\'s old name can be made current again' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$albumId = makeAlbum($ctx, 'Original Pressing', $ctx->makeArtist('Alias Promote Artist'), []);
		$ctx->post('/music/ajax/album-edit', ['album_id' => $albumId, 'field' => 'name', 'value' => 'Reissue']);

		$promoted = $ctx->post('/music/ajax/album-edit', ['album_id' => $albumId, 'field' => 'name', 'value' => 'Original Pressing']);
		assertSame('ok', $promoted['json']['status'], 'promoting is a rename to a name it has');
		assertSame(['Original Pressing', 'Reissue'], $promoted['json']['names'], 'and keeps the one it replaced');
	},

	'an artist works the same way' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Alias Artsit');

		$renamed = $ctx->post('/music/ajax/artist-edit', ['artist_id' => $artistId, 'field' => 'name', 'value' => 'Alias Artist']);
		assertSame(['Alias Artist', 'Alias Artsit'], $renamed['json']['names'], 'a rename keeps the typo, and says so');

		$card = $ctx->post('/music/ajax/artist-card', ['artist_id' => $artistId])['json']['html'];
		assertContains('<dd class="artistAliasCell" data-field="aliases">Alias Artsit</dd>', $card, 'the card lists it');
		assertContains('data-alias-names="', $card, 'and carries every name for edit mode');

		assertSame('error', aliasesDelete($ctx, 'artist', $artistId, 'Alias Artist')['json']['status'], 'the current name stays');
		assertSame(['Alias Artist'], aliasesDelete($ctx, 'artist', $artistId, 'Alias Artsit')['json']['names'], 'the typo goes');
		assertSame(['Alias Artist'], aliasesStored($ctx, 'artist', $artistId), 'from the table too');
	},

	'a song name an album lists is only deleted once the album is let go of it' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Alias Song Artist');
		$songId = makeSong($ctx, $artistId, 'Alias Studio Title');
		$sleeve = $ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Alias Sleeve Title', 'song_id' => $songId, 'also_alias_provided_name' => true]]);
		$sleeveId = (int)$sleeve['json'][0]['song_alias_id'];
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Alias Stray Title', 'song_id' => $songId, 'also_alias_provided_name' => true]]);
		$albumId = makeAlbum($ctx, 'Alias Listing Record', $artistId, [['song_id' => $songId, 'song_alias_id' => $sleeveId, 'position' => 1]]);

		$stray = aliasesDelete($ctx, 'song', $songId, 'Alias Stray Title');
		assertSame('ok', $stray['json']['status'], 'a name no album uses just goes');
		assertSame(['Alias Studio Title', 'Alias Sleeve Title'], $stray['json']['names'], 'leaving the others');

		assertSame('error', aliasesDelete($ctx, 'song', $songId, 'Alias Studio Title')['json']['status'], 'the current title stays');

		$listed = aliasesDelete($ctx, 'song', $songId, 'Alias Sleeve Title');
		assertSame('listed', $listed['json']['status'], 'a name an album lists the song under is held back');
		assertSame(['Alias Listing Record'], $listed['json']['albums'], 'naming the album');
		assertContains('Alias Listing Record', $listed['json']['message'], 'in the message too');
		assertSame($sleeveId, aliasesTrackListing($ctx, $albumId, $songId), 'and the album still lists it so');

		assertSame('listed', aliasesDelete($ctx, 'song', $songId, 'Alias Sleeve Title', 'true')['json']['status'], 'only a real true lets go of the album');

		$cleared = aliasesDelete($ctx, 'song', $songId, 'Alias Sleeve Title', true);
		assertSame('ok', $cleared['json']['status'], 'asked to, it goes');
		assertSame(['Alias Studio Title'], $cleared['json']['names'], 'leaving the title');
		assertSame(null, aliasesTrackListing($ctx, $albumId, $songId), 'and the album lists the song under its current title');

		$row = songsRowFor($ctx->get("/music/songs?artist={$artistId}&album={$albumId}")['body'], $songId);
		assertSame('Alias Studio Title', songsCellValue($row, 'title'), 'which is what the song list shows for that album');
	},

	'another name can be written in for an album, an artist or a song' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Alias Writer Artist');
		$albumId = makeAlbum($ctx, 'Alias Written Record', $artistId, []);
		$songId = makeSong($ctx, $artistId, 'Alias Written Song');

		$album = aliasesAdd($ctx, 'album', $albumId, '  Alias Written Record (Deluxe)  ');
		assertSame('ok', $album['json']['status'], 'an album takes another name');
		assertSame(['Alias Written Record', 'Alias Written Record (Deluxe)'], $album['json']['names'], 'trimmed, and not as its current one');

		$card = $ctx->post('/music/ajax/album-card', ['album_id' => $albumId])['json']['html'];
		assertContains('<dd class="albumAliasCell" data-field="aliases">Alias Written Record (Deluxe)</dd>', $card, 'which the card lists');

		$again = aliasesAdd($ctx, 'album', $albumId, 'Alias Written Record (Deluxe)');
		assertSame('duplicate', $again['json']['status'], 'the same name twice is a duplicate');
		assertSame(['Alias Written Record', 'Alias Written Record (Deluxe)'], aliasesStored($ctx, 'album', $albumId), 'and is not stored twice');
		assertSame('error', aliasesAdd($ctx, 'album', $albumId, '   ')['json']['status'], 'an empty name is refused');

		assertSame(['Alias Writer Artist', 'The Alias Writers'], aliasesAdd($ctx, 'artist', $artistId, 'The Alias Writers')['json']['names'], 'an artist takes one too');

		$song = aliasesAdd($ctx, 'song', $songId, 'Alias Written Song (Live)');
		assertSame(['Alias Written Song', 'Alias Written Song (Live)'], $song['json']['names'], 'and so does a song');
		assertSame('Alias Written Song', $ctx->songTitle($songId), 'whose title stays as it was');

		$body = $ctx->get("/music/songs?artist={$artistId}")['body'];
		assertContains('<div class="songAlias" title="Alias Written Song (Live)">Alias Written Song (Live)</div>', songsRowFor($body, $songId), 'the song list shows it');
		assertContains('>Alias Written Song (Live)</dd>', songsFieldCell(songsCardFor($body, $songId), 'aliases'), 'and the card has the row now');
	},

	'a song cannot take a name another song by its artist goes by' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Alias Clash Artist');
		makeSong($ctx, $artistId, 'Alias Clash Taken');
		$songId = makeSong($ctx, $artistId, 'Alias Clash Other');

		$clash = aliasesAdd($ctx, 'song', $songId, 'Alias Clash Taken');
		assertSame('error', $clash['json']['status'], 'refused, as a rename to it is');
		assertSame(['Alias Clash Other'], aliasesStored($ctx, 'song', $songId), 'and nothing is added');

		$elsewhere = makeSong($ctx, $ctx->makeArtist('Alias Clash Elsewhere'), 'Alias Clash Elsewhere Song');
		assertSame('ok', aliasesAdd($ctx, 'song', $elsewhere, 'Alias Clash Taken')['json']['status'], 'another artist\'s song may share it');
	},

	'only an admin deletes names, and only by POST' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Alias Guard Artist');
		$ctx->post('/music/ajax/artist-edit', ['artist_id' => $artistId, 'field' => 'name', 'value' => 'Alias Guard Artist Renamed']);

		assertSame(400, aliasesDelete($ctx, 'playlist', $artistId, 'Alias Guard Artist')['status'], 'an unknown kind is refused');
		assertSame('error', aliasesDelete($ctx, 'song', 999999, 'Anything')['json']['status'], 'and a song that does not exist');
		assertSame(405, $ctx->get(ALIAS_DELETE_ENDPOINT)['status'], 'a GET is refused');

		$ctx->ensureLoggedIn('aliases_reader', 'test password', false);
		assertSame(403, aliasesDelete($ctx, 'artist', $artistId, 'Alias Guard Artist')['status'], 'a non-admin is refused');
		assertSame(403, aliasesAdd($ctx, 'artist', $artistId, 'Alias Guard Artist Again')['status'], 'and cannot add one either');
		assertSame(['Alias Guard Artist Renamed', 'Alias Guard Artist'], aliasesStored($ctx, 'artist', $artistId), 'and nothing went');

		$card = $ctx->post('/music/ajax/artist-card', ['artist_id' => $artistId])['json']['html'];
		assertTrue(strpos($card, 'data-alias-names') === false, 'nor does their card carry the names for edit mode');
	},

];
