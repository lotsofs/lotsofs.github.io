<?php

function registerAccount($ctx, $fields) {
	$fields['csrf_token'] = $ctx->csrfTokenFrom('/music/register');
	return $ctx->postForm('/music/register', $fields);
}

function logInAs($ctx, $name, $password) {
	return $ctx->postForm('/music/login', [
		'csrf_token' => $ctx->csrfTokenFrom('/music/login'),
		'account_name' => $name,
		'password' => $password,
	]);
}

return [

	'the first account needs no invite' => function ($ctx) {
		$ctx->newSession();

		assertSame(0, (int)$ctx->db()->query("SELECT COUNT(*) c FROM account")->fetch()['c'], 'account table starts empty');

		$response = registerAccount($ctx, [
			'account_name' => 'first_owner',
			'password' => 'correct horse',
			'password_confirm' => 'correct horse',
		]);

		assertSame(302, $response['status'], 'registration redirects on success');

		$account = $ctx->db()->query("SELECT account_name FROM account WHERE account_name = 'first_owner'")->fetch();
		assertTrue($account !== false, 'account row exists');
	},

	'the password is stored as a hash' => function ($ctx) {
		$hash = $ctx->db()->query("SELECT password_hash FROM account WHERE account_name = 'first_owner'")->fetch()['password_hash'];

		assertTrue($hash !== 'correct horse', 'plaintext is not stored');
		assertTrue(password_verify('correct horse', $hash), 'hash verifies against the password');
		assertTrue(!password_verify('wrong horse', $hash), 'hash rejects a wrong password');
	},

	'later accounts cannot register without an invite' => function ($ctx) {
		$ctx->newSession();

		$response = registerAccount($ctx, [
			'account_name' => 'no_invite',
			'password' => 'correct horse',
			'password_confirm' => 'correct horse',
		]);

		assertSame(200, $response['status'], 'form is redisplayed rather than redirecting');
		assertContains('invite code', strtolower($response['body']), 'complains about the invite');

		$account = $ctx->db()->query("SELECT id FROM account WHERE account_name = 'no_invite'")->fetch();
		assertTrue($account === false, 'no account was created');
	},

	'the invites page redirects when signed out' => function ($ctx) {
		$ctx->newSession();

		$response = $ctx->get('/music/invites');
		assertSame(302, $response['status'], 'status');
		assertContains('/music/login', $response['location'], 'redirect target');
	},

	'a signed in account can create an invite' => function ($ctx) {
		$ctx->newSession();
		logInAs($ctx, 'first_owner', 'correct horse');

		$before = (int)$ctx->db()->query("SELECT COUNT(*) c FROM invite")->fetch()['c'];

		$response = $ctx->postForm('/music/invites', ['csrf_token' => $ctx->csrfTokenFrom('/music/invites')]);
		assertSame(302, $response['status'], 'redirects after creating');

		$after = (int)$ctx->db()->query("SELECT COUNT(*) c FROM invite")->fetch()['c'];
		assertSame($before + 1, $after, 'one invite was created');
	},

	'an invite lets a second account register, once' => function ($ctx) {
		$ctx->newSession();
		logInAs($ctx, 'first_owner', 'correct horse');
		$ctx->postForm('/music/invites', ['csrf_token' => $ctx->csrfTokenFrom('/music/invites')]);

		$code = $ctx->db()->query("SELECT code FROM invite WHERE used_at IS NULL ORDER BY id DESC")->fetch()['code'];

		$ctx->newSession();
		$response = registerAccount($ctx, [
			'invite_code' => $code,
			'account_name' => 'invited_friend',
			'password' => 'correct horse',
			'password_confirm' => 'correct horse',
		]);
		assertSame(302, $response['status'], 'registration succeeded');

		$invite = $ctx->db()->query("SELECT used_at, used_by_account_id FROM invite WHERE code = '{$code}'")->fetch();
		assertTrue($invite['used_at'] !== null, 'invite is marked used');
		assertTrue($invite['used_by_account_id'] !== null, 'invite records who used it');

		$ctx->newSession();
		$reuse = registerAccount($ctx, [
			'invite_code' => $code,
			'account_name' => 'late_arrival',
			'password' => 'correct horse',
			'password_confirm' => 'correct horse',
		]);
		assertSame(200, $reuse['status'], 'a spent invite is refused');

		$account = $ctx->db()->query("SELECT id FROM account WHERE account_name = 'late_arrival'")->fetch();
		assertTrue($account === false, 'no account was created from the spent invite');
	},

	'registration validates its fields' => function ($ctx) {
		$ctx->newSession();
		logInAs($ctx, 'first_owner', 'correct horse');
		$ctx->postForm('/music/invites', ['csrf_token' => $ctx->csrfTokenFrom('/music/invites')]);
		$code = $ctx->db()->query("SELECT code FROM invite WHERE used_at IS NULL ORDER BY id DESC")->fetch()['code'];
		$ctx->newSession();

		$tooShort = registerAccount($ctx, [
			'invite_code' => $code,
			'account_name' => 'shorty',
			'password' => 'abc',
			'password_confirm' => 'abc',
		]);
		assertSame(200, $tooShort['status'], 'short password refused');
		assertContains('8 characters', $tooShort['body'], 'explains the length rule');

		$mismatch = registerAccount($ctx, [
			'invite_code' => $code,
			'account_name' => 'mismatcher',
			'password' => 'correct horse',
			'password_confirm' => 'different horse',
		]);
		assertSame(200, $mismatch['status'], 'mismatched confirmation refused');

		$taken = registerAccount($ctx, [
			'invite_code' => $code,
			'account_name' => 'first_owner',
			'password' => 'correct horse',
			'password_confirm' => 'correct horse',
		]);
		assertSame(200, $taken['status'], 'duplicate name refused');

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM account")->fetch()['c'];
		assertSame(2, $count, 'no extra accounts were created');
	},

	'logging in and out works' => function ($ctx) {
		$ctx->newSession();

		$response = logInAs($ctx, 'first_owner', 'correct horse');
		assertSame(302, $response['status'], 'login redirects on success');
		assertContains('Log Out', $ctx->get('/music/songs')['body'], 'nav shows the logged in state');

		$logout = $ctx->postForm('/music/logout', ['csrf_token' => $ctx->csrfTokenFrom('/music/songs')]);
		assertSame(302, $logout['status'], 'logout redirects');
		assertSame(302, $ctx->get('/music/songs')['status'], 'the songs page is gated again');
		assertContains('Log In', $ctx->get('/music/login')['body'], 'nav shows the logged out state');
	},

	'a bad password is refused the same way as an unknown account' => function ($ctx) {
		$ctx->newSession();
		$wrongPassword = logInAs($ctx, 'first_owner', 'not the password');

		$ctx->newSession();
		$unknownAccount = logInAs($ctx, 'nobody_at_all', 'not the password');

		assertSame(200, $wrongPassword['status'], 'wrong password is refused');
		assertSame(200, $unknownAccount['status'], 'unknown account is refused');

		$message = t_testMessage($wrongPassword['body']);
		assertSame($message, t_testMessage($unknownAccount['body']), 'both failures say the same thing');
		assertTrue($message !== '', 'an error message was shown');
	},

	'repeated failures block further attempts from that address' => function ($ctx) {
		$ctx->newSession();
		$ctx->db()->exec("DELETE FROM login_attempt");

		for ($i = 0; $i < 5; $i++) {
			$response = logInAs($ctx, 'first_owner', 'wrong guess');
			assertSame(200, $response['status'], "attempt {$i} refused");
		}

		$blocked = logInAs($ctx, 'first_owner', 'wrong guess');
		assertContains('Too many', t_testMessage($blocked['body']), 'the sixth attempt is rate limited');

		$correct = logInAs($ctx, 'first_owner', 'correct horse');
		assertSame(200, $correct['status'], 'no redirect, so no login');
		assertContains('Too many', t_testMessage($correct['body']), 'the correct password is refused too');
		assertSame(302, $ctx->get('/music/songs')['status'], 'still signed out');

		$ctx->db()->exec("DELETE FROM login_attempt");
	},

	'the block lifts once the window passes' => function ($ctx) {
		$ctx->newSession();
		$ctx->db()->exec("DELETE FROM login_attempt");

		for ($i = 0; $i < 5; $i++) {
			logInAs($ctx, 'first_owner', 'wrong guess');
		}
		assertContains('Too many', t_testMessage(logInAs($ctx, 'first_owner', 'correct horse')['body']), 'blocked to begin with');

		$ctx->db()->exec("UPDATE login_attempt SET attempted_at = attempted_at - 1000");

		$response = logInAs($ctx, 'first_owner', 'correct horse');
		assertSame(302, $response['status'], 'login works again once the attempts age out');

		$ctx->db()->exec("DELETE FROM login_attempt");
	},

	'a successful login clears the count' => function ($ctx) {
		$ctx->newSession();
		$ctx->db()->exec("DELETE FROM login_attempt");

		for ($i = 0; $i < 4; $i++) {
			logInAs($ctx, 'first_owner', 'wrong guess');
		}

		assertSame(302, logInAs($ctx, 'first_owner', 'correct horse')['status'], 'still under the limit');

		$remaining = (int)$ctx->db()->query("SELECT COUNT(*) c FROM login_attempt")->fetch()['c'];
		assertSame(0, $remaining, 'the failures were forgotten on success');
	},

	'a stale form does not count towards the limit' => function ($ctx) {
		$ctx->newSession();
		$ctx->db()->exec("DELETE FROM login_attempt");

		for ($i = 0; $i < 8; $i++) {
			$ctx->postForm('/music/login', ['account_name' => 'first_owner', 'password' => 'wrong guess']);
		}

		$count = (int)$ctx->db()->query("SELECT COUNT(*) c FROM login_attempt")->fetch()['c'];
		assertSame(0, $count, 'csrf rejections are not password guesses');

		assertSame(302, logInAs($ctx, 'first_owner', 'correct horse')['status'], 'login still works');
	},

	'the rate limit message does not reveal whether an account exists' => function ($ctx) {
		$ctx->newSession();
		$ctx->db()->exec("DELETE FROM login_attempt");

		for ($i = 0; $i < 5; $i++) {
			logInAs($ctx, 'first_owner', 'wrong guess');
		}

		$known = t_testMessage(logInAs($ctx, 'first_owner', 'wrong guess')['body']);
		$unknown = t_testMessage(logInAs($ctx, 'nobody_at_all', 'wrong guess')['body']);

		assertSame($known, $unknown, 'both say the same thing while blocked');
		assertTrue($known !== '', 'a message was shown');

		$ctx->db()->exec("DELETE FROM login_attempt");
	},

	'a post without a csrf token is refused' => function ($ctx) {
		$ctx->newSession();

		$response = $ctx->postForm('/music/login', [
			'account_name' => 'first_owner',
			'password' => 'correct horse',
		]);

		assertSame(200, $response['status'], 'not logged in');
		assertTrue(strpos($response['body'], 'expired') !== false, 'reports an expired form');

		assertSame(302, $ctx->get('/music/songs')['status'], 'still signed out');
	},

];

function t_testMessage($body) {
	if (preg_match('/<p class="formError">([^<]*)<\/p>/', $body, $m)) {
		return trim($m[1]);
	}
	return '';
}
