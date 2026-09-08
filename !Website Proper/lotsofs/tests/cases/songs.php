<?php

const SONG_ENDPOINT = '/modules/music/ajax/song.php';

return [

	'a song is stored against its artist' => function ($ctx) {
		$artistId = $ctx->makeArtist('Song Owner');

		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'First Track']]);
		assertSame('ok', $response['json'][0]['status'], 'status');

		$song = $ctx->db()->query("SELECT artist_id, title FROM song WHERE title = 'First Track'")->fetch();
		assertSame($artistId, (int)$song['artist_id'], 'attached to the right artist');
	},

	'the added message names the artist' => function ($ctx) {
		$artistId = $ctx->makeArtist('Named In Message');

		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => 'Some Song']]);
		assertContains('Some Song', $response['json'][0]['message'], 'message names the song');
		assertContains('Named In Message', $response['json'][0]['message'], 'message names the artist');
	},

	'resubmitting a song reports a duplicate' => function ($ctx) {
		$artistId = $ctx->makeArtist('Repeat Owner');
		$payload = [['artist_id' => $artistId, 'title' => 'Repeated Track']];

		$ctx->post(SONG_ENDPOINT, $payload);
		$response = $ctx->post(SONG_ENDPOINT, $payload);

		assertSame('duplicate', $response['json'][0]['status'], 'status');

		$count = $ctx->db()->query("SELECT COUNT(*) c FROM song WHERE title = 'Repeated Track'")->fetch()['c'];
		assertSame(1, (int)$count, 'only one row exists');
	},

	'the same title can belong to two artists' => function ($ctx) {
		$first = $ctx->makeArtist('Coverer One');
		$second = $ctx->makeArtist('Coverer Two');

		$ctx->post(SONG_ENDPOINT, [['artist_id' => $first, 'title' => 'Shared Title']]);
		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $second, 'title' => 'Shared Title']]);

		assertSame('ok', $response['json'][0]['status'], 'second artist may reuse the title');
	},

	'a titleless row is rejected' => function ($ctx) {
		$artistId = $ctx->makeArtist('No Title Owner');

		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => '']]);
		assertSame('error', $response['json'][0]['status'], 'status');
	},

	'punctuation in titles survives the round trip' => function ($ctx) {
		$artistId = $ctx->makeArtist('Punctuation Owner');
		$titles = ["Ain't Talkin' 'Bout Love", 'Salt & Pepper', '<script>alert(1)</script>', 'Voilà'];

		foreach ($titles as $title) {
			$ctx->post(SONG_ENDPOINT, [['artist_id' => $artistId, 'title' => $title]]);
		}

		$stored = $ctx->db()->query("SELECT title FROM song WHERE artist_id = {$artistId}")->fetchAll(PDO::FETCH_COLUMN);
		foreach ($titles as $title) {
			assertTrue(in_array($title, $stored, true), "stored form of {$title}");
		}
	},

	'a database error comes back as json, not html' => function ($ctx) {
		$response = $ctx->post(SONG_ENDPOINT, [['artist_id' => 999999, 'title' => 'Orphan Track']]);

		assertSame(500, $response['status'], 'status');
		assertTrue(is_array($response['json']), 'body parses as json');
		assertTrue(isset($response['json']['error']), 'body carries an error key');
		assertTrue(strpos($response['body'], '<pre>') === false, 'body contains no html');
	},

	'endpoints reject non post requests' => function ($ctx) {
		$response = $ctx->get(SONG_ENDPOINT);

		assertSame(405, $response['status'], 'status');
		assertTrue(isset($response['json']['error']), 'body carries an error key');
	},

	'endpoints reject bodies that are not arrays' => function ($ctx) {
		$response = $ctx->post(SONG_ENDPOINT, 'null');

		assertSame(400, $response['status'], 'status');
		assertTrue(isset($response['json']['error']), 'body carries an error key');
	},

];
