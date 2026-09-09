<?php

// this file runs after accounts.php, so first_owner already exists and is the admin

const ADMIN_ENDPOINTS = [
	'/modules/music/ajax/artistAlias.php',
	'/modules/music/ajax/song.php',
	'/modules/music/ajax/songEdit.php',
];

const ADMIN_PAGES = ['/music/add-songs', '/music/invites', '/music/accounts'];

function logInAsViewer($ctx) {
	return $ctx->ensureLoggedIn('test_viewer', 'test password', false);
}

return [

	'the first registered account is an admin' => function ($ctx) {
		$isAdmin = $ctx->db()->query("SELECT is_admin FROM account WHERE account_name = 'first_owner'")->fetch()['is_admin'];
		assertSame(1, (int)$isAdmin, 'first_owner is an admin');
	},

	'an invited account is not an admin' => function ($ctx) {
		$isAdmin = $ctx->db()->query("SELECT is_admin FROM account WHERE account_name = 'invited_friend'")->fetch()['is_admin'];
		assertSame(0, (int)$isAdmin, 'invited_friend is not an admin');
	},

	'a non admin is redirected away from the admin pages' => function ($ctx) {
		logInAsViewer($ctx);

		foreach (ADMIN_PAGES as $path) {
			$response = $ctx->get($path);
			assertSame(302, $response['status'], "GET {$path} as a non admin");
			assertContains('/music/songs', $response['location'], "{$path} redirect target");
		}
	},

	'a non admin is refused by the admin endpoints' => function ($ctx) {
		logInAsViewer($ctx);

		foreach (ADMIN_ENDPOINTS as $path) {
			$response = $ctx->post($path, []);
			assertSame(403, $response['status'], "POST {$path} as a non admin");
			assertTrue(isset($response['json']['error']), "{$path} returns a json error");
		}
	},

	'a non admin cannot rename a song' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$artistId = $ctx->makeArtist('Guarded Owner');
		$ctx->post('/modules/music/ajax/song.php', [['artist_id' => $artistId, 'title' => 'Guarded Title']]);
		$songId = $ctx->songId('Guarded Title');

		logInAsViewer($ctx);
		$ctx->post('/modules/music/ajax/songEdit.php', ['id' => $songId, 'field' => 'title', 'value' => 'Renamed By A Viewer']);

		$stored = $ctx->songTitle($songId);
		assertSame('Guarded Title', $stored, 'the title is untouched');
	},

	'a non admin gets a read only songs page' => function ($ctx) {
		logInAsViewer($ctx);

		$body = $ctx->get('/music/songs')['body'];

		assertTrue(strpos($body, 'data-can-edit') === false, 'no edit flag for title and note editing');

		assertContains('songTitleCell', $body, 'the table still renders');
		assertContains('?sort=title', $body, 'the columns still sort');
		assertContains('songResultCell', $body, 'the result column is there for rating feedback');
		assertContains('songMineCell', $body, 'their own rating column is editable');
	},

	'a non admin sees no links to the admin pages' => function ($ctx) {
		logInAsViewer($ctx);

		$body = $ctx->get('/music/songs')['body'];

		foreach (ADMIN_PAGES as $path) {
			assertTrue(strpos($body, "href=\"{$path}\"") === false, "no nav link to {$path}");
		}
		assertContains('href="/music/songs"', $body, 'the songs link remains');
	},

	'an admin sees the links to the admin pages' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$body = $ctx->get('/music/songs')['body'];

		foreach (ADMIN_PAGES as $path) {
			assertContains("href=\"{$path}\"", $body, "nav links to {$path}");
		}
	},

	'an admin can promote and demote another account' => function ($ctx) {
		logInAsViewer($ctx);
		$ctx->ensureLoggedIn();

		$viewerId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_viewer'")->fetch()['id'];

		$ctx->postForm('/music/accounts', [
			'csrf_token' => $ctx->csrfTokenFrom('/music/accounts'),
			'account_id' => $viewerId,
			'action' => 'promote',
		]);
		$promoted = $ctx->db()->query("SELECT is_admin FROM account WHERE id = {$viewerId}")->fetch()['is_admin'];
		assertSame(1, (int)$promoted, 'promoted');

		$ctx->postForm('/music/accounts', [
			'csrf_token' => $ctx->csrfTokenFrom('/music/accounts'),
			'account_id' => $viewerId,
			'action' => 'demote',
		]);
		$demoted = $ctx->db()->query("SELECT is_admin FROM account WHERE id = {$viewerId}")->fetch()['is_admin'];
		assertSame(0, (int)$demoted, 'demoted');
	},

	'a promotion takes effect without a new login' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$viewerId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_viewer'")->fetch()['id'];

		logInAsViewer($ctx);
		assertSame(302, $ctx->get('/music/add-songs')['status'], 'blocked before promotion');

		// the session is left untouched, only the row changes
		$ctx->db()->exec("UPDATE account SET is_admin = 1 WHERE id = {$viewerId}");
		assertSame(200, $ctx->get('/music/add-songs')['status'], 'allowed straight after promotion');

		$ctx->db()->exec("UPDATE account SET is_admin = 0 WHERE id = {$viewerId}");
		assertSame(302, $ctx->get('/music/add-songs')['status'], 'blocked again once removed');
	},

	'an account cannot change its own admin status' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$adminId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];

		$ctx->postForm('/music/accounts', [
			'csrf_token' => $ctx->csrfTokenFrom('/music/accounts'),
			'account_id' => $adminId,
			'action' => 'demote',
		]);

		$stillAdmin = $ctx->db()->query("SELECT is_admin FROM account WHERE id = {$adminId}")->fetch()['is_admin'];
		assertSame(1, (int)$stillAdmin, 'still an admin');
	},

	'the accounts page offers no button for your own row' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$adminId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetch()['id'];
		$viewerId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_viewer'")->fetch()['id'];

		$body = $ctx->get('/music/accounts')['body'];

		assertContains('test_runner', $body, 'your own account is listed');
		assertContains("name=\"account_id\" value=\"{$viewerId}\"", $body, 'other rows carry a form');
		assertTrue(strpos($body, "name=\"account_id\" value=\"{$adminId}\"") === false, 'no form targets your own row');
	},

	'a session outliving its account is booted' => function ($ctx) {
		$ctx->ensureLoggedIn('ghost_account', 'test password', false);

		assertSame(200, $ctx->get('/music/songs')['status'], 'signed in to begin with');

		$ctx->db()->exec("DELETE FROM account WHERE account_name = 'ghost_account'");

		$response = $ctx->get('/music/songs');
		assertSame(302, $response['status'], 'the deleted account is turned away');
		assertContains('/music/login', $response['location'], 'sent to login');

		// the session is cleared, not merely redirected, so the landing page reads as signed out
		$landing = $ctx->get('/music')['body'];
		assertContains('href="/music/register"', $landing, 'the signed out nav is shown');
		assertTrue(strpos($landing, 'ghost_account') === false, 'the stale name is gone');
	},

	'the accounts page rejects a post without a csrf token' => function ($ctx) {
		$ctx->ensureLoggedIn();

		$viewerId = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_viewer'")->fetch()['id'];

		$ctx->postForm('/music/accounts', ['account_id' => $viewerId, 'action' => 'promote']);

		$isAdmin = $ctx->db()->query("SELECT is_admin FROM account WHERE id = {$viewerId}")->fetch()['is_admin'];
		assertSame(0, (int)$isAdmin, 'nothing changed');
	},

];
