<?php

/* The song list's column picker and its Other names & versions column. The
   picker's tests use accounts of their own, so test_runner's table is left as
   every later case file expects it. */

const COLUMNS_ENDPOINT = '/music/ajax/song-columns';

/// The song list, with one song of its own so the table is there to read.
function columnsPage($ctx, $name) {
	$ctx->ensureLoggedIn();
	makeSong($ctx, $ctx->makeArtist('Column Page Artist ' . $name), 'Column Page Song ' . $name);

	$ctx->ensureLoggedIn($name, 'test password', false);

	return $ctx->get('/music/songs')['body'];
}

function columnsTableHides($body) {
	if (!preg_match('/<table id="songListTable" class="([^"]*)"/', $body, $match)) {
		throw new Exception('no song table on the page');
	}

	return array_values(array_filter(preg_split('/\s+/', $match[1]), fn($class) => strpos($class, 'songHide') === 0));
}

/// key => ticked, in the picker's order.
function columnsPickerBoxes($body) {
	preg_match_all('/<input type="checkbox" data-column="([^"]+)" data-hide-class="([^"]+)"( checked)?>/', $body, $matches, PREG_SET_ORDER);

	$boxes = [];
	foreach ($matches as $match) {
		$boxes[$match[1]] = ($match[3] ?? '') !== '';
	}

	return $boxes;
}

