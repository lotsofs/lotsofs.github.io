<?php

function sessionScope($scope = null) {
	static $active = null;

	if ($scope !== null) {
		$active = $scope;
		startSession();
	}

	return $active;
}

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

function logOut() {
	unset($_SESSION[sessionScope()]);
	session_regenerate_id(true);
}

function requireLogin($loginPath = null) {
	if (!currentAccountId()) {
		header('Location: ' . ($loginPath ?? '/' . sessionScope() . '/login'), true, 302);
		exit;
	}
}

function requireLoginJson($message) {
	if (!currentAccountId()) {
		http_response_code(401);
		echo json_encode(['error' => $message]);
		exit;
	}
}

function requireCsrfJson($message) {
	if (!checkCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
		http_response_code(403);
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
