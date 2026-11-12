<?php

/* The song list's column picker, its Other names column, its id columns and its
   time columns. The picker's tests use accounts of their own, so test_runner's
   table is left as every later case file expects it. */

const COLUMNS_ENDPOINT = '/music/ajax/song-columns';

/// The relationship id columns, all hidden until ticked.
const COLUMNS_RELATION_HIDES = ['songHideRemasteredBy', 'songHideRemasterOf', 'songHideInGameAs', 'songHideGameVersionOf', 'songHideAlternateTakes', 'songHideAlternateTakeOf'];

/// Everything a table hides by default after the raters, in table order.
const COLUMNS_LATER_HIDES = ['songHideRaterUpdated', 'songHideMedian', 'songHideMode', 'songHideHighest', 'songHideLowest', 'songHideSum', 'songHideRated', 'songHideAdded', 'songHideUpdated'];

/// What a table hides for an account that has chosen nothing, in table order.
const COLUMNS_DEFAULT_HIDES = ['songHideAliases', ...COLUMNS_RELATION_HIDES, ...COLUMNS_LATER_HIDES];

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

/// A row's Other names cell, whole.
function columnsNamesCell($row) {
	return preg_match('/<td class="songAliasesCell" data-field="aliases"[^>]*>.*?<\/td>/s', (string)$row, $match) ? $match[0] : '';
}

/// Each other-name line of that cell as it reads.
function columnsAliasLines($cell) {
	preg_match_all('/<div class="songAlias"[^>]*>(.*?)<\/div>/s', $cell, $matches);
	return array_map(fn($line) => html_entity_decode(strip_tags($line), ENT_QUOTES), $matches[1]);
}

/// Each line of that cell's tooltip, the version names' as name => lines.
function columnsVersionTooltips($cell) {
	preg_match_all('/<div class="songAlias" title="([^"]*)"><span class="songVersionHint">/', $cell, $matches);

	$tooltips = [];
	foreach ($matches[1] as $title) {
		$lines = explode("\n", html_entity_decode($title, ENT_QUOTES));
		$tooltips[array_shift($lines)] = $lines;
	}
	return $tooltips;
}

/// A row's id cell, whole.
function columnsRelationCell($row, $key) {
	return preg_match('/<td class="songRelationCell[^"]*" data-field="' . $key . '">.*?<\/td>/s', (string)$row, $match) ? $match[0] : '';
}

/// A separate song under a title, the remaster of another.
function columnsRemaster($ctx, $artistId, $title, $originalId) {
	return (int)$ctx->post('/music/ajax/song', [[
		'artist_id' => $artistId,
		'title' => $title,
		'song_id' => 'separate',
		'related_song_id' => $originalId,
		'relationship_type' => 'remaster',
		'relationship_role' => 'variant',
	]])['json'][0]['song_id'];
}

/// A time cell as the page writes it: [text, data-sort-value or null].
function columnsTimeCell($row, $field) {
	$cell = songsFieldCell($row, $field);
	if ($cell === '') {
		throw new Exception("no {$field} cell in the row");
	}

	return [
		trim(strip_tags($cell)),
		preg_match('/data-sort-value="([^"]*)"/', $cell, $match) ? $match[1] : null,
	];
}

/// The song ids of the page in its order, only those asked about.
function columnsSongOrder($body, $ids) {
	$order = [];
	foreach (songsRowChunks($body) as $chunk) {
		$id = (int)$chunk;
		if (in_array($id, $ids, true)) {
			$order[] = $id;
		}
	}
	return $order;
}

function columnsSetRatingTime($ctx, $accountId, $songId, $time) {
	$ctx->db()->prepare("UPDATE account_song SET updated_at = ? WHERE account_id = ? AND song_id = ?")->execute([$time, $accountId, $songId]);
}

function columnsRatingTime($ctx, $accountId, $songId) {
	$stmt = $ctx->db()->prepare("SELECT updated_at FROM account_song WHERE account_id = ? AND song_id = ?");
	$stmt->execute([$accountId, $songId]);
	$row = $stmt->fetch();

	return $row ? (int)$row['updated_at'] : null;
}

