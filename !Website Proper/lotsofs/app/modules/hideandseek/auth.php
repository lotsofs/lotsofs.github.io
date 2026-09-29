<?php

require_once __ROOT__ . '/session.php';

/// The signed-in account row, or null. Read once per request.
function hnsAccount($db) {
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

function hnsIsAdmin($db) {
	$account = hnsAccount($db);

	return $account ? (bool)$account['is_admin'] : false;
}

/// Signs out a session whose account has since been deleted, rather than
/// letting every later query answer for an account that is not there.
function requireHnsAccount($db, $loginPath = '/hideandseek/login') {
	if (currentAccountId() && !hnsAccount($db)) {
		logOut();
		header('Location: ' . $loginPath, true, 302);
		exit;
	}
}

function requireHnsAdmin($db, $path) {
	if (!hnsIsAdmin($db)) {
		header('Location: ' . $path, true, 302);
		exit;
	}
}

function requireHnsAccountJson($db, $message) {
	if (!hnsAccount($db)) {
		http_response_code(403);
		echo json_encode(['error' => $message]);
		exit;
	}
}

function requireHnsAdminJson($db, $message) {
	if (!hnsIsAdmin($db)) {
		http_response_code(403);
		echo json_encode(['error' => $message]);
		exit;
	}
}
