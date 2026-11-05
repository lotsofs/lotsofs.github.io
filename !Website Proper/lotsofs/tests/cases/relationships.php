<?php

const RELATIONSHIP_ENDPOINT = '/music/ajax/song-relationship';

function relationshipRows($ctx, $songId) {
	return $ctx->db()->query("
		SELECT variant_song_id, target_song_id, relationship_type
		FROM song_relationship
		WHERE variant_song_id = {$songId} OR target_song_id = {$songId}
		ORDER BY id
	")->fetchAll();
}

function relateSongs($ctx, $songId, $otherId, $role, $type = 'remaster') {
	return $ctx->post(RELATIONSHIP_ENDPOINT, ['action' => 'add', 'song_id' => $songId, 'other_song_id' => $otherId, 'type' => $type, 'role' => $role]);
}

return [

	'a relationship is one row, the right way round from either end' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Ends Owner');
		$original = makeSong($ctx, $artistId, 'Relation Ends Original');
		$remaster = makeSong($ctx, $artistId, 'Relation Ends Remaster');

		$response = relateSongs($ctx, $original, $remaster, 'target');
		assertSame('ok', $response['json']['status'], 'added from the original as its target');

		$rows = relationshipRows($ctx, $original);
		assertSame(1, count($rows), 'one row for the pair');
		assertSame($remaster, (int)$rows[0]['variant_song_id'], 'the remaster is the variant');
		assertSame($original, (int)$rows[0]['target_song_id'], 'the original is the target');
		assertSame('remaster', $rows[0]['relationship_type'], 'type');

		$lists = $response['json']['relations'];
		assertSame('original version of', $lists[$original][0]['label'], "the original's end");
		assertSame('Relation Ends Remaster', $lists[$original][0]['title'], 'names the other song');
		assertSame('remastered version of', $lists[$remaster][0]['label'], "the remaster's end");
		assertSame($original, $lists[$remaster][0]['song_id'], 'points at the other song');

		$again = relateSongs($ctx, $remaster, $original, 'variant');
		assertSame('duplicate', $again['json']['status'], 'the same pair from the other end is the same row');
		assertSame(1, count(relationshipRows($ctx, $original)), 'still one row');
	},

	'both cards show their own end and link to the other' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Card Owner');
		$original = makeSong($ctx, $artistId, 'Relation Card Original');
		$remaster = makeSong($ctx, $artistId, 'Relation Card Remaster');
		$loner = makeSong($ctx, $artistId, 'Relation Card Loner');
		relateSongs($ctx, $remaster, $original, 'variant');

		$body = $ctx->get('/music/songs')['body'];

		$originalCard = songsCardFor($body, $original);
		assertContains('songRelationsCell', $originalCard, 'the original has the row');
		assertContains('original version of <a class="songRelationLink" href="/music/songs?songCard=' . $remaster . '" data-song-card-id="' . $remaster . '"><span class="songRelationId">' . $remaster . ':</span> <span class="songRelationTitle">Relation Card Remaster</span></a>', $originalCard, "the original's line, the other song by id and title");

		$remasterCard = songsCardFor($body, $remaster);
		assertContains('remastered version of <a class="songRelationLink" href="/music/songs?songCard=' . $original . '" data-song-card-id="' . $original . '"><span class="songRelationId">' . $original . ':</span> <span class="songRelationTitle">Relation Card Original</span></a>', $remasterCard, "the remaster's line");

		assertTrue(strpos(songsCardFor($body, $loner), 'songRelationsCell') === false, 'a song with none has no row');

		$tableCell = songsFieldCell(songsRowFor($body, $original), 'relations');
		assertContains('<div class="songRelation" title="original version of ' . $remaster . ': Relation Card Remaster">original version of <a class="songRelationLink" href="/music/songs?songCard=' . $remaster . '" data-song-card-id="' . $remaster . '">', $tableCell, 'the table cell has the line, its whole text in the title');
		assertSame('<td class="songRelationsCell" data-field="relations"></td>', songsFieldCell(songsRowFor($body, $loner), 'relations'), 'and a song with none has an empty cell');
	},

	'a relationship can be turned round in place' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Turn Owner');
		$original = makeSong($ctx, $artistId, 'Relation Turn Original');
		$remaster = makeSong($ctx, $artistId, 'Relation Turn Remaster');
		relateSongs($ctx, $remaster, $original, 'variant');
		$id = (int)$ctx->db()->query("SELECT id FROM song_relationship WHERE variant_song_id = {$remaster}")->fetch()['id'];

		$response = $ctx->post(RELATIONSHIP_ENDPOINT, ['action' => 'update', 'id' => $id, 'song_id' => $original, 'other_song_id' => $remaster, 'type' => 'remaster', 'role' => 'variant']);

		assertSame('ok', $response['json']['status'], 'status');
		$rows = $ctx->db()->query("SELECT id, variant_song_id, target_song_id FROM song_relationship WHERE id = {$id}")->fetchAll();
		assertSame([['id' => $id, 'variant_song_id' => $original, 'target_song_id' => $remaster]], array_map(fn($row) => array_map('intval', $row), $rows), 'the same row, the other way round');
		assertSame('remastered version of', $response['json']['relations'][$original][0]['label'], "the original's end now reads as the remaster");
		assertSame(1, count(relationshipRows($ctx, $original)), 'still one row');
	},

	'pointing a relationship at another song moves its line' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Move Owner');
		$original = makeSong($ctx, $artistId, 'Relation Move Original');
		$wrong = makeSong($ctx, $artistId, 'Relation Move Wrong');
		$right = makeSong($ctx, $artistId, 'Relation Move Right');
		relateSongs($ctx, $wrong, $original, 'variant');
		$id = (int)$ctx->db()->query("SELECT id FROM song_relationship WHERE variant_song_id = {$wrong}")->fetch()['id'];

		$response = $ctx->post(RELATIONSHIP_ENDPOINT, ['action' => 'update', 'id' => $id, 'song_id' => $original, 'other_song_id' => $right, 'type' => 'remaster', 'role' => 'target']);

		assertSame('ok', $response['json']['status'], 'status');
		assertSame([], $response['json']['relations'][$wrong], 'the song it pointed at loses the line');
		assertSame([$right], array_column($response['json']['relations'][$original], 'song_id'), 'which now names the other song');
		assertSame($id, (int)$response['json']['relations'][$right][0]['id'], 'and that song has it, as the same row');
	},

	'an update is refused for a row the song is not on, and for one that would repeat another' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Update Refusal Owner');
		$original = makeSong($ctx, $artistId, 'Relation Update Original');
		$first = makeSong($ctx, $artistId, 'Relation Update First');
		$second = makeSong($ctx, $artistId, 'Relation Update Second');
		$stranger = makeSong($ctx, $artistId, 'Relation Update Stranger');
		relateSongs($ctx, $first, $original, 'variant');
		relateSongs($ctx, $second, $original, 'variant');
		$secondId = (int)$ctx->db()->query("SELECT id FROM song_relationship WHERE variant_song_id = {$second}")->fetch()['id'];

		$elsewhere = $ctx->post(RELATIONSHIP_ENDPOINT, ['action' => 'update', 'id' => $secondId, 'song_id' => $stranger, 'other_song_id' => $original, 'type' => 'remaster', 'role' => 'variant']);
		assertSame('error', $elsewhere['json']['status'], 'not a row this song is on');

		$repeat = $ctx->post(RELATIONSHIP_ENDPOINT, ['action' => 'update', 'id' => $secondId, 'song_id' => $original, 'other_song_id' => $first, 'type' => 'remaster', 'role' => 'target']);
		assertSame('duplicate', $repeat['json']['status'], 'the same pair and type as another row');
		assertSame($second, (int)$ctx->db()->query("SELECT variant_song_id FROM song_relationship WHERE id = {$secondId}")->fetch()['variant_song_id'], 'and the row is left as it was');
	},

	'the other song is named and escaped as it is called now' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Escape Owner');
		$original = makeSong($ctx, $artistId, 'Relation <b>Escape</b> Original');
		$remaster = makeSong($ctx, $artistId, 'Relation Escape Remaster');
		relateSongs($ctx, $remaster, $original, 'variant');
		$ctx->post('/music/ajax/song-edit', ['id' => $original, 'field' => 'title', 'value' => 'Relation <i>Renamed</i> Original']);

		$card = songsCardFor($ctx->get('/music/songs')['body'], $remaster);
		assertContains('Relation &lt;i&gt;Renamed&lt;/i&gt; Original</span></a>', $card, 'the current name, escaped');
		assertTrue(strpos($card, '<i>Renamed') === false, 'no raw markup');
	},

	'the same pair turned round is a duplicate, added or updated' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Reverse Owner');
		$original = makeSong($ctx, $artistId, 'Relation Reverse Original');
		$remaster = makeSong($ctx, $artistId, 'Relation Reverse Remaster');
		$third = makeSong($ctx, $artistId, 'Relation Reverse Third');
		relateSongs($ctx, $remaster, $original, 'variant');

		assertSame('duplicate', relateSongs($ctx, $remaster, $original, 'target')['json']['status'], 'the remaster as the original of its own original');
		assertSame(1, count(relationshipRows($ctx, $original)), 'nothing more was stored');

		relateSongs($ctx, $third, $original, 'variant');
		$id = (int)$ctx->db()->query("SELECT id FROM song_relationship WHERE variant_song_id = {$third}")->fetch()['id'];
		$turned = $ctx->post(RELATIONSHIP_ENDPOINT, ['action' => 'update', 'id' => $id, 'song_id' => $original, 'other_song_id' => $remaster, 'type' => 'remaster', 'role' => 'variant']);
		assertSame('duplicate', $turned['json']['status'], 'an update onto the pair the other way round');
		assertSame($third, (int)$ctx->db()->query("SELECT variant_song_id FROM song_relationship WHERE id = {$id}")->fetch()['variant_song_id'], 'and that row is left as it was');
	},

	'a relationship needs two different songs and a known type and end' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Refusal Owner');
		$song = makeSong($ctx, $artistId, 'Relation Refusal Song');
		$other = makeSong($ctx, $artistId, 'Relation Refusal Other');

		assertSame('error', relateSongs($ctx, $song, $song, 'variant')['json']['status'], 'itself');
		assertSame('error', relateSongs($ctx, $song, 999999, 'variant')['json']['status'], 'a song that does not exist');
		assertSame('error', relateSongs($ctx, $song, $other, 'variant', 'cover')['json']['status'], 'a type not in the list');
		assertSame('error', relateSongs($ctx, $song, $other, 'sideways')['json']['status'], 'an end that is neither');
		assertSame(0, count(relationshipRows($ctx, $song)), 'nothing was stored');
	},

	'removing a relationship takes it off both cards' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Remove Owner');
		$original = makeSong($ctx, $artistId, 'Relation Remove Original');
		$remaster = makeSong($ctx, $artistId, 'Relation Remove Remaster');
		$kept = makeSong($ctx, $artistId, 'Relation Remove Kept');
		relateSongs($ctx, $remaster, $original, 'variant');
		relateSongs($ctx, $kept, $original, 'variant');

		$id = (int)$ctx->db()->query("SELECT id FROM song_relationship WHERE variant_song_id = {$remaster}")->fetch()['id'];
		$response = $ctx->post(RELATIONSHIP_ENDPOINT, ['action' => 'remove', 'id' => $id]);

		assertSame('ok', $response['json']['status'], 'status');
		assertSame([], $response['json']['relations'][$remaster], "the remaster's list is empty");
		assertSame([$kept], array_column($response['json']['relations'][$original], 'song_id'), "the original keeps its other one");
		assertSame('error', $ctx->post(RELATIONSHIP_ENDPOINT, ['action' => 'remove', 'id' => $id])['json']['status'], 'gone already');
	},

	'a type no longer in the list still shows, as its code' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Retired Owner');
		$song = makeSong($ctx, $artistId, 'Relation Retired Song');
		$other = makeSong($ctx, $artistId, 'Relation Retired Other');
		$ctx->db()->exec("INSERT INTO song_relationship (variant_song_id, target_song_id, relationship_type) VALUES ({$song}, {$other}, 'retired_kind')");

		$card = songsCardFor($ctx->get('/music/songs')['body'], $song);
		assertContains('retired_kind <a class="songRelationLink"', $card, 'shown, not dropped');
	},

	'a separate song from the import can say how it relates' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Import Owner');
		$remaster = makeSong($ctx, $artistId, 'Relation Import Title');

		$response = $ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Relation Import Title',
			'song_id' => 'separate',
			'related_song_id' => (string)$remaster,
			'relationship_type' => 'remaster',
			'relationship_role' => 'target',
		]]);

		assertSame('ok', $response['json'][0]['status'], 'status');
		$original = (int)$response['json'][0]['song_id'];

		$rows = relationshipRows($ctx, $original);
		assertSame(1, count($rows), 'one row');
		assertSame($remaster, (int)$rows[0]['variant_song_id'], 'the existing song is the remaster');
		assertSame($original, (int)$rows[0]['target_song_id'], 'the new song is the original');
	},

	'a separate song without a relationship writes none' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Import Bare Owner');
		makeSong($ctx, $artistId, 'Relation Import Bare Title');

		$response = $ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Relation Import Bare Title', 'song_id' => 'separate']]);

		assertSame('ok', $response['json'][0]['status'], 'status');
		assertSame(0, count(relationshipRows($ctx, (int)$response['json'][0]['song_id'])), 'no row');
	},

	'an import cannot relate a song to another artists song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Relation Import Mine');
		$stranger = makeSong($ctx, $ctx->makeArtist('Relation Import Theirs'), 'Relation Import Stranger');
		makeSong($ctx, $artistId, 'Relation Import Guarded');

		$response = $ctx->post('/music/ajax/song', [[
			'artist_id' => $artistId,
			'title' => 'Relation Import Guarded',
			'song_id' => 'separate',
			'related_song_id' => $stranger,
			'relationship_type' => 'remaster',
			'relationship_role' => 'target',
		]]);

		assertSame('error', $response['json'][0]['status'], 'refused');
		assertSame(1, $ctx->songCount('Relation Import Guarded'), 'no second song');
		assertSame(0, count(relationshipRows($ctx, $stranger)), 'no row');
	},

];
