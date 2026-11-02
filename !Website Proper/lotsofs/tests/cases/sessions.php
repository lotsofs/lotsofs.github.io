<?php

require_once __DIR__ . '/../../app/session.php';

/// The session cookie a response leaves behind: of several, a browser keeps the last.
function sessionCookieIn($response) {
	preg_match_all('/^Set-Cookie: PHPSESSID=([^;\r\n]*)([^\r\n]*)/mi', $response['headers'], $matches, PREG_SET_ORDER);

	if (!$matches) {
		return null;
	}

	$last = end($matches);

	return ['id' => $last[1], 'attributes' => $last[2]];
}

function assertKept399Days($cookie, $what) {
	assertTrue($cookie !== null, "{$what}: no session cookie");
	assertTrue(preg_match('/Max-Age=(\d+)/i', $cookie['attributes'], $match) === 1, "{$what}: no Max-Age in " . $cookie['attributes']);
	assertTrue(abs(399 * 86400 - (int)$match[1]) < 60, "{$what}: Max-Age is " . $match[1]);
}

function assertEndsWithBrowser($cookie, $what) {
	assertTrue($cookie !== null, "{$what}: no session cookie");
	assertTrue(stripos($cookie['attributes'], 'expires') === false && stripos($cookie['attributes'], 'max-age') === false, "{$what}: has an expiry: " . $cookie['attributes']);
}

function sessionFile($ctx, $id) {
	return $ctx->path('data/sessions/sess_' . $id);
}

/// Rewrites when the session file says its cookie was last sent; an empty string removes it.
function rewriteCookieSent($ctx, $id, $replacement) {
	$file = sessionFile($ctx, $id);
	$rewritten = preg_replace('/login_cookie_sent\|i:\d+;/', $replacement, file_get_contents($file), -1, $count);

	assertSame(1, $count, 'the session file records when its cookie was sent');
	file_put_contents($file, $rewritten);
}

return [

	'a login outlives the browser' => function ($ctx) {
		assertKept399Days(sessionCookieIn($ctx->ensureLoggedIn('session_login')), 'the login response');
	},

	'registering signs in the same way' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$ctx->postForm('/music/invites', ['csrf_token' => $ctx->csrfTokenFrom('/music/invites')]);
		$code = $ctx->db()->query("SELECT code FROM invite ORDER BY id DESC LIMIT 1")->fetch()['code'];

		$ctx->newSession();
		$response = registerAccount($ctx, [
			'account_name' => 'session_registered',
			'password' => 'test password',
			'password_confirm' => 'test password',
			'invite_code' => $code,
		]);

		assertSame(302, $response['status'], 'the account was made');
		assertKept399Days(sessionCookieIn($response), 'the registration response');
	},

	'an anonymous visit keeps a cookie that ends with the browser' => function ($ctx) {
		$ctx->newSession();

		assertEndsWithBrowser(sessionCookieIn($ctx->get('/music/login')), 'an anonymous visit');
	},

	'the cookie is not sent again on every request' => function ($ctx) {
		$ctx->ensureLoggedIn('session_quiet');

		assertSame(null, sessionCookieIn($ctx->get('/music')), 'a request straight after logging in');
	},

	'a day on, the next visit renews the cookie, once' => function ($ctx) {
		$id = sessionCookieIn($ctx->ensureLoggedIn('session_renewed'))['id'];
		rewriteCookieSent($ctx, $id, 'login_cookie_sent|i:' . (time() - 86460) . ';');

		$cookie = sessionCookieIn($ctx->get('/music'));

		assertKept399Days($cookie, 'the first visit a day on');
		assertSame($id, $cookie['id'], 'renewing keeps the session');
		assertSame(null, sessionCookieIn($ctx->get('/music')), 'the visit after it');
	},

	'a session from before logins lasted is picked up on its next visit' => function ($ctx) {
		$id = sessionCookieIn($ctx->ensureLoggedIn('session_upgraded'))['id'];
		rewriteCookieSent($ctx, $id, '');

		$cookie = sessionCookieIn($ctx->get('/music'));

		assertKept399Days($cookie, 'the first visit');
		assertSame($id, $cookie['id'], 'upgrading keeps the session');
	},

	'logging out ends the session and the long cookie with it' => function ($ctx) {
		$ctx->ensureLoggedIn('session_out');

		$response = $ctx->postForm('/music/logout', ['csrf_token' => $ctx->csrfTokenFrom('/music/songs')]);

		assertEndsWithBrowser(sessionCookieIn($response), 'the logout response');
		assertSame(302, $ctx->get('/music/changes')['status'], 'signed out');
	},

	'a real login stays signed in through the sweep after a long idle' => function ($ctx) {
		$id = sessionCookieIn($ctx->ensureLoggedIn('session_idle'))['id'];
		touch(sessionFile($ctx, $id), time() - 3 * 86400);

		sweepSessions($ctx->path('data/sessions'));

		assertTrue(file_exists(sessionFile($ctx, $id)), 'the session file is still there');
		assertSame(200, $ctx->get('/music/changes')['status'], 'still signed in');
	},

	'the sweep gives a login far longer than an anonymous session' => function ($ctx) {
		$dir = $ctx->path('data/sweep_fixture');
		mkdir($dir);

		$limit = (int)ini_get('session.gc_maxlifetime');
		$anonymous = 'lang|a:0:{}';
		$signedIn = 'music|a:1:{s:10:"account_id";i:3;}';

		$cases = [
			'sess_a' => [$anonymous, $limit - 60, true, 'anonymous, inside the limit'],
			'sess_b' => [$anonymous, $limit + 60, false, 'anonymous, past it'],
			'sess_c' => ['', $limit + 60, false, 'anonymous and empty, past it'],
			'sess_d' => [$signedIn, 398 * 86400, true, 'signed in, idle just under 399 days'],
			'sess_e' => [$signedIn, 399 * 86400 + 60, false, 'signed in, idle over 399 days'],
			'other' => [$anonymous, 399 * 86400 + 60, true, 'not a session file'],
		];

		foreach ($cases as $name => [$contents, $age]) {
			file_put_contents("{$dir}/{$name}", $contents);
			touch("{$dir}/{$name}", time() - $age);
		}

		sweepSessions($dir);

		foreach ($cases as $name => [, , $survives, $what]) {
			assertSame($survives, file_exists("{$dir}/{$name}"), $what);
		}
	},

	'PHP\'s own collector stays off' => function ($ctx) {
		assertContains("ini_set('session.gc_probability', '0')", file_get_contents($ctx->path('app/session.php')), 'left on, it deletes a login idle past its one short limit');
	},

];
