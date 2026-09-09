<?php

require_once __ROOT__ . '/session.php';

// read per request rather than cached in the session, so a promotion takes effect at once
function musicAccount($db) {
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

function musicIsAdmin($db) {
	$account = musicAccount($db);

	return $account ? (bool)$account['is_admin'] : false;
}

function requireMusicAccount($db, $loginPath = '/music/login') {
	if (currentAccountId() && !musicAccount($db)) {
		logOut();
		header('Location: ' . $loginPath, true, 302);
		exit;
	}
}

function requireMusicAdmin($db, $path) {
	if (!musicIsAdmin($db)) {
		header('Location: ' . $path, true, 302);
		exit;
	}
}

function requireMusicAdminJson($db, $message) {
	if (!musicIsAdmin($db)) {
		http_response_code(403);
		echo json_encode(['error' => $message]);
		exit;
	}
}