function columnsAddedTime($ctx, $songId) {
	$stmt = $ctx->db()->prepare("SELECT created_at FROM edit_audit WHERE kind = 'song' AND field = 'added' AND entity_id = ?");
	$stmt->execute([$songId]);

	return (int)$stmt->fetch()['created_at'];
}

/// Sets every History line about a song to one time.
function columnsSetHistoryTime($ctx, $songId, $time) {
	$ctx->db()->prepare("UPDATE edit_audit SET created_at = ? WHERE kind = 'song' AND entity_id = ?")->execute([$time, $songId]);
}

return [

	'a new account sees every column but Other names, the id columns, the statistics past σ and the time columns' => function ($ctx) {
		$body = columnsPage($ctx, 'columns_fresh');
		$me = columnsAccountId($ctx, 'columns_fresh');

		assertSame(COLUMNS_DEFAULT_HIDES, columnsTableHides($body), 'only Other names, the id columns, every statistic but Average and σ, Date added, Last updated and every Updated column start hidden');

		$boxes = columnsPickerBoxes($body);
		$raters = [];
		foreach ($ctx->db()->query("SELECT id FROM account ORDER BY id = {$me} DESC, id")->fetchAll() as $row) {
			$raters[] = 'rater_' . (int)$row['id'];
		}

		assertSame(
			array_merge(
				['id', 'artist', 'title', 'aliases', 'album', 'year', 'duration', 'links', 'remasteredBy', 'remasterOf', 'inGameAs', 'gameVersionOf', 'alternateTakes', 'alternateTakeOf'],
				$raters,
				['raterScore', 'raterNote', 'raterUpdated'],
				['average', 'deviation', 'median', 'mode', 'highest', 'lowest', 'sum', 'rated'],
				['added', 'updated']
			),
			array_keys($boxes),
			'the picker lists the fixed columns, every rater with you first and then each of their columns, the statistics, then the times'
		);
		assertSame(['aliases', 'remasteredBy', 'remasterOf', 'inGameAs', 'gameVersionOf', 'alternateTakes', 'alternateTakeOf', 'raterUpdated', 'median', 'mode', 'highest', 'lowest', 'sum', 'rated', 'added', 'updated'], array_keys(array_filter($boxes, fn($ticked) => !$ticked)), 'Other names, the id columns, the statistics past σ and the time columns are unticked, everything else ticked');
		foreach (['raterScore' => 'Score', 'raterNote' => 'Note', 'raterUpdated' => 'Updated'] as $key => $label) {
			assertTrue((bool)preg_match('/data-column="' . $key . '"[^>]*>\s*<span>' . $label . ' \(each rater\)<\/span>/', $body), "every rater's {$label} column is one entry");
		}
		assertTrue((bool)preg_match('/data-column="added"[^>]*>\s*<span>Date added<\/span>/', $body), 'Date added is named in full');

		assertContains('<summary class="songColumnPickerButton">Columns</summary>', $body, 'the picker is a button labelled Columns');
		assertTrue(strpos($body, 'id="songCardViewToggle"') < strpos($body, 'id="songColumnPicker"'), 'beside the card view button');
		assertContains("#songListTable.songHideRater{$me} :is(td[data-account-id=\"{$me}\"], th[data-sort-key=\"score_{$me}\"], th[data-sort-key=\"note_{$me}\"], th[data-sort-key=\"updated_{$me}\"], th.songRaterGroup{$me})", $body, 'and your own columns can be hidden too');
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
		assertSame(['songHideAliases', 'songHideAlbum', ...COLUMNS_RELATION_HIDES, ...COLUMNS_LATER_HIDES], columnsTableHides($body), 'the table hides it, in table order');
		assertSame(false, columnsPickerBoxes($body)['album'], 'and its box is unticked');

		$ctx->post(COLUMNS_ENDPOINT, ['column' => 'album', 'shown' => true]);
		assertSame(['album' => 1], columnsChoiceRows($ctx, 'columns_picker'), 'showing it again rewrites the same row');

		$ctx->post(COLUMNS_ENDPOINT, ['column' => 'aliases', 'shown' => true]);
		$ctx->post(COLUMNS_ENDPOINT, ['column' => "rater_{$other}", 'shown' => false]);

		$body = $ctx->get('/music/songs')['body'];
		assertSame([...COLUMNS_RELATION_HIDES, "songHideRater{$other}", ...COLUMNS_LATER_HIDES], columnsTableHides($body), 'Other names shows once ticked, and a rater hides');
		assertSame(true, columnsPickerBoxes($body)['aliases'], 'with its box ticked');
		assertSame(['album' => 1, 'aliases' => 1, "rater_{$other}" => 0], columnsChoiceRows($ctx, 'columns_picker'), 'each key is a row of its own');

		$ctx->ensureLoggedIn('columns_fresh', 'test password', false);
		assertSame(COLUMNS_DEFAULT_HIDES, columnsTableHides($ctx->get('/music/songs')['body']), 'another account keeps its own columns');
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
			'--songWidthAliases' => '14em',
			'--songWidthAlbum' => '18em',
			'--songNoteWidth' => '10em',
		];
		foreach ($fallbacks as $property => $width) {
			assertContains("max-width: var({$property}, {$width});", $css, "{$property} unset clips at {$width}, as before");
		}
		assertContains('width: var(--songNoteWidth, 12em);', $css, 'and a note cell is 12em wide');
	},

	'Other names lists every name but the one the title shows, a line each' => function ($ctx) {
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

		assertSame(['Known Alternate Take', 'Known Sleeve Version'], columnsAliasLines($cell), 'the other names, a line each');
		assertContains('<div class="songAlias" title="Known Alternate Take">Known Alternate Take</div>', $cell, 'the name alone, whole in its tooltip');
		assertSame(['Known Studio Version', 'Known Alternate Take', 'Known Sleeve Version'], columnsCellTitles($cell), 'every name travels with the cell, the current one first');

		$card = songsCardFor($body, $songId);
		$cardCell = songsFieldCell($card, 'aliases');
		assertContains('>Known Alternate Take' . "\n" . 'Known Sleeve Version</dd>', $cardCell, 'the card lists them too');
		assertTrue(strpos($card, 'data-field="aliases"') < strpos($card, 'data-field="album"'), 'before the album, as the table has them');

		$retitled = columnsNamesCell(songsRowFor($ctx->get("/music/songs?artist={$artistId}&album={$odd}")['body'], $songId));
		assertSame(['Known Studio Version', 'Known Alternate Take'], columnsAliasLines($retitled), 'under the album\'s name for it, the real title is listed');

		$alone = columnsNamesCell(songsRowFor($body, $single));
		assertSame('<td class="songAliasesCell" data-field="aliases"></td>', $alone, 'a song with one name and no versions has an empty cell');
		assertSame(null, columnsCellTitles($alone), 'with no names for the script');
		assertSame('', songsFieldCell(songsCardFor($body, $single), 'aliases'), 'and no row on its card');

		assertTrue(strpos($body, 'data-sort-key="aliases"') === false, 'the column does not sort');
		assertContains('<th rowspan="2" class="songAliasesCell">Other names</th>', $body, 'and its header is plain text, like Links');
		assertTrue(strpos($body, '<th rowspan="2" class="songRelationsCell">') === false, 'and there is no Related column');
	},

	'Other names sits between Title and Album, and the id columns between Links and the raters' => function ($ctx) {
		$body = columnsPage($ctx, 'columns_order');

		$relationColumns = [
			'remasteredBy' => ['Remastered by', 'Rem. by'],
			'remasterOf' => ['Remaster of', 'Rem. of'],
			'inGameAs' => ['In game as', 'Game as'],
			'gameVersionOf' => ['Game version of', 'Game of'],
			'alternateTakes' => ['Alternate takes', 'Alt. takes'],
			'alternateTakeOf' => ['Alternate take of', 'Alt. of'],
		];

		$headers = array_map(fn($class) => strpos($body, '<th rowspan="2" class="' . $class), array_merge(
			['songTitleCell', 'songAliasesCell', 'songAlbumCell', 'songLinksCell'],
			array_map(fn($key) => 'songRelationCell song' . ucfirst($key) . 'Cell', array_keys($relationColumns))
		));
		$headers[] = strpos($body, 'class="songRaterGroup');
		assertTrue(!in_array(false, $headers, true), 'every header is there');
		assertSame($headers, array_values(array_unique($headers)), 'once each');
		$sorted = $headers;
		sort($sorted);
		assertSame($sorted, $headers, 'Title, Other names, Album, then Links, the relationship id columns and the raters');
		foreach ($relationColumns as $key => [$name, $short]) {
			$class = 'songRelationCell song' . ucfirst($key) . 'Cell';
			assertContains('<th rowspan="2" class="' . $class . '" title="' . $name . '">' . $short . '</th>', $body, "{$name} is headed short, its full name the tooltip");
			assertTrue((bool)preg_match('/data-column="' . $key . '"[^>]*>\s*<span>' . $name . '<\/span>/', $body), "the picker names {$key} in full");
		}

		$row = songsRowChunks($body)[0];
		$cells = array_map(fn($field) => strpos($row, 'data-field="' . $field . '"'), array_merge(['title', 'aliases', 'album', 'links'], array_keys($relationColumns)));
		$cells[] = strpos($row, 'data-field="score_');
		$sorted = $cells;
		sort($sorted);
		assertTrue(!in_array(false, $cells, true), 'every row has the cells');
		assertSame($sorted, $cells, 'in the same order');
	},

	'Other names lists the names its versions go by, once each, saying which' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Version Names Artist');
		$original = makeSong($ctx, $artistId, 'Version Names Ninth Heaven');
		$ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Version Names Nine',
			'song_id' => $original,
			'also_alias_provided_name' => true,
		]]);
		$first = columnsRemaster($ctx, $artistId, 'Version Names 9th Heaven', $original);
		$second = columnsRemaster($ctx, $artistId, 'Version Names 9th Heaven', $original);
		$same = columnsRemaster($ctx, $artistId, 'Version Names Ninth Heaven', $original);

		$body = $ctx->get("/music/songs?artist={$artistId}")['body'];

		$cell = columnsNamesCell(songsRowFor($body, $original));
		assertSame(['Version Names Nine', 'Version Names 9th Heaven'], columnsAliasLines($cell), 'its own name, then the remasters\' name once, and none it has itself');
		assertSame(
			['Version Names 9th Heaven' => ["Name of the remastered version ({$first})", "Name of the remastered version ({$second})"]],
			columnsVersionTooltips($cell),
			'the remasters\' name is marked, its tooltip naming both'
		);
		assertContains('<div class="songAlias" title="Version Names Nine">Version Names Nine</div>', $cell, 'its own name is not marked');

		$remaster = columnsNamesCell(songsRowFor($body, $first));
		assertSame(['Version Names Ninth Heaven', 'Version Names Nine'], columnsAliasLines($remaster), 'a remaster lists every name of the original');
		assertSame(
			['Version Names Ninth Heaven' => ["Name of the original version ({$original})"], 'Version Names Nine' => ["Name of the original version ({$original})"]],
			columnsVersionTooltips($remaster),
			'each saying it is the original\'s'
		);

		assertSame(['Version Names Nine'], columnsAliasLines(columnsNamesCell(songsRowFor($body, $same))), 'and a remaster under the original\'s title lists only the name it lacks');
		assertTrue(strpos(songsFieldCell(songsCardFor($body, $original), 'aliases'), '9th Heaven') === false, 'the card lists only its own names');
	},

	'the id columns list each end of a remaster, marking a song called otherwise' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Id Columns Artist');
		$original = makeSong($ctx, $artistId, 'Id Columns Original');
		$renamed = columnsRemaster($ctx, $artistId, 'Id Columns Remastered', $original);
		$same = columnsRemaster($ctx, $artistId, 'Id Columns Original', $original);
		$loner = makeSong($ctx, $artistId, 'Id Columns Loner');

		$body = $ctx->get("/music/songs?artist={$artistId}")['body'];
		$link = fn($id, $as) => '<a class="songRelationLink' . ($as === null ? '' : ' songVersionHint') . '" href="/music/songs?songCard=' . $id . '" data-song-card-id="' . $id . '"'
			. ($as === null ? '' : ' title="as ' . $as . '"') . '>' . $id . '</a>';

		assertSame(
			'<td class="songRelationCell songRemasteredByCell" data-field="remasteredBy">' . $link($renamed, 'Id Columns Remastered') . ', ' . $link($same, null) . '</td>',
			columnsRelationCell(songsRowFor($body, $original), 'remasteredBy'),
			'the original lists its remasters, lowest first, marking the one called otherwise'
		);
		assertSame('<td class="songRelationCell songRemasterOfCell" data-field="remasterOf"></td>', columnsRelationCell(songsRowFor($body, $original), 'remasterOf'), 'and is the remaster of nothing');

		assertSame(
			'<td class="songRelationCell songRemasterOfCell" data-field="remasterOf">' . $link($original, 'Id Columns Original') . '</td>',
			columnsRelationCell(songsRowFor($body, $renamed), 'remasterOf'),
			'a remaster called otherwise lists the original, marked'
		);
		assertSame(
			'<td class="songRelationCell songRemasterOfCell" data-field="remasterOf">' . $link($original, null) . '</td>',
			columnsRelationCell(songsRowFor($body, $same), 'remasterOf'),
			'and one under the same title, unmarked'
		);
		assertSame('<td class="songRelationCell songRemasteredByCell" data-field="remasteredBy"></td>', columnsRelationCell(songsRowFor($body, $loner), 'remasteredBy'), 'a song with none has empty cells');

		assertTrue(strpos($body, 'data-sort-key="remasteredBy"') === false && strpos($body, 'data-sort-key="remasterOf"') === false, 'neither sorts');
	},

	'each rater has an Updated column, when their rating last changed, a borrowed one taking its version\'s time' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Times Rater Artist');
		$original = makeSong($ctx, $artistId, 'Times Rater Original');
		$remaster = columnsRemaster($ctx, $artistId, 'Times Rater Remaster', $original);
		$unrated = makeSong($ctx, $artistId, 'Times Rater Unrated');

		$ctx->ensureLoggedIn('times_rater', 'test password', false);
		$me = columnsAccountId($ctx, 'times_rater');

		$ctx->post('/music/ajax/song-rating', ['id' => $original, 'field' => 'score', 'value' => '7']);
		columnsSetRatingTime($ctx, $me, $original, 1700000000);
		$expected = [date('Y-m-d H:i:s', 1700000000), '1700000000'];

		$body = $ctx->get('/music/songs')['body'];
		assertSame($expected, columnsTimeCell(songsRowFor($body, $original), "updated_{$me}"), 'the rated song shows when it was rated, and sorts by it');
		assertSame($expected, columnsTimeCell(songsRowFor($body, $remaster), "updated_{$me}"), 'its remaster borrows the score, and the time with it');
		assertSame(['', null], columnsTimeCell(songsRowFor($body, $unrated), "updated_{$me}"), 'a song nobody rated is blank');
		assertTrue((bool)preg_match('/<td class="songRatingUpdatedCell songMineCell songMyUpdatedCell" data-field="updated_' . $me . '" data-account-id="' . $me . '"/', $body), 'the cell is the rater\'s, so hiding them hides it');
		assertTrue(strpos(songsFieldCell(songsRowFor($body, $original), "updated_{$me}"), 'songGate') === false, 'and nothing covers it');

		$ctx->post('/music/ajax/song-rating', ['id' => $original, 'field' => 'score', 'value' => '7']);
		assertSame(1700000000, columnsRatingTime($ctx, $me, $original), 'writing the same score again changes nothing, so the time stays');

		$rated = $ctx->post('/music/ajax/song-rating', ['id' => $original, 'field' => 'note', 'value' => 'Times note']);
		$now = columnsRatingTime($ctx, $me, $original);
		assertTrue($now > 1700000000, 'a new note moves it');
		$own = array_values(array_filter($rated['json']['ratings'], fn($entry) => $entry['song'] === $original));
		assertSame([date('Y-m-d H:i:s', $now), $now], [$own[0]['updated'], $own[0]['updatedSort']], 'and the answer carries the new time for the page');

		$poll = $ctx->post('/music/ajax/song-rating-poll', ['since' => $now, 'sinceAudit' => PHP_INT_MAX])['json'];
		$changes = array_values(array_filter($poll['changes'], fn($entry) => $entry['song'] === $remaster && $entry['account'] === $me));
		assertSame(date('Y-m-d H:i:s', $now), $changes[0]['updated'], 'the poll hands other pages the time on the remaster too');
		$stats = array_values(array_filter($poll['stats'], fn($entry) => $entry['song'] === $original));
		assertSame([date('Y-m-d H:i:s', $now), $now], [$stats[0]['updated'], $stats[0]['updatedSort']], 'and the song\'s Last updated beside its statistics');

		$ctx->post('/music/ajax/song-rating', ['id' => $original, 'field' => 'score', 'value' => '']);
		$ctx->post('/music/ajax/song-rating', ['id' => $original, 'field' => 'note', 'value' => '']);
		$body = $ctx->get('/music/songs')['body'];
		assertSame(['', null], columnsTimeCell(songsRowFor($body, $original), "updated_{$me}"), 'a rating cleared to nothing has no time');
		assertSame(['', null], columnsTimeCell(songsRowFor($body, $remaster), "updated_{$me}"), 'nor does what borrowed it');
	},

	'a rater\'s Updated is faded while it is a version\'s time rather than their own' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Times Borrowed Artist');
		$original = makeSong($ctx, $artistId, 'Times Borrowed Original');
		$remaster = columnsRemaster($ctx, $artistId, 'Times Borrowed Remaster', $original);

		$ctx->ensureLoggedIn('times_borrowed', 'test password', false);
		$me = columnsAccountId($ctx, 'times_borrowed');
		$faded = fn($body, $song) => strpos(songsFieldCell(songsRowFor($body, $song), "updated_{$me}"), '<span class="songInherited">') !== false;

		$rated = $ctx->post('/music/ajax/song-rating', ['id' => $original, 'field' => 'score', 'value' => '6']);
		$borrowed = [];
		foreach ($rated['json']['ratings'] as $entry) {
			$borrowed[$entry['song']] = $entry['updatedBorrowed'];
		}
		assertSame([$original => false, $remaster => true], array_intersect_key($borrowed, [$original => 0, $remaster => 0]), 'the answer says which time is borrowed, for the page');

		columnsSetRatingTime($ctx, $me, $original, 1700000000);
		$body = $ctx->get('/music/songs')['body'];
		assertTrue(!$faded($body, $original), 'a time of your own is plain');
		assertTrue($faded($body, $remaster), 'one borrowed with the score is faded');
		assertSame([date('Y-m-d H:i:s', 1700000000), '1700000000'], columnsTimeCell(songsRowFor($body, $remaster), "updated_{$me}"), 'and still reads and sorts as the time');

		$ctx->post('/music/ajax/song-rating', ['id' => $original, 'field' => 'note', 'value' => 'Times borrowed note']);
		columnsSetRatingTime($ctx, $me, $original, 1700000000);
		$ctx->post('/music/ajax/song-rating', ['id' => $remaster, 'field' => 'score', 'value' => '8']);
		columnsSetRatingTime($ctx, $me, $remaster, 1600000000);
		$body = $ctx->get('/music/songs')['body'];
		assertSame(date('Y-m-d H:i:s', 1700000000), columnsTimeCell(songsRowFor($body, $remaster), "updated_{$me}")[0], 'a borrowed note newer than your own score sets the time');
		assertTrue($faded($body, $remaster), 'so it is faded');

		columnsSetRatingTime($ctx, $me, $remaster, 1800000000);
		$body = $ctx->get('/music/songs')['body'];
		assertSame(date('Y-m-d H:i:s', 1800000000), columnsTimeCell(songsRowFor($body, $remaster), "updated_{$me}")[0], 'your own score newer than the borrowed note sets it');
		assertTrue(!$faded($body, $remaster), 'and it is plain');
	},

	'Date added is from History, the epoch for older songs, and Last updated the newest rating or History line' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Times Song Artist');
		$added = makeSong($ctx, $artistId, 'Times Song Added');
		$older = makeSong($ctx, $artistId, 'Times Song Older');

		$addedAt = columnsAddedTime($ctx, $added);
		$ctx->db()->prepare("DELETE FROM edit_audit WHERE kind = 'song' AND entity_id = ?")->execute([$older]);

		$body = $ctx->get('/music/songs')['body'];
		assertSame([date('Y-m-d H:i:s', $addedAt), (string)$addedAt], columnsTimeCell(songsRowFor($body, $added), 'added'), 'a song added through the page shows when');
		assertSame(['1970-01-01 00:00:00', '0'], columnsTimeCell(songsRowFor($body, $older), 'added'), 'one from before History is the epoch');
		assertSame([date('Y-m-d H:i:s', $addedAt), (string)$addedAt], columnsTimeCell(songsRowFor($body, $added), 'updated'), 'being added is its last update');
		assertSame(['', null], columnsTimeCell(songsRowFor($body, $older), 'updated'), 'and a song with no History and no rating has none');

		$ctx->ensureLoggedIn('times_song_rater', 'test password', false);
		$rater = columnsAccountId($ctx, 'times_song_rater');
		$ctx->post('/music/ajax/song-rating', ['id' => $added, 'field' => 'score', 'value' => '4']);
		columnsSetRatingTime($ctx, $rater, $added, 1650000000);
		columnsSetHistoryTime($ctx, $added, 1600000000);

		$body = $ctx->get('/music/songs')['body'];
		assertSame([date('Y-m-d H:i:s', 1650000000), '1650000000'], columnsTimeCell(songsRowFor($body, $added), 'updated'), 'a rating newer than its History is the last update');

		columnsSetHistoryTime($ctx, $added, 1690000000);
		$body = $ctx->get('/music/songs')['body'];
		assertSame([date('Y-m-d H:i:s', 1690000000), '1690000000'], columnsTimeCell(songsRowFor($body, $added), 'updated'), 'and an admin edit newer than every rating is');
	},

	'the time columns sort newest first on the first click, blanks last either way' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$artistId = $ctx->makeArtist('Times Sort Artist');
		$songs = [];
		foreach (['Old', 'New', 'Blank'] as $name) {
			$songs[$name] = makeSong($ctx, $artistId, 'Times Sort ' . $name);
		}
		columnsSetHistoryTime($ctx, $songs['Old'], 1500000000);
		columnsSetHistoryTime($ctx, $songs['New'], 1600000000);
		columnsSetHistoryTime($ctx, $songs['Blank'], 1550000000);

		$ctx->ensureLoggedIn('times_sorter', 'test password', false);
		$me = columnsAccountId($ctx, 'times_sorter');
		foreach (['Old' => 1500000000, 'New' => 1600000000] as $name => $time) {
			$ctx->post('/music/ajax/song-rating', ['id' => $songs[$name], 'field' => 'score', 'value' => '5']);
			columnsSetRatingTime($ctx, $me, $songs[$name], $time);
		}
		$ids = array_values($songs);

		$desc = $ctx->get("/music/songs?sort=updated_{$me}&dir=desc")['body'];
		assertSame([$songs['New'], $songs['Old'], $songs['Blank']], columnsSongOrder($desc, $ids), 'newest rating first, the unrated last');
		$asc = $ctx->get("/music/songs?sort=updated_{$me}&dir=asc")['body'];
		assertSame([$songs['Old'], $songs['New'], $songs['Blank']], columnsSongOrder($asc, $ids), 'oldest first, and the unrated still last');

		$added = $ctx->get('/music/songs?sort=added&dir=desc')['body'];
		assertSame([$songs['New'], $songs['Blank'], $songs['Old']], columnsSongOrder($added, $ids), 'Date added sorts by History');
		$updated = $ctx->get('/music/songs?sort=updated&dir=desc')['body'];
		assertSame([$songs['New'], $songs['Blank'], $songs['Old']], columnsSongOrder($updated, $ids), 'and so does Last updated');

		$body = $ctx->get('/music/songs')['body'];
		foreach (['added', 'updated', "updated_{$me}"] as $key) {
			assertTrue((bool)preg_match('/<th [^>]*data-sort-key="' . $key . '" data-sort-type="number" data-sort-first="desc">\s*<a href="\?sort=' . $key . '&amp;dir=desc"/', $body), "{$key} starts newest first");
		}
		assertTrue((bool)preg_match('/<th [^>]*data-sort-key="year" data-sort-type="number">\s*<a href="\?sort=year&amp;dir=asc"/', $body), 'and the other columns still start ascending');
		assertTrue((bool)preg_match('/<th [^>]*data-sort-key="updated" data-sort-type="number" data-sort-first="desc">\s*<a href="\?sort=updated&amp;dir=asc"/', $updated), 'a second click on it goes the other way');
	},

	'a rater\'s name spans as many of their columns as the picker shows' => function ($ctx) {
		$body = columnsPage($ctx, 'columns_span');

		$spans = function ($body) {
			preg_match_all('/<th colspan="(\d+)" class="songRaterGroup /', $body, $matches);
			return array_values(array_unique($matches[1]));
		};

		assertSame(['2'], $spans($body), 'Score and Note, with Updated hidden');

		$ctx->post(COLUMNS_ENDPOINT, ['column' => 'raterUpdated', 'shown' => true]);
		$body = $ctx->get('/music/songs')['body'];
		assertSame(['3'], $spans($body), 'all three once Updated shows');
		assertTrue(!in_array('songHideRaterUpdated', columnsTableHides($body), true), 'and the column is there');

		$ctx->post(COLUMNS_ENDPOINT, ['column' => 'raterScore', 'shown' => false]);
		$body = $ctx->get('/music/songs')['body'];
		assertSame(['2'], $spans($body), 'two with every Score hidden');
		assertTrue(in_array('songHideRaterScore', columnsTableHides($body), true), 'which the table hides');

		$ctx->post(COLUMNS_ENDPOINT, ['column' => 'raterNote', 'shown' => false]);
		$ctx->post(COLUMNS_ENDPOINT, ['column' => 'raterUpdated', 'shown' => false]);
		$body = $ctx->get('/music/songs')['body'];
		assertSame(['1'], $spans($body), 'never less than one, the stylesheet hiding the name once nothing of theirs shows');
		assertSame(['raterNote' => 0, 'raterScore' => 0, 'raterUpdated' => 0], columnsChoiceRows($ctx, 'columns_span'), 'each a row of its own');
	},

	'Date added and Last updated come after the statistics' => function ($ctx) {
		$body = columnsPage($ctx, 'columns_times_order');

		$headers = array_map(fn($key) => strpos($body, 'data-sort-key="' . $key . '"'), ['rated', 'added', 'updated']);
		$headers[] = strpos($body, '<th rowspan="2" class="songResultCell">');
		assertTrue(!in_array(false, $headers, true), 'every header is there');
		$sorted = $headers;
		sort($sorted);
		assertSame($sorted, $headers, 'the last statistic, Date added, Last updated, then Result');

		$row = songsRowChunks($body)[0];
		$cells = array_map(fn($field) => strpos($row, 'data-field="' . $field . '"'), ['rated', 'added', 'updated', 'result']);
		$sorted = $cells;
		sort($sorted);
		assertTrue(!in_array(false, $cells, true), 'every row has the cells');
		assertSame($sorted, $cells, 'in the same order');
	},

];
