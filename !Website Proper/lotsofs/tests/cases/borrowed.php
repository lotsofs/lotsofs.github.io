<?php

const BORROWED_RATING_ENDPOINT = '/music/ajax/song-rating';

/// An admin with blind rating off, so a test reads every value: relationships need an admin.
function borrowedReader($ctx) {
	$ctx->ensureLoggedIn('borrowed_reader');
	$ctx->db()->exec("UPDATE account SET blind_rating = 0 WHERE account_name = 'borrowed_reader'");
	$ctx->ensureLoggedIn('borrowed_reader');

	return (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'borrowed_reader'")->fetch()['id'];
}

/// Two songs by one artist, the second a remaster of the first.
function borrowedPair($ctx, $name) {
	$artistId = $ctx->makeArtist("{$name} Owner");
	$original = makeSong($ctx, $artistId, "{$name} Original");
	$remaster = makeSong($ctx, $artistId, "{$name} Remaster");
	$ctx->post('/music/ajax/song-relationship', ['action' => 'add', 'song_id' => $remaster, 'other_song_id' => $original, 'type' => 'remaster', 'role' => 'variant']);

	return [$artistId, $original, $remaster];
}

function borrowedRate($ctx, $songId, $field, $value) {
	return $ctx->post(BORROWED_RATING_ENDPOINT, ['id' => $songId, 'field' => $field, 'value' => $value]);
}

function borrowedStats($body, $songId) {
	$row = songsRowFor($body, $songId);

	return ['average' => songsCellValue($row, 'average'), 'rated' => songsCellValue($row, 'rated')];
}

return [

	'an empty rating shows what the rater gave the other version, faded, and it counts' => function ($ctx) {
		$me = borrowedReader($ctx);
		[, $original, $remaster] = borrowedPair($ctx, 'Borrow Basic');
		borrowedRate($ctx, $remaster, 'score', '8');
		borrowedRate($ctx, $remaster, 'note', 'Lent note');

		$body = $ctx->get('/music/songs')['body'];
		$row = songsRowFor($body, $original);

		$score = songsFieldCell($row, "score_{$me}");
		assertClasses(['songCellEmpty', 'songInheritedCell'], $score, '/class="([^"]*)"/', 'the score cell is empty and borrowing');
		assertContains('<span class="songGateValue"></span><span class="songInherited">8</span>', $score, 'its own value empty, the borrowed one beside it');

		$note = songsFieldCell($row, "note_{$me}");
		assertContains('<span class="ratingNoteText songGateValue"></span><span class="songInherited">Lent note</span>', $note, 'the note borrows too');
		assertContains('<span class="songInherited">8</span>', songsFieldCell(songsCardFor($body, $original), "score_{$me}"), 'and so does the card');

		assertSame(['average' => '8', 'rated' => '1'], borrowedStats($body, $original), 'it counts in the statistics and the rated count');
		assertSame(null, $ctx->db()->query("SELECT score FROM account_song WHERE song_id = {$original}")->fetch()['score'] ?? null, 'and nothing was written for it');
	},

	'a game version and an alternate take lend their ratings as a remaster does' => function ($ctx) {
		$me = borrowedReader($ctx);
		$artistId = $ctx->makeArtist('Borrow Kinds Owner');
		$soundtrack = makeSong($ctx, $artistId, 'Borrow Kinds Soundtrack');
		$cue = makeSong($ctx, $artistId, 'Borrow Kinds Cue');
		$main = makeSong($ctx, $artistId, 'Borrow Kinds Main Take');
		$alternate = makeSong($ctx, $artistId, 'Borrow Kinds Alt Take');
		$ctx->post('/music/ajax/song-relationship', ['action' => 'add', 'song_id' => $cue, 'other_song_id' => $soundtrack, 'type' => 'game', 'role' => 'variant']);
		$ctx->post('/music/ajax/song-relationship', ['action' => 'add', 'song_id' => $alternate, 'other_song_id' => $main, 'type' => 'alternate', 'role' => 'variant']);
		borrowedRate($ctx, $soundtrack, 'score', '7');
		borrowedRate($ctx, $alternate, 'score', '9');

		$body = $ctx->get('/music/songs')['body'];
		assertContains('<span class="songInherited">7</span>', songsFieldCell(songsRowFor($body, $cue), "score_{$me}"), 'the cue borrows its soundtrack release\'s score');
		assertContains('<span class="songInherited">9</span>', songsFieldCell(songsRowFor($body, $main), "score_{$me}"), 'and the main take its alternate take\'s');
	},

	'several versions average their scores, and the newest note wins' => function ($ctx) {
		$me = borrowedReader($ctx);
		[$artistId, $original, $older] = borrowedPair($ctx, 'Borrow Many');
		$newer = makeSong($ctx, $artistId, 'Borrow Many Second Remaster');
		$ctx->post('/music/ajax/song-relationship', ['action' => 'add', 'song_id' => $newer, 'other_song_id' => $original, 'type' => 'remaster', 'role' => 'variant']);

		borrowedRate($ctx, $older, 'score', '6');
		borrowedRate($ctx, $newer, 'score', '9');
		borrowedRate($ctx, $older, 'note', 'Older version note');
		borrowedRate($ctx, $newer, 'note', 'Newer version note');
		/// The older version's note rewritten later, by its audit time.
		$ctx->db()->exec("UPDATE rating_audit SET created_at = created_at + 100 WHERE song_id = {$older} AND field = 'note'");

		$body = $ctx->get('/music/songs')['body'];
		$row = songsRowFor($body, $original);

		assertContains('<span class="songInherited">7.5</span>', songsFieldCell($row, "score_{$me}"), 'the average of the versions');
		assertContains('<span class="songInherited">Older version note</span>', songsFieldCell($row, "note_{$me}"), 'the note written last');

		$order = array_values(array_filter(
			array_map('intval', array_map(fn($chunk) => strtok($chunk, '"'), songsRowChunks($ctx->get("/music/songs?sort=score_{$me}&dir=desc")['body']))),
			fn($id) => in_array($id, [$original, $older, $newer], true)
		));
		assertSame([$newer, $original, $older], $order, 'and sorting places the borrowed 7.5 between 9 and 6');
	},

	'an audited note beats one from before the audit, however recently that row was touched' => function ($ctx) {
		$me = borrowedReader($ctx);
		[$artistId, $original, $old] = borrowedPair($ctx, 'Borrow Unaudited');
		$audited = makeSong($ctx, $artistId, 'Borrow Unaudited Audited');
		$ctx->post('/music/ajax/song-relationship', ['action' => 'add', 'song_id' => $audited, 'other_song_id' => $original, 'type' => 'remaster', 'role' => 'variant']);

		borrowedRate($ctx, $audited, 'note', 'Audited note');
		borrowedRate($ctx, $old, 'note', 'Note from before the audit');
		/// As a note written before migration 005: no audit row, and its row touched since by a score.
		$ctx->db()->exec("DELETE FROM rating_audit WHERE song_id = {$old} AND field = 'note'");
		$ctx->db()->exec("UPDATE account_song SET updated_at = updated_at + 1000 WHERE song_id = {$old}");

		$note = songsFieldCell(songsRowFor($ctx->get('/music/songs')['body'], $original), "note_{$me}");
		assertContains('<span class="songInherited">Audited note</span>', $note, 'the audited note is the newer one');
	},

	'a rating of its own replaces the borrowed one' => function ($ctx) {
		$me = borrowedReader($ctx);
		[, $original, $remaster] = borrowedPair($ctx, 'Borrow Own');
		borrowedRate($ctx, $remaster, 'score', '8');
		borrowedRate($ctx, $original, 'score', '5');

		$body = $ctx->get('/music/songs')['body'];
		$score = songsFieldCell(songsRowFor($body, $original), "score_{$me}");
		assertContains('<span class="songGateValue">5</span>', $score, 'the real score');
		assertTrue(strpos($score, 'songInherited') === false, 'nothing borrowed beside it');
		assertSame(['average' => '5', 'rated' => '1'], borrowedStats($body, $original), 'and only it counts');

		$cleared = borrowedRate($ctx, $original, 'score', '');
		$entry = array_values(array_filter($cleared['json']['ratings'], fn($entry) => $entry['song'] === $original))[0];
		assertSame(['score' => '8', 'scoreBorrowed' => true], ['score' => $entry['score'], 'scoreBorrowed' => $entry['scoreBorrowed']], 'clearing it borrows again, and the answer says so');
	},

	'a kind that does not lend leaves the song unrated' => function ($ctx) {
		$me = borrowedReader($ctx);
		$artistId = $ctx->makeArtist('Borrow Kind Owner');
		$song = makeSong($ctx, $artistId, 'Borrow Kind Song');
		$other = makeSong($ctx, $artistId, 'Borrow Kind Other');
		$ctx->db()->exec("INSERT INTO song_relationship (variant_song_id, target_song_id, relationship_type) VALUES ({$other}, {$song}, 'retired_kind')");
		borrowedRate($ctx, $other, 'score', '8');

		$body = $ctx->get('/music/songs')['body'];
		assertTrue(strpos(songsFieldCell(songsRowFor($body, $song), "score_{$me}"), 'songInherited') === false, 'nothing borrowed');
		assertSame(['average' => '', 'rated' => '0'], borrowedStats($body, $song), 'and nothing counted');
	},

	'the album card, the albums list and the artist card count a borrowed score' => function ($ctx) {
		borrowedReader($ctx);
		[$artistId, $original, $remaster] = borrowedPair($ctx, 'Borrow Album');
		borrowedRate($ctx, $remaster, 'score', '8');
		$albumId = makeAlbum($ctx, 'Borrow Album Record', $artistId, [['song_id' => $original, 'position' => 1, 'song_alias_id' => null]]);

		$card = $ctx->post('/music/ajax/album-card', ['album_id' => $albumId])['json']['html'];
		assertContains('albumGraphDotBorrowed', $card, 'the album graph draws it, faded');

		$listRow = listRowFor($ctx->get('/music/albums')['body'], 'data-album-card-id', $albumId);
		assertSame('8', listCells($listRow, 'albumStatsScoreCell')[0], "the albums list's average counts it");

		$artistCard = $ctx->post('/music/ajax/artist-card', ['artist_id' => $artistId])['json']['html'];
		assertContains('albumGraphDotBorrowed', $artistCard, 'and so does the artist graph');
	},

	'your own borrowed score uncovers the others, and theirs is covered until then' => function ($ctx) {
		borrowedReader($ctx);
		[, $original, $remaster] = borrowedPair($ctx, 'Borrow Gate');
		[, $plainOriginal, $plainRemaster] = borrowedPair($ctx, 'Borrow Gate Plain');

		$ctx->ensureLoggedIn('borrowed_other', 'test password', false);
		$other = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'borrowed_other'")->fetch()['id'];
		borrowedRate($ctx, $original, 'score', '3');
		borrowedRate($ctx, $plainRemaster, 'score', '4');

		$ctx->ensureLoggedIn('borrowed_blind', 'test password', false);
		$ctx->db()->exec("UPDATE account SET blind_rating = 1 WHERE account_name = 'borrowed_blind'");
		$ctx->ensureLoggedIn('borrowed_blind', 'test password', false);
		borrowedRate($ctx, $remaster, 'score', '7');

		$body = $ctx->get('/music/songs')['body'];
		assertTrue(strpos(songsFieldCell(songsRowFor($body, $original), "score_{$other}"), 'songGated') === false, 'a borrowed score of my own uncovers theirs');
		assertClasses(['songGated'], songsFieldCell(songsRowFor($body, $plainOriginal), "score_{$other}"), '/class="([^"]*)"/', 'their borrowed score is covered while I have nothing');
	},

	'a write answers with what it changed on the versions, and the poll carries it' => function ($ctx) {
		$me = borrowedReader($ctx);
		[, $original, $remaster] = borrowedPair($ctx, 'Borrow Live');
		$since = time() - 1;

		$write = borrowedRate($ctx, $remaster, 'score', '8');
		$lent = array_values(array_filter($write['json']['ratings'], fn($entry) => $entry['song'] === $original && $entry['account'] === $me));
		assertSame(['score' => '8', 'scoreBorrowed' => true], ['score' => $lent[0]['score'], 'scoreBorrowed' => $lent[0]['scoreBorrowed']], 'the answer has the borrowed score');
		assertSame('8', array_column($write['json']['relatedStats'], 'average', 'song')[$original], "and the version's statistics");

		$poll = $ctx->post('/music/ajax/song-rating-poll', ['since' => $since, 'sinceAudit' => PHP_INT_MAX]);
		$polled = array_values(array_filter($poll['json']['changes'], fn($entry) => $entry['song'] === $original && $entry['account'] === $me));
		assertSame(true, $polled[0]['scoreBorrowed'] ?? null, 'the poll brings the version along');
		assertSame('8', array_column($poll['json']['stats'], 'average', 'song')[$original] ?? null, 'with its statistics');
	},

	'linking and unlinking versions answers with what they now borrow' => function ($ctx) {
		$me = borrowedReader($ctx);
		$artistId = $ctx->makeArtist('Borrow Link Owner');
		$original = makeSong($ctx, $artistId, 'Borrow Link Original');
		$remaster = makeSong($ctx, $artistId, 'Borrow Link Remaster');
		borrowedRate($ctx, $remaster, 'score', '6');

		$linked = $ctx->post('/music/ajax/song-relationship', ['action' => 'add', 'song_id' => $original, 'other_song_id' => $remaster, 'type' => 'remaster', 'role' => 'target']);
		$entry = array_values(array_filter($linked['json']['ratings'], fn($entry) => $entry['song'] === $original && $entry['account'] === $me))[0];
		assertSame(['6', true], [$entry['score'], $entry['scoreBorrowed']], 'linking lends the score');
		assertSame('6', array_column($linked['json']['stats'], 'average', 'song')[$original], 'and counts it');

		$id = (int)$ctx->db()->query("SELECT id FROM song_relationship WHERE target_song_id = {$original}")->fetch()['id'];
		$unlinked = $ctx->post('/music/ajax/song-relationship', ['action' => 'remove', 'id' => $id]);
		$entry = array_values(array_filter($unlinked['json']['ratings'], fn($entry) => $entry['song'] === $original && $entry['account'] === $me))[0];
		assertSame(['', false], [$entry['score'], $entry['scoreBorrowed']], 'unlinking takes it back');
		assertSame('', array_column($unlinked['json']['stats'], 'average', 'song')[$original], 'and the statistics with it');
	},

];
