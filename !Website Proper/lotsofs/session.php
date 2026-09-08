<?php

// accounts are separate per module, so each gets its own slice of the session
function sessionScope($scope = null) {
	static $active = null;

	if ($scope !== null) {
		$active = $scope;
		startSession();
	}

	return $active;
}

// start a session, only for pages that need one
function startSession() {
	if (session_status() === PHP_SESSION_ACTIVE) {
		return;
	}

	session_set_cookie_params([
		'httponly' => true,
		'samesite' => 'Lax',
		'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
	]);

	session_start();
}

function currentAccountId() {
	$scope = sessionScope();
	return $scope === null ? null : ($_SESSION[$scope]['account_id'] ?? null);
}

function currentAccountName() {
	$scope = sessionScope();
	return $scope === null ? null : ($_SESSION[$scope]['account_name'] ?? null);
}

function logIn($accountId, $accountName) {
	session_regenerate_id(true);

	$_SESSION[sessionScope()] = [
		'account_id' => $accountId,
		'account_name' => $accountName,
	];
}

// signs out of this module only, any other module stays signed in
function logOut() {
	unset($_SESSION[sessionScope()]);
	session_regenerate_id(true);
}

// send a signed out visitor to the login page
function requireLogin($loginPath = null) {
	if (!currentAccountId()) {
		header('Location: ' . ($loginPath ?? '/' . sessionScope() . '/login'), true, 302);
		exit;
	}
}

// the json equivalent, for endpoints that are fetched rather than browsed to
function requireLoginJson($message) {
	if (!currentAccountId()) {
		http_response_code(401);
		echo json_encode(['error' => $message]);
		exit;
	}
}

function csrfToken() {
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

function checkCsrf($token) {
	return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}
