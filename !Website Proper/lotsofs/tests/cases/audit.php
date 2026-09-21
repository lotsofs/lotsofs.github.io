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

		$ids = array_map('intval', array_map(fn($c) => substr($c, 0, strpos($c, '"')), auditRowChunks($ctx->get('/music/audit')['body'])));

		assertTrue(count($ids) > 1, 'there are entries to order');

		$sorted = $ids;
		rsort($sorted);
		assertSame($sorted, $ids, 'entries run newest to oldest');
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
