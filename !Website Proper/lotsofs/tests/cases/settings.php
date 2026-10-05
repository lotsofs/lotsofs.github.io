<?php

/* The cog menu, and the one switch in it. Every test here uses an account of
   its own: this file sorts before songs.php and shares its database, so a test
   that left `test_runner` with the gate off would break every gate test there
   rather than its own. */

function saveGateSetting($ctx, $on, $return = '/music/songs') {
	$fields = [
		'csrf_token' => $ctx->csrfTokenFrom('/music/songs'),
		'return' => $return,
	];

	if ($on) {
		$fields['blind_rating'] = '1';
	}

	return $ctx->postForm('/music/settings', $fields);
}

function gateSettingOf($ctx, $name) {
	$stmt = $ctx->db()->prepare("SELECT blind_rating FROM account WHERE account_name = ?");
	$stmt->execute([$name]);
	$row = $stmt->fetch();

	return $row === false ? null : (int)$row['blind_rating'];
}

return [

	'the cog sits in the nav beside the colour and language menus' => function ($ctx) {
		$ctx->ensureLoggedIn('gate_nav', 'test password', false);

		$body = $ctx->get('/music/songs')['body'];

		assertContains('action="/music/settings"', $body, 'the nav carries the settings form');
		assertContains('name="blind_rating"', $body, 'with the switch in it');
		assertTrue(strpos($body, 'action="/music/colour"') < strpos($body, 'action="/music/language"'), 'colour still precedes language');
		assertTrue(strpos($body, 'action="/music/settings"') < strpos($body, 'action="/music/language"'), 'and the cog sits between them');
	},

	'a new account has other people covered' => function ($ctx) {
		$ctx->ensureLoggedIn('gate_fresh', 'test password', false);

		assertSame(1, gateSettingOf($ctx, 'gate_fresh'), 'the column defaults to on');
		assertContains('name="blind_rating" value="1" checked', $ctx->get('/music/songs')['body'], 'and the box is ticked');
	},

	'turning it off uncovers everything' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$songId = makeSong($ctx, $ctx->makeArtist('Gate Switch Artist'), 'Gate Switch Song');

		$ctx->ensureLoggedIn('gate_switch_other', 'test password', false);
		$ctx->post('/music/ajax/song-rating', ['id' => $songId, 'field' => 'score', 'value' => '8']);
		$ctx->post('/music/ajax/song-rating', ['id' => $songId, 'field' => 'note', 'value' => 'their note']);

		$ctx->ensureLoggedIn('gate_switcher', 'test password', false);
		$otherId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'gate_switch_other'")->fetch()['id'];

		$covered = songsRowFor($ctx->get('/music/songs')['body'], $songId);
		assertContains('songGated', songsFieldCell($covered, "score_{$otherId}"), 'covered while the switch is on');

		assertSame(303, saveGateSetting($ctx, false)['status'], 'turning it off redirects back');
		assertSame(0, gateSettingOf($ctx, 'gate_switcher'), 'and is stored on the account');

		$body = $ctx->get('/music/songs')['body'];
		$open = songsRowFor($body, $songId);

		assertTrue(strpos(songsFieldCell($open, "score_{$otherId}"), 'songGated') === false, 'their score is in the open');
		assertTrue(strpos(songsFieldCell($open, "note_{$otherId}"), 'songGated') === false, 'and so is their note');
		assertContains('songScoreColoured', songsFieldCell($open, "score_{$otherId}"), 'with the colour ramp back on it');
		assertTrue(strpos($body, 'songGateReveal') === false, 'and no pill anywhere on the page');
	},

	'the page tells the browser which way the switch is set' => function ($ctx) {
		$ctx->ensureLoggedIn('gate_reader', 'test password', false);

		assertContains('<script id="songGateSetting" type="application/json">true</script>', $ctx->get('/music/songs')['body'], 'on by default');

		saveGateSetting($ctx, false);

		assertContains('<script id="songGateSetting" type="application/json">false</script>', $ctx->get('/music/songs')['body'], 'and off once turned off');
	},

	'the switch survives logging back in' => function ($ctx) {
		$ctx->ensureLoggedIn('gate_returner', 'test password', false);
		saveGateSetting($ctx, false);

		$ctx->newSession();
		$ctx->ensureLoggedIn('gate_returner', 'test password', false);

		assertContains('<script id="songGateSetting" type="application/json">false</script>', $ctx->get('/music/songs')['body'], 'the account carries it between sessions');
	},

	'logging out does not leave it behind for the next person' => function ($ctx) {
		$ctx->ensureLoggedIn('gate_leaver', 'test password', false);
		saveGateSetting($ctx, false);

		$ctx->postForm('/music/logout', ['csrf_token' => $ctx->csrfTokenFrom('/music/songs')]);
		$ctx->ensureLoggedIn('gate_arriver', 'test password', false);

		assertContains('<script id="songGateSetting" type="application/json">true</script>', $ctx->get('/music/songs')['body'], 'the next account on this browser gets its own');
	},

	'a forged settings post changes nothing' => function ($ctx) {
		$ctx->ensureLoggedIn('gate_forged', 'test password', false);

		$ctx->postForm('/music/settings', ['csrf_token' => 'not the token', 'return' => '/music/songs']);

		assertSame(1, gateSettingOf($ctx, 'gate_forged'), 'the switch is untouched');
	},

];
