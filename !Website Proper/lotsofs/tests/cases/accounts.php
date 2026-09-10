<?php

function registerAccount($ctx, $fields, $extraHeaders = []) {
	$fields['csrf_token'] = $ctx->csrfTokenFrom('/music/register');
	return $ctx->postForm('/music/register', $fields, false, $extraHeaders);
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

	'a freshly minted invite is easy for a person to read' => function ($ctx) {
		$ctx->newSession();
		logInAs($ctx, 'first_owner', 'correct horse');
		$ctx->postForm('/music/invites', ['csrf_token' => $ctx->csrfTokenFrom('/music/invites')]);

		$code = $ctx->db()->query("SELECT code FROM invite WHERE used_at IS NULL ORDER BY id DESC")->fetch()['code'];

		assertSame(8, strlen($code), 'short enough to read out loud');
		assertSame(1, preg_match('/^[A-Z]{8}$/', $code), 'letters only');

		$shown = $ctx->get('/music/invites')['body'];
		assertContains(substr($code, 0, 4) . '-' . substr($code, 4), $shown, 'displayed in two grouped halves');
	},

	'an invite code is accepted however a person types it back' => function ($ctx) {
		$ctx->newSession();
		logInAs($ctx, 'first_owner', 'correct horse');
		$ctx->db()->exec("INSERT INTO invite (code, created_at) VALUES ('ABCDWXYZ', '2020-01-01T00:00:00+00:00')");

		$ctx->newSession();
		$response = registerAccount($ctx, [
			'invite_code' => ' abcd-wxyz ',
			'account_name' => 'sloppy_typist',
			'password' => 'correct horse',
			'password_confirm' => 'correct horse',
		]);

		assertSame(302, $response['status'], 'lowercase, spaced and dashed all forgiven');

		$invite = $ctx->db()->query("SELECT used_by_account_id FROM invite WHERE code = 'ABCDWXYZ'")->fetch();
		assertTrue($invite['used_by_account_id'] !== null, 'the invite was spent');
	},

	'a wrong invite code is still refused' => function ($ctx) {
		$ctx->newSession();

		$response = registerAccount($ctx, [
			'invite_code' => 'ZZZZ-ZZZZ',
			'account_name' => 'gatecrasher',
			'password' => 'correct horse',
			'password_confirm' => 'correct horse',
		]);

		assertSame(200, $response['status'], 'no redirect');
		assertTrue($ctx->db()->query("SELECT id FROM account WHERE account_name = 'gatecrasher'")->fetch() === false, 'no account created');
	},

	'an unused invite can be invalidated from the invites page' => function ($ctx) {
		$ctx->newSession();
		logInAs($ctx, 'first_owner', 'correct horse');
		$ctx->postForm('/music/invites', ['csrf_token' => $ctx->csrfTokenFrom('/music/invites')]);

		$invite = $ctx->db()->query("SELECT id, code FROM invite WHERE used_at IS NULL AND revoked_at IS NULL ORDER BY id DESC")->fetch();
		assertContains(substr($invite['code'], 0, 4) . '-' . substr($invite['code'], 4), $ctx->get('/music/invites')['body'], 'the code is on the page to begin with');

		$ctx->postForm('/music/invites', [
			'csrf_token' => $ctx->csrfTokenFrom('/music/invites'),
			'action' => 'revoke',
			'invite_id' => $invite['id'],
		]);

		$row = $ctx->db()->query("SELECT used_at, revoked_at FROM invite WHERE id = {$invite['id']}")->fetch();
		assertTrue($row !== false, 'the invite record is kept, not deleted');
		assertTrue($row['revoked_at'] !== null, 'it is marked invalidated');
		assertSame(null, $row['used_at'], 'and never counts as used');

		$body = $ctx->get('/music/invites')['body'];
		assertContains('Invalidated', $body, 'the page shows it as invalidated');
		assertTrue(strpos($body, 'value="' . $invite['id'] . '"') === false, 'and drops its invalidate button');

		$ctx->newSession();
		$rejected = registerAccount($ctx, [
			'invite_code' => $invite['code'],
			'account_name' => 'too_late',
			'password' => 'correct horse',
			'password_confirm' => 'correct horse',
		]);
		assertSame(200, $rejected['status'], 'the invalidated code no longer registers anyone');
	},

	'invalidating leaves a spent invite alone' => function ($ctx) {
		$ctx->newSession();
		logInAs($ctx, 'first_owner', 'correct horse');
		$ctx->db()->exec("INSERT INTO invite (code, created_at, used_at, used_by_account_id) VALUES ('SPENTCOD', '2020-01-01T00:00:00+00:00', '2020-01-02T00:00:00+00:00', 1)");
		$spentId = (int)$ctx->db()->query("SELECT id FROM invite WHERE code = 'SPENTCOD'")->fetch()['id'];

		$ctx->postForm('/music/invites', [
			'csrf_token' => $ctx->csrfTokenFrom('/music/invites'),
			'action' => 'revoke',
			'invite_id' => $spentId,
		]);

		$row = $ctx->db()->query("SELECT revoked_at FROM invite WHERE id = {$spentId}")->fetch();
		assertSame(null, $row['revoked_at'], 'a used invite cannot be invalidated after the fact');

		$body = $ctx->get('/music/invites')['body'];
		assertTrue(strpos($body, 'value="' . $spentId . '"') === false, 'and no invalidate button is offered for it');
	},

	'registration validates its fields' => function ($ctx) {
		$ctx->newSession();
		logInAs($ctx, 'first_owner', 'correct horse');
		$ctx->postForm('/music/invites', ['csrf_token' => $ctx->csrfTokenFrom('/music/invites')]);
		$code = $ctx->db()->query("SELECT code FROM invite WHERE used_at IS NULL ORDER BY id DESC")->fetch()['code'];
		$ctx->newSession();

		$before = (int)$ctx->db()->query("SELECT COUNT(*) c FROM account")->fetch()['c'];

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

		$after = (int)$ctx->db()->query("SELECT COUNT(*) c FROM account")->fetch()['c'];
		assertSame($before, $after, 'no account was created by any of the rejected attempts');
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
