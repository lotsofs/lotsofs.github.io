<?php

require_once __ROOT__ . '/session.php';

/// The signed-in account row, or null. Read once per request.
function gamesAccount($db) {
	static $account = null;
	static $loaded = false;

	if (!$loaded) {
		$loaded = true;
		$accountId = currentAccountId();
		$account = $accountId
			? ($db->query("SELECT id, account_name, is_admin FROM account WHERE id = ?", [$accountId])->fetch() ?: null)
			: null;
	}

	return $account;
}

function gamesIsAdmin($db) {
	$account = gamesAccount($db);

	return $account ? (bool)$account['is_admin'] : false;
}

/// Signs out a session whose account has since been deleted, rather than
/// letting every later query answer for an account that is not there.
function requireGamesAccount($db, $loginPath = '/games/login') {
	if (currentAccountId() && !gamesAccount($db)) {
		logOut();
		header('Location: ' . $loginPath, true, 302);
		exit;
	}
}

function requireGamesAdmin($db, $path) {
	if (!gamesIsAdmin($db)) {
		header('Location: ' . $path, true, 302);
		exit;
	}
}

function requireGamesAccountJson($db, $message) {
	if (!gamesAccount($db)) {
		http_response_code(403);
		echo json_encode(['error' => $message]);
		exit;
	}
}
