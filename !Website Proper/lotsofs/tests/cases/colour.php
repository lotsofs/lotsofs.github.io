<?php

function pickColour($ctx, $hue, $return = '/music/songs', $csrfFrom = '/music/songs') {
	return $ctx->postForm('/music/colour', [
		'csrf_token' => $ctx->csrfTokenFrom($csrfFrom),
		'hue' => (string)$hue,
		'return' => $return,
	]);
}

function pageHue($ctx, $path = '/music/songs') {
	$body = $ctx->get($path)['body'];

	return preg_match('/<html lang="[a-z]+" style="--hue: (\d+)">/', $body, $match) ? (int)$match[1] : null;
}

function accountRow($ctx, $name) {
	$stmt = $ctx->db()->prepare("SELECT id, hue FROM account WHERE account_name = ?");
	$stmt->execute([$name]);

	return $stmt->fetch();
}

return [

	'an account that never picked a colour gets one from its id' => function ($ctx) {
		$ctx->ensureLoggedIn('hue_untouched');

		$account = accountRow($ctx, 'hue_untouched');
		assertTrue($account['hue'] === null, 'nothing is stored until the account picks something');
		assertSame(((int)$account['id'] * 73) % 360, pageHue($ctx), 'and the page renders in the hue its id gives it');
	},

	'picking a colour saves it and renders every page in it' => function ($ctx) {
		$ctx->ensureLoggedIn('hue_picker');

		assertSame(303, pickColour($ctx, 137)['status'], 'the switch redirects');

		assertSame(137, (int)accountRow($ctx, 'hue_picker')['hue'], 'the account remembers it');
		assertSame(137, pageHue($ctx), 'the song list renders in it');
		assertSame(137, pageHue($ctx, '/music/albums'), 'and so does every other page');
	},

	'the colour survives logging back in' => function ($ctx) {
		$ctx->ensureLoggedIn('hue_returner');
		pickColour($ctx, 12);

		$ctx->newSession();
		$ctx->ensureLoggedIn('hue_returner');

		assertSame(12, pageHue($ctx), 'a fresh session picks it back up from the account');
	},

	'the hue is kept inside the colour wheel' => function ($ctx) {
		$ctx->ensureLoggedIn('hue_bounds');
		pickColour($ctx, 200);

		foreach (['360', '-1', '3600', 'blue', '', '12.5'] as $refused) {
			pickColour($ctx, $refused);
			assertSame(200, (int)accountRow($ctx, 'hue_bounds')['hue'], "a hue of '{$refused}' is refused rather than stored");
		}

		foreach ([0, 359] as $allowed) {
			pickColour($ctx, $allowed);
			assertSame($allowed, (int)accountRow($ctx, 'hue_bounds')['hue'], "{$allowed} is inside the wheel");
		}
	},

	'a forged colour post changes nothing' => function ($ctx) {
		$ctx->ensureLoggedIn('hue_forged');
		pickColour($ctx, 88);

		$ctx->postForm('/music/colour', ['csrf_token' => 'not the token', 'hue' => '300', 'return' => '/music/songs']);

		assertSame(88, (int)accountRow($ctx, 'hue_forged')['hue'], 'the stored hue is untouched');
	},

	'a signed out visitor sees the site blue' => function ($ctx) {
		$ctx->newSession();

		assertSame(240, pageHue($ctx, '/music/login'), 'the login page is the default blue');
	},

	'the picker is in the nav, next to the language switcher' => function ($ctx) {
		$ctx->ensureLoggedIn('hue_nav');
		pickColour($ctx, 77);

		$body = $ctx->get('/music/songs')['body'];

		assertContains('action="/music/colour"', $body, 'the nav carries the colour form');
		assertContains('type="range"', $body, 'with a dial to drag');
		assertContains('min="0" max="359"', $body, 'over the whole wheel');
		assertContains('value="77"', $body, 'starting where this account already is');
		assertTrue(strpos($body, 'action="/music/colour"') < strpos($body, 'action="/music/language"'), 'and it sits beside the language switcher');
	},

	'a rater is graphed in their own colour' => function ($ctx) {
		$ctx->ensureLoggedIn('hue_grapher');
		pickColour($ctx, 19);

		$artistId = $ctx->makeArtist('Hue Graph Artist');
		$ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => 'Hue Graph Song']]);
		$songId = $ctx->songId('Hue Graph Song');

		$albumId = (int)$ctx->post('/music/ajax/album', [[
			'provided_name' => 'Hue Graph Record',
			'album_id' => 'new',
			'og_name' => 'Hue Graph Record',
			'is_actual' => true,
			'artist_id' => $artistId,
			'release_year' => '',
			'tracks' => [['song_id' => $songId, 'position' => 1]],
		]])['json'][0]['album_id'];

		$ctx->post('/music/ajax/song-rating', ['id' => $songId, 'field' => 'score', 'value' => '7']);

		$html = $ctx->post('/music/ajax/album-card', ['album_id' => $albumId])['json']['html'];
		assertContains('fill="hsl(19, 70%, 62%)"', $html, 'the dot takes the hue that rater chose');

		pickColour($ctx, 300);
		$moved = $ctx->post('/music/ajax/album-card', ['album_id' => $albumId])['json']['html'];
		assertContains('fill="hsl(300, 70%, 62%)"', $moved, 'and follows them when they change it');
	},

];
