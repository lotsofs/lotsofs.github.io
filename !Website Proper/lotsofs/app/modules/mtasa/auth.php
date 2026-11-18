<?php

require_once __ROOT__ . '/session.php';

/// The signed-in account row, or null. Read once per request.
function mtasaAccount($db) {
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

function mtasaIsAdmin($db) {
	$account = mtasaAccount($db);

	return $account ? (bool)$account['is_admin'] : false;
}

/// Signs out a session whose account has since been deleted, rather than
/// letting every later query answer for an account that is not there.
function requireMtasaAccount($db, $loginPath = '/mtasa/login') {
	if (currentAccountId() && !mtasaAccount($db)) {
		logOut();
		header('Location: ' . $loginPath, true, 302);
		exit;
	}
}

function requireMtasaAdmin($db, $path) {
	if (!mtasaIsAdmin($db)) {
		header('Location: ' . $path, true, 302);
		exit;
	}
}

function requireMtasaAccountJson($db, $message) {
	if (!mtasaAccount($db)) {
		http_response_code(403);
		echo json_encode(['error' => $message]);
		exit;
	}
}