function columnsChoiceRows($ctx, $name) {
	$stmt = $ctx->db()->prepare("
		SELECT c.column_key, c.shown
		FROM account_song_column c
		JOIN account a ON a.id = c.account_id
		WHERE a.account_name = ?
		ORDER BY c.column_key
	");
	$stmt->execute([$name]);

	$rows = [];
	foreach ($stmt->fetchAll() as $row) {
		$rows[$row['column_key']] = (int)$row['shown'];
	}

	return $rows;
}

function columnsAccountId($ctx, $name) {
	$stmt = $ctx->db()->prepare("SELECT id FROM account WHERE account_name = ?");
	$stmt->execute([$name]);

	return (int)$stmt->fetch()['id'];
}

/// The names a cell carries for songs.js, decoded.
function columnsCellTitles($cell) {
	return preg_match('/data-titles="([^"]*)"/', $cell, $match)
		? json_decode(html_entity_decode($match[1], ENT_QUOTES), true)
		: null;
}

/// A row's Other names & versions cell, whole.
function columnsNamesCell($row) {
	return preg_match('/<td class="songAliasesCell">.*?<\/td>/s', (string)$row, $match) ? $match[0] : '';
}

/// Each other-name line of that cell as it reads.
function columnsAliasLines($cell) {
	preg_match_all('/<div class="songAlias"[^>]*>(.*?)<\/div>/s', $cell, $matches);
	return array_map(fn($line) => html_entity_decode(strip_tags($line), ENT_QUOTES), $matches[1]);
}

return [

	'a new account sees every column but Other names & versions' => function ($ctx) {
		$body = columnsPage($ctx, 'columns_fresh');
		$me = columnsAccountId($ctx, 'columns_fresh');

		assertSame(['songHideAliases'], columnsTableHides($body), 'only Other names & versions starts hidden');

		$boxes = columnsPickerBoxes($body);
		$raters = [];
		foreach ($ctx->db()->query("SELECT id FROM account ORDER BY id = {$me} DESC, id")->fetchAll() as $row) {
			$raters[] = 'rater_' . (int)$row['id'];
		}

		assertSame(
			array_merge(
				['id', 'artist', 'title', 'aliases', 'album', 'year', 'duration', 'links'],
				$raters,
				['average', 'deviation', 'median', 'mode', 'highest', 'lowest', 'sum', 'rated']
			),
			array_keys($boxes),
			'the picker lists the fixed columns, every rater with you first, then the statistics'
		);
		assertSame(false, $boxes['aliases'], 'Other names & versions is unticked');
		assertSame([], array_keys(array_filter($boxes, fn($ticked, $key) => !$ticked && $key !== 'aliases', ARRAY_FILTER_USE_BOTH)), 'and everything else ticked');

		assertContains('<summary class="songColumnPickerButton">Columns</summary>', $body, 'the picker is a button labelled Columns');
		assertTrue(strpos($body, 'id="songCardViewToggle"') < strpos($body, 'id="songColumnPicker"'), 'beside the card view button');
		assertContains("#songListTable.songHideRater{$me} :is(td[data-account-id=\"{$me}\"], th[data-sort-key=\"score_{$me}\"], th[data-sort-key=\"note_{$me}\"], th.songRaterGroup{$me})", $body, 'and your own columns can be hidden too');
		assertSame([], columnsChoiceRows($ctx, 'columns_fresh'), 'looking writes nothing');
	},

	'a choice is one row, and the next page load follows it' => function ($ctx) {
		columnsPage($ctx, 'columns_picker');
		$other = columnsAccountId($ctx, 'test_runner');

		$response = $ctx->post(COLUMNS_ENDPOINT, ['column' => 'album', 'shown' => false]);
		assertSame(200, $response['status'], 'hiding a column is accepted');
		assertSame('ok', $response['json']['status'], 'and says so');
		assertSame(['album' => 0], columnsChoiceRows($ctx, 'columns_picker'), 'and stored as one row');

		$body = $ctx->get('/music/songs')['body'];
		assertSame(['songHideAliases', 'songHideAlbum'], columnsTableHides($body), 'the table hides it');
		assertSame(false, columnsPickerBoxes($body)['album'], 'and its box is unticked');

		$ctx->post(COLUMNS_ENDPOINT, ['column' => 'album', 'shown' => true]);
		assertSame(['album' => 1], columnsChoiceRows($ctx, 'columns_picker'), 'showing it again rewrites the same row');

		$ctx->post(COLUMNS_ENDPOINT, ['column' => 'aliases', 'shown' => true]);
		$ctx->post(COLUMNS_ENDPOINT, ['column' => "rater_{$other}", 'shown' => false]);

		$body = $ctx->get('/music/songs')['body'];
		assertSame(["songHideRater{$other}"], columnsTableHides($body), 'Other names & versions shows once ticked, and a rater hides');
		assertSame(true, columnsPickerBoxes($body)['aliases'], 'with its box ticked');
		assertSame(['album' => 1, 'aliases' => 1, "rater_{$other}" => 0], columnsChoiceRows($ctx, 'columns_picker'), 'each key is a row of its own');

		$ctx->ensureLoggedIn('columns_fresh', 'test password', false);
		assertSame(['songHideAliases'], columnsTableHides($ctx->get('/music/songs')['body']), 'another account keeps its own columns');
	},

	'the picker refuses what it does not offer' => function ($ctx) {
		$ctx->ensureLoggedIn('columns_fresh', 'test password', false);

		$refused = [
			['column' => 'colour', 'shown' => false],
			['column' => 'rater_x', 'shown' => false],
			['column' => 'rater_0', 'shown' => false],
			['column' => 'album', 'shown' => 'false'],
			['column' => 'album', 'shown' => 0],
			['column' => 'album'],
			['shown' => false],
		];

		foreach ($refused as $payload) {
			assertSame(400, $ctx->post(COLUMNS_ENDPOINT, $payload)['status'], json_encode($payload) . ' is refused');
		}

		assertSame([], columnsChoiceRows($ctx, 'columns_fresh'), 'and nothing is stored');
		assertSame(405, $ctx->get(COLUMNS_ENDPOINT)['status'], 'a GET is refused');

		$ctx->newSession();
		assertSame(401, $ctx->post(COLUMNS_ENDPOINT, ['column' => 'album', 'shown' => false])['status'], 'and so is nobody');
	},

	'a dragged column width is put back before the table, and every text column reads its own' => function ($ctx) {
		$body = columnsPage($ctx, 'columns_widths');

		$restore = strpos($body, 'localStorage.getItem("musicSongColumnWidths")');
		assertTrue($restore !== false && $restore < strpos($body, '<table id="songListTable"'), 'the widths are set before the table is drawn');

		foreach ($ctx->db()->query("SELECT id FROM account")->fetchAll() as $row) {
			$id = (int)$row['id'];
			assertContains("#songListTable :is(td[data-field=\"note_{$id}\"], th[data-sort-key=\"note_{$id}\"]) { --songNoteWidth: var(--songWidthRater{$id}); }", $body, "rater {$id}'s note column reads its own width");
		}

		$css = $ctx->get('/modules/music/css/styles.css')['body'];
		$fallbacks = [
			'--songWidthArtist' => '14em',
			'--songWidthTitle' => '14em',
			'--songWidthAliases' => '22em',
			'--songWidthAlbum' => '18em',
			'--songNoteWidth' => '10em',
		];
		foreach ($fallbacks as $property => $width) {
			assertContains("max-width: var({$property}, {$width});", $css, "{$property} unset clips at {$width}, as before");
		}
		assertContains('width: var(--songNoteWidth, 12em);', $css, 'and a note cell is 12em wide');
	},

	'Other names & versions lists every name but the one the title shows, a line each' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Known As Artist');
		$songId = makeSong($ctx, $artistId, 'Known Studio Version');
		$sleeve = $ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Known Sleeve Version',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);
		$ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Known Alternate Take',
			'song_id' => $songId,
			'also_alias_provided_name' => true,
		]]);
		$single = makeSong($ctx, $artistId, 'Known By One Name');

		$odd = makeAlbum($ctx, 'Known Odd Listing', $artistId, [
			['song_id' => $songId, 'song_alias_id' => (int)$sleeve['json'][0]['song_alias_id'], 'position' => 1],
		]);

		$body = $ctx->get("/music/songs?artist={$artistId}")['body'];
		$cell = columnsNamesCell(songsRowFor($body, $songId));

		assertSame(['alternative name: Known Alternate Take', 'alternative name: Known Sleeve Version'], columnsAliasLines($cell), 'the other names, a line each');
		assertContains('<div class="songAlias" title="alternative name: Known Alternate Take">alternative name: <span class="songAliasTitle">Known Alternate Take</span></div>', $cell, 'the name set apart, and the whole line in its tooltip');
		assertSame(['Known Studio Version', 'Known Alternate Take', 'Known Sleeve Version'], columnsCellTitles($cell), 'every name travels with the cell, the current one first');
		assertTrue(strpos($cell, 'class="songAliasLines" data-field="aliases"') < strpos($cell, 'class="songRelationLines" data-field="relations"'), 'above the relationships, each half under its own field');

		$card = songsCardFor($body, $songId);
		$cardCell = songsFieldCell($card, 'aliases');
		assertContains('>Known Alternate Take' . "\n" . 'Known Sleeve Version</dd>', $cardCell, 'the card lists them too');
		assertTrue(strpos($card, 'data-field="aliases"') < strpos($card, 'data-field="album"'), 'before the album, as the table has them');

		$retitled = columnsNamesCell(songsRowFor($ctx->get("/music/songs?artist={$artistId}&album={$odd}")['body'], $songId));
		assertSame(['alternative name: Known Studio Version', 'alternative name: Known Alternate Take'], columnsAliasLines($retitled), 'under the album\'s name for it, the real title is listed');

		$alone = columnsNamesCell(songsRowFor($body, $single));
		assertContains('<div class="songAliasLines" data-field="aliases"></div><div class="songRelationLines" data-field="relations"></div>', $alone, 'a song with one name and no relationship has an empty cell');
		assertSame(null, columnsCellTitles($alone), 'with no names for the script');
		assertSame('', songsFieldCell(songsCardFor($body, $single), 'aliases'), 'and no row on its card');

		assertTrue(strpos($body, 'data-sort-key="aliases"') === false, 'the column does not sort');
		assertContains('<th rowspan="2" class="songAliasesCell">Other names &amp; versions</th>', $body, 'and its header is plain text, like Links');
		assertTrue(strpos($body, '<th rowspan="2" class="songRelationsCell">') === false, 'and there is no Related column of its own');
	},

];
