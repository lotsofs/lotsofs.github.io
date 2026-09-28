<?php

const ARTIST_CARD_ENDPOINT = '/music/ajax/artist-card';
const ARTIST_EDIT_ENDPOINT = '/music/ajax/artist-edit';

function artistCardSong($ctx, $artistId, $title, $score = null) {
	$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => $title]]);
	$songId = $ctx->songId($title);

	if ($score !== null) {
		$ctx->post('/music/ajax/song-rating', ['id' => $songId, 'field' => 'score', 'value' => $score]);
	}

	return $songId;
}

return [

	'an artist card carries the ratings table and the graph, but no track list' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Carded Artist');
		artistCardSong($ctx, $artistId, 'Carded Artist Song One', '8');
		artistCardSong($ctx, $artistId, 'Carded Artist Song Two', '4');
		artistCardSong($ctx, $artistId, 'Carded Artist Song Three');

		$response = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId]);
		assertSame('ok', $response['json']['status'], 'status');

		$html = $response['json']['html'];
		assertContains('data-card-kind="artist"', $html, 'the card says what kind it is, so one modal can host either');
		assertContains('data-card-id="' . $artistId . '"', $html, 'and which one');
		assertContains('Carded Artist', $html, 'it names the artist');

		assertContains('albumStatsTable', $html, 'the ratings table is there');
		assertContains('albumStatsTotalRow', $html, 'including the totals row');
		assertContains('<svg class="albumGraph"', $html, 'and so is the graph');

		// an artist's track list would be every song they ever recorded
		assertTrue(strpos($html, 'albumTrackTable') === false, 'but no track table, which is the album card\'s job');

		assertSame(3, substr_count($html, '<rect class="albumGraphColumn"'), 'the graph plots every song of theirs, rated or not');
		assertContains('Carded Artist Song One', $html, 'each column names its song');

		// An artist's songs have no track numbers to put on the x axis.
		assertSame(0, preg_match_all('/text-anchor="middle"/', $html), 'the x axis carries no numbers');
		assertContains('text-anchor="end"', $html, 'but the score axis still does');
	},

	'the artist graph offers no album order, because songs are not a sequence' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Unordered Artist');
		artistCardSong($ctx, $artistId, 'Unordered Zulu', '3');
		artistCardSong($ctx, $artistId, 'Unordered Alpha', '9');

		$html = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'];

		/// Marked so the wheel steps them; the editor's dropdowns are not.
		foreach (['albumGraphSortKey', 'albumGraphSortDir'] as $control) {
			assertTrue((bool)preg_match('/<select id="' . $control . '"[^>]*\bclass="[^"]*\bcardWheelSelect\b/', $html), "{$control} answers the wheel");
		}

		assertTrue(strpos($html, 'value="album"') === false, 'no album order on an artist');
		assertContains('value="title"', $html, 'the list opens on title instead');
		assertContains('value="average"', $html, 'and the score orders are all there');

		$order = function ($html) {
			preg_match_all('/<rect class="albumGraphColumn"[^>]*data-tooltip="([^"\n]*)/', $html, $matches);
			return $matches[1];
		};

		assertSame(['Unordered Alpha', 'Unordered Zulu'], $order($html), 'the default order is alphabetical');
		assertSame(['Unordered Alpha', 'Unordered Zulu'], $order($ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId, 'sort' => 'average', 'dir' => 'desc'])['json']['html']), 'and by average the 9 leads');
	},

	'an artist card opens on release year, oldest first, undated songs last' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Chronological Artist');
		$order = function ($html) {
			preg_match_all('/<rect class="albumGraphColumn"[^>]*data-tooltip="([^"\n]*)/', $html, $matches);
			return $matches[1];
		};

		$late = artistCardSong($ctx, $artistId, 'Chronological Later', '5');
		$early = artistCardSong($ctx, $artistId, 'Chronological Earlier', '5');
		$never = artistCardSong($ctx, $artistId, 'Chronological Undated', '5');

		/// Undated songs do not offer the order at all.
		$html = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'];
		assertTrue(strpos($html, 'value="year"') === false, 'undated songs offer no chronological order');
		assertSame(['Chronological Earlier', 'Chronological Later', 'Chronological Undated'], $order($html), 'so the list is alphabetical');

		assertSame('ok', $ctx->post('/music/ajax/song-year', ['song_id' => $late, 'value' => '1994'])['json']['status'], 'setting a year');
		assertSame('ok', $ctx->post('/music/ajax/song-year', ['song_id' => $early, 'value' => '1988'])['json']['status'], 'setting the other year');

		$html = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'];
		assertTrue((bool)preg_match('/<option value="year"[^>]*\bselected\b/', $html), 'now the card opens on release year');
		assertTrue((bool)preg_match('/<option value="desc"(?![^>]*selected)/', $html), 'reading oldest to newest, so the direction control is not on high to low');
		assertSame(['Chronological Earlier', 'Chronological Later', 'Chronological Undated'], $order($html), 'oldest first, and the undated one sinks to the end');

		$flipped = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId, 'sort' => 'year', 'dir' => 'desc'])['json']['html'];
		assertSame(['Chronological Later', 'Chronological Earlier', 'Chronological Undated'], $order($flipped), 'newest first still leaves the undated one last');

		/// A song with no year of its own dates from its earliest record.
		$ctx->post('/music/ajax/album', [[
			'provided_name' => 'Chronological Debut',
			'album_id' => 'new',
			'og_name' => 'Chronological Debut',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '1979',
			'tracks' => [['song_id' => $never, 'position' => 1]],
		]]);

		$dated = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'];
		assertSame(['Chronological Undated', 'Chronological Earlier', 'Chronological Later'], $order($dated), 'an album year dates a song that has none of its own');
	},

	'the album table on an artist card can be read as one person' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Whose Discography');
		$tracks = [];
		foreach (['Whose Disco One' => '4', 'Whose Disco Two' => '8', 'Whose Disco Three' => '6'] as $title => $score) {
			$tracks[] = ['song_id' => artistCardSong($ctx, $artistId, $title, $score), 'position' => count($tracks) + 1];
		}

		$ctx->post('/music/ajax/album', [[
			'provided_name' => 'Whose Discography Record',
			'album_id' => 'new',
			'og_name' => 'Whose Discography Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '1990',
			'tracks' => $tracks,
		]]);

		$ctx->ensureLoggedIn('whose_album_rater', 'test password', false);
		$otherId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'whose_album_rater'")->fetch()['id'];
		$ctx->post('/music/ajax/song-rating', ['id' => $tracks[0]['song_id'], 'field' => 'score', 'value' => '1']);
		$ctx->post('/music/ajax/song-rating', ['id' => $tracks[1]['song_id'], 'field' => 'score', 'value' => '3']);

		$ctx->ensureLoggedIn();
		$albumRow = function ($html) {
			preg_match('/<tr class="artistAlbumRow[^>]*>(.*?)<\/tr>/s', $html, $row);
			preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row[1], $cells, PREG_SET_ORDER);
			return array_map('strip_tags', array_column($cells, 1));
		};

		$everyone = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'];
		assertTrue((bool)preg_match('/<select id="cardStatsWho"[^>]*\bclass="[^"]*\bcardStatsWho\b/', $everyone), 'the albums section carries the control');

		/// 4, 8, 6 from one rater and 1, 3 from the other, pooled.
		assertSame(['4.4', '2.42', '4'], array_slice($albumRow($everyone), 2, 3), 'pooled, the record averages all five scores');

		/// An album keeps every statistic where a track collapses to one score.
		$theirs = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId, 'who' => $otherId])['json']['html'];
		$row = $albumRow($theirs);

		assertSame(['2', '1', '2'], array_slice($row, 2, 3), 'read as one person it is their 1 and 3 alone');
		assertSame('2 / 3', $row[6], 'counted against the tracks, not against every rating that could exist');
		assertSame(7, count($row), 'and the columns are the same ones, not collapsed');

		$stale = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId, 'who' => 999999])['json']['html'];
		assertSame($albumRow($everyone), $albumRow($stale), 'a rater who is not here reads as everyone');
	},

	'a song with two artists appears on both their cards' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$first = $ctx->makeArtist('Duet Singer One');
		$second = $ctx->makeArtist('Duet Singer Two');

		$songId = artistCardSong($ctx, $first, 'Duet Between Two', '7');
		$ctx->post('/music/ajax/song-artist', ['song_id' => $songId, 'artist_id' => (int)$second, 'action' => 'add']);

		foreach ([$first, $second] as $artistId) {
			$html = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'];
			assertContains('Duet Between Two', $html, "the duet is on artist {$artistId}'s card");
		}
	},

	'an artist card lists their albums, each opening its own card' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Discography Artist');
		$songId = artistCardSong($ctx, $artistId, 'Discography Song', '6');

		$albumId = (int)$ctx->post('/music/ajax/album', [[
			'provided_name' => 'Discography Record',
			'album_id' => 'new',
			'og_name' => 'Discography Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '1981',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]])['json'][0]['album_id'];

		$html = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'];

		assertContains('Discography Record', $html, 'the album is listed');
		assertContains('1981', $html, 'with the year it came out');
		assertContains('data-album-card-id="' . $albumId . '"', $html, 'and opens the album card from here, so one card walks to the other');
	},

	'each album on an artist card carries its own statistics' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Statistical Artist');
		$tracks = [];
		foreach (['Statistical One' => '2', 'Statistical Two' => '4', 'Statistical Three' => '9'] as $title => $score) {
			$tracks[] = ['song_id' => artistCardSong($ctx, $artistId, $title, $score), 'position' => count($tracks) + 1];
		}

		/// A fourth song on no record: it counts towards the card, not the album row.
		artistCardSong($ctx, $artistId, 'Statistical Stray', '10');

		$albumId = (int)$ctx->post('/music/ajax/album', [[
			'provided_name' => 'Statistical Record',
			'album_id' => 'new',
			'og_name' => 'Statistical Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '1977',
			'tracks' => $tracks,
		]])['json'][0]['album_id'];

		$html = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'];
		$scoreCells = function ($rowHtml) {
			preg_match_all('/<td class="albumStatsScoreCell[^"]*"[^>]*>(?:<span[^>]*>)?([0-9.]+)/', $rowHtml, $cells);
			return $cells[1];
		};

		preg_match('/<tr class="artistAlbumRow[^>]*>(.*?)<\/tr>/s', $html, $row);

		/// Over 2, 4 and 9; three distinct scores have no mode.
		assertContains('>1977<', $row[1], 'the row opens with the year');
		assertSame(['5', '2.94', '4'], $scoreCells($row[1]), 'then the average, the deviation and the median');
		assertContains('albumStatsEmpty', $row[1], 'three scores that are all different have no most common one');
		/// Counted against every track by every account, not against the track count.
		$accounts = preg_match_all('/<tr class="albumStatsRow" data-account-id=/', $html);
		assertContains('3 / ' . (3 * $accounts), $row[1], 'three of the three tracks were scored, by one of the accounts there are');

		/// The same numbers the album's own card pools.
		$card = $ctx->post('/music/ajax/album-card', ['album_id' => $albumId])['json']['html'];
		preg_match('/<tr class="albumStatsRow albumStatsTotalRow[^>]*>(.*?)<\/tr>/s', $card, $totals);

		assertSame($scoreCells($row[1]), $scoreCells($totals[1]), 'the album card pools the same three scores the same way');
	},

	// The box is fixed, so a hundred-odd songs cannot squash it flat.
	'the graph is the same shape whether it plots one song or a hundred' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$small = $ctx->makeArtist('One Song Artist');
		artistCardSong($ctx, $small, 'The Only Song', '7');

		$large = $ctx->makeArtist('Many Songs Artist');
		for ($i = 1; $i <= 40; $i++) {
			artistCardSong($ctx, $large, "Many Songs Number {$i}", (string)($i % 11));
		}

		$shapeOf = function ($artistId) use ($ctx) {
			$html = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'];
			preg_match('/viewBox="([^"]+)"/', $html, $box);
			preg_match('/albumGraphDot[^>]*r="([0-9.]+)"/', $html, $radius);
			return ['box' => $box[1], 'radius' => (float)$radius[1], 'columns' => substr_count($html, '<rect class="albumGraphColumn"'), 'labels' => preg_match_all('/text-anchor="middle"/', $html)];
		};

		$one = $shapeOf($small);
		$many = $shapeOf($large);

		assertSame($one['box'], $many['box'], 'the coordinate box is fixed, so the rendered height is too');
		assertSame(1, $one['columns'], 'one song, one column');
		assertSame(40, $many['columns'], 'forty songs, forty columns in the same box');

		// The radius leaves its cap past ~22 songs and reaches its floor past ~60.
		assertTrue($many['radius'] < $one['radius'], 'the dots shrink with their spacing');
		assertTrue($many['radius'] >= 2, 'but never past the floor - a rater with a single score has no line to be on, only a dot');
		/// Compared by the box, since the plot inside it differs with the x axis.
		assertSame(0, $many['labels'], 'neither of them numbers the x axis');
		assertSame(0, $one['labels'], 'however few songs there are');
	},

	'an artist card for someone who is not there says so' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$response = $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => 999999]);

		assertSame('error', $response['json']['status'], 'status');
		assertTrue(empty($response['json']['html']), 'no card comes back');
	},

	'an artist can be renamed from their card, keeping the old name as an alias' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Renamed Wrongly');

		assertSame('ok', $ctx->post(ARTIST_EDIT_ENDPOINT, ['artist_id' => $artistId, 'field' => 'name', 'value' => 'Renamed Rightly'])['json']['status'], 'rename status');

		$aliases = $ctx->db()->query("SELECT name, is_actual FROM artist_alias WHERE artist_id = {$artistId} ORDER BY is_actual DESC")->fetchAll();
		assertSame(2, count($aliases), 'the old spelling is still there');
		assertSame('Renamed Rightly', $aliases[0]['name'], 'the new name is the actual one');
		assertSame('Renamed Wrongly', $aliases[1]['name'], 'the old one stayed behind as an alias');

		assertSame('ok', $ctx->post(ARTIST_EDIT_ENDPOINT, ['artist_id' => $artistId, 'field' => 'name', 'value' => 'Renamed Wrongly'])['json']['status'], 'renaming back status');
		$actual = $ctx->db()->query("SELECT name FROM artist_alias WHERE artist_id = {$artistId} AND is_actual = 1")->fetchAll();
		assertSame(1, count($actual), 'exactly one name is actual at a time');
		assertSame('Renamed Wrongly', $actual[0]['name'], 'renaming back reuses that alias rather than duplicating it');

		assertSame('error', $ctx->post(ARTIST_EDIT_ENDPOINT, ['artist_id' => $artistId, 'field' => 'name', 'value' => '  '])['json']['status'], 'an empty name is refused');
		assertSame('error', $ctx->post(ARTIST_EDIT_ENDPOINT, ['artist_id' => 999999, 'field' => 'name', 'value' => 'Ghost'])['json']['status'], 'so is an artist that does not exist');
	},

	'only an admin gets the artist card edit button' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Gated Artist');

		assertContains('artistCardEditBtn', $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'], 'an admin gets it');

		$ctx->ensureLoggedIn('artist_card_rater', 'test password', false);
		assertTrue(strpos($ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['json']['html'], 'artistCardEditBtn') === false, 'a rater does not');
		assertSame(403, $ctx->post(ARTIST_EDIT_ENDPOINT, ['artist_id' => $artistId, 'field' => 'name', 'value' => 'Nope'])['status'], 'and cannot rename either');

		$ctx->newSession();
		assertSame(401, $ctx->post(ARTIST_CARD_ENDPOINT, ['artist_id' => $artistId])['status'], 'signed out sees nothing');
	},

	'every artist name opens its card' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Clickable Artist');
		artistCardSong($ctx, $artistId, 'Clickable Artist Song');
		$songId = $ctx->songId('Clickable Artist Song');

		$trigger = 'data-artist-card-id="' . $artistId . '"';

		$artists = $ctx->get('/music/artists')['body'];
		assertContains($trigger, $artists, 'the artist list name opens the card');
		assertContains('id="albumCardModal"', $artists, 'and the page carries the modal it opens into');

		$songs = $ctx->get('/music/songs')['body'];
		assertContains($trigger, songsRowFor($songs, $songId), 'the table row artist name opens it');
		assertContains($trigger, songsCardFor($songs, $songId), 'and so does the song card artist name');
	},

];
