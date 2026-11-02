<?php

const LOGIN_LIFETIME = 399 * 86400;
const LOGIN_COOKIE_RENEWAL = 86400;

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

	if (defined('__DATA__')) {
		$sessionPath = __DATA__ . '/sessions';
		if (!is_dir($sessionPath)) {
			@mkdir($sessionPath, 0700, true);
		}
		if (is_writable($sessionPath)) {
			session_save_path($sessionPath);
			ini_set('session.gc_probability', '0');
			if (random_int(1, 100) === 1) {
				sweepSessions($sessionPath);
			}
		}
	}

	session_set_cookie_params([
		'httponly' => true,
		'samesite' => 'Lax',
		'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
	]);

	session_start();

	if (currentAccountId() && ($_SESSION['login_cookie_sent'] ?? 0) < time() - LOGIN_COOKIE_RENEWAL) {
		sendLoginCookie();
	}
}

/// PHP's collector has one lifetime for every session, and anonymous ones, a
/// file per cookieless bot hit, must not last as long as a login.
function sweepSessions($path) {
	$anonymousBefore = time() - (int)ini_get('session.gc_maxlifetime');
	$signedInBefore = time() - LOGIN_LIFETIME;

	foreach (glob($path . '/sess_*') ?: [] as $file) {
		$touched = @filemtime($file);

		if ($touched === false || $touched >= $anonymousBefore) {
			continue;
		}

		if ($touched < $signedInBefore || strpos((string)@file_get_contents($file), 'account_id') === false) {
			@unlink($file);
		}
	}
}

/// PHP sends the session cookie only when the id changes, so without this its
/// expiry counts from the login rather than the last visit.
function sendLoginCookie() {
	$_SESSION['login_cookie_sent'] = time();

	$cookie = session_get_cookie_params();
	unset($cookie['lifetime']);
	$cookie['expires'] = time() + LOGIN_LIFETIME;

	setcookie(session_name(), session_id(), $cookie);
}

function currentAccountId() {
	$scope = sessionScope();
	return $scope === null ? null : ($_SESSION[$scope]['account_id'] ?? null);
}

function currentAccountName() {
	$scope = sessionScope();
	return $scope === null ? null : ($_SESSION[$scope]['account_name'] ?? null);
}

/// A password hash. The cost drops under PHP's built-in server: the only
/// accounts reachable there are a module's _test database's.
function hashPassword($password) {
	if (php_sapi_name() === 'cli-server') {
		return password_hash($password, PASSWORD_BCRYPT, ['cost' => 5]);
	}

	return password_hash($password, PASSWORD_DEFAULT);
}

function logIn($accountId, $accountName) {
	session_regenerate_id(true);

	$_SESSION[sessionScope()] = [
		'account_id' => $accountId,
		'account_name' => $accountName,
	];

	sendLoginCookie();
}

function logOut() {
	unset($_SESSION[sessionScope()], $_SESSION['login_cookie_sent']);
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
